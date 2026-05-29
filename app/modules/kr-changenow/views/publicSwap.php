<?php

$ChangeNowInitialState = [
  'providerEnabled' => false,
  'missingSettings' => [],
  'enabledFlows' => ['standard'],
  'defaultFlow' => 'standard',
  'defaultFrom' => ['currency' => 'btc', 'network' => 'btc'],
  'defaultTo' => ['currency' => 'eth', 'network' => 'eth'],
  'sourceAssets' => [],
  'destinationAssets' => [],
  'eligibility' => [
    'allowed' => true,
    'state' => 'allowed',
    'message' => '',
    'country' => ''
  ],
  'supportEmail' => ''
];
$ChangeNowUserLogged = (isset($User) && $User->_isLogged());
$ChangeNowSignupAllowed = (!$ChangeNowUserLogged && $App->_allowSignup());

try {
  $ChangeNowClient = ChangeNowApiClient::_fromApp($App);
  $ChangeNowMarketData = new ChangeNowMarketData($ChangeNowClient, null, $App);
  $ChangeNowRepository = new ChangeNowPublicSwapRepository();
  $ChangeNowFlow = new ChangeNowPublicSwapFlow($ChangeNowClient, $ChangeNowMarketData, $ChangeNowRepository, $App, ($ChangeNowUserLogged ? $User : null));
  $ChangeNowInitialState = $ChangeNowFlow->_getInitialState();
} catch (Throwable $e) {
  error_log('[krypto] publicSwap init failure: '.$e->getMessage().' in '.$e->getFile().':'.$e->getLine());
  $ChangeNowInitialState['missingSettings'][] = $e->getMessage();
}

$ChangeNowStatusToken = (isset($_GET['swap_token']) ? trim((string) $_GET['swap_token']) : '');
$ChangeNowRegionEligibility = (array_key_exists('eligibility', $ChangeNowInitialState) && is_array($ChangeNowInitialState['eligibility']) ? $ChangeNowInitialState['eligibility'] : [
  'allowed' => true,
  'state' => 'allowed',
  'message' => '',
  'country' => ''
]);
$ChangeNowRegionAllowed = (!array_key_exists('allowed', $ChangeNowRegionEligibility) || $ChangeNowRegionEligibility['allowed'] !== false);
$ChangeNowRegionDisabled = ($ChangeNowRegionAllowed ? '' : ' disabled');
$ChangeNowRegionMessage = (array_key_exists('message', $ChangeNowRegionEligibility) ? (string) $ChangeNowRegionEligibility['message'] : '');
if(trim($ChangeNowRegionMessage) == '') $ChangeNowRegionMessage = 'ChangeNOW swaps are not available in your region under current provider or local policy.';

if(!function_exists('changenow_public_asset_options')){
  function changenow_public_asset_options($assets, $selectedCurrency, $selectedNetwork){
    foreach ($assets as $asset) {
      $ticker = htmlspecialchars($asset['ticker']);
      $network = htmlspecialchars($asset['network']);
      $name = htmlspecialchars($asset['name']);
      $selected = ($asset['ticker'] == $selectedCurrency && $asset['network'] == $selectedNetwork ? ' selected' : '');
      echo '<option value="'.$ticker.':'.$network.'" data-currency="'.$ticker.'" data-network="'.$network.'"'.$selected.'>'.strtoupper($ticker).' / '.strtoupper($network).' - '.$name.'</option>';
    }
  }
}

if(!function_exists('changenow_public_swap_tr')){
  function changenow_public_swap_tr($text){
    global $Lang;
    $text = (string) $text;
    if(is_object($Lang) && method_exists($Lang, 'tr')){
      try {
        return $Lang->tr($text);
      } catch (Throwable $e) {
        return $text;
      }
    }

    return $text;
  }
}

?>
<section
  class="kr-public-swap-shell"
  data-action-url="<?php echo APP_URL; ?>/app/modules/kr-changenow/src/actions/publicSwap.php"
  data-user-logged="<?php echo ($ChangeNowUserLogged ? '1' : '0'); ?>"
  data-signup-allowed="<?php echo ($ChangeNowSignupAllowed ? '1' : '0'); ?>"
  data-region-supported="<?php echo ($ChangeNowRegionAllowed ? '1' : '0'); ?>"
  data-region-state="<?php echo htmlspecialchars((array_key_exists('state', $ChangeNowRegionEligibility) ? $ChangeNowRegionEligibility['state'] : 'allowed')); ?>"
  data-status-token="<?php echo htmlspecialchars($ChangeNowStatusToken); ?>">
  <header class="kr-public-swap-topbar">
    <div class="kr-public-swap-logo">
      <img src="<?php echo APP_URL.$App->_getLogoBlackPath(); ?>" alt="<?php echo htmlspecialchars($App->_getAppTitle()); ?>">
    </div>
    <nav>
      <?php if($ChangeNowUserLogged): ?>
        <a href="<?php echo APP_URL; ?>/dashboard<?php echo ($App->_rewriteDashBoardName() ? '' : '.php'); ?>">Dashboard</a>
      <?php else: ?>
        <a href="#kr-account-access" class="kr-public-auth-jump">Login</a>
      <?php endif; ?>
    </nav>
  </header>

  <section class="kr-public-swap-card">
    <div class="kr-public-swap-heading">
      <h1>Swap crypto</h1>
      <span>ChangeNOW</span>
    </div>

    <?php if(!$ChangeNowInitialState['providerEnabled'] || count($ChangeNowInitialState['missingSettings']) > 0): ?>
      <section class="kr-public-swap-alert" role="status">
        <?php if(!$ChangeNowInitialState['providerEnabled']): ?>
          ChangeNOW swaps are disabled.
        <?php else: ?>
          ChangeNOW setup is incomplete: <?php echo htmlspecialchars(join(', ', $ChangeNowInitialState['missingSettings'])); ?>.
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <?php if(!$ChangeNowRegionAllowed): ?>
      <section class="kr-public-swap-alert kr-public-swap-alert-error" role="status">
        <?php echo htmlspecialchars(changenow_public_swap_tr($ChangeNowRegionMessage), ENT_QUOTES, 'UTF-8'); ?>
      </section>
    <?php endif; ?>

    <div class="kr-public-swap-message" role="alert" aria-live="polite"></div>

    <form class="kr-public-swap-form">
      <input type="hidden" name="fromCurrency" value="<?php echo htmlspecialchars($ChangeNowInitialState['defaultFrom']['currency']); ?>">
      <input type="hidden" name="fromNetwork" value="<?php echo htmlspecialchars($ChangeNowInitialState['defaultFrom']['network']); ?>">
      <input type="hidden" name="toCurrency" value="<?php echo htmlspecialchars($ChangeNowInitialState['defaultTo']['currency']); ?>">
      <input type="hidden" name="toNetwork" value="<?php echo htmlspecialchars($ChangeNowInitialState['defaultTo']['network']); ?>">
      <input type="hidden" name="rateId" value="">
      <input type="hidden" name="validUntil" value="">

      <div class="kr-public-swap-grid">
        <label>
          <span>From</span>
          <?php if(count($ChangeNowInitialState['sourceAssets']) > 0): ?>
            <select name="fromAsset" class="kr-public-asset-select" data-asset-prefix="from">
              <?php changenow_public_asset_options($ChangeNowInitialState['sourceAssets'], $ChangeNowInitialState['defaultFrom']['currency'], $ChangeNowInitialState['defaultFrom']['network']); ?>
            </select>
          <?php else: ?>
            <div class="kr-public-swap-code-row">
              <input type="text" name="fromCurrencyText" value="<?php echo htmlspecialchars($ChangeNowInitialState['defaultFrom']['currency']); ?>" data-sync-field="fromCurrency">
              <input type="text" name="fromNetworkText" value="<?php echo htmlspecialchars($ChangeNowInitialState['defaultFrom']['network']); ?>" data-sync-field="fromNetwork">
            </div>
          <?php endif; ?>
        </label>

        <label>
          <span>Amount</span>
          <input type="text" name="amount" value="0.01" inputmode="decimal" autocomplete="off">
        </label>

        <label>
          <span>To</span>
          <?php if(count($ChangeNowInitialState['destinationAssets']) > 0): ?>
            <select name="toAsset" class="kr-public-asset-select" data-asset-prefix="to">
              <?php changenow_public_asset_options($ChangeNowInitialState['destinationAssets'], $ChangeNowInitialState['defaultTo']['currency'], $ChangeNowInitialState['defaultTo']['network']); ?>
            </select>
          <?php else: ?>
            <div class="kr-public-swap-code-row">
              <input type="text" name="toCurrencyText" value="<?php echo htmlspecialchars($ChangeNowInitialState['defaultTo']['currency']); ?>" data-sync-field="toCurrency">
              <input type="text" name="toNetworkText" value="<?php echo htmlspecialchars($ChangeNowInitialState['defaultTo']['network']); ?>" data-sync-field="toNetwork">
            </div>
          <?php endif; ?>
        </label>

        <label>
          <span>Flow</span>
          <select name="flow">
            <?php foreach ($ChangeNowInitialState['enabledFlows'] as $flow): ?>
              <option value="<?php echo htmlspecialchars($flow); ?>"<?php echo ($flow == $ChangeNowInitialState['defaultFlow'] ? ' selected' : ''); ?>><?php echo htmlspecialchars($flow); ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>

      <label>
        <span>Destination address</span>
        <input type="text" name="destinationAddress" autocomplete="off">
      </label>

      <div class="kr-public-swap-grid">
        <label>
          <span>Destination memo/tag</span>
          <input type="text" name="destinationExtraId" autocomplete="off">
        </label>
        <label>
          <span>Refund address</span>
          <input type="text" name="refundAddress" autocomplete="off">
        </label>
      </div>

      <label>
        <span>Refund memo/tag</span>
        <input type="text" name="refundExtraId" autocomplete="off">
      </label>

      <footer class="kr-public-swap-actions">
        <button type="button" class="kr-public-validate-address"<?php echo $ChangeNowRegionDisabled; ?>>Validate address</button>
        <button type="button" class="kr-public-get-quote"<?php echo $ChangeNowRegionDisabled; ?>>Get quote</button>
        <button type="submit" class="kr-public-create-swap" disabled>Create swap</button>
      </footer>
    </form>

    <section class="kr-public-quote-panel" aria-live="polite"></section>
    <section class="kr-public-result-panel" aria-live="polite"></section>
  </section>
</section>
