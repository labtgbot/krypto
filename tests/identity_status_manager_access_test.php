<?php

// Regression test for #150 (SEC-35): managers must be able to change identity
// status without also being admins.

$root = dirname(__DIR__);
$actionPath = $root.'/app/modules/kr-identity/src/actions/changeIdentityStatus.php';
$source = file_get_contents($actionPath);

function assert_identity_status_access($condition, $message) {
    if (!$condition) {
        throw new Exception($message);
    }
}

assert_identity_status_access($source !== false && trim($source) !== '', 'Cannot read changeIdentityStatus.php');

assert_identity_status_access(
    strpos($source, '$User->_isLogged()') !== false,
    'Identity status changes must still require an authenticated user.'
);

assert_identity_status_access(
    preg_match('/if\s*\(\s*!\s*\$User->_isAdmin\s*\(\s*\)\s*&&\s*!\s*\$User->_isManager\s*\(\s*\)\s*\)/', $source) === 1,
    'Identity status changes must allow either admin or manager roles.'
);

assert_identity_status_access(
    preg_match('/if\s*\(\s*!\s*\$User->_isAdmin\s*\(\s*\)\s*\)\s*\{\s*throw new Exception\("Permission denied"/', $source) !== 1,
    'Identity status changes must not reject real managers with a standalone admin-only guard.'
);

assert_identity_status_access(
    preg_match('/if\s*\(\s*!\s*\$User->_isManager\s*\(\s*\)\s*\)\s*\{\s*throw new Exception\("Permission denied"/', $source) !== 1,
    'Identity status changes must not use a second standalone manager guard after admin-only rejection.'
);

echo "Identity status manager access regression check passed.\n";

?>
