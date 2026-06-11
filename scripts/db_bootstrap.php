<?php

$root = dirname(__DIR__);
require_once $root.'/tests/support/db_fixtures.php';

function krypto_db_bootstrap_usage() {
    echo "Usage: php scripts/db_bootstrap.php [--wait] [--reset] [--seed-fixtures] [--apply-migrations]\n";
    echo "Environment: KRYPTO_TEST_DB_HOST, KRYPTO_TEST_DB_PORT, KRYPTO_TEST_DB_NAME, KRYPTO_TEST_DB_USER, KRYPTO_TEST_DB_PASSWORD\n";
}

function krypto_db_bootstrap_has_arg($name) {
    global $argv;
    return in_array($name, $argv, true);
}

function krypto_db_bootstrap_connect($wait) {
    $deadline = time() + 60;
    $lastError = null;

    do {
        try {
            return krypto_db_pdo();
        } catch (Exception $e) {
            $lastError = $e;
            if (!$wait || time() >= $deadline) {
                break;
            }
            sleep(2);
        }
    } while (true);

    throw $lastError;
}

function krypto_db_bootstrap_drop_tables($pdo) {
    $tables = $pdo->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')->fetchAll(PDO::FETCH_NUM);
    if (count($tables) === 0) {
        return 0;
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($tables as $tableRow) {
        $table = str_replace('`', '``', $tableRow[0]);
        $pdo->exec('DROP TABLE `'.$table.'`');
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    return count($tables);
}

function krypto_db_bootstrap_exec_file($pdo, $path) {
    if (!file_exists($path)) {
        throw new Exception('Missing SQL file: '.$path);
    }

    $sql = file_get_contents($path);
    if ($sql === false || trim($sql) === '') {
        throw new Exception('SQL file is empty: '.$path);
    }

    $pdo->exec($sql);
}

if (krypto_db_bootstrap_has_arg('--help') || krypto_db_bootstrap_has_arg('-h')) {
    krypto_db_bootstrap_usage();
    exit(0);
}

$reset = krypto_db_bootstrap_has_arg('--reset');
$wait = krypto_db_bootstrap_has_arg('--wait');
$seedFixtures = krypto_db_bootstrap_has_arg('--seed-fixtures');
$applyMigrations = krypto_db_bootstrap_has_arg('--apply-migrations');

try {
    $pdo = krypto_db_bootstrap_connect($wait);
    $config = krypto_db_config();

    if ($reset) {
        $dropped = krypto_db_bootstrap_drop_tables($pdo);
        echo 'Dropped '.$dropped.' existing tables from '.$config['database'].".\n";
    }

    $tableCount = krypto_db_table_count($pdo);
    if ($tableCount === 0) {
        krypto_db_bootstrap_exec_file($pdo, $root.'/install/assets/sql/krypto.sql');
        echo "Loaded fresh schema from install/assets/sql/krypto.sql.\n";
    } else {
        echo 'Database '.$config['database'].' already has '.$tableCount." tables; fresh schema load skipped.\n";
    }

    if ($applyMigrations) {
        $migrationPaths = [];
        foreach (['changenow-*-migration.sql', 'security-*-migration.sql'] as $migrationPattern) {
            foreach (glob($root.'/install/assets/sql/'.$migrationPattern) as $migrationPath) {
                $migrationPaths[] = $migrationPath;
            }
        }
        sort($migrationPaths);

        foreach ($migrationPaths as $migrationPath) {
            krypto_db_bootstrap_exec_file($pdo, $migrationPath);
            echo 'Applied migration '.basename($migrationPath).".\n";
        }
    }

    if ($seedFixtures) {
        $fixtures = krypto_db_seed_minimal_fixtures($pdo);
        echo 'Seeded local fixtures: admin '.$fixtures['admin']['email_user'].' and user '.$fixtures['user']['email_user'].".\n";
    }

    echo "DB bootstrap completed.\n";
} catch (Exception $e) {
    fwrite(STDERR, 'DB bootstrap failed: '.$e->getMessage()."\n");
    exit(1);
}

?>
