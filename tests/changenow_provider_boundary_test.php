<?php

$root = dirname(__DIR__);
$autoload = $root.'/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

$files = [
    $root.'/app/modules/kr-changenow/src/ChangeNowProviderMode.php',
    $root.'/app/modules/kr-changenow/src/ChangeNowSwapProviderInterface.php',
    $root.'/app/modules/kr-changenow/src/ChangeNowUnavailableProvider.php',
];

foreach ($files as $file) {
    if (!file_exists($file)) {
        throw new Exception('Missing provider boundary file: '.$file);
    }
    require_once $file;
}

if (!interface_exists('ChangeNowSwapProviderInterface', false)) {
    throw new Exception('ChangeNowSwapProviderInterface was not loaded');
}

if (!class_exists('ChangeNowProviderMode', false)) {
    throw new Exception('ChangeNowProviderMode was not loaded');
}

if (!class_exists('ChangeNowUnavailableProvider', false)) {
    throw new Exception('ChangeNowUnavailableProvider was not loaded');
}

$requiredMethods = [
    '_getProviderCode',
    '_getProductModes',
    '_listCurrencies',
    '_listPairs',
    '_getQuote',
    '_createSwap',
    '_getSwapStatus',
    '_validateAddress',
];

$interface = new ReflectionClass('ChangeNowSwapProviderInterface');
foreach ($requiredMethods as $method) {
    if (!$interface->hasMethod($method)) {
        throw new Exception('Missing provider interface method: '.$method);
    }
}

foreach (['_getQuote', '_createSwap'] as $method) {
    $reflectionMethod = $interface->getMethod($method);
    foreach ($reflectionMethod->getParameters() as $parameter) {
        if (stripos($parameter->getName(), 'user') !== false) {
            throw new Exception($method.' must not require a User parameter');
        }
    }
}

$expectedModes = [
    ChangeNowProviderMode::PUBLIC_SWAP,
    ChangeNowProviderMode::OPTIONAL_ACCOUNT_HISTORY,
    ChangeNowProviderMode::ADMIN_OPERATIONS,
    ChangeNowProviderMode::LEGACY_DISABLED,
];

if (ChangeNowProviderMode::_list() !== $expectedModes) {
    throw new Exception('Unexpected ChangeNOW product mode list');
}

$provider = new ChangeNowUnavailableProvider();
if (!($provider instanceof ChangeNowSwapProviderInterface)) {
    throw new Exception('Unavailable provider does not implement the interface');
}

if ($provider->_getProviderCode() !== 'changenow') {
    throw new Exception('Unexpected provider code');
}

if (class_exists('\\ccxt\\Exchange', false)) {
    throw new Exception('Provider boundary loaded CCXT classes');
}

$appSource = file_get_contents($root.'/app/src/App/App.php');
foreach (['_changeNowProviderEnabled', '_legacyExchangeConnectionsEnabled', '_changeNowLegacyDisabledMode'] as $method) {
    if (strpos($appSource, 'function '.$method.'(') === false) {
        throw new Exception('Missing App feature flag reader: '.$method);
    }
}

$installerSql = file_get_contents($root.'/install/assets/sql/krypto.sql');
$requiredSettings = [
    "'changenow_provider_enabled', '0', 0",
    "'legacy_exchange_connections_enabled', '0', 0",
];

foreach ($requiredSettings as $settingSeed) {
    if (strpos($installerSql, $settingSeed) === false) {
        throw new Exception('Missing installer setting seed: '.$settingSeed);
    }
}

$legacyFlagMethod = strpos($appSource, "if(is_null(\$this->_getSettingsAttribute('legacy_exchange_connections_enabled'))) return false;");
if ($legacyFlagMethod === false) {
    throw new Exception('Legacy exchange connections must fail closed when the installer seed is absent');
}

echo "ChangeNOW provider boundary check passed\n";
