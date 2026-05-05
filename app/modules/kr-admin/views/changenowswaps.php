<?php

/**
 * Admin ChangeNOW transaction lookup and support actions.
 *
 * @package Krypto
 */

session_start();

require "../../../../config/config.settings.php";

require_once "../../../../app/src/bootstrap_paths.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/vendor/autoload.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/MySQL/MySQL.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/App/App.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/App/AppModule.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/User/User.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/Lang/Lang.php";

$App = new App(true);
$App->_loadModulesControllers();

$User = new User();
if(!$User->_isLogged()) throw new Exception("User are not logged", 1);
if(!$User->_isAdmin()) throw new Exception("Permission denied", 1);

$Lang = new Lang($User->_getLang(), $App);
$Admin = new Admin();

$ChangeNowRepository = new ChangeNowPublicSwapRepository();
$ChangeNowFilters = [
  'search' => (!empty($_POST) && isset($_POST['search']) ? $_POST['search'] : ''),
  'status' => (!empty($_POST) && isset($_POST['status']) ? $_POST['status'] : '')
];
$ChangeNowTransactions = $ChangeNowRepository->_listForSupport($ChangeNowFilters, 100);
$ChangeNowReferralReport = $ChangeNowRepository->_referralReportSummary($ChangeNowFilters, 500);

?>
<section class="kr-admin">
  <nav class="kr-admin-nav">
    <ul>
      <?php foreach ($Admin->_getListSection() as $key => $section) {
        echo '<li type="module" kr-module="admin" kr-view="'.strtolower(str_replace(' ', '', $section)).'" '.($section == 'ChangeNOW swaps' ? 'class="kr-admin-nav-selected"' : '').'>'.$Lang->tr($section).'</li>';
      } ?>
    </ul>
  </nav>

  <div class="kr-manager-filter">
    <form class="kr-changenow-filter-search-f" kr-module="admin" kr-view="changenowswaps">
      <input type="text" name="" placeholder="<?php echo $Lang->tr('Provider ID, user, status, asset, referral'); ?>" value="<?php echo htmlspecialchars($ChangeNowFilters['search']); ?>">
    </form>
  </div>

  <?php require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/modules/kr-changenow/views/supportTransactions.php"; ?>
</section>
