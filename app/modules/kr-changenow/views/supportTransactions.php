<?php

$changeNowActionUrl = APP_URL.'/app/modules/kr-changenow/src/actions/supportAction.php';

function changenow_support_escape($value){
  return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function changenow_support_date($value){
  if(is_numeric($value)) return date('d/m/Y H:i:s', $value);
  return (string) $value;
}

?>
<div class="kr-admin-table">
  <table>
    <thead>
      <tr>
        <td><?php echo $Lang->tr('Transaction'); ?></td>
        <td><?php echo $Lang->tr('User'); ?></td>
        <td><?php echo $Lang->tr('Pair'); ?></td>
        <td><?php echo $Lang->tr('Amount'); ?></td>
        <td><?php echo $Lang->tr('Status'); ?></td>
        <td><?php echo $Lang->tr('Actions'); ?></td>
        <td><?php echo $Lang->tr('Support notes'); ?></td>
        <td><?php echo $Lang->tr('Audit'); ?></td>
      </tr>
    </thead>
    <tbody>
      <?php if(count($ChangeNowTransactions) == 0): ?>
      <tr>
        <td colspan="8"><?php echo $Lang->tr('No ChangeNOW transactions found.'); ?></td>
      </tr>
      <?php endif; ?>

      <?php foreach ($ChangeNowTransactions as $ChangeNowTransaction):
        $changeNowEvents = [];
        try {
          $changeNowEvents = $ChangeNowRepository->_listEventsForProvider($ChangeNowTransaction['providerId'], 5);
        } catch (Exception $e) {
          $changeNowEvents = [];
        }
        ?>
      <tr>
        <td>
          <b><?php echo changenow_support_escape($ChangeNowTransaction['providerId']); ?></b><br>
          <span><?php echo changenow_support_escape(changenow_support_date($ChangeNowTransaction['createdAt'])); ?></span><br>
          <span><?php echo $Lang->tr('Updated'); ?>: <?php echo changenow_support_escape(changenow_support_date($ChangeNowTransaction['updatedAt'])); ?></span>
        </td>
        <td>
          <?php echo ($ChangeNowTransaction['userId'] == '' ? $Lang->tr('Anonymous') : '#'.changenow_support_escape($ChangeNowTransaction['userId'])); ?>
        </td>
        <td>
          <?php echo changenow_support_escape(strtoupper($ChangeNowTransaction['fromCurrency']).' / '.strtoupper($ChangeNowTransaction['fromNetwork'])); ?><br>
          <?php echo changenow_support_escape(strtoupper($ChangeNowTransaction['toCurrency']).' / '.strtoupper($ChangeNowTransaction['toNetwork'])); ?>
        </td>
        <td>
          <span><?php echo changenow_support_escape($ChangeNowTransaction['fromAmount']); ?></span><br>
          <b><?php echo changenow_support_escape($ChangeNowTransaction['toAmount']); ?></b>
        </td>
        <td>
          <span class="kr-admin-lst-tag kr-admin-lst-tag-blue"><?php echo changenow_support_escape($ChangeNowTransaction['status']); ?></span><br>
          <?php if($ChangeNowTransaction['refundAvailable']): ?>
            <span class="kr-admin-lst-tag kr-admin-lst-tag-orange"><?php echo $Lang->tr('Refund available'); ?></span><br>
          <?php endif; ?>
          <?php if($ChangeNowTransaction['continueAvailable']): ?>
            <span class="kr-admin-lst-tag kr-admin-lst-tag-orange"><?php echo $Lang->tr('Continue available'); ?></span>
          <?php endif; ?>
        </td>
        <td>
          <form class="kr-admin kr-adm-post-evs" action="<?php echo changenow_support_escape($changeNowActionUrl); ?>" method="post" style="margin-bottom:6px;">
            <input type="hidden" name="provider_id" value="<?php echo changenow_support_escape($ChangeNowTransaction['providerId']); ?>">
            <input type="hidden" name="action" value="refresh">
            <input type="submit" class="btn btn-small btn-autowidth" value="<?php echo $Lang->tr('Refresh'); ?>">
          </form>
          <?php if($ChangeNowTransaction['continueAvailable']): ?>
          <form class="kr-admin kr-adm-post-evs kr-adm-post-evs-confirm" action="<?php echo changenow_support_escape($changeNowActionUrl); ?>" method="post" style="margin-bottom:6px;">
            <input type="hidden" name="provider_id" value="<?php echo changenow_support_escape($ChangeNowTransaction['providerId']); ?>">
            <input type="hidden" name="action" value="continue">
            <input type="submit" class="btn btn-small btn-autowidth btn-green" value="<?php echo $Lang->tr('Continue'); ?>">
          </form>
          <?php endif; ?>
          <?php if($ChangeNowTransaction['refundAvailable']): ?>
          <form class="kr-admin kr-adm-post-evs kr-adm-post-evs-confirm" action="<?php echo changenow_support_escape($changeNowActionUrl); ?>" method="post">
            <input type="hidden" name="provider_id" value="<?php echo changenow_support_escape($ChangeNowTransaction['providerId']); ?>">
            <input type="hidden" name="action" value="refund">
            <input type="text" name="refund_address" value="<?php echo changenow_support_escape($ChangeNowTransaction['refundAddress']); ?>" placeholder="<?php echo $Lang->tr('Refund address'); ?>" style="max-width:180px;margin-bottom:4px;">
            <input type="text" name="refund_extra_id" value="<?php echo changenow_support_escape($ChangeNowTransaction['refundExtraId']); ?>" placeholder="<?php echo $Lang->tr('Memo / tag'); ?>" style="max-width:180px;margin-bottom:4px;">
            <input type="submit" class="btn btn-small btn-autowidth btn-red" value="<?php echo $Lang->tr('Refund'); ?>">
          </form>
          <?php endif; ?>
        </td>
        <td>
          <form class="kr-admin kr-adm-post-evs" action="<?php echo changenow_support_escape($changeNowActionUrl); ?>" method="post">
            <input type="hidden" name="provider_id" value="<?php echo changenow_support_escape($ChangeNowTransaction['providerId']); ?>">
            <input type="hidden" name="action" value="note">
            <textarea name="support_note" style="min-width:180px;min-height:70px;"><?php echo changenow_support_escape($ChangeNowTransaction['supportNote']); ?></textarea>
            <input type="submit" class="btn btn-small btn-autowidth" value="<?php echo $Lang->tr('Save'); ?>">
          </form>
        </td>
        <td>
          <?php foreach ($changeNowEvents as $changeNowEvent): ?>
          <div style="margin-bottom:6px;">
            <b><?php echo changenow_support_escape($changeNowEvent['eventType']); ?></b>
            <span><?php echo changenow_support_escape($changeNowEvent['eventStatus']); ?></span><br>
            <span><?php echo changenow_support_escape($changeNowEvent['actorType']); ?><?php echo ($changeNowEvent['actorUserId'] == '' ? '' : ' #'.changenow_support_escape($changeNowEvent['actorUserId'])); ?></span><br>
            <span><?php echo changenow_support_escape(changenow_support_date($changeNowEvent['createdAt'])); ?></span>
          </div>
          <?php endforeach; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
