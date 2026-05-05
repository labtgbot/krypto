<?php

/**
 * Regression test for issue #47: "The cabinet won't load".
 *
 * Root cause: When a new user registers and is redirected to dashboard.php,
 * the page renders the welcome overlay HTML first, then tries to seed the
 * user's dashboard with a starting pair (DashboardTopList::_addItem) and
 * watching list items (WatchingList::_addItem) in the HTML-output section of
 * the page — outside the main try/catch.
 *
 * If either _addItem call throws (e.g. SQL error, misconfigured starting_pair,
 * or a PHP TypeError on PHP 8 when starting_pair is null), the exception
 * propagates uncaught. Because the scripts are loaded at the bottom of the
 * page, a mid-render exception truncates the output before any JavaScript
 * loads. The welcome overlay is already in the DOM but completely inert:
 * startWelcome() never runs, the 3-second auto-advance never fires, and the
 * user is permanently frozen on the welcome screen.
 *
 * A secondary bug: when _isNew() is true but the subscription check fails,
 * body.kr-nblr is still added (blurring the whole page) even though no
 * welcome overlay is rendered, leaving the user with an inaccessible,
 * permanently blurred dashboard.
 *
 * This test verifies both fixes are present in dashboard.php.
 */

$root = dirname(__DIR__);
$dashboardSource = @file_get_contents($root . '/dashboard.php');
if ($dashboardSource === false) {
    throw new Exception('Cannot read dashboard.php');
}

// Fix 1: _addItem calls must be wrapped in try/catch(Throwable) so that a
// seeding failure cannot truncate page output before scripts load.
if (!preg_match('/try\s*\{[^}]*_addItem\s*\(/s', $dashboardSource)) {
    throw new Exception(
        'dashboard.php: DashboardTopList::_addItem and WatchingList::_addItem '
        . 'must be wrapped in a try block so seeding failures cannot truncate '
        . 'page output and freeze the welcome screen.'
    );
}

// The catch must use Throwable (not just Exception) to absorb PHP 8 TypeErrors
// that arise when starting_pair is null or malformed.
if (!preg_match('/catch\s*\(\s*\\\\Throwable/', $dashboardSource)) {
    throw new Exception(
        'dashboard.php: the catch block around _addItem calls must catch '
        . '\Throwable (not Exception) to handle PHP 8 TypeErrors from '
        . 'malformed or null starting_pair configuration.'
    );
}

// Fix 2: body.kr-nblr must only be applied when the welcome overlay will
// actually be rendered (i.e. when the subscription check also passes).
// The wrong pattern is: $Dashboard->_isNew() alone controlling kr-nblr.
// The correct pattern includes the subscription gate inside the nblr check.
if (preg_match(
    '/class=.*kr-nblr.*if\s*\(\s*\$Dashboard->_isNew\(\)\s*\|\|/s',
    $dashboardSource
)) {
    throw new Exception(
        'dashboard.php: body.kr-nblr is applied when $Dashboard->_isNew() is '
        . 'true without checking the subscription gate. When a new user does '
        . 'not pass the subscription check, no welcome overlay is rendered, '
        . 'but the dashboard is blurred with no way to dismiss it. The kr-nblr '
        . 'condition must match the same subscription condition as the welcome '
        . 'overlay.'
    );
}

// Verify the body class line now includes the subscription check inside the nblr condition.
if (!preg_match(
    '/class=.*kr-nblr.*\$Dashboard->_isNew\(\)\s*&&\s*\(\s*\$Charge/s',
    $dashboardSource
)) {
    throw new Exception(
        'dashboard.php: body.kr-nblr condition must include the subscription '
        . 'gate ($Charge->_activeAbo() etc.) alongside $Dashboard->_isNew(), '
        . 'matching the welcome overlay condition on the following lines.'
    );
}

echo "Cabinet welcome freeze regression checks passed\n";
