<?php

$root = dirname(__DIR__);
$files = [
    $root.'/app/modules/kr-changenow/src/ChangeNowApiException.php',
    $root.'/app/modules/kr-changenow/src/ChangeNowPublicSwapFlow.php',
];

foreach ($files as $file) {
    if (!file_exists($file)) {
        throw new Exception('Missing ChangeNOW lifecycle file: '.$file);
    }
    require_once $file;
}

function assertLifecycleSame($expected, $actual, $message) {
    if ($expected !== $actual) {
        throw new Exception($message.' Expected '.var_export($expected, true).', got '.var_export($actual, true));
    }
}

function assertLifecycleTrue($condition, $message) {
    if (!$condition) {
        throw new Exception($message);
    }
}

function assertLifecycleMissing($key, $array, $message) {
    if (is_array($array) && array_key_exists($key, $array)) {
        throw new Exception($message.' Unexpected key: '.$key);
    }
}

function assertLifecycleException($expectedClass, $callable, $message) {
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

class ChangeNowLifecycleFakeClient {
    public $statusCalls = [];
    public $refundCalls = [];
    public $continueCalls = [];

    public function _getSwapStatus($transactionId) {
        $this->statusCalls[] = $transactionId;
        return [
            'id' => $transactionId,
            'status' => 'waiting',
            'actionsAvailable' => [
                'refund' => ($transactionId === 'tx-anon'),
                'continue' => ($transactionId === 'tx-anon'),
            ],
            'fromCurrency' => 'btc',
            'fromNetwork' => 'btc',
            'toCurrency' => 'eth',
            'toNetwork' => 'eth',
            'amountFrom' => '0.01',
            'amountTo' => '0.052',
            'payinAddress' => 'payin-'.$transactionId,
            'payoutAddress' => 'payout-'.$transactionId,
            'updatedAt' => '2026-05-04T13:00:00.000Z',
        ];
    }

    public function _refundTransaction($transactionId, $address, $extraId = null) {
        $this->refundCalls[] = [$transactionId, $address, $extraId];
        return [
            'id' => $transactionId,
            'action' => 'refund',
            'result' => true,
        ];
    }

    public function _continueTransaction($transactionId) {
        $this->continueCalls[] = [$transactionId];
        return [
            'id' => $transactionId,
            'action' => 'continue',
            'result' => true,
        ];
    }
}

class ChangeNowLifecycleFakeMarketData {
    public function _listSourceAssets($filters = []) { return []; }
    public function _listDestinationAssets($fromCurrency, $fromNetwork = null, $flow = null) { return []; }
}

class ChangeNowLifecycleFakeRepository {
    public $records = [];
    public $statusUpdates = [];
    public $events = [];

    public function __construct() {
        $this->records = [
            'token-anon' => $this->record('tx-anon', null, 'waiting', true, true),
            'token-user-42' => $this->record('tx-user-42', 42, 'waiting', false, false),
            'token-user-99' => $this->record('tx-user-99', 99, 'waiting', true, false),
        ];
    }

    public function _findByLookupToken($lookupToken) {
        return (array_key_exists($lookupToken, $this->records) ? $this->records[$lookupToken] : null);
    }

    public function _findByProviderId($providerId) {
        foreach ($this->records as $record) {
            if ($record['providerId'] === $providerId) return $record;
        }
        return null;
    }

    public function _listByUser($userId, $limit = 50) {
        $result = [];
        foreach ($this->records as $record) {
            if ((int) $record['userId'] === (int) $userId) $result[] = $record;
        }
        return array_slice($result, 0, $limit);
    }

    public function _updateStatusSnapshot($lookupToken, $statusPayload, $updatedAt = null) {
        $this->statusUpdates[] = [$lookupToken, $statusPayload];
        $previous = $this->records[$lookupToken];
        $record = array_merge($previous, [
            'status' => $statusPayload['status'],
            'rawStatus' => $statusPayload,
            'availableActions' => [
                'refund' => (bool) $statusPayload['actionsAvailable']['refund'],
                'continue' => (bool) $statusPayload['actionsAvailable']['continue'],
            ],
        ]);
        $this->records[$lookupToken] = $record;

        if ($previous['status'] !== $record['status']) {
            $this->_recordEvent($record['providerId'], 'status', $record['status'], null, 'system', '', $statusPayload);
        }

        return $record;
    }

    public function _recordEvent($providerId, $eventType, $eventStatus, $actorUserId = null, $actorType = 'system', $note = '', $rawEvent = []) {
        if (!array_key_exists($providerId, $this->events)) $this->events[$providerId] = [];
        $this->events[$providerId][] = [
            'providerId' => $providerId,
            'eventType' => $eventType,
            'eventStatus' => $eventStatus,
            'actorUserId' => $actorUserId,
            'actorType' => $actorType,
            'note' => $note,
            'rawEvent' => $rawEvent,
        ];
        return true;
    }

    private function record($providerId, $userId, $status, $refundAvailable, $continueAvailable) {
        return [
            'providerId' => $providerId,
            'userId' => $userId,
            'flow' => 'standard',
            'fromCurrency' => 'btc',
            'fromNetwork' => 'btc',
            'toCurrency' => 'eth',
            'toNetwork' => 'eth',
            'fromAmount' => '0.01',
            'toAmount' => '0.052',
            'payinAddress' => 'payin-'.$providerId,
            'payoutAddress' => 'payout-'.$providerId,
            'payoutAddressFingerprint' => hash('sha256', 'payout-'.$providerId),
            'refundAddress' => 'refund-'.$providerId,
            'status' => $status,
            'availableActions' => [
                'refund' => $refundAvailable,
                'continue' => $continueAvailable,
            ],
            'rawCreate' => ['secret' => 'create'],
            'rawStatus' => ['secret' => 'status'],
            'lookupTokenHash' => hash('sha256', $providerId),
            'sessionKey' => hash('sha256', 'session-'.$providerId),
            'referralAttribution' => ['campaign' => 'private'],
            'createdAt' => 1777899600,
            'updatedAt' => 1777899600,
        ];
    }
}

$client = new ChangeNowLifecycleFakeClient();
$repository = new ChangeNowLifecycleFakeRepository();
$flow = new ChangeNowPublicSwapFlow($client, new ChangeNowLifecycleFakeMarketData(), $repository, null, null, [
    'provider_enabled' => true,
    'support_email' => 'support@example.com',
]);

$status = $flow->_getStatus('token-anon');
assertLifecycleSame('tx-anon', $status['transaction']['providerId'], 'Anonymous lookup token should restore its transaction');
assertLifecycleSame(true, $status['transaction']['availableActions']['refund'], 'Status should expose refund only when provider marks it available');
assertLifecycleSame(true, $status['transaction']['availableActions']['continue'], 'Status should expose continue only when provider marks it available');
assertLifecycleMissing('lookupTokenHash', $status['transaction'], 'Public status response should redact token hash');
assertLifecycleMissing('sessionKey', $status['transaction'], 'Public status response should redact session hash');
assertLifecycleMissing('rawStatus', $status['transaction'], 'Public status response should redact provider status snapshot');
assertLifecycleMissing('referralAttribution', $status['transaction'], 'Public status response should redact referral attribution');

$flow->_getStatus('token-anon');
assertLifecycleSame(2, count($repository->statusUpdates), 'Duplicate polling should still refresh provider status');
$anonEvents = (array_key_exists('tx-anon', $repository->events) ? $repository->events['tx-anon'] : []);
assertLifecycleSame(0, count($anonEvents), 'Duplicate polling with no status transition should not duplicate audit events');

$history = $flow->_getUserHistory(42);
assertLifecycleSame(1, count($history['transactions']), 'User history should include only the logged-in user transactions');
assertLifecycleSame('tx-user-42', $history['transactions'][0]['providerId'], 'User history should not expose unrelated transactions');

assertLifecycleException('ChangeNowApiValidationException', function() use ($flow) {
    $flow->_requestRefund('token-user-42', 'refund-address', '', 42, 'user');
}, 'Refund should be blocked when provider does not mark refund available');

assertLifecycleException('ChangeNowApiNotFoundException', function() use ($flow) {
    $flow->_requestRefund('missing-token', 'refund-address', '', null, 'anonymous');
}, 'Unknown lookup token should not authorize refund actions');

$refund = $flow->_requestRefund('token-anon', 'refund-destination', 'memo-1', null, 'anonymous');
assertLifecycleSame('refund', $refund['lastAction']['action'], 'Available refund action should call provider refund endpoint');
assertLifecycleSame(['tx-anon', 'refund-destination', 'memo-1'], $client->refundCalls[0], 'Refund should pass provider id and refund address to ChangeNOW');
assertLifecycleSame('refund_requested', $repository->events['tx-anon'][0]['eventType'], 'Refund action should be audited');

$continued = $flow->_continueSwap('token-anon', null, 'anonymous');
assertLifecycleSame('continue', $continued['lastAction']['action'], 'Available continue action should call provider continue endpoint');
assertLifecycleSame(['tx-anon'], $client->continueCalls[0], 'Continue should pass provider id to ChangeNOW');
assertLifecycleSame('continue_requested', $repository->events['tx-anon'][1]['eventType'], 'Continue action should be audited');

echo "ChangeNOW transaction lifecycle check passed\n";

?>
