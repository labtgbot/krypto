<?php

/**
 * Public ChangeNOW quote, address validation, transaction creation, and status.
 *
 * @package Krypto
 */

session_start();

require "../../../../../config/config.settings.php";

require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/vendor/autoload.php";

require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/MySQL/MySQL.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/App/App.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/App/AppModule.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/User/User.php";

header('Content-Type: application/json');

function changenow_public_json($payload){
  die(json_encode($payload));
}

function changenow_public_error($errorCode, $message, $type = 'error'){
  changenow_public_json([
    'error' => $errorCode,
    'type' => $type,
    'msg' => $message
  ]);
}

$App = new App(true);
$App->_loadModulesControllers();

try {
  $User = new User();
  $loggedUserId = null;
  if($User->_isLogged()) $loggedUserId = $User->_getUserID();

  $Client = ChangeNowApiClient::_fromApp($App);
  $MarketData = new ChangeNowMarketData($Client, null, $App);
  $Repository = new ChangeNowPublicSwapRepository();
  $Flow = new ChangeNowPublicSwapFlow($Client, $MarketData, $Repository, $App, ($User->_isLogged() ? $User : null));

  $action = '';
  if(array_key_exists('action', $_POST)) $action = $_POST['action'];
  elseif(array_key_exists('action', $_GET)) $action = $_GET['action'];
  $action = strtolower(trim((string) $action));

  if($action == 'quote'){
    changenow_public_json([
      'error' => 0,
      'quote' => $Flow->_getQuote($_POST)
    ]);
  }

  if($action == 'validate'){
    changenow_public_json([
      'error' => 0,
      'validation' => $Flow->_validateDestinationAddress($_POST)
    ]);
  }

  if($action == 'destinations'){
    changenow_public_json([
      'error' => 0,
      'assets' => $Flow->_getDestinationAssets($_POST)
    ]);
  }

  if($action == 'create'){
    $sessionKey = ChangeNowPublicSwapFlow::_sessionKeyFromSession($_SESSION);
    changenow_public_json([
      'error' => 0,
      'swap' => $Flow->_createSwap($_POST, $sessionKey, $loggedUserId)
    ]);
  }

  if($action == 'status'){
    $lookupToken = '';
    if(array_key_exists('lookupToken', $_POST)) $lookupToken = $_POST['lookupToken'];
    elseif(array_key_exists('lookup_token', $_POST)) $lookupToken = $_POST['lookup_token'];
    elseif(array_key_exists('lookupToken', $_GET)) $lookupToken = $_GET['lookupToken'];
    elseif(array_key_exists('lookup_token', $_GET)) $lookupToken = $_GET['lookup_token'];
    changenow_public_json([
      'error' => 0,
      'status' => $Flow->_getStatus($lookupToken)
    ]);
  }

  if($action == 'refund'){
    $lookupToken = '';
    if(array_key_exists('lookupToken', $_POST)) $lookupToken = $_POST['lookupToken'];
    elseif(array_key_exists('lookup_token', $_POST)) $lookupToken = $_POST['lookup_token'];
    $refundAddress = (array_key_exists('refundAddress', $_POST) ? $_POST['refundAddress'] : (array_key_exists('refund_address', $_POST) ? $_POST['refund_address'] : ''));
    $refundExtraId = (array_key_exists('refundExtraId', $_POST) ? $_POST['refundExtraId'] : (array_key_exists('refund_extra_id', $_POST) ? $_POST['refund_extra_id'] : ''));
    changenow_public_json([
      'error' => 0,
      'status' => $Flow->_requestRefund($lookupToken, $refundAddress, $refundExtraId, $loggedUserId, (is_null($loggedUserId) ? 'anonymous' : 'user'))
    ]);
  }

  if($action == 'continue'){
    $lookupToken = '';
    if(array_key_exists('lookupToken', $_POST)) $lookupToken = $_POST['lookupToken'];
    elseif(array_key_exists('lookup_token', $_POST)) $lookupToken = $_POST['lookup_token'];
    changenow_public_json([
      'error' => 0,
      'status' => $Flow->_continueSwap($lookupToken, $loggedUserId, (is_null($loggedUserId) ? 'anonymous' : 'user'))
    ]);
  }

  changenow_public_error(2, 'Unknown ChangeNOW public swap action.', 'validation');
} catch (ChangeNowApiException $e) {
  $errorCode = ($e instanceof ChangeNowApiValidationException ? 2 : 1);
  changenow_public_error($errorCode, $e->_getUserMessage(), $e->_getType());
} catch (Exception $e) {
  changenow_public_error(1, $e->getMessage(), 'error');
}

?>
