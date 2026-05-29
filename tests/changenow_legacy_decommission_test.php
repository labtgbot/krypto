<?php

$root = dirname(__DIR__);
$failures = [];

function fail_decommission_if($condition, $message) {
    global $failures;
    if ($condition) {
        $failures[] = $message;
    }
}

function read_decommission_required_file($path, $label) {
    fail_decommission_if(!file_exists($path), $label.' should exist at '.$path);
    if (!file_exists($path)) {
        return '';
    }

    $contents = file_get_contents($path);
    fail_decommission_if($contents === false || $contents === '', $label.' should not be empty');
    return (string) $contents;
}

function assert_decommission_contains($haystack, $needle, $message) {
    fail_decommission_if(strpos($haystack, $needle) === false, $message.' Missing: '.$needle);
}

function assert_decommission_not_contains($haystack, $needle, $message) {
    fail_decommission_if(stripos($haystack, $needle) !== false, $message.' Found forbidden text: '.$needle);
}

$installerSql = read_decommission_required_file(
    $root.'/install/assets/sql/krypto.sql',
    'Fresh installer SQL'
);
$decommissionSql = read_decommission_required_file(
    $root.'/install/assets/sql/changenow-open05-decommission-legacy-custody.sql',
    'OPEN-05 legacy custody decommission SQL'
);
$schemaDoc = read_decommission_required_file(
    $root.'/docs/changenow-schema-migration.md',
    'ChangeNOW schema migration documentation'
);
$migrationPlan = read_decommission_required_file(
    $root.'/docs/changenow-migration-tasks.md',
    'ChangeNOW migration task plan'
);
$dashboardRegression = read_decommission_required_file(
    $root.'/tests/dashboard_legacy_exchange_gate_regression_test.php',
    'Dashboard legacy decommission regression test'
);
$legacyUxRegression = read_decommission_required_file(
    $root.'/tests/cn08_legacy_exchange_ux_disabled.sh',
    'Legacy exchange UX decommission smoke test'
);

$legacyConnectorFiles = [
    'app/modules/kr-trade/src/0Exchange.php',
    'app/modules/kr-trade/src/Binance.php',
    'app/modules/kr-trade/src/Bitbank.php',
    'app/modules/kr-trade/src/Bitfinex.php',
    'app/modules/kr-trade/src/Bitmex.php',
    'app/modules/kr-trade/src/Bitstamp.php',
    'app/modules/kr-trade/src/Bittrex.php',
    'app/modules/kr-trade/src/Btcmarket.php',
    'app/modules/kr-trade/src/Cex.php',
    'app/modules/kr-trade/src/Coinex.php',
    'app/modules/kr-trade/src/Coinspot.php',
    'app/modules/kr-trade/src/Ethfinex.php',
    'app/modules/kr-trade/src/Exmo.php',
    'app/modules/kr-trade/src/Gateio.php',
    'app/modules/kr-trade/src/Gdax.php',
    'app/modules/kr-trade/src/Gemini.php',
    'app/modules/kr-trade/src/HiddenThirdParty.php',
    'app/modules/kr-trade/src/Hitbtc.php',
    'app/modules/kr-trade/src/Kraken.php',
    'app/modules/kr-trade/src/Kucoin.php',
    'app/modules/kr-trade/src/Livecoin.php',
    'app/modules/kr-trade/src/Luno.php',
    'app/modules/kr-trade/src/Okcoinusd.php',
    'app/modules/kr-trade/src/Okex.php',
    'app/modules/kr-trade/src/Poloniex.php',
    'app/modules/kr-trade/src/Quoinex.php',
    'app/modules/kr-trade/src/Trade.php',
    'app/modules/kr-trade/src/Widthdraw.php',
    'app/modules/kr-trade/src/Yobit.php',
];

foreach ($legacyConnectorFiles as $relativePath) {
    fail_decommission_if(
        file_exists($root.'/'.$relativePath),
        'Legacy exchange connector/runtime file should be removed: '.$relativePath
    );
}

$legacyRouteFiles = [
    'app/modules/kr-user/views/exchanges.php',
    'app/modules/kr-user/views/widthdraw.php',
    'app/modules/kr-trade/views/connectThirdparty.php',
    'app/modules/kr-trade/views/initWidthdraw.php',
    'app/modules/kr-admin/views/walletaddress.php',
    'app/modules/kr-admin/views/autowithdrawconfigure.php',
    'app/modules/kr-admin/src/actions/saveWallets.php',
    'app/modules/kr-admin/src/actions/saveWithdrawExchange.php',
    'app/modules/kr-payment/views/directdeposit.php',
];

foreach ($legacyRouteFiles as $relativePath) {
    fail_decommission_if(
        file_exists($root.'/'.$relativePath),
        'Legacy exchange or wallet route should be removed: '.$relativePath
    );
}

$legacyTables = [
    'balance_krypto',
    'binance_krypto',
    'bitbank_krypto',
    'bitmex_krypto',
    'bittrex_krypto',
    'btcmarket_krypto',
    'cex_krypto',
    'coinex_krypto',
    'coinspot_krypto',
    'ethfinex_krypto',
    'exchanges_withdraw_krypto',
    'exmo_krypto',
    'gateio_krypto',
    'gdax_krypto',
    'gemini_krypto',
    'hitbtc2_krypto',
    'internal_order_krypto',
    'kraken_krypto',
    'kucoin_krypto',
    'leader_board_krypto',
    'livecoin_krypto',
    'luno_krypto',
    'okcoinusd_krypto',
    'okex_krypto',
    'order_krypto',
    'poloniex_krypto',
    'quoinex_krypto',
    'thirdparty_crypto_krypto',
    'user_thirdparty_selected_krypto',
    'user_widthdraw_krypto',
    'widthdraw_history_krypto',
    'yobit_krypto',
];

foreach ($legacyTables as $table) {
    assert_decommission_not_contains(
        $installerSql,
        'CREATE TABLE `'.$table.'`',
        'Fresh installer SQL should not create legacy custodial table '.$table.'.'
    );
    assert_decommission_contains(
        $decommissionSql,
        'DROP TABLE IF EXISTS `'.$table.'`',
        'OPEN-05 SQL should provide an explicit drop for '.$table.'.'
    );
}

foreach ([
    'Archive first',
    'CREATE TABLE IF NOT EXISTS `legacy_custody_archive_manifest_krypto`',
    'INSERT INTO `legacy_custody_archive_manifest_krypto`',
    'DROP TABLE IF EXISTS `balance_krypto`',
    'DROP TABLE IF EXISTS `widthdraw_history_krypto`',
] as $needle) {
    assert_decommission_contains(
        $decommissionSql,
        $needle,
        'OPEN-05 SQL should document and execute the legacy custody decommission path.'
    );
}

foreach ([
    'changenow-open05-decommission-legacy-custody.sql',
    'archive',
    'legacy_custody_archive_manifest_krypto',
    'Fresh installs no longer create legacy custodial tables',
    'Existing installs',
] as $needle) {
    assert_decommission_contains(
        $schemaDoc,
        $needle,
        'Schema documentation should describe OPEN-05 archival and table removal.'
    );
}

foreach ([
    'OPEN-05 decommissioned',
    'app/modules/kr-trade/src',
    'changenow-open05-decommission-legacy-custody.sql',
] as $needle) {
    assert_decommission_contains(
        $migrationPlan,
        $needle,
        'Migration task plan should record the completed OPEN-05 decommission decision.'
    );
}

assert_decommission_contains(
    $dashboardRegression,
    'must not instantiate legacy exchange or balance classes',
    'Dashboard regression should verify legacy classes are no longer used.'
);
assert_decommission_contains(
    $legacyUxRegression,
    'legacy exchange runtime files are removed',
    'Legacy UX smoke test should verify runtime files are gone.'
);

if (count($failures) > 0) {
    fwrite(STDERR, "ChangeNOW legacy decommission test failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- '.$failure."\n");
    }
    exit(1);
}

echo "ChangeNOW legacy decommission checks passed.\n";

?>
