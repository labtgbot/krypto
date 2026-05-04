<?php

$root = dirname(__DIR__);
$exceptionFile = $root.'/app/modules/kr-changenow/src/ChangeNowApiException.php';
$clientFile = $root.'/app/modules/kr-changenow/src/ChangeNowApiClient.php';

if (!file_exists($exceptionFile)) {
    throw new Exception('Missing ChangeNOW API exception file: '.$exceptionFile);
}
if (!file_exists($clientFile)) {
    throw new Exception('Missing ChangeNOW API client file: '.$clientFile);
}

require_once $exceptionFile;
require_once $clientFile;

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

$requests = [];
$transport = function($method, $url, $headers, $body, $timeout, $connectTimeout) use (&$requests) {
    $requests[] = [
        'method' => $method,
        'url' => $url,
        'headers' => $headers,
        'body' => $body,
        'timeout' => $timeout,
        'connectTimeout' => $connectTimeout
    ];

    if (strpos($url, '/v2/exchange/estimated-amount') !== false && strpos($url, 'flow=standard') !== false) {
        return [
            'status' => 200,
            'headers' => [],
            'body' => json_encode([
                'fromCurrency' => 'btc',
                'fromNetwork' => 'btc',
                'toCurrency' => 'eth',
                'toNetwork' => 'eth',
                'flow' => 'standard',
                'type' => 'direct',
                'rateId' => null,
                'validUntil' => null,
                'transactionSpeedForecast' => '10-60',
                'warningMessage' => null,
                'depositFee' => 0.0001,
                'withdrawalFee' => 0.002,
                'fromAmount' => 0.1,
                'toAmount' => 1.5
            ])
        ];
    }

    if (strpos($url, '/v2/exchange/estimated-amount') !== false && strpos($url, 'flow=fixed-rate') !== false) {
        return [
            'status' => 200,
            'headers' => [],
            'body' => json_encode([
                'fromCurrency' => 'btc',
                'fromNetwork' => 'btc',
                'toCurrency' => 'eth',
                'toNetwork' => 'eth',
                'flow' => 'fixed-rate',
                'type' => 'direct',
                'rateId' => 'rate-123',
                'validUntil' => '2026-05-04T12:00:00.000Z',
                'fromAmount' => 0.2,
                'toAmount' => 3.0
            ])
        ];
    }

    if (strpos($url, '/v2/exchange/by-id') !== false) {
        return [
            'status' => 200,
            'headers' => [],
            'body' => json_encode([
                'id' => 'tx-1',
                'status' => 'finished',
                'actionsAvailable' => false,
                'fromCurrency' => 'btc',
                'fromNetwork' => 'btc',
                'toCurrency' => 'eth',
                'toNetwork' => 'eth',
                'amountFrom' => 0.1,
                'amountTo' => 1.4,
                'payinAddress' => 'payin-address',
                'payoutAddress' => 'payout-address',
                'createdAt' => '2026-05-04T11:00:00.000Z',
                'updatedAt' => '2026-05-04T11:10:00.000Z'
            ])
        ];
    }

    if (strpos($url, '/v2/validate/address') !== false) {
        return [
            'status' => 200,
            'headers' => [],
            'body' => json_encode([
                'isActivated' => null,
                'result' => false,
                'message' => 'address is not valid'
            ])
        ];
    }

    if (strpos($url, '/v2/exchanges') !== false) {
        return [
            'status' => 200,
            'headers' => [],
            'body' => json_encode([
                'count' => 1,
                'exchanges' => [
                    [
                        'exchangeId' => 'tx-2',
                        'status' => 'waiting',
                        'flow' => 'standard',
                        'validUntil' => null
                    ]
                ]
            ])
        ];
    }

    if (strpos($url, '/v2/exchange') !== false && $method === 'POST') {
        return [
            'status' => 200,
            'headers' => [],
            'body' => json_encode([
                'id' => 'tx-created',
                'fromAmount' => 0.003,
                'toAmount' => 0.052286,
                'flow' => 'standard',
                'type' => 'direct',
                'payinAddress' => 'deposit-address',
                'payoutAddress' => 'recipient-address',
                'refundAddress' => 'refund-address',
                'fromCurrency' => 'btc',
                'fromNetwork' => 'btc',
                'toCurrency' => 'eth',
                'toNetwork' => 'eth',
                'validUntil' => null
            ])
        ];
    }

    return [
        'status' => 500,
        'headers' => [],
        'body' => json_encode(['error' => 'unexpected_url', 'message' => $url])
    ];
};

$client = new ChangeNowApiClient([
    'public_api_key' => 'public-key',
    'private_api_key' => 'private-key',
    'retry_count' => 0,
    'timeout' => 9,
    'connect_timeout' => 4
], $transport);

$standardQuote = $client->_getEstimatedAmount([
    'fromCurrency' => 'btc',
    'toCurrency' => 'eth',
    'fromNetwork' => 'btc',
    'toNetwork' => 'eth',
    'fromAmount' => '0.1',
    'flow' => 'standard'
]);
assertSameValue('standard', $standardQuote['flow'], 'Standard quote flow should be normalized');
assertSameValue(null, $standardQuote['rateId'], 'Standard quote should not require a rateId');
assertSameValue(1.5, $standardQuote['toAmount'], 'Standard quote amount should be parsed');
assertSameValue('public-key', $requests[0]['headers']['x-changenow-api-key'], 'Public API key should be sent in a server-side header');
assertSameValue(9, $requests[0]['timeout'], 'Configured timeout should be passed to transport');
assertSameValue(4, $requests[0]['connectTimeout'], 'Configured connect timeout should be passed to transport');

$fixedQuote = $client->_getQuote([
    'fromCurrency' => 'btc',
    'toCurrency' => 'eth',
    'fromNetwork' => 'btc',
    'toNetwork' => 'eth',
    'fromAmount' => '0.2',
    'flow' => 'fixed-rate',
    'useRateId' => true
]);
assertSameValue('fixed-rate', $fixedQuote['flow'], 'Fixed-rate quote flow should be normalized');
assertSameValue('rate-123', $fixedQuote['rateId'], 'Fixed-rate quote should expose the rateId');
assertTrueValue(strpos($requests[1]['url'], 'useRateId=true') !== false, 'Fixed-rate quote should request a rateId when asked');

$created = $client->_createTransaction([
    'fromCurrency' => 'btc',
    'toCurrency' => 'eth',
    'fromNetwork' => 'btc',
    'toNetwork' => 'eth',
    'fromAmount' => '0.003',
    'address' => 'recipient-address',
    'refundAddress' => 'refund-address',
    'flow' => 'standard',
    'payload' => ['order' => 'internal-order-1']
]);
assertSameValue('tx-created', $created['id'], 'Created transaction id should be parsed');
assertSameValue('deposit-address', $created['payinAddress'], 'Created transaction deposit address should be parsed');
$createdBody = json_decode($requests[2]['body'], true);
assertSameValue('recipient-address', $createdBody['address'], 'Create transaction body should include payout address');
assertSameValue('internal-order-1', $createdBody['payload']['order'], 'Create transaction should pass partner payload server-side');

$status = $client->_getTransactionStatus('tx-1');
assertSameValue('finished', $status['status'], 'Transaction status should be parsed');
assertSameValue(false, $status['actionsAvailable'], 'Transaction actions availability should be parsed');

$validation = $client->_validateAddress('eth', 'bad-address', 'eth');
assertSameValue(false, $validation['result'], 'Address validation false result should not throw');
assertSameValue('address is not valid', $validation['message'], 'Address validation message should be parsed');

$list = $client->_listTransactions(['limit' => 10, 'offset' => 0, 'statuses' => ['waiting']]);
assertSameValue(1, $list['count'], 'Transaction list count should be parsed');
assertSameValue('tx-2', $list['exchanges'][0]['id'], 'Transaction list exchange id should be normalized');
assertSameValue('private-key', $requests[5]['headers']['x-changenow-api-key'], 'Transaction list should use private API key');

$validationErrorClient = new ChangeNowApiClient(['public_api_key' => 'public-key', 'retry_count' => 0], function() {
    return [
        'status' => 400,
        'headers' => [],
        'body' => json_encode(['error' => 'not_valid_params', 'message' => 'toCurrency is required'])
    ];
});
$validationException = assertExceptionClass('ChangeNowApiValidationException', function() use ($validationErrorClient) {
    $validationErrorClient->_getMinAmount([
        'fromCurrency' => 'btc',
        'toCurrency' => 'eth',
        'flow' => 'standard'
    ]);
}, 'HTTP 400 should map to a validation exception');
assertSameValue('validation', $validationException->_getType(), 'Validation exception type should be predictable');
assertSameValue('ChangeNOW rejected the request. Please check the swap parameters.', $validationException->_getUserMessage(), 'Validation exception should expose a user-safe message');

$rateLimitClient = new ChangeNowApiClient(['public_api_key' => 'public-key', 'retry_count' => 2], function() {
    return [
        'status' => 429,
        'headers' => ['Retry-After' => '3'],
        'body' => json_encode(['error' => 'too_many_requests', 'message' => 'Too many requests'])
    ];
});
$rateLimitException = assertExceptionClass('ChangeNowApiRateLimitException', function() use ($rateLimitClient) {
    $rateLimitClient->_getRange([
        'fromCurrency' => 'btc',
        'toCurrency' => 'eth',
        'flow' => 'standard'
    ]);
}, 'HTTP 429 should map to a rate-limit exception');
assertSameValue('rate_limit', $rateLimitException->_getType(), 'Rate-limit exception type should be predictable');
assertSameValue('3', $rateLimitException->_getDebugContext()['retryAfter'], 'Retry-After should be captured for callers');

$malformedClient = new ChangeNowApiClient(['public_api_key' => 'public-key', 'retry_count' => 0], function() {
    return [
        'status' => 200,
        'headers' => [],
        'body' => '{not-json'
    ];
});
assertExceptionClass('ChangeNowApiMalformedResponseException', function() use ($malformedClient) {
    $malformedClient->_getNetworkFee([
        'fromCurrency' => 'usdt',
        'toCurrency' => 'usdt',
        'fromNetwork' => 'eth',
        'toNetwork' => 'eth',
        'fromAmount' => '100'
    ]);
}, 'Malformed JSON should map to a malformed-response exception');

$attempts = 0;
$retryClient = new ChangeNowApiClient([
    'public_api_key' => 'public-key',
    'retry_count' => 1,
    'retry_delay_ms' => 0
], function() use (&$attempts) {
    $attempts++;
    if ($attempts === 1) {
        throw new Exception('connection reset');
    }
    return [
        'status' => 200,
        'headers' => [],
        'body' => json_encode(['fromCurrency' => 'btc', 'fromNetwork' => 'btc', 'toCurrency' => 'eth', 'toNetwork' => 'eth', 'flow' => 'standard', 'minAmount' => 0.01])
    ];
});
$minAmount = $retryClient->_getMinAmount(['fromCurrency' => 'btc', 'toCurrency' => 'eth', 'flow' => 'standard']);
assertSameValue(2, $attempts, 'Idempotent GET requests should retry transient transport errors');
assertSameValue(0.01, $minAmount['minAmount'], 'Retried min amount response should be parsed');

$createAttempts = 0;
$noRetryCreateClient = new ChangeNowApiClient([
    'public_api_key' => 'public-key',
    'retry_count' => 3,
    'retry_delay_ms' => 0
], function() use (&$createAttempts) {
    $createAttempts++;
    throw new Exception('connection reset');
});
assertExceptionClass('ChangeNowApiNetworkException', function() use ($noRetryCreateClient) {
    $noRetryCreateClient->_createTransaction([
        'fromCurrency' => 'btc',
        'toCurrency' => 'eth',
        'fromAmount' => '0.003',
        'address' => 'recipient-address',
        'flow' => 'standard'
    ]);
}, 'Create transaction should surface transport failures');
assertSameValue(1, $createAttempts, 'Create transaction must not be retried automatically');

$debugMessages = [];
$debugClient = new ChangeNowApiClient([
    'public_api_key' => 'public-key',
    'debug' => true,
    'debug_logger' => function($message) use (&$debugMessages) {
        $debugMessages[] = $message;
    },
    'retry_count' => 0
], $transport);
$debugClient->_createTransaction([
    'fromCurrency' => 'btc',
    'toCurrency' => 'eth',
    'fromAmount' => '0.003',
    'address' => 'recipient-address',
    'refundAddress' => 'refund-address',
    'flow' => 'standard',
    'userId' => 'user-42',
    'payload' => ['order' => 'secret-order']
]);
$debugClient->_validateAddress('eth', 'address-url-secret', 'eth');
$debugOutput = join("\n", $debugMessages);
foreach (['public-key', 'recipient-address', 'refund-address', 'user-42', 'secret-order', 'address-url-secret'] as $secretNeedle) {
    assertTrueValue(strpos($debugOutput, $secretNeedle) === false, 'Debug logging should redact '.$secretNeedle);
}
assertTrueValue(strpos($debugOutput, '[redacted]') !== false, 'Debug logging should include redaction markers');

echo "ChangeNOW API client check passed\n";
