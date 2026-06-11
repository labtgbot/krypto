<?php

/**
 * Admin trading settings
 *
 * @package Krypto
 * @author Ovrley <hello@ovrley.com>
 */

require "../../../../config/config.settings.php";

krypto_session_start();

require_once "../../../../app/src/bootstrap_paths.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/vendor/autoload.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/MySQL/MySQL.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/App/App.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/App/AppModule.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/User/User.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/Lang/Lang.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/CryptoApi/CryptoApi.php";

// Load app modules
$App = new App(true);
$App->_loadModulesControllers();

// Check loggin & permission
$User = new User();
if(!$User->_isLogged()) throw new Exception("User are not logged", 1);
if(!$User->_isAdmin()) throw new Exception("Permission denied", 1);

// Init language object
$Lang = new Lang($User->_getLang(), $App);

// Init admin object
$Admin = new Admin();

$SymbolListAvailable = [];
$MoneyListAvailable = [];
foreach (MySQL::querySqlRequest("SELECT code_iso_currency FROM currency_krypto ORDER BY code_iso_currency") as $currency) {
  $MoneyListAvailable[] = $currency['code_iso_currency'];
}
if(count($MoneyListAvailable) == 0) $MoneyListAvailable = ['USD', 'EUR', 'GBP'];

?>
<form class="kr-admin kr-adm-post-evs" action="<?php echo APP_URL; ?>/app/modules/kr-admin/src/actions/saveTrading.php" method="post">
  <nav class="kr-admin-nav">
    <ul>
      <?php foreach ($Admin->_getListSection() as $key => $section) { // Get list admin section
        echo '<li type="module" kr-module="admin" kr-view="'.strtolower(str_replace(' ', '', $section)).'" '.($section == 'Trading' ? 'class="kr-admin-nav-selected"' : '').'>'.$Lang->tr($section).'</li>';
      } ?>
    </ul>
  </nav>

  <h3><?php echo $Lang->tr('Trading provider'); ?></h3>
  <div class="kr-admin-line kr-admin-line-cls">
    <div class="kr-admin-field">
      <div>
        <label><?php echo $Lang->tr('Swap provider'); ?></label>
      </div>
      <div>
        <span>ChangeNOW</span>
      </div>
    </div>

    <div class="kr-admin-field">
      <div>
        <label><?php echo $Lang->tr('Provider status'); ?></label>
      </div>
      <div>
        <span><?php echo $Lang->tr($App->_changeNowProviderEnabled() ? 'Enabled' : 'Disabled'); ?></span>
      </div>
    </div>
  </div>

    <h3><?php echo $Lang->tr('Balance configuration'); ?></h3>
    <div class="kr-admin-line kr-admin-line-cls">
      <div class="kr-admin-field">
        <div>
          <label><?php echo $Lang->tr('Show balance estimation'); ?></label><br/>
          <span><?php echo $Lang->tr('Based on payment history'); ?></span>
        </div>
        <div>
          <div class="ckbx-style-14">
              <input type="checkbox" id="kr-adm-chk-balancestimationshown" <?php echo ($App->_getBalanceEstimationShown() ? 'checked' : ''); ?> name="kr-adm-chk-balancestimationshown">
              <label for="kr-adm-chk-balancestimationshown"></label>
          </div>
        </div>
      </div>
      <div class="kr-admin-field">
        <div>
          <label><?php echo $Lang->tr('User user currency select'); ?></label><br/>
        </div>
        <div>
          <div class="ckbx-style-14">
              <input type="checkbox" id="kr-adm-chk-balancestimationuseuser" <?php echo ($App->_getBalanceEstimationUserCurrency() ? 'checked' : ''); ?> name="kr-adm-chk-balancestimationuseuser">
              <label for="kr-adm-chk-balancestimationuseuser"></label>
          </div>
        </div>
      </div>
      <div class="kr-admin-field">
        <div>
          <label><?php echo $Lang->tr('Show balance estimation in'); ?></label>
        </div>
        <div>
          <select name="kr-adm-balancestimationcurrency">
            <?php
            foreach ($MoneyListAvailable as $value) {
              echo '<option '.($App->_getBalanceEstimationSymbol() == $value ? 'selected' : '').' value="'.$value.'">'.$value.'</option>';
            }
            ?>
          </select>
        </div>
      </div>
    </div>

    <h3><?php echo $Lang->tr('Referal system configuration'); ?></h3>
    <div class="kr-admin-line kr-admin-line-cls">
      <div class="kr-admin-field">
        <div>
          <label><?php echo $Lang->tr('Enable referral (ChangeNOW need to be enabled)'); ?></label>
        </div>
        <div>
          <div class="ckbx-style-14">
              <input type="checkbox" id="kr-adm-chk-enablereferal" <?php echo ($App->_referalEnabled() ? 'checked' : ''); ?> name="kr-adm-chk-enablereferal">
              <label for="kr-adm-chk-enablereferal"></label>
          </div>
        </div>
      </div>
      <div class="kr-admin-field">
        <div>
          <label><?php echo $Lang->tr('Referal comission (in $, fixed amount)'); ?></label>
        </div>
        <div>
          <input type="text" placeholder="<?php echo $Lang->tr('Referal comission (in $, fixed amount) ex : When a referal signup & deposit real cash, the refer win 5 $ (value = 5)'); ?>" name="kr-adm-referalcomission" value="<?php echo $App->_getReferalWinAmount(); ?>">
        </div>
      </div>
    </div>

    <h3><?php echo $Lang->tr('Deposit configuration'); ?></h3>

    <div class="kr-admin-line kr-admin-line-cls">
      <div class="kr-admin-field">
        <div>
          <label><?php echo $Lang->tr('Deposit fees (in %)'); ?></label>
        </div>
        <div>
          <input type="text" placeholder="<?php echo $Lang->tr('Deposit fees (in %)'); ?>" name="kr-adm-depositfees" value="<?php echo $App->_getFeesDeposit(); ?>">
        </div>
      </div>
      <div class="kr-admin-field">
        <div>
          <label><?php echo $Lang->tr('Deposit minimum (in $)'); ?></label>
        </div>
        <div>
          <input type="text" placeholder="<?php echo $Lang->tr('Deposit minimum (in $)'); ?>" name="kr-adm-depositminimum" value="<?php echo $App->_getMinimalDeposit(); ?>">
        </div>
      </div>
      <div class="kr-admin-field">
        <div>
          <label><?php echo $Lang->tr('Deposit maximum (in $)'); ?></label>
        </div>
        <div>
          <input type="text" placeholder="<?php echo $Lang->tr('Deposit maximum (in $)'); ?>" name="kr-adm-depositmaximum" value="<?php echo $App->_getMaximalDeposit(); ?>">
        </div>
      </div>
      <div class="kr-admin-field">
        <div>
          <label><?php echo $Lang->tr('Deposit currencies allowed'); ?></label>
        </div>
        <div>
          <select id="select-state-disabled" name="deposit_currencies_allowed[]" multiple class="demo-default" placeholder="Select some currencies">
            <?php
            foreach ($MoneyListAvailable as $value) {
              ?>
              <option <?php if(!is_null($App->_getListCurrencyDepositAvailable()) && in_array($value, $App->_getListCurrencyDepositAvailable())) echo 'selected'; ?> value="<?php echo $value; ?>"><?php echo $value; ?></option>
              <?php
            }
            ?>
        </select>
        <script type="text/javascript">
        $('#select-state-disabled').selectize();
        </script>
        </div>
      </div>

      <div class="kr-admin-field">
        <div>
          <label><?php echo $Lang->tr('Bank transfert deposit agreement'); ?></label>
        </div>
        <div>
          <textarea name="banktransfert_alert_deposit" style="width:100%; height:161px;"><?php echo $App->_getDepositMessage(); ?></textarea>
        </div>
      </div>
  </div>
    <div class="kr-admin-action">
      <input type="submit" class="btn btn-orange" name="" value="<?php echo $Lang->tr('Save'); ?>">
    </div>
</form>
