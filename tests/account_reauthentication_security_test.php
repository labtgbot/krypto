<?php

/**
 * Regression coverage for issue #102 (SEC-15): sensitive account changes must
 * require re-authentication and Google 2FA checks must use the active secret.
 */

$root = dirname(__DIR__);

foreach ([
  'MYSQL_HOST' => 'localhost',
  'MYSQL_USER' => 'root',
  'MYSQL_PASSWD' => '',
  'MYSQL_PORT' => '3306',
  'MYSQL_DATABASE' => 'krypto_test',
  'CRYPTED_KEY' => 'account-reauthentication-security-test-key',
  'APP_URL' => $root,
  'APP_URL_FORCE' => false,
  'FILE_PATH' => '',
] as $constant => $value) {
  if(!defined($constant)) define($constant, $value);
}

require_once $root.'/vendor/autoload.php';
require_once $root.'/app/src/MySQL/MySQL.php';
require_once $root.'/app/src/App/App.php';
require_once $root.'/app/src/User/User.php';

function assert_account_reauth($condition, $message) {
  if(!$condition) {
    throw new Exception($message);
  }
}

function account_reauth_expect_exception($callback, $message) {
  try {
    $callback();
  } catch (Exception $e) {
    return $e;
  }

  throw new Exception($message);
}

function account_reauth_use_pdo(PDO $pdo) {
  $reflection = new ReflectionClass('MySQL');
  $property = $reflection->getProperty('bdd');
  $property->setAccessible(true);
  $property->setValue(null, $pdo);
}

$userSource = file_get_contents($root.'/app/src/User/User.php');
$profileActionSource = file_get_contents($root.'/app/modules/kr-user/src/actions/updateUserprofile.php');
$removeTfsActionSource = file_get_contents($root.'/app/modules/kr-user/src/actions/removeGoogleTFS.php');
$profileViewSource = file_get_contents($root.'/app/modules/kr-user/views/profile.php');
$securityViewSource = file_get_contents($root.'/app/modules/kr-user/views/security.php');

assert_account_reauth($userSource !== false, 'Cannot read User.php.');
assert_account_reauth($profileActionSource !== false, 'Cannot read updateUserprofile.php.');
assert_account_reauth($removeTfsActionSource !== false, 'Cannot read removeGoogleTFS.php.');
assert_account_reauth($profileViewSource !== false, 'Cannot read profile.php.');
assert_account_reauth($securityViewSource !== false, 'Cannot read security.php.');

assert_account_reauth(strpos($profileActionSource, '_assertSensitiveChangeReauthenticated(') !== false, 'Profile updates must require server-side re-authentication for password/email changes.');
assert_account_reauth(strpos($profileActionSource, 'kr-user-current-pwd') !== false, 'Profile update action must read the current password confirmation.');
assert_account_reauth(strpos($profileActionSource, 'google_tfs_code') !== false, 'Profile update action must read the Google 2FA code confirmation.');
assert_account_reauth(strpos($removeTfsActionSource, '_confirmGoogleTFSDisable(') !== false, 'Google 2FA removal must require a valid TOTP code or current password.');
assert_account_reauth(strpos($removeTfsActionSource, '_sendAccountSecurityNotification(') !== false, 'Google 2FA removal must notify the user.');
assert_account_reauth(strpos($profileViewSource, 'name="kr-user-current-pwd"') !== false, 'Profile form must expose the current password confirmation field.');
assert_account_reauth(strpos($profileViewSource, 'name="google_tfs_code"') !== false, 'Profile form must expose the Google 2FA confirmation field.');
assert_account_reauth(strpos($securityViewSource, 'name="google_tfs_code"') !== false, '2FA removal form must expose the current TOTP code field.');
assert_account_reauth(strpos($securityViewSource, 'name="kr-user-current-pwd"') !== false, '2FA removal form must expose the current password fallback field.');
assert_account_reauth(strpos($userSource, 'status_googletfs=:status_googletfs') !== false, 'Google 2FA secret lookup must filter active secrets.');
assert_account_reauth(strpos($userSource, 'ORDER BY date_googletfs DESC, id_googletfs DESC LIMIT 1') !== false, 'Google 2FA secret lookup must deterministically choose the latest active secret.');

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE user_krypto (
  id_user integer primary key,
  email_user text NOT NULL,
  name_user text NOT NULL,
  password_user text NOT NULL,
  picture_user text,
  oauth_user text NOT NULL,
  pushbullet_user text,
  twostep_user text,
  created_date_user text,
  admin_user text,
  status_user text,
  lang_user text,
  currency_user text,
  reset_token_user text,
  reset_token_created_user text
)');
$pdo->exec('CREATE TABLE user_settings_krypto (
  id_user integer NOT NULL,
  key_user_settings text NOT NULL,
  value_user_settings text NOT NULL
)');
$pdo->exec('CREATE TABLE googletfs_krypto (
  id_googletfs integer primary key autoincrement,
  id_user integer NOT NULL,
  date_googletfs text NOT NULL,
  secret_googletfs text NOT NULL,
  status_googletfs integer NOT NULL DEFAULT 0
)');

account_reauth_use_pdo($pdo);

$pdo->prepare('INSERT INTO user_krypto (
  id_user,
  email_user,
  name_user,
  password_user,
  picture_user,
  oauth_user,
  pushbullet_user,
  twostep_user,
  created_date_user,
  admin_user,
  status_user,
  lang_user,
  currency_user
) VALUES (
  :id_user,
  :email_user,
  :name_user,
  :password_user,
  :picture_user,
  :oauth_user,
  :pushbullet_user,
  :twostep_user,
  :created_date_user,
  :admin_user,
  :status_user,
  :lang_user,
  :currency_user
)')->execute([
  'id_user' => 1,
  'email_user' => 'reauth@example.test',
  'name_user' => 'Reauth User',
  'password_user' => User::_hashPassword('current-password'),
  'picture_user' => '',
  'oauth_user' => 'standard',
  'pushbullet_user' => '',
  'twostep_user' => '0',
  'created_date_user' => (string) time(),
  'admin_user' => '0',
  'status_user' => '1',
  'lang_user' => 'en',
  'currency_user' => 'USD',
]);

$user = new User(1);

account_reauth_expect_exception(function () use ($user) {
  $user->_assertSensitiveChangeReauthenticated('', null);
}, 'Sensitive profile changes must reject missing current password.');

account_reauth_expect_exception(function () use ($user) {
  $user->_assertSensitiveChangeReauthenticated('wrong-password', null);
}, 'Sensitive profile changes must reject an invalid current password.');

assert_account_reauth($user->_assertSensitiveChangeReauthenticated('current-password', null), 'Sensitive profile changes must accept the correct current password when 2FA is disabled.');

$googleAuthenticator = new \RobThree\Auth\TwoFactorAuth('Krypto Test');
do {
  $inactiveSecret = $googleAuthenticator->createSecret();
  $olderActiveSecret = $googleAuthenticator->createSecret();
  $latestActiveSecret = $googleAuthenticator->createSecret();
  $latestCode = $googleAuthenticator->getCode($latestActiveSecret);
} while (
  $googleAuthenticator->verifyCode($inactiveSecret, $latestCode) ||
  $googleAuthenticator->verifyCode($olderActiveSecret, $latestCode)
);

$insertSecret = $pdo->prepare('INSERT INTO googletfs_krypto (
  id_user,
  date_googletfs,
  secret_googletfs,
  status_googletfs
) VALUES (
  :id_user,
  :date_googletfs,
  :secret_googletfs,
  :status_googletfs
)');
$insertSecret->execute([
  'id_user' => 1,
  'date_googletfs' => '300',
  'secret_googletfs' => App::_encryptSecret($inactiveSecret),
  'status_googletfs' => 0,
]);
$insertSecret->execute([
  'id_user' => 1,
  'date_googletfs' => '100',
  'secret_googletfs' => App::_encryptSecret($olderActiveSecret),
  'status_googletfs' => 1,
]);
$insertSecret->execute([
  'id_user' => 1,
  'date_googletfs' => '200',
  'secret_googletfs' => App::_encryptSecret($latestActiveSecret),
  'status_googletfs' => 1,
]);

account_reauth_expect_exception(function () use ($user) {
  $user->_assertSensitiveChangeReauthenticated('current-password', null);
}, 'Sensitive profile changes must require a TOTP code when 2FA is enabled.');

account_reauth_expect_exception(function () use ($user) {
  $user->_assertSensitiveChangeReauthenticated('current-password', '000000');
}, 'Sensitive profile changes must reject an invalid TOTP code.');

assert_account_reauth($user->_checkGoogleTFS($latestCode), 'Google 2FA checks must use the latest active secret, not inactive or older rows.');
assert_account_reauth($user->_assertSensitiveChangeReauthenticated('current-password', $latestCode), 'Sensitive profile changes must accept current password plus valid TOTP when 2FA is enabled.');

account_reauth_expect_exception(function () use ($user) {
  $user->_confirmGoogleTFSDisable('', '');
}, 'Google 2FA removal must reject missing TOTP and password confirmations.');

assert_account_reauth($user->_confirmGoogleTFSDisable($latestCode, ''), 'Google 2FA removal must accept a valid TOTP code.');
assert_account_reauth($user->_confirmGoogleTFSDisable('', 'current-password'), 'Google 2FA removal must accept the current password fallback.');

echo "Account re-authentication security checks passed.\n";

?>
