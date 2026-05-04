<?php

/**
 * Account referral dashboard for ChangeNOW swaps.
 *
 * @package Krypto
 */

session_start();

require "../../../../config/config.settings.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/vendor/autoload.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/MySQL/MySQL.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/App/App.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/App/AppModule.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/User/User.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/Lang/Lang.php";

$App = new App(true);
$App->_loadModulesControllers();

$User = new User();
if(!$User->_isLogged()) die('User not logged');

$Lang = new Lang($User->_getLang(), $App);

$UserLogged = $User;
$adminView = false;
if(($User->_isAdmin() || $User->_isManager()) && isset($_SESSION['kr_account_view_user']) && !empty($_SESSION['kr_account_view_user']) && $_SESSION['kr_account_view_user'] != $User->_getUserID()){
  $User = new User($_SESSION['kr_account_view_user']);
  $adminView = true;
}

if(($User->_isAdmin() || $User->_isManager()) && !$UserLogged->_isAdmin()){
  $User = $UserLogged;
  $adminView = false;
}

if(!function_exists('changenow_referrals_escape')){
  function changenow_referrals_escape($value){
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
  }
}

$ReferralCode = '';
$ReferralUrl = '';
$ReferralTransactions = [];
$ReferralSummary = [
  'total' => 0,
  'anonymous' => 0,
  'loggedIn' => 0,
  'commissionStates' => [
    'pending_provider_confirmation' => 0,
    'pending_admin_review' => 0,
    'not_eligible' => 0
  ]
];

if($App->_referalEnabled() && class_exists('ChangeNowPublicSwapRepository')){
  $ReferralCode = $User->_getReferalUrl();
  $ReferralUrl = rtrim(APP_URL, '/').'/?ref='.rawurlencode($ReferralCode);

  $ChangeNowRepository = new ChangeNowPublicSwapRepository();
  $ReferralTransactions = $ChangeNowRepository->_listByReferralCode($ReferralCode, 100);
  $ReferralSummary = $ChangeNowRepository->_referralSummaryByCode($ReferralCode, 500);
}

?>

<section class="kr-user-referrals">
  <?php if(!$App->_referalEnabled()): ?>
    <div class="kr-user-f-l">
      <div>
        <label><?php echo $Lang->tr('Referrals'); ?></label>
        <span><?php echo $Lang->tr('Referral rewards are disabled.'); ?></span>
      </div>
    </div>
  <?php else: ?>
    <div class="kr-user-f-l">
      <div>
        <label><?php echo $Lang->tr('Public referral link'); ?></label>
        <input type="text" readonly value="<?php echo changenow_referrals_escape($ReferralUrl); ?>">
      </div>
      <div>
        <label><?php echo $Lang->tr('Terms'); ?></label>
        <span><?php echo $Lang->tr('Expected commission stays pending until ChangeNOW provider and admin confirmation.'); ?></span>
      </div>
    </div>

    <div class="kr-user-f-l">
      <div>
        <label><?php echo $Lang->tr('Attributed swaps'); ?></label>
        <input type="text" readonly value="<?php echo changenow_referrals_escape($ReferralSummary['total']); ?>">
      </div>
      <div>
        <label><?php echo $Lang->tr('Anonymous / logged-in'); ?></label>
        <input type="text" readonly value="<?php echo changenow_referrals_escape($ReferralSummary['anonymous'].' / '.$ReferralSummary['loggedIn']); ?>">
      </div>
    </div>

    <div class="kr-user-f-l">
      <div>
        <label><?php echo $Lang->tr('Pending provider confirmation'); ?></label>
        <input type="text" readonly value="<?php echo changenow_referrals_escape($ReferralSummary['commissionStates']['pending_provider_confirmation']); ?>">
      </div>
      <div>
        <label><?php echo $Lang->tr('Pending admin review'); ?></label>
        <input type="text" readonly value="<?php echo changenow_referrals_escape($ReferralSummary['commissionStates']['pending_admin_review']); ?>">
      </div>
    </div>

    <div class="kr-admin-table">
      <table>
        <thead>
          <tr>
            <td><?php echo $Lang->tr('Transaction'); ?></td>
            <td><?php echo $Lang->tr('Swapper'); ?></td>
            <td><?php echo $Lang->tr('Pair'); ?></td>
            <td><?php echo $Lang->tr('Amount'); ?></td>
            <td><?php echo $Lang->tr('Status'); ?></td>
            <td><?php echo $Lang->tr('Commission state'); ?></td>
          </tr>
        </thead>
        <tbody>
          <?php if(count($ReferralTransactions) == 0): ?>
            <tr>
              <td colspan="6"><?php echo $Lang->tr('No referred ChangeNOW swaps found.'); ?></td>
            </tr>
          <?php endif; ?>

          <?php foreach ($ReferralTransactions as $ReferralTransaction): ?>
            <tr>
              <td><?php echo changenow_referrals_escape($ReferralTransaction['providerId']); ?></td>
              <td><?php echo ($ReferralTransaction['userId'] == '' ? $Lang->tr('Anonymous') : '#'.changenow_referrals_escape($ReferralTransaction['userId'])); ?></td>
              <td>
                <?php echo changenow_referrals_escape(strtoupper($ReferralTransaction['fromCurrency']).' / '.strtoupper($ReferralTransaction['fromNetwork'])); ?><br>
                <?php echo changenow_referrals_escape(strtoupper($ReferralTransaction['toCurrency']).' / '.strtoupper($ReferralTransaction['toNetwork'])); ?>
              </td>
              <td>
                <?php echo changenow_referrals_escape($ReferralTransaction['fromAmount']); ?><br>
                <?php echo changenow_referrals_escape($ReferralTransaction['toAmount']); ?>
              </td>
              <td><?php echo changenow_referrals_escape($ReferralTransaction['status']); ?></td>
              <td><?php echo changenow_referrals_escape(ChangeNowPublicSwapRepository::_referralCommissionStateLabel($ReferralTransaction['referralCommissionState'])); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
