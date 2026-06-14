<?php

// Regression coverage for SEC-25 (#140): Coinbase Commerce deposit webhooks
// must validate the dedicated webhook secret and confirm the stored charge id.

$root = dirname(__DIR__);

if(!class_exists('MySQL')) {
    class MySQL {}
}

require_once $root.'/app/modules/kr-payment/src/CoinbaseCommerce.php';

function coinbase_commerce_assert($condition, $message) {
    if (!$condition) {
        throw new Exception($message);
    }
}

function coinbase_commerce_assert_same($expected, $actual, $message) {
    if ($expected !== $actual) {
        throw new Exception($message.' Expected '.var_export($expected, true).', got '.var_export($actual, true));
    }
}

function coinbase_commerce_assert_exception($callable, $message) {
    try {
        $callable();
    } catch (Exception $e) {
        return $e;
    }

    throw new Exception($message.' Expected exception was not thrown.');
}

class CoinbaseCommerceRegressionRepository {
    public $deposits = [];
    public $updates = [];
    public $findPendingArgs = [];

    public function __construct() {
        $this->deposits = [
            'charge_123' => [
                'id_deposit_history' => 51,
                'id_user' => '7',
                'ref_deposit_history' => 'CB-REF-1',
                'payment_status_deposit_history' => '0',
                'payment_type_deposit_history' => 'coinbasecommerce',
                'payment_data_deposit_history' => 'charge_123',
            ],
        ];
    }

    public function _findProcessedCoinbaseCommerceDeposit($chargeId, $userId) {
        foreach ($this->deposits as $deposit) {
            if((string) $deposit['id_user'] !== (string) $userId) continue;
            if($deposit['payment_type_deposit_history'] !== 'coinbasecommerce') continue;
            if($deposit['payment_status_deposit_history'] === '0') continue;
            if($deposit['payment_data_deposit_history'] === $chargeId || strpos($deposit['payment_data_deposit_history'], (string) $chargeId) !== false) {
                return $deposit;
            }
        }

        return null;
    }

    public function _findPendingCoinbaseCommerceDeposit($chargeId, $userId, $depositReference) {
        $this->findPendingArgs[] = [$chargeId, $userId, $depositReference];

        if(!array_key_exists($chargeId, $this->deposits)) return null;
        $deposit = $this->deposits[$chargeId];
        if((string) $deposit['id_user'] !== (string) $userId) return null;
        if($deposit['ref_deposit_history'] !== $depositReference) return null;
        if($deposit['payment_type_deposit_history'] !== 'coinbasecommerce') return null;
        if($deposit['payment_status_deposit_history'] !== '0') return null;
        return $deposit;
    }

    public function _confirmCoinbaseCommerceDeposit($deposit, $status) {
        $chargeId = $deposit['payment_data_deposit_history'];
        if(!array_key_exists($chargeId, $this->deposits)) return false;
        if($this->deposits[$chargeId]['payment_status_deposit_history'] !== '0') return false;

        $this->deposits[$chargeId]['payment_status_deposit_history'] = (string) $status;
        $this->updates[] = [$chargeId, (string) $status];
        return true;
    }
}

function coinbase_commerce_payload($eventType = 'charge:confirmed', $chargeId = 'charge_123', $userId = '7', $depositReference = 'CB-REF-1', $eventId = 'evt_123') {
    return [
        'event' => [
            'id' => $eventId,
            'type' => $eventType,
            'data' => [
                'id' => $chargeId,
                'metadata' => [
                    'deposit_reference' => $depositReference,
                    'id_user' => $userId,
                ],
            ],
        ],
    ];
}

coinbase_commerce_assert(class_exists('CoinbaseCommerceDepositProcessor'), 'Coinbase Commerce deposit processor must exist.');

$webhookSecret = 'webhook-secret';
$apiKey = 'api-key-that-must-not-sign-webhooks';
$payload = coinbase_commerce_payload();
$rawBody = json_encode($payload);
coinbase_commerce_assert($rawBody !== false, 'Coinbase Commerce fixture payload should encode.');

$repository = new CoinbaseCommerceRegressionRepository();
$processor = new CoinbaseCommerceDepositProcessor($repository);
$signature = CoinbaseCommerceDepositProcessor::_expectedSignature($rawBody, $webhookSecret);
$result = $processor->_processWebhook($payload, $rawBody, $signature, $webhookSecret);

coinbase_commerce_assert_same('confirmed', $result['status'], 'Valid Coinbase Commerce webhook should confirm the pending deposit.');
coinbase_commerce_assert_same(1, count($repository->updates), 'Valid Coinbase Commerce webhook should update exactly one deposit.');
coinbase_commerce_assert_same('charge_123', $repository->findPendingArgs[0][0], 'Coinbase Commerce confirmation must look up the stored charge id, not the event id.');
coinbase_commerce_assert_same('1', $repository->deposits['charge_123']['payment_status_deposit_history'], 'Confirmed Coinbase Commerce deposit should leave pending status.');
coinbase_commerce_assert_same('charge_123', $repository->deposits['charge_123']['payment_data_deposit_history'], 'Confirmed Coinbase Commerce deposit should keep the stored charge id.');

$replay = $processor->_processWebhook($payload, $rawBody, $signature, $webhookSecret);
coinbase_commerce_assert_same('duplicate', $replay['status'], 'Replay with the same Coinbase Commerce charge id should be idempotent.');
coinbase_commerce_assert_same(1, count($repository->updates), 'Replay must not update another deposit.');

$badSignatureRepository = new CoinbaseCommerceRegressionRepository();
$badSignatureProcessor = new CoinbaseCommerceDepositProcessor($badSignatureRepository);
$apiKeySignature = CoinbaseCommerceDepositProcessor::_expectedSignature($rawBody, $apiKey);
coinbase_commerce_assert_exception(function() use ($badSignatureProcessor, $payload, $rawBody, $apiKeySignature, $webhookSecret) {
    $badSignatureProcessor->_processWebhook($payload, $rawBody, $apiKeySignature, $webhookSecret);
}, 'Coinbase Commerce webhook signed with the API key should be rejected.');
coinbase_commerce_assert_same(0, count($badSignatureRepository->updates), 'Rejected API-key signature must not update deposits.');

$wrongUserRepository = new CoinbaseCommerceRegressionRepository();
$wrongUserProcessor = new CoinbaseCommerceDepositProcessor($wrongUserRepository);
$wrongUserPayload = coinbase_commerce_payload('charge:confirmed', 'charge_123', '8');
$wrongUserBody = json_encode($wrongUserPayload);
$wrongUserSignature = CoinbaseCommerceDepositProcessor::_expectedSignature($wrongUserBody, $webhookSecret);
coinbase_commerce_assert_exception(function() use ($wrongUserProcessor, $wrongUserPayload, $wrongUserBody, $wrongUserSignature, $webhookSecret) {
    $wrongUserProcessor->_processWebhook($wrongUserPayload, $wrongUserBody, $wrongUserSignature, $webhookSecret);
}, 'Coinbase Commerce webhook should reject a charge whose metadata user does not own the pending deposit.');
coinbase_commerce_assert_same(0, count($wrongUserRepository->updates), 'Rejected wrong-user webhook must not update deposits.');

$ignoredRepository = new CoinbaseCommerceRegressionRepository();
$ignoredProcessor = new CoinbaseCommerceDepositProcessor($ignoredRepository);
$ignoredPayload = coinbase_commerce_payload('charge:created');
$ignoredBody = json_encode($ignoredPayload);
$ignoredSignature = CoinbaseCommerceDepositProcessor::_expectedSignature($ignoredBody, $webhookSecret);
$ignored = $ignoredProcessor->_processWebhook($ignoredPayload, $ignoredBody, $ignoredSignature, $webhookSecret);
coinbase_commerce_assert_same('ignored', $ignored['status'], 'Non-confirmation Coinbase Commerce webhook should not update deposits.');
coinbase_commerce_assert_same(0, count($ignoredRepository->updates), 'Ignored webhook must not update deposits.');

$coinbaseSource = file_get_contents($root.'/app/modules/kr-payment/src/CoinbaseCommerce.php');
coinbase_commerce_assert(strpos($coinbaseSource, 'CoinbaseCommerceDepositProcessor') !== false, 'Coinbase Commerce source must use the deposit processor.');
coinbase_commerce_assert(strpos($coinbaseSource, '_getCoinbaseCommerceWebhookSecret()') !== false, 'Coinbase Commerce webhook validation must use the webhook secret getter.');
coinbase_commerce_assert(strpos($coinbaseSource, 'hash_hmac("sha256", file_get_contents(\'php://input\'), $this->_getApp()->_getCoinbaseCommerceAPIKey())') === false, 'Coinbase Commerce webhook must not sign php://input with the API key.');
coinbase_commerce_assert(strpos($coinbaseSource, '$payment[\'charge_id\']') !== false, 'Coinbase Commerce confirmation must match the charge id.');

$actionSource = file_get_contents($root.'/app/modules/kr-payment/src/actions/deposit/processCoinbaseCommerce.php');
coinbase_commerce_assert(strpos($actionSource, '$rawBody = file_get_contents(\'php://input\');') !== false, 'Coinbase Commerce action should read the raw webhook body once.');
coinbase_commerce_assert(strpos($actionSource, '_parseWebhook($payload, $rawBody, $signature)') !== false, 'Coinbase Commerce action should pass raw body and signature to the parser.');

$appSource = file_get_contents($root.'/app/src/App/App.php');
coinbase_commerce_assert(strpos($appSource, 'function _getCoinbaseCommerceWebhookSecret(') !== false, 'App must expose the Coinbase Commerce webhook secret.');

$adminView = file_get_contents($root.'/app/modules/kr-admin/views/payment.php');
coinbase_commerce_assert(strpos($adminView, 'kr-adm-coinbasecommercewebhooksecret') !== false, 'Admin payment view must expose Coinbase Commerce webhook secret field.');

$saveAction = file_get_contents($root.'/app/modules/kr-admin/src/actions/savePayment.php');
coinbase_commerce_assert(strpos($saveAction, 'coinbasecommerce_webhook_secret') !== false, 'Payment save action must persist Coinbase Commerce webhook secret.');
coinbase_commerce_assert(strpos($saveAction, 'App::_encryptSecret($_POST[\'kr-adm-coinbasecommercewebhooksecret\'])') !== false, 'Coinbase Commerce webhook secret must be saved encrypted.');

$installerSql = file_get_contents($root.'/install/assets/sql/krypto.sql');
coinbase_commerce_assert(strpos($installerSql, "'coinbasecommerce_webhook_secret', '', 1") !== false, 'Installer must seed encrypted Coinbase Commerce webhook secret.');

$csrfPolicy = file_get_contents($root.'/app/src/App/csrf_policy.php');
coinbase_commerce_assert(strpos($csrfPolicy, 'Coinbase Commerce webhook shared secret') !== false, 'CSRF policy should describe the dedicated Coinbase Commerce webhook secret.');

$balanceSource = file_get_contents($root.'/app/modules/kr-trade/src/Balance.php');
coinbase_commerce_assert(strpos($balanceSource, 'id_user=:id_user') !== false, 'Deposit status changes must be scoped to the owning user.');
coinbase_commerce_assert(strpos($balanceSource, 'payment_status_deposit_history=:pending_status') !== false, 'Deposit status changes must update only pending deposits.');

echo "Coinbase Commerce webhook regression checks passed.\n";

?>
