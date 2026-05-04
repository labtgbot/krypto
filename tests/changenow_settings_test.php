<?php

$root = dirname(__DIR__);
$settingsFile = $root.'/app/modules/kr-changenow/src/ChangeNowSettings.php';

if (!file_exists($settingsFile)) {
    throw new Exception('Missing ChangeNOW settings helper: '.$settingsFile);
}

require_once $settingsFile;

function assertSameValue($expected, $actual, $message) {
    if ($expected !== $actual) {
        throw new Exception($message.' Expected '.var_export($expected, true).', got '.var_export($actual, true));
    }
}

function assertTrueValue($condition, $message) {
    if (!$condition) {
        throw new Exception($message);
    }
}

$post = [
    'kr-adm-chk-enablechangenow' => 'on',
    'kr-adm-changenowpublicapikey' => 'public-key',
    'kr-adm-changenowprivateapikey' => 'private-key',
    'kr-adm-changenowcallbacksecret' => 'callback-secret',
    'kr-adm-changenowreferrallinkid' => 'partner-link',
    'kr-adm-changenowwidgetlinkid' => 'widget-link',
    'kr-adm-chk-changenowflowstandard' => 'on',
    'kr-adm-chk-changenowflowfixedrate' => 'on',
    'kr-adm-changenowdefaultflow' => 'fixed-rate',
    'kr-adm-changenowdefaultfromasset' => 'BTC',
    'kr-adm-changenowdefaultfromnetwork' => 'BTC',
    'kr-adm-changenowdefaulttoasset' => 'ETH',
    'kr-adm-changenowdefaulttonetwork' => 'ETH',
    'kr-adm-changenowsupportemail' => ' swaps@example.com ',
    'kr-adm-changenowratelimitsecond' => '25',
    'kr-adm-changenowratelimitminute' => '1200',
    'kr-adm-changenowquotecachettl' => '45',
];

$settings = ChangeNowSettings::_adminPostToSettings($post);

assertSameValue('1', $settings['changenow_provider_enabled'], 'Provider enabled flag should be saved');
assertSameValue('public-key', $settings['changenow_public_api_key'], 'Public API key should be saved');
assertSameValue('private-key', $settings['changenow_private_api_key'], 'Private API key should be saved');
assertSameValue('callback-secret', $settings['changenow_callback_secret'], 'Callback secret should be saved');
assertSameValue('partner-link', $settings['changenow_referral_link_id'], 'Referral link ID should be saved');
assertSameValue('widget-link', $settings['changenow_widget_link_id'], 'Widget link ID should be saved');
assertSameValue('standard,fixed-rate', $settings['changenow_enabled_flows'], 'Enabled flows should be normalized');
assertSameValue('fixed-rate', $settings['changenow_default_flow'], 'Default flow should be saved');
assertSameValue('btc', $settings['changenow_default_from_asset'], 'Default source asset should be normalized');
assertSameValue('btc', $settings['changenow_default_from_network'], 'Default source network should be normalized');
assertSameValue('eth', $settings['changenow_default_to_asset'], 'Default destination asset should be normalized');
assertSameValue('eth', $settings['changenow_default_to_network'], 'Default destination network should be normalized');
assertSameValue('swaps@example.com', $settings['changenow_support_email'], 'Support email should be trimmed');
assertSameValue('25', $settings['changenow_rate_limit_per_second'], 'Per-second rate limit should be saved');
assertSameValue('1200', $settings['changenow_rate_limit_per_minute'], 'Per-minute rate limit should be saved');
assertSameValue('45', $settings['changenow_quote_cache_ttl'], 'Quote cache TTL should be saved');

$maskedSettings = ChangeNowSettings::_adminPostToSettings([
    'kr-adm-changenowpublicapikey' => ChangeNowSettings::SECRET_MASK,
    'kr-adm-changenowprivateapikey' => ChangeNowSettings::SECRET_MASK,
    'kr-adm-changenowcallbacksecret' => ChangeNowSettings::SECRET_MASK,
    'kr-adm-chk-changenowflowstandard' => 'on',
    'kr-adm-changenowdefaultflow' => 'unsupported-flow',
    'kr-adm-changenowratelimitsecond' => '0',
    'kr-adm-changenowratelimitminute' => '-9',
    'kr-adm-changenowquotecachettl' => 'invalid',
]);

assertTrueValue(!array_key_exists('changenow_public_api_key', $maskedSettings), 'Masked public API key should preserve the existing encrypted value');
assertTrueValue(!array_key_exists('changenow_private_api_key', $maskedSettings), 'Masked private API key should preserve the existing encrypted value');
assertTrueValue(!array_key_exists('changenow_callback_secret', $maskedSettings), 'Masked callback secret should preserve the existing encrypted value');
assertTrueValue(!array_key_exists('changenow_public_api_key', ChangeNowSettings::_adminPostToSettings([])), 'Missing secret field should preserve the existing encrypted value');
assertSameValue('0', $maskedSettings['changenow_provider_enabled'], 'Missing checkbox should disable the provider');
assertSameValue('standard', $maskedSettings['changenow_default_flow'], 'Invalid default flow should fall back to standard');
assertSameValue('30', $maskedSettings['changenow_rate_limit_per_second'], 'Invalid per-second rate limit should fall back to the safe default');
assertSameValue('1800', $maskedSettings['changenow_rate_limit_per_minute'], 'Invalid per-minute rate limit should fall back to the safe default');
assertSameValue('30', $maskedSettings['changenow_quote_cache_ttl'], 'Invalid quote cache TTL should fall back to the safe default');

$encryptedKeys = ChangeNowSettings::_encryptedKeys();
foreach (['changenow_public_api_key', 'changenow_private_api_key', 'changenow_callback_secret'] as $encryptedKey) {
    assertTrueValue(in_array($encryptedKey, $encryptedKeys, true), $encryptedKey.' must be marked encrypted');
}

$installerSql = file_get_contents($root.'/install/assets/sql/krypto.sql');
foreach ([
    "'changenow_public_api_key', '', 1",
    "'changenow_private_api_key', '', 1",
    "'changenow_callback_secret', '', 1",
    "'changenow_provider_enabled', '0', 0",
    "'changenow_quote_cache_ttl', '30', 0",
] as $settingSeed) {
    assertTrueValue(strpos($installerSql, $settingSeed) !== false, 'Missing installer seed: '.$settingSeed);
}

$paymentView = file_get_contents($root.'/app/modules/kr-admin/views/payment.php');
foreach ([
    'kr-adm-changenowpublicapikey',
    'kr-adm-changenowprivateapikey',
    'kr-adm-changenowcallbacksecret',
    'kr-adm-chk-changenowflowstandard',
    'kr-adm-chk-changenowflowfixedrate',
    'kr-adm-changenowquotecachettl',
    '*********************',
] as $viewNeedle) {
    assertTrueValue(strpos($paymentView, $viewNeedle) !== false, 'Missing admin payment view wiring: '.$viewNeedle);
}

$saveAction = file_get_contents($root.'/app/modules/kr-admin/src/actions/savePayment.php');
assertTrueValue(strpos($saveAction, 'ChangeNowSettings::_adminPostToSettings') !== false, 'Save action should read ChangeNOW admin settings');
assertTrueValue(strpos($saveAction, '_saveChangeNowSettings') !== false, 'Save action should persist ChangeNOW settings through App');

$appSource = file_get_contents($root.'/app/src/App/App.php');
foreach ([
    '_saveChangeNowSettings',
    '_getChangeNowPublicApiKey',
    '_getChangeNowPrivateApiKey',
    '_getChangeNowCallbackSecret',
    '_getChangeNowQuoteCacheTtl',
    '_validateChangeNowLiveSwapSettings',
] as $appMethod) {
    assertTrueValue(strpos($appSource, 'function '.$appMethod.'(') !== false, 'Missing App ChangeNOW method: '.$appMethod);
}

$docs = file_get_contents($root.'/docs/changenow-provider-settings.md');
assertTrueValue(strpos($docs, 'ChangeNOW Business') !== false, 'Missing ChangeNOW credential documentation');

echo "ChangeNOW settings check passed\n";
