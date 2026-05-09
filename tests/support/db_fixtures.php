<?php

function krypto_db_env_value($keys, $default = null) {
    if (!is_array($keys)) {
        $keys = [$keys];
    }

    foreach ($keys as $key) {
        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return $value;
        }
    }

    return $default;
}

function krypto_db_is_enabled() {
    return getenv('KRYPTO_RUN_DB_TESTS') === '1';
}

function krypto_db_skip($message) {
    echo '[SKIP] '.$message."\n";
    exit(0);
}

function krypto_db_config() {
    return [
        'host' => krypto_db_env_value(['KRYPTO_TEST_DB_HOST', 'MYSQL_HOST'], '127.0.0.1'),
        'port' => krypto_db_env_value(['KRYPTO_TEST_DB_PORT', 'MYSQL_PORT'], '3306'),
        'database' => krypto_db_env_value(['KRYPTO_TEST_DB_NAME', 'MYSQL_DATABASE'], 'krypto'),
        'user' => krypto_db_env_value(['KRYPTO_TEST_DB_USER', 'MYSQL_USER'], 'krypto'),
        'password' => krypto_db_env_value(['KRYPTO_TEST_DB_PASSWORD', 'MYSQL_PASSWD'], 'krypto'),
        'crypted_key' => krypto_db_env_value(['KRYPTO_CRYPTED_KEY', 'CRYPTED_KEY'], 'local-dev-only-change-me'),
        'app_url' => krypto_db_env_value('KRYPTO_APP_URL', 'http://localhost:8080'),
        'file_path' => krypto_db_env_value('KRYPTO_FILE_PATH', ''),
    ];
}

function krypto_db_define_runtime_constants($root) {
    $config = krypto_db_config();

    if (!defined('APP_URL')) {
        define('APP_URL', $config['app_url']);
    }
    if (!defined('APP_URL_FORCE')) {
        define('APP_URL_FORCE', true);
    }
    if (!defined('FILE_PATH')) {
        define('FILE_PATH', $config['file_path']);
    }
    if (!defined('MYSQL_HOST')) {
        define('MYSQL_HOST', $config['host']);
    }
    if (!defined('MYSQL_USER')) {
        define('MYSQL_USER', $config['user']);
    }
    if (!defined('MYSQL_PASSWD')) {
        define('MYSQL_PASSWD', $config['password']);
    }
    if (!defined('MYSQL_PORT')) {
        define('MYSQL_PORT', $config['port']);
    }
    if (!defined('MYSQL_DATABASE')) {
        define('MYSQL_DATABASE', $config['database']);
    }
    if (!defined('CRYPTED_KEY')) {
        define('CRYPTED_KEY', $config['crypted_key']);
    }

    $_SERVER['DOCUMENT_ROOT'] = $root;
}

function krypto_db_pdo() {
    static $pdo = null;

    if (!is_null($pdo)) {
        return $pdo;
    }

    $config = krypto_db_config();
    $dsn = 'mysql:host='.$config['host'].';port='.$config['port'].';dbname='.$config['database'].';charset=utf8mb4';
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
        $options[PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES utf8mb4';
    }

    $pdo = new PDO($dsn, $config['user'], $config['password'], $options);

    return $pdo;
}

function krypto_db_table_count($pdo) {
    $statement = $pdo->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"');
    return count($statement->fetchAll(PDO::FETCH_NUM));
}

function krypto_db_table_exists($pdo, $table) {
    $statement = $pdo->prepare('SHOW TABLES LIKE :table_name');
    $statement->execute(['table_name' => $table]);
    return $statement->fetch(PDO::FETCH_NUM) !== false;
}

function krypto_db_fetch_user_by_id($pdo, $id) {
    $statement = $pdo->prepare('SELECT * FROM user_krypto WHERE id_user = :id_user');
    $statement->execute(['id_user' => $id]);
    return $statement->fetch(PDO::FETCH_ASSOC);
}

function krypto_db_fixture_user($pdo, $overrides = []) {
    $now = (string) time();
    $defaults = [
        'email_user' => 'dev.user@example.test',
        'name_user' => 'Dev User',
        'password_user' => hash('sha512', 'password'),
        'picture_user' => '',
        'oauth_user' => 'standard',
        'pushbullet_user' => '',
        'twostep_user' => 0,
        'currency_user' => 'USD',
        'admin_user' => 0,
        'lang_user' => 'EN',
        'created_date_user' => $now,
        'reset_token_user' => '',
        'status_user' => 1,
        'current_market' => 'CCCAGG',
    ];
    $user = array_merge($defaults, $overrides);

    $existing = $pdo->prepare('SELECT id_user FROM user_krypto WHERE email_user = :email_user ORDER BY id_user ASC LIMIT 1');
    $existing->execute(['email_user' => $user['email_user']]);
    $row = $existing->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $user['id_user'] = $row['id_user'];
        $update = $pdo->prepare('UPDATE user_krypto SET
            name_user = :name_user,
            password_user = :password_user,
            picture_user = :picture_user,
            oauth_user = :oauth_user,
            pushbullet_user = :pushbullet_user,
            twostep_user = :twostep_user,
            currency_user = :currency_user,
            admin_user = :admin_user,
            lang_user = :lang_user,
            created_date_user = :created_date_user,
            reset_token_user = :reset_token_user,
            status_user = :status_user,
            current_market = :current_market
            WHERE id_user = :id_user');
        $update->execute($user);
    } else {
        $insert = $pdo->prepare('INSERT INTO user_krypto (
            email_user,
            name_user,
            password_user,
            picture_user,
            oauth_user,
            pushbullet_user,
            twostep_user,
            currency_user,
            admin_user,
            lang_user,
            created_date_user,
            reset_token_user,
            status_user,
            current_market
        ) VALUES (
            :email_user,
            :name_user,
            :password_user,
            :picture_user,
            :oauth_user,
            :pushbullet_user,
            :twostep_user,
            :currency_user,
            :admin_user,
            :lang_user,
            :created_date_user,
            :reset_token_user,
            :status_user,
            :current_market
        )');
        $insert->execute($user);
        $user['id_user'] = $pdo->lastInsertId();
    }

    return krypto_db_fetch_user_by_id($pdo, $user['id_user']);
}

function krypto_db_fixture_admin($pdo, $overrides = []) {
    return krypto_db_fixture_user($pdo, array_merge([
        'email_user' => 'dev.admin@example.test',
        'name_user' => 'Dev Admin',
        'admin_user' => 1,
    ], $overrides));
}

function krypto_db_fixture_session_for_user($user) {
    return [
        'kr_login' => json_encode($user),
    ];
}

function krypto_db_seed_minimal_fixtures($pdo) {
    $admin = krypto_db_fixture_admin($pdo);
    $user = krypto_db_fixture_user($pdo);

    return [
        'admin' => $admin,
        'user' => $user,
        'session' => krypto_db_fixture_session_for_user($admin),
    ];
}

function krypto_db_require_application($root) {
    krypto_db_define_runtime_constants($root);

    require_once $root.'/vendor/autoload.php';
    require_once $root.'/app/src/MySQL/MySQL.php';
    require_once $root.'/app/src/App/App.php';
    require_once $root.'/app/src/App/AppModule.php';
    require_once $root.'/app/src/User/User.php';
}

?>
