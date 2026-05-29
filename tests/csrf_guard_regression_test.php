<?php

/**
 * Regression coverage for issue #56 audit findings around CSRF protection.
 *
 * Every first-party action endpoint must either invoke the shared CSRF guard or
 * be documented as a non-browser callback/API/cron exception with its own
 * provider-specific validation. Public views that mutate state via GET must
 * validate a CSRF query token on those mutation branches.
 */

$root = dirname(__DIR__);

function assert_csrf_guard($condition, $message) {
    if (!$condition) {
        throw new Exception($message);
    }
}

function assert_csrf_contains($source, $needle, $message) {
    assert_csrf_guard(strpos($source, $needle) !== false, $message.' Missing: '.$needle);
}

$policyPath = $root.'/app/src/App/csrf_policy.php';
assert_csrf_guard(file_exists($policyPath), 'CSRF policy allowlist must exist.');

$policy = require $policyPath;
assert_csrf_guard(is_array($policy), 'CSRF policy must return an array.');
assert_csrf_guard(isset($policy['allowlist']) && is_array($policy['allowlist']), 'CSRF policy must expose an allowlist array.');

$helperPath = $root.'/app/src/App/Csrf.php';
assert_csrf_guard(file_exists($helperPath), 'Shared CSRF helper must exist.');
$helperSource = file_get_contents($helperPath);
assert_csrf_contains($helperSource, 'class Krypto_Csrf', 'Shared CSRF helper must define Krypto_Csrf.');
assert_csrf_contains($helperSource, 'random_bytes', 'CSRF tokens must be generated with random_bytes.');
assert_csrf_contains($helperSource, 'hash_equals', 'CSRF token comparison must use hash_equals.');
assert_csrf_contains($helperSource, 'HTTP_X_CSRF_TOKEN', 'CSRF helper must accept AJAX header tokens.');
assert_csrf_contains($helperSource, 'krypto_csrf_token', 'CSRF helper must use a stable field name.');

$csrfJsPath = $root.'/assets/js/csrf.js';
assert_csrf_guard(file_exists($csrfJsPath), 'Shared CSRF browser helper must exist.');
$csrfJs = file_get_contents($csrfJsPath);
assert_csrf_contains($csrfJs, '$.ajaxPrefilter', 'Browser helper must attach tokens to jQuery AJAX requests.');
assert_csrf_contains($csrfJs, 'X-CSRF-Token', 'Browser helper must send the CSRF AJAX header.');
assert_csrf_contains($csrfJs, 'krypto_csrf_token', 'Browser helper must inject the CSRF form field.');

foreach (['index.php', 'dashboard.php'] as $entrypoint) {
    $source = file_get_contents($root.'/'.$entrypoint);
    assert_csrf_contains($source, 'Krypto_Csrf::metaTag()', $entrypoint.' must expose the CSRF token to browser flows.');
    assert_csrf_contains($source, 'assets/js/csrf.js', $entrypoint.' must load the shared CSRF browser helper.');
}

$actionFiles = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root.'/app', FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if (!$file->isFile() || substr($file->getFilename(), -4) !== '.php') {
        continue;
    }

    $relative = str_replace($root.'/', '', $file->getPathname());
    if (strpos($relative, '/actions/') === false) {
        continue;
    }

    $source = file_get_contents($file->getPathname());
    if (trim($source) === '') {
        continue;
    }

    $actionFiles[$relative] = $source;
}

ksort($actionFiles);

foreach ($actionFiles as $relative => $source) {
    $hasGuard = strpos($source, 'Krypto_Csrf::validateRequest(') !== false;
    $isAllowed = array_key_exists($relative, $policy['allowlist']);

    assert_csrf_guard(
        $hasGuard || $isAllowed,
        $relative.' must either call Krypto_Csrf::validateRequest() or be documented in the CSRF allowlist.'
    );
}

foreach ($policy['allowlist'] as $relative => $entry) {
    assert_csrf_guard(isset($actionFiles[$relative]), 'CSRF allowlist references a missing action endpoint: '.$relative);
    assert_csrf_guard(is_array($entry), 'CSRF allowlist entry must be structured for '.$relative);
    assert_csrf_guard(!empty($entry['reason']), 'CSRF allowlist entry must explain the exception reason for '.$relative);
    assert_csrf_guard(!empty($entry['validation']), 'CSRF allowlist entry must document alternate validation for '.$relative);
}

$bankTransferView = file_get_contents($root.'/app/modules/kr-payment/views/banktransfert.php');
assert_csrf_contains(
    $bankTransferView,
    "Krypto_Csrf::validateRequest(['methods' => ['GET']])",
    'banktransfert.php must validate CSRF before GET mutation branches.'
);
assert_csrf_contains(
    $bankTransferView,
    'Krypto_Csrf::queryParameter()',
    'banktransfert.php must include CSRF query tokens on GET mutation links.'
);
assert_csrf_contains(
    $bankTransferView,
    "formData.append('krypto_csrf_token'",
    'banktransfert.php Dropzone upload must append the CSRF field.'
);

$proofSendingView = file_get_contents($root.'/app/modules/kr-payment/views/proofSending.php');
assert_csrf_contains(
    $proofSendingView,
    'Krypto_Csrf::metaTag()',
    'proofSending.php must expose the CSRF token for standalone proof uploads.'
);
assert_csrf_contains(
    $proofSendingView,
    'assets/js/csrf.js',
    'proofSending.php must load the shared CSRF browser helper.'
);

$proofSendingJs = file_get_contents($root.'/app/modules/kr-payment/statics/js/proofsending.js');
assert_csrf_contains(
    $proofSendingJs,
    "formData.append('krypto_csrf_token'",
    'proofsending.js Dropzone upload must append the CSRF field.'
);

$identityJs = file_get_contents($root.'/app/modules/kr-identity/statics/js/script.js');
assert_csrf_contains(
    $identityJs,
    "formData.append('krypto_csrf_token'",
    'identity document Dropzone upload must append the CSRF field.'
);

$bankTransferContractView = file_get_contents($root.'/app/modules/kr-payment/views/banktransfert_contract.php');
assert_csrf_contains(
    $bankTransferContractView,
    'Krypto_Csrf::queryParameter()',
    'banktransfert_contract.php must include CSRF token on the create-transfer GET mutation link.'
);

$syncRightBarAction = file_get_contents($root.'/app/modules/kr-chat/src/actions/syncRightBar.php');
assert_csrf_contains(
    $syncRightBarAction,
    "\$_SERVER['REQUEST_METHOD'] !== 'POST'",
    'syncRightBar.php must reject GET status mutations.'
);
assert_csrf_contains(
    $syncRightBarAction,
    "\$_POST['chat_user_status']",
    'syncRightBar.php must read status updates from POST.'
);

$chatBarJs = file_get_contents($root.'/app/modules/kr-chat/statics/js/bar.js');
assert_csrf_contains(
    $chatBarJs,
    '$.post',
    'Chat right-bar sync must send status mutations with POST.'
);

$askProofAction = file_get_contents($root.'/app/modules/kr-manager/src/actions/askProof.php');
assert_csrf_contains(
    $askProofAction,
    "\$_SERVER['REQUEST_METHOD'] !== 'POST'",
    'askProof.php must reject GET proof-request mutations.'
);
assert_csrf_contains(
    $askProofAction,
    "\$_POST['id_deposit_history']",
    'askProof.php must read proof-request ids from POST.'
);
foreach (array_keys($policy['allowlist']) as $allowlistPath) {
    assert_csrf_guard(
        strpos($allowlistPath, 'app/modules/kr-trade/') !== 0,
        'Retired kr-trade endpoints must not remain in the CSRF allowlist: '.$allowlistPath
    );
}

echo "CSRF guard regression checks passed.\n";

?>
