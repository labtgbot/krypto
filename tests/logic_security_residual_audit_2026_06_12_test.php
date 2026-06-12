<?php

// Регрессионный тест целостности остаточного аудита логики и безопасности (#127).
// Гарантирует, что отчет 2026-06-12 и трекер сохраняют связь с #127, PR #128
// и заведенными задачами SEC-20..SEC-23 (#129..#132).

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
    $root.'/docs/logic-security-audit-2026-06-12.md',
    'Residual logic & security audit report'
);

$tracker = residual_audit_read_required(
    $root.'/docs/logic-security-audit-tracker-2026-06-12.md',
    'Residual logic & security audit tracker'
);

foreach (['#127', '#128'] as $reference) {
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
    'SEC-20' => ['issue' => 129, 'finding' => 'R1', 'title' => 'status/refund/continue'],
    'SEC-21' => ['issue' => 130, 'finding' => 'R2', 'title' => 'raw exception messages'],
    'SEC-22' => ['issue' => 131, 'finding' => 'P1', 'title' => 'Payeer callback'],
    'SEC-23' => ['issue' => 132, 'finding' => 'P2', 'title' => 'Perfect Money IPN'],
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
    'app/modules/kr-changenow/src/ChangeNowPublicRateLimit.php',
    'app/modules/kr-changenow/src/actions/publicSwap.php',
    'tests/changenow_public_swap_rate_limit_test.php',
    'app/modules/kr-payment/src/actions/processPayeer.php',
    'app/modules/kr-payment/src/Payeer.php',
    'app/modules/kr-payment/src/actions/deposit/processPerfectMoney.php',
    'app/modules/kr-payment/src/PerfectMoney.php',
    'app/modules/kr-payment/views/perfectmoney.php',
    'app/modules/kr-admin/views/payment.php',
    'app/modules/kr-admin/src/actions/savePayment.php',
] as $evidencePath) {
    residual_audit_assert_contains(
        $audit,
        $evidencePath,
        'Audit report should preserve source evidence path.'
    );
}

residual_audit_assert_contains(
    $tracker,
    'logic-security-audit-2026-06-12.md',
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
