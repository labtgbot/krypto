<?php

$root = dirname(__DIR__);
$failures = [];

function fail_if($condition, $message) {
    global $failures;
    if ($condition) {
        $failures[] = $message;
    }
}

function read_required_file($path, $label) {
    fail_if(!file_exists($path), $label.' should exist at '.$path);
    if (!file_exists($path)) {
        return '';
    }

    $contents = file_get_contents($path);
    fail_if($contents === false || $contents === '', $label.' should not be empty');
    return (string) $contents;
}

function assert_contains_text($haystack, $needle, $message) {
    fail_if(strpos($haystack, $needle) === false, $message.' Missing: '.$needle);
}

function collect_files($directory, $extension) {
    if (!is_dir($directory)) {
        return [];
    }

    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && substr($file->getFilename(), -strlen($extension)) === $extension) {
            $files[] = $file->getPathname();
        }
    }

    sort($files);
    return $files;
}

$ciWorkflow = read_required_file($root.'/.github/workflows/ci.yml', 'GitHub Actions CI workflow');
$testRunner = read_required_file($root.'/scripts/run_tests.php', 'Lightweight test runner');
$phpLint = read_required_file($root.'/scripts/lint_php.php', 'PHP syntax lint runner');
$releaseDocs = read_required_file($root.'/docs/changenow-release-checks.md', 'ChangeNOW release checks documentation');
$stagingAuditDocs = read_required_file($root.'/docs/changenow-staging-audit-checklist.md', 'ChangeNOW staging audit checklist');

assert_contains_text($ciWorkflow, 'php scripts/lint_php.php', 'CI should validate first-party PHP syntax.');
assert_contains_text($ciWorkflow, 'php scripts/run_tests.php', 'CI should run the automated test suite.');
assert_contains_text($ciWorkflow, 'pull_request', 'CI should run on pull requests.');
assert_contains_text($ciWorkflow, 'timeout-minutes:', 'CI should have a bounded job timeout.');

assert_contains_text($testRunner, '*_test.php', 'The test runner should discover PHP test files.');
assert_contains_text($testRunner, '.sh', 'The test runner should support shell smoke tests.');
assert_contains_text($phpLint, 'RecursiveDirectoryIterator', 'The syntax runner should discover first-party PHP files.');

foreach ([
    'Automated release checks',
    'Mocked provider fixtures',
    'Manual live-test procedure',
    'Feature flags and rollback',
    'Provider tests must not call live ChangeNOW APIs',
] as $requiredSection) {
    assert_contains_text($releaseDocs, $requiredSection, 'Release docs should cover CN-15 readiness.');
}

foreach ([
    'Preconditions',
    'P1 Integration And Data Flow',
    'P2 Security And Privacy',
    'P3 Resilience And Rollback',
    'Current Automated Coverage',
    'Known Limitations To Record',
] as $requiredSection) {
    assert_contains_text($stagingAuditDocs, $requiredSection, 'Staging audit checklist should cover issue 37 verification.');
}

foreach ([
    'Currency/network mapping',
    'Network error handling',
    'Quote cache expiry',
    'Webhook or callback handling',
    'Redacted logging',
    'Widget URL sanitization',
    'Access control',
    'Feature flag off',
    'Database compatibility',
    'Rollback simulation',
] as $requiredChecklistItem) {
    assert_contains_text($stagingAuditDocs, $requiredChecklistItem, 'Staging audit checklist should include issue 37 audit item.');
}

$fixtureDirectory = $root.'/tests/fixtures/changenow';
$fixtureFiles = [
    'estimated_amount_standard_success.json',
    'exchange_create_success.json',
    'exchange_status_finished.json',
    'validation_error.json',
];

foreach ($fixtureFiles as $fixtureFile) {
    $fixturePath = $fixtureDirectory.'/'.$fixtureFile;
    $fixtureContents = read_required_file($fixturePath, 'ChangeNOW fixture '.$fixtureFile);
    if ($fixtureContents === '') {
        continue;
    }

    $decoded = json_decode($fixtureContents, true);
    fail_if(json_last_error() !== JSON_ERROR_NONE, $fixtureFile.' should contain valid JSON.');
    fail_if(!is_array($decoded), $fixtureFile.' should decode to an object or array.');
}

$testFiles = collect_files($root.'/tests', '.php');
foreach ($testFiles as $testFile) {
    if (basename($testFile) === basename(__FILE__)) {
        continue;
    }

    $source = file_get_contents($testFile);
    if ($source === false) {
        $source = '';
    }

    fail_if(stripos($source, 'curl_exec(') !== false, basename($testFile).' should not perform live cURL calls.');
    fail_if(stripos($source, 'file_get_contents(\'http') !== false, basename($testFile).' should not fetch live HTTP URLs.');
    fail_if(stripos($source, 'file_get_contents("http') !== false, basename($testFile).' should not fetch live HTTP URLs.');
    fail_if(
        preg_match('/new\s+ChangeNowApiClient\s*\([^)]*changenow\.io/i', $source) === 1,
        basename($testFile).' should not instantiate the real ChangeNOW API host.'
    );
    fail_if(
        preg_match('/(?:->|::)\s*_request\s*\([^)]*changenow\.io/i', $source) === 1,
        basename($testFile).' should not issue requests to the real ChangeNOW API host.'
    );
}

if (count($failures) > 0) {
    fwrite(STDERR, "ChangeNOW release readiness test failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "- ".$failure."\n");
    }
    exit(1);
}

echo "ChangeNOW release readiness checks passed.\n";

?>
