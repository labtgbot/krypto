<?php
/**
 * Process payment paypal action
 *
 * @package Krypto
 * @author Ovrley <hello@ovrley.com>
 */

require "../../../../../../config/config.settings.php";

krypto_session_start();

require_once "../../../../../../app/src/bootstrap_paths.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/vendor/autoload.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/MySQL/MySQL.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/App/App.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/App/AppModule.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/User/User.php";

try {

    // Load app modules
    $App = new App(true);
    $App->_loadModulesControllers();

    $rawBody = file_get_contents('php://input');
    $payload = json_decode($rawBody, true);
    $signature = (array_key_exists('HTTP_X_CC_WEBHOOK_SIGNATURE', $_SERVER) ? $_SERVER['HTTP_X_CC_WEBHOOK_SIGNATURE'] : null);

    $CoinbaseCommerce = new CoinbaseCommerce($App);
    $CoinbaseCommerce->_parseWebhook($payload, $rawBody, $signature);


} catch (Exception $e) {
    krypto_log_exception('Coinbase Commerce webhook processing failed', $e);
    http_response_code(500);
    die('Payment processing failed.');
}
