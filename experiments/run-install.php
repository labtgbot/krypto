<?php
// Reproduce install end-to-end against the local MariaDB
session_start();

// Mock POST and SESSION as if user filled the install forms
$_SESSION['languages'] = ['language_select' => 'en'];
$_SESSION['bdd'] = [
  'sql_host' => '127.0.0.1',
  'sql_port' => '3306',
  'sql_user' => 'krypto',
  'sql_password' => 'krypto',
  'sql_database_name' => 'krypto'
];
$_SESSION['configure'] = [
  'website_url' => 'http://localhost:8000',
  'website_path' => ''
];
$_SESSION['admin'] = [
  'admin_email' => 'admin@localhost',
  'admin_name' => 'Admin',
  'admin_password' => 'admin'
];

chdir(__DIR__ . '/../install');

require __DIR__ . '/../install/app/src/Install.php';

$Install = new Install();

echo "=== Generating BDD ===\n";
$result = $Install->_generateBDD();
var_dump($result);

echo "\n=== Creating admin ===\n";
try {
  $result = $Install->_createAdmin();
  var_dump($result);
} catch (Throwable $e) {
  echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== Done ===\n";
