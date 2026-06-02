<?php

/**
 * Regression coverage for issue #88 (audit finding A1): passwords must be
 * stored with a modern salted algorithm (password_hash / PASSWORD_DEFAULT) and
 * verified with password_verify, while legacy unsalted sha512 hashes keep
 * working and are transparently migrated on the next successful login.
 *
 * The functional half loads the real User helpers (no database required) and
 * exercises verify + legacy-migration semantics. The static half guards the
 * call-sites in User.php / Install.php against a regression that reintroduces
 * raw sha512 hashing or a password comparison inside the login SQL.
 */

$root = dirname(__DIR__);

function assert_pw($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, 'FAIL: '.$message."\n");
        exit(1);
    }
}

// --- Static source guards ----------------------------------------------------
$userSource = file_get_contents($root.'/app/src/User/User.php');
assert_pw($userSource !== false && trim($userSource) !== '', 'Cannot read User.php');

assert_pw(
    strpos($userSource, 'password_hash($password, PASSWORD_DEFAULT)') !== false,
    'User.php must hash passwords with password_hash($password, PASSWORD_DEFAULT) (A1).'
);
assert_pw(
    strpos($userSource, 'password_verify(') !== false,
    'User.php must verify passwords with password_verify (A1).'
);
assert_pw(
    strpos($userSource, 'password_needs_rehash(') !== false,
    'User.php must detect outdated hashes with password_needs_rehash (A1).'
);
// The standard login path must verify in PHP, not compare the password in SQL.
assert_pw(
    strpos($userSource, '_passwordMatches($password, $r[0][\'password_user\'])') !== false,
    'Standard login must verify the password with _passwordMatches, not in SQL (A1).'
);
// No write-path may persist a raw unsalted sha512 hash anymore.
assert_pw(
    strpos($userSource, "'password_user' => hash('sha512', \$password)") === false,
    'User.php must not write raw sha512 password hashes (A1).'
);

$installSource = file_get_contents($root.'/install/app/src/Install.php');
assert_pw($installSource !== false, 'Cannot read Install.php');
assert_pw(
    strpos($installSource, "hash('sha512'") === false,
    'Installer must not seed the admin account with a raw sha512 hash (A1).'
);
assert_pw(
    strpos($installSource, 'password_hash(') !== false,
    'Installer must seed the admin account with password_hash (A1).'
);

// --- Functional checks (no database needed) ----------------------------------
// User extends MySQL, whose static property defaults reference these constants.
foreach (['MYSQL_HOST', 'MYSQL_USER', 'MYSQL_DATABASE', 'MYSQL_PASSWD', 'MYSQL_PORT'] as $const) {
    if (!defined($const)) {
        define($const, '');
    }
}
require_once $root.'/app/src/MySQL/MySQL.php';
require_once $root.'/app/src/User/User.php';

$password = 'S3cret-Pass!';

// Modern hashing round-trip.
$hash = User::_hashPassword($password);
assert_pw(is_string($hash) && $hash !== '', 'password_hash must return a non-empty string.');
assert_pw($hash !== hash('sha512', $password), 'Stored hash must not be a plain sha512 digest.');
assert_pw(User::_passwordMatches($password, $hash), 'A fresh hash must verify against the password.');
assert_pw(!User::_passwordMatches('wrong-password', $hash), 'A wrong password must not verify.');
assert_pw(!User::_passwordNeedsUpgrade($hash), 'A fresh PASSWORD_DEFAULT hash must not need an upgrade.');
assert_pw(!User::_isLegacyPasswordHash($hash), 'A modern hash must not be flagged as legacy sha512.');

// Legacy unsalted sha512 compatibility + migration signal.
$legacy = hash('sha512', $password);
assert_pw(User::_isLegacyPasswordHash($legacy), 'A 128-hex sha512 digest must be detected as legacy.');
assert_pw(User::_passwordMatches($password, $legacy), 'Existing sha512 passwords must keep verifying.');
assert_pw(!User::_passwordMatches('wrong-password', $legacy), 'A wrong password must not verify against legacy.');
assert_pw(User::_passwordNeedsUpgrade($legacy), 'Legacy sha512 hashes must be flagged for migration.');

// Defensive: empty / null stored hashes must never authenticate.
assert_pw(!User::_passwordMatches($password, ''), 'An empty stored hash must never authenticate.');
assert_pw(!User::_passwordMatches($password, null), 'A null stored hash must never authenticate.');

echo "Password hashing migration checks passed.\n";

?>
