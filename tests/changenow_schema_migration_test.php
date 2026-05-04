<?php

$root = dirname(__DIR__);
$installerSqlPath = $root.'/install/assets/sql/krypto.sql';
$migrationSqlPath = $root.'/install/assets/sql/changenow-cn12-migration.sql';
$docPath = $root.'/docs/changenow-schema-migration.md';

$failures = [];

function fail_if($condition, $message) {
  global $failures;
  if($condition) $failures[] = $message;
}

function read_required_file($path, $label) {
  fail_if(!file_exists($path), $label.' should exist at '.$path);
  if(!file_exists($path)) return '';
  $contents = file_get_contents($path);
  fail_if($contents === false || $contents === '', $label.' should not be empty');
  return (string) $contents;
}

function assert_contains_text($haystack, $needle, $message) {
  fail_if(strpos($haystack, $needle) === false, $message.' Missing: '.$needle);
}

function assert_not_contains_text($haystack, $needle, $message) {
  fail_if(stripos($haystack, $needle) !== false, $message.' Found forbidden text: '.$needle);
}

$installerSql = read_required_file($installerSqlPath, 'Fresh installer SQL');
$migrationSql = read_required_file($migrationSqlPath, 'CN-12 upgrade migration SQL');
$doc = read_required_file($docPath, 'CN-12 schema migration documentation');

$requiredTables = [
  'changenow_assets_krypto',
  'changenow_pairs_krypto',
  'changenow_quote_cache_krypto',
  'changenow_transactions_krypto',
  'changenow_transaction_events_krypto',
  'changenow_referral_attribution_krypto',
  'changenow_sync_status_krypto'
];

foreach($requiredTables as $table) {
  assert_contains_text($installerSql, 'CREATE TABLE `'.$table.'`', 'Fresh installer SQL should create '.$table.'.');
  assert_contains_text($migrationSql, 'CREATE TABLE IF NOT EXISTS `'.$table.'`', 'Upgrade migration should create '.$table.' idempotently.');
}

$requiredInstallerFragments = [
  'ADD UNIQUE KEY `ticker_network_changenow_asset`',
  'ADD UNIQUE KEY `pair_flow_changenow_pair`',
  'ADD UNIQUE KEY `cache_key_changenow_quote_cache`',
  'ADD UNIQUE KEY `provider_id_changenow_transaction`',
  'ADD UNIQUE KEY `lookup_token_hash_changenow_transaction`',
  'ADD KEY `user_changenow_transaction`',
  'ADD KEY `status_changenow_transaction`',
  'ADD KEY `created_at_changenow_transaction`',
  'ADD KEY `referral_code_changenow_referral`',
  'ADD UNIQUE KEY `sync_key_changenow_sync`'
];

foreach($requiredInstallerFragments as $fragment) {
  assert_contains_text($installerSql, $fragment, 'Fresh installer SQL should include required ChangeNOW key/index.');
}

$requiredSettings = [
  "'changenow_provider_enabled', '0', 0",
  "'legacy_exchange_connections_enabled', '1', 0",
  "'changenow_public_api_key', '', 1",
  "'changenow_private_api_key', '', 1",
  "'changenow_callback_secret', '', 1",
  "'changenow_referral_link_id', '', 0",
  "'changenow_widget_link_id', '', 0",
  "'changenow_enabled_flows', 'standard,fixed-rate', 0",
  "'changenow_default_flow', 'standard', 0",
  "'changenow_default_from_asset', 'btc', 0",
  "'changenow_default_from_network', 'btc', 0",
  "'changenow_default_to_asset', 'eth', 0",
  "'changenow_default_to_network', 'eth', 0",
  "'changenow_support_email', '', 0",
  "'changenow_rate_limit_per_second', '30', 0",
  "'changenow_rate_limit_per_minute', '1800', 0",
  "'changenow_quote_cache_ttl', '30', 0",
  "'changenow_debug_logging_enabled', '0', 0"
];

foreach($requiredSettings as $setting) {
  assert_contains_text($installerSql, $setting, 'Fresh installer SQL should seed disabled ChangeNOW defaults.');
}

foreach([
  'changenow_provider_enabled',
  'legacy_exchange_connections_enabled',
  'changenow_public_api_key',
  'changenow_private_api_key',
  'changenow_callback_secret',
  'changenow_quote_cache_ttl',
  'changenow_debug_logging_enabled'
] as $settingKey) {
  assert_contains_text($migrationSql, "`key_settings` = '".$settingKey."'", 'Upgrade migration should insert '.$settingKey.' only when missing.');
}

foreach(['DROP TABLE', 'TRUNCATE TABLE', 'DELETE FROM', 'DROP COLUMN'] as $forbiddenSql) {
  assert_not_contains_text($migrationSql, $forbiddenSql, 'Upgrade migration should preserve legacy and historical data.');
}

foreach([
  'binance_krypto',
  'thirdparty_crypto_krypto',
  'balance_krypto',
  'deposit_history_krypto',
  'widthdraw_history_krypto',
  'referal_krypto',
  'Rollback'
] as $docFragment) {
  assert_contains_text($doc, $docFragment, 'Documentation should cover legacy data retention and rollback.');
}

if(count($failures) > 0) {
  fwrite(STDERR, "ChangeNOW schema migration test failed:\n");
  foreach($failures as $failure) {
    fwrite(STDERR, "- ".$failure."\n");
  }
  exit(1);
}

echo "ChangeNOW schema migration checks passed.\n";

?>
