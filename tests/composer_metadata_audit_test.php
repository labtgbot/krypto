<?php

$root = dirname(__DIR__);
$composerPath = $root.'/composer.json';
$composer = json_decode(file_get_contents($composerPath), true);

if (!is_array($composer)) {
    fwrite(STDERR, "composer.json is not valid JSON.\n");
    exit(1);
}

function assert_true($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, $message."\n");
        exit(1);
    }
}

foreach (['name', 'description', 'license', 'type'] as $field) {
    assert_true(
        isset($composer[$field]) && trim((string)$composer[$field]) !== '',
        'composer.json must define a non-empty '.$field.'.'
    );
}

assert_true(
    isset($composer['require']['php']) && strpos($composer['require']['php'], '>=7.4') !== false,
    'composer.json must declare the PHP runtime supported by the installer.'
);

$allowedPinnedDevPackages = [
    'codename065/coinbase-commerce',
    'coingate/omnipay-coingate',
    'php-curl-class/php-curl-class',
];

foreach ($composer['require'] as $package => $constraint) {
    if ($package === 'php') {
        continue;
    }

    $constraint = trim((string)$constraint);

    assert_true($constraint !== '*', $package.' must not use an unbounded Composer constraint.');
    assert_true(
        !preg_match('/^\d+(?:\.\d+){1,3}$/', $constraint),
        $package.' must not use an exact Composer version constraint.'
    );

    if (strpos($constraint, 'dev-') === 0) {
        assert_true(
            in_array($package, $allowedPinnedDevPackages, true),
            $package.' must use a stable Composer constraint unless explicitly allowlisted.'
        );
        assert_true(
            $constraint === 'dev-master',
            $package.' must use the documented dev-master exception.'
        );
    }
}

$reportPath = $root.'/docs/composer-dependency-audit-2026-05-09.md';
assert_true(is_file($reportPath), 'Composer dependency audit report must be committed.');
$report = file_get_contents($reportPath);
foreach ($allowedPinnedDevPackages as $package) {
    assert_true(
        strpos($report, $package) !== false,
        $package.' dev-master exception must be documented in the audit report.'
    );
}

echo "Composer metadata audit passed.\n";

?>
