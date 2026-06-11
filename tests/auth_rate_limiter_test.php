<?php

require __DIR__.'/../app/src/Auth/AuthRateLimiter.php';

function assert_auth_rate($condition, $message) {
  if(!$condition) {
    throw new Exception($message);
  }
}

function assert_auth_rate_equals($expected, $actual, $message) {
  if($expected !== $actual) {
    throw new Exception($message.' Expected '.var_export($expected, true).', got '.var_export($actual, true));
  }
}

$storagePath = sys_get_temp_dir().'/krypto-auth-rate-test-'.uniqid('', true);
$limiter = new KryptoAuthRateLimiter($storagePath);
$config = [
  'failure_limit' => 2,
  'captcha_after' => 1,
  'base_delay_seconds' => 10,
  'max_delay_seconds' => 40,
  'decay_seconds' => 30
];

$initial = $limiter->check('login', 'account:alice', $config, 1000);
assert_auth_rate($initial['allowed'], 'Fresh auth identities must be allowed.');
assert_auth_rate(!$initial['captcha_required'], 'Fresh auth identities must not require CAPTCHA.');
assert_auth_rate_equals(2, $initial['remaining'], 'Fresh auth identities should expose the full remaining budget.');

$firstFailure = $limiter->recordFailure('login', 'account:alice', $config, 1001);
assert_auth_rate($firstFailure['allowed'], 'A single failure below the lockout threshold should not block.');
assert_auth_rate($firstFailure['captcha_required'], 'CAPTCHA should be required after the configured number of failures.');
assert_auth_rate_equals(1, $firstFailure['failures'], 'The first failure should be recorded.');

$secondFailure = $limiter->recordFailure('login', 'account:alice', $config, 1002);
assert_auth_rate(!$secondFailure['allowed'], 'The threshold-crossing failure should block the identity.');
assert_auth_rate_equals(10, $secondFailure['retry_after'], 'Initial lockout should use the base delay.');

$duringLockout = $limiter->check('login', 'account:alice', $config, 1007);
assert_auth_rate(!$duringLockout['allowed'], 'The identity must remain blocked before blocked_until.');
assert_auth_rate_equals(5, $duringLockout['retry_after'], 'Retry-after should count down inside the lockout window.');

$afterLockout = $limiter->check('login', 'account:alice', $config, 1012);
assert_auth_rate($afterLockout['allowed'], 'The identity should be allowed again after blocked_until.');
assert_auth_rate($afterLockout['captcha_required'], 'CAPTCHA should remain required until a success or decay clears failures.');

$thirdFailure = $limiter->recordFailure('login', 'account:alice', $config, 1012);
assert_auth_rate(!$thirdFailure['allowed'], 'A later failure after lockout should block again.');
assert_auth_rate_equals(20, $thirdFailure['retry_after'], 'Repeated failures should use exponential backoff.');

$success = $limiter->recordSuccess('login', 'account:alice', $config, 1013);
assert_auth_rate($success['allowed'], 'Successful authentication should clear the lockout.');
assert_auth_rate_equals(0, $success['failures'], 'Successful authentication should clear recorded failures.');
assert_auth_rate(!$success['captcha_required'], 'Successful authentication should clear CAPTCHA requirements.');

$otherIdentity = $limiter->check('login', 'account:bob', $config, 1013);
assert_auth_rate($otherIdentity['allowed'], 'Different auth identities must have independent limiter state.');

$limiter->recordFailure('login', 'account:decay', $config, 2000);
$afterDecay = $limiter->check('login', 'account:decay', $config, 2030);
assert_auth_rate($afterDecay['allowed'], 'Limiter state should be allowed after the decay window.');
assert_auth_rate_equals(0, $afterDecay['failures'], 'Limiter failures should decay after the configured window.');
assert_auth_rate(!$afterDecay['captcha_required'], 'CAPTCHA should not be required after decay clears failures.');

$aggregateStorage = sys_get_temp_dir().'/krypto-auth-rate-aggregate-test-'.uniqid('', true);
$aggregateLimiter = new KryptoAuthRateLimiter($aggregateStorage);
$server = ['REMOTE_ADDR' => '198.51.100.7'];

KryptoAuthRateLimit::recordFailure('login', 'User@Example.Test', $server, $aggregateLimiter, $config, 3000);
$aggregateBlock = KryptoAuthRateLimit::recordFailure('login', 'User@Example.Test', $server, $aggregateLimiter, $config, 3001);
assert_auth_rate(!$aggregateBlock['allowed'], 'Aggregate account+IP decisions should block after repeated failures.');

$sameAccountOtherIp = KryptoAuthRateLimit::check('login', 'user@example.test', ['REMOTE_ADDR' => '198.51.100.8'], $aggregateLimiter, $config, 3002);
assert_auth_rate(!$sameAccountOtherIp['allowed'], 'The per-account bucket should block the same account from a different IP.');

$sameIpOtherAccount = KryptoAuthRateLimit::check('login', 'other@example.test', $server, $aggregateLimiter, $config, 3002);
assert_auth_rate(!$sameIpOtherAccount['allowed'], 'The per-IP bucket should block a different account from the same IP.');

$payload = KryptoAuthRateLimit::failurePayload($aggregateBlock, KryptoAuthRateLimit::GENERIC_AUTH_MESSAGE, true);
assert_auth_rate_equals(1, $payload['error'], 'Auth failure payloads should use the existing action error shape.');
assert_auth_rate_equals(KryptoAuthRateLimit::GENERIC_AUTH_MESSAGE, $payload['msg'], 'Auth failures should use the generic credentials message.');
assert_auth_rate($payload['captcha_required'], 'Auth failure payloads should expose CAPTCHA requirements when CAPTCHA is enabled.');

echo "Authentication rate limiter checks passed.\n";

?>
