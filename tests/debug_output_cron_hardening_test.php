<?php

/**
 * Regression coverage for issue #106 (SEC-19): production paths must not leak
 * debug output or raw exception messages, and cron endpoints must reject
 * anonymous HTTP access.
 */

$root = dirname(__DIR__);

function assert_sec19($condition, $message) {
  if(!$condition) {
    throw new Exception($message);
  }
}

function sec19_php_files($root, array $relativeRoots) {
  $files = [];

  foreach ($relativeRoots as $relativeRoot) {
    $path = $root.'/'.$relativeRoot;
    if(!file_exists($path)) continue;

    if(is_file($path)) {
      if(substr($path, -4) === '.php') $files[] = $path;
      continue;
    }

    $iterator = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
      if($file->isFile() && substr($file->getFilename(), -4) === '.php') {
        $files[] = $file->getPathname();
      }
    }
  }

  sort($files);
  return $files;
}

function sec19_strip_comments($source) {
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

$productionFiles = sec19_php_files($root, [
  'app',
  'install/app/src',
  'dashboard.php',
]);

assert_sec19(count($productionFiles) > 0, 'No production PHP files found for SEC-19 scan.');

$forbiddenPatterns = [
  '/\bvar_dump\s*\(/' => 'Production code must not call var_dump().',
  '/\bdie\s*\(\s*(?:[\'"]Error\s*:\s*[\'"]\s*\.\s*)?\$[A-Za-z_][A-Za-z0-9_]*->getMessage\s*\(/' => 'Production code must not die() with raw exception messages.',
  '/\bbase64_encode\s*\(\s*\$[A-Za-z_][A-Za-z0-9_]*->getMessage\s*\(/' => 'Production code must not redirect raw exception messages.',
];

$failures = [];

foreach ($productionFiles as $file) {
  $source = file_get_contents($file);
  assert_sec19($source !== false, 'Cannot read '.$file);
  $source = sec19_strip_comments($source);
  $relative = str_replace($root.'/', '', $file);

  foreach ($forbiddenPatterns as $pattern => $message) {
    if(preg_match($pattern, $source) === 1) {
      $failures[] = $relative.': '.$message;
    }
  }
}

$cronEndpoints = [
  'app/modules/kr-chat/src/actions/clearCron.php',
  'app/modules/kr-changenow/src/actions/syncMarketData.php',
  'app/modules/kr-user/src/actions/cronDemo.php',
  'app/src/App/actions/cronCleanCache.php',
  'app/src/CryptoApi/actions/CheckNotification.php',
  'app/src/CryptoApi/actions/SyncCoin.php',
  'app/src/CryptoApi/actions/SyncExchanges.php',
];

foreach ($cronEndpoints as $relative) {
  $path = $root.'/'.$relative;
  assert_sec19(file_exists($path), 'Cron endpoint is missing: '.$relative);
  $source = file_get_contents($path);
  assert_sec19($source !== false, 'Cannot read cron endpoint: '.$relative);
  $source = sec19_strip_comments($source);
  if(strpos($source, 'krypto_require_cron_access();') === false) {
    $failures[] = $relative.': Cron endpoint must call krypto_require_cron_access().';
  }
}

$targetedGenericErrorFiles = [
  'app/modules/kr-chat/src/actions/clearCron.php',
  'app/modules/kr-changenow/src/actions/syncMarketData.php',
  'app/modules/kr-user/src/actions/cronDemo.php',
  'install/app/src/Install.php',
];

foreach ($targetedGenericErrorFiles as $relative) {
  $source = file_get_contents($root.'/'.$relative);
  assert_sec19($source !== false, 'Cannot read targeted SEC-19 file: '.$relative);
  $source = sec19_strip_comments($source);
  if(strpos($source, '->getMessage()') !== false) {
    $failures[] = $relative.': SEC-19 target must log raw exceptions and return a generic message.';
  }
}

$bootstrap = file_get_contents($root.'/app/src/bootstrap_paths.php');
assert_sec19($bootstrap !== false, 'Cannot read bootstrap_paths.php.');
foreach ([
  'function krypto_require_cron_access',
  'PHP_SAPI === \'cli\'',
  'KRYPTO_CRON_TOKEN',
  'hash_equals',
  'http_response_code(403)',
] as $needle) {
  if(strpos($bootstrap, $needle) === false) {
    $failures[] = 'app/src/bootstrap_paths.php: Cron guard helper missing '.$needle;
  }
}

if(count($failures) > 0) {
  throw new Exception("SEC-19 hardening regression failures:\n- ".implode("\n- ", $failures));
}

echo "Debug-output and cron hardening checks passed.\n";

?>
