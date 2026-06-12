<?php

// Regression coverage for SEC-22 (#131): the Payeer provider callback must not
// replace the incoming webhook with a production-hosted fixture or emit samples.

$root = dirname(__DIR__);
$actionPath = $root.'/app/modules/kr-payment/src/actions/processPayeer.php';

function payeer_callback_assert($condition, $message) {
    if (!$condition) {
        throw new Exception($message);
    }
}

payeer_callback_assert(file_exists($actionPath), 'Payeer callback endpoint must exist.');

$action = file_get_contents($actionPath);
payeer_callback_assert($action !== false && trim($action) !== '', 'Payeer callback endpoint must not be empty.');

foreach ([
    '$_POST = json_decode' => 'Payeer callback must not replace the provider POST payload.',
    '630437734' => 'Payeer callback must not keep the hard-coded sample operation id.',
    'C2EC9DB00FFD51EC59F045FBEC03DDFB76C52F58DBC6CA1C6947FE5EBA366910' => 'Payeer callback must not keep the hard-coded sample signature.',
    'client_email' => 'Payeer callback must not keep the sample customer email field.',
    '// GET' => 'Payeer callback must not emit raw GET samples.',
    '// POST' => 'Payeer callback must not emit raw POST samples.',
] as $needle => $message) {
    payeer_callback_assert(strpos($action, $needle) === false, $message);
}

$closingTagPosition = strpos($action, '?>');
payeer_callback_assert(
    $closingTagPosition === false || trim(substr($action, $closingTagPosition + 2)) === '',
    'Payeer callback must not have raw output after the PHP closing tag.'
);

$checkPaymentPosition = strpos($action, '$Payeer->_checkPayment($_POST)');
$requiredFieldsPosition = strpos($action, '$requiredPayeerCallbackFields');
payeer_callback_assert($checkPaymentPosition !== false, 'Payeer callback must pass the real incoming $_POST payload to _checkPayment().');
payeer_callback_assert(
    $requiredFieldsPosition !== false && $requiredFieldsPosition < $checkPaymentPosition,
    'Payeer callback must validate required provider fields before _checkPayment().'
);

foreach ([
    'm_operation_id',
    'm_operation_ps',
    'm_operation_date',
    'm_operation_pay_date',
    'm_shop',
    'm_orderid',
    'm_amount',
    'm_curr',
    'm_desc',
    'm_status',
    'm_sign',
] as $field) {
    payeer_callback_assert(
        strpos($action, "'".$field."'") !== false || strpos($action, '"'.$field.'"') !== false,
        'Payeer callback must require '.$field.'.'
    );
}

payeer_callback_assert(
    strpos($action, "'|'.") !== false || strpos($action, '."|".') !== false,
    'Payeer callback must return a Payeer-compatible order status response.'
);

echo "Payeer callback regression checks passed.\n";

?>
