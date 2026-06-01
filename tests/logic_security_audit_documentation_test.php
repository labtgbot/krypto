<?php

// Регрессионный тест присутствия документа аудита логики и безопасности (#85).
// Гарантирует, что отчёт `docs/logic-security-audit-2026-06-01.md` остаётся в
// репозитории, перечисляет ключевые находки и сохраняет трассируемость до #85.

$root = dirname(__DIR__);
$failures = [];

function audit_doc_fail($message) {
    global $failures;
    $failures[] = $message;
}

function audit_doc_read_required($path, $label) {
    if (!is_file($path)) {
        audit_doc_fail($label.' should exist at '.$path);
        return '';
    }

    $contents = file_get_contents($path);
    if ($contents === false || trim($contents) === '') {
        audit_doc_fail($label.' should not be empty.');
        return '';
    }

    return (string)$contents;
}

function audit_doc_assert_contains($haystack, $needle, $message) {
    if (strpos($haystack, $needle) === false) {
        audit_doc_fail($message.' Missing: '.$needle);
    }
}

$audit = audit_doc_read_required(
    $root.'/docs/logic-security-audit-2026-06-01.md',
    'Logic & security audit report'
);

// Трассируемость до исходного запроса и трекера.
foreach ([
    '#85',
    '#87',
] as $requiredReference) {
    audit_doc_assert_contains(
        $audit,
        $requiredReference,
        'Audit report should keep traceability to the originating issue and tracker.'
    );
}

// Каждая категория находок должна присутствовать.
foreach ([
    '## A. Аутентификация и учётные записи',
    '## B. Межсайтовый скриптинг (XSS)',
    '## C. Нарушение контроля доступа / IDOR',
    '## D. Установщик и runtime-конфигурация',
    '## E. Секреты и цепочка поставок',
    '## F. Сеть, транспорт, SSRF и доверие к заголовкам',
    '## G. Целостность ChangeNOW-свопа',
    '## I. Раскрытие информации и операционные риски',
] as $requiredSection) {
    audit_doc_assert_contains(
        $audit,
        $requiredSection,
        'Audit report should document every finding category.'
    );
}

// Каждый код находки, на который ссылаются заведённые задачи, должен быть в отчёте.
foreach ([
    'A1', 'A2', 'A3', 'A4', 'A5', 'A6', 'A7', 'A9', 'A10', 'A11', 'A12',
    'B1', 'B2', 'B3', 'B4', 'B5', 'B6', 'B7', 'B8',
    'C1', 'C2', 'C3', 'C6', 'C7',
    'D1', 'D2', 'D3',
    'E1', 'E2', 'E3', 'E4',
    'F1', 'F2', 'F3', 'F4', 'F5', 'F6',
    'G1', 'G2', 'G3',
    'I1', 'I2',
] as $code) {
    audit_doc_assert_contains(
        $audit,
        '### '.$code.' ',
        'Audit report should describe finding '.$code.'.'
    );
}

// Каждая заведённая задача (#88..#106) должна упоминаться в таблице соответствия.
for ($issue = 88; $issue <= 106; $issue++) {
    audit_doc_assert_contains(
        $audit,
        '#'.$issue,
        'Audit report should map findings to sub-issue #'.$issue.'.'
    );
}

// Этапы реализации (milestones) должны быть зафиксированы.
foreach ([
    'Stage 1 — Critical & High',
    'Stage 2 — Medium hardening',
    'Stage 3 — Supply-chain',
    'Stage 4 — Cleanup & robustness',
] as $stage) {
    audit_doc_assert_contains(
        $audit,
        $stage,
        'Audit report should record the implementation stages.'
    );
}

if (count($failures) > 0) {
    fwrite(STDERR, "Logic & security audit documentation test failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- '.$failure."\n");
    }
    exit(1);
}

echo "Logic & security audit documentation checks passed.\n";

?>
