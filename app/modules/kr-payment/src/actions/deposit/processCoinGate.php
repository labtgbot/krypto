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

    if(!empty($_POST)){

      if(!isset($_POST['order_id']) || !isset($_POST['status']) || !isset($_POST['created_at'])) throw new Exception("Wrong arguments", 1);

      $CoinGate = new CoinGate($App);
      $resultParsed = $CoinGate->_parseResultDeposit($_POST);

      $User = $resultParsed['user'];
      $Balance = new Balance($User, $App, 'real');
      if($resultParsed['status'] == 1){
        $Balance->_validDeposit($resultParsed['order_id']);
      }

    } else {

      die("<script>window.close();</script>");
    }

} catch (Exception $e) {
    krypto_log_exception('CoinGate deposit processing failed', $e);
    http_response_code(500);
    die('Payment processing failed.');
}
