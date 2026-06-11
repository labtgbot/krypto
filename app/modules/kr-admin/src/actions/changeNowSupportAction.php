<?php

/**
 * Admin ChangeNOW transaction support actions.
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

header('Content-Type: application/json');

function changenow_admin_support_json($payload){
  die(json_encode($payload));
}

function changenow_admin_support_value($key, $fallback = ''){
  if(array_key_exists($key, $_POST)) return trim((string) $_POST[$key]);
  return $fallback;
}

function changenow_admin_support_payload(){
  $refundAddress = changenow_admin_support_value('refund_address', changenow_admin_support_value('refundAddress'));
  $payload = [
    'source' => 'admin_panel'
  ];

  if($refundAddress != ''){
    $payload['refund_address_fingerprint'] = hash('sha256', $refundAddress);
  }

  $refundExtraId = changenow_admin_support_value('refund_extra_id', changenow_admin_support_value('refundExtraId'));
  if($refundExtraId != ''){
    $payload['refund_extra_id_present'] = true;
  }

  return $payload;
}

$App = new App(true);
$App->_loadModulesControllers();

Krypto_Csrf::validateRequest();

try {
  $User = new User();
  if(!$User->_isLogged()) throw new Exception("Your are not logged", 1);
  if(!$User->_isAdmin()) throw new Exception("Error : Permission denied", 1);
  if($App->_isDemoMode()) throw new Exception("App currently in demo mode", 1);

  $providerId = changenow_admin_support_value('provider_id', changenow_admin_support_value('providerId'));
  if($providerId == '') throw new Exception("ChangeNOW transaction id is required.", 1);

  $action = strtolower(changenow_admin_support_value('action'));
  $actorUserId = $User->_getUserID();

  if(class_exists('ChangeNowApiClient') && class_exists('ChangeNowMarketData') &&
     class_exists('ChangeNowPublicSwapRepository') && class_exists('ChangeNowPublicSwapFlow')){
    $Client = ChangeNowApiClient::_fromApp($App);
    $MarketData = new ChangeNowMarketData($Client, null, $App);
    $Repository = new ChangeNowPublicSwapRepository();
    $Flow = new ChangeNowPublicSwapFlow($Client, $MarketData, $Repository, $App, $User);

    if($action == 'refresh' || $action == 'status'){
      $Flow->_refreshProviderStatus($providerId, $actorUserId, 'admin');
      changenow_admin_support_json(['error' => 0, 'title' => 'Success', 'msg' => 'ChangeNOW status refreshed.']);
    }

    if($action == 'refund'){
      $Flow->_requestRefundByProviderId(
        $providerId,
        changenow_admin_support_value('refund_address', changenow_admin_support_value('refundAddress')),
        changenow_admin_support_value('refund_extra_id', changenow_admin_support_value('refundExtraId')),
        $actorUserId,
        'admin'
      );
      changenow_admin_support_json(['error' => 0, 'title' => 'Success', 'msg' => 'ChangeNOW refund requested.']);
    }

    if($action == 'continue'){
      $Flow->_continueSwapByProviderId($providerId, $actorUserId, 'admin');
      changenow_admin_support_json(['error' => 0, 'title' => 'Success', 'msg' => 'ChangeNOW continue requested.']);
    }

    if($action == 'note'){
      $Flow->_saveSupportNoteByProviderId($providerId, changenow_admin_support_value('support_note', changenow_admin_support_value('note')), $actorUserId, 'admin');
      changenow_admin_support_json(['error' => 0, 'title' => 'Success', 'msg' => 'ChangeNOW support note saved.']);
    }
  }

  $Repository = new ChangeNowAdminRepository();
  if($action == 'note'){
    $Repository->_saveSupportNote($providerId, changenow_admin_support_value('support_note', changenow_admin_support_value('note')), $actorUserId);
    changenow_admin_support_json(['error' => 0, 'title' => 'Success', 'msg' => 'ChangeNOW support note saved.']);
  }

  if($action == 'refresh' || $action == 'status' || $action == 'refund' || $action == 'continue'){
    $Repository->_recordSupportAction($providerId, ($action == 'status' ? 'refresh' : $action), $actorUserId, changenow_admin_support_payload());
    $message = ($action == 'refresh' || $action == 'status' ? 'ChangeNOW refresh requested.' : 'ChangeNOW '.$action.' requested.');
    changenow_admin_support_json(['error' => 0, 'title' => 'Success', 'msg' => $message]);
  }

  throw new Exception("Unknown ChangeNOW support action.", 1);
} catch (Exception $e) {
  changenow_admin_support_json([
    'error' => 1,
    'msg' => (method_exists($e, '_getUserMessage') ? $e->_getUserMessage() : $e->getMessage())
  ]);
}

?>
