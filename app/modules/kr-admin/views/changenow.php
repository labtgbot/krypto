<?php

/**
 * ChangeNOW admin panel.
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

function changenow_admin_escape($value){
  return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function changenow_admin_checked($value){
  return ($value == '1' ? 'checked' : '');
}

function changenow_admin_flow_checked($flows, $flow){
  return (in_array($flow, $flows, true) ? 'checked' : '');
}

function changenow_admin_selected($value, $expected){
  return ($value == $expected ? 'selected="selected"' : '');
}

function changenow_admin_status_class($status){
  $status = strtolower(trim((string) $status));
  if(in_array($status, ['failed', 'refunded', 'expired', 'overdue', 'rejected'], true)) return 'kr-admin-lst-tag-red';
  if(in_array($status, ['waiting', 'confirming', 'sending', 'pending'], true)) return 'kr-admin-lst-tag-orange';
  if($status == '' || $status == 'unknown') return 'kr-admin-lst-tag-grey';
  return '';
}

function changenow_admin_date_value($value){
  return changenow_admin_escape(ChangeNowAdminPanel::_formatTimestamp($value));
}

$changeNowSettings = $App->_getChangeNowSettings();
$changeNowSummary = ChangeNowAdminPanel::_statusSummary($changeNowSettings);
$changeNowFlows = $changeNowSummary['enabledFlows'];
$changeNowFilters = ChangeNowAdminPanel::_normalizeTransactionFilters($_POST);
$changeNowTransactions = [];
$changeNowTransactionsAvailable = false;
$changeNowTransactionError = '';
$changeNowActionUrl = APP_URL.'/app/modules/kr-admin/src/actions/changeNowSupportAction.php';

try {
  $ChangeNowRepository = new ChangeNowAdminRepository();
  $changeNowTransactionsAvailable = $ChangeNowRepository->_transactionsAvailable();
  $changeNowTransactions = $ChangeNowRepository->_listTransactions($changeNowFilters, 100);
} catch (Exception $e) {
  $changeNowTransactionError = $e->getMessage();
}

?>
<section class="kr-admin">
  <nav class="kr-admin-nav">
    <ul>
      <?php foreach ($Admin->_getListSection() as $key => $section) {
        echo '<li type="module" kr-module="admin" kr-view="'.strtolower(str_replace(' ', '', $section)).'" '.($section == 'ChangeNOW' ? 'class="kr-admin-nav-selected"' : '').'>'.$Lang->tr($section).'</li>';
      } ?>
    </ul>
  </nav>

  <div class="kr-changenow-status-grid">
    <div>
      <label><?php echo $Lang->tr('Swap availability'); ?></label>
      <span class="kr-admin-lst-tag <?php echo changenow_admin_escape($changeNowSummary['tagClass']); ?>"><?php echo changenow_admin_escape($changeNowSummary['label']); ?></span>
      <p><?php echo changenow_admin_escape($changeNowSummary['detail']); ?></p>
    </div>
    <div>
      <label><?php echo $Lang->tr('Provider configuration'); ?></label>
      <p>
        <?php echo $Lang->tr('Public key'); ?>:
        <span class="kr-admin-lst-tag <?php echo ($changeNowSummary['publicKeyPresent'] ? '' : 'kr-admin-lst-tag-red'); ?>"><?php echo ($changeNowSummary['publicKeyPresent'] ? $Lang->tr('Present') : $Lang->tr('Missing')); ?></span>
      </p>
      <p>
        <?php echo $Lang->tr('Private key'); ?>:
        <span class="kr-admin-lst-tag <?php echo ($changeNowSummary['privateKeyPresent'] ? '' : 'kr-admin-lst-tag-grey'); ?>"><?php echo ($changeNowSummary['privateKeyPresent'] ? $Lang->tr('Present') : $Lang->tr('Not set')); ?></span>
      </p>
      <p>
        <?php echo $Lang->tr('Callback secret'); ?>:
        <span class="kr-admin-lst-tag <?php echo ($changeNowSummary['callbackSecretPresent'] ? '' : 'kr-admin-lst-tag-grey'); ?>"><?php echo ($changeNowSummary['callbackSecretPresent'] ? $Lang->tr('Present') : $Lang->tr('Not set')); ?></span>
      </p>
    </div>
    <div>
      <label><?php echo $Lang->tr('Provider health'); ?></label>
      <p><?php echo $Lang->tr('Health'); ?>: <?php echo changenow_admin_escape($changeNowSummary['providerHealth']); ?></p>
      <p><?php echo $Lang->tr('Rate state'); ?>: <?php echo changenow_admin_escape($changeNowSummary['rateLimitWarningState']); ?></p>
      <p><?php echo $Lang->tr('Last sync'); ?>: <?php echo changenow_admin_escape($changeNowSummary['lastSuccessfulSync']); ?></p>
    </div>
    <div>
      <label><?php echo $Lang->tr('Widget and flows'); ?></label>
      <p><?php echo $Lang->tr('Flows'); ?>: <?php echo changenow_admin_escape(implode(', ', $changeNowFlows)); ?></p>
      <p><?php echo $Lang->tr('Widget'); ?>: <?php echo ($changeNowSettings['changenow_widget_enabled'] == '1' ? $Lang->tr('Enabled') : $Lang->tr('Disabled')); ?></p>
      <p><?php echo $Lang->tr('Default'); ?>: <?php echo changenow_admin_escape(strtoupper($changeNowSettings['changenow_default_from_asset']).' / '.strtoupper($changeNowSettings['changenow_default_to_asset'])); ?></p>
    </div>
  </div>

  <form class="kr-adm-post-evs" action="<?php echo APP_URL; ?>/app/modules/kr-admin/src/actions/saveChangeNow.php" method="post">
    <div class="kr-admin-line kr-admin-line-cls">
      <div class="kr-admin-field">
        <div>
          <label><?php echo $Lang->tr('Enable ChangeNOW swaps'); ?></label><br/>
          <span><?php echo $Lang->tr('Local switch used by widget and swap policy checks.'); ?></span>
        </div>
        <div>
          <div class="ckbx-style-14">
            <input type="checkbox" id="kr-adm-chk-enablechangenow" <?php echo changenow_admin_checked($changeNowSettings['changenow_provider_enabled']); ?> name="kr-adm-chk-enablechangenow">
            <label for="kr-adm-chk-enablechangenow"></label>
          </div>
        </div>
      </div>
      <div class="kr-admin-field">
        <div>
          <label><?php echo $Lang->tr('Public API key'); ?></label>
        </div>
        <div>
          <input type="text" name="kr-adm-changenowpublicapikey" value="<?php echo changenow_admin_escape(ChangeNowAdminPanel::_maskSecret($changeNowSettings['changenow_public_api_key'])); ?>" autocomplete="off">
        </div>
      </div>
      <div class="kr-admin-field">
        <div>
          <label><?php echo $Lang->tr('Private API key'); ?></label>
        </div>
        <div>
          <input type="text" name="kr-adm-changenowprivateapikey" value="<?php echo changenow_admin_escape(ChangeNowAdminPanel::_maskSecret($changeNowSettings['changenow_private_api_key'])); ?>" autocomplete="off">
        </div>
      </div>
      <div class="kr-admin-field">
        <div>
          <label><?php echo $Lang->tr('Callback secret'); ?></label>
        </div>
        <div>
          <input type="text" name="kr-adm-changenowcallbacksecret" value="<?php echo changenow_admin_escape(ChangeNowAdminPanel::_maskSecret($changeNowSettings['changenow_callback_secret'])); ?>" autocomplete="off">
        </div>
      </div>
      <div class="kr-admin-field">
        <div>
          <label><?php echo $Lang->tr('Provider health status'); ?></label>
        </div>
        <div>
          <select name="kr-adm-changenowproviderhealth">
            <?php foreach (['unknown', 'healthy', 'degraded', 'outage'] as $healthStatus): ?>
              <option value="<?php echo $healthStatus; ?>" <?php echo changenow_admin_selected($changeNowSettings['changenow_provider_health_status'], $healthStatus); ?>><?php echo changenow_admin_escape(ucfirst($healthStatus)); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="kr-admin-field">
        <div>
          <label><?php echo $Lang->tr('Health message'); ?></label>
        </div>
        <div>
          <input type="text" name="kr-adm-changenowproviderhealthmessage" value="<?php echo changenow_admin_escape($changeNowSettings['changenow_provider_health_message']); ?>">
        </div>
      </div>
      <div class="kr-admin-field">
        <div>
          <label><?php echo $Lang->tr('Local disabled message'); ?></label>
        </div>
        <div>
          <input type="text" name="kr-adm-changenowlocaldisabledreason" value="<?php echo changenow_admin_escape($changeNowSettings['changenow_local_disabled_reason']); ?>">
        </div>
      </div>
    </div>

    <div class="kr-admin-line kr-admin-line-cls">
      <div class="kr-admin-field">
        <div>
          <label><?php echo $Lang->tr('Enabled flows'); ?></label>
        </div>
        <div class="kr-changenow-checkbox-row">
          <div class="ckbx-style-14">
            <input type="checkbox" id="kr-adm-chk-changenowflowstandard" <?php echo changenow_admin_flow_checked($changeNowFlows, 'standard'); ?> name="kr-adm-chk-changenowflowstandard">
            <label for="kr-adm-chk-changenowflowstandard"></label>
          </div>
          <span><?php echo $Lang->tr('Standard'); ?></span>
          <div class="ckbx-style-14">
            <input type="checkbox" id="kr-adm-chk-changenowflowfixedrate" <?php echo changenow_admin_flow_checked($changeNowFlows, 'fixed-rate'); ?> name="kr-adm-chk-changenowflowfixedrate">
            <label for="kr-adm-chk-changenowflowfixedrate"></label>
          </div>
          <span><?php echo $Lang->tr('Fixed rate'); ?></span>
        </div>
      </div>
      <div class="kr-admin-field">
        <div>
          <label><?php echo $Lang->tr('Default flow'); ?></label>
        </div>
        <div>
          <select name="kr-adm-changenowdefaultflow">
            <option value="standard" <?php echo changenow_admin_selected($changeNowSettings['changenow_default_flow'], 'standard'); ?>><?php echo $Lang->tr('Standard'); ?></option>
            <option value="fixed-rate" <?php echo changenow_admin_selected($changeNowSettings['changenow_default_flow'], 'fixed-rate'); ?>><?php echo $Lang->tr('Fixed rate'); ?></option>
          </select>
        </div>
      </div>
      <div class="kr-admin-field">
        <div>
          <label><?php echo $Lang->tr('Default from asset'); ?></label>
        </div>
        <div class="kr-changenow-inline-fields">
          <input type="text" name="kr-adm-changenowdefaultfromasset" value="<?php echo changenow_admin_escape($changeNowSettings['changenow_default_from_asset']); ?>" placeholder="btc">
          <input type="text" name="kr-adm-changenowdefaultfromnetwork" value="<?php echo changenow_admin_escape($changeNowSettings['changenow_default_from_network']); ?>" placeholder="btc">
        </div>
      </div>
      <div class="kr-admin-field">
        <div>
          <label><?php echo $Lang->tr('Default to asset'); ?></label>
        </div>
        <div class="kr-changenow-inline-fields">
          <input type="text" name="kr-adm-changenowdefaulttoasset" value="<?php echo changenow_admin_escape($changeNowSettings['changenow_default_to_asset']); ?>" placeholder="eth">
          <input type="text" name="kr-adm-changenowdefaulttonetwork" value="<?php echo changenow_admin_escape($changeNowSettings['changenow_default_to_network']); ?>" placeholder="eth">
        </div>
      </div>
      <div class="kr-admin-field">
        <div>
          <label><?php echo $Lang->tr('Enabled assets'); ?></label><br/>
          <span><?php echo $Lang->tr('Leave empty to allow provider-supported assets.'); ?></span>
        </div>
        <div>
          <textarea name="kr-adm-changenowenabledassets"><?php echo changenow_admin_escape($changeNowSettings['changenow_enabled_assets']); ?></textarea>
        </div>
      </div>
      <div class="kr-admin-field">
        <div>
          <label><?php echo $Lang->tr('Enabled networks'); ?></label>
        </div>
        <div>
          <textarea name="kr-adm-changenowenablednetworks"><?php echo changenow_admin_escape($changeNowSettings['changenow_enabled_networks']); ?></textarea>
        </div>
      </div>
      <div class="kr-admin-field">
        <div>
          <label><?php echo $Lang->tr('Disabled pairs'); ?></label><br/>
          <span><?php echo $Lang->tr('Use asset/network pairs, one per line.'); ?></span>
        </div>
        <div>
          <textarea name="kr-adm-changenowdisabledpairs"><?php echo changenow_admin_escape($changeNowSettings['changenow_disabled_pairs']); ?></textarea>
        </div>
      </div>
    </div>

    <div class="kr-admin-line kr-admin-line-cls">
      <div class="kr-admin-field">
        <div>
          <label><?php echo $Lang->tr('Widget enabled'); ?></label>
        </div>
        <div>
          <div class="ckbx-style-14">
            <input type="checkbox" id="kr-adm-chk-changenowwidgetenabled" <?php echo changenow_admin_checked($changeNowSettings['changenow_widget_enabled']); ?> name="kr-adm-chk-changenowwidgetenabled">
            <label for="kr-adm-chk-changenowwidgetenabled"></label>
          </div>
        </div>
      </div>
      <div class="kr-admin-field">
        <div>
          <label><?php echo $Lang->tr('Widget link id'); ?></label>
        </div>
        <div>
          <input type="text" name="kr-adm-changenowwidgetlinkid" value="<?php echo changenow_admin_escape($changeNowSettings['changenow_widget_link_id']); ?>">
        </div>
      </div>
      <div class="kr-admin-field">
        <div>
          <label><?php echo $Lang->tr('Referral link id'); ?></label>
        </div>
        <div>
          <input type="text" name="kr-adm-changenowreferrallinkid" value="<?php echo changenow_admin_escape($changeNowSettings['changenow_referral_link_id']); ?>">
        </div>
      </div>
      <div class="kr-admin-field">
        <div>
          <label><?php echo $Lang->tr('Widget amount'); ?></label>
        </div>
        <div>
          <input type="text" name="kr-adm-changenowwidgetamount" value="<?php echo changenow_admin_escape($changeNowSettings['changenow_widget_amount']); ?>">
        </div>
      </div>
      <div class="kr-admin-field">
        <div>
          <label><?php echo $Lang->tr('Widget fiat input'); ?></label>
        </div>
        <div>
          <div class="ckbx-style-14">
            <input type="checkbox" id="kr-adm-chk-changenowwidgetfiat" <?php echo changenow_admin_checked($changeNowSettings['changenow_widget_fiat_enabled']); ?> name="kr-adm-chk-changenowwidgetfiat">
            <label for="kr-adm-chk-changenowwidgetfiat"></label>
          </div>
        </div>
      </div>
      <div class="kr-admin-field">
        <div>
          <label><?php echo $Lang->tr('Widget colors'); ?></label>
        </div>
        <div class="kr-changenow-inline-fields">
          <input type="text" name="kr-adm-changenowwidgetprimarycolor" value="<?php echo changenow_admin_escape($changeNowSettings['changenow_widget_primary_color']); ?>" placeholder="00C26F">
          <input type="text" name="kr-adm-changenowwidgetbackgroundcolor" value="<?php echo changenow_admin_escape($changeNowSettings['changenow_widget_background_color']); ?>" placeholder="FFFFFF">
        </div>
      </div>
      <div class="kr-admin-field">
        <div>
          <label><?php echo $Lang->tr('Widget language'); ?></label>
        </div>
        <div>
          <input type="text" name="kr-adm-changenowwidgetlanguage" value="<?php echo changenow_admin_escape($changeNowSettings['changenow_widget_language']); ?>" placeholder="en-US">
        </div>
      </div>
    </div>

    <div class="kr-admin-line kr-admin-line-cls">
      <div class="kr-admin-field">
        <div>
          <label><?php echo $Lang->tr('Support email'); ?></label>
        </div>
        <div>
          <input type="text" name="kr-adm-changenowsupportemail" value="<?php echo changenow_admin_escape($changeNowSettings['changenow_support_email']); ?>">
        </div>
      </div>
      <div class="kr-admin-field">
        <div>
          <label><?php echo $Lang->tr('Support copy'); ?></label>
        </div>
        <div>
          <textarea name="kr-adm-changenowsupportcopy"><?php echo changenow_admin_escape($changeNowSettings['changenow_support_copy']); ?></textarea>
        </div>
      </div>
      <div class="kr-admin-field">
        <div>
          <label><?php echo $Lang->tr('Refund copy'); ?></label>
        </div>
        <div>
          <textarea name="kr-adm-changenowrefundcopy"><?php echo changenow_admin_escape($changeNowSettings['changenow_refund_copy']); ?></textarea>
        </div>
      </div>
      <div class="kr-admin-field">
        <div>
          <label><?php echo $Lang->tr('Continue copy'); ?></label>
        </div>
        <div>
          <textarea name="kr-adm-changenowcontinuecopy"><?php echo changenow_admin_escape($changeNowSettings['changenow_continue_copy']); ?></textarea>
        </div>
      </div>
      <div class="kr-admin-field">
        <div>
          <label><?php echo $Lang->tr('Rate limits'); ?></label>
        </div>
        <div class="kr-changenow-inline-fields">
          <input type="text" name="kr-adm-changenowratelimitsecond" value="<?php echo changenow_admin_escape($changeNowSettings['changenow_rate_limit_per_second']); ?>">
          <input type="text" name="kr-adm-changenowratelimitminute" value="<?php echo changenow_admin_escape($changeNowSettings['changenow_rate_limit_per_minute']); ?>">
        </div>
      </div>
      <div class="kr-admin-field">
        <div>
          <label><?php echo $Lang->tr('Rate warning state'); ?></label>
        </div>
        <div>
          <select name="kr-adm-changenowratelimitwarning">
            <?php foreach (['normal', 'warning', 'limited'] as $rateState): ?>
              <option value="<?php echo $rateState; ?>" <?php echo changenow_admin_selected($changeNowSettings['changenow_rate_limit_warning_state'], $rateState); ?>><?php echo changenow_admin_escape(ucfirst($rateState)); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="kr-admin-field">
        <div>
          <label><?php echo $Lang->tr('Quote cache TTL'); ?></label>
        </div>
        <div>
          <input type="text" name="kr-adm-changenowquotecachettl" value="<?php echo changenow_admin_escape($changeNowSettings['changenow_quote_cache_ttl']); ?>">
        </div>
      </div>
      <div class="kr-admin-field">
        <div>
          <label><?php echo $Lang->tr('Anonymous retention days'); ?></label>
        </div>
        <div>
          <input type="text" name="kr-adm-changenowretentionanonymousdays" value="<?php echo changenow_admin_escape($changeNowSettings['changenow_retention_anonymous_days']); ?>">
        </div>
      </div>
      <div class="kr-admin-field">
        <div>
          <label><?php echo $Lang->tr('Completed retention days'); ?></label>
        </div>
        <div>
          <input type="text" name="kr-adm-changenowretentioncompleteddays" value="<?php echo changenow_admin_escape($changeNowSettings['changenow_retention_completed_days']); ?>">
        </div>
      </div>
      <div class="kr-admin-field">
        <div>
          <label><?php echo $Lang->tr('Debug logging'); ?></label>
        </div>
        <div>
          <div class="ckbx-style-14">
            <input type="checkbox" id="kr-adm-chk-changenowdebuglogging" <?php echo changenow_admin_checked($changeNowSettings['changenow_debug_logging_enabled']); ?> name="kr-adm-chk-changenowdebuglogging">
            <label for="kr-adm-chk-changenowdebuglogging"></label>
          </div>
        </div>
      </div>
      <div class="kr-admin-field">
        <div></div>
        <div class="kr-admin-field-ws">
          <input type="submit" class="btn btn-autowidth" value="<?php echo $Lang->tr('Save'); ?>">
        </div>
      </div>
    </div>
  </form>

  <form class="kr-changenow-search-f">
    <div class="kr-admin-line kr-admin-line-cls">
      <div class="kr-admin-field">
        <div>
          <label><?php echo $Lang->tr('Transaction support'); ?></label><br/>
          <span><?php echo $Lang->tr('Search by safe identifiers, status, user email, asset, or referral code.'); ?></span>
        </div>
        <div class="kr-changenow-search-grid">
          <input type="text" name="search" value="<?php echo changenow_admin_escape($changeNowFilters['search']); ?>" placeholder="<?php echo $Lang->tr('Search'); ?>">
          <input type="text" name="provider_id" value="<?php echo changenow_admin_escape($changeNowFilters['provider_id']); ?>" placeholder="<?php echo $Lang->tr('Provider id'); ?>">
          <input type="text" name="internal_id" value="<?php echo changenow_admin_escape($changeNowFilters['internal_id']); ?>" placeholder="<?php echo $Lang->tr('Internal id'); ?>">
          <input type="text" name="user_email" value="<?php echo changenow_admin_escape($changeNowFilters['user_email']); ?>" placeholder="<?php echo $Lang->tr('User email'); ?>">
          <input type="text" name="anonymous_token" value="<?php echo changenow_admin_escape($changeNowFilters['anonymous_token']); ?>" placeholder="<?php echo $Lang->tr('Lookup token fragment'); ?>">
          <input type="text" name="status" value="<?php echo changenow_admin_escape($changeNowFilters['status']); ?>" placeholder="<?php echo $Lang->tr('Status'); ?>">
          <input type="text" name="date_from" value="<?php echo changenow_admin_escape($changeNowFilters['date_from']); ?>" placeholder="YYYY-MM-DD">
          <input type="text" name="date_to" value="<?php echo changenow_admin_escape($changeNowFilters['date_to']); ?>" placeholder="YYYY-MM-DD">
          <input type="text" name="asset" value="<?php echo changenow_admin_escape($changeNowFilters['asset']); ?>" placeholder="<?php echo $Lang->tr('Asset'); ?>">
          <input type="text" name="referral_code" value="<?php echo changenow_admin_escape($changeNowFilters['referral_code']); ?>" placeholder="<?php echo $Lang->tr('Referral'); ?>">
        </div>
      </div>
      <div class="kr-admin-field">
        <div></div>
        <div class="kr-admin-field-ws kr-changenow-search-actions">
          <input type="submit" class="btn btn-autowidth" value="<?php echo $Lang->tr('Search'); ?>">
          <button type="button" class="btn btn-autowidth kr-changenow-clear-search"><?php echo $Lang->tr('Clear'); ?></button>
        </div>
      </div>
    </div>
  </form>

  <?php if($changeNowTransactionError != ''): ?>
    <div class="kr-changenow-empty-state"><?php echo changenow_admin_escape($changeNowTransactionError); ?></div>
  <?php elseif(!$changeNowTransactionsAvailable): ?>
    <div class="kr-changenow-empty-state"><?php echo $Lang->tr('ChangeNOW transaction tables are not installed yet.'); ?></div>
  <?php endif; ?>

  <div class="kr-admin-table kr-changenow-transaction-table">
    <table>
      <thead>
        <tr>
          <td><?php echo $Lang->tr('Transaction'); ?></td>
          <td><?php echo $Lang->tr('User'); ?></td>
          <td><?php echo $Lang->tr('Pair'); ?></td>
          <td><?php echo $Lang->tr('Amounts'); ?></td>
          <td><?php echo $Lang->tr('Status'); ?></td>
          <td><?php echo $Lang->tr('Referral'); ?></td>
          <td><?php echo $Lang->tr('Support actions'); ?></td>
          <td><?php echo $Lang->tr('Support notes'); ?></td>
        </tr>
      </thead>
      <tbody>
        <?php if(count($changeNowTransactions) == 0): ?>
          <tr>
            <td colspan="8"><?php echo $Lang->tr('No ChangeNOW transactions found.'); ?></td>
          </tr>
        <?php endif; ?>

        <?php foreach ($changeNowTransactions as $changeNowTransaction): ?>
          <tr>
            <td>
              <b><?php echo changenow_admin_escape($changeNowTransaction['providerId']); ?></b><br/>
              <span>#<?php echo changenow_admin_escape($changeNowTransaction['id']); ?></span><br/>
              <span><?php echo $Lang->tr('Updated'); ?>: <?php echo changenow_admin_date_value($changeNowTransaction['updatedAt']); ?></span>
            </td>
            <td>
              <?php if($changeNowTransaction['userEmail'] != ''): ?>
                <?php echo changenow_admin_escape($changeNowTransaction['userEmail']); ?><br/>
              <?php endif; ?>
              <?php echo ($changeNowTransaction['userId'] == '' ? $Lang->tr('Anonymous') : '#'.changenow_admin_escape($changeNowTransaction['userId'])); ?><br/>
              <?php if($changeNowTransaction['lookupTokenFragment'] != ''): ?>
                <span><?php echo $Lang->tr('Lookup'); ?>: <?php echo changenow_admin_escape($changeNowTransaction['lookupTokenFragment']); ?></span>
              <?php endif; ?>
            </td>
            <td>
              <span><?php echo changenow_admin_escape(strtoupper($changeNowTransaction['fromCurrency']).' / '.strtoupper($changeNowTransaction['fromNetwork'])); ?></span><br/>
              <b><?php echo changenow_admin_escape(strtoupper($changeNowTransaction['toCurrency']).' / '.strtoupper($changeNowTransaction['toNetwork'])); ?></b><br/>
              <span><?php echo changenow_admin_escape($changeNowTransaction['flow']); ?></span>
            </td>
            <td>
              <span><?php echo changenow_admin_escape($changeNowTransaction['fromAmount']); ?></span><br/>
              <b><?php echo changenow_admin_escape($changeNowTransaction['toAmount']); ?></b>
            </td>
            <td>
              <span class="kr-admin-lst-tag <?php echo changenow_admin_escape(changenow_admin_status_class($changeNowTransaction['status'])); ?>"><?php echo changenow_admin_escape($changeNowTransaction['status']); ?></span><br/>
              <?php if($changeNowTransaction['refundAvailable']): ?>
                <span class="kr-admin-lst-tag kr-admin-lst-tag-orange"><?php echo $Lang->tr('Refund available'); ?></span><br/>
              <?php endif; ?>
              <?php if($changeNowTransaction['continueAvailable']): ?>
                <span class="kr-admin-lst-tag kr-admin-lst-tag-orange"><?php echo $Lang->tr('Continue available'); ?></span><br/>
              <?php endif; ?>
              <?php if($changeNowTransaction['payoutAddressFingerprint'] != ''): ?>
                <span><?php echo $Lang->tr('Address fingerprint'); ?>: <?php echo changenow_admin_escape($changeNowTransaction['payoutAddressFingerprint']); ?></span>
              <?php endif; ?>
            </td>
            <td>
              <?php echo ($changeNowTransaction['referralCode'] == '' ? $Lang->tr('None') : changenow_admin_escape($changeNowTransaction['referralCode'])); ?>
            </td>
            <td class="kr-changenow-actions">
              <?php if($changeNowTransaction['providerId'] != ''): ?>
                <form class="kr-adm-post-evs" action="<?php echo changenow_admin_escape($changeNowActionUrl); ?>" method="post">
                  <input type="hidden" name="provider_id" value="<?php echo changenow_admin_escape($changeNowTransaction['providerId']); ?>">
                  <input type="hidden" name="action" value="refresh">
                  <input type="submit" class="btn btn-small btn-autowidth" value="<?php echo $Lang->tr('Refresh'); ?>">
                </form>
                <?php if($changeNowTransaction['continueAvailable']): ?>
                  <form class="kr-adm-post-evs kr-adm-post-evs-confirm" action="<?php echo changenow_admin_escape($changeNowActionUrl); ?>" method="post">
                    <input type="hidden" name="provider_id" value="<?php echo changenow_admin_escape($changeNowTransaction['providerId']); ?>">
                    <input type="hidden" name="action" value="continue">
                    <input type="submit" class="btn btn-small btn-autowidth btn-green" value="<?php echo $Lang->tr('Continue'); ?>">
                  </form>
                <?php endif; ?>
                <?php if($changeNowTransaction['refundAvailable']): ?>
                  <form class="kr-adm-post-evs kr-adm-post-evs-confirm" action="<?php echo changenow_admin_escape($changeNowActionUrl); ?>" method="post">
                    <input type="hidden" name="provider_id" value="<?php echo changenow_admin_escape($changeNowTransaction['providerId']); ?>">
                    <input type="hidden" name="action" value="refund">
                    <input type="text" name="refund_address" value="" placeholder="<?php echo $Lang->tr('Refund address'); ?>">
                    <input type="text" name="refund_extra_id" value="" placeholder="<?php echo $Lang->tr('Memo / tag'); ?>">
                    <input type="submit" class="btn btn-small btn-autowidth btn-red" value="<?php echo $Lang->tr('Refund'); ?>">
                  </form>
                <?php endif; ?>
              <?php endif; ?>
            </td>
            <td>
              <?php if($changeNowTransaction['providerId'] != ''): ?>
                <form class="kr-adm-post-evs" action="<?php echo changenow_admin_escape($changeNowActionUrl); ?>" method="post">
                  <input type="hidden" name="provider_id" value="<?php echo changenow_admin_escape($changeNowTransaction['providerId']); ?>">
                  <input type="hidden" name="action" value="note">
                  <textarea name="support_note"><?php echo changenow_admin_escape($changeNowTransaction['supportNote']); ?></textarea>
                  <input type="submit" class="btn btn-small btn-autowidth" value="<?php echo $Lang->tr('Save'); ?>">
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
