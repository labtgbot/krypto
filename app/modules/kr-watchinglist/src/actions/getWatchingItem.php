<?php

/**
 * WatchingList item view
 *
 * @package Krypto
 * @author Ovrley <hello@ovrley.com>
 */

session_start();

require "../../../../../config/config.settings.php";

require_once "../../../../../app/src/bootstrap_paths.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/vendor/autoload.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/MySQL/MySQL.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/User/User.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/App/App.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/App/AppModule.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/CryptoApi/CryptoIndicators.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/CryptoApi/CryptoGraph.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/CryptoApi/CryptoHisto.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/CryptoApi/CryptoCoin.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/CryptoApi/CryptoApi.php";

// Load app modules
$App = new App(true);
$App->_loadModulesControllers();

try {

  $Request = ($_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET);
  if(!empty($Request['t']) && $Request['t'] == "add"){
    if($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Error : Invalid request method", 1);
    Krypto_Csrf::validateRequest();
  }

  // Check if user is logged
  $User = new User();
  if(!$User->_isLogged()) throw new Exception("User are not logged", 1);

  // Check args
  if(empty($Request) || empty($Request['symb'])) throw new Exception("Error : Args missing", 1);

  // Init CryptoApi object
  $CryptoApi = new CryptoApi(null, [$Request['currency'], $Request['currency']], $App, (isset($Request['market']) ? $Request['market'] : 'CCCAGG'));

  // Get coin data
  $Coin = $CryptoApi->_getCoin($Request['symb']);


  // If item need to be added --> add
  if(!empty($Request['t']) && $Request['t'] == "add"){
    // Init watching list
    $WatchingList = new WatchingList($CryptoApi, $User);
    $WatchingList->_addItem($Coin->_getSymbol(), $Request['currency'], (isset($Request['market']) ? $Request['market'] : 'CCCAGG'));
  }

} catch (Exception $e) { // If error detected, show error
  die(json_encode([
    'error' => 1,
    'msg' => $e->getMessage()
  ]));
}

?>
<li kr-watchinglistpair="<?php echo $Coin->_getMarket().':'.$Coin->_getSymbol().'/'.$CryptoApi->_getCurrency(); ?>" class="<?php //echo ($i == 2 ? 'kr-wtchl-lst-selected' : ''); ?>">
  <div>
    <span><?php echo $Coin->_getSymbol().'/'.$CryptoApi->_getCurrency(); ?></span>
  </div>
  <div>
    <span class="kr-watchinglistpair-price"><?php echo $App->_formatNumber($Coin->_getPrice(), ($Coin->_getPrice() > 10 ? 2 : 5)); ?></span>
  </div>
  <div>
    <span class="kr-watchinglistpair-evolv"><?php echo $App->_formatNumber($Coin->_getCoin24Evolv(), 2); ?>%</span>
  </div>
  <div class="kr-wtchl-lst-remove">
    <svg class="lnr lnr-cross"><use xlink:href="#lnr-cross"></use></svg>
  </div>
</li>
