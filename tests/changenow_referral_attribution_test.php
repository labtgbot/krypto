<?php

$root = dirname(__DIR__);
$files = [
    $root.'/app/modules/kr-changenow/src/ChangeNowApiException.php',
    $root.'/app/modules/kr-changenow/src/ChangeNowReferralAttribution.php',
    $root.'/app/modules/kr-changenow/src/ChangeNowPublicSwapFlow.php',
];

foreach ($files as $file) {
    if (!file_exists($file)) {
        throw new Exception('Missing ChangeNOW referral attribution file: '.$file);
    }
    require_once $file;
}

function assertReferralSame($expected, $actual, $message) {
    if ($expected !== $actual) {
        throw new Exception($message.' Expected '.var_export($expected, true).', got '.var_export($actual, true));
    }
}

function assertReferralTrue($condition, $message) {
    if (!$condition) {
        throw new Exception($message);
    }
}

function assertReferralMissing($key, $array, $message) {
    if (is_array($array) && array_key_exists($key, $array)) {
        throw new Exception($message.' Unexpected key '.$key.' in '.var_export($array, true));
    }
}

class ChangeNowReferralFakeClient {
    public $created = [];

    public function _validateAddress($currency, $address, $network = null) {
        return [
            'result' => true,
            'message' => '',
        ];
    }

    public function _createSwap($request) {
        $this->created[] = $request;
        return [
            'id' => 'tx-'.count($this->created),
            'fromAmount' => $request['fromAmount'],
            'toAmount' => '0.052286',
            'flow' => $request['flow'],
            'payinAddress' => 'payin-address',
            'payoutAddress' => $request['address'],
            'fromCurrency' => $request['fromCurrency'],
            'fromNetwork' => $request['fromNetwork'],
            'toCurrency' => $request['toCurrency'],
            'toNetwork' => $request['toNetwork'],
            'validUntil' => null,
        ];
    }
}

class ChangeNowReferralFakeMarketData {
    public function _getQuote($request) {
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
            'rateId' => null,
            'validUntil' => null,
            'cached' => false,
        ];
    }
}

class ChangeNowReferralFakeRepository {
    public $saved = [];

    public function _saveCreatedSwap($request, $transaction, $lookupToken, $sessionKey, $userId = null, $createdAt = null) {
        $record = array_merge($transaction, [
            'providerId' => $transaction['id'],
            'userId' => $userId,
            'status' => 'waiting',
            'request' => $request,
            'referralAttribution' => (array_key_exists('referralAttribution', $request) ? $request['referralAttribution'] : []),
        ]);
        $this->saved[$lookupToken] = $record;
        return $record;
    }
}

$ownerMap = [
    'creator' => 7,
    'partner' => 9,
    'self' => 42,
];
$ownerResolver = function($code) use ($ownerMap) {
    return (array_key_exists($code, $ownerMap) ? $ownerMap[$code] : null);
};

$session = [];
$captured = ChangeNowReferralAttribution::_captureLanding([
    'ref' => 'Creator',
    'utm_source' => 'newsletter',
    'utm_medium' => 'email',
    'utm_campaign' => 'spring-launch',
], $session, $ownerResolver, 1770000000);

assertReferralSame('creator', $session[ChangeNowReferralAttribution::SESSION_REFERRAL_CODE_KEY], 'Landing capture should normalize and persist a valid referral code');
assertReferralSame('spring-launch', $session[ChangeNowReferralAttribution::SESSION_UTM_KEY]['campaign'], 'Landing capture should persist UTM campaign data');
assertReferralSame('creator', $captured['referralCode'], 'Capture result should expose the normalized referral code');

ChangeNowReferralAttribution::_captureLanding(['ref' => 'missing-code'], $session, $ownerResolver, 1770000005);
assertReferralSame('creator', $session[ChangeNowReferralAttribution::SESSION_REFERRAL_CODE_KEY], 'Invalid referral codes should not overwrite an existing captured source');

$client = new ChangeNowReferralFakeClient();
$repository = new ChangeNowReferralFakeRepository();
$flow = new ChangeNowPublicSwapFlow($client, new ChangeNowReferralFakeMarketData(), $repository, null, null, [
    'provider_enabled' => true,
    'enabled_flows' => ['standard', 'fixed-rate'],
    'default_flow' => 'standard',
    'default_from_asset' => 'btc',
    'default_from_network' => 'btc',
    'default_to_asset' => 'eth',
    'default_to_network' => 'eth',
    'support_email' => 'support@example.com',
    'status_base_url' => 'https://krypto.test/',
    'token_factory' => function() { return 'lookup-referral-1'; },
    'referral_session' => $session,
    'change_now_referral_link_id' => 'changenow-partner-123',
    'referral_owner_resolver' => $ownerResolver,
    'attribution_time_factory' => function() { return 1770000010; },
]);

$quote = $flow->_getQuote([
    'fromAsset' => 'btc:btc',
    'toAsset' => 'eth:eth',
    'amount' => '0.01',
    'flow' => 'standard',
], 'session-key-1');

$created = $flow->_createSwap([
    'fromAsset' => 'btc:btc',
    'toAsset' => 'eth:eth',
    'amount' => '0.01',
    'quoteId' => $quote['quoteId'],
    'destinationAddress' => 'recipient-address',
    'flow' => 'standard',
], 'session-key-1', null);

$payload = $client->created[0]['payload']['kryptoReferralAttribution'];
assertReferralSame('creator', $payload['internal']['code'], 'Create payload should include the captured internal referral code for anonymous swaps');
assertReferralSame(7, $payload['internal']['ownerUserId'], 'Create payload should retain the internal referrer owner when known');
assertReferralSame('changenow-partner-123', $payload['changeNow']['referralLinkId'], 'Create payload should include ChangeNOW partner attribution');
assertReferralSame('spring-launch', $payload['utm']['campaign'], 'Create payload should include captured UTM attribution');
assertReferralSame('pending_provider_confirmation', $payload['commissionState'], 'Referral rewards should remain pending until provider/admin confirmation');
assertReferralSame($payload, $repository->saved['lookup-referral-1']['request']['referralAttribution'], 'Saved transaction request should preserve the same attribution sent to ChangeNOW');
assertReferralMissing('referralAttribution', $created['transaction'], 'Public transaction responses should not expose referral attribution internals');

$clientLoggedIn = new ChangeNowReferralFakeClient();
$repositoryLoggedIn = new ChangeNowReferralFakeRepository();
$flowLoggedIn = new ChangeNowPublicSwapFlow($clientLoggedIn, new ChangeNowReferralFakeMarketData(), $repositoryLoggedIn, null, null, [
    'provider_enabled' => true,
    'enabled_flows' => ['standard'],
    'default_flow' => 'standard',
    'token_factory' => function() { return 'lookup-referral-2'; },
    'referral_session' => [
        ChangeNowReferralAttribution::SESSION_REFERRAL_CODE_KEY => 'creator',
    ],
    'referral_owner_resolver' => $ownerResolver,
    'attribution_time_factory' => function() { return 1770000020; },
]);

$loggedInQuote = $flowLoggedIn->_getQuote([
    'fromAsset' => 'btc:btc',
    'toAsset' => 'eth:eth',
    'amount' => '0.02',
    'flow' => 'standard',
], 'session-key-2');

$flowLoggedIn->_createSwap([
    'fromAsset' => 'btc:btc',
    'toAsset' => 'eth:eth',
    'amount' => '0.02',
    'quoteId' => $loggedInQuote['quoteId'],
    'destinationAddress' => 'recipient-address',
    'flow' => 'standard',
    'ref' => 'partner',
], 'session-key-2', 42);

$loggedInPayload = $clientLoggedIn->created[0]['payload']['kryptoReferralAttribution'];
assertReferralSame('42', $clientLoggedIn->created[0]['userId'], 'Logged-in swaps should keep the account userId for ChangeNOW userId attribution');
assertReferralSame('partner', $loggedInPayload['internal']['code'], 'A valid request referral code should override an older session referral code');
assertReferralSame('42', $loggedInPayload['loggedInUserId'], 'Attribution payload should record the logged-in user separately from the referrer');

$selfAttribution = ChangeNowReferralAttribution::_fromRequest([
    'ref' => 'self',
], [], [
    'loggedInUserId' => 42,
    'referralCodeOwnerResolver' => $ownerResolver,
    'now' => 1770000030,
]);

assertReferralMissing('internal', $selfAttribution, 'A user should not receive internal referral attribution from their own code');
assertReferralSame('self_referral', $selfAttribution['blockedInternalReferral']['reason'], 'Self-referral attempts should be visible for support review without creating a reward');

$selfOverrideAttribution = ChangeNowReferralAttribution::_fromRequest([
    'ref' => 'self',
], [
    ChangeNowReferralAttribution::SESSION_REFERRAL_CODE_KEY => 'creator',
], [
    'loggedInUserId' => 42,
    'referralCodeOwnerResolver' => $ownerResolver,
    'now' => 1770000035,
]);

assertReferralMissing('internal', $selfOverrideAttribution, 'A blocked request referral should not fall back to an older session referral');
assertReferralSame('self_referral', $selfOverrideAttribution['blockedInternalReferral']['reason'], 'The blocked request referral should remain visible for support review');

$noReferralAttribution = ChangeNowReferralAttribution::_fromRequest([], [], [
    'loggedInUserId' => 42,
    'referralCodeOwnerResolver' => $ownerResolver,
    'now' => 1770000040,
]);

assertReferralSame([], $noReferralAttribution, 'Logged-in user attribution alone should not turn an ordinary swap into referral traffic');

echo "ChangeNOW referral attribution check passed\n";

?>
