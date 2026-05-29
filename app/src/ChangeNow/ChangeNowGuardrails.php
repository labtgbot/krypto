<?php

/**
 * ChangeNOW security, compliance, and observability helpers.
 *
 * These classes are dependency-free so public swap endpoints can use them
 * before the full ChangeNOW module stack is loaded.
 */

class ChangeNowGuardrails {

  public static function createRequestId($prefix = 'cnreq'){
    return $prefix.'_'.self::randomHex(12);
  }

  public static function defaultRateLimits(){
    return [
      'quote' => [
        'limit' => 30,
        'window_seconds' => 60
      ],
      'transaction' => [
        'limit' => 6,
        'window_seconds' => 60
      ]
    ];
  }

  public static function normalizeRateLimitConfig($config){
    if(is_string($config)){
      $decoded = json_decode($config, true);
      $config = (is_array($decoded) ? $decoded : []);
    }

    if(!is_array($config)) $config = [];

    $defaults = self::defaultRateLimits();
    $normalized = [];

    foreach ($defaults as $bucket => $bucketDefaults) {
      $bucketConfig = (array_key_exists($bucket, $config) && is_array($config[$bucket]) ? $config[$bucket] : []);
      $limit = (array_key_exists('limit', $bucketConfig) ? intval($bucketConfig['limit']) : $bucketDefaults['limit']);
      $windowSeconds = (array_key_exists('window_seconds', $bucketConfig) ? intval($bucketConfig['window_seconds']) : $bucketDefaults['window_seconds']);

      $normalized[$bucket] = [
        'limit' => max(1, $limit),
        'window_seconds' => max(1, $windowSeconds)
      ];
    }

    return $normalized;
  }

  public static function defaultComplianceCopy(){
    return ChangeNowEligibility::defaultCopy();
  }

  public static function messages(){
    return self::defaultComplianceCopy();
  }

  public static function mergeComplianceCopy($copy){
    if(is_string($copy)){
      $decoded = json_decode($copy, true);
      $copy = (is_array($decoded) ? $decoded : []);
    }

    if(!is_array($copy)) $copy = [];

    $merged = self::defaultComplianceCopy();
    foreach ($copy as $key => $value) {
      if(array_key_exists($key, $merged) && is_string($value) && strlen(trim($value)) > 0) {
        $merged[$key] = trim($value);
      }
    }

    return $merged;
  }

  private static function randomHex($bytes){
    if(function_exists('random_bytes')){
      return bin2hex(random_bytes($bytes));
    }

    if(function_exists('openssl_random_pseudo_bytes')){
      return bin2hex(openssl_random_pseudo_bytes($bytes));
    }

    return sha1(uniqid('', true).mt_rand());
  }

}

class ChangeNowRedactor {

  public static function redact($value, $key = null){
    if(is_array($value)){
      if(self::isFullySensitiveKey($key)) return '[redacted]';
      if(self::isAddressKey($key)) return self::fingerprintAddress(json_encode($value));

      $redacted = [];
      foreach ($value as $nestedKey => $nestedValue) {
        $redacted[$nestedKey] = self::redact($nestedValue, $nestedKey);
      }
      return $redacted;
    }

    if(is_object($value)){
      return self::redact(get_object_vars($value), $key);
    }

    if(is_null($value) || is_bool($value) || is_int($value) || is_float($value)){
      return $value;
    }

    if(self::isFullySensitiveKey($key)) return '[redacted]';
    if(self::isAddressKey($key)) return self::fingerprintAddress($value);

    return self::redactString($value);
  }

  public static function fingerprintAddress($address){
    $address = trim((string) $address);
    if(strlen($address) == 0) return '';
    return 'address#'.substr(hash('sha256', $address), 0, 12);
  }

  private static function redactString($value){
    $value = (string) $value;
    $value = preg_replace('/(Bearer\s+)[A-Za-z0-9._~+\/=-]+/i', '$1[redacted]', $value);
    $value = preg_replace('/([?&](?:api[_-]?key|apikey|token|secret|private[_-]?key|memo|payload|address|refund[_-]?address|destination[_-]?address)=)[^&\s]+/i', '$1[redacted]', $value);
    return $value;
  }

  private static function isFullySensitiveKey($key){
    if(is_null($key)) return false;
    $normalized = self::normalizeKey($key);

    if(in_array($normalized, [
      'apikey',
      'authorization',
      'authtoken',
      'clientsecret',
      'extraid',
      'extramemo',
      'memo',
      'password',
      'payload',
      'privatekey',
      'secret',
      'secretkey',
      'token'
    ])) {
      return true;
    }

    return (
      strpos($normalized, 'apikey') !== false ||
      strpos($normalized, 'authorization') !== false ||
      strpos($normalized, 'password') !== false ||
      strpos($normalized, 'privatekey') !== false ||
      strpos($normalized, 'secret') !== false ||
      strpos($normalized, 'token') !== false
    );
  }

  private static function isAddressKey($key){
    if(is_null($key)) return false;
    $normalized = self::normalizeKey($key);

    if(in_array($normalized, [
      'address',
      'destinationaddress',
      'payinaddress',
      'payoutaddress',
      'refundaddress',
      'recipientaddress',
      'walletaddress'
    ])) {
      return true;
    }

    if(strpos($normalized, 'address') !== false && !in_array($normalized, [
      'ipaddress',
      'emailaddress',
      'supportaddress'
    ])) {
      return true;
    }

    return false;
  }

  private static function normalizeKey($key){
    return strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $key));
  }

}

class ChangeNowLogger {

  private $enabled = true;
  private $debugEnabled = false;

  public function __construct($enabled = true, $debugEnabled = false){
    $this->enabled = $enabled;
    $this->debugEnabled = $debugEnabled;
  }

  public function buildEntry($level, $event, $context = [], $requestId = null, $providerTransactionId = null){
    if(is_null($requestId) || strlen($requestId) == 0) $requestId = ChangeNowGuardrails::createRequestId();

    return [
      'service' => 'changenow',
      'level' => strtoupper($level),
      'event' => (string) $event,
      'request_id' => $requestId,
      'provider_transaction_id' => $providerTransactionId,
      'context' => ChangeNowRedactor::redact($context),
      'created_at' => gmdate('c')
    ];
  }

  public function log($level, $event, $context = [], $requestId = null, $providerTransactionId = null){
    $entry = $this->buildEntry($level, $event, $context, $requestId, $providerTransactionId);

    if(!$this->enabled) return $entry;
    if(strtoupper($level) == 'DEBUG' && !$this->debugEnabled) return $entry;

    error_log('[changenow] '.json_encode($entry));
    return $entry;
  }

  public function info($event, $context = [], $requestId = null, $providerTransactionId = null){
    return $this->log('INFO', $event, $context, $requestId, $providerTransactionId);
  }

  public function warning($event, $context = [], $requestId = null, $providerTransactionId = null){
    return $this->log('WARNING', $event, $context, $requestId, $providerTransactionId);
  }

  public function debug($event, $context = [], $requestId = null, $providerTransactionId = null){
    return $this->log('DEBUG', $event, $context, $requestId, $providerTransactionId);
  }

}

class ChangeNowRateLimiter {

  private $storagePath = null;

  public function __construct($storagePath = null){
    $this->storagePath = (is_null($storagePath) ? sys_get_temp_dir().'/krypto-changenow-rate-limit' : $storagePath);
  }

  public function check($bucket, $identity, $limit, $windowSeconds, $now = null){
    $limit = intval($limit);
    $windowSeconds = intval($windowSeconds);
    $now = (is_null($now) ? time() : intval($now));

    if($limit < 1 || $windowSeconds < 1){
      return [
        'allowed' => false,
        'limit' => $limit,
        'remaining' => 0,
        'retry_after' => $windowSeconds,
        'reset_at' => $now + max(1, $windowSeconds),
        'window_seconds' => $windowSeconds
      ];
    }

    $this->ensureStoragePath();

    $windowStart = floor($now / $windowSeconds) * $windowSeconds;
    $filePath = $this->filePath($bucket, $identity);
    $handle = fopen($filePath, 'c+');

    if(!$handle) throw new Exception('Unable to open ChangeNOW rate-limit bucket.');

    flock($handle, LOCK_EX);

    $contents = stream_get_contents($handle);
    $state = json_decode($contents, true);
    if(!is_array($state) || !array_key_exists('window_start', $state) || intval($state['window_start']) !== intval($windowStart)){
      $state = [
        'window_start' => $windowStart,
        'count' => 0
      ];
    }

    $allowed = intval($state['count']) < $limit;
    if($allowed) $state['count'] = intval($state['count']) + 1;

    $remaining = max(0, $limit - intval($state['count']));
    $resetAt = $windowStart + $windowSeconds;

    rewind($handle);
    ftruncate($handle, 0);
    fwrite($handle, json_encode($state));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);

    return [
      'allowed' => $allowed,
      'limit' => $limit,
      'remaining' => $remaining,
      'retry_after' => max(0, $resetAt - $now),
      'reset_at' => $resetAt,
      'window_seconds' => $windowSeconds
    ];
  }

  private function ensureStoragePath(){
    if(is_dir($this->storagePath)) return;
    if(!mkdir($this->storagePath, 0775, true) && !is_dir($this->storagePath)){
      throw new Exception('Unable to create ChangeNOW rate-limit storage.');
    }
  }

  private function filePath($bucket, $identity){
    $bucket = preg_replace('/[^a-z0-9_.-]/i', '_', (string) $bucket);
    return $this->storagePath.'/'.$bucket.'-'.hash('sha256', (string) $identity).'.json';
  }

}

class ChangeNowEligibility {

  public static function defaultCopy(){
    return [
      'non_custodial_warning' => 'Krypto does not custody funds. ChangeNOW processes the exchange and controls provider-side timing, limits, and status updates.',
      'unsupported_region' => 'ChangeNOW swaps are not available in your region under current provider or local policy.',
      'unsupported_pair' => 'This pair is not available for ChangeNOW exchange right now.',
      'provider_down' => 'ChangeNOW exchange is temporarily unavailable. Please try again later.',
      'expired_quote' => 'The quote expired. Request a new quote before creating a transaction.',
      'address_validation_failed' => 'The destination or refund address could not be validated for the selected asset and network.',
      'rate_limited' => 'Too many swap requests. Please wait before trying again.'
    ];
  }

  public static function countryState($countryCode, $blockedCountries, $copy = []){
    $countryCode = strtoupper(trim((string) $countryCode));
    $blockedCountries = self::normalizeCountryList($blockedCountries);
    $copy = ChangeNowGuardrails::mergeComplianceCopy($copy);

    if(strlen($countryCode) == 0 || !in_array($countryCode, $blockedCountries)){
      return [
        'allowed' => true,
        'state' => 'allowed',
        'message' => ''
      ];
    }

    return [
      'allowed' => false,
      'state' => 'unsupported_region',
      'message' => $copy['unsupported_region']
    ];
  }

  public static function providerState($available, $copy = []){
    $copy = ChangeNowGuardrails::mergeComplianceCopy($copy);
    if($available){
      return [
        'available' => true,
        'state' => 'available',
        'message' => ''
      ];
    }

    return [
      'available' => false,
      'state' => 'provider_down',
      'message' => $copy['provider_down']
    ];
  }

  public static function errorState($state, $copy = []){
    $copy = ChangeNowGuardrails::mergeComplianceCopy($copy);
    $state = (array_key_exists($state, $copy) ? $state : 'provider_down');

    return [
      'state' => $state,
      'message' => $copy[$state]
    ];
  }

  public static function normalizeCountryList($countries){
    if(is_string($countries)){
      $decoded = json_decode($countries, true);
      if(is_array($decoded)) {
        $countries = $decoded;
      } else {
        $countries = preg_split('/[\s,;]+/', $countries);
      }
    }

    if(!is_array($countries)) return [];

    $normalized = [];
    foreach ($countries as $country) {
      $country = strtoupper(trim((string) $country));
      if(preg_match('/^[A-Z]{2}$/', $country)) $normalized[] = $country;
    }

    return array_values(array_unique($normalized));
  }

}

class ChangeNowRequestRegion {

  public static function requestState($server, $blockedCountries, $copy = [], $geoIpResolver = null, $trustedProxies = null){
    $countryCode = self::countryCode($server, $geoIpResolver, $trustedProxies);
    $state = ChangeNowEligibility::countryState($countryCode, $blockedCountries, $copy);
    $state['country'] = $countryCode;
    return $state;
  }

  public static function countryCode($server, $geoIpResolver = null, $trustedProxies = null){
    if(!is_array($server)) $server = [];

    $serverCountry = self::serverCountryCode($server);
    if($serverCountry != '') return $serverCountry;

    $trustedHeaderCountry = self::trustedHeaderCountryCode($server, $trustedProxies);
    if($trustedHeaderCountry != '') return $trustedHeaderCountry;

    $clientIp = self::clientIp($server, $trustedProxies);
    if($clientIp == '' || is_null($geoIpResolver)) return '';

    return self::countryCodeFromGeoIpPayload(self::resolveGeoIp($clientIp, $geoIpResolver));
  }

  public static function countryCodeFromGeoIpPayload($payload){
    if(is_string($payload)) return self::normalizeCountryCode($payload);
    if(is_object($payload)) $payload = get_object_vars($payload);
    if(!is_array($payload)) return '';

    foreach (['country_code', 'countryCode', 'countryCode2', 'country_code2', 'code'] as $key) {
      if(array_key_exists($key, $payload)){
        $countryCode = self::normalizeCountryCode($payload[$key]);
        if($countryCode != '') return $countryCode;
      }
    }

    foreach (['country', 'location'] as $containerKey) {
      if(!array_key_exists($containerKey, $payload)) continue;
      $container = $payload[$containerKey];
      if(is_object($container)) $container = get_object_vars($container);
      if(!is_array($container)) continue;

      foreach (['code', 'iso_code', 'isoCode', 'country_code', 'countryCode'] as $key) {
        if(array_key_exists($key, $container)){
          $countryCode = self::normalizeCountryCode($container[$key]);
          if($countryCode != '') return $countryCode;
        }
      }
    }

    return '';
  }

  public static function normalizeCountryCode($value){
    $value = strtoupper(trim((string) $value));
    if($value == '') return '';

    $value = preg_replace('/[^A-Z]/', '', $value);
    if(!preg_match('/^[A-Z]{2}$/', $value)) return '';
    if(in_array($value, ['XX', 'ZZ'], true)) return '';

    return $value;
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

  public static function normalizeIp($value){
    $value = trim((string) $value);
    if($value == '') return '';

    $value = trim($value, " \t\n\r\0\x0B[]\"");
    if(filter_var($value, FILTER_VALIDATE_IP)) return $value;

    if(preg_match('/^([0-9.]+):[0-9]+$/', $value, $matches) && filter_var($matches[1], FILTER_VALIDATE_IP)){
      return $matches[1];
    }

    return '';
  }

  public static function normalizeTrustedProxies($trustedProxies = null){
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

  private static function serverCountryCode($server){
    foreach (['GEOIP_COUNTRY_CODE', 'MM_COUNTRY_CODE', 'COUNTRY_CODE', 'REDIRECT_GEOIP_COUNTRY_CODE'] as $key) {
      if(array_key_exists($key, $server)){
        $countryCode = self::normalizeCountryCode($server[$key]);
        if($countryCode != '') return $countryCode;
      }
    }

    return '';
  }

  private static function trustedHeaderCountryCode($server, $trustedProxies = null){
    $remoteAddr = (array_key_exists('REMOTE_ADDR', $server) ? self::normalizeIp($server['REMOTE_ADDR']) : '');
    if($remoteAddr == '' || !self::isTrustedProxy($remoteAddr, $trustedProxies)) return '';

    foreach ([
      'HTTP_CF_IPCOUNTRY',
      'HTTP_CLOUDFRONT_VIEWER_COUNTRY',
      'HTTP_X_APPENGINE_COUNTRY',
      'HTTP_X_VERCEL_IP_COUNTRY',
      'HTTP_X_GEOIP_COUNTRY_CODE',
      'HTTP_X_COUNTRY_CODE',
      'HTTP_GEOIP_COUNTRY_CODE'
    ] as $key) {
      if(array_key_exists($key, $server)){
        $countryCode = self::normalizeCountryCode($server[$key]);
        if($countryCode != '') return $countryCode;
      }
    }

    return '';
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

  private static function resolveGeoIp($clientIp, $geoIpResolver){
    if(is_callable($geoIpResolver)) return call_user_func($geoIpResolver, $clientIp);
    if(is_object($geoIpResolver)){
      foreach (['_getCountryCodeForIp', 'countryCodeForIp', 'lookup'] as $method) {
        if(method_exists($geoIpResolver, $method)) return $geoIpResolver->{$method}($clientIp);
      }
    }

    return null;
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

}

class ChangeNowAccessPolicy {

  public static function canViewTransaction($actor, $transaction, $lookupToken = null){
    if(self::actorIsAdmin($actor) || self::actorIsManager($actor)) return true;

    $transactionUserId = self::valueFrom($transaction, ['id_user', 'user_id']);
    if(!is_null($transactionUserId) && strlen((string) $transactionUserId) > 0){
      $actorId = self::actorId($actor);
      return (!is_null($actorId) && (string) $actorId === (string) $transactionUserId);
    }

    $lookupTokenHash = self::valueFrom($transaction, ['lookup_token_hash', 'anonymous_lookup_token_hash']);
    if(is_null($lookupTokenHash) || is_null($lookupToken)) return false;

    return self::hashEquals($lookupTokenHash, self::hashLookupToken($lookupToken));
  }

  public static function canManageProviderTransaction($actor){
    return (self::actorIsAdmin($actor) || self::actorIsManager($actor));
  }

  public static function hashLookupToken($token){
    return hash('sha256', (string) $token);
  }

  private static function actorId($actor){
    if(is_object($actor) && method_exists($actor, '_getUserID')) return $actor->_getUserID();
    return self::valueFrom($actor, ['id_user', 'user_id', 'id']);
  }

  private static function actorIsAdmin($actor){
    if(is_object($actor) && method_exists($actor, '_isAdmin')) return $actor->_isAdmin();
    return self::truthy(self::valueFrom($actor, ['is_admin', 'admin']));
  }

  private static function actorIsManager($actor){
    if(is_object($actor) && method_exists($actor, '_isManager')) return $actor->_isManager();
    return self::truthy(self::valueFrom($actor, ['is_manager', 'manager']));
  }

  private static function valueFrom($source, $keys){
    if(is_array($source)){
      foreach ($keys as $key) {
        if(array_key_exists($key, $source)) return $source[$key];
      }
    }

    if(is_object($source)){
      foreach ($keys as $key) {
        if(isset($source->$key)) return $source->$key;
      }
    }

    return null;
  }

  private static function truthy($value){
    return ($value === true || $value === 1 || $value === '1' || $value === 'true');
  }

  private static function hashEquals($expected, $actual){
    if(function_exists('hash_equals')) return hash_equals((string) $expected, (string) $actual);
    return (sha1((string) $expected) === sha1((string) $actual) && (string) $expected === (string) $actual);
  }

}

?>
