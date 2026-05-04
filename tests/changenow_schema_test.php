<?php

$root = dirname(__DIR__);
$installerSql = file_get_contents($root.'/install/assets/sql/krypto.sql');
$migrationFile = $root.'/install/assets/sql/changenow-cn04-migration.sql';

if ($installerSql === false) {
    throw new Exception('Unable to read installer SQL');
}

if (!file_exists($migrationFile)) {
    throw new Exception('Missing ChangeNOW CN-04 migration file: '.$migrationFile);
}

$migrationSql = file_get_contents($migrationFile);
if ($migrationSql === false) {
    throw new Exception('Unable to read ChangeNOW CN-04 migration SQL');
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

$adminSource = file_get_contents($root.'/app/modules/kr-admin/src/Admin.php');
assertContainsText('app/modules/kr-changenow/src/actions/syncMarketData.php', $adminSource, 'Admin cron list should expose ChangeNOW market sync');

echo "ChangeNOW schema check passed\n";

?>
