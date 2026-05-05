<?php

/**
 * Regression test for issue #43: "The site does not load" (HTTP 500 after install).
 *
 * Root cause: critical bootstrap blocks used `catch (Exception $e)`. PHP 7+
 * throws `Error` (not `Exception`) for things like class-not-found, type
 * errors, and other engine-level failures. Combined with `error_reporting(0)`
 * in App::_loadPlatform(), any such Error escapes the catch block and the
 * server returns an empty 500 with no useful trace in the response body.
 *
 * This test verifies that the catch sites that wrap third-party / late-binding
 * code paths declare `catch (Throwable ...)` so engine errors are handled the
 * same as Exceptions.
 */

$root = dirname(__DIR__);

function assert_catches_throwable(string $file, string $context): void {
    $source = @file_get_contents($file);
    if ($source === false) {
        throw new Exception('Cannot read '.$file);
    }
    if (preg_match('/catch\s*\(\s*Exception\s+\$/', $source)) {
        throw new Exception($context.': '.$file.' still contains `catch (Exception $...)` for a critical bootstrap block. '
            .'Use `catch (Throwable $...)` so PHP Errors (e.g. class-not-found) do not escape and produce a silent 500.');
    }
    if (!preg_match('/catch\s*\(\s*Throwable\s+\$/', $source)) {
        throw new Exception($context.': '.$file.' does not catch Throwable in any block, but should defend its bootstrap.');
    }
}

assert_catches_throwable($root.'/index.php', 'index.php bootstrap');
assert_catches_throwable($root.'/app/modules/kr-changenow/views/publicSwap.php', 'publicSwap view bootstrap');

$appSource = file_get_contents($root.'/app/src/App/App.php');
if (strpos($appSource, 'set_exception_handler') === false) {
    throw new Exception('App::_loadPlatform must register set_exception_handler so uncaught Throwables are logged');
}
if (strpos($appSource, 'register_shutdown_function') === false) {
    throw new Exception('App::_loadPlatform must register register_shutdown_function so fatal errors are logged');
}
if (strpos($appSource, 'KRYPTO_ERROR_HANDLERS_REGISTERED') === false) {
    throw new Exception('App::_loadPlatform must guard handler registration with KRYPTO_ERROR_HANDLERS_REGISTERED');
}

// Functional check: with error_reporting(0), a Throwable catch must absorb
// a class-not-found Error rather than terminate the script.
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

$caught = null;
try {
    /** @phpstan-ignore-next-line */
    Krypto_DefinitelyMissing_Class_For_Test::doSomething();
} catch (Throwable $e) {
    $caught = $e;
}

if (!($caught instanceof Throwable)) {
    throw new Exception('catch(Throwable) failed to absorb a class-not-found Error');
}
if (!($caught instanceof Error)) {
    throw new Exception('Expected an Error instance for class-not-found, got '.get_class($caught));
}

echo "Silent-500 catch regression check passed\n";
