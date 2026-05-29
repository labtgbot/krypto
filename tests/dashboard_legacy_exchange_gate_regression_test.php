<?php

/**
 * OPEN-05 regression: the cabinet must not instantiate legacy exchange or balance classes
 * during render after the ChangeNOW migration.
 */

$root = dirname(__DIR__);
$dashboardSource = @file_get_contents($root . '/dashboard.php');
if ($dashboardSource === false) {
    throw new Exception('Cannot read dashboard.php');
}

$forbiddenNeedles = [
    'new Trade(' => 'dashboard.php must not instantiate legacy exchange classes.',
    'new Balance(' => 'dashboard.php must not instantiate legacy balance classes.',
    'new HiddenThirdParty(' => 'dashboard.php must not instantiate hidden legacy exchange configuration.',
    '_getBalance(true)' => 'dashboard.php must not probe legacy exchange balances.',
    '_getThirdPartyListAvailable(' => 'dashboard.php must not list legacy exchange providers.',
    'kr-wallet-top-thirdparty' => 'dashboard.php must not render the legacy exchange wallet selector.',
    'kr-credit-widthdraw' => 'dashboard.php must not render the legacy withdraw shortcut.',
    'kr-credit-balance' => 'dashboard.php must not render the legacy balance shortcut.',
];

foreach ($forbiddenNeedles as $needle => $message) {
    if (strpos($dashboardSource, $needle) !== false) {
        throw new Exception($message.' Found forbidden text: '.$needle);
    }
}

echo "Dashboard legacy exchange decommission regression checks passed\n";
