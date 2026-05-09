<?php

/**
 * Regression test for issue #49: the cabinet must not hang on installation
 * because the dashboard header tries to initialize legacy exchange providers.
 *
 * After the ChangeNOW migration, legacy exchange connections are disabled by
 * default through legacy_exchange_connections_enabled. Most feature entry
 * points already respect that flag, but the dashboard header balance dropdown
 * used to instantiate Trade and fetch a third-party balance during page render
 * whenever hidden third-party trading was disabled.
 *
 * If a user still has legacy credentials, or a provider library throws while
 * probing balances, this happens before the footer scripts are emitted. The
 * login page then looks like it is stuck while redirecting to the cabinet.
 */

$root = dirname(__DIR__);
$dashboardSource = @file_get_contents($root . '/dashboard.php');
if ($dashboardSource === false) {
    throw new Exception('Cannot read dashboard.php');
}

$blockStart = strpos($dashboardSource, '$listThirdParty = null;');
$blockEnd = strpos($dashboardSource, '<?php if($App->_hiddenThirdpartyActive()', $blockStart);

if ($blockStart === false || $blockEnd === false || $blockEnd <= $blockStart) {
    throw new Exception(
        'dashboard.php: cannot locate the legacy exchange wallet header block.'
    );
}

$walletBlock = substr($dashboardSource, $blockStart, $blockEnd - $blockStart);

$legacyFlagPosition = strpos($walletBlock, '_legacyExchangeConnectionsEnabled()');
$tradePosition = strpos($walletBlock, 'new Trade($User, $App)');

if ($tradePosition === false) {
    throw new Exception(
        'dashboard.php: the test could not locate the Trade initialization in '
        . 'the legacy exchange wallet header block.'
    );
}

if ($legacyFlagPosition === false || $legacyFlagPosition > $tradePosition) {
    throw new Exception(
        'dashboard.php: the legacy exchange wallet header must check '
        . '$App->_legacyExchangeConnectionsEnabled() before instantiating '
        . 'Trade or probing third-party balances.'
    );
}

$thirdPartyListPosition = strpos($walletBlock, '_getThirdPartyListAvailable()');
$balanceFetchPosition = strpos($walletBlock, '_getBalance(true)');
$tryPosition = strpos($walletBlock, 'try');
$catchThrowablePosition = strpos($walletBlock, 'catch (\\Throwable');

if ($thirdPartyListPosition === false || $balanceFetchPosition === false) {
    throw new Exception(
        'dashboard.php: the test could not locate the third-party list or '
        . 'balance fetch in the legacy wallet header block.'
    );
}

if (
    $tryPosition === false
    || $tryPosition > $thirdPartyListPosition
    || $catchThrowablePosition === false
    || $catchThrowablePosition < $balanceFetchPosition
) {
    throw new Exception(
        'dashboard.php: legacy exchange provider discovery and balance fetch '
        . 'must be wrapped in catch (\\Throwable) so provider failures cannot '
        . 'truncate the cabinet page before scripts load.'
    );
}

if (strpos($walletBlock, 'error_log(') === false) {
    throw new Exception(
        'dashboard.php: swallowed legacy exchange header failures must be '
        . 'logged with error_log for post-install diagnostics.'
    );
}

echo "Dashboard legacy exchange gate regression checks passed\n";
