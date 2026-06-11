<?php

/**
 * Load left coin infos
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

require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/CryptoApi/CryptoOrder.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/CryptoApi/CryptoNotification.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/CryptoApi/CryptoIndicators.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/CryptoApi/CryptoGraph.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/CryptoApi/CryptoHisto.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/CryptoApi/CryptoCoin.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/CryptoApi/CryptoApi.php";
require_once dirname(__DIR__)."/OrderBookRequest.php";

// Load app modules
$App = new App(true);
$App->_loadModulesControllers();

Krypto_Csrf::validateRequest();

// Check if user is logged
$User = new User();
if (!$User->_isLogged()) {
    throw new Exception("User is not logged", 1);
}

header('Content-Type: application/json; charset=UTF-8');

try {
  $exchangeClass = KryptoOrderBookRequest::exchangeClassName(isset($_GET['market']) ? $_GET['market'] : '');
  $pairSymbol = KryptoOrderBookRequest::pairSymbol(isset($_GET['symbol']) ? $_GET['symbol'] : '', isset($_GET['currency']) ? $_GET['currency'] : '');

  if(!class_exists($exchangeClass)){
    throw new RuntimeException('Order-book provider is not installed.');
  }

  $exchange = new $exchangeClass();
  if(!method_exists($exchange, 'fetch_order_book')){
    throw new RuntimeException('Order-book provider does not expose fetch_order_book.');
  }

  echo json_encode($exchange->fetch_order_book($pairSymbol, 100));
} catch (InvalidArgumentException $e) {
  http_response_code(400);
  echo json_encode([
    'error' => true,
    'message' => 'Invalid order book request.'
  ]);
} catch (Throwable $e) {
  error_log('Order-book fetch failed: '.$e->getMessage());
  http_response_code(503);
  echo json_encode([
    'error' => true,
    'message' => 'Order book is unavailable.'
  ]);
}

?>
