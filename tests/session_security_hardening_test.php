<?php

/**
 * Regression coverage for issue #92 (SEC-05): session cookies must be hardened
 * before the session starts, and successful authentication must regenerate the
 * session id before persisting the logged-in user.
 */

$root = dirname(__DIR__);

function assert_session_security($condition, $message) {
    if (!$condition) {
        throw new Exception($message);
    }
}

function session_security_php_files($target) {
    if (is_file($target)) {
        return [$target];
    }

    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && substr($file->getFilename(), -4) === '.php') {
            $files[] = $file->getPathname();
        }
    }

    return $files;
}

if (!defined('APP_URL')) define('APP_URL', 'https://example.test');

require_once $root.'/app/src/bootstrap_paths.php';

assert_session_security(function_exists('krypto_session_start'), 'Bootstrap must expose krypto_session_start().');
assert_session_security(function_exists('krypto_session_cookie_options'), 'Bootstrap must expose hardened session cookie options.');
assert_session_security(function_exists('krypto_session_regenerate_id'), 'Bootstrap must expose krypto_session_regenerate_id().');

$options = krypto_session_cookie_options();
assert_session_security(isset($options['httponly']) && $options['httponly'] === true, 'Session cookie must be HttpOnly.');
assert_session_security(isset($options['secure']) && $options['secure'] === true, 'HTTPS deployments must mark the session cookie Secure.');
assert_session_security(isset($options['samesite']) && $options['samesite'] === 'Lax', 'Session cookie must use SameSite=Lax.');

$bootstrapSource = file_get_contents($root.'/app/src/bootstrap_paths.php');
assert_session_security($bootstrapSource !== false, 'Cannot read bootstrap_paths.php.');
assert_session_security(strpos($bootstrapSource, 'session_set_cookie_params($options)') !== false, 'Bootstrap must apply session cookie options before start.');
assert_session_security(strpos($bootstrapSource, "ini_set('session.cookie_httponly', '1')") !== false, 'Bootstrap must force session.cookie_httponly.');
assert_session_security(strpos($bootstrapSource, "ini_set('session.cookie_samesite', \$options['samesite'])") !== false, 'Bootstrap must force session.cookie_samesite.');
assert_session_security(strpos($bootstrapSource, "ini_set('session.use_strict_mode', '1')") !== false, 'Bootstrap must enable strict session ids.');
assert_session_security(
    strpos($bootstrapSource, 'session_set_cookie_params($options)') < strpos($bootstrapSource, 'session_start()'),
    'Session cookie options must be configured before session_start().'
);

assert_session_security(krypto_session_configure_cookie(), 'Session cookie configuration should run before any test session starts.');
assert_session_security(ini_get('session.cookie_httponly') === '1', 'Runtime session.cookie_httponly must be enabled.');
assert_session_security(ini_get('session.cookie_secure') === '1', 'Runtime session.cookie_secure must be enabled for HTTPS APP_URL.');
assert_session_security(ini_get('session.cookie_samesite') === 'Lax', 'Runtime session.cookie_samesite must be Lax.');
assert_session_security(ini_get('session.use_strict_mode') === '1', 'Runtime strict mode must be enabled.');

$scanTargets = [
    $root.'/index.php',
    $root.'/dashboard.php',
    $root.'/install',
    $root.'/app',
];

foreach ($scanTargets as $target) {
    foreach (session_security_php_files($target) as $path) {
        $relative = str_replace($root.'/', '', $path);
        if ($relative === 'app/src/bootstrap_paths.php') {
            continue;
        }

        $source = file_get_contents($path);
        assert_session_security($source !== false, 'Cannot read '.$relative);
        assert_session_security(
            preg_match('/(?<![A-Za-z0-9_])session_start\s*\(/', $source) !== 1,
            $relative.' must use krypto_session_start() instead of raw session_start().'
        );

        $sessionStartPos = strpos($source, 'krypto_session_start()');
        if ($sessionStartPos === false || strpos($source, 'config/config.settings.php') === false) {
            continue;
        }

        assert_session_security(
            strpos($source, 'config/config.settings.php') < $sessionStartPos,
            $relative.' must load config.settings.php before krypto_session_start().'
        );
    }
}

$userSource = file_get_contents($root.'/app/src/User/User.php');
assert_session_security($userSource !== false, 'Cannot read User.php.');
$tfsCheckPos = strpos($userSource, 'if(!is_null($tfscode) && count($authentificatorSet) > 0)');
$regeneratePos = strpos($userSource, 'krypto_session_regenerate_id(true)');
$loginSessionPos = strpos($userSource, '$_SESSION[\'kr_login\'] = json_encode($r[0]);');

assert_session_security($tfsCheckPos !== false, 'User login must keep the Google TFS verification branch.');
assert_session_security($regeneratePos !== false, 'Successful login must call krypto_session_regenerate_id(true).');
assert_session_security($loginSessionPos !== false, 'Successful login must write kr_login to the session.');
assert_session_security($tfsCheckPos < $regeneratePos, 'Session id regeneration must happen after successful Google TFS verification.');
assert_session_security($regeneratePos < $loginSessionPos, 'Session id regeneration must happen before kr_login is written.');

echo "Session security hardening checks passed.\n";

?>
