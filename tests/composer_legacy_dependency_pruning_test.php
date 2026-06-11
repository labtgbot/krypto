<?php

$root = dirname(__DIR__);
$failures = [];

function composer_pruning_fail($message) {
    global $failures;
    $failures[] = $message;
}

function composer_pruning_assert($condition, $message) {
    if (!$condition) {
        composer_pruning_fail($message);
    }
}

function composer_pruning_read_json($path, $label) {
    composer_pruning_assert(is_file($path), $label.' should exist at '.$path);
    if (!is_file($path)) {
        return [];
    }

    $decoded = json_decode(file_get_contents($path), true);
    composer_pruning_assert(is_array($decoded), $label.' should contain valid JSON.');
    return is_array($decoded) ? $decoded : [];
}

function composer_pruning_collect_files($paths) {
    $files = [];

    foreach ($paths as $path) {
        if (is_file($path)) {
            $files[] = $path;
            continue;
        }

        if (!is_dir($path)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && substr($file->getFilename(), -4) === '.php') {
                $files[] = $file->getPathname();
            }
        }
    }

    sort($files);
    return $files;
}

$composer = composer_pruning_read_json($root.'/composer.json', 'composer.json');
$lock = composer_pruning_read_json($root.'/composer.lock', 'composer.lock');
$reportPath = $root.'/docs/composer-dependency-audit-2026-05-09.md';
$report = is_file($reportPath) ? file_get_contents($reportPath) : '';

$requiredPackages = isset($composer['require']) && is_array($composer['require']) ? $composer['require'] : [];
$lockedPackages = [];
foreach (array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []) as $package) {
    if (isset($package['name'])) {
        $lockedPackages[$package['name']] = true;
    }
}

$retiredRootPackages = [
    'sigismund/coinpayments',
    'bert-w/coinpayments-api',
    'stripe/stripe-php',
    'samrap/gemini',
    'react/stream',
    'react/socket',
    'react/promise-timer',
    'react/promise',
    'react/event-loop',
    'react/dns',
    'react/cache',
    'ratchet/rfc6455',
    'ratchet/pawl',
    'psr/log',
    'php-http/promise',
    'php-http/message-factory',
    'php-http/message',
    'php-http/httplug',
    'php-http/guzzle6-adapter',
    'php-http/discovery',
    'paypal/rest-api-sdk-php',
    'paragonie/random_compat',
    'mrteye/gdax',
    'mollie/mollie-api-php',
    'jaggedsoft/php-binance-api',
    'hanischit/kraken-api',
    'evenement/evenement',
    'curl/curl',
    'coingate/coingate-php',
    'coinbase/coinbase',
    'clue/stream-filter',
    'ccxt/ccxt',
    'guzzlehttp/guzzle',
    'league/omnipay',
    'coingate/omnipay-coingate',
    'omnipay/stripe',
    'omnipay/common',
    'omnipay/paypal',
    'aferrandini/phpqrcode',
    'milon/barcode',
    'payer/sdk',
    'cmpayments/iban',
    'fadion/fixerio',
    'php-curl-class/php-curl-class',
    'infiniweb/fixer-api-php',
    'gladcodes/ravephp',
    'aleksandrzhiliaev/omnipay-advcash',
    'codename065/coinbase-commerce',
    'dg/rss-php',
    'vinelab/rss',
    'simplepie/simplepie',
    'ronmelkhior/coinpayments-ipn',
    'yabacon/paystack-php',
    'ziplr/php-qr-code',
];

$retiredLockedPackages = array_diff($retiredRootPackages, [
    'guzzlehttp/guzzle',
    'paragonie/random_compat',
]);

$retainedRootPackages = [
    'symfony/polyfill-mbstring',
    'robthree/twofactorauth',
    'phpmailer/phpmailer',
    'mobiledetect/mobiledetectlib',
    'liquid/liquid',
    'league/oauth2-google',
    'league/oauth2-facebook',
    'league/oauth2-client',
    'google/recaptcha',
    'oceanapplications/currencylayer-php-client',
];

foreach ($retiredRootPackages as $package) {
    composer_pruning_assert(
        !array_key_exists($package, $requiredPackages),
        $package.' should not remain a root Composer requirement after OPEN-07 cleanup.'
    );
}

foreach ($retiredLockedPackages as $package) {
    composer_pruning_assert(
        !array_key_exists($package, $lockedPackages),
        $package.' should not remain in composer.lock after OPEN-07 cleanup.'
    );
}

foreach ($retainedRootPackages as $package) {
    composer_pruning_assert(
        array_key_exists($package, $requiredPackages),
        $package.' should remain documented as an actively used runtime dependency.'
    );
}

$primaryRuntimeFiles = composer_pruning_collect_files([
    $root.'/index.php',
    $root.'/dashboard.php',
    $root.'/app/src',
    $root.'/app/modules/kr-changenow',
    $root.'/app/views/changenow',
    $root.'/scripts/changenow_retention.php',
]);

$primaryRuntimeSource = '';
foreach ($primaryRuntimeFiles as $file) {
    $primaryRuntimeSource .= "\n".file_get_contents($file);
}

$retiredRuntimeTokens = [
    '\\ccxt\\',
    'Binance\\API',
    'Samrap\\Gemini',
    'HanischIt\\KrakenApi',
    '\\PayPal\\',
    '\\Stripe\\',
    '\\Mollie\\Api',
    '\\CoinGate\\',
    '\\Coinbase\\Wallet',
    '\\WPDMPP\\Coinbase\\Commerce',
    '\\Omnipay\\',
    '\\Rave\\Payment',
    'Yabacon\\',
    'BertW\\',
    'Sigismund\\',
    '\\PHPQRCode\\',
    'Milon\\Barcode',
    'Payer\\Sdk',
    'CMPayments\\',
    'Fadion\\Fixerio',
    'InfiniWeb\\FixerAPI',
    'SimplePie',
    'Vinelab\\Rss',
    'Ratchet\\',
    'React\\',
    'Http\\Adapter\\Guzzle6',
    'Http\\Client\\',
    'Curl\\Curl',
];

foreach ($retiredRuntimeTokens as $token) {
    composer_pruning_assert(
        strpos($primaryRuntimeSource, $token) === false,
        'Primary ChangeNOW/runtime source should not reference retired Composer token '.$token.'.'
    );
}

foreach ([
    'OPEN-07 cleanup results',
    'Removed legacy Composer packages',
    'Retained runtime dependencies',
] as $needle) {
    composer_pruning_assert(
        strpos($report, $needle) !== false,
        'Composer dependency audit should document OPEN-07 cleanup. Missing: '.$needle
    );
}

if (count($failures) > 0) {
    fwrite(STDERR, "Composer legacy dependency pruning test failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- '.$failure."\n");
    }
    exit(1);
}

echo "Composer legacy dependency pruning checks passed.\n";

?>
