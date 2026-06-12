<?php

/**
 * Process payment Payeer
 *
 * @package Krypto
 * @author Ovrley <hello@ovrley.com>
 */

require "../../../../../config/config.settings.php";

krypto_session_start();

require_once "../../../../../app/src/bootstrap_paths.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/vendor/autoload.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/MySQL/MySQL.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/App/App.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/App/AppModule.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/User/User.php";

$requiredPayeerCallbackFields = [
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
    'm_sign'
];

$respondPayeerCallback = function($status) {
    $orderId = '';
    if(isset($_POST['m_orderid']) && is_scalar($_POST['m_orderid'])) {
        $orderId = trim((string) $_POST['m_orderid']);
    }

    if(!headers_sent()) {
        header('Content-Type: text/plain; charset=UTF-8');
    }

    die($orderId === '' ? $status : $orderId.'|'.$status);
};

try {

    $payeerAllowedIps = array('185.71.65.92', '185.71.65.189', '149.202.17.210');
    $remoteAddress = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    if(!in_array($remoteAddress, $payeerAllowedIps, true)) throw new Exception("Permission denied", 1);

    if(!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Wrong request method", 1);
    if(empty($_POST)) throw new Exception("Access denied", 1);

    foreach($requiredPayeerCallbackFields as $field) {
        if(!array_key_exists($field, $_POST) || !is_scalar($_POST[$field]) || trim((string) $_POST[$field]) === '') {
            throw new Exception("Wrong arguments", 1);
        }
    }

    if(!is_numeric($_POST['m_amount'])) throw new Exception("Wrong amount", 1);

    // Load app modules after cheap provider callback validation.
    $App = new App(true);
    $App->_loadModulesControllers();

    $Payeer = new Payeer($App);
    if(!array_key_exists((string) $_POST['m_curr'], $Payeer->_getListCurrencyAvailable())) throw new Exception("Wrong currency", 1);

    $Payeer->_checkPayment($_POST);

    $respondPayeerCallback('success');
} catch (Throwable $e) {
  krypto_log_exception('Payeer payment processing failed', $e);
  $respondPayeerCallback('error');
}
