<?php

$root = dirname(__DIR__);
require_once $root.'/tests/support/db_fixtures.php';

if (getenv('KRYPTO_RUN_DB_TESTS') !== '1' || !krypto_db_is_enabled()) {
    krypto_db_skip('DB-backed smoke test disabled. Set KRYPTO_RUN_DB_TESTS=1 by running php scripts/run_tests.php --db or --only-db inside the local DB environment.');
}

function assertDbSmokeTrue($condition, $message) {
    if (!$condition) {
        throw new Exception($message);
    }
}

$pdo = krypto_db_pdo();

assertDbSmokeTrue(krypto_db_table_count($pdo) >= 58, 'Fresh Krypto schema should contain the active non-custodial application tables.');

foreach ([
    'settings_krypto',
    'user_krypto',
    'user_settings_krypto',
    'changenow_transactions_krypto',
    'changenow_transaction_events_krypto',
] as $table) {
    assertDbSmokeTrue(krypto_db_table_exists($pdo, $table), 'Bootstrapped schema missing table: '.$table);
}

foreach ([
    'balance_krypto',
    'thirdparty_crypto_krypto',
    'widthdraw_history_krypto',
] as $legacyTable) {
    assertDbSmokeTrue(!krypto_db_table_exists($pdo, $legacyTable), 'Fresh schema should not create retired legacy custody table: '.$legacyTable);
}

$settings = $pdo->query('SELECT key_settings, value_settings, encrypted_settings FROM settings_krypto')->fetchAll(PDO::FETCH_ASSOC);
assertDbSmokeTrue(count($settings) > 40, 'Fresh schema should seed application settings.');

$changeNowDefaults = $pdo->prepare('SELECT value_settings FROM settings_krypto WHERE key_settings = :key_settings LIMIT 1');
$changeNowDefaults->execute(['key_settings' => 'changenow_provider_enabled']);
$providerEnabled = $changeNowDefaults->fetchColumn();
assertDbSmokeTrue($providerEnabled === '0', 'ChangeNOW provider should stay disabled in local fixtures until explicitly configured.');

$adminFixture = krypto_db_fixture_admin($pdo);
$userFixture = krypto_db_fixture_user($pdo);
$fixtures = [
    'admin' => $adminFixture,
    'user' => $userFixture,
    'session' => krypto_db_fixture_session_for_user($adminFixture),
];
assertDbSmokeTrue((int) $fixtures['admin']['admin_user'] === 1, 'Admin fixture should have admin privileges.');
assertDbSmokeTrue((int) $fixtures['user']['admin_user'] === 0, 'User fixture should be a regular active account.');
assertDbSmokeTrue(array_key_exists('kr_login', $fixtures['session']), 'Session fixture should include the login payload.');

$_SESSION = krypto_db_fixture_session_for_user($fixtures['admin']);
krypto_db_require_application($root);

$app = new App(false);
assertDbSmokeTrue($app->_getAppTitle() === 'Krypto', 'App should load settings from the local database.');

$admin = new User((int) $fixtures['admin']['id_user']);
assertDbSmokeTrue($admin->_isAdmin(), 'Admin fixture should load through the real User model.');

$loggedUser = new User();
assertDbSmokeTrue($loggedUser->_isLogged(), 'Session fixture should make User detect a logged-in session.');

echo "DB-backed environment smoke check passed.\n";

?>
