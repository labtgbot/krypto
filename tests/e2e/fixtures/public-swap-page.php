<?php

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = (array_key_exists('HTTP_HOST', $_SERVER) ? $_SERVER['HTTP_HOST'] : '127.0.0.1:8772');
if(!defined('APP_URL')) define('APP_URL', $scheme.'://'.$host);

class KryptoE2ePublicSwapApp {
  public function _allowSignup(){ return false; }
  public function _getLogoBlackPath(){ return '/assets/img/logo.svg'; }
  public function _getAppTitle(){ return 'Krypto E2E'; }
}

class KryptoE2ePublicSwapUser {
  public function _isLogged(){ return false; }
}

class KryptoE2ePublicSwapLang {
  public function tr($text){ return $text; }
}

if(!class_exists('ChangeNowApiClient')){
  class ChangeNowApiClient {
    public static function _fromApp($App){ return new self(); }
  }
}

if(!class_exists('ChangeNowMarketData')){
  class ChangeNowMarketData {
    public function __construct($Client = null, $Repository = null, $App = null){}
  }
}

if(!class_exists('ChangeNowPublicSwapRepository')){
  class ChangeNowPublicSwapRepository {}
}

if(!class_exists('ChangeNowPublicSwapFlow')){
  class ChangeNowPublicSwapFlow {
    public function __construct($Client = null, $MarketData = null, $Repository = null, $App = null, $User = null, $Options = []){}

    public function _getInitialState(){
      return [
        'providerEnabled' => true,
        'missingSettings' => [],
        'enabledFlows' => ['standard', 'fixed-rate'],
        'defaultFlow' => 'standard',
        'defaultFrom' => [
          'currency' => 'btc',
          'network' => 'btc'
        ],
        'defaultTo' => [
          'currency' => 'eth',
          'network' => 'eth'
        ],
        'sourceAssets' => [
          [
            'ticker' => 'btc',
            'network' => 'btc',
            'name' => 'Bitcoin',
            'sell' => true,
            'buy' => true
          ]
        ],
        'destinationAssets' => [
          [
            'ticker' => 'eth',
            'network' => 'eth',
            'name' => 'Ethereum',
            'sell' => true,
            'buy' => true
          ]
        ],
        'eligibility' => [
          'allowed' => true,
          'state' => 'allowed',
          'message' => '',
          'country' => ''
        ],
        'supportEmail' => 'support@example.test'
      ];
    }
  }
}

$App = new KryptoE2ePublicSwapApp();
$User = new KryptoE2ePublicSwapUser();
$Lang = new KryptoE2ePublicSwapLang();

?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="krypto-csrf-token" content="e2e-csrf-token">
    <title>Krypto public swap e2e</title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/app/modules/kr-changenow/statics/css/public-swap.css">
  </head>
  <body class="kr-login kr-public-swap-enabled">
    <?php require dirname(__DIR__, 3).'/app/modules/kr-changenow/views/publicSwap.php'; ?>
    <script src="<?php echo APP_URL; ?>/assets/bower/jquery/dist/jquery.min.js"></script>
    <script src="<?php echo APP_URL; ?>/assets/js/csrf.js"></script>
    <script src="<?php echo APP_URL; ?>/app/modules/kr-changenow/statics/js/public-swap.js"></script>
  </body>
</html>
