<?php

// Regression coverage for SEC-24 (#139): a paid Mollie deposit webhook must
// confirm one existing pending deposit and replays must not credit again.

$root = dirname(__DIR__);

if(!class_exists('MySQL')) {
    class MySQL {}
}

require_once $root.'/app/modules/kr-payment/src/Mollie.php';

function mollie_deposit_assert($condition, $message) {
    if (!$condition) {
        throw new Exception($message);
    }
}

function mollie_deposit_assert_same($expected, $actual, $message) {
    if ($expected !== $actual) {
        throw new Exception($message.' Expected '.var_export($expected, true).', got '.var_export($actual, true));
    }
}

function mollie_deposit_assert_exception($callable, $message) {
    try {
        $callable();
    } catch (Exception $e) {
        return $e;
    }

    throw new Exception($message.' Expected exception was not thrown.');
}

mollie_deposit_assert(class_exists('MollieDepositProcessor'), 'Mollie deposit processor must exist.');

class MollieDepositRegressionRepository {
    public $deposits = [];
    public $updates = [];

    public function __construct() {
        $this->deposits = [
            'tr_mollie_123' => [
                'id_deposit_history' => 11,
                'id_user' => '7',
                'ref_deposit_history' => 'tr_mollie_123',
                'payment_status_deposit_history' => '0',
                'payment_type_deposit_history' => 'mollie',
                'amount_deposit_history' => '98.00000000',
                'fees_deposit_history' => '2.00000000',
                'currency_deposit_history' => 'EUR',
                'payment_data_deposit_history' => '{"provider":"mollie","payment_id":"tr_mollie_123","cid":"encrypted-cid"}',
            ],
        ];
    }

    public function _findProcessedMollieDeposit($paymentId) {
        foreach ($this->deposits as $deposit) {
            if($deposit['ref_deposit_history'] !== $paymentId) continue;
            if($deposit['payment_type_deposit_history'] !== 'mollie') continue;
            if($deposit['payment_status_deposit_history'] === '0') continue;
            return $deposit;
        }

        return null;
    }

    public function _findPendingMollieDeposit($paymentId, $userId) {
        if(!array_key_exists($paymentId, $this->deposits)) return null;
        $deposit = $this->deposits[$paymentId];
        if($deposit['id_user'] !== (string) $userId) return null;
        if($deposit['payment_type_deposit_history'] !== 'mollie') return null;
        if($deposit['payment_status_deposit_history'] !== '0') return null;
        return $deposit;
    }

    public function _confirmMollieDeposit($deposit, $auditPayload, $status) {
        $paymentId = $deposit['ref_deposit_history'];
        if($this->deposits[$paymentId]['payment_status_deposit_history'] !== '0') return false;
        $this->deposits[$paymentId]['payment_status_deposit_history'] = (string) $status;
        $this->deposits[$paymentId]['payment_data_deposit_history'] = $auditPayload;
        $this->updates[] = [$paymentId, $auditPayload, (string) $status];
        return true;
    }
}

function mollie_deposit_payment($overrides = []) {
    return array_merge([
        'cid' => 'encrypted-cid',
        'order_id' => 'tr_mollie_123',
        'user_id' => '7',
        'amount' => '100.00',
        'currency' => 'EUR',
    ], $overrides);
}

$repository = new MollieDepositRegressionRepository();
$processor = new MollieDepositProcessor($repository);
$result = $processor->_process(mollie_deposit_payment());

mollie_deposit_assert_same('confirmed', $result['status'], 'Paid Mollie webhook should confirm the pending deposit.');
mollie_deposit_assert_same(1, count($repository->updates), 'Paid Mollie webhook should update exactly one deposit.');
mollie_deposit_assert_same('1', $repository->deposits['tr_mollie_123']['payment_status_deposit_history'], 'Confirmed Mollie deposit should leave pending status.');

$auditPayload = json_decode($repository->deposits['tr_mollie_123']['payment_data_deposit_history'], true);
mollie_deposit_assert(is_array($auditPayload), 'Confirmed Mollie deposit should store structured audit payload.');
mollie_deposit_assert_same('mollie', $auditPayload['provider'], 'Audit payload should retain provider.');
mollie_deposit_assert_same('tr_mollie_123', $auditPayload['payment_id'], 'Audit payload should retain Mollie payment id.');
mollie_deposit_assert_same('encrypted-cid', $auditPayload['cid'], 'Audit payload should retain Mollie metadata cid.');

$replay = $processor->_process(mollie_deposit_payment());
mollie_deposit_assert_same('duplicate', $replay['status'], 'Replay with the same Mollie payment id should be idempotent.');
mollie_deposit_assert_same(1, count($repository->updates), 'Replay must not update another deposit.');

foreach ([
    'missing pending deposit' => ['order_id' => 'tr_missing'],
    'wrong user' => ['user_id' => '8'],
    'wrong amount' => ['amount' => '101.00'],
    'wrong currency' => ['currency' => 'USD'],
] as $case => $overrides) {
    $caseRepository = new MollieDepositRegressionRepository();
    $caseProcessor = new MollieDepositProcessor($caseRepository);
    mollie_deposit_assert_exception(function() use ($caseProcessor, $overrides) {
        $caseProcessor->_process(mollie_deposit_payment($overrides));
    }, 'Mollie deposit processor should reject '.$case.'.');
    mollie_deposit_assert_same(0, count($caseRepository->updates), 'Rejected '.$case.' must not update deposits.');
}

$ignoredRepository = new MollieDepositRegressionRepository();
$ignoredProcessor = new MollieDepositProcessor($ignoredRepository);
$ignored = $ignoredProcessor->_process(false);
mollie_deposit_assert_same('ignored', $ignored['status'], 'Falsy Mollie payment check should short-circuit without a deposit update.');
mollie_deposit_assert_same(0, count($ignoredRepository->updates), 'Falsy Mollie payment check must not update deposits.');

$action = file_get_contents($root.'/app/modules/kr-payment/src/actions/deposit/processMollie.php');
mollie_deposit_assert(strpos($action, 'new User($paymentCheck[\'user_id\'])') === false, 'Mollie deposit action must not build a user directly from unchecked payment data.');
mollie_deposit_assert(strpos($action, '_addDeposit(') === false, 'Mollie deposit action must not insert a paid deposit directly.');
mollie_deposit_assert(strpos($action, '_processDepositPayment($paymentCheck)') !== false, 'Mollie deposit action must delegate idempotent confirmation to Mollie.');

$mollieSource = file_get_contents($root.'/app/modules/kr-payment/src/Mollie.php');
mollie_deposit_assert(strpos($mollieSource, "payment_status_deposit_history=:pending_status") !== false, 'Mollie confirmation must update only a pending deposit.');
mollie_deposit_assert(strpos($mollieSource, '$Balance->_addDeposit($amount, \'mollie\'') !== false, 'Mollie checkout creation must create a pending deposit.');

echo "Mollie deposit webhook regression checks passed.\n";

?>
