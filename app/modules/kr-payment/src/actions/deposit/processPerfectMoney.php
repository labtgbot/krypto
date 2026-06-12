<?php

/**
 * Process Perfect Money IPN callbacks.
 *
 * @package Krypto
 */

require "../../../../../config/config.settings.php";

krypto_session_start();

require_once "../../../../../app/src/bootstrap_paths.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/vendor/autoload.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/MySQL/MySQL.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/App/App.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/App/AppModule.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/User/User.php";

$respondPerfectMoneyIpn = function($status, $httpStatus = 200) {
  if(!headers_sent()){
    http_response_code($httpStatus);
    header('Content-Type: text/plain; charset=UTF-8');
  }

  die($status);
};

try {
  if(!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('Wrong request method', 1);
  if(empty($_POST)) throw new Exception('Empty Perfect Money callback', 1);

  $App = new App(true);
  $App->_loadModulesControllers();

  $PerfectMoney = new PerfectMoney($App);
  $PerfectMoney->_checkPayment($_POST);

  $respondPerfectMoneyIpn('OK');
} catch (Throwable $e) {
  krypto_log_exception('Perfect Money payment processing failed', $e);
  $respondPerfectMoneyIpn('ERROR', 400);
}
