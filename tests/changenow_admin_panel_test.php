<?php

require __DIR__.'/../app/modules/kr-changenow/src/ChangeNowSettings.php';
require __DIR__.'/../app/modules/kr-changenow/src/ChangeNowAdminPanel.php';

function assert_true($condition, $message){
  if(!$condition){
    fwrite(STDERR, "Assertion failed: ".$message.PHP_EOL);
    exit(1);
  }
}

$disabledSummary = ChangeNowAdminPanel::_statusSummary(ChangeNowSettings::_defaults());
assert_true($disabledSummary['state'] === 'disabled', 'disabled provider is reported as local disabled');

$missingConfig = ChangeNowSettings::_sanitizeSettings([
  'changenow_provider_enabled' => '1',
  'changenow_public_api_key' => ''
]);
$missingSummary = ChangeNowAdminPanel::_statusSummary($missingConfig);
assert_true($missingSummary['state'] === 'missing_config', 'enabled provider without public key is missing config');

$outageConfig = ChangeNowSettings::_sanitizeSettings([
  'changenow_provider_enabled' => '1',
  'changenow_public_api_key' => 'public-key',
  'changenow_provider_health_status' => 'outage'
]);
$outageSummary = ChangeNowAdminPanel::_statusSummary($outageConfig);
assert_true($outageSummary['state'] === 'provider_outage', 'provider outage is distinguished from missing config');

$postSettings = ChangeNowSettings::_adminPostToSettings([
  'kr-adm-chk-enablechangenow' => 'on',
  'kr-adm-changenowpublicapikey' => ChangeNowSettings::SECRET_MASK,
  'kr-adm-changenowprivateapikey' => 'new-private-key',
  'kr-adm-changenowdefaultflow' => 'fixed',
  'kr-adm-changenowdefaultfromasset' => 'BTC!',
  'kr-adm-changenowenabledassets' => "BTC\nETH\nBTC",
  'kr-adm-chk-changenowflowfixedrate' => 'on',
  'kr-adm-changenowratelimitsecond' => '0',
  'kr-adm-changenowwidgetprimarycolor' => '#ff7700'
]);
assert_true(!array_key_exists('changenow_public_api_key', $postSettings) || $postSettings['changenow_public_api_key'] === '', 'masked public key is not overwritten');
assert_true($postSettings['changenow_private_api_key'] === 'new-private-key', 'new private key is saved');
assert_true($postSettings['changenow_default_flow'] === 'fixed-rate', 'fixed flow alias is normalized');
assert_true($postSettings['changenow_default_from_asset'] === 'btc', 'asset symbols are normalized');
assert_true($postSettings['changenow_enabled_assets'] === "btc\neth", 'asset list is deduplicated');
assert_true($postSettings['changenow_rate_limit_per_second'] === '30', 'invalid rate limit falls back to default');
assert_true($postSettings['changenow_widget_primary_color'] === 'FF7700', 'widget color is sanitized');

$columns = array_merge(ChangeNowAdminPanel::_knownTransactionColumns(), [
  'lookup_token_fragment_changenow_transaction'
]);
$query = ChangeNowAdminPanel::_buildTransactionSearchQuery([
  'search' => 'abc',
  'provider_id' => 'cn-123',
  'internal_id' => '42',
  'user_email' => 'user@example.com',
  'anonymous_token' => 'tok-frag',
  'status' => 'waiting',
  'date_from' => '2026-05-01',
  'date_to' => '2026-05-04',
  'asset' => 'BTC',
  'referral_code' => 'ref42'
], 100, $columns);

assert_true(strpos($query['sql'], 'provider_id_changenow_transaction LIKE :provider_id') !== false, 'provider ID filter is present');
assert_true(strpos($query['sql'], 'u.email_user LIKE :user_email') !== false, 'user email filter is present');
assert_true(strpos($query['sql'], 'lookup_token_fragment_changenow_transaction LIKE :anonymous_token') !== false, 'anonymous token fragment filter is present when schema supports it');
assert_true(strpos($query['sql'], 'lookup_token_hash_changenow_transaction=:anonymous_token_hash') !== false, 'anonymous token hash filter is present when schema supports it');
assert_true(strpos($query['sql'], 'referral_attribution_changenow_transaction LIKE :referral_code') !== false, 'referral filter is present');
assert_true(strpos($query['sql'], 't.payout_address_changenow_transaction') === false, 'raw payout address is not selected or searched');
assert_true($query['params']['asset'] === 'btc', 'asset filter is normalized');
assert_true($query['params']['anonymous_token_hash'] === hash('sha256', 'tok-frag'), 'anonymous token hash is generated locally for exact lookup');
assert_true($query['limit'] === 100, 'limit is preserved within bounds');

assert_true(ChangeNowAdminPanel::_supportActionAllowed(['refundAvailable' => true], 'refund'), 'refund action is allowed only when marked available');
assert_true(!ChangeNowAdminPanel::_supportActionAllowed(['refundAvailable' => false], 'refund'), 'refund action is blocked when unavailable');
assert_true(ChangeNowAdminPanel::_supportActionAllowed([], 'note'), 'support notes are always allowed for found transactions');

echo "ChangeNOW admin panel tests passed.".PHP_EOL;

?>
