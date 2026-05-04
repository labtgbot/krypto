<?php

if(!isset($changeNowSwapContext)) $changeNowSwapContext = 'public';

if(!function_exists('kr_changenow_label')){
  function kr_changenow_label($text){
    global $Lang;
    $label = (isset($Lang) && is_object($Lang) && method_exists($Lang, 'tr')) ? $Lang->tr($text) : $text;
    return htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
  }
}

$changeNowSwapIsDashboard = $changeNowSwapContext === 'dashboard';
$changeNowIconBase = (defined('APP_URL') ? APP_URL : '').'/assets/img/icons/crypto/';
$changeNowAssets = [
  ['symbol' => 'BTC', 'name' => 'Bitcoin', 'network' => 'Bitcoin'],
  ['symbol' => 'ETH', 'name' => 'Ethereum', 'network' => 'ERC20'],
  ['symbol' => 'USDT', 'name' => 'Tether', 'network' => 'TRC20'],
  ['symbol' => 'XMR', 'name' => 'Monero', 'network' => 'Monero']
];

?>
<section class="kr-changenow-swap kr-changenow-swap-<?php echo htmlspecialchars($changeNowSwapContext, ENT_QUOTES, 'UTF-8'); ?>" data-kr-changenow-swap>
  <header class="kr-changenow-swap-header">
    <div>
      <span><?php echo kr_changenow_label('ChangeNOW swaps'); ?></span>
      <?php if($changeNowSwapIsDashboard): ?>
        <h2><?php echo kr_changenow_label('Swap assets through ChangeNOW'); ?></h2>
      <?php else: ?>
        <h1><?php echo kr_changenow_label('Swap crypto without signing in'); ?></h1>
      <?php endif; ?>
      <p><?php echo kr_changenow_label('Choose a pair, preview the quote, send funds to the generated deposit address, and track the transaction from the same screen.'); ?></p>
    </div>
    <ul class="kr-changenow-asset-strip" aria-label="<?php echo kr_changenow_label('Popular swap assets'); ?>">
      <?php foreach ($changeNowAssets as $changeNowAsset): ?>
        <li>
          <img src="<?php echo $changeNowIconBase.$changeNowAsset['symbol']; ?>.svg" alt="">
          <div>
            <span><?php echo $changeNowAsset['symbol']; ?></span>
            <label><?php echo $changeNowAsset['network']; ?></label>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  </header>

  <div class="kr-changenow-swap-grid">
    <section class="kr-changenow-quote-card" aria-label="<?php echo kr_changenow_label('Create swap quote'); ?>">
      <nav class="kr-changenow-rate-mode" aria-label="<?php echo kr_changenow_label('Rate mode'); ?>">
        <button type="button" class="kr-changenow-rate-active" data-kr-rate-mode="standard"><?php echo kr_changenow_label('Standard'); ?></button>
        <button type="button" data-kr-rate-mode="fixed"><?php echo kr_changenow_label('Fixed'); ?></button>
      </nav>

      <div class="kr-changenow-field-group">
        <label for="kr-changenow-from-amount-<?php echo $changeNowSwapContext; ?>"><?php echo kr_changenow_label('You send'); ?></label>
        <div class="kr-changenow-amount-row">
          <input id="kr-changenow-from-amount-<?php echo $changeNowSwapContext; ?>" type="text" inputmode="decimal" value="0.25" data-kr-swap-amount>
          <select aria-label="<?php echo kr_changenow_label('Source asset'); ?>" data-kr-swap-from>
            <option value="BTC" selected>BTC</option>
            <option value="ETH">ETH</option>
            <option value="USDT">USDT</option>
            <option value="XMR">XMR</option>
          </select>
        </div>
        <span data-kr-swap-from-network><?php echo kr_changenow_label('Bitcoin network'); ?></span>
      </div>

      <button type="button" class="kr-changenow-switch" aria-label="<?php echo kr_changenow_label('Switch assets'); ?>" data-kr-swap-switch>
        <span aria-hidden="true">&#8645;</span>
      </button>

      <div class="kr-changenow-field-group">
        <label for="kr-changenow-to-amount-<?php echo $changeNowSwapContext; ?>"><?php echo kr_changenow_label('You receive'); ?></label>
        <div class="kr-changenow-amount-row">
          <input id="kr-changenow-to-amount-<?php echo $changeNowSwapContext; ?>" type="text" value="4.075" readonly data-kr-swap-receive>
          <select aria-label="<?php echo kr_changenow_label('Destination asset'); ?>" data-kr-swap-to>
            <option value="BTC">BTC</option>
            <option value="ETH" selected>ETH</option>
            <option value="USDT">USDT</option>
            <option value="XMR">XMR</option>
          </select>
        </div>
        <span data-kr-swap-to-network><?php echo kr_changenow_label('Ethereum network'); ?></span>
      </div>

      <div class="kr-changenow-field-group">
        <label for="kr-changenow-address-<?php echo $changeNowSwapContext; ?>"><?php echo kr_changenow_label('Destination address'); ?></label>
        <input id="kr-changenow-address-<?php echo $changeNowSwapContext; ?>" type="text" placeholder="<?php echo kr_changenow_label('Paste receiving wallet address'); ?>" data-kr-swap-address>
      </div>

      <div class="kr-changenow-field-group kr-changenow-refund-field">
        <label for="kr-changenow-refund-<?php echo $changeNowSwapContext; ?>"><?php echo kr_changenow_label('Refund address'); ?></label>
        <input id="kr-changenow-refund-<?php echo $changeNowSwapContext; ?>" type="text" placeholder="<?php echo kr_changenow_label('Optional'); ?>" data-kr-swap-refund>
      </div>

      <section class="kr-changenow-quote-result" aria-live="polite">
        <div>
          <span><?php echo kr_changenow_label('Estimated receive'); ?></span>
          <strong><i data-kr-swap-summary-amount>4.075</i> <i data-kr-swap-summary-symbol>ETH</i></strong>
        </div>
        <div>
          <span><?php echo kr_changenow_label('Network fee'); ?></span>
          <strong data-kr-swap-fee>Included in quote</strong>
        </div>
        <div>
          <span><?php echo kr_changenow_label('Quote state'); ?></span>
          <strong data-kr-swap-state>Ready for preview</strong>
        </div>
      </section>

      <button type="button" class="kr-changenow-primary-action" data-kr-swap-action><?php echo kr_changenow_label('Get quote'); ?></button>

      <section class="kr-changenow-transaction-panel" data-kr-swap-transaction>
        <header>
          <span><?php echo kr_changenow_label('Transaction created'); ?></span>
          <strong data-kr-swap-transaction-id>CN-LOCAL-2481</strong>
        </header>
        <div>
          <label><?php echo kr_changenow_label('Pay-in address'); ?></label>
          <code data-kr-swap-payin>bc1qchangenowpreviewdeposit000000000</code>
        </div>
        <div>
          <label><?php echo kr_changenow_label('Status'); ?></label>
          <span data-kr-swap-transaction-status><?php echo kr_changenow_label('Waiting for deposit'); ?></span>
        </div>
      </section>
    </section>

    <aside class="kr-changenow-side-panel">
      <section class="kr-changenow-status-card">
        <header>
          <span><?php echo kr_changenow_label('Track transaction'); ?></span>
        </header>
        <div class="kr-changenow-status-search">
          <input type="text" placeholder="<?php echo kr_changenow_label('Transaction ID'); ?>" data-kr-swap-track-input>
          <button type="button" data-kr-swap-track><?php echo kr_changenow_label('Track'); ?></button>
        </div>
        <p data-kr-swap-track-result><?php echo kr_changenow_label('Enter an ID to view the latest provider status.'); ?></p>
      </section>

      <section class="kr-changenow-status-card">
        <header>
          <span><?php echo kr_changenow_label('Provider status'); ?></span>
        </header>
        <ul class="kr-changenow-provider-list">
          <li>
            <span></span>
            <div>
              <strong><?php echo kr_changenow_label('Quotes available'); ?></strong>
              <label><?php echo kr_changenow_label('Live quotes appear after provider configuration'); ?></label>
            </div>
          </li>
          <li class="kr-changenow-state-muted">
            <span></span>
            <div>
              <strong><?php echo kr_changenow_label('Validation error'); ?></strong>
              <label><?php echo kr_changenow_label('Address or pair needs correction'); ?></label>
            </div>
          </li>
          <li class="kr-changenow-state-error">
            <span></span>
            <div>
              <strong><?php echo kr_changenow_label('Provider down'); ?></strong>
              <label><?php echo kr_changenow_label('Retry quote or contact support'); ?></label>
            </div>
          </li>
        </ul>
      </section>

      <section class="kr-changenow-status-card kr-changenow-history-card">
        <header>
          <span><?php echo kr_changenow_label('Recent swap activity'); ?></span>
          <?php if($changeNowSwapIsDashboard): ?>
            <a><?php echo kr_changenow_label('Account history'); ?></a>
          <?php endif; ?>
        </header>
        <ul>
          <li>
            <strong>BTC <?php echo kr_changenow_label('to'); ?> ETH</strong>
            <span><?php echo kr_changenow_label('Awaiting first ChangeNOW transaction'); ?></span>
          </li>
          <li>
            <strong>USDT <?php echo kr_changenow_label('to'); ?> XMR</strong>
            <span><?php echo kr_changenow_label('Saved here after account signup'); ?></span>
          </li>
        </ul>
      </section>

      <?php if(!$changeNowSwapIsDashboard): ?>
        <section class="kr-changenow-status-card kr-changenow-account-card">
          <header>
            <span><?php echo kr_changenow_label('Account optional'); ?></span>
          </header>
          <p><?php echo kr_changenow_label('Sign in after a swap only when you want saved history, alerts, referrals, or advanced settings.'); ?></p>
        </section>
      <?php endif; ?>
    </aside>
  </div>
</section>
