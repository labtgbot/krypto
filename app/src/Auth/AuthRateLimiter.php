<?php

require_once __DIR__.'/../ChangeNow/ChangeNowGuardrails.php';

/**
 * File-backed authentication abuse limiter with per-identity exponential
 * backoff. Identities are hashed in filenames, so raw emails/IPs are not
 * persisted in the limiter storage directory.
 */
class KryptoAuthRateLimiter {

  private $storagePath = null;

  public function __construct($storagePath = null){
    $this->storagePath = (is_null($storagePath) ? sys_get_temp_dir().'/krypto-auth-rate-limit' : $storagePath);
  }

  public function check($bucket, $identity, $config = [], $now = null){
    return $this->withState($bucket, $identity, $config, $now, function($state, $config, $now) {
      return [
        'state' => $state,
        'decision' => KryptoAuthRateLimiter::decisionFromState($state, $config, $now)
      ];
    });
  }

  public function recordFailure($bucket, $identity, $config = [], $now = null){
    return $this->withState($bucket, $identity, $config, $now, function($state, $config, $now) {
      $state['failures'] = intval($state['failures']) + 1;
      $state['last_failure_at'] = $now;

      $delay = KryptoAuthRateLimiter::delayForFailures($state['failures'], $config);
      if($delay > 0){
        $state['blocked_until'] = max(intval($state['blocked_until']), $now + $delay);
      }

      return [
        'state' => $state,
        'decision' => KryptoAuthRateLimiter::decisionFromState($state, $config, $now)
      ];
    });
  }

  public function recordSuccess($bucket, $identity, $config = [], $now = null){
    return $this->withState($bucket, $identity, $config, $now, function($state, $config, $now) {
      $state = KryptoAuthRateLimiter::emptyState();
      return [
        'state' => $state,
        'decision' => KryptoAuthRateLimiter::decisionFromState($state, $config, $now)
      ];
    });
  }

  public static function normalizeConfig($config = []){
    if(!is_array($config)) $config = [];

    $normalized = [
      'failure_limit' => 5,
      'captcha_after' => 3,
      'base_delay_seconds' => 60,
      'max_delay_seconds' => 900,
      'decay_seconds' => 3600
    ];

    foreach ($normalized as $key => $defaultValue) {
      if(array_key_exists($key, $config)) $normalized[$key] = intval($config[$key]);
      if($normalized[$key] < 0) $normalized[$key] = $defaultValue;
    }

    if($normalized['failure_limit'] < 1) $normalized['failure_limit'] = 1;
    if($normalized['captcha_after'] < 1) $normalized['captcha_after'] = $normalized['failure_limit'];
    if($normalized['base_delay_seconds'] < 1) $normalized['base_delay_seconds'] = 1;
    if($normalized['max_delay_seconds'] < $normalized['base_delay_seconds']) $normalized['max_delay_seconds'] = $normalized['base_delay_seconds'];
    if($normalized['decay_seconds'] < 1) $normalized['decay_seconds'] = 3600;

    return $normalized;
  }

  public static function emptyState(){
    return [
      'failures' => 0,
      'blocked_until' => 0,
      'last_failure_at' => 0
    ];
  }

  public static function decisionFromState($state, $config, $now){
    $blockedUntil = intval($state['blocked_until']);
    $failures = intval($state['failures']);
    $retryAfter = max(0, $blockedUntil - $now);

    return [
      'allowed' => $retryAfter === 0,
      'failures' => $failures,
      'failure_limit' => intval($config['failure_limit']),
      'remaining' => max(0, intval($config['failure_limit']) - $failures),
      'captcha_required' => $failures >= intval($config['captcha_after']),
      'captcha_after' => intval($config['captcha_after']),
      'retry_after' => $retryAfter,
      'blocked_until' => $blockedUntil,
      'decay_at' => ($failures > 0 ? intval($state['last_failure_at']) + intval($config['decay_seconds']) : 0)
    ];
  }

  public static function delayForFailures($failures, $config){
    $failures = intval($failures);
    $failureLimit = intval($config['failure_limit']);
    if($failures < $failureLimit) return 0;

    $exponent = min(20, max(0, $failures - $failureLimit));
    $delay = intval($config['base_delay_seconds'] * pow(2, $exponent));
    return min(intval($config['max_delay_seconds']), max(1, $delay));
  }

  private function withState($bucket, $identity, $config, $now, $callback){
    $config = self::normalizeConfig($config);
    $now = (is_null($now) ? time() : intval($now));

    $this->ensureStoragePath();

    $filePath = $this->filePath($bucket, $identity);
    $handle = fopen($filePath, 'c+');
    if(!$handle) throw new Exception('Unable to open authentication rate-limit bucket.');

    flock($handle, LOCK_EX);

    $contents = stream_get_contents($handle);
    $state = json_decode($contents, true);
    $state = $this->normalizeState($state, $config, $now);

    $result = call_user_func($callback, $state, $config, $now);
    $state = (is_array($result) && array_key_exists('state', $result) ? $result['state'] : $state);
    $decision = (is_array($result) && array_key_exists('decision', $result) ? $result['decision'] : self::decisionFromState($state, $config, $now));

    rewind($handle);
    ftruncate($handle, 0);
    fwrite($handle, json_encode($state));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);

    return $decision;
  }

  private function normalizeState($state, $config, $now){
    if(!is_array($state)) $state = self::emptyState();

    $state = [
      'failures' => (array_key_exists('failures', $state) ? intval($state['failures']) : 0),
      'blocked_until' => (array_key_exists('blocked_until', $state) ? intval($state['blocked_until']) : 0),
      'last_failure_at' => (array_key_exists('last_failure_at', $state) ? intval($state['last_failure_at']) : 0)
    ];

    if($state['failures'] < 0) $state['failures'] = 0;
    if($state['blocked_until'] < 0) $state['blocked_until'] = 0;
    if($state['last_failure_at'] < 0) $state['last_failure_at'] = 0;

    if($state['last_failure_at'] > 0 && $now - $state['last_failure_at'] >= intval($config['decay_seconds'])){
      return self::emptyState();
    }

    return $state;
  }

  private function ensureStoragePath(){
    if(is_dir($this->storagePath)) return;
    if(!mkdir($this->storagePath, 0775, true) && !is_dir($this->storagePath)){
      throw new Exception('Unable to create authentication rate-limit storage.');
    }
  }

  private function filePath($bucket, $identity){
    $bucket = preg_replace('/[^a-z0-9_.-]/i', '_', (string) $bucket);
    return $this->storagePath.'/'.$bucket.'-'.hash('sha256', (string) $identity).'.json';
  }

}

class KryptoAuthRateLimit {

  const GENERIC_AUTH_MESSAGE = 'Invalid credentials';
  const RATE_LIMIT_MESSAGE = 'Too many attempts. Please wait before trying again.';
  const RESET_PASSWORD_MESSAGE = 'If an account exists for this email, password reset instructions will be sent.';

  public static function defaultConfig($bucket = null){
    $config = [
      'login' => [
        'failure_limit' => 5,
        'captcha_after' => 3,
        'base_delay_seconds' => 60,
        'max_delay_seconds' => 900,
        'decay_seconds' => 3600
      ],
      'totp' => [
        'failure_limit' => 5,
        'captcha_after' => 3,
        'base_delay_seconds' => 60,
        'max_delay_seconds' => 900,
        'decay_seconds' => 3600
      ],
      'reset_password' => [
        'failure_limit' => 3,
        'captcha_after' => 2,
        'base_delay_seconds' => 300,
        'max_delay_seconds' => 3600,
        'decay_seconds' => 3600
      ]
    ];

    if(is_null($bucket)) return $config;
    return (array_key_exists($bucket, $config) ? $config[$bucket] : $config['login']);
  }

  public static function identitiesForRequest($account, $server = null){
    if(is_null($server)) $server = $_SERVER;
    if(!is_array($server)) $server = [];

    $identities = [];
    $account = strtolower(trim((string) $account));
    if($account != ''){
      $identities[] = [
        'type' => 'account',
        'key' => 'account:'.hash('sha256', $account)
      ];
    }

    $clientIp = ChangeNowRequestRegion::clientIp($server);
    if($clientIp != ''){
      $identities[] = [
        'type' => 'ip',
        'key' => 'ip:'.hash('sha256', $clientIp)
      ];
    }

    if(count($identities) == 0){
      $identities[] = [
        'type' => 'anonymous',
        'key' => 'anonymous:global'
      ];
    }

    return $identities;
  }

  public static function check($bucket, $account, $server, $limiter, $config = null, $now = null){
    return self::runForIdentities('check', $bucket, $account, $server, $limiter, $config, $now);
  }

  public static function recordFailure($bucket, $account, $server, $limiter, $config = null, $now = null){
    return self::runForIdentities('recordFailure', $bucket, $account, $server, $limiter, $config, $now);
  }

  public static function recordSuccess($bucket, $account, $server, $limiter, $config = null, $now = null){
    return self::runForIdentities('recordSuccess', $bucket, $account, $server, $limiter, $config, $now);
  }

  public static function failurePayload($decision = null, $message = null, $captchaEnabled = false){
    $payload = [
      'error' => 1,
      'msg' => (is_null($message) ? self::GENERIC_AUTH_MESSAGE : $message)
    ];

    if(is_array($decision)){
      $payload['retry_after'] = (array_key_exists('retry_after', $decision) ? intval($decision['retry_after']) : 0);
      $payload['captcha_required'] = ($captchaEnabled && array_key_exists('captcha_required', $decision) && $decision['captcha_required'] === true);
    }

    return $payload;
  }

  public static function resetPasswordPayload($decision = null, $captchaEnabled = false){
    $payload = self::failurePayload($decision, self::RESET_PASSWORD_MESSAGE, $captchaEnabled);
    $payload['error'] = 0;
    return $payload;
  }

  public static function captchaEnabled($App){
    if(!is_object($App) || !method_exists($App, '_captchaSignup')) return false;
    if(!$App->_captchaSignup()) return false;
    if(!method_exists($App, '_getGoogleRecaptchaSecretKey') || trim((string) $App->_getGoogleRecaptchaSecretKey()) == '') return false;
    if(!method_exists($App, '_getGoogleRecaptchaSiteKey') || trim((string) $App->_getGoogleRecaptchaSiteKey()) == '') return false;
    return class_exists('\\ReCaptcha\\ReCaptcha');
  }

  public static function captchaRequired($decision, $App){
    return is_array($decision) && array_key_exists('captcha_required', $decision) && $decision['captcha_required'] === true && self::captchaEnabled($App);
  }

  public static function verifyCaptcha($App, $post, $server = null){
    if(!self::captchaEnabled($App)) return true;
    if(!is_array($post) || !array_key_exists('g-recaptcha-response', $post) || trim((string) $post['g-recaptcha-response']) == '') return false;
    if(is_null($server)) $server = $_SERVER;
    if(!is_array($server)) $server = [];

    $recaptcha = new \ReCaptcha\ReCaptcha($App->_getGoogleRecaptchaSecretKey());
    $clientIp = ChangeNowRequestRegion::clientIp($server);
    $resp = $recaptcha->verify($post['g-recaptcha-response'], $clientIp);
    return $resp->isSuccess();
  }

  private static function runForIdentities($method, $bucket, $account, $server, $limiter, $config = null, $now = null){
    if(!is_object($limiter) || !method_exists($limiter, $method)){
      throw new Exception('Authentication rate limiter is not available.');
    }

    if(is_null($config)) $config = self::defaultConfig($bucket);

    $aggregate = null;
    foreach (self::identitiesForRequest($account, $server) as $identity) {
      $decision = $limiter->{$method}($bucket, $identity['key'], $config, $now);
      $decision['identity_type'] = $identity['type'];
      $aggregate = self::aggregateDecision($aggregate, $decision);
    }

    return $aggregate;
  }

  private static function aggregateDecision($aggregate, $decision){
    if(is_null($aggregate)) return $decision;

    if(array_key_exists('allowed', $decision) && $decision['allowed'] !== true) $aggregate['allowed'] = false;
    $aggregate['retry_after'] = max(intval($aggregate['retry_after']), intval($decision['retry_after']));
    $aggregate['blocked_until'] = max(intval($aggregate['blocked_until']), intval($decision['blocked_until']));
    $aggregate['failures'] = max(intval($aggregate['failures']), intval($decision['failures']));
    $aggregate['remaining'] = min(intval($aggregate['remaining']), intval($decision['remaining']));
    $aggregate['captcha_required'] = ($aggregate['captcha_required'] === true || $decision['captcha_required'] === true);
    if($decision['captcha_required'] === true) $aggregate['identity_type'] = $decision['identity_type'];

    return $aggregate;
  }

}

?>
