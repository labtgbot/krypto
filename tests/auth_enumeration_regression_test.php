<?php

/**
 * Regression coverage for issue #93 (SEC-06): pre-auth authentication
 * responses must not reveal account state, activation mail must not be sent
 * from login attempts, and login / TOTP / reset-password requests must pass
 * through the shared authentication limiter.
 */

$root = dirname(__DIR__);

function assert_auth_enum($condition, $message) {
  if(!$condition) {
    throw new Exception($message);
  }
}

function auth_enum_source_between($source, $startNeedle, $endNeedle) {
  $start = strpos($source, $startNeedle);
  assert_auth_enum($start !== false, 'Cannot find source start: '.$startNeedle);
  $end = strpos($source, $endNeedle, $start);
  assert_auth_enum($end !== false, 'Cannot find source end: '.$endNeedle);
  return substr($source, $start, $end - $start);
}

$userSource = file_get_contents($root.'/app/src/User/User.php');
$loginActionSource = file_get_contents($root.'/app/modules/kr-user/src/actions/login.php');
$resetActionSource = file_get_contents($root.'/app/modules/kr-user/src/actions/resetPassword.php');
$authLimiterSource = file_get_contents($root.'/app/src/Auth/AuthRateLimiter.php');

assert_auth_enum($userSource !== false, 'Cannot read User.php.');
assert_auth_enum($loginActionSource !== false, 'Cannot read login action.');
assert_auth_enum($resetActionSource !== false, 'Cannot read resetPassword action.');
assert_auth_enum($authLimiterSource !== false, 'Cannot read AuthRateLimiter.php.');

$loginMethod = auth_enum_source_between($userSource, 'public function _login(', 'public function _oauthCallback(');
assert_auth_enum(strpos($loginMethod, '_sendActivationEmailLink($email)') === false, 'Login must not send activation email links.');
assert_auth_enum(strpos($loginMethod, 'Your account has been disabled') === false, 'Login must not expose disabled-account state.');
assert_auth_enum(strpos($loginMethod, 'You need to enable your account') === false, 'Login must not expose activation-required state.');
assert_auth_enum(strpos($loginMethod, 'Invalid login') === false, 'Login must not use the legacy non-generic error copy.');
assert_auth_enum(substr_count($loginMethod, 'KryptoAuthRateLimit::GENERIC_AUTH_MESSAGE') >= 3, 'Login failures must use the generic authentication message.');

assert_auth_enum(strpos($loginActionSource, 'KryptoAuthRateLimit::check($authBucket') !== false, 'Login action must check the auth limiter before authentication.');
assert_auth_enum(strpos($loginActionSource, 'KryptoAuthRateLimit::recordFailure($authBucket') !== false, 'Login action must record failed password/account-state attempts.');
assert_auth_enum(strpos($loginActionSource, "KryptoAuthRateLimit::recordFailure('totp'") !== false, 'Login action must record failed TOTP attempts.');
assert_auth_enum(strpos($loginActionSource, 'Wrong Google Authenticator code') === false, 'TOTP failures must not expose factor-specific copy.');
assert_auth_enum(strpos($loginActionSource, 'KryptoAuthRateLimit::recordSuccess') !== false, 'Successful authentication must clear limiter state.');

assert_auth_enum(strpos($resetActionSource, 'KryptoAuthRateLimit::check($authBucket') !== false, 'Reset password action must check the auth limiter.');
assert_auth_enum(strpos($resetActionSource, 'KryptoAuthRateLimit::recordFailure($authBucket') !== false, 'Reset password email requests must consume limiter budget.');
assert_auth_enum(strpos($resetActionSource, 'KryptoAuthRateLimit::resetPasswordPayload') !== false, 'Reset password action must use generic success copy.');

$resetMethod = auth_enum_source_between($userSource, 'public function _resetPassword(', 'public function _parseToken(');
$userLookupPos = strpos($resetMethod, 'SELECT name_user FROM user_krypto');
$missingUserReturnPos = strpos($resetMethod, 'if(count($infosUser) == 0) return true;');
$tokenGenerationPos = strpos($resetMethod, '$generateResetToken = $this->_generateUserResetToken($Email);');
assert_auth_enum($userLookupPos !== false && $tokenGenerationPos !== false, 'Reset password must keep explicit user lookup and token generation.');
assert_auth_enum($userLookupPos < $tokenGenerationPos, 'Reset password must look up the user before generating a token.');
assert_auth_enum($missingUserReturnPos !== false && $missingUserReturnPos < $tokenGenerationPos, 'Reset password must return generically without email for unknown accounts.');

assert_auth_enum(strpos($authLimiterSource, 'class KryptoAuthRateLimiter') !== false, 'Auth limiter class must exist.');
assert_auth_enum(strpos($authLimiterSource, 'recordFailure') !== false, 'Auth limiter must record failed attempts.');
assert_auth_enum(strpos($authLimiterSource, 'blocked_until') !== false, 'Auth limiter must persist lockout windows.');
assert_auth_enum(strpos($authLimiterSource, 'captcha_required') !== false, 'Auth limiter must expose CAPTCHA escalation.');
assert_auth_enum(strpos($authLimiterSource, 'delayForFailures') !== false, 'Auth limiter must implement exponential backoff.');

echo "Authentication enumeration regression checks passed.\n";

?>
