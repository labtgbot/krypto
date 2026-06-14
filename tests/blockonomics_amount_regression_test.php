<?php

// Regression coverage for SEC-26 (#141): Blockonomics must credit only the
// amount delivered to the locally assigned deposit address.

$root = dirname(__DIR__);

if(!class_exists('MySQL')) {
    class MySQL {}
}

require_once $root.'/app/modules/kr-payment/src/Blockonomics.php';

function blockonomics_assert($condition, $message) {
    if (!$condition) {
        throw new Exception($message);
    }
}

function blockonomics_assert_same($expected, $actual, $message) {
    if ($expected !== $actual) {
        throw new Exception($message.' Expected '.var_export($expected, true).', got '.var_export($actual, true));
    }
}

function blockonomics_assert_exception($callable, $message) {
    try {
        $callable();
    } catch (Exception $e) {
        return $e;
    }

    throw new Exception($message.' Expected exception was not thrown.');
}

class BlockonomicsValidPaymentProbe extends Blockonomics {
    public $lookedUpAddress = null;
    public $setTransactionAddress = null;

    public function _getTransactionDetails($txtid, $addr = null) {
        return (object) [
            'status' => 'PARTIALLY CONFIRMED',
            'vin' => [
                (object) ['value' => 500000000],
            ],
            'vout' => [
                (object) ['address' => 'sender-change-address', 'value' => 450000000],
                (object) ['address' => 'deposit-address', 'value' => 10000000],
            ],
        ];
    }

    public function _getUserByAddress($address) {
        $this->lookedUpAddress = $address;
        return (object) ['id_user' => 7];
    }

    public function _setTransaction($User, $txtid, $addr, $status = 0) {
        $this->setTransactionAddress = $addr;
        return true;
    }
}

$Blockonomics = new Blockonomics();

$paymentDetail = (object) [
    'vin' => [
        (object) ['value' => 500000000],
    ],
    'vout' => [
        (object) ['address' => 'sender-change-address', 'value' => 450000000],
        (object) ['address' => 'deposit-address', 'value' => 10000000],
        (object) [
            'scriptPubKey' => (object) [
                'addresses' => ['deposit-address'],
            ],
            'value' => 2500000,
        ],
        (object) [
            'scriptPubKey' => (object) [
                'address' => 'other-recipient-address',
            ],
            'value' => 7500000,
        ],
    ],
];

blockonomics_assert_same(
    0.125,
    $Blockonomics->_calcAmountPayment($paymentDetail, 'deposit-address'),
    'Blockonomics amount must sum only outputs paying the expected deposit address.'
);

blockonomics_assert_exception(function() use ($Blockonomics) {
    $Blockonomics->_calcAmountPayment((object) [
        'vin' => [
            (object) ['value' => 500000000],
        ],
        'vout' => [
            (object) ['address' => 'other-recipient-address', 'value' => 10000000],
        ],
    ], 'deposit-address');
}, 'Blockonomics amount calculation must reject transactions without an output to the expected address.');

blockonomics_assert_exception(function() use ($Blockonomics, $paymentDetail) {
    $Blockonomics->_calcAmountPayment($paymentDetail, '');
}, 'Blockonomics amount calculation must require an expected deposit address.');

$probe = new BlockonomicsValidPaymentProbe();
$probe->_validPayment('tx-id', 'deposit-address');

blockonomics_assert_same(
    'deposit-address',
    $probe->lookedUpAddress,
    'Blockonomics validation must resolve the user from the callback deposit address, not vout[0].'
);
blockonomics_assert_same(
    'deposit-address',
    $probe->setTransactionAddress,
    'Blockonomics validation must record the transaction against the callback deposit address.'
);

$source = file_get_contents($root.'/app/modules/kr-payment/src/Blockonomics.php');
blockonomics_assert($source !== false, 'Blockonomics source must be readable.');
blockonomics_assert(
    strpos($source, 'vin[0]->value') === false,
    'Blockonomics amount calculation must not credit the first transaction input.'
);

echo "Blockonomics amount regression checks passed.\n";

?>
