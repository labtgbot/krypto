<?php

/**
 * Regression coverage for issue #95 (SEC-08): the installer must generate the
 * master encryption key with a CSPRNG, refuse to run after configuration is
 * already populated, and protect installer forms/AJAX endpoints with CSRF.
 */

$root = dirname(__DIR__);

function assert_installer_security($condition, $message) {
  if(!$condition) {
    fwrite(STDERR, 'FAIL: '.$message."\n");
    exit(1);
  }
}

function assert_installer_contains($source, $needle, $message) {
  assert_installer_security(strpos($source, $needle) !== false, $message.' Missing: '.$needle);
}

function assert_installer_not_contains($source, $needle, $message) {
  assert_installer_security(strpos($source, $needle) === false, $message.' Forbidden: '.$needle);
}

function installer_security_temp_root() {
  $path = sys_get_temp_dir().'/krypto-installer-security-'.bin2hex(random_bytes(8));
  if(!mkdir($path.'/config', 0700, true)) {
    throw new Exception('Unable to create temp installer root.');
  }
  return $path;
}

function installer_security_remove_temp_root($path) {
  if(!is_dir($path)) return;
  $items = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
  );
  foreach ($items as $item) {
    if($item->isDir()) {
      rmdir($item->getPathname());
    } else {
      unlink($item->getPathname());
    }
  }
  rmdir($path);
}

$installSource = file_get_contents($root.'/install/app/src/Install.php');
$indexSource = file_get_contents($root.'/install/index.php');
$checkSqlSource = file_get_contents($root.'/install/app/src/actions/checkSQL.php');

assert_installer_security($installSource !== false && trim($installSource) !== '', 'Cannot read Install.php.');
assert_installer_security($indexSource !== false && trim($indexSource) !== '', 'Cannot read install/index.php.');
assert_installer_security($checkSqlSource !== false && trim($checkSqlSource) !== '', 'Cannot read installer checkSQL.php.');

assert_installer_contains($installSource, 'bin2hex(random_bytes(32))', 'CRYPTED_KEY must be generated from 32 CSPRNG bytes.');
assert_installer_not_contains($installSource, 'rand(', 'Installer key generation must not use rand().');
assert_installer_contains($installSource, '_isInstalled()', 'Installer must expose an installation lock check.');
assert_installer_contains($installSource, 'KRYPTO_INSTALLED', 'Generated config must include an explicit installed flag.');
assert_installer_contains($installSource, 'Krypto_Csrf::validateRequest()', 'Installer POST flow must validate CSRF tokens.');

assert_installer_contains($indexSource, '$Install->_isInstalled()', 'Installer entrypoint must block already-installed systems.');
assert_installer_contains($indexSource, 'http_response_code(403)', 'Installer lockout must return a forbidden response.');
assert_installer_contains($indexSource, 'Krypto_Csrf::input()', 'Installer forms must include a CSRF field.');

assert_installer_contains($checkSqlSource, '$Install->_isInstalled()', 'Installer SQL check endpoint must respect lockout.');
assert_installer_contains($checkSqlSource, 'Krypto_Csrf::validateRequest()', 'Installer SQL check endpoint must validate CSRF tokens.');

require_once $root.'/install/app/src/Install.php';

$install = new Install($root);
$method = new ReflectionMethod('Install', 'generateScretkey');
$method->setAccessible(true);
$firstKey = $method->invoke($install);
$secondKey = $method->invoke($install);

assert_installer_security(preg_match('/^[a-f0-9]{64}$/', $firstKey) === 1, 'CRYPTED_KEY must be a 64-character lowercase hex string.');
assert_installer_security($firstKey !== $secondKey, 'Two generated CRYPTED_KEY values must not repeat in normal operation.');
assert_installer_security(!$install->_isInstalled(), 'Repository template config must not be treated as an installed system.');

$tempRoot = installer_security_temp_root();
try {
  file_put_contents(
    $tempRoot.'/config/config.settings.php',
    "<?php\n".
    "define('APP_URL', 'https://example.test');\n".
    "define('APP_URL_FORCE', false);\n".
    "define('FILE_PATH', '/var/www/krypto');\n".
    "define('MYSQL_HOST', 'localhost');\n".
    "define('MYSQL_USER', 'krypto');\n".
    "define('MYSQL_PASSWD', '');\n".
    "define('MYSQL_PORT', '3306');\n".
    "define('MYSQL_DATABASE', 'krypto');\n".
    "define('CRYPTED_KEY', 'legacy-installed-key');\n"
  );

  $installed = new Install($tempRoot);
  assert_installer_security($installed->_isInstalled(), 'Filled legacy config must lock the installer even without KRYPTO_INSTALLED.');

  $_POST = ['admin_email' => 'attacker@example.test'];
  $_SESSION = [];
  $postResult = $installed->_post('admin');
  assert_installer_security($postResult !== true, 'Installer POST must be rejected once config is populated.');
  assert_installer_security(!isset($_SESSION['admin']), 'Rejected installer POST must not persist attacker-controlled session state.');
} finally {
  $_POST = [];
  $_SESSION = [];
  installer_security_remove_temp_root($tempRoot);
}

$flagRoot = installer_security_temp_root();
try {
  file_put_contents(
    $flagRoot.'/config/config.settings.php',
    "<?php\n".
    "define('KRYPTO_INSTALLED', true);\n"
  );

  $installedByFlag = new Install($flagRoot);
  assert_installer_security($installedByFlag->_isInstalled(), 'KRYPTO_INSTALLED must lock the installer.');
} finally {
  installer_security_remove_temp_root($flagRoot);
}

echo "Installer security regression checks passed.\n";

?>
