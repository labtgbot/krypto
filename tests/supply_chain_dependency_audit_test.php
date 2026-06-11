<?php

$root = dirname(__DIR__);
$failures = [];

function supply_chain_fail($message) {
    global $failures;
    $failures[] = $message;
}

function supply_chain_assert($condition, $message) {
    if (!$condition) {
        supply_chain_fail($message);
    }
}

function supply_chain_read_json($path, $label) {
    supply_chain_assert(is_file($path), $label.' should exist.');
    if (!is_file($path)) {
        return [];
    }

    $decoded = json_decode(file_get_contents($path), true);
    supply_chain_assert(is_array($decoded), $label.' should contain valid JSON.');
    return is_array($decoded) ? $decoded : [];
}

function supply_chain_read_file($path, $label) {
    supply_chain_assert(is_file($path), $label.' should exist.');
    if (!is_file($path)) {
        return '';
    }

    $contents = file_get_contents($path);
    supply_chain_assert($contents !== false, $label.' should be readable.');
    return $contents === false ? '' : $contents;
}

function supply_chain_git_ls_files($root, $pathspecs) {
    $command = 'git -C '.escapeshellarg($root).' ls-files';
    foreach ($pathspecs as $pathspec) {
        $command .= ' '.escapeshellarg($pathspec);
    }

    $output = [];
    $exitCode = 0;
    exec($command, $output, $exitCode);

    supply_chain_assert($exitCode === 0, 'git ls-files should inspect tracked dependency artifacts.');
    return array_values(array_filter($output, function ($line) {
        return $line !== '';
    }));
}

$composer = supply_chain_read_json($root.'/composer.json', 'composer.json');
$lock = supply_chain_read_json($root.'/composer.lock', 'composer.lock');
$package = supply_chain_read_json($root.'/package.json', 'package.json');
$gitignore = supply_chain_read_file($root.'/.gitignore', '.gitignore');
$ciWorkflow = supply_chain_read_file($root.'/.github/workflows/ci.yml', 'CI workflow');

$requiredPackages = isset($composer['require']) && is_array($composer['require']) ? $composer['require'] : [];
$lockedPackages = [];
$abandonedPackages = [];
foreach (array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []) as $packageEntry) {
    if (!isset($packageEntry['name'])) {
        continue;
    }

    $lockedPackages[$packageEntry['name']] = true;
    if (!empty($packageEntry['abandoned'])) {
        $abandonedPackages[] = $packageEntry['name'];
    }
}

foreach ([
    'facebook/graph-sdk',
    'milqmedia/poeditor-api-client',
    'sonata-project/google-authenticator',
] as $retiredPackage) {
    supply_chain_assert(
        !array_key_exists($retiredPackage, $requiredPackages),
        $retiredPackage.' should not remain a root Composer requirement.'
    );
    supply_chain_assert(
        !array_key_exists($retiredPackage, $lockedPackages),
        $retiredPackage.' should not remain in composer.lock.'
    );
}

supply_chain_assert(
    array_key_exists('league/oauth2-facebook', $requiredPackages),
    'Facebook OAuth should use the supported league/oauth2-facebook provider.'
);
supply_chain_assert(
    array_key_exists('robthree/twofactorauth', $requiredPackages),
    'Google Authenticator TOTP should use a maintained two-factor library.'
);
supply_chain_assert(
    count($abandonedPackages) === 0,
    'composer.lock should not contain abandoned packages. Found: '.implode(', ', $abandonedPackages)
);

foreach ([
    '/vendor/',
    '/assets/bower/',
    '/assets/node_modules/',
] as $ignoredArtifact) {
    supply_chain_assert(
        strpos($gitignore, $ignoredArtifact) !== false,
        '.gitignore should ignore generated dependency artifact '.$ignoredArtifact.'.'
    );
}

$trackedArtifacts = supply_chain_git_ls_files($root, [
    'vendor',
    'assets/bower',
    'assets/node_modules',
]);
supply_chain_assert(
    count($trackedArtifacts) === 0,
    'Generated dependency artifacts should not be tracked. Found '.count($trackedArtifacts).' tracked files.'
);

$scripts = isset($package['scripts']) && is_array($package['scripts']) ? $package['scripts'] : [];
supply_chain_assert(
    array_key_exists('build:assets', $scripts),
    'package.json should expose a reproducible frontend asset build.'
);
supply_chain_assert(
    array_key_exists('audit:dependencies', $scripts),
    'package.json should expose an npm audit command for CI.'
);

foreach ([
    'composer install',
    'composer audit',
    'npm run build:assets',
    'npm run audit:dependencies',
] as $ciNeedle) {
    supply_chain_assert(
        strpos($ciWorkflow, $ciNeedle) !== false,
        'CI should run dependency setup/audit step: '.$ciNeedle
    );
}

if (count($failures) > 0) {
    fwrite(STDERR, "Supply-chain dependency audit test failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- '.$failure."\n");
    }
    exit(1);
}

echo "Supply-chain dependency audit checks passed.\n";

?>
