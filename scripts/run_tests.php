<?php

$root = dirname(__DIR__);
$testsRoot = $root.'/tests';
$patterns = ['*_test.php', '*.sh'];
$runDbTests = in_array('--db', $argv, true) || in_array('--only-db', $argv, true);
$onlyDbTests = in_array('--only-db', $argv, true);

if (in_array('--help', $argv, true) || in_array('-h', $argv, true)) {
    echo "Usage: php scripts/run_tests.php [--db] [--only-db]\n";
    echo "  --db       Run the normal suite with opt-in DB-backed tests enabled.\n";
    echo "  --only-db  Run only *_db_test.php checks with DB-backed tests enabled.\n";
    exit(0);
}

if ($runDbTests) {
    putenv('KRYPTO_RUN_DB_TESTS=1');
    $_ENV['KRYPTO_RUN_DB_TESTS'] = '1';
}

if (!is_dir($testsRoot)) {
    fwrite(STDERR, "Missing tests directory.\n");
    exit(1);
}

$tests = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($testsRoot, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if (!$file->isFile()) {
        continue;
    }

    $path = $file->getPathname();
    $relativePath = str_replace($testsRoot.DIRECTORY_SEPARATOR, '', $path);

    if (strpos($relativePath, 'fixtures'.DIRECTORY_SEPARATOR) === 0) {
        continue;
    }

    if (strpos($relativePath, 'support'.DIRECTORY_SEPARATOR) === 0) {
        continue;
    }

    $isDbBackedTest = substr($file->getFilename(), -12) === '_db_test.php';
    if ($onlyDbTests && !$isDbBackedTest) {
        continue;
    }

    if (substr($file->getFilename(), -9) === '_test.php' || substr($file->getFilename(), -3) === '.sh') {
        $tests[] = $path;
    }
}

sort($tests);

if (count($tests) === 0) {
    fwrite(STDERR, 'No test files matched '.implode(', ', $patterns).".\n");
    exit(1);
}

$failures = [];

foreach ($tests as $test) {
    $isShell = substr($test, -3) === '.sh';
    $command = ($isShell ? 'bash ' : escapeshellarg(PHP_BINARY).' ').escapeshellarg($test);
    $descriptors = [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $startedAt = microtime(true);
    $process = proc_open($command, $descriptors, $pipes, $root);

    if (!is_resource($process)) {
        $failures[] = [
            'test' => $test,
            'output' => 'Unable to start test process.',
            'duration' => 0,
        ];
        continue;
    }

    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);
    $duration = microtime(true) - $startedAt;
    $relative = substr($test, strlen($root) + 1);

    if ($exitCode === 0) {
        echo '[PASS] '.$relative.' ('.number_format($duration, 2)."s)\n";
        if (trim($stdout) !== '') {
            echo trim($stdout)."\n";
        }
        continue;
    }

    $failures[] = [
        'test' => $relative,
        'output' => trim($stdout."\n".$stderr),
        'duration' => $duration,
    ];
}

if (count($failures) > 0) {
    fwrite(STDERR, "Test suite failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- '.$failure['test'].' ('.number_format($failure['duration'], 2)."s)\n");
        if ($failure['output'] !== '') {
            fwrite(STDERR, $failure['output']."\n");
        }
    }
    exit(1);
}

echo 'Test suite passed for '.count($tests)." checks.\n";

?>
