<?php

session_start();

require "../../../../config/config.settings.php";

require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/vendor/autoload.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/MySQL/MySQL.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/User/User.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/Lang/Lang.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/App/App.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/App/AppModule.php";

$App = new App(true);
$App->_loadModulesControllers();

$User = new User();
if(!$User->_isLogged()) die('Error : User not logged');

$Lang = new Lang($User->_getLang(), $App);
$changeNowSwapContext = 'dashboard';

?>
<section class="kr-changenow-dashboard-surface">
  <div class="kr-changenow-dashboard-heading">
    <div>
      <span><?php echo $Lang->tr('Swap center'); ?></span>
      <h1><?php echo $Lang->tr('ChangeNOW transaction workspace'); ?></h1>
    </div>
    <ul>
      <li>
        <strong><?php echo $Lang->tr('Provider'); ?></strong>
        <span><?php echo $Lang->tr('ChangeNOW'); ?></span>
      </li>
      <li>
        <strong><?php echo $Lang->tr('Login'); ?></strong>
        <span><?php echo $Lang->tr('Optional for swaps'); ?></span>
      </li>
      <li>
        <strong><?php echo $Lang->tr('History'); ?></strong>
        <span><?php echo $Lang->tr('Saved for accounts'); ?></span>
      </li>
    </ul>
  </div>
  <?php require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/views/changenow/swap_panel.php"; ?>
</section>
