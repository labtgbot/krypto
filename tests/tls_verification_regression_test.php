<?php

/**
 * Regression coverage for issue #97: first-party cURL calls must not disable
 * TLS certificate or host verification, and external cURL URLs must not use
 * plaintext HTTP.
 */

$root = dirname(__DIR__);

function assert_tls_regression($condition, $message) {
    if (!$condition) {
        throw new Exception($message);
    }
}

function tls_php_source_without_comments($source) {
    $tokens = token_get_all($source);
    $stripped = '';

    foreach ($tokens as $token) {
        if (is_array($token)) {
            if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                $stripped .= str_repeat("\n", substr_count($token[1], "\n"));
                continue;
            }

            $stripped .= $token[1];
            continue;
        }

        $stripped .= $token;
    }

    return $stripped;
}

function tls_relative_path($root, $path) {
    return str_replace($root.DIRECTORY_SEPARATOR, '', $path);
}

function tls_line_number($source, $offset) {
    return substr_count(substr($source, 0, $offset), "\n") + 1;
}

function tls_first_party_php_files($root) {
    $targets = [
        $root.'/app',
        $root.'/config',
        $root.'/install',
        $root.'/public',
        $root.'/dashboard.php',
        $root.'/index.php',
    ];

    $files = [];

    foreach ($targets as $target) {
        if (!file_exists($target)) {
            continue;
        }

        if (is_file($target)) {
            if (substr($target, -4) === '.php') {
                $files[] = $target;
            }
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && substr($file->getFilename(), -4) === '.php') {
                $files[] = $file->getPathname();
            }
        }
    }

    $files = array_values(array_unique($files));
    sort($files);
    return $files;
}

$forbiddenPatterns = [
    '/CURLOPT_SSL_VERIFYPEER\s*,\s*(?:0|false)\b/i' => 'CURLOPT_SSL_VERIFYPEER must not be disabled',
    '/CURLOPT_SSL_VERIFYPEER\s*=>\s*(?:0|false)\b/i' => 'CURLOPT_SSL_VERIFYPEER must not be disabled',
    '/CURLOPT_SSL_VERIFYHOST\s*,\s*(?:0|1|false|true)\b/i' => 'CURLOPT_SSL_VERIFYHOST must be 2',
    '/CURLOPT_SSL_VERIFYHOST\s*=>\s*(?:0|1|false|true)\b/i' => 'CURLOPT_SSL_VERIFYHOST must be 2',
    '/curl_init\s*\(\s*[\'"]http:\/\//i' => 'cURL calls to external APIs must use HTTPS',
    '/CURLOPT_URL\s*(?:,|=>)\s*[\'"]http:\/\//i' => 'cURL calls to external APIs must use HTTPS',
];

$violations = [];

foreach (tls_first_party_php_files($root) as $file) {
    $source = file_get_contents($file);
    assert_tls_regression($source !== false, 'Cannot read '.$file);

    $source = tls_php_source_without_comments($source);
    foreach ($forbiddenPatterns as $pattern => $message) {
        if (!preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE)) {
            continue;
        }

        foreach ($matches[0] as $match) {
            $violations[] = tls_relative_path($root, $file).':'.tls_line_number($source, $match[1]).' '.$message;
        }
    }
}

assert_tls_regression(
    count($violations) === 0,
    "Unsafe cURL TLS configuration found:\n- ".implode("\n- ", $violations)
);

echo "TLS verification checks passed.\n";

?>
