<?php

$root = dirname(__DIR__);
$files = [
    $root.'/app/modules/kr-changenow/src/ChangeNowApiException.php',
    $root.'/app/modules/kr-changenow/src/ChangeNowPublicSwapFlow.php',
];

foreach ($files as $file) {
    if (!file_exists($file)) {
        throw new Exception('Missing ChangeNOW public swap file: '.$file);
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

class ChangeNowPublicSwapFakeClient {
    public $validated = [];
    public $created = [];
    public $statusCalls = [];
    public $addressResult = true;

    public function _validateAddress($currency, $address, $network = null) {
        $this->validated[] = [$currency, $address, $network];
        return [
            'result' => $this->addressResult,
            'message' => ($this->addressResult ? '' : 'address is not valid'),
            'isActivated' => null,
        ];
    }

    public function _createSwap($request) {
        $this->created[] = $request;
        return [
            'id' => 'tx-created',
            'fromAmount' => $request['fromAmount'],
            'toAmount' => '0.052286',
            'flow' => $request['flow'],
            'type' => 'direct',
            'payinAddress' => 'payin-address',
            'payoutAddress' => $request['address'],
            'fromCurrency' => $request['fromCurrency'],
            'fromNetwork' => $request['fromNetwork'],
            'toCurrency' => $request['toCurrency'],
            'toNetwork' => $request['toNetwork'],
            'refundAddress' => (array_key_exists('refundAddress', $request) ? $request['refundAddress'] : null),
            'payinExtraId' => 'payin-memo',
            'payoutExtraId' => (array_key_exists('extraId', $request) ? $request['extraId'] : null),
            'refundExtraId' => (array_key_exists('refundExtraId', $request) ? $request['refundExtraId'] : null),
            'validUntil' => null,
        ];
    }

    public function _getSwapStatus($transactionId) {
        $this->statusCalls[] = $transactionId;
        return [
            'id' => $transactionId,
            'status' => 'finished',
            'actionsAvailable' => false,
            'fromCurrency' => 'btc',
            'fromNetwork' => 'btc',
            'toCurrency' => 'eth',
            'toNetwork' => 'eth',
            'amountFrom' => '0.01',
            'amountTo' => '0.052',
            'payinAddress' => 'payin-address',
            'payinExtraId' => 'payin-memo',
            'payoutAddress' => 'recipient-address',
            'createdAt' => '2026-05-04T11:00:00.000Z',
            'updatedAt' => '2026-05-04T11:10:00.000Z',
            'validUntil' => null,
        ];
    }
}

class ChangeNowPublicSwapFakeMarketData {
    public $quoteRequests = [];

    public function _getQuote($request) {
        $this->quoteRequests[] = $request;
        return [
            'fromCurrency' => $request['fromCurrency'],
            'fromNetwork' => $request['fromNetwork'],
            'toCurrency' => $request['toCurrency'],
            'toNetwork' => $request['toNetwork'],
            'flow' => $request['flow'],
            'type' => 'direct',
            'amount' => $request['fromAmount'],
            'fromAmount' => $request['fromAmount'],
            'toAmount' => '0.052286',
            'estimatedReceiveAmount' => '0.052286',
            'minAmount' => '0.001',
            'maxAmount' => '2',
            'networkFee' => '0.0001',
            'depositFee' => '0.00002',
            'withdrawalFee' => '0.0002',
            'rateId' => 'rate-1',
            'validUntil' => date('c', time() + 300),
            'transactionSpeedForecast' => '10-60',
            'warningMessage' => null,
            'cached' => false,
        ];
    }

    public function _listSourceAssets($filters = []) {
        return [
            [
                'ticker' => 'btc',
                'network' => 'btc',
                'name' => 'Bitcoin',
                'image' => '',
                'sell' => true,
                'buy' => true,
            ],
        ];
    }

    public function _listDestinationAssets($fromCurrency, $fromNetwork = null, $flow = null) {
        return [
            [
                'ticker' => 'eth',
                'network' => 'eth',
                'name' => 'Ethereum',
                'image' => '',
                'sell' => true,
                'buy' => true,
            ],
        ];
    }
}

class ChangeNowPublicSwapFakeRepository {
    public $saved = [];
    public $statusUpdates = [];

    public function _saveCreatedSwap($request, $transaction, $lookupToken, $sessionKey, $userId = null, $createdAt = null) {
        $record = array_merge($transaction, [
            'providerId' => $transaction['id'],
            'lookupTokenHash' => hash('sha256', $lookupToken),
            'sessionKey' => $sessionKey,
            'userId' => $userId,
            'status' => 'waiting',
            'createdAt' => (is_null($createdAt) ? time() : $createdAt),
            'request' => $request,
        ]);
        $this->saved[$lookupToken] = $record;
        return $record;
    }

    public function _findByLookupToken($lookupToken) {
        return (array_key_exists($lookupToken, $this->saved) ? $this->saved[$lookupToken] : null);
    }

    public function _updateStatusSnapshot($lookupToken, $statusPayload, $updatedAt = null) {
        $this->statusUpdates[] = [$lookupToken, $statusPayload];
        $this->saved[$lookupToken]['status'] = $statusPayload['status'];
        $this->saved[$lookupToken]['statusPayload'] = $statusPayload;
        return $this->saved[$lookupToken];
    }
}

$client = new ChangeNowPublicSwapFakeClient();
$marketData = new ChangeNowPublicSwapFakeMarketData();
$repository = new ChangeNowPublicSwapFakeRepository();
$flow = new ChangeNowPublicSwapFlow($client, $marketData, $repository, null, null, [
    'provider_enabled' => true,
    'enabled_flows' => ['standard', 'fixed-rate'],
    'default_flow' => 'standard',
    'default_from_asset' => 'btc',
    'default_from_network' => 'btc',
    'default_to_asset' => 'eth',
    'default_to_network' => 'eth',
    'support_email' => 'support@example.com',
    'status_base_url' => 'https://krypto.test/',
    'token_factory' => function() { return 'lookup-token-1'; },
]);

$initialState = $flow->_getInitialState();
assertSameValue(true, $initialState['providerEnabled'], 'Initial state should expose provider readiness');
assertSameValue('btc', $initialState['defaultFrom']['currency'], 'Initial state should expose default source currency');
assertSameValue(1, count($initialState['sourceAssets']), 'Initial state should include source assets');

$destinationAssets = $flow->_getDestinationAssets([
    'fromAsset' => 'btc:btc',
    'flow' => 'standard',
]);
assertSameValue('eth', $destinationAssets[0]['ticker'], 'Destination assets should be available for the selected source asset');

$quote = $flow->_getQuote([
    'fromAsset' => 'btc:btc',
    'toAsset' => 'eth:eth',
    'amount' => '0.01',
    'flow' => 'standard',
]);
assertSameValue('0.052286', $quote['estimatedReceiveAmount'], 'Public quote should return normalized estimated amount');
assertSameValue('btc', $marketData->quoteRequests[0]['fromCurrency'], 'Quote request should normalize source asset');

$created = $flow->_createSwap([
    'fromAsset' => 'btc:btc',
    'toAsset' => 'eth:eth',
    'amount' => '0.01',
    'destinationAddress' => 'recipient-address',
    'destinationExtraId' => 'destination-memo',
    'refundAddress' => 'refund-address',
    'refundExtraId' => 'refund-memo',
    'flow' => 'standard',
], 'session-key-1', 42);
assertSameValue('lookup-token-1', $created['lookupToken'], 'Created swap should return an anonymous lookup token');
assertSameValue(42, $repository->saved['lookup-token-1']['userId'], 'Logged-in user id should be linked when available');
assertSameValue('payin-address', $created['transaction']['payinAddress'], 'Created swap should expose pay-in address');
assertSameValue('payin-memo', $created['transaction']['payinExtraId'], 'Created swap should expose required pay-in memo');
assertSameValue('destination-memo', $client->created[0]['extraId'], 'Create request should send destination memo');
assertTrueValue(strpos($created['statusUrl'], 'swap_token=lookup-token-1') !== false, 'Created swap should include lookup URL');

$status = $flow->_getStatus('lookup-token-1');
assertSameValue('finished', $status['transaction']['status'], 'Status lookup should refresh provider status');
assertSameValue('tx-created', $client->statusCalls[0], 'Status lookup should use provider transaction id');

$client->addressResult = false;
$validationException = assertExceptionClass('ChangeNowApiValidationException', function() use ($flow) {
    $flow->_createSwap([
        'fromAsset' => 'btc:btc',
        'toAsset' => 'eth:eth',
        'amount' => '0.01',
        'destinationAddress' => 'bad-address',
        'flow' => 'standard',
    ], 'session-key-1', null);
}, 'Invalid destination address should stop transaction creation');
assertSameValue('Destination address is not valid.', $validationException->_getUserMessage(), 'Address validation error should be user-safe');

assertExceptionClass('ChangeNowApiValidationException', function() use ($flow) {
    $flow->_createSwap([
        'fromAsset' => 'btc:btc',
        'toAsset' => 'eth:eth',
        'amount' => '0.01',
        'destinationAddress' => 'recipient-address',
        'flow' => 'fixed-rate',
        'rateId' => 'expired-rate',
        'validUntil' => date('c', time() - 30),
    ], 'session-key-1', null);
}, 'Expired fixed-rate quote should be rejected before create');

assertExceptionClass('ChangeNowApiValidationException', function() use ($flow) {
    $flow->_getStatus('');
}, 'Empty lookup token should not be accepted');

echo "ChangeNOW public swap flow check passed\n";

?>
