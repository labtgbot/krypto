<?php

/**
 * Sync ChangeNOW assets, networks, pairs, and local quote metadata.
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

header('Content-Type: application/json');

// Load app modules
$App = new App(true);
$App->_loadModulesControllers();

try {

  $Repository = new ChangeNowMarketRepository();

  if(!$App->_changeNowProviderEnabled()){
    $Repository->_recordSyncStart(ChangeNowMarketData::SYNC_KEY_MARKET_DATA, time());
    $Repository->_recordSyncFinish(ChangeNowMarketData::SYNC_KEY_MARKET_DATA, 'skipped', 'ChangeNOW provider is disabled.', 0, 0, time());
    $App->_saveCronStatus('app/modules/kr-changenow/src/actions/syncMarketData.php');
    die(json_encode([
      'error' => 0,
      'status' => 'skipped',
      'msg' => 'ChangeNOW provider is disabled.'
    ]));
  }

  if(strlen($App->_getChangeNowPublicApiKey()) == 0){
    $Repository->_recordSyncStart(ChangeNowMarketData::SYNC_KEY_MARKET_DATA, time());
    $Repository->_recordSyncFinish(ChangeNowMarketData::SYNC_KEY_MARKET_DATA, 'skipped', 'ChangeNOW public API key is not configured.', 0, 0, time());
    $App->_saveCronStatus('app/modules/kr-changenow/src/actions/syncMarketData.php');
    die(json_encode([
      'error' => 0,
      'status' => 'skipped',
      'msg' => 'ChangeNOW public API key is not configured.'
    ]));
  }

  $MarketData = new ChangeNowMarketData(null, $Repository, $App);
  $result = $MarketData->_sync($App->_getChangeNowEnabledFlows());

  $App->_saveCronStatus('app/modules/kr-changenow/src/actions/syncMarketData.php');

  die(json_encode([
    'error' => 0,
    'sync' => $result
  ]));

} catch (Exception $e) {
  error_log('ChangeNOW market data sync error: '.$e->getMessage());
  die(json_encode([
    'error' => 1,
    'msg' => $e->getMessage()
  ]));
}

?>
