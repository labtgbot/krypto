<?php

$root = dirname(__DIR__);
$failures = [];

function failDbEnvironmentIf($condition, $message) {
    global $failures;
    if ($condition) {
        $failures[] = $message;
    }
}

function readDbEnvironmentFile($relativePath) {
    global $root;
    $path = $root.'/'.$relativePath;
    failDbEnvironmentIf(!file_exists($path), $relativePath.' should exist.');
    if (!file_exists($path)) {
        return '';
    }

    $contents = file_get_contents($path);
    failDbEnvironmentIf($contents === false || $contents === '', $relativePath.' should not be empty.');
    return (string) $contents;
}

function assertDbEnvironmentContains($contents, $needle, $message) {
    failDbEnvironmentIf(strpos($contents, $needle) === false, $message.' Missing: '.$needle);
}

$compose = readDbEnvironmentFile('docker-compose.yml');
foreach ([
    'services:',
    'app:',
    'db:',
    'mariadb:',
    'KRYPTO_ENV_CONFIG',
    'KRYPTO_TEST_DB_HOST',
    'db-bootstrap',
    'scripts/db_bootstrap.php',
] as $needle) {
    assertDbEnvironmentContains($compose, $needle, 'Docker Compose should define the local PHP/MySQL environment.');
}

$dockerfile = readDbEnvironmentFile('docker/dev/php/Dockerfile');
foreach ([
    'FROM php:',
    'pdo_mysql',
] as $needle) {
    assertDbEnvironmentContains($dockerfile, $needle, 'PHP dev image should support DB-backed tests.');
}

$bootstrap = readDbEnvironmentFile('scripts/db_bootstrap.php');
foreach ([
    'install/assets/sql/krypto.sql',
    '--reset',
    '--seed-fixtures',
    'KRYPTO_TEST_DB_HOST',
    'tests/support/db_fixtures.php',
] as $needle) {
    assertDbEnvironmentContains($bootstrap, $needle, 'DB bootstrap script should load schema and local fixtures without production secrets.');
}

$runner = readDbEnvironmentFile('scripts/run_tests.php');
foreach ([
    '--db',
    '--only-db',
    'KRYPTO_RUN_DB_TESTS',
] as $needle) {
    assertDbEnvironmentContains($runner, $needle, 'Test runner should expose an opt-in DB-backed mode.');
}

$support = readDbEnvironmentFile('tests/support/db_fixtures.php');
foreach ([
    'krypto_db_fixture_admin',
    'krypto_db_fixture_user',
    'krypto_db_fixture_session_for_user',
    'dev.admin@example.test',
] as $needle) {
    assertDbEnvironmentContains($support, $needle, 'DB fixture support should provide minimal admin/user/session factories.');
}

$smoke = readDbEnvironmentFile('tests/db_environment_smoke_db_test.php');
foreach ([
    'KRYPTO_RUN_DB_TESTS',
    'krypto_db_fixture_admin',
    'new App(false)',
    'new User(',
    'changenow_transactions_krypto',
] as $needle) {
    assertDbEnvironmentContains($smoke, $needle, 'DB smoke test should verify a real bootstrapped schema and fixtures.');
}

$docs = readDbEnvironmentFile('docs/local-db-tests.md');
foreach ([
    'docker compose up -d',
    'php scripts/db_bootstrap.php --reset --seed-fixtures',
    'php scripts/run_tests.php --db',
    'docker compose logs',
    'docker compose down -v',
    'No production credentials',
] as $needle) {
    assertDbEnvironmentContains($docs, $needle, 'Local DB documentation should cover startup, reset, logs, troubleshooting, and secrets boundary.');
}

$readme = readDbEnvironmentFile('README.md');
assertDbEnvironmentContains($readme, 'docs/local-db-tests.md', 'README should link to local DB-backed test documentation.');

if (count($failures) > 0) {
    fwrite(STDERR, "DB environment static test failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- '.$failure."\n");
    }
    exit(1);
}

echo "DB environment static checks passed.\n";

?>
