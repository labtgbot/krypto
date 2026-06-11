<?php

/**
 * Admin dashboard page
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
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/CryptoApi/CryptoIndicators.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/CryptoApi/CryptoGraph.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/CryptoApi/CryptoHisto.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/CryptoApi/CryptoCoin.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/CryptoApi/CryptoApi.php";

// Load app modules
$App = new App(true);
$App->_loadModulesControllers();

// Check loggin & permission
$User = new User();
if(!$User->_isLogged()) throw new Exception("User are not logged", 1);
if(!$User->_isAdmin() && !$User->_isManager()) throw new Exception("Permission denied", 1);

if(empty($_POST) || !isset($_POST['idu']) || empty($_POST['idu'])) throw new Exception("Permission denied", 1);

// Init language object
$Lang = new Lang($User->_getLang(), $App);

// Init admin object
$Manager = new Manager($App);

$UserFetched = $Manager->_getUserByManager($_POST['idu']);
$PageViewed = 'card';
if(isset($_POST['np']) && !empty($_POST['np']) && in_array($_POST['np'], ['card', 'payments', 'security'])) $PageViewed = $_POST['np'];

$Charge = $UserFetched->_getCharge($App);
?>
<section class="kr-manager kr-admin">
  <nav class="kr-manager-nav">
    <ul>
      <?php foreach ($Manager->_getListSection() as $key => $section) { // Get list admin section
        $NotificationNumber = $Manager->_getNumberManagerNotification(strtolower(str_replace(' ', '', $section)));
        echo '<li type="module" kr-module="manager" kr-view="'.strtolower(str_replace(' ', '', $section)).'" '.($section == 'Users' ? 'class="kr-manager-nav-selected"' : '').'>'.$Lang->tr($section).' '.($NotificationNumber > 0 ? '<span>'.$NotificationNumber.'</span>' : '').'</li>';
      } ?>
    </ul>
  </nav>
  <section class="kr-manager-user-profile">
    <nav>
      <ul class="kr-manager-user-profile-tab">
        <li onclick="showManagerUserInfos('<?php echo $_POST['idu']; ?>');" class="<?php if($PageViewed == 'card') echo 'kr-manager-user-profile-tabs'; ?>"><?php echo $Lang->tr('Card'); ?></li>
        <li onclick="showManagerUserInfos('<?php echo $_POST['idu']; ?>', 'payments');" class="<?php if($PageViewed == 'payments') echo 'kr-manager-user-profile-tabs'; ?>"><?php echo $Lang->tr('Payments'); ?></li>
        <li onclick="showManagerUserInfos('<?php echo $_POST['idu']; ?>', 'security');" class="<?php if($PageViewed == 'security') echo 'kr-manager-user-profile-tabs'; ?>"><?php echo $Lang->tr('Security'); ?></li>
      </ul>
    </nav>
    <section>
      <section class="kr-manager-user-profile-mi">
        <div style="background-image:url('<?php echo $UserFetched->_getPicture(); ?>')">
        </div>
        <div>
          <h3><?php echo $UserFetched->_getName(); ?></h3>
          <span><?php echo $UserFetched->_getEmail(); ?></span>
          <div class="kr-manager-user-profile-mi-country">
            <div style="background-image:url('<?php echo APP_URL; ?>/assets/img/icons/country/<?php echo strtolower($UserFetched->_getUserLocation(true)); ?>.png');"></div>
            <span><?php echo $UserFetched->_getUserLocation(); ?></span>
          </div>
        </div>

        <div class="kr-manager-user-profile-mi-btn">
          <?php if($User->_getUserID() != $UserFetched->_getUserID()): ?>
            <?php if(!($UserFetched->_isAdmin() || $UserFetched->_isManager()) && !$User->_isAdmin()): ?>
              <ul class="kr-manager-user-profile-actionlist">
                <li><button  class="btn btn-autowidth btn-adm-user-c" style="margin-bottom:10px;" idu="<?php echo $UserFetched->_getUserID(); ?>"><?php echo $Lang->tr('Edit profile'); ?></button></li>
                <li><button  class="btn btn-autowidth btn-red btn-small btn-adm-user-delete" idu="<?php echo $UserFetched->_getUserID(); ?>"><?php echo $Lang->tr('Delete profile'); ?></button></li>
              </ul>
            <?php endif; ?>
            <?php
            if($User->_isAdmin()){
              ?>
              <ul class="kr-manager-user-profile-actionlist">
                <li><button  class="btn btn-autowidth btn-adm-user-c" style="margin-bottom:10px;" idu="<?php echo $UserFetched->_getUserID(); ?>"><?php echo $Lang->tr('Edit profile'); ?></button></li>
                <li><button  class="btn btn-autowidth btn-red btn-small btn-adm-user-delete" idu="<?php echo $UserFetched->_getUserID(); ?>"><?php echo $Lang->tr('Delete profile'); ?></button></li>
              </ul>
              <?php
            }
            ?>
          <?php endif; ?>
        </div>
      </section>
      <?php if($PageViewed == "card"): ?>
      <section class="kr-manager-user-profile-tabinfos">
        <ul>
          <li>
            <div><?php echo $Lang->tr('Name'); ?></div>
            <div><?php echo $UserFetched->_getName(); ?></div>
          </li>
          <li>
            <div><?php echo $Lang->tr('Last location'); ?></div>
            <div><?php echo $UserFetched->_getUserLocation(); ?></div>
          </li>
          <li>
            <div><?php echo $Lang->tr('Last login'); ?></div>
            <div><?php echo (is_null($UserFetched->_getLastLogin()) ? "-" : $UserFetched->_getLastLogin()->format('d/m/Y H:i:s')); ?></div>
          </li>
          <li>
            <div><?php echo $Lang->tr('Language'); ?></div>
            <div><?php echo strtoupper($UserFetched->_getLang(true)); ?></div>
          </li>
          <li>
            <div><?php echo $Lang->tr('Currency use'); ?></div>
            <div><?php echo $UserFetched->_getCurrency(); ?></div>
          </li>
          <?php if($App->_subscriptionEnabled()): ?>
          <li>
            <div><?php echo $Lang->tr('Subscription'); ?></div>
            <div>
              <?php if($Charge->_isTrial() && !$Charge->_activeAbo() && $App->_subscriptionEnabled()): ?>
                <span><?php echo $Lang->tr('Trial version'); ?>, <b><?php echo $Charge->_getTrialNumberDay().' '.$Lang->tr('day').($Charge->_getTrialNumberDay() > 1 ? 's' : '').' '.$Lang->tr('left'); ?></b></span>
              <?php elseif($App->_subscriptionEnabled()): ?>
                <span><?php echo $Lang->tr('Premium'); ?>, <b><?php echo $Charge->_getTimeRes().' '.$Lang->tr('day').($Charge->_getTimeRes() > 1 ? 's' : '').' '.$Lang->tr('left'); ?></b></span>
              <?php endif; ?>
            </div>
          </li>
        <?php endif; ?>
        </ul>
        <ul>
          <li>
            <div><?php echo $Lang->tr('Signup with'); ?></div>
            <div><?php echo ucfirst($UserFetched->_getOauth()); ?></div>
          </li>
          <?php
          if($App->_getIdentityEnabled()){
            $Identity = new Identity($UserFetched);
          ?>
            <li>
              <div><?php echo $Lang->tr('Identity verification'); ?></div>
              <?php
              if($Identity->_identityInVerifcation()) echo '<div><span class="kr-manager-user-profile-tabinfos-tag-0" >'.$Lang->tr('In verification').'</span></div>';
              if($Identity->_identityVerified()) echo '<div><span class="kr-manager-user-profile-tabinfos-tag-1">'.$Lang->tr('Verified').'</span></div>';
              if($Identity->_identityWizardNotStarted()) echo '<div><span class="kr-manager-user-profile-tabinfos-tag-0" style="background-color:#178adc;">'.$Lang->tr('In verification').'</span></div>';
              ?>

            <?php  ?>
            </li>
          <?php } ?>
          <li>
            <div><?php echo $Lang->tr('Notification'); ?></div>
            <div><span class="kr-manager-user-profile-tabinfos-tag-<?php echo (strlen($UserFetched->_getPushbulletKey()) > 0 ? '1' : '0'); ?>"><?php echo (strlen($UserFetched->_getPushbulletKey()) > 0 ? 'Enable' : 'Disabled'); ?></span></div>
          </li>
          <li>
            <div><?php echo $Lang->tr('2 Step Authentification'); ?></div>
            <div><span class="kr-manager-user-profile-tabinfos-tag-<?php echo ($UserFetched->_isTwostep() ? '1' : '0'); ?>"><?php echo ($UserFetched->_isTwostep() ? 'Enable' : 'Disabled'); ?></span></div>
          </li>
          <li>
            <div><?php echo $Lang->tr('Created date'); ?></div>
            <div><?php echo date('d/m/Y H:i:s', $UserFetched->_getCreatedDate()); ?></div>
          </li>
          <li>
            <div><?php echo $Lang->tr('User status'); ?></div>
            <div><span class="kr-manager-user-profile-tabinfos-tag-<?php echo ($UserFetched->_isActive() ? '1' : '0'); ?>"><?php echo ($UserFetched->_isActive() ? 'Active' : 'Inactive'); ?></span></div>
          </li>
        </ul>
      </section>
      <section class="kr-manager-user-profile-nlc">
        <section>
          <header>
            <h4><?php echo $Lang->tr('Login history'); ?></h4>
            <button type="button" class="btn btn-small btn-autowidth btn-orange" name="button"><?php echo $Lang->tr('More details'); ?></button>
          </header>
          <?php
          $LoginUserHistory = $UserFetched->_getHistoryLoginUser();
          ?>
          <table class="kr-manager-user-profile-table">
            <?php
            foreach (array_slice($LoginUserHistory, 0, 8) as $key => $UserLoginDetails) {
            ?>
              <tr>
                <td>
                  <div class="kr-manager-user-profile-login-dt-loc">
                    <div style="background-image:url('<?php echo APP_URL; ?>/assets/img/icons/country/<?php echo strtolower($UserLoginDetails['country_code_user_login_history']); ?>.png');"></div>
                    <span><?php echo $UserLoginDetails['location_user_login_history']; ?></span>
                  </div>
                </td>
                <td>
                  <div>
                    <span><?php echo ($App->_isDemoMode() ? '***.***.***.***' : $UserLoginDetails['ip_user_login_history']); ?></span>
                  </div>
                </td>
                <td>
                  <div>
                    <span><?php echo date('d/m/Y H:i:s', $UserLoginDetails['date_user_login_history']); ?></span>
                  </div>
                </td>
              </tr>
            <?php } ?>
          </table>
        </section>
        <section>
          <header>
            <h4><?php echo $Lang->tr('Deposit history'); ?></h4>
            <button type="button" class="btn btn-small btn-autowidth btn-orange" name="button"><?php echo $Lang->tr('More details'); ?></button>
          </header>
          <table class="kr-manager-user-profile-table">
            <?php
              foreach (array_slice($Manager->_fetchPayments($UserFetched), 0, 8) as $key => $infosDeposit) {
                ?>
                <tr>
                  <td>
                    <div>
                      <b><?php echo (strlen($infosDeposit['ref_deposit_history']) > 0 ? $infosDeposit['ref_deposit_history'] : '#'.$infosDeposit['id_deposit_history']); ?></b>
                    </div>
                  </td>
                  <td>
                    <div>
                      <span><?php echo date('d/M/Y H:i:s', $infosDeposit['date_deposit_history']); ?></span>
                    </div>
                  </td>
                  <td>
                    <div>
                      <span><?php echo ucfirst($infosDeposit['payment_type_deposit_history']); ?></span>
                    </div>
                  </td>
                  <td>
                    <div>
                      <span><?php echo $infosDeposit['description_deposit_history']; ?></span>
                    </div>
                  </td>
                  <td>
                    <div>
                      <span><?php echo rtrim($App->_formatNumber($infosDeposit['amount_deposit_history'], 8), "0").' '.$infosDeposit['currency_deposit_history']; ?></span>
                    </div>
                  </td>
                </tr>
                <?php
              }
            ?>
          </table>
        </section>
      </section>
    <?php elseif($PageViewed == "payments"):

      ?>
      <table class="kr-admin-table-view">
        <thead>
          <tr>
            <td><?php echo $Lang->tr('User'); ?></td>
            <td><?php echo $Lang->tr('Ref.'); ?></td>
            <td><?php echo $Lang->tr('Created date'); ?></td>
            <td><?php echo $Lang->tr('Status'); ?></td>
            <td><?php echo $Lang->tr('Amount paid'); ?></td>
            <td><?php echo $Lang->tr('Wallet received'); ?></td>
            <td><?php echo $Lang->tr('Amount received'); ?></td>
            <td><?php echo $Lang->tr('Payment gateway'); ?></td>
            <td><?php echo $Lang->tr('Proof'); ?></td>
            <td></td>
          </tr>
        </thead>
        <tbody>
          <?php
          foreach ($Manager->_fetchPayments($UserFetched) as $key => $infosPayment):
            if($infosPayment['payment_type_deposit_history'] == "Initial") continue;

            ?>
            <tr>
               <td>
                 <div class="kr-admin-coin-nsa">
                   <span><?php echo '#'.$infosPayment['id_user'].' - '.$UserFetched->_getName(); ?></span>
                 </div>
               </td>
               <td>
                 <b><?php echo $infosPayment['ref_deposit_history']; ?></b>
               </td>
               <td>
                 <?php echo date('d/m/Y H:i:s', $infosPayment['date_deposit_history']); ?>
               </td>
               <td>
                 <?php
                  if($infosPayment['payment_status_deposit_history'] == 0) echo '<span class="kr-admin-lst-c-status kr-admin-lst-tag kr-admin-lst-tag-red">'.$Manager->_getPaymentStatus($infosPayment['payment_status_deposit_history']).'</span>';
                  if($App->_getPaymentApproveNeeded()){
                    if($infosPayment['payment_status_deposit_history'] == 1) echo '<span class="kr-admin-lst-c-status kr-admin-lst-tag kr-admin-lst-tag-orange">'.$Manager->_getPaymentStatus($infosPayment['payment_status_deposit_history']).'</span>';
                    if($infosPayment['payment_status_deposit_history'] == 2) echo '<span class="kr-admin-lst-c-status kr-admin-lst-tag kr-admin-lst-tag-green">'.$Manager->_getPaymentStatus($infosPayment['payment_status_deposit_history']).'</span>';
                  } else {
                    if($infosPayment['payment_status_deposit_history'] == 1) echo '<span class="kr-admin-lst-c-status kr-admin-lst-tag kr-admin-lst-tag-green">'.$Manager->_getPaymentStatus($infosPayment['payment_status_deposit_history']).'</span>';
                  }

                  ?>
               </td>
               <td>
                 <?php
                 echo $App->_formatNumber($infosPayment['amount_deposit_history'], 8).' '.$infosPayment['currency_deposit_history'];
                 ?>
               </td>
               <td>
                 <?php echo $infosPayment['wallet_deposit_history']; ?>
               </td>
               <td>
                 <?php
                 $AmountNeedReceive = $infosPayment['amount_deposit_history'] * $infosPayment['wallet_convert_m_deposit_history'];
                 echo $App->_formatNumber($AmountNeedReceive, 8).' '.$infosPayment['wallet_deposit_history'];
                 ?>
               </td>
               <td>
                 <?php
                 echo $infosPayment['payment_type_deposit_history'];
                 ?>
               </td>
               <td>
                 <?php
                 $listProof = $Manager->_getProofPaymentAssociated($infosPayment['id_deposit_history']);
                 if(count($listProof) > 0){
                   echo '<div style="display:flex;">';
                   foreach ($listProof as $keyProof => $valueProof) {
                     if(strlen($valueProof['url_deposit_history_proof']) > 0){
                       echo '<a style="margin-right:5px;" title="'.date('d/m/Y H:i:s', $valueProof['sended_deposit_history_proof']).'" class="btn btn-autowidth btn-small btn-green" target=_bank href="'.APP_URL.'/'.$valueProof['url_deposit_history_proof'].'">'.($keyProof + 1).'</a>';
                     } else {
                       echo '<a style="margin-right:5px;" title="Not received" class="btn btn-autowidth btn-small" target=_bank>'.($keyProof + 1).'</a>';
                     }
                   }
                   echo '</div>';
                 } else {
                   echo '-';
                 }
                 ?>
               </td>
               <td>
                 <?php
                 if($infosPayment['payment_status_deposit_history'] == 0){
                   ?>
                   <button type="button" onclick="_actionPaymentManager('<?php echo App::encrypt_decrypt('encrypt', 'accept_payment-'.$infosPayment['id_deposit_history']); ?>')" name="button" style="margin-bottom:5px;" class="btn btn-small btn-autowidth btn-green"><?php echo $Lang->tr('Validate'); ?></button>
                   <button type="button" onclick="_actionPaymentManager('<?php echo App::encrypt_decrypt('encrypt', 'askproof-'.$infosPayment['id_deposit_history']); ?>', 'askproof')" name="button" style="margin-bottom:5px;" class="btn btn-small btn-autowidth btn-orange"><?php echo $Lang->tr('Ask a proof'); ?></button>
                   <?php
                 }
                 if($infosPayment['payment_status_deposit_history'] != 0){
                   if($App->_getPaymentApproveNeeded() && $infosPayment['payment_status_deposit_history'] == "1"){
                     ?>
                     <button type="button" onclick="_actionPaymentManager('<?php echo App::encrypt_decrypt('encrypt', 'accept_payment-'.$infosPayment['id_deposit_history']); ?>')" name="button" style="margin-bottom:5px;" class="btn btn-small btn-autowidth btn-green"><?php echo $Lang->tr('Validate'); ?></button>
                     <?php
                   }
                   ?>
                   <button type="button" onclick="_actionPaymentManager('<?php echo App::encrypt_decrypt('encrypt', 'cancel_payment-'.$infosPayment['id_deposit_history']); ?>', 'cancel')" name="button" class="btn btn-small btn-autowidth btn-red"><?php echo $Lang->tr('Cancel'); ?></button>
                   <?php
                 }
                 ?>
               </td>
             </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
    </section>
  </section>

</section>
