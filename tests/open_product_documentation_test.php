<?php

$root = dirname(__DIR__);
$failures = [];

function open_doc_fail($message) {
    global $failures;
    $failures[] = $message;
}

function open_doc_read_required($path, $label) {
    if (!is_file($path)) {
        open_doc_fail($label.' should exist at '.$path);
        return '';
    }

    $contents = file_get_contents($path);
    if ($contents === false || trim($contents) === '') {
        open_doc_fail($label.' should not be empty.');
        return '';
    }

    return (string)$contents;
}

function open_doc_assert_contains($haystack, $needle, $message) {
    if (strpos($haystack, $needle) === false) {
        open_doc_fail($message.' Missing: '.$needle);
    }
}

function open_doc_assert_not_contains_ci($haystack, $needle, $message) {
    if (stripos($haystack, $needle) !== false) {
        open_doc_fail($message.' Unexpected: '.$needle);
    }
}

$readme = open_doc_read_required($root.'/README.md', 'README');
$composerJson = open_doc_read_required($root.'/composer.json', 'Composer metadata');
$roadmap = open_doc_read_required($root.'/docs/open-noncustodial-roadmap-2026-05-29.md', 'Open non-custodial roadmap');
$platformAnalysis = open_doc_read_required($root.'/docs/platform-analysis.md', 'Platform analysis');

$composer = json_decode($composerJson, true);
if (!is_array($composer)) {
    open_doc_fail('composer.json should contain valid JSON.');
    $composer = [];
}

foreach ([
    'open ChangeNOW-powered cross-currency swap',
    'without mandatory registration',
    'non-custodial',
    'Krypto does not store customer funds',
    'ChangeNOW executes each exchange',
    '## Quick Start',
    '## ChangeNOW Provider Setup',
    'docs/changenow-provider-settings.md',
    'docs/changenow-release-checks.md',
    'docs/local-db-tests.md',
] as $requiredReadmeText) {
    open_doc_assert_contains($readme, $requiredReadmeText, 'README should present the current open swap product.');
}

foreach ([
    'online trading, advanced data, market analysis, watchlist, portfolio, subscriptions',
    'Legacy PHP cryptocurrency service',
] as $staleReadmeText) {
    open_doc_assert_not_contains_ci($readme, $staleReadmeText, 'README should not present the legacy custodial trading terminal as the product.');
}

$description = isset($composer['description']) ? (string)$composer['description'] : '';
foreach (['ChangeNOW', 'non-custodial', 'without mandatory registration'] as $requiredDescriptionText) {
    open_doc_assert_contains($description, $requiredDescriptionText, 'Composer description should match the current product.');
}

foreach (['Legacy PHP cryptocurrency service', 'trading, market data, payment, subscription'] as $staleDescriptionText) {
    open_doc_assert_not_contains_ci($description, $staleDescriptionText, 'Composer description should not advertise the legacy product.');
}

open_doc_assert_contains(
    $readme,
    'The Composer package remains marked `proprietary` until maintainers publish an explicit source license.',
    'README should make the current source-license state explicit while the product is open-access.'
);

foreach ([
    'Gap 6 was addressed by issue #74',
    'README and Composer metadata now present Krypto as an open, non-custodial ChangeNOW swap product.',
] as $requiredRoadmapText) {
    open_doc_assert_contains($roadmap, $requiredRoadmapText, 'Roadmap should not keep stale documentation findings as current facts.');
}

foreach ([
    'Current product update',
    'open ChangeNOW-powered cross-currency swap',
    'legacy trading-terminal capabilities below are retained as historical architecture context',
] as $requiredAnalysisText) {
    open_doc_assert_contains($platformAnalysis, $requiredAnalysisText, 'Platform analysis should distinguish current product positioning from legacy architecture inventory.');
}

if (count($failures) > 0) {
    fwrite(STDERR, "Open product documentation test failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "- ".$failure."\n");
    }
    exit(1);
}

echo "Open product documentation checks passed.\n";

?>
