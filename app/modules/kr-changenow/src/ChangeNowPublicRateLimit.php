<?php

/**
 * Public ChangeNOW endpoint rate-limit helpers.
 *
 * The limiter keys are fingerprints, so bucket files never contain raw IPs or
 * raw session keys. Forwarded client IPs are accepted only when REMOTE_ADDR is
 * configured as a trusted proxy through KRYPTO_TRUSTED_PROXIES.
 *
 * @package Krypto
 */
class ChangeNowPublicRateLimit {

  const SESSION_KEY = 'kr_changenow_session_key';

  public static function bucketForAction($action){
    $action = strtolower(trim((string) $action));

    if($action == 'quote' || $action == 'validate') return 'quote';
    if($action == 'create') return 'transaction';
    if($action == 'status') return 'status';
    if($action == 'refund' || $action == 'continue') return 'support_action';

    return null;
  }

  public static function check($action, $server, &$session, $rateLimitConfig, $limiter, $now = null, $trustedProxies = null){
    $bucket = self::bucketForAction($action);
    if(is_null($bucket)){
      return [
        'allowed' => true,
        'bucket' => null,
        'result' => null
      ];
    }

    if(!is_object($limiter) || !method_exists($limiter, 'check')){
      throw new Exception('ChangeNOW public rate limiter is not available.');
    }

    $config = ChangeNowGuardrails::normalizeRateLimitConfig($rateLimitConfig);
    $bucketConfig = $config[$bucket];
    $identities = self::identitiesForRequest($server, $session, $trustedProxies);
    $lastResult = null;

    foreach ($identities as $identity) {
      $result = $limiter->check(
        $bucket,
        $identity['key'],
        $bucketConfig['limit'],
        $bucketConfig['window_seconds'],
        $now
      );
      $lastResult = $result;

      if(!is_array($result) || !array_key_exists('allowed', $result) || $result['allowed'] !== true){
        return [
          'allowed' => false,
          'bucket' => $bucket,
          'identity_type' => $identity['type'],
          'result' => (is_array($result) ? $result : null)
        ];
      }
    }

    return [
      'allowed' => true,
      'bucket' => $bucket,
      'identities_checked' => count($identities),
      'result' => $lastResult
    ];
  }

  public static function identitiesForRequest($server, &$session, $trustedProxies = null){
    if(!is_array($server)) $server = [];
    if(!is_array($session)) $session = [];

    $identities = [];
    $clientIp = self::clientIp($server, $trustedProxies);
    if($clientIp != ''){
      $identities[] = [
        'type' => 'ip',
        'key' => self::fingerprintIdentity('ip', $clientIp)
      ];
    }

    $sessionKey = self::sessionKey($session);
    if($sessionKey != ''){
      $identities[] = [
        'type' => 'session',
        'key' => self::fingerprintIdentity('session', $sessionKey)
      ];
    }

    if(count($identities) == 0){
      $identities[] = [
        'type' => 'anonymous',
        'key' => self::fingerprintIdentity('anonymous', 'global')
      ];
    }

    return $identities;
  }

  public static function clientIp($server, $trustedProxies = null){
    if(!is_array($server)) $server = [];

    $remoteAddr = (array_key_exists('REMOTE_ADDR', $server) ? self::normalizeIp($server['REMOTE_ADDR']) : '');
    if($remoteAddr != '' && self::isTrustedProxy($remoteAddr, $trustedProxies)){
      foreach (self::forwardedIps($server) as $forwardedIp) {
        $forwardedIp = self::normalizeIp($forwardedIp);
        if($forwardedIp != '') return $forwardedIp;
      }
    }

    return $remoteAddr;
  }

  public static function isTrustedProxy($remoteAddr, $trustedProxies = null){
    $remoteAddr = self::normalizeIp($remoteAddr);
    if($remoteAddr == '') return false;

    foreach (self::normalizeTrustedProxies($trustedProxies) as $trustedProxy) {
      if($trustedProxy == '') continue;
      if($trustedProxy == $remoteAddr) return true;
      if(strpos($trustedProxy, '/') !== false && self::ipInCidr($remoteAddr, $trustedProxy)) return true;
    }

    return false;
  }

  private static function sessionKey(&$session){
    if(!is_array($session)) $session = [];

    if(!array_key_exists(self::SESSION_KEY, $session) || trim((string) $session[self::SESSION_KEY]) == ''){
      $session[self::SESSION_KEY] = self::randomToken();
    }

    return trim((string) $session[self::SESSION_KEY]);
  }

  private static function forwardedIps($server){
    $ips = [];

    if(array_key_exists('HTTP_X_FORWARDED_FOR', $server)){
      foreach (explode(',', (string) $server['HTTP_X_FORWARDED_FOR']) as $ip) {
        $ips[] = $ip;
      }
    }

    foreach (['HTTP_X_REAL_IP', 'HTTP_CF_CONNECTING_IP', 'HTTP_TRUE_CLIENT_IP'] as $header) {
      if(array_key_exists($header, $server)) $ips[] = $server[$header];
    }

    if(array_key_exists('HTTP_FORWARDED', $server)){
      foreach (explode(',', (string) $server['HTTP_FORWARDED']) as $forwardedPart) {
        if(preg_match('/(?:^|;)\s*for="?([^";,]+)"?/i', $forwardedPart, $matches)){
          $ips[] = $matches[1];
        }
      }
    }

    return $ips;
  }

  private static function normalizeTrustedProxies($trustedProxies = null){
    if(is_null($trustedProxies)){
      $trustedProxies = '';
      if(defined('KRYPTO_TRUSTED_PROXIES')) $trustedProxies = KRYPTO_TRUSTED_PROXIES;
      elseif(getenv('KRYPTO_TRUSTED_PROXIES') !== false) $trustedProxies = getenv('KRYPTO_TRUSTED_PROXIES');
    }

    if(is_string($trustedProxies)) $trustedProxies = preg_split('/[\s,]+/', $trustedProxies);
    if(!is_array($trustedProxies)) return [];

    $normalized = [];
    foreach ($trustedProxies as $trustedProxy) {
      $trustedProxy = trim((string) $trustedProxy);
      if($trustedProxy != '') $normalized[] = $trustedProxy;
    }

    return $normalized;
  }

  private static function normalizeIp($value){
    $value = trim((string) $value);
    if($value == '') return '';

    $value = trim($value, " \t\n\r\0\x0B[]\"");
    if(filter_var($value, FILTER_VALIDATE_IP)) return $value;

    if(preg_match('/^([0-9.]+):[0-9]+$/', $value, $matches) && filter_var($matches[1], FILTER_VALIDATE_IP)){
      return $matches[1];
    }

    return '';
  }

  private static function ipInCidr($ip, $cidr){
    $parts = explode('/', $cidr, 2);
    if(count($parts) != 2) return false;

    $network = self::normalizeIp($parts[0]);
    $bits = intval($parts[1]);
    $ipPacked = inet_pton($ip);
    $networkPacked = inet_pton($network);

    if($network == '' || $ipPacked === false || $networkPacked === false) return false;
    if(strlen($ipPacked) !== strlen($networkPacked)) return false;

    $maxBits = strlen($ipPacked) * 8;
    if($bits < 0 || $bits > $maxBits) return false;

    $fullBytes = intval(floor($bits / 8));
    $remainingBits = $bits % 8;

    if($fullBytes > 0 && substr($ipPacked, 0, $fullBytes) !== substr($networkPacked, 0, $fullBytes)) return false;
    if($remainingBits == 0) return true;

    $mask = (0xff << (8 - $remainingBits)) & 0xff;
    return ((ord($ipPacked[$fullBytes]) & $mask) === (ord($networkPacked[$fullBytes]) & $mask));
  }

  private static function fingerprintIdentity($type, $value){
    return $type.':'.hash('sha256', (string) $value);
  }

  private static function randomToken(){
    if(function_exists('random_bytes')) return bin2hex(random_bytes(32));
    if(function_exists('openssl_random_pseudo_bytes')) return bin2hex(openssl_random_pseudo_bytes(32));
    return hash('sha256', uniqid('', true).mt_rand());
  }

}

?>
