<?php

if(!function_exists('krypto_path_is_absolute')){
  function krypto_path_is_absolute($path){
    if(!is_string($path) || $path === '') return false;
    if($path[0] === '/' || $path[0] === '\\') return true;
    return preg_match('/^[A-Za-z]:[\/\\\\]/', $path) === 1;
  }
}

if(!function_exists('krypto_trim_path')){
  function krypto_trim_path($path){
    if(!is_string($path)) return '';
    $path = rtrim($path, "/\\");
    return ($path === '' ? '/' : $path);
  }
}

if(!function_exists('krypto_join_path')){
  function krypto_join_path($base, $path = ''){
    $base = krypto_trim_path($base);
    $path = ltrim((string) $path, "/\\");
    if($path === '') return $base;
    return $base.'/'.$path;
  }
}

if(!function_exists('krypto_detect_app_root')){
  function krypto_detect_app_root(){
    $candidates = [];

    if(defined('FILE_PATH')){
      $filePath = krypto_trim_path(FILE_PATH);

      if(krypto_path_is_absolute($filePath)){
        $candidates[] = $filePath;
      }

      if(isset($_SERVER['DOCUMENT_ROOT']) && $_SERVER['DOCUMENT_ROOT'] !== ''){
        $candidates[] = krypto_join_path($_SERVER['DOCUMENT_ROOT'], $filePath);
      }
    }

    $candidates[] = dirname(__DIR__, 2);

    foreach ($candidates as $candidate) {
      if(is_dir($candidate)
        && file_exists(krypto_join_path($candidate, 'vendor/autoload.php'))
        && file_exists(krypto_join_path($candidate, 'app/src/App/App.php'))){
        return krypto_trim_path($candidate);
      }
    }

    return null;
  }
}

if(!function_exists('krypto_normalize_document_root')){
  function krypto_normalize_document_root(){
    if(!defined('FILE_PATH')) return;

    $root = krypto_detect_app_root();
    if(is_null($root)) return;

    if(!defined('KRYPTO_APP_ROOT')) define('KRYPTO_APP_ROOT', $root);

    $filePath = krypto_trim_path(FILE_PATH);
    if(krypto_path_is_absolute($filePath) && $root === $filePath){
      $_SERVER['DOCUMENT_ROOT'] = '';
    }
  }
}

if(!function_exists('krypto_app_path')){
  function krypto_app_path($path = ''){
    $root = (defined('KRYPTO_APP_ROOT') ? KRYPTO_APP_ROOT : krypto_detect_app_root());
    if(is_null($root)) {
      $root = (isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : '').(defined('FILE_PATH') ? FILE_PATH : '');
    }
    return krypto_join_path($root, $path);
  }
}

if(!function_exists('krypto_request_is_https')){
  function krypto_request_is_https(){
    if(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && strtolower((string) $_SERVER['HTTPS']) !== 'off') return true;
    if(isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443') return true;

    foreach (['HTTP_X_FORWARDED_PROTO', 'HTTP_X_FORWARDED_SCHEME', 'REQUEST_SCHEME'] as $key) {
      if(!isset($_SERVER[$key])) continue;
      $value = strtolower(trim(explode(',', (string) $_SERVER[$key])[0]));
      if($value === 'https') return true;
    }

    if(isset($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_SSL']) === 'on') return true;
    return false;
  }
}

if(!function_exists('krypto_bool_env')){
  function krypto_bool_env($key, $default){
    $value = getenv($key);
    if($value === false || $value === '') return $default;
    return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
  }
}

if(!function_exists('krypto_runtime_config_value')){
  function krypto_runtime_config_value($key, $default = ''){
    if(defined($key)) return (string) constant($key);
    if(function_exists('krypto_env_config_value')) return (string) krypto_env_config_value($key, $default);
    $value = getenv($key);
    return ($value === false || $value === '' ? $default : (string) $value);
  }
}

if(!function_exists('krypto_generic_error_message')){
  function krypto_generic_error_message(){
    return 'An internal error occurred. Please try again later.';
  }
}

if(!function_exists('krypto_log_exception')){
  function krypto_log_exception($context, $exception){
    $message = (string) $context;
    if($exception instanceof Throwable) $message .= ': '.$exception->getMessage();
    error_log($message);
  }
}

if(!function_exists('krypto_cron_token')){
  function krypto_cron_token(){
    return krypto_runtime_config_value('KRYPTO_CRON_TOKEN', '');
  }
}

if(!function_exists('krypto_cron_query_parameter')){
  function krypto_cron_query_parameter($url = ''){
    $token = krypto_cron_token();
    if($token === '') return '';
    return (strpos((string) $url, '?') === false ? '?' : '&').'cron_token='.rawurlencode($token);
  }
}

if(!function_exists('krypto_cron_url')){
  function krypto_cron_url($path){
    $url = rtrim((defined('APP_URL') ? APP_URL : ''), '/').'/'.ltrim((string) $path, '/');
    return $url.krypto_cron_query_parameter($url);
  }
}

if(!function_exists('krypto_require_cron_access')){
  function krypto_require_cron_access(){
    if(PHP_SAPI === 'cli') return true;

    $configuredToken = krypto_cron_token();
    $providedToken = '';
    if(isset($_GET['cron_token'])) $providedToken = (string) $_GET['cron_token'];
    elseif(isset($_SERVER['HTTP_X_KRYPTO_CRON_TOKEN'])) $providedToken = (string) $_SERVER['HTTP_X_KRYPTO_CRON_TOKEN'];

    if($configuredToken !== '' && hash_equals($configuredToken, $providedToken)) return true;

    if(!headers_sent()){
      http_response_code(403);
      header('Content-Type: application/json');
    }

    die(json_encode([
      'error' => 1,
      'msg' => 'Forbidden'
    ]));
  }
}

if(!function_exists('krypto_session_cookie_secure')){
  function krypto_session_cookie_secure(){
    $override = getenv('KRYPTO_SESSION_COOKIE_SECURE');
    if($override !== false && $override !== '') return krypto_bool_env('KRYPTO_SESSION_COOKIE_SECURE', true);

    if(defined('APP_URL')){
      $scheme = parse_url(APP_URL, PHP_URL_SCHEME);
      if(is_string($scheme) && strtolower($scheme) === 'https') return true;
    }

    return krypto_request_is_https();
  }
}

if(!function_exists('krypto_session_cookie_options')){
  function krypto_session_cookie_options(){
    $path = ini_get('session.cookie_path');
    if($path === false || $path === '') $path = '/';

    $domain = ini_get('session.cookie_domain');
    if($domain === false) $domain = '';

    return [
      'lifetime' => (int) ini_get('session.cookie_lifetime'),
      'path' => $path,
      'domain' => $domain,
      'secure' => krypto_session_cookie_secure(),
      'httponly' => true,
      'samesite' => 'Lax'
    ];
  }
}

if(!function_exists('krypto_session_configure_cookie')){
  function krypto_session_configure_cookie(){
    if(function_exists('session_status') && session_status() !== PHP_SESSION_NONE) return false;
    if(headers_sent()) return false;

    $options = krypto_session_cookie_options();
    session_set_cookie_params($options);

    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', ($options['secure'] ? '1' : '0'));
    ini_set('session.cookie_samesite', $options['samesite']);
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');

    return true;
  }
}

if(!function_exists('krypto_session_start')){
  function krypto_session_start(){
    if(function_exists('session_status')){
      $status = session_status();
      if($status === PHP_SESSION_ACTIVE) return true;
      if($status === PHP_SESSION_DISABLED) return false;
    }

    krypto_session_configure_cookie();
    if(headers_sent()) return false;

    return session_start();
  }
}

if(!function_exists('krypto_session_regenerate_id')){
  function krypto_session_regenerate_id($deleteOldSession = true){
    if(!function_exists('session_status') || session_status() !== PHP_SESSION_ACTIVE) return false;
    if(headers_sent()) return false;
    return session_regenerate_id($deleteOldSession);
  }
}

krypto_normalize_document_root();

?>
