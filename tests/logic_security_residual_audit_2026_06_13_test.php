<?php

// Регрессионный тест целостности остаточного аудита логики и безопасности (#137).
// Гарантирует, что отчет 2026-06-13 и трекер сохраняют связь с #137, PR #138
// и заведенными задачами SEC-24..SEC-35 (#139..#150).

$root = dirname(__DIR__);
$failures = [];

function residual_audit_fail($message) {
    global $failures;
    $failures[] = $message;
}

function residual_audit_read_required($path, $label) {
    if (!is_file($path)) {
        residual_audit_fail($label.' should exist at '.$path);
        return '';
    }

    $contents = file_get_contents($path);
    if ($contents === false || trim($contents) === '') {
        residual_audit_fail($label.' should not be empty.');
        return '';
    }

    return (string)$contents;
}

function residual_audit_assert_contains($haystack, $needle, $message) {
    if (strpos($haystack, $needle) === false) {
        residual_audit_fail($message.' Missing: '.$needle);
    }
}

$audit = residual_audit_read_required(
    $root.'/docs/logic-security-audit-2026-06-13.md',
    'Residual logic & security audit report'
);

$tracker = residual_audit_read_required(
    $root.'/docs/logic-security-audit-tracker-2026-06-13.md',
    'Residual logic & security audit tracker'
);

foreach (['#137', '#138'] as $reference) {
    residual_audit_assert_contains(
        $tracker,
        $reference,
        'Tracker should keep traceability to the source issue and pull request.'
    );
}

foreach ([
    'security',
    'severity:',
    'audit-2026-06',
] as $label) {
    residual_audit_assert_contains(
        $tracker,
        $label,
        'Tracker should document the label convention.'
    );
}

foreach ([
    'Critical & High security',
    'Medium hardening',
    'Cleanup & robustness',
] as $milestone) {
    residual_audit_assert_contains(
        $tracker,
        $milestone,
        'Tracker should record implementation milestones.'
    );
}

$tasks = [
    'SEC-24' => ['issue' => 139, 'finding' => 'P1', 'title' => 'Mollie deposit webhook'],
    'SEC-25' => ['issue' => 140, 'finding' => 'P2', 'title' => 'Coinbase Commerce webhook'],
    'SEC-26' => ['issue' => 141, 'finding' => 'P3', 'title' => 'Blockonomics'],
    'SEC-27' => ['issue' => 142, 'finding' => 'N1', 'title' => 'Fixed-rate'],
    'SEC-28' => ['issue' => 143, 'finding' => 'N2', 'title' => 'market-data'],
    'SEC-29' => ['issue' => 144, 'finding' => 'N3', 'title' => 'destinations'],
    'SEC-30' => ['issue' => 145, 'finding' => 'N4', 'title' => '_validateAddress'],
    'SEC-31' => ['issue' => 146, 'finding' => 'N5', 'title' => '_checkReferalSource'],
    'SEC-32' => ['issue' => 147, 'finding' => 'L1', 'title' => 'Blockfolio'],
    'SEC-33' => ['issue' => 148, 'finding' => 'L2', 'title' => 'Histo cache'],
    'SEC-34' => ['issue' => 149, 'finding' => 'L3', 'title' => 'usort'],
    'SEC-35' => ['issue' => 150, 'finding' => 'L4', 'title' => 'changeIdentityStatus'],
];

foreach ($tasks as $code => $task) {
    residual_audit_assert_contains(
        $tracker,
        $code,
        'Tracker should list SEC code '.$code.'.'
    );
    residual_audit_assert_contains(
        $tracker,
        '#'.$task['issue'],
        'Tracker should map '.$code.' to issue #'.$task['issue'].'.'
    );
    residual_audit_assert_contains(
        $tracker,
        $task['finding'],
        'Tracker should map '.$code.' to finding '.$task['finding'].'.'
    );
    residual_audit_assert_contains(
        $tracker,
        $task['title'],
        'Tracker should retain the task topic for '.$code.'.'
    );

    residual_audit_assert_contains(
        $audit,
        '### '.$task['finding'].' ',
        'Audit report should describe finding '.$task['finding'].'.'
    );
    residual_audit_assert_contains(
        $audit,
        '#'.$task['issue'],
        'Audit report should reference issue #'.$task['issue'].'.'
    );
}

foreach ([
    'app/modules/kr-payment/src/actions/deposit/processMollie.php',
    'app/modules/kr-payment/src/Mollie.php',
    'app/modules/kr-payment/src/CoinbaseCommerce.php',
    'app/modules/kr-payment/src/Blockonomics.php',
    'app/modules/kr-changenow/src/ChangeNowMarketData.php',
    'app/modules/kr-changenow/src/ChangeNowMarketRepository.php',
    'app/modules/kr-changenow/src/ChangeNowPublicRateLimit.php',
    'app/modules/kr-changenow/src/ChangeNowPublicSwapFlow.php',
    'app/modules/kr-changenow/src/actions/publicSwap.php',
    'app/src/App/App.php',
    'app/src/App/csrf_policy.php',
    'app/modules/kr-blockfolio/views/blockfolio.php',
    'app/src/CryptoApi/CryptoCoin.php',
    'app/modules/kr-identity/src/actions/changeIdentityStatus.php',
    'app/src/User/User.php',
    'app/modules/kr-trade/src/Balance.php',
] as $evidencePath) {
    residual_audit_assert_contains(
        $audit,
        $evidencePath,
        'Audit report should preserve source evidence path.'
    );
}

residual_audit_assert_contains(
    $tracker,
    'logic-security-audit-2026-06-13.md',
    'Tracker should link to the full residual audit report.'
);

if (count($failures) > 0) {
    fwrite(STDERR, "Residual logic & security audit test failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- '.$failure."\n");
    }
    exit(1);
}

echo "Residual logic & security audit checks passed.\n";

?>
