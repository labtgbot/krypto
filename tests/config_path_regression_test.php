<?php

/**
 * Regression test for issue #45: dynamic login/admin endpoints built paths as
 * $_SERVER['DOCUMENT_ROOT'].FILE_PATH. Some hosts report a document root that
 * differs from the installed app path, so the installer may save FILE_PATH as
 * an absolute path. Concatenating both produced duplicated paths and broke
 * vendor/autoload.php on the login view.
 */

$root = dirname(__DIR__);

function assert_config_path($condition, $message) {
  if(!$condition) {
    fwrite(STDERR, "FAIL: ".$message."\n");
    exit(1);
  }
}

$_SERVER['DOCUMENT_ROOT'] = '/tmp/krypto-wrong-document-root';

define('APP_URL', 'https://example.test/krypto');
define('APP_URL_FORCE', false);
define('FILE_PATH', $root);

require $root.'/config/config.settings.php';

$legacyResolvedAutoload = $_SERVER['DOCUMENT_ROOT'].FILE_PATH.'/vendor/autoload.php';

assert_config_path(file_exists($legacyResolvedAutoload), 'Absolute FILE_PATH must still resolve vendor/autoload.php through legacy concatenation');
assert_config_path(function_exists('krypto_app_path'), 'Config bootstrap should expose krypto_app_path()');
assert_config_path(krypto_app_path('/vendor/autoload.php') === $root.'/vendor/autoload.php', 'krypto_app_path() should resolve paths from the application root');

$installerSource = file_get_contents($root.'/install/app/src/Install.php');
assert_config_path(strpos($installerSource, 'bootstrap_paths.php') !== false, 'Installer-generated config should load the path bootstrap for future installs');

$scanTargets = [
  $root.'/index.php',
  $root.'/dashboard.php',
  $root.'/app',
];

foreach ($scanTargets as $target) {
  $files = is_dir($target)
    ? new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target))
    : [$target];

  foreach ($files as $file) {
    $path = is_string($file) ? $file : $file->getPathname();
    if(substr($path, -4) !== '.php') continue;

    $source = file_get_contents($path);
    if(!preg_match('/require(?:_once)?\s+["\'][^"\']*config\/config\.settings\.php["\']\s*;/', $source)) continue;

    $relativePath = substr($path, strlen($root) + 1);
    assert_config_path(
      preg_match('/require_once\s+["\'][^"\']*app\/src\/bootstrap_paths\.php["\']\s*;/', $source) === 1,
      $relativePath.' should load bootstrap_paths.php after config.settings.php for existing installed configs'
    );
  }
}

echo "Config path regression check passed\n";

?>
