<?php

/**
 * Article new view
 *
 * @package Krypto
 * @author Ovrley <hello@ovrley.com>
 */

require "../../../../../config/config.settings.php";

krypto_session_start();

require_once "../../../../../app/src/bootstrap_paths.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/vendor/autoload.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/MySQL/MySQL.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/Security/HtmlSanitizer.php";

require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/App/App.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/App/AppModule.php";

require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/User/User.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/Lang/Lang.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/CryptoApi/CryptoIndicators.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/CryptoApi/CryptoGraph.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/CryptoApi/CryptoHisto.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/CryptoApi/CryptoCoin.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/CryptoApi/CryptoApi.php";


// Load app modules
$App = new App(true);
$App->_loadModulesControllers();

Krypto_Csrf::validateRequest();

try {

    // Check if user is logged
    $User = new User();
    if (!$User->_isLogged()) {
        throw new Exception("User not logged", 1);
    }

    // Init language object
    $Lang = new Lang($User->_getLang(), $App);

    if(empty($_POST) || !isset($_POST['itemid']) || empty($_POST['itemid']) || !is_numeric($_POST['itemid'])) throw new Exception("Permission denied", 1);


    $CryptoApi = new CryptoApi($User, null, $App);

    $Calendar = new Calendar($App);
    $Event = $Calendar->_getEventItem($_POST['itemid'], $CryptoApi);
    if(is_null($Event)) throw new Exception("Not found", 1);


} catch (Exception $e) {
    die(json_encode([
      'error' => 1,
      'msg' => $e->getMessage()
    ]));
}

?>
<header>
  <div>
    <span><?php echo htmlspecialchars($Event['title'], ENT_QUOTES, 'UTF-8'); ?></span>
    <svg onclick="closeCalendarItemView();" class="lnr lnr-cross"><use xlink:href="#lnr-cross"></use></svg>
  </div>
  <span><?php echo htmlspecialchars($Event['formate_date'], ENT_QUOTES, 'UTF-8'); ?></span>
</header>
<?php if(!is_null($Event['coins_kr'])): ?>
<section class="kr-calendareventitem-coininfos">
  <div>
    <img src="<?php echo htmlspecialchars(HtmlSanitizer::safeUrl($Event['coins_kr']->_getIcon()), ENT_QUOTES, 'UTF-8'); ?>" alt="">
    <div>
      <span><?php echo htmlspecialchars($Event['coins_kr']->_getCoinName(), ENT_QUOTES, 'UTF-8'); ?></span>
      <span><?php echo $App->_formatNumber($Event['coins_kr']->_getPrice(), ($Event['coins_kr']->_getPrice() > 10 ? 2 : 4)).' '.$CryptoApi->_getCurrencySymbol(); ?></span>
    </div>
  </div>
  <ul>
    <li class="<?php echo ($Event['coins_kr']->_getCoin24Evolv() < 0 ? 'kr-calendareventitem-coinsinfo-negativ' : ''); ?>">
      <span>Change</span>
      <span><?php echo $App->_formatNumber($Event['coins_kr']->_getCoin24Evolv(), 2); ?>%</span>
    </li>
  </ul>
</section>
<?php endif; ?>
<section class="kr-calendareventitem-content">
  <div class="kr-calendareventitem-content-vote">
    <div class="kr-calendareventitem-content-vote-i">
      <span><?php echo htmlspecialchars($Event['vote_count'], ENT_QUOTES, 'UTF-8'); ?> votes</span>
      <i>&mdash;</i>
      <span><?php echo htmlspecialchars($Event['percentage'], ENT_QUOTES, 'UTF-8'); ?>%</span>
    </div>
    <div class="kr-calendareventitem-content-vote-pb">
      <div style="width:<?php echo (float) $Event['percentage']; ?>%;"></div>
    </div>
  </div>
  <a href="<?php echo htmlspecialchars(HtmlSanitizer::safeUrl($Event['source']), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-grey btn-small" target=_bank rel="noopener noreferrer nofollow">Go to <?php echo htmlspecialchars(substr(str_replace(['http://', 'https://'], ['', ''], $Event['source']), 0, 30).(strlen($Event['source']) > 30 ? '...' : ''), ENT_QUOTES, 'UTF-8'); ?>
  </a>
  <p><?php echo htmlspecialchars($Event['description'], ENT_QUOTES, 'UTF-8'); ?></p>
  <img src="<?php echo htmlspecialchars(HtmlSanitizer::safeUrl($Event['proof']), ENT_QUOTES, 'UTF-8'); ?>" alt="">
  <div class="kr-calendareventitem-content-source">
    <span>Source : <a href="https://coinmarketcal.com/" target=_bank>Coinmarketcal.com</a></span>
  </div>
</section>
