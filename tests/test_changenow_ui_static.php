<?php

$root = dirname(__DIR__);

function assert_contains($haystack, $needle, $message) {
  if (strpos($haystack, $needle) === false) {
    fwrite(STDERR, "FAIL: ".$message."\nMissing: ".$needle."\n");
    exit(1);
  }
}

function assert_not_contains($haystack, $needle, $message) {
  if (strpos($haystack, $needle) !== false) {
    fwrite(STDERR, "FAIL: ".$message."\nUnexpected: ".$needle."\n");
    exit(1);
  }
}

$index = file_get_contents($root.'/index.php');
$dashboard = file_get_contents($root.'/dashboard.php');
$swapCss = file_get_contents($root.'/app/modules/kr-changenow/statics/css/swap.css');
$panel = '';
if(file_exists($root.'/app/modules/kr-changenow/views/swap.php')) {
  $panel .= file_get_contents($root.'/app/modules/kr-changenow/views/swap.php');
}
if(file_exists($root.'/app/views/changenow/swap_panel.php')) {
  $panel .= file_get_contents($root.'/app/views/changenow/swap_panel.php');
}
$pannelJs = file_get_contents($root.'/assets/js/pannel.js');

assert_contains($index, 'kr-public-swap-panel', 'public first viewport should render the swap panel');
assert_contains($index, 'app/views/changenow/swap_panel.php', 'public page should reuse the ChangeNOW swap component');
assert_contains($index, 'app/modules/kr-changenow/statics/js/swap.js', 'public page should load the ChangeNOW swap interaction script');
assert_contains($dashboard, 'kr-module="changenow" kr-view="swap"', 'dashboard navigation should expose swap as the primary view');
assert_contains($dashboard, 'kr-modules-hleft="true" kr-module="changenow"', 'dashboard swap view should suppress the legacy watchlist side panel');
assert_contains($dashboard, 'kr-legacy-orderbook-nav', 'legacy order book entry should remain available outside the swap view');
assert_contains($swapCss, 'kr-legacy-orderbook-nav', 'swap styles should hide the order book entry from the default swap path');
assert_contains($pannelJs, "changeView('changenow', 'swap')", 'dashboard should load the swap view by default');
assert_contains($panel, 'kr-changenow-dashboard-surface', 'dashboard swap view should include the ChangeNOW dashboard surface');
assert_contains($panel, 'Recent swap activity', 'dashboard swap view should include transaction history surface');
assert_contains($panel, 'Provider status', 'dashboard swap view should include provider status surface');
assert_not_contains($pannelJs, "changeView('dashboard', 'dashboard');", 'legacy chart board should not be the default dashboard view');

echo "ChangeNOW UI static assertions passed.\n";
