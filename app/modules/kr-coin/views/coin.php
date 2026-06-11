<?php

require "../../../../config/config.settings.php";

krypto_session_start();

require_once "../../../../app/src/bootstrap_paths.php";

require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/vendor/autoload.php";

require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/MySQL/MySQL.php";

require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/User/User.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/Lang/Lang.php";

require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/App/App.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/App/AppModule.php";

require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/CryptoApi/CryptoIndicators.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/CryptoApi/CryptoGraph.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/CryptoApi/CryptoHisto.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/CryptoApi/CryptoCoin.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/CryptoApi/CryptoApi.php";

$App = new App(true);
$App->_loadModulesControllers();

$User = new User();
if(!$User->_isLogged()) die('Error : User not logged');

$Lang = new Lang($User->_getLang(), $App);

try {

  if(empty($_POST) || !isset($_POST['symbol']) || empty($_POST['symbol'])) throw new Exception("Error : Args missing", 1);

  if(!isset($_POST['market'])) $_POST['market'] = "CCCAGG";
  if(strtoupper($_POST['market']) == "COINBASE") $_POST['market'] = "GDAX";

  // Init crypto api
  $CryptoApi = new CryptoApi($User, [$_POST['currency'], null], $App, $_POST['market']);

  // Init coin associate to the graph
  $Coin = new CryptoCoin($CryptoApi, $_POST['symbol'], null, $_POST['market']);
  $CoinCurrency = new CryptoCoin($CryptoApi, $_POST['currency'], null, $_POST['market']);

  $GraphContainer = uniqid().rand().uniqid();

  $availableTrading = null;
  $listMarketAvailable = [];



} catch (Exception $e) {
  die('<span style="color:#fff;">'.$e->getMessage().'</span>');
}


$OrderBook = null;
try {
  //$DepthGraphValue = $Coin->_getDephGraphValue();
  //$OrderBook = $availableTrading->_getOrderPublicBook($Coin->_getSymbol(), $CryptoApi->_getCurrency());
  //$DepthGraphValue = $availableTrading->_getDepthGraphValue($OrderBook);
} catch (Exception $e) {
}

$showChangeNowCoinWidget = $App->_changeNowWidgetEnabled('coin');

?>

<section class="kr-coin-inf">
  <header class="kr-mono">
    <div class="kr-cinf-name">
      <div class="kr-cinf-ndt">
        <span><?php echo $Coin->_getCoinName().' / '.$CoinCurrency->_getCoinName(); ?></span>
        <span><?php echo $Coin->_getSymbol().'/'.$CryptoApi->_getCurrency(); ?></span>
      </div>
    </div>
    <div class="kr-cinf-item">
      <label><?php echo $Lang->tr('Price'); ?></label>
      <span><i kr-cinf-v="PRICE"><?php echo $App->_formatNumber($Coin->_getPrice(), ($Coin->_getPrice() > 10 ? 2 : 4)); ?></i> <?php echo $CryptoApi->_getCurrencySymbol(); ?></span>
    </div>
    <div class="kr-cinf-item <?php echo ($Coin->_getCoin24Evolv() > 0 ? 'kr-cinf-item-positiv' : 'kr-cinf-item-negativ'); ?>">
      <label><?php echo $Lang->tr('Chg. 24H'); ?></label>
      <span><i kr-cinf-v="CHANGE24HOURPCT"><?php echo $App->_formatNumber($Coin->_getCoin24Evolv(), 2); ?></i> %</span>
    </div>
    <div class="kr-cinf-item">
      <label><?php echo $Lang->tr('Market Cap'); ?></label>
      <span><?php echo $CryptoApi->_getCurrencySymbol().' '.$Coin->_formatNumberCommarization($Coin->_getMarketCap()); ?></span>
    </div>
    <div class="kr-cinf-item">
      <label><?php echo $Lang->tr('Direct Vol. 24H'); ?></label>
      <span><?php echo $CryptoApi->_getCurrencySymbol().' '.$Coin->_formatNumberCommarization($Coin->_getDirectVol24()); ?></span>
    </div>
    <div class="kr-cinf-item">
      <label><?php echo $Lang->tr('Total Vol. 24H'); ?></label>
      <span><?php echo $CryptoApi->_getCurrencySymbol().' '.$Coin->_formatNumberCommarization($Coin->_getTotalVol24()); ?></span>
    </div>
  </header>
  <section class="<?php echo ($showChangeNowCoinWidget ? 'kr-coin-with-changenow '.(is_null($availableTrading) ? 'kr-coin-with-changenow-only' : '') : ''); ?>">
    <div style="<?php echo ($showChangeNowCoinWidget ? (is_null($availableTrading) ? 'width:70%;' : 'width:55%;') : (is_null($availableTrading) ? 'width:100%;' : (is_null($OrderBook) ? 'width:85%;' : ''))); ?>">
      <div class="kr-dash-pan-cry kr-dash-pan-cry-vsbl" style="width:100%;" id="<?php echo $GraphContainer; ?>" graph-id="<?php echo $GraphContainer; ?>" type-graph="candlestick" container="<?php echo $GraphContainer; ?>" currency="<?php echo $CryptoApi->_getCurrency(); ?>" market="<?php echo strtoupper($Coin->_getMarket()); ?>" symbol="<?php echo $Coin->_getSymbol(); ?>">

      </div>
    </div>
    <?php if($showChangeNowCoinWidget): ?>
      <section class="kr-changenow-coin-placement">
        <?php echo ChangeNowWidget::_renderFromApp($App, 'coin'); ?>
      </section>
    <?php endif; ?>
  <section class="kr-infoscurrencylf-orderbook kr-infoscurrencylf-orderbook-coin" kr-ob-force="true" style="margin-top:0px;">
    <?php
    foreach (['asks', 'bids'] as $key => $sideOrderBook) {
    ?>
      <div>
        <header>
          <ul>
            <li><?php echo $Lang->tr('Total'); ?></li>
            <li><?php echo $Lang->tr('Amount'); ?></li>
            <li><?php echo $Lang->tr('Price'); ?></li>
          </ul>
        </header>
        <section kr-orderbook-side="<?php echo $sideOrderBook; ?>">

        </section>
      </div>
    <?php } ?>
  </section>
  <script type="text/javascript">
    $(document).ready(function(){
      startLeftInfosOrderBookSync($('.kr-dash-pan-cry').attr('market').toLowerCase(), $('.kr-dash-pan-cry').attr('symbol'), $('.kr-dash-pan-cry').attr('currency'));
    });
  </script>
  </section>
</section>
