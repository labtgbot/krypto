<?php

$root = dirname(__DIR__);
$settingsFile = $root.'/app/modules/kr-changenow/src/ChangeNowSettings.php';
$retentionFile = $root.'/app/modules/kr-changenow/src/ChangeNowRetention.php';
$scriptFile = $root.'/scripts/changenow_retention.php';
$docFile = $root.'/docs/changenow-retention-policy.md';

foreach ([$settingsFile, $retentionFile, $scriptFile, $docFile] as $requiredFile) {
    if (!file_exists($requiredFile)) {
        throw new Exception('Missing ChangeNOW retention file: '.$requiredFile);
    }
}

require_once $settingsFile;
require_once $retentionFile;

function assertRetentionSame($expected, $actual, $message) {
    if ($expected !== $actual) {
        throw new Exception($message.' Expected '.var_export($expected, true).', got '.var_export($actual, true));
    }
}

function assertRetentionTrue($condition, $message) {
    if (!$condition) {
        throw new Exception($message);
    }
}

$defaults = ChangeNowSettings::_defaults();
assertRetentionSame('30', $defaults['changenow_retention_anonymous_days'], 'Anonymous retention default should be safe and short.');
assertRetentionSame('365', $defaults['changenow_retention_completed_days'], 'Completed swap retention default should preserve audit history for one year.');

$settings = ChangeNowSettings::_adminPostToSettings([
    'kr-adm-chk-changenowflowstandard' => 'on',
    'kr-adm-changenowretentionanonymousdays' => '14',
    'kr-adm-changenowretentioncompleteddays' => '730',
]);

assertRetentionSame('14', $settings['changenow_retention_anonymous_days'], 'Anonymous retention days should be configurable from admin settings.');
assertRetentionSame('730', $settings['changenow_retention_completed_days'], 'Completed retention days should be configurable from admin settings.');

$invalidSettings = ChangeNowSettings::_adminPostToSettings([
    'kr-adm-chk-changenowflowstandard' => 'on',
    'kr-adm-changenowretentionanonymousdays' => '0',
    'kr-adm-changenowretentioncompleteddays' => 'invalid',
]);

assertRetentionSame('30', $invalidSettings['changenow_retention_anonymous_days'], 'Invalid anonymous retention should fall back to default.');
assertRetentionSame('365', $invalidSettings['changenow_retention_completed_days'], 'Invalid completed retention should fall back to default.');

$options = ChangeNowRetention::_optionsFromSettings($settings, ['dry_run' => true, 'now' => 1234567890]);
assertRetentionSame(14, $options['anonymous_retention_days'], 'Retention options should read anonymous days from settings.');
assertRetentionSame(730, $options['completed_retention_days'], 'Retention options should read completed days from settings.');
assertRetentionSame(true, $options['dry_run'], 'Retention options should preserve dry-run override.');
assertRetentionSame(1234567890, $options['now'], 'Retention options should preserve deterministic clock override.');

$terminalStatuses = ChangeNowRetention::_terminalStatuses();
foreach (['finished', 'failed', 'refunded', 'expired'] as $status) {
    assertRetentionTrue(in_array($status, $terminalStatuses, true), 'Completed retention should recognize terminal status: '.$status);
}

$scriptSyntax = [];
$scriptExit = 0;
exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($scriptFile), $scriptSyntax, $scriptExit);
assertRetentionSame(0, $scriptExit, 'Retention CLI script should pass PHP syntax validation: '.implode("\n", $scriptSyntax));

$installerSql = file_get_contents($root.'/install/assets/sql/krypto.sql');
foreach ([
    "'changenow_retention_anonymous_days', '30', 0",
    "'changenow_retention_completed_days', '365', 0",
] as $settingSeed) {
    assertRetentionTrue(strpos($installerSql, $settingSeed) !== false, 'Fresh installer SQL should seed retention setting: '.$settingSeed);
}

$migrationSql = file_get_contents($root.'/install/assets/sql/changenow-cn13-retention-migration.sql');
foreach ([
    "`key_settings` = 'changenow_retention_anonymous_days'",
    "`key_settings` = 'changenow_retention_completed_days'",
] as $migrationNeedle) {
    assertRetentionTrue(strpos($migrationSql, $migrationNeedle) !== false, 'Retention migration should insert missing setting: '.$migrationNeedle);
}

$paymentView = file_get_contents($root.'/app/modules/kr-admin/views/payment.php');
$changeNowView = file_get_contents($root.'/app/modules/kr-admin/views/changenow.php');
foreach (['kr-adm-changenowretentionanonymousdays', 'kr-adm-changenowretentioncompleteddays'] as $viewNeedle) {
    assertRetentionTrue(strpos($paymentView, $viewNeedle) !== false, 'Payment settings view should expose retention field: '.$viewNeedle);
    assertRetentionTrue(strpos($changeNowView, $viewNeedle) !== false, 'ChangeNOW settings view should expose retention field: '.$viewNeedle);
}

$doc = file_get_contents($docFile);
foreach ([
    'scripts/changenow_retention.php',
    'changenow_quote_cache_krypto',
    'anonymous lookup tokens',
    'changenow_retention_anonymous_days',
    'changenow_retention_completed_days',
] as $docNeedle) {
    assertRetentionTrue(strpos($doc, $docNeedle) !== false, 'Retention documentation should cover: '.$docNeedle);
}

echo "ChangeNOW retention policy checks passed.\n";

?>
