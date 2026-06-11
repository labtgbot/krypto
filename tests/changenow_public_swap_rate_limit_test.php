<?php

$root = dirname(__DIR__);

$guardrailsFile = $root.'/app/src/ChangeNow/ChangeNowGuardrails.php';
$rateLimitFile = $root.'/app/modules/kr-changenow/src/ChangeNowPublicRateLimit.php';
$publicActionFile = $root.'/app/modules/kr-changenow/src/actions/publicSwap.php';

foreach ([$guardrailsFile, $rateLimitFile, $publicActionFile] as $file) {
  if(!file_exists($file)) {
    throw new Exception('Missing ChangeNOW rate-limit file: '.$file);
  }
}

require_once $guardrailsFile;
require_once $rateLimitFile;

define('KRYPTO_PUBLIC_SWAP_HELPERS_ONLY', true);
require_once $publicActionFile;

function assertSameValue($expected, $actual, $message) {
  if($expected !== $actual) {
    throw new Exception($message.' Expected '.var_export($expected, true).', got '.var_export($actual, true));
  }
}

function assertTrueValue($condition, $message) {
  if(!$condition) {
    throw new Exception($message);
  }
}

class ChangeNowPublicRateLimitFakeLimiter {
  public $calls = [];
  private $results = [];

  public function __construct($results = []) {
    $this->results = $results;
  }

  public function check($bucket, $identity, $limit, $windowSeconds, $now = null) {
    $this->calls[] = [
      'bucket' => $bucket,
      'identity' => $identity,
      'limit' => $limit,
      'window_seconds' => $windowSeconds,
      'now' => $now
    ];

    if(count($this->results) > 0) return array_shift($this->results);

    return [
      'allowed' => true,
      'limit' => $limit,
      'remaining' => max(0, $limit - 1),
      'retry_after' => $windowSeconds,
      'reset_at' => (is_null($now) ? time() : $now) + $windowSeconds,
      'window_seconds' => $windowSeconds
    ];
  }
}

class ChangeNowPublicRateLimitFakeApp {
  private $limiter = null;

  public function __construct($limiter) {
    $this->limiter = $limiter;
  }

  public function _getChangeNowRateLimitConfig($bucket = null) {
    $config = [
      'quote' => [
        'limit' => 1,
        'window_seconds' => 60
      ],
      'transaction' => [
        'limit' => 2,
        'window_seconds' => 120
      ]
    ];

    if(is_null($bucket)) return $config;
    return (array_key_exists($bucket, $config) ? $config[$bucket] : null);
  }

  public function _getChangeNowRateLimiter($storagePath = null) {
    return $this->limiter;
  }

  public function _getChangeNowComplianceCopy($key = null) {
    $copy = [
      'rate_limited' => 'Custom public swap rate-limit copy.'
    ];

    if(is_null($key)) return $copy;
    return (array_key_exists($key, $copy) ? $copy[$key] : null);
  }
}

class ChangeNowPublicRegionFakeApp {
  public $requestedCountries = [];

  public function _getChangeNowBlockedCountries() {
    return ['US'];
  }

  public function _getChangeNowRequestCountry($server = null, $geoIpResolver = null) {
    $country = '';
    if(is_callable($geoIpResolver)) $country = call_user_func($geoIpResolver, $server);
    $this->requestedCountries[] = $country;
    return $country;
  }

  public function _getChangeNowEligibilityForCountry($countryCode) {
    return ChangeNowEligibility::countryState($countryCode, ['US'], [
      'unsupported_region' => 'Custom unsupported-region copy.'
    ]);
  }
}

class ChangeNowPublicEmptyRegionFakeApp {
  public $requestedCountries = 0;

  public function _getChangeNowBlockedCountries() {
    return [];
  }

  public function _getChangeNowRequestCountry($server = null, $geoIpResolver = null) {
    $this->requestedCountries++;
    return 'US';
  }
}

assertSameValue('quote', ChangeNowPublicRateLimit::bucketForAction('quote'), 'quote should use the quote bucket');
assertSameValue('quote', ChangeNowPublicRateLimit::bucketForAction('validate'), 'validate should share the quote bucket');
assertSameValue('transaction', ChangeNowPublicRateLimit::bucketForAction('create'), 'create should use the transaction bucket');
assertSameValue(null, ChangeNowPublicRateLimit::bucketForAction('status'), 'status should not consume the transaction bucket');

$untrustedServer = [
  'REMOTE_ADDR' => '203.0.113.10',
  'HTTP_X_FORWARDED_FOR' => '198.51.100.45'
];
assertSameValue('203.0.113.10', ChangeNowPublicRateLimit::clientIp($untrustedServer, []), 'untrusted proxies must not override REMOTE_ADDR');
assertSameValue('198.51.100.45', ChangeNowPublicRateLimit::clientIp($untrustedServer, ['203.0.113.10']), 'trusted proxies should expose the forwarded client IP');

$limitedLimiter = new ChangeNowPublicRateLimitFakeLimiter([
  [
    'allowed' => false,
    'limit' => 1,
    'remaining' => 0,
    'retry_after' => 45,
    'reset_at' => 145,
    'window_seconds' => 60
  ]
]);
$limitedApp = new ChangeNowPublicRateLimitFakeApp($limitedLimiter);
$session = [];
$decision = changenow_public_rate_limit_decision($limitedApp, 'quote', [
  'REMOTE_ADDR' => '198.51.100.7'
], $session, null, 100);

assertSameValue(false, $decision['allowed'], 'publicSwap action helper should deny a mocked exhausted limiter');
assertSameValue('quote', $limitedLimiter->calls[0]['bucket'], 'publicSwap action helper should check the quote bucket');
assertSameValue(1, $limitedLimiter->calls[0]['limit'], 'publicSwap action helper should use admin quote limit config');
assertSameValue(60, $limitedLimiter->calls[0]['window_seconds'], 'publicSwap action helper should use admin quote window config');
assertTrueValue(strpos($limitedLimiter->calls[0]['identity'], 'ip:') === 0, 'rate-limit identity should not store a raw IP address');
assertTrueValue(isset($session['kr_changenow_session_key']), 'limited public actions should maintain a non-sensitive session key');

$payload = changenow_public_rate_limited_payload($limitedApp, $decision);
assertSameValue(1, $payload['error'], 'rate-limited public response should be an error');
assertSameValue('rate_limited', $payload['type'], 'rate-limited public response should expose the requested type');
assertSameValue('Custom public swap rate-limit copy.', $payload['msg'], 'rate-limited public response should use compliance copy');
assertSameValue(45, $payload['retry_after'], 'rate-limited public response should expose retry guidance');

$createLimiter = new ChangeNowPublicRateLimitFakeLimiter();
$createApp = new ChangeNowPublicRateLimitFakeApp($createLimiter);
$createSession = [];
changenow_public_rate_limit_decision($createApp, 'create', [
  'REMOTE_ADDR' => '198.51.100.7'
], $createSession, null, 100);

assertSameValue('transaction', $createLimiter->calls[0]['bucket'], 'create should check the transaction bucket');
assertSameValue(2, $createLimiter->calls[0]['limit'], 'create should use admin transaction limit config');
assertSameValue(120, $createLimiter->calls[0]['window_seconds'], 'create should use admin transaction window config');

$statusLimiter = new ChangeNowPublicRateLimitFakeLimiter();
$statusApp = new ChangeNowPublicRateLimitFakeApp($statusLimiter);
$statusSession = [];
$statusDecision = changenow_public_rate_limit_decision($statusApp, 'status', [
  'REMOTE_ADDR' => '198.51.100.7'
], $statusSession, null, 100);

assertSameValue(true, $statusDecision['allowed'], 'status should be allowed by the publicSwap rate-limit helper');
assertSameValue(0, count($statusLimiter->calls), 'status should not consume any limiter bucket');
assertSameValue(0, count($statusSession), 'status should not create a public swap rate-limit session key');

$regionApp = new ChangeNowPublicRegionFakeApp();
$regionDecision = changenow_public_region_decision($regionApp, 'create', [
  'REMOTE_ADDR' => '198.51.100.7'
], function() {
  return 'US';
});

assertSameValue(false, $regionDecision['allowed'], 'publicSwap action helper should deny create from a mocked blocked country');
assertSameValue('unsupported_region', $regionDecision['state'], 'blocked region helper should expose unsupported_region state');
assertSameValue('US', $regionDecision['country'], 'blocked region helper should expose the detected country code');
assertSameValue('Custom unsupported-region copy.', $regionDecision['message'], 'blocked region helper should use admin compliance copy');
assertSameValue(1, count($regionApp->requestedCountries), 'blocked region helper should use the mocked country resolver');

$regionPayload = changenow_public_unsupported_region_payload($regionDecision);
assertSameValue(2, $regionPayload['error'], 'unsupported region public response should be a validation error');
assertSameValue('unsupported_region', $regionPayload['type'], 'unsupported region public response should expose the requested type');
assertSameValue('Custom unsupported-region copy.', $regionPayload['msg'], 'unsupported region public response should expose the configured copy');

$unknownRegionDecision = changenow_public_region_decision($regionApp, 'quote', [
  'REMOTE_ADDR' => '198.51.100.7'
], function() {
  return '';
});
assertSameValue(false, $unknownRegionDecision['allowed'], 'publicSwap action helper should fail closed when a blocked-country list exists and country detection is unknown');
assertSameValue('unsupported_region', $unknownRegionDecision['state'], 'unknown region helper should expose unsupported_region state');
assertSameValue('', $unknownRegionDecision['country'], 'unknown region helper should preserve an empty country code for diagnostics');

$emptyRegionApp = new ChangeNowPublicEmptyRegionFakeApp();
$emptyRegionDecision = changenow_public_region_decision($emptyRegionApp, 'create', [
  'REMOTE_ADDR' => '198.51.100.7'
], function() {
  return 'US';
});
assertSameValue(true, $emptyRegionDecision['allowed'], 'empty unsupported-country lists should preserve public create behavior');
assertSameValue('', $emptyRegionDecision['country'], 'empty unsupported-country lists should not resolve or expose request country');
assertSameValue(0, $emptyRegionApp->requestedCountries, 'empty unsupported-country lists should not call the country resolver');

$statusRegionDecision = changenow_public_region_decision($regionApp, 'status', [
  'REMOTE_ADDR' => '198.51.100.7'
], function() {
  return 'US';
});
assertSameValue(true, $statusRegionDecision['allowed'], 'status should not require regional eligibility checks');

echo "ChangeNOW public swap rate-limit tests passed\n";

?>
