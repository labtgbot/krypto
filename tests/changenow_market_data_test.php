<?php

$root = dirname(__DIR__);
$files = [
    $root.'/app/modules/kr-changenow/src/ChangeNowApiException.php',
    $root.'/app/modules/kr-changenow/src/ChangeNowSettings.php',
    $root.'/app/modules/kr-changenow/src/ChangeNowMarketData.php',
];

foreach ($files as $file) {
    if (!file_exists($file)) {
        throw new Exception('Missing ChangeNOW market data file: '.$file);
    }
    require_once $file;
}

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

function assertExceptionClass($expectedClass, $callable, $message) {
    try {
        $callable();
    } catch (Exception $e) {
        if (!($e instanceof $expectedClass)) {
            throw new Exception($message.' Expected '.$expectedClass.', got '.get_class($e).': '.$e->getMessage());
        }
        return $e;
    }

    throw new Exception($message.' Expected exception '.$expectedClass.' was not thrown');
}

class ChangeNowMarketDataFakeClient {
    public $quoteCalls = 0;
    public $rangeCalls = 0;
    public $feeCalls = 0;

    public function _listCurrencies($filters = []) {
        return [
            [
                'ticker' => 'btc',
                'name' => 'Bitcoin',
                'network' => 'btc',
                'legacyTicker' => 'btc',
                'image' => 'https://content.changenow.io/uploads/btc.svg',
                'isFiat' => false,
                'featured' => true,
                'isStable' => false,
                'supportsFixedRate' => true,
                'tokenContract' => null,
                'buy' => true,
                'sell' => true,
            ],
            [
                'ticker' => 'usdt',
                'name' => 'Tether USD (Ethereum)',
                'network' => 'eth',
                'legacyTicker' => 'usdterc20',
                'image' => 'https://content.changenow.io/uploads/usdterc20.svg',
                'isFiat' => false,
                'featured' => false,
                'isStable' => true,
                'supportsFixedRate' => true,
                'tokenContract' => '0xdac17f958d2ee523a2206206994597c13d831ec7',
                'buy' => true,
                'sell' => true,
            ],
            [
                'ticker' => 'usdt',
                'name' => 'Tether USD (Tron)',
                'network' => 'trx',
                'legacyTicker' => 'usdttrc20',
                'image' => 'https://content.changenow.io/uploads/usdttrc20.svg',
                'isFiat' => false,
                'featured' => false,
                'isStable' => true,
                'supportsFixedRate' => true,
                'tokenContract' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
                'buy' => true,
                'sell' => true,
            ],
            [
                'ticker' => 'eth',
                'name' => 'Ethereum',
                'network' => 'eth',
                'legacyTicker' => 'eth',
                'image' => 'https://content.changenow.io/uploads/eth.svg',
                'isFiat' => false,
                'featured' => true,
                'isStable' => false,
                'supportsFixedRate' => true,
                'tokenContract' => null,
                'buy' => true,
                'sell' => true,
            ],
        ];
    }

    public function _listPairs($filters = []) {
        $flow = (array_key_exists('flow', $filters) ? $filters['flow'] : 'standard');
        if ($flow === 'fixed-rate') {
            return [
                [
                    'fromCurrency' => 'btc',
                    'fromNetwork' => 'btc',
                    'toCurrency' => 'eth',
                    'toNetwork' => 'eth',
                    'flow' => 'fixed-rate',
                ],
            ];
        }

        return [
            [
                'fromCurrency' => 'btc',
                'fromNetwork' => 'btc',
                'toCurrency' => 'usdt',
                'toNetwork' => 'eth',
                'flow' => 'standard',
            ],
            [
                'fromCurrency' => 'btc',
                'fromNetwork' => 'btc',
                'toCurrency' => 'usdt',
                'toNetwork' => 'trx',
                'flow' => 'standard',
            ],
        ];
    }

    public function _getRange($request) {
        $this->rangeCalls++;
        return [
            'fromCurrency' => $request['fromCurrency'],
            'fromNetwork' => $request['fromNetwork'],
            'toCurrency' => $request['toCurrency'],
            'toNetwork' => $request['toNetwork'],
            'flow' => $request['flow'],
            'minAmount' => '0.001',
            'maxAmount' => '2.5',
        ];
    }

    public function _getQuote($request) {
        $this->quoteCalls++;
        return [
            'fromCurrency' => $request['fromCurrency'],
            'fromNetwork' => $request['fromNetwork'],
            'toCurrency' => $request['toCurrency'],
            'toNetwork' => $request['toNetwork'],
            'flow' => $request['flow'],
            'type' => 'direct',
            'rateId' => 'rate-'.$this->quoteCalls,
            'validUntil' => '2026-05-04T12:00:00.000Z',
            'transactionSpeedForecast' => '10-60',
            'warningMessage' => null,
            'depositFee' => '0.00002',
            'withdrawalFee' => '1.5',
            'fromAmount' => $request['fromAmount'],
            'toAmount' => '245.75',
        ];
    }

    public function _getNetworkFee($request) {
        $this->feeCalls++;
        return ['estimatedFee' => '1.25'];
    }
}

class ChangeNowMarketDataFakeRepository {
    public $assets = [];
    public $pairs = [];
    public $quoteCache = [];
    public $syncEvents = [];

    public function _replaceAssets($assets, $syncedAt = null) {
        foreach ($this->assets as $key => $asset) {
            $this->assets[$key]['providerActive'] = false;
        }

        foreach ($assets as $asset) {
            $key = $asset['ticker'].':'.$asset['network'];
            if (array_key_exists($key, $this->assets)) {
                $asset['adminEnabled'] = $this->assets[$key]['adminEnabled'];
            }
            $asset['providerActive'] = true;
            $this->assets[$key] = $asset;
        }
    }

    public function _replacePairs($pairs, $syncedAt = null, $flows = []) {
        foreach ($this->pairs as $key => $pair) {
            $this->pairs[$key]['providerActive'] = false;
        }

        foreach ($pairs as $pair) {
            $key = $pair['fromCurrency'].':'.$pair['fromNetwork'].'>'.$pair['toCurrency'].':'.$pair['toNetwork'].':'.$pair['flow'];
            if (array_key_exists($key, $this->pairs)) {
                $pair['adminEnabled'] = $this->pairs[$key]['adminEnabled'];
            }
            $pair['providerActive'] = true;
            $this->pairs[$key] = $pair;
        }
    }

    public function _recordSyncStart($syncKey, $startedAt) {
        $this->syncEvents[] = ['start', $syncKey, $startedAt];
    }

    public function _recordSyncFinish($syncKey, $status, $message, $assetsCount, $pairsCount, $finishedAt) {
        $this->syncEvents[] = ['finish', $syncKey, $status, $message, $assetsCount, $pairsCount, $finishedAt];
    }

    public function _listSourceAssets($filters = []) {
        $sources = [];
        foreach ($this->pairs as $pair) {
            if (!$pair['providerActive'] || !$pair['adminEnabled']) continue;
            if (array_key_exists('flow', $filters) && $filters['flow'] !== $pair['flow']) continue;
            $key = $pair['fromCurrency'].':'.$pair['fromNetwork'];
            if (!array_key_exists($key, $this->assets)) continue;
            $asset = $this->assets[$key];
            if (!$asset['providerActive'] || !$asset['adminEnabled'] || !$asset['sell']) continue;
            $sources[$key] = $asset;
        }
        return array_values($sources);
    }

    public function _listDestinationAssets($fromCurrency, $fromNetwork, $flow = null) {
        $destinations = [];
        foreach ($this->pairs as $pair) {
            if (!$pair['providerActive'] || !$pair['adminEnabled']) continue;
            if ($pair['fromCurrency'] !== $fromCurrency || $pair['fromNetwork'] !== $fromNetwork) continue;
            if (!is_null($flow) && $pair['flow'] !== $flow) continue;
            $key = $pair['toCurrency'].':'.$pair['toNetwork'];
            if (!array_key_exists($key, $this->assets)) continue;
            $asset = $this->assets[$key];
            if (!$asset['providerActive'] || !$asset['adminEnabled'] || !$asset['buy']) continue;
            $destinations[$key] = $asset;
        }
        return array_values($destinations);
    }

    public function _isAssetEnabled($ticker, $network) {
        $key = $ticker.':'.$network;
        return array_key_exists($key, $this->assets) && $this->assets[$key]['providerActive'] && $this->assets[$key]['adminEnabled'];
    }

    public function _isPairEnabled($fromCurrency, $fromNetwork, $toCurrency, $toNetwork, $flow) {
        $key = $fromCurrency.':'.$fromNetwork.'>'.$toCurrency.':'.$toNetwork.':'.$flow;
        return array_key_exists($key, $this->pairs) && $this->pairs[$key]['providerActive'] && $this->pairs[$key]['adminEnabled'];
    }

    public function _savePairLimits($fromCurrency, $fromNetwork, $toCurrency, $toNetwork, $flow, $minAmount, $maxAmount, $updatedAt = null) {
        $key = $fromCurrency.':'.$fromNetwork.'>'.$toCurrency.':'.$toNetwork.':'.$flow;
        $this->pairs[$key]['minAmount'] = $minAmount;
        $this->pairs[$key]['maxAmount'] = $maxAmount;
    }

    public function _getQuoteCache($cacheKey, $now = null) {
        if (!array_key_exists($cacheKey, $this->quoteCache)) return null;
        if ($this->quoteCache[$cacheKey]['expiresAt'] <= $now) return null;
        return $this->quoteCache[$cacheKey]['payload'];
    }

    public function _saveQuoteCache($cacheKey, $request, $payload, $expiresAt, $createdAt = null) {
        $this->quoteCache[$cacheKey] = [
            'request' => $request,
            'payload' => $payload,
            'expiresAt' => $expiresAt,
        ];
    }

    public function _setAssetAdminEnabled($ticker, $network, $enabled) {
        $key = $ticker.':'.$network;
        $this->assets[$key]['adminEnabled'] = $enabled;
    }

    public function _setPairAdminEnabled($fromCurrency, $fromNetwork, $toCurrency, $toNetwork, $flow, $enabled) {
        $key = $fromCurrency.':'.$fromNetwork.'>'.$toCurrency.':'.$toNetwork.':'.$flow;
        $this->pairs[$key]['adminEnabled'] = $enabled;
    }
}

$client = new ChangeNowMarketDataFakeClient();
$repository = new ChangeNowMarketDataFakeRepository();
$marketData = new ChangeNowMarketData($client, $repository, null, [
    'enabled_flows' => ['standard', 'fixed-rate'],
    'quote_cache_ttl' => 30,
]);

$sync = $marketData->_sync(['standard', 'fixed-rate']);
assertSameValue(4, $sync['assets'], 'Sync should preserve every network-specific asset');
assertSameValue(3, $sync['pairs'], 'Sync should preserve pair availability by flow and network');
assertTrueValue(isset($repository->assets['usdt:eth']), 'USDT on Ethereum should be stored separately');
assertTrueValue(isset($repository->assets['usdt:trx']), 'USDT on Tron should be stored separately');
assertSameValue('TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t', $repository->assets['usdt:trx']['tokenContract'], 'Token contract metadata should be preserved');
assertSameValue(false, class_exists('\\ccxt\\Exchange', false), 'ChangeNOW market sync must not load CCXT exchange classes');

$sources = $marketData->_listSourceAssets(['flow' => 'standard']);
assertSameValue(1, count($sources), 'Only BTC should be listed as a standard-flow source in the fixture');
assertSameValue('btc', $sources[0]['ticker'], 'Source asset ticker should be normalized');

$destinations = $marketData->_listDestinationAssets('btc', 'btc', 'standard');
assertSameValue(2, count($destinations), 'Network-specific USDT destinations should both be selectable');

$marketData->_setAssetEnabled('usdt', 'trx', false);
$destinationsAfterDisable = $marketData->_listDestinationAssets('btc', 'btc', 'standard');
assertSameValue(1, count($destinationsAfterDisable), 'Locally disabled assets should disappear from selectable destinations');
assertSameValue('eth', $destinationsAfterDisable[0]['network'], 'Ethereum USDT should remain after disabling Tron USDT');

$quote = $marketData->_getQuote([
    'fromCurrency' => 'BTC',
    'fromNetwork' => 'BTC',
    'toCurrency' => 'USDT',
    'toNetwork' => 'ETH',
    'flow' => 'standard',
    'fromAmount' => '0.01',
]);

assertSameValue('0.01', $quote['amount'], 'Quote should expose the requested amount');
assertSameValue('245.75', $quote['estimatedReceiveAmount'], 'Quote should expose estimated receive amount');
assertSameValue('0.001', $quote['minAmount'], 'Quote should include provider min amount');
assertSameValue('2.5', $quote['maxAmount'], 'Quote should include provider max amount');
assertSameValue('1.25', $quote['networkFee'], 'Quote should include network fee when available');
assertSameValue('rate-1', $quote['rateId'], 'Quote should expose provider rate ID');
assertSameValue('2026-05-04T12:00:00.000Z', $quote['validUntil'], 'Quote should expose provider expiry');
assertSameValue(false, $quote['cached'], 'First quote should be fetched from the provider');
assertSameValue(1, $client->quoteCalls, 'Provider quote should be called once');

$cachedQuote = $marketData->_getQuote([
    'from_currency' => 'btc',
    'from_network' => 'btc',
    'to_currency' => 'usdt',
    'to_network' => 'eth',
    'flow' => 'standard',
    'from_amount' => '0.01',
]);
assertSameValue(true, $cachedQuote['cached'], 'Second quote should come from short-lived cache');
assertSameValue(1, $client->quoteCalls, 'Cached quote should not call the provider again');

$cacheKey = ChangeNowMarketData::_quoteCacheKey(ChangeNowMarketData::_normalizeQuoteRequest([
    'fromCurrency' => 'btc',
    'fromNetwork' => 'btc',
    'toCurrency' => 'usdt',
    'toNetwork' => 'eth',
    'flow' => 'standard',
    'fromAmount' => '0.01',
]));
$repository->quoteCache[$cacheKey]['expiresAt'] = time() - 1;
$expiredQuote = $marketData->_getQuote([
    'fromCurrency' => 'btc',
    'fromNetwork' => 'btc',
    'toCurrency' => 'usdt',
    'toNetwork' => 'eth',
    'flow' => 'standard',
    'fromAmount' => '0.01',
]);
assertSameValue(false, $expiredQuote['cached'], 'Expired cache should be refreshed');
assertSameValue(2, $client->quoteCalls, 'Expired quote should call the provider again');

$standardOnlyMarketData = new ChangeNowMarketData($client, $repository, null, [
    'enabled_flows' => ['standard'],
]);
assertExceptionClass('ChangeNowApiValidationException', function() use ($standardOnlyMarketData) {
    $standardOnlyMarketData->_getQuote([
        'fromCurrency' => 'btc',
        'fromNetwork' => 'btc',
        'toCurrency' => 'eth',
        'toNetwork' => 'eth',
        'flow' => 'fixed-rate',
        'fromAmount' => '0.01',
    ]);
}, 'Disabled flows should be rejected before provider calls');

echo "ChangeNOW market data check passed\n";

?>
