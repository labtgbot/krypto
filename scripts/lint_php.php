<?php

$root = dirname(__DIR__);
$targets = [
    $root.'/app',
    $root.'/config',
    $root.'/install',
    $root.'/public',
    $root.'/dashboard.php',
    $root.'/index.php',
];

$files = [];

function add_php_file($path) {
    global $files;
    if (is_file($path) && substr($path, -4) === '.php') {
        $files[] = $path;
    }
}

foreach ($targets as $target) {
    if (!file_exists($target)) {
        continue;
    }

    if (is_file($target)) {
        add_php_file($target);
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile()) {
            add_php_file($file->getPathname());
        }
    }
}

$files = array_values(array_unique($files));
sort($files);

if (count($files) === 0) {
    fwrite(STDERR, "No first-party PHP files found for syntax validation.\n");
    exit(1);
}

$failures = [];

foreach ($files as $file) {
    $command = escapeshellarg(PHP_BINARY).' -d error_reporting=E_PARSE -l '.escapeshellarg($file);
    $output = [];
    $exitCode = 0;
    exec($command, $output, $exitCode);

    if ($exitCode !== 0) {
        $failures[] = [
            'file' => $file,
            'output' => implode("\n", $output),
        ];
    }
}

if (count($failures) > 0) {
    fwrite(STDERR, "PHP syntax validation failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "- ".$failure['file']."\n");
        if ($failure['output'] !== '') {
            fwrite(STDERR, $failure['output']."\n");
        }
    }
    exit(1);
}

echo 'PHP syntax validation passed for '.count($files)." first-party files.\n";

?>
