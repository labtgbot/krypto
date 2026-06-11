<?php

/**
 * Regression coverage for issue #100 (SEC-13): request Host/PHP_SELF must not
 * influence canonical redirects or payment callback URLs.
 */

$root = dirname(__DIR__);
$failures = [];

function sec13_fail($message) {
  global $failures;
  $failures[] = $message;
}

function sec13_assert($condition, $message) {
  if(!$condition) {
    sec13_fail($message);
  }
}

function sec13_read($root, $relativePath) {
  $path = $root.'/'.$relativePath;
  sec13_assert(file_exists($path), 'Missing required file: '.$relativePath);
  if(!file_exists($path)) return '';

  $source = file_get_contents($path);
  sec13_assert($source !== false && trim($source) !== '', 'Cannot read '.$relativePath);
  return (string) $source;
}

$helperPath = $root.'/app/src/App/KryptoUrl.php';
sec13_assert(file_exists($helperPath), 'Canonical URL helper must exist.');

if(file_exists($helperPath)) {
  require_once $helperPath;

  sec13_assert(class_exists('KryptoUrl'), 'Canonical URL helper class must be loadable.');
  if(class_exists('KryptoUrl')) {
    $appUrl = 'https://app.example.test/krypto';
    $spoofedServer = [
      'HTTPS' => 'off',
      'SERVER_PORT' => '80',
      'HTTP_HOST' => 'evil.example.test',
      'SCRIPT_NAME' => '/krypto/dashboard.php',
      'PHP_SELF' => '//evil.example.test/%0d%0aLocation:%20https://evil.example.test',
      'QUERY_STRING' => 'a=1'
    ];

    sec13_assert(
      KryptoUrl::requestMatchesCanonicalHost($spoofedServer, $appUrl) === false,
      'Spoofed Host must be rejected against the canonical APP_URL host.'
    );

    $canonicalServer = $spoofedServer;
    $canonicalServer['HTTP_HOST'] = 'app.example.test:443';
    sec13_assert(
      KryptoUrl::requestMatchesCanonicalHost($canonicalServer, $appUrl) === true,
      'Canonical Host with the default HTTPS port must be accepted.'
    );

    $bracketedDomainServer = $spoofedServer;
    $bracketedDomainServer['HTTP_HOST'] = '[app.example.test]';
    sec13_assert(
      KryptoUrl::requestMatchesCanonicalHost($bracketedDomainServer, $appUrl) === false,
      'Bracketed non-IP Host values must be rejected.'
    );

    $redirectUrl = KryptoUrl::canonicalUrlForRequest($spoofedServer, $appUrl);
    sec13_assert(
      $redirectUrl === 'https://app.example.test/krypto/dashboard.php?a=1',
      'Canonical redirect URL must be built from APP_URL and SCRIPT_NAME, not Host/PHP_SELF. Got '.$redirectUrl
    );
    sec13_assert(
      strpos($redirectUrl, 'evil.example.test') === false,
      'Canonical redirect URL must not contain the spoofed Host or PHP_SELF payload.'
    );

    $callbackUrl = KryptoUrl::paymentCallbackUrl(
      '/app/modules/kr-payment/src/paybear/callback.php',
      ['order_id' => 'order 100'],
      $appUrl
    );
    sec13_assert(
      $callbackUrl === 'https://app.example.test/krypto/app/modules/kr-payment/src/paybear/callback.php?order_id=order%20100',
      'Payment callback URL must be an HTTPS APP_URL-based URL. Got '.$callbackUrl
    );

    try {
      KryptoUrl::paymentCallbackUrl('/callback.php', ['order_id' => '100'], 'http://app.example.test');
      sec13_fail('Payment callback URL must reject plaintext APP_URL.');
    } catch (InvalidArgumentException $e) {
      // Expected.
    } catch (Throwable $e) {
      sec13_fail('Payment callback URL must reject plaintext APP_URL with InvalidArgumentException, got '.get_class($e).': '.$e->getMessage());
    }

    try {
      KryptoUrl::canonicalBaseUrl('https://[app.example.test]');
      sec13_fail('APP_URL validation must reject bracketed non-IP hosts.');
    } catch (InvalidArgumentException $e) {
      // Expected.
    } catch (Throwable $e) {
      sec13_fail('APP_URL validation must reject bracketed non-IP hosts with InvalidArgumentException, got '.get_class($e).': '.$e->getMessage());
    }
  }
}

$appSource = sec13_read($root, 'app/src/App/App.php');
$payBearSource = sec13_read($root, 'app/modules/kr-payment/src/paybear/lib/PayBearOrder.php');

$checkDomainStart = strpos($appSource, 'public function _checkDomain()');
$checkDomainEnd = strpos($appSource, '  /**', $checkDomainStart + 1);
sec13_assert($checkDomainStart !== false && $checkDomainEnd !== false, 'Cannot locate App::_checkDomain source.');
if($checkDomainStart !== false && $checkDomainEnd !== false) {
  $checkDomainSource = substr($appSource, $checkDomainStart, $checkDomainEnd - $checkDomainStart);
  sec13_assert(strpos($checkDomainSource, 'KryptoUrl::requestMatchesCanonicalHost') !== false, 'App::_checkDomain must validate Host against APP_URL.');
  sec13_assert(strpos($checkDomainSource, 'KryptoUrl::canonicalUrlForRequest') !== false, 'App::_checkDomain redirects must be built by the canonical URL helper.');
  sec13_assert(strpos($checkDomainSource, '$_SERVER[\'PHP_SELF\']') === false && strpos($checkDomainSource, '$_SERVER["PHP_SELF"]') === false, 'App::_checkDomain must not use PHP_SELF.');
}

sec13_assert(strpos($payBearSource, '$_SERVER[\'HTTP_HOST\']') === false && strpos($payBearSource, '$_SERVER["HTTP_HOST"]') === false, 'PayBear callback must not use HTTP_HOST.');
sec13_assert(strpos($payBearSource, "'http://'") === false && strpos($payBearSource, '"http://"') === false, 'PayBear callback must not hard-code plaintext http://.');
sec13_assert(strpos($payBearSource, 'KryptoUrl::paymentCallbackUrl') !== false, 'PayBear callback must be built by the canonical URL helper.');

if(count($failures) > 0) {
  fwrite(STDERR, "SEC-13 host-header URL regression test failed:\n");
  foreach ($failures as $failure) {
    fwrite(STDERR, '- '.$failure."\n");
  }
  exit(1);
}

echo "SEC-13 host-header URL regression checks passed.\n";

?>
