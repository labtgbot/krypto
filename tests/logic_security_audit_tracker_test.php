<?php

// Регрессионный тест целостности трекера аудита логики и безопасности (SEC-00, #87).
// Гарантирует, что документ-трекер `docs/logic-security-audit-tracker-2026-06-01.md`
// остаётся в репозитории, перечисляет все 19 подзадач (#88–#106) с их кодами
// SEC-NN, severity и этапами, и сохраняет согласованность с полным отчётом и
// трассируемость до запроса #85.

$root = dirname(__DIR__);
$failures = [];

function tracker_fail($message) {
    global $failures;
    $failures[] = $message;
}

function tracker_read_required($path, $label) {
    if (!is_file($path)) {
        tracker_fail($label.' should exist at '.$path);
        return '';
    }

    $contents = file_get_contents($path);
    if ($contents === false || trim($contents) === '') {
        tracker_fail($label.' should not be empty.');
        return '';
    }

    return (string)$contents;
}

function tracker_assert_contains($haystack, $needle, $message) {
    if (strpos($haystack, $needle) === false) {
        tracker_fail($message.' Missing: '.$needle);
    }
}

$tracker = tracker_read_required(
    $root.'/docs/logic-security-audit-tracker-2026-06-01.md',
    'Logic & security audit tracker'
);

$audit = tracker_read_required(
    $root.'/docs/logic-security-audit-2026-06-01.md',
    'Logic & security audit report'
);

// Трассируемость до запроса-источника, зонтичного трекера и полного отчёта.
foreach ([
    '#85',
    '#87',
    'SEC-00',
    'logic-security-audit-2026-06-01.md',
] as $reference) {
    tracker_assert_contains(
        $tracker,
        $reference,
        'Tracker should keep traceability to the source request, tracker, and full report.'
    );
}

// Соглашение о метках должно быть зафиксировано.
foreach ([
    'security',
    'severity:',
    'audit-2026-06',
] as $label) {
    tracker_assert_contains(
        $tracker,
        $label,
        'Tracker should document the label convention.'
    );
}

// Этапы реализации (milestones) должны присутствовать.
foreach ([
    'Critical & High security',
    'Medium hardening',
    'Supply-chain & dependencies',
    'Cleanup & robustness',
] as $milestone) {
    tracker_assert_contains(
        $tracker,
        $milestone,
        'Tracker should record the implementation milestones.'
    );
}

// Каждая подзадача трекера: код SEC-NN, номер issue, и наличие в полном отчёте.
// Маппинг сверен с docs/logic-security-audit-2026-06-01.md (таблица соответствия).
$tasks = [
    'SEC-01' => ['issue' => 88, 'findings' => ['A1']],
    'SEC-02' => ['issue' => 89, 'findings' => ['B1', 'B5', 'B6']],
    'SEC-03' => ['issue' => 90, 'findings' => ['B2', 'B3', 'B4', 'B7', 'B8']],
    'SEC-04' => ['issue' => 91, 'findings' => ['C1', 'C2', 'C3', 'C6', 'C7']],
    'SEC-05' => ['issue' => 92, 'findings' => ['A3', 'A4']],
    'SEC-06' => ['issue' => 93, 'findings' => ['A2', 'A6']],
    'SEC-07' => ['issue' => 94, 'findings' => ['A5']],
    'SEC-08' => ['issue' => 95, 'findings' => ['D1', 'D2']],
    'SEC-09' => ['issue' => 96, 'findings' => ['E1', 'A7']],
    'SEC-10' => ['issue' => 97, 'findings' => ['F1']],
    'SEC-11' => ['issue' => 98, 'findings' => ['F2', 'F3', 'F4']],
    'SEC-12' => ['issue' => 99, 'findings' => ['F5']],
    'SEC-13' => ['issue' => 100, 'findings' => ['F6']],
    'SEC-14' => ['issue' => 101, 'findings' => ['G1', 'G2', 'G3']],
    'SEC-15' => ['issue' => 102, 'findings' => ['A9', 'A10', 'A11']],
    'SEC-16' => ['issue' => 103, 'findings' => ['A12']],
    'SEC-17' => ['issue' => 104, 'findings' => ['E2', 'E3', 'E4']],
    'SEC-18' => ['issue' => 105, 'findings' => ['D3']],
    'SEC-19' => ['issue' => 106, 'findings' => ['I1', 'I2']],
];

foreach ($tasks as $code => $task) {
    tracker_assert_contains(
        $tracker,
        $code,
        'Tracker should list SEC code '.$code.'.'
    );
    tracker_assert_contains(
        $tracker,
        '#'.$task['issue'],
        'Tracker should map '.$code.' to sub-issue #'.$task['issue'].'.'
    );

    foreach ($task['findings'] as $finding) {
        // Каждая находка обязана упоминаться в трекере и быть описана в отчёте,
        // что гарантирует согласованность маппинга трекер ↔ отчёт.
        tracker_assert_contains(
            $tracker,
            $finding,
            'Tracker should reference finding '.$finding.' for '.$code.'.'
        );
        tracker_assert_contains(
            $audit,
            '### '.$finding.' ',
            'Audit report should describe finding '.$finding.' referenced by tracker '.$code.'.'
        );
    }
}

// Зонтичная задача SEC-00 трекера должна быть зафиксирована явно.
tracker_assert_contains(
    $tracker,
    '#87',
    'Tracker should record the umbrella SEC-00 issue.'
);

if (count($failures) > 0) {
    fwrite(STDERR, "Logic & security audit tracker test failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- '.$failure."\n");
    }
    exit(1);
}

echo "Logic & security audit tracker checks passed.\n";

?>
