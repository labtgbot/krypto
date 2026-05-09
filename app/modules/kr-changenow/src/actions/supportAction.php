<?php

/**
 * Admin and manager ChangeNOW transaction support actions.
 *
 * @package Krypto
 */

session_start();

require "../../../../../config/config.settings.php";

require_once "../../../../../app/src/bootstrap_paths.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/vendor/autoload.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/MySQL/MySQL.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/App/App.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/App/AppModule.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/User/User.php";

header('Content-Type: application/json');

function changenow_support_json($payload){
  die(json_encode($payload));
}

$App = new App(true);
$App->_loadModulesControllers();

Krypto_Csrf::validateRequest();

try {
  $User = new User();
  if(!$User->_isLogged() || (!$User->_isAdmin() && !$User->_isManager())){
    throw new Exception("Permission denied", 1);
  }

  if($App->_isDemoMode()) throw new Exception("App currently in demo mode", 1);

  $providerId = '';
  if(array_key_exists('provider_id', $_POST)) $providerId = $_POST['provider_id'];
  elseif(array_key_exists('providerId', $_POST)) $providerId = $_POST['providerId'];
  $providerId = trim((string) $providerId);
  if($providerId == '') throw new ChangeNowApiValidationException('ChangeNOW transaction id is required.', 'Support action requires provider id.');

  $action = strtolower(trim((string) (array_key_exists('action', $_POST) ? $_POST['action'] : '')));
  $actorType = ($User->_isAdmin() ? 'admin' : 'manager');

  $Client = ChangeNowApiClient::_fromApp($App);
  $MarketData = new ChangeNowMarketData($Client, null, $App);
  $Repository = new ChangeNowPublicSwapRepository();
  $Flow = new ChangeNowPublicSwapFlow($Client, $MarketData, $Repository, $App, $User);

  if($action == 'refresh' || $action == 'status'){
    $Flow->_refreshProviderStatus($providerId, $User->_getUserID(), $actorType);
    changenow_support_json([
      'error' => 0,
      'title' => 'Success',
      'msg' => 'ChangeNOW status refreshed.'
    ]);
  }

  if($action == 'refund'){
    $refundAddress = (array_key_exists('refund_address', $_POST) ? $_POST['refund_address'] : (array_key_exists('refundAddress', $_POST) ? $_POST['refundAddress'] : ''));
    $refundExtraId = (array_key_exists('refund_extra_id', $_POST) ? $_POST['refund_extra_id'] : (array_key_exists('refundExtraId', $_POST) ? $_POST['refundExtraId'] : ''));
    $Flow->_requestRefundByProviderId($providerId, $refundAddress, $refundExtraId, $User->_getUserID(), $actorType);
    changenow_support_json([
      'error' => 0,
      'title' => 'Success',
      'msg' => 'ChangeNOW refund requested.'
    ]);
  }

  if($action == 'continue'){
    $Flow->_continueSwapByProviderId($providerId, $User->_getUserID(), $actorType);
    changenow_support_json([
      'error' => 0,
      'title' => 'Success',
      'msg' => 'ChangeNOW continue requested.'
    ]);
  }

  if($action == 'note'){
    $note = (array_key_exists('support_note', $_POST) ? $_POST['support_note'] : (array_key_exists('note', $_POST) ? $_POST['note'] : ''));
    $Flow->_saveSupportNoteByProviderId($providerId, $note, $User->_getUserID(), $actorType);
    changenow_support_json([
      'error' => 0,
      'title' => 'Success',
      'msg' => 'ChangeNOW support note saved.'
    ]);
  }

  throw new ChangeNowApiValidationException('Unknown ChangeNOW support action.', 'Unsupported ChangeNOW support action: '.$action);
} catch (ChangeNowApiException $e) {
  changenow_support_json([
    'error' => 1,
    'msg' => $e->_getUserMessage(),
    'type' => $e->_getType()
  ]);
} catch (Exception $e) {
  changenow_support_json([
    'error' => 1,
    'msg' => $e->getMessage()
  ]);
}

?>
