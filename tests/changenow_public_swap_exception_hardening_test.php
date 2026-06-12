<?php

/**
 * Regression coverage for SEC-21 / issue #130.
 *
 * The public ChangeNOW JSON endpoint must not expose generic exception messages
 * to anonymous clients, while ChangeNowApiException validation copy remains
 * intentionally user-facing.
 */

$root = dirname(__DIR__);
$publicActionFile = $root.'/app/modules/kr-changenow/src/actions/publicSwap.php';

foreach ([
  $root.'/app/src/bootstrap_paths.php',
  $root.'/app/src/ChangeNow/ChangeNowGuardrails.php',
  $root.'/app/modules/kr-changenow/src/ChangeNowApiException.php',
] as $file) {
  if(!file_exists($file)) {
    throw new Exception('Missing ChangeNOW exception hardening dependency: '.$file);
  }
  require_once $file;
}

if(!file_exists($publicActionFile)) {
  throw new Exception('Missing public ChangeNOW action: '.$publicActionFile);
}

function sec21_assert($condition, $message) {
  if(!$condition) {
    throw new Exception($message);
  }
}

function sec21_assert_same($expected, $actual, $message) {
  if($expected !== $actual) {
    throw new Exception($message.' Expected '.var_export($expected, true).', got '.var_export($actual, true));
  }
}

function sec21_strip_comments($source) {
  $tokens = token_get_all($source);
  $stripped = '';

  foreach ($tokens as $token) {
    if(is_array($token)){
      if($token[0] == T_COMMENT || $token[0] == T_DOC_COMMENT) continue;
      $stripped .= $token[1];
      continue;
    }

    $stripped .= $token;
  }

  return $stripped;
}

function sec21_extract_catch_body($source, $className, $variableName = null) {
  $variablePattern = (is_null($variableName) ? '[A-Za-z_][A-Za-z0-9_]*' : preg_quote($variableName, '/'));
  $pattern = '/catch\s*\(\s*'.preg_quote($className, '/').'\s+\$'.$variablePattern.'\s*\)\s*\{(?P<body>.*?)\n\s*\}/s';
  if(preg_match($pattern, $source, $matches) !== 1) {
    throw new Exception('Missing catch block for '.$className);
  }
  return $matches['body'];
}

function sec21_extract_catch_bodies($source, $className, $variableName = null) {
  $variablePattern = (is_null($variableName) ? '[A-Za-z_][A-Za-z0-9_]*' : preg_quote($variableName, '/'));
  $pattern = '/catch\s*\(\s*'.preg_quote($className, '/').'\s+\$'.$variablePattern.'\s*\)\s*\{(?P<body>.*?)\n\s*\}/s';
  if(preg_match_all($pattern, $source, $matches) < 1) {
    throw new Exception('Missing catch block for '.$className);
  }
  return $matches['body'];
}

$source = file_get_contents($publicActionFile);
sec21_assert($source !== false, 'Cannot read public ChangeNOW action source.');
$sourceWithoutComments = sec21_strip_comments($source);

$apiCatchBody = sec21_extract_catch_body($sourceWithoutComments, 'ChangeNowApiException', 'e');
sec21_assert(strpos($apiCatchBody, 'changenow_public_api_exception_payload') !== false, 'ChangeNowApiException catch should use the provider exception payload helper.');
sec21_assert(strpos($apiCatchBody, '_getUserMessage()') === false, 'ChangeNowApiException catch should keep user-facing mapping inside a testable helper.');

$genericCatchBody = null;
foreach (sec21_extract_catch_bodies($sourceWithoutComments, 'Throwable', 'e') as $catchBody) {
  if(strpos($catchBody, 'changenow_public_generic_exception_payload') !== false) {
    $genericCatchBody = $catchBody;
    break;
  }
}
sec21_assert(!is_null($genericCatchBody), 'Generic public swap catch should use the generic exception payload helper.');
sec21_assert(strpos($genericCatchBody, 'changenow_public_generic_exception_payload') !== false, 'Generic public swap catch should use the generic exception payload helper.');
sec21_assert(strpos($genericCatchBody, '->getMessage()') === false, 'Generic public swap catch must not return raw exception messages.');
sec21_assert(strpos($sourceWithoutComments, 'changenow_public_error(1, $e->getMessage()') === false, 'Raw generic exception messages must not be passed to the public JSON error helper.');

define('KRYPTO_PUBLIC_SWAP_HELPERS_ONLY', true);
require_once $publicActionFile;

sec21_assert(function_exists('changenow_public_api_exception_payload'), 'Provider exception payload helper should be available.');
sec21_assert(function_exists('changenow_public_generic_exception_payload'), 'Generic exception payload helper should be available.');

$providerException = new ChangeNowApiValidationException(
  'Please check the destination address.',
  'Provider validation diagnostics with api_key=provider-secret-token'
);
$providerPayload = changenow_public_api_exception_payload($providerException);

sec21_assert_same(2, $providerPayload['error'], 'Provider validation errors should keep validation error code.');
sec21_assert_same('validation', $providerPayload['type'], 'Provider validation errors should keep provider type.');
sec21_assert_same('Please check the destination address.', $providerPayload['msg'], 'Provider validation user copy should remain public.');
sec21_assert(strpos(json_encode($providerPayload), 'provider-secret-token') === false, 'Provider admin diagnostics must not leak into provider exception payload.');

class ChangeNowPublicSwapExceptionFakeApp {
  public function _getChangeNowLogger($enabled = true) {
    return new ChangeNowLogger($enabled, false);
  }
}

$logFile = sys_get_temp_dir().'/krypto-sec21-public-swap-'.uniqid('', true).'.log';
ini_set('log_errors', '1');
ini_set('error_log', $logFile);

$rawMessage = 'SQLSTATE[42S02]: missing table changenow_transactions_secret; provider url https://api.example.test/v2/exchange?api_key=live-secret-token&address=bc1qrawwallet';
$genericPayload = changenow_public_generic_exception_payload(
  new ChangeNowPublicSwapExceptionFakeApp(),
  new RuntimeException($rawMessage)
);

sec21_assert_same(1, $genericPayload['error'], 'Generic exceptions should use generic error code.');
sec21_assert_same('error', $genericPayload['type'], 'Generic exceptions should keep generic error type.');
sec21_assert_same(krypto_generic_error_message(), $genericPayload['msg'], 'Generic exceptions should return stable public copy.');
sec21_assert(strpos(json_encode($genericPayload), 'SQLSTATE') === false, 'Generic exception SQL details must not leak into JSON payload.');
sec21_assert(strpos(json_encode($genericPayload), 'live-secret-token') === false, 'Generic exception secrets must not leak into JSON payload.');

clearstatcache(true, $logFile);
$logContents = (file_exists($logFile) ? file_get_contents($logFile) : '');
if(file_exists($logFile)) unlink($logFile);

sec21_assert($logContents !== false && trim($logContents) !== '', 'Generic exception details should be written to server logs.');
sec21_assert(strpos($logContents, 'public_swap_failed') !== false, 'Generic exception log should include ChangeNOW public swap event name.');
sec21_assert(strpos($logContents, 'RuntimeException') !== false, 'Generic exception log should include exception class.');
sec21_assert(strpos($logContents, 'SQLSTATE[42S02]') !== false, 'Generic exception log should retain operational diagnostics.');
sec21_assert(strpos($logContents, 'live-secret-token') === false, 'Generic exception log should redact URL API keys.');
sec21_assert(strpos($logContents, 'bc1qrawwallet') === false, 'Generic exception log should redact URL addresses.');
sec21_assert(strpos($logContents, '[redacted]') !== false, 'Generic exception log should show redaction markers for sensitive values.');

echo "ChangeNOW public swap exception hardening checks passed.\n";

?>
