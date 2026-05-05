<?php
/**
 * Verify that catching Throwable handles Errors gracefully.
 */

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

echo "=== Scenario: catch(Throwable) on missing class ===\n";

try {
    $x = MissingClass::doStuff();
    echo "did not throw\n";
} catch (Throwable $e) {
    echo "caught Throwable: " . get_class($e) . " -> " . $e->getMessage() . "\n";
}

echo "graceful continuation: OK\n";
