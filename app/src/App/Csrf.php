<?php

/**
 * Session-bound CSRF helper for first-party browser requests.
 *
 * @package Krypto
 */
class Krypto_Csrf {

  const SESSION_KEY = 'krypto_csrf_token';
  const FIELD_NAME = 'krypto_csrf_token';
  const HEADER_NAME = 'HTTP_X_CSRF_TOKEN';

  public static function token(){
    self::ensureSession();

    if(!isset($_SESSION[self::SESSION_KEY]) || !is_string($_SESSION[self::SESSION_KEY]) || strlen($_SESSION[self::SESSION_KEY]) < 32){
      $_SESSION[self::SESSION_KEY] = self::generateToken();
    }

    return $_SESSION[self::SESSION_KEY];
  }

  public static function fieldName(){
    return self::FIELD_NAME;
  }

  public static function input(){
    return '<input type="hidden" name="'.self::escape(self::FIELD_NAME).'" value="'.self::escape(self::token()).'">';
  }

  public static function metaTag(){
    return '<meta name="krypto-csrf-token" content="'.self::escape(self::token()).'">';
  }

  public static function queryParameter(){
    return rawurlencode(self::FIELD_NAME).'='.rawurlencode(self::token());
  }

  public static function appendToUrl($url){
    $separator = (strpos($url, '?') === false ? '?' : '&');
    return $url.$separator.self::queryParameter();
  }

  public static function validateRequest($options = []){
    $methods = (isset($options['methods']) && is_array($options['methods']) ? $options['methods'] : ['POST', 'PUT', 'PATCH', 'DELETE']);
    $methods = array_map('strtoupper', $methods);
    $method = self::requestMethod();

    if(!in_array($method, $methods, true)) return true;

    $expected = self::token();
    $actual = self::requestToken();

    if(is_string($actual) && $actual !== '' && hash_equals($expected, $actual)){
      return true;
    }

    if(isset($options['throw']) && $options['throw']){
      throw new Exception('Invalid CSRF token.', 1);
    }

    self::reject();
    return false;
  }

  public static function requestToken(){
    if(isset($_POST[self::FIELD_NAME])) return (string) $_POST[self::FIELD_NAME];
    if(isset($_GET[self::FIELD_NAME])) return (string) $_GET[self::FIELD_NAME];
    if(isset($_SERVER[self::HEADER_NAME])) return (string) $_SERVER[self::HEADER_NAME];
    if(isset($_SERVER['HTTP_X_KRYPTO_CSRF_TOKEN'])) return (string) $_SERVER['HTTP_X_KRYPTO_CSRF_TOKEN'];

    if(function_exists('getallheaders')){
      $headers = getallheaders();
      foreach ($headers as $name => $value) {
        if(strtolower($name) == 'x-csrf-token' || strtolower($name) == 'x-krypto-csrf-token'){
          return (string) $value;
        }
      }
    }

    return null;
  }

  private static function generateToken(){
    return bin2hex(random_bytes(32));
  }

  private static function ensureSession(){
    if(function_exists('session_status') && session_status() === PHP_SESSION_NONE && !headers_sent()){
      session_start();
    }
  }

  private static function requestMethod(){
    return strtoupper(isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : 'GET');
  }

  private static function reject(){
    if(!headers_sent()){
      http_response_code(403);
      header('Content-Type: application/json');
    }

    die(json_encode([
      'error' => 1,
      'msg' => 'Invalid CSRF token.'
    ]));
  }

  private static function escape($value){
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
  }
}

?>
