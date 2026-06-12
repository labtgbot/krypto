<?php

/**
 * Charge plan selected view
 *
 * @package Krypto
 * @author Ovrley <hello@ovrley.com>
 */

require "../../../../config/config.settings.php";

krypto_session_start();

require_once "../../../../app/src/bootstrap_paths.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/vendor/autoload.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/MySQL/MySQL.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/App/App.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/App/AppModule.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/User/User.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/Lang/Lang.php";

require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/CryptoApi/CryptoApi.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/CryptoApi/CryptoCoin.php";

try {

  // Load app module
  $App = new App(true);
  $App->_loadModulesControllers();

  // Check if user is logged
  $User = new User();
  if(!$User->_isLogged()) die('User not logged');

  if(empty($_GET) || !isset($_GET['m']) || !isset($_GET['cr'])) throw new Exception("Error : Wrong args", 1);
  $amount = floatval($_GET['m']);
  if($amount <= 0) throw new Exception("Error : Wrong amount", 1);
  $currency = strtoupper((string) $_GET['cr']);
  $Balance = new Balance($User, $App, 'real');

  $PerfectMoney = new PerfectMoney($App);
  $DepositRef = $PerfectMoney->_createDeposit($User, $amount, $Balance, $currency);
  $statusUrl = rtrim(APP_URL, '/').'/app/modules/kr-payment/src/actions/deposit/processPerfectMoney.php';
  $returnUrl = rtrim(APP_URL, '/').'/dashboard.php';

} catch (Exception $e) {
  krypto_log_exception('Perfect Money payment view failed', $e);
  die(json_encode([
    'error' => 1,
    'msg' => krypto_generic_error_message()
  ]));
}

?>
<form action="https://perfectmoney.is/api/step1.asp" method="POST">
<input type="hidden" name="PAYEE_ACCOUNT" value="<?php echo htmlspecialchars((string) $App->_getPerfectMoneyPayeeAccount(), ENT_QUOTES, 'UTF-8'); ?>">
<input type="hidden" name="PAYEE_NAME" value="<?php echo htmlspecialchars((string) $App->_getPerfectMoneyPayeeName(), ENT_QUOTES, 'UTF-8'); ?>">
<input type="hidden" name="PAYMENT_ID" value="<?php echo htmlspecialchars((string) $DepositRef, ENT_QUOTES, 'UTF-8'); ?>">
<input type="hidden" name="PAYMENT_AMOUNT" value="<?php echo htmlspecialchars(number_format($amount, 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>">
<input type="hidden" name="PAYMENT_UNITS" value="<?php echo htmlspecialchars($currency, ENT_QUOTES, 'UTF-8'); ?>">
<input type="hidden" name="STATUS_URL" value="<?php echo htmlspecialchars($statusUrl, ENT_QUOTES, 'UTF-8'); ?>">
<input type="hidden" name="PAYMENT_URL" value="<?php echo htmlspecialchars($returnUrl, ENT_QUOTES, 'UTF-8'); ?>">
<input type="hidden" name="PAYMENT_URL_METHOD" value="LINK">
<input type="hidden" name="NOPAYMENT_URL" value="<?php echo htmlspecialchars($returnUrl, ENT_QUOTES, 'UTF-8'); ?>">
<input type="hidden" name="NOPAYMENT_URL_METHOD" value="LINK">
<input type="hidden" name="SUGGESTED_MEMO" value="">
<input type="hidden" name="BAGGAGE_FIELDS" value="">
<input type="submit" name="PAYMENT_METHOD" value="Pay Now!">
</form>
