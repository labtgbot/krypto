<?php

$root = dirname(__DIR__);

function assertContainsSource($needle, $haystack, $message) {
    if (strpos($haystack, $needle) === false) {
        throw new Exception($message.' Missing: '.$needle);
    }
}

function assertNotContainsSource($needle, $haystack, $message) {
    if (strpos($haystack, $needle) !== false) {
        throw new Exception($message.' Unexpected: '.$needle);
    }
}

$indexSource = file_get_contents($root.'/index.php');
$publicSwapViewSource = file_get_contents($root.'/app/modules/kr-changenow/views/publicSwap.php');
$publicSwapActionSource = file_get_contents($root.'/app/modules/kr-changenow/src/actions/publicSwap.php');
$publicSwapScriptSource = file_get_contents($root.'/app/modules/kr-changenow/statics/js/public-swap.js');
$publicSwapStyleSource = file_get_contents($root.'/app/modules/kr-changenow/statics/css/public-swap.css');

assertContainsSource('require \'app/modules/kr-changenow/views/publicSwap.php\';', $indexSource, 'Fresh visitors must land on the public swap experience');
assertContainsSource('Keep the public swap page reachable for anonymous and signed-in visitors.', $indexSource, 'Index should document the public visitor flow');
assertNotContainsSource('if($User->_isLogged()) header', $indexSource, 'Logged-in visitors must not be redirected away from the public swap page before they can see it');
assertContainsSource('kr-public-account-access-visible', $indexSource, 'Account access should be opt-in from account-specific entry links');

assertNotContainsSource('if (!$User->_isLogged())', $publicSwapActionSource, 'Public ChangeNOW AJAX actions must not require login');
assertNotContainsSource('if(!$User->_isLogged())', $publicSwapActionSource, 'Public ChangeNOW AJAX actions must not require login');
foreach (['quote', 'validate', 'destinations', 'create', 'status'] as $publicAction) {
    assertContainsSource("if(\$action == '".$publicAction."')", $publicSwapActionSource, 'Public swap action should expose '.$publicAction);
}

assertContainsSource('data-user-logged', $publicSwapViewSource, 'Public swap view should expose authentication state to the browser');
assertContainsSource('data-signup-allowed', $publicSwapViewSource, 'Public swap view should expose signup availability to the browser');
assertContainsSource('$App->_allowSignup()', $publicSwapViewSource, 'Public swap signup CTA should respect the signup feature flag');

assertContainsSource('var userLogged = $shell.attr(\'data-user-logged\') === \'1\';', $publicSwapScriptSource, 'Browser behavior should read the rendered authentication state');
assertContainsSource('var signupAllowed = $shell.attr(\'data-signup-allowed\') === \'1\';', $publicSwapScriptSource, 'Browser behavior should read the rendered signup policy');
assertContainsSource("$('body').addClass('kr-public-account-access-visible');", $publicSwapScriptSource, 'Account access should become visible only when the visitor asks for login or post-swap signup');
assertContainsSource("if(signupAllowed && !userLogged && $('#kr-account-access').length > 0)", $publicSwapScriptSource, 'Create account prompt should only appear after swap creation for anonymous visitors who can sign up');
assertContainsSource('body.kr-login.kr-public-swap-enabled.kr-public-account-access-visible', $publicSwapStyleSource, 'Account access panel should be hidden from the open-first landing state');
assertContainsSource('body.kr-public-swap-enabled.kr-public-account-access-visible > form', $publicSwapStyleSource, 'Account access panel should be revealed by an explicit account action');

$protectedFiles = [
    'dashboard gate' => $root.'/dashboard.php',
    'account settings view' => $root.'/app/modules/kr-user/views/account.php',
    'profile settings view' => $root.'/app/modules/kr-user/views/profile.php',
    'security settings view' => $root.'/app/modules/kr-user/views/security.php',
    'identity wizard view' => $root.'/app/modules/kr-identity/views/identityWizard.php',
    'identity upload action' => $root.'/app/modules/kr-identity/src/actions/submitAsset.php',
];

foreach ($protectedFiles as $label => $file) {
    $source = file_get_contents($file);
    if (strpos($source, '_isLogged()') === false) {
        throw new Exception('Protected '.$label.' should still require a logged-in user');
    }
}

$signupViewSource = file_get_contents($root.'/app/views/login/signup.php');
$signupActionSource = file_get_contents($root.'/app/modules/kr-user/src/actions/signup.php');
assertContainsSource('if(!$App->_allowSignup())', $signupViewSource, 'Signup view should remain controlled by the signup setting');
assertContainsSource('if(!$App->_allowSignup())', $signupActionSource, 'Signup action should remain controlled by the signup setting');

$managerActionSource = file_get_contents($root.'/app/modules/kr-identity/src/actions/changeIdentityStatus.php');
assertContainsSource('_isLogged()', $managerActionSource, 'Identity management status changes should require login');
assertContainsSource('_isAdmin()', $managerActionSource, 'Identity management status changes should require admin authorization');
assertContainsSource('_isManager()', $managerActionSource, 'Identity management status changes should require manager authorization');

echo "ChangeNOW optional registration boundary check passed\n";

?>
