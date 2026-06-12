<?php

// Regression coverage for SEC-23 (#132): Perfect Money IPN must validate the
// provider callback before confirming a pending deposit.

$root = dirname(__DIR__);

if(!class_exists('MySQL')) {
    class MySQL {}
}

require_once $root.'/app/modules/kr-payment/src/PerfectMoney.php';

function perfectmoney_assert($condition, $message) {
    if (!$condition) {
        throw new Exception($message);
    }
}

function perfectmoney_assert_same($expected, $actual, $message) {
    if ($expected !== $actual) {
        throw new Exception($message.' Expected '.var_export($expected, true).', got '.var_export($actual, true));
    }
}

function perfectmoney_assert_exception($callable, $message) {
    try {
        $callable();
    } catch (Exception $e) {
        return $e;
    }

    throw new Exception($message.' Expected exception was not thrown.');
}

perfectmoney_assert(class_exists('PerfectMoneySettings'), 'Perfect Money settings helper must exist.');
perfectmoney_assert(class_exists('PerfectMoneyIpnProcessor'), 'Perfect Money IPN processor must exist.');

class PerfectMoneyRegressionRepository {
    public $deposits = [];
    public $updates = [];

    public function __construct() {
        $this->deposits = [
            'PM-REF-1' => [
                'id_deposit_history' => 42,
                'ref_deposit_history' => 'PM-REF-1',
                'payment_status_deposit_history' => '0',
                'payment_type_deposit_history' => 'perfectmoney',
                'amount_deposit_history' => '10.00000000',
                'fees_deposit_history' => '0.00000000',
                'currency_deposit_history' => 'USD',
                'payment_data_deposit_history' => '',
            ],
            'PM-REF-EUR' => [
                'id_deposit_history' => 43,
                'ref_deposit_history' => 'PM-REF-EUR',
                'payment_status_deposit_history' => '0',
                'payment_type_deposit_history' => 'perfectmoney',
                'amount_deposit_history' => '20.00000000',
                'fees_deposit_history' => '0.00000000',
                'currency_deposit_history' => 'EUR',
                'payment_data_deposit_history' => '',
            ],
        ];
    }

    public function _findProcessedPerfectMoneyBatch($paymentBatchNum) {
        foreach ($this->deposits as $deposit) {
            if($deposit['payment_status_deposit_history'] === '0') continue;
            if(strpos($deposit['payment_data_deposit_history'], (string) $paymentBatchNum) !== false) {
                return $deposit;
            }
        }

        return null;
    }

    public function _findPendingPerfectMoneyDeposit($paymentId) {
        if(!array_key_exists($paymentId, $this->deposits)) return null;
        if($this->deposits[$paymentId]['payment_status_deposit_history'] !== '0') return null;
        return $this->deposits[$paymentId];
    }

    public function _confirmPerfectMoneyDeposit($deposit, $auditPayload, $status) {
        $paymentId = $deposit['ref_deposit_history'];
        $this->deposits[$paymentId]['payment_status_deposit_history'] = (string) $status;
        $this->deposits[$paymentId]['payment_data_deposit_history'] = $auditPayload;
        $this->updates[] = [$paymentId, $auditPayload, (string) $status];
        return true;
    }
}

function perfectmoney_processor_config() {
    return [
        'enabled' => true,
        'payee_account' => 'U1234567',
        'alternate_passphrase' => 'alternate-passphrase',
        'confirmation_status' => '1',
        'allowed_currencies' => ['USD', 'EUR', 'OAU'],
    ];
}

function perfectmoney_payload($overrides = []) {
    $payload = array_merge([
        'PAYMENT_ID' => 'PM-REF-1',
        'PAYEE_ACCOUNT' => 'U1234567',
        'PAYMENT_AMOUNT' => '10.00',
        'PAYMENT_UNITS' => 'USD',
        'PAYMENT_BATCH_NUM' => '987654321',
        'PAYER_ACCOUNT' => 'U7654321',
        'TIMESTAMPGMT' => '1770000000',
    ], $overrides);

    if(!array_key_exists('V2_HASH', $overrides)) {
        $payload['V2_HASH'] = PerfectMoneyIpnProcessor::_expectedV2Hash($payload, perfectmoney_processor_config()['alternate_passphrase']);
    }
    return $payload;
}

$repository = new PerfectMoneyRegressionRepository();
$processor = new PerfectMoneyIpnProcessor($repository, perfectmoney_processor_config());
$result = $processor->_process(perfectmoney_payload());

perfectmoney_assert_same('confirmed', $result['status'], 'Valid IPN should confirm the pending deposit.');
perfectmoney_assert_same(1, count($repository->updates), 'Valid IPN should update exactly one deposit.');
perfectmoney_assert_same('1', $repository->deposits['PM-REF-1']['payment_status_deposit_history'], 'Confirmed deposit should leave pending status.');

$auditPayload = json_decode($repository->deposits['PM-REF-1']['payment_data_deposit_history'], true);
perfectmoney_assert(is_array($auditPayload), 'Confirmed deposit should store structured audit payload.');
perfectmoney_assert_same('987654321', $auditPayload['payment_batch_num'], 'Audit payload should retain batch id for idempotency.');
perfectmoney_assert(!array_key_exists('payer_account', $auditPayload), 'Audit payload should not persist payer account PII.');
perfectmoney_assert(!array_key_exists('v2_hash', $auditPayload), 'Audit payload should not persist callback hashes.');

$replay = $processor->_process(perfectmoney_payload());
perfectmoney_assert_same('duplicate', $replay['status'], 'Replay with the same PAYMENT_BATCH_NUM should be idempotent.');
perfectmoney_assert_same(1, count($repository->updates), 'Replay must not update another deposit.');

foreach ([
    'bad hash' => ['V2_HASH' => 'BADHASH'],
    'wrong payee account' => ['PAYEE_ACCOUNT' => 'U9999999'],
    'wrong amount' => ['PAYMENT_AMOUNT' => '11.00'],
    'wrong currency' => ['PAYMENT_UNITS' => 'EUR'],
] as $case => $overrides) {
    $caseRepository = new PerfectMoneyRegressionRepository();
    $caseProcessor = new PerfectMoneyIpnProcessor($caseRepository, perfectmoney_processor_config());
    $payload = perfectmoney_payload($overrides);
    if($case !== 'bad hash') {
        $payload['V2_HASH'] = PerfectMoneyIpnProcessor::_expectedV2Hash($payload, perfectmoney_processor_config()['alternate_passphrase']);
    }

    perfectmoney_assert_exception(function() use ($caseProcessor, $payload) {
        $caseProcessor->_process($payload);
    }, 'Perfect Money IPN should reject '.$case.'.');
    perfectmoney_assert_same(0, count($caseRepository->updates), 'Rejected '.$case.' must not update deposits.');
}

$settings = PerfectMoneySettings::_adminPostToSettings([
    'kr-adm-chk-enableperfectmoney' => 'on',
    'kr-adm-perfectmoneypayeeaccount' => ' U1234567 ',
    'kr-adm-perfectmoneypayeename' => ' Merchant ',
    'kr-adm-perfectmoneyalternatepassphrase' => ' alternate-passphrase ',
]);
perfectmoney_assert_same('1', $settings['perfectmoney_enabled'], 'Admin settings should save enabled flag.');
perfectmoney_assert_same('U1234567', $settings['perfectmoney_payee_account'], 'Admin settings should save payee account.');
perfectmoney_assert_same('Merchant', $settings['perfectmoney_payee_name'], 'Admin settings should save payee name.');
perfectmoney_assert_same('alternate-passphrase', $settings['perfectmoney_alternate_passphrase'], 'Admin settings should save alternate passphrase.');
perfectmoney_assert(in_array('perfectmoney_alternate_passphrase', PerfectMoneySettings::_encryptedKeys(), true), 'Alternate passphrase must be marked encrypted.');

$maskedSettings = PerfectMoneySettings::_adminPostToSettings([
    'kr-adm-perfectmoneyalternatepassphrase' => PerfectMoneySettings::SECRET_MASK,
]);
perfectmoney_assert(!array_key_exists('perfectmoney_alternate_passphrase', $maskedSettings), 'Masked alternate passphrase should preserve the existing encrypted value.');

$action = file_get_contents($root.'/app/modules/kr-payment/src/actions/deposit/processPerfectMoney.php');
perfectmoney_assert(strpos($action, 'PASSWORD_ACCOUNT') === false, 'Perfect Money IPN must not keep a hard-coded passphrase.');
perfectmoney_assert(strpos($action, 'json_encode($_POST)') === false, 'Perfect Money IPN must not raw-log provider POST data.');
perfectmoney_assert(strpos($action, '$_POST') !== false && strpos($action, '_checkPayment($_POST)') !== false, 'Perfect Money IPN must pass the real provider payload to _checkPayment().');

$paymentView = file_get_contents($root.'/app/modules/kr-payment/views/perfectmoney.php');
perfectmoney_assert(strpos($paymentView, 'krypto.dev.ovrley.com') === false, 'Perfect Money payment form must not use dev return URLs.');
perfectmoney_assert(strpos($paymentView, '$statusUrl = rtrim(APP_URL') !== false, 'Perfect Money status URL should be built from APP_URL.');
perfectmoney_assert(strpos($paymentView, '$returnUrl = rtrim(APP_URL') !== false, 'Perfect Money return URLs should be built from APP_URL.');

foreach ([$root.'/app', $root.'/assets', $root.'/public'] as $scanRoot) {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($scanRoot, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if(!$file->isFile()) continue;
        $source = file_get_contents($file->getPathname());
        perfectmoney_assert(strpos($source, 'krypto.dev.ovrley.com') === false, 'Production code must not reference dev host: '.$file->getPathname());
    }
}

$adminView = file_get_contents($root.'/app/modules/kr-admin/views/payment.php');
foreach ([
    'kr-adm-chk-enableperfectmoney',
    'kr-adm-perfectmoneypayeeaccount',
    'kr-adm-perfectmoneypayeename',
    'kr-adm-perfectmoneyalternatepassphrase',
] as $field) {
    perfectmoney_assert(strpos($adminView, $field) !== false, 'Admin payment view missing Perfect Money field: '.$field);
}
$perfectMoneyBlockPosition = strpos($adminView, 'kr-adm-chk-enableperfectmoney');
perfectmoney_assert($perfectMoneyBlockPosition !== false, 'Perfect Money admin block should be present.');
$perfectMoneyBlockPrefix = substr($adminView, max(0, $perfectMoneyBlockPosition - 500), 500);
perfectmoney_assert(strpos($perfectMoneyBlockPrefix, 'if(false)') === false, 'Perfect Money admin settings must not be hidden behind if(false).');

$saveAction = file_get_contents($root.'/app/modules/kr-admin/src/actions/savePayment.php');
perfectmoney_assert(strpos($saveAction, '$App->_savePerfectMoneySettings($_POST);') !== false, 'Payment save action must persist Perfect Money settings.');

$appSource = file_get_contents($root.'/app/src/App/App.php');
perfectmoney_assert(strpos($appSource, 'function _getPerfectMoneyAlternatePassphrase(') !== false, 'App must expose Perfect Money alternate passphrase.');
perfectmoney_assert(strpos($appSource, 'function _savePerfectMoneySettings(') !== false, 'App must save Perfect Money admin settings.');

$installerSql = file_get_contents($root.'/install/assets/sql/krypto.sql');
perfectmoney_assert(strpos($installerSql, "'perfectmoney_alternate_passphrase', '', 1") !== false, 'Installer must seed encrypted Perfect Money alternate passphrase.');

echo "Perfect Money IPN regression checks passed.\n";

?>
