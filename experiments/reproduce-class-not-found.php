<?php
/**
 * Reproduce the silent 500 caused by `catch (Exception)` not catching
 * `Error` (e.g. class-not-found) under error_reporting(0).
 *
 * Run:
 *   php experiments/reproduce-class-not-found.php
 */

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

echo "=== Scenario 1: catch(Exception) on missing class ===\n";

try {
    $x = MissingClass::doStuff();
    echo "did not throw\n";
} catch (Exception $e) {
    echo "caught Exception: " . $e->getMessage() . "\n";
}

echo "if you can read this, the catch worked\n";
