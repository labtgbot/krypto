<?php

$root = dirname(__DIR__);
$installerSql = file_get_contents($root.'/install/assets/sql/krypto.sql');
$migrationFile = $root.'/install/assets/sql/changenow-cn04-migration.sql';
$publicSwapMigrationFile = $root.'/install/assets/sql/changenow-cn05-migration.sql';
$lifecycleMigrationFile = $root.'/install/assets/sql/changenow-cn06-migration.sql';

if ($installerSql === false) {
    throw new Exception('Unable to read installer SQL');
}

if (!file_exists($migrationFile)) {
    throw new Exception('Missing ChangeNOW CN-04 migration file: '.$migrationFile);
}

if (!file_exists($publicSwapMigrationFile)) {
    throw new Exception('Missing ChangeNOW CN-05 migration file: '.$publicSwapMigrationFile);
}

if (!file_exists($lifecycleMigrationFile)) {
    throw new Exception('Missing ChangeNOW CN-06 migration file: '.$lifecycleMigrationFile);
}

$migrationSql = file_get_contents($migrationFile);
if ($migrationSql === false) {
    throw new Exception('Unable to read ChangeNOW CN-04 migration SQL');
}

$publicSwapMigrationSql = file_get_contents($publicSwapMigrationFile);
if ($publicSwapMigrationSql === false) {
    throw new Exception('Unable to read ChangeNOW CN-05 migration SQL');
}

$lifecycleMigrationSql = file_get_contents($lifecycleMigrationFile);
if ($lifecycleMigrationSql === false) {
    throw new Exception('Unable to read ChangeNOW CN-06 migration SQL');
}

function assertContainsText($needle, $haystack, $message) {
    if (strpos($haystack, $needle) === false) {
        throw new Exception($message.' Missing: '.$needle);
    }
}

$tables = [
    'changenow_assets_krypto',
    'changenow_pairs_krypto',
    'changenow_quote_cache_krypto',
    'changenow_sync_status_krypto',
];

foreach ($tables as $table) {
    assertContainsText('CREATE TABLE `'.$table.'`', $installerSql, 'Installer SQL should include ChangeNOW table');
    assertContainsText('CREATE TABLE IF NOT EXISTS `'.$table.'`', $migrationSql, 'Migration SQL should include ChangeNOW table');
}

$installerRequired = [
    '`ticker_changenow_asset`',
    '`network_changenow_asset`',
    '`admin_enabled_changenow_asset`',
    '`from_currency_changenow_pair`',
    '`from_network_changenow_pair`',
    '`to_currency_changenow_pair`',
    '`to_network_changenow_pair`',
    '`flow_changenow_pair`',
    '`min_amount_changenow_pair`',
    '`max_amount_changenow_pair`',
    '`cache_key_changenow_quote_cache`',
    '`expires_at_changenow_quote_cache`',
    'ADD UNIQUE KEY `ticker_network_changenow_asset`',
    'ADD UNIQUE KEY `pair_flow_changenow_pair`',
    'ADD UNIQUE KEY `cache_key_changenow_quote_cache`',
    "'changenow_quote_cache_ttl', '30', 0",
];

foreach ($installerRequired as $needle) {
    assertContainsText($needle, $installerSql, 'Installer SQL should include ChangeNOW schema detail');
}

$migrationRequired = [
    'ticker_network_changenow_asset',
    'pair_flow_changenow_pair',
    'cache_key_changenow_quote_cache',
    'INSERT IGNORE INTO `settings_krypto`',
    "'changenow_quote_cache_ttl', '30', 0",
];

foreach ($migrationRequired as $needle) {
    assertContainsText($needle, $migrationSql, 'Migration SQL should include ChangeNOW upgrade detail');
}

$publicSwapRequired = [
    'changenow_transactions_krypto',
    '`provider_id_changenow_transaction`',
    '`lookup_token_hash_changenow_transaction`',
    '`session_key_changenow_transaction`',
    '`status_changenow_transaction`',
    '`raw_create_changenow_transaction`',
    'ADD UNIQUE KEY `lookup_token_hash_changenow_transaction`',
    'ADD KEY `user_changenow_transaction`',
    'ADD KEY `pair_changenow_transaction`',
];

assertContainsText('CREATE TABLE `changenow_transactions_krypto`', $installerSql, 'Installer SQL should include public ChangeNOW transaction table');
assertContainsText('CREATE TABLE IF NOT EXISTS `changenow_transactions_krypto`', $publicSwapMigrationSql, 'CN-05 migration should include public ChangeNOW transaction table');

foreach ($publicSwapRequired as $needle) {
    assertContainsText($needle, $installerSql, 'Installer SQL should include public swap schema detail');
    assertContainsText(str_replace('ADD ', '', $needle), $publicSwapMigrationSql, 'CN-05 migration should include public swap schema detail');
}

$transactionLifecycleRequired = [
    'changenow_transaction_events_krypto',
    '`payout_address_fingerprint_changenow_transaction`',
    '`refund_available_changenow_transaction`',
    '`continue_available_changenow_transaction`',
    '`referral_attribution_changenow_transaction`',
    '`raw_actions_changenow_transaction`',
    '`support_note_changenow_transaction`',
    '`actor_user_id_changenow_transaction_event`',
    '`event_type_changenow_transaction_event`',
    '`event_status_changenow_transaction_event`',
    'ADD KEY `action_changenow_transaction`',
    'KEY `provider_changenow_transaction_event`',
];

assertContainsText('CREATE TABLE `changenow_transaction_events_krypto`', $installerSql, 'Installer SQL should include ChangeNOW transaction audit table');
assertContainsText('CREATE TABLE IF NOT EXISTS `changenow_transaction_events_krypto`', $lifecycleMigrationSql, 'CN-06 migration should include ChangeNOW transaction audit table');

foreach ($transactionLifecycleRequired as $needle) {
    assertContainsText($needle, $installerSql, 'Installer SQL should include ChangeNOW lifecycle schema detail');
    assertContainsText(str_replace('ADD ', '', $needle), $lifecycleMigrationSql, 'CN-06 migration should include ChangeNOW lifecycle schema detail');
}

$adminSource = file_get_contents($root.'/app/modules/kr-admin/src/Admin.php');
$managerSource = file_get_contents($root.'/app/modules/kr-manager/src/Manager.php');
$panelSource = file_get_contents($root.'/assets/js/pannel.js');
assertContainsText('app/modules/kr-changenow/src/actions/syncMarketData.php', $adminSource, 'Admin cron list should expose ChangeNOW market sync');
assertContainsText('ChangeNOW swaps', $adminSource, 'Admin navigation should expose ChangeNOW transaction support');
assertContainsText('ChangeNOW swaps', $managerSource, 'Manager navigation should expose ChangeNOW transaction support');
assertContainsText('changenowswaps', $panelSource, 'Dashboard router should initialize ChangeNOW support screens');

$indexSource = file_get_contents($root.'/index.php');
$publicActionSource = file_get_contents($root.'/app/modules/kr-changenow/src/actions/publicSwap.php');
assertContainsText('kr-public-swap-enabled', $indexSource, 'Homepage should render the public swap shell');
assertContainsText("require 'app/modules/kr-changenow/views/publicSwap.php'", $indexSource, 'Homepage should include public swap view');
assertContainsText('$Flow->_createSwap($_POST, $sessionKey, $loggedUserId)', $publicActionSource, 'Public action should create anonymous ChangeNOW swaps');
assertContainsText('$Flow->_requestRefund($lookupToken', $publicActionSource, 'Public action should expose ChangeNOW refund action');
assertContainsText('$Flow->_continueSwap($lookupToken', $publicActionSource, 'Public action should expose ChangeNOW continue action');

echo "ChangeNOW schema check passed\n";

?>
