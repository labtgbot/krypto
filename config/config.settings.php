<?php
    if(!function_exists('krypto_env_config_value')){
      function krypto_env_config_value($key, $default = ''){
        $value = getenv($key);
        return ($value === false || $value === '' ? $default : $value);
      }
    }

    if(getenv('KRYPTO_ENV_CONFIG') === '1'){
      if(!defined('APP_URL')) define('APP_URL', krypto_env_config_value('KRYPTO_APP_URL', 'http://localhost:8080'));
      if(!defined('APP_URL_FORCE')) define('APP_URL_FORCE', krypto_env_config_value('KRYPTO_APP_URL_FORCE', 'true') === 'true');

      if(!defined('FILE_PATH')) define('FILE_PATH', krypto_env_config_value('KRYPTO_FILE_PATH', ''));

      if(!defined('MYSQL_HOST')) define('MYSQL_HOST', krypto_env_config_value('KRYPTO_DB_HOST', krypto_env_config_value('KRYPTO_TEST_DB_HOST', 'db')));
      if(!defined('MYSQL_USER')) define('MYSQL_USER', krypto_env_config_value('KRYPTO_DB_USER', krypto_env_config_value('KRYPTO_TEST_DB_USER', 'krypto')));
      if(!defined('MYSQL_PASSWD')) define('MYSQL_PASSWD', krypto_env_config_value('KRYPTO_DB_PASSWORD', krypto_env_config_value('KRYPTO_TEST_DB_PASSWORD', 'krypto')));
      if(!defined('MYSQL_PORT')) define('MYSQL_PORT', krypto_env_config_value('KRYPTO_DB_PORT', krypto_env_config_value('KRYPTO_TEST_DB_PORT', '3306')));
      if(!defined('MYSQL_DATABASE')) define('MYSQL_DATABASE', krypto_env_config_value('KRYPTO_DB_NAME', krypto_env_config_value('KRYPTO_TEST_DB_NAME', 'krypto')));

      if(!defined('CRYPTED_KEY')) define('CRYPTED_KEY', krypto_env_config_value('KRYPTO_CRYPTED_KEY', 'local-dev-only-change-me'));

      if(!defined('KRYPTO_DATA_API_KEY')) define('KRYPTO_DATA_API_KEY', krypto_env_config_value('KRYPTO_DATA_API_KEY', ''));
      if(!defined('KRYPTO_RSS2JSON_API_KEY')) define('KRYPTO_RSS2JSON_API_KEY', krypto_env_config_value('KRYPTO_RSS2JSON_API_KEY', ''));
      if(!defined('KRYPTO_ETHERSCAN_API_KEY')) define('KRYPTO_ETHERSCAN_API_KEY', krypto_env_config_value('KRYPTO_ETHERSCAN_API_KEY', ''));
    }

    // define('APP_URL', '');
    // define('APP_URL_FORCE', false);
    //
    // define('FILE_PATH', '/');
    //
    // define('MYSQL_HOST', '');  // MySQL Database host (localhost, 127.0.0.1, X.X.X.X, domain.tld)
    // define('MYSQL_USER', '');   // MySQL User (Please not use 'root', create a dedicated user with full permision user --> go doc)
    // define('MYSQL_PASSWD', ''); // MySQL Password
    // define('MYSQL_PORT', '');        // MySQL Port (Set empty for not specify port)
    // define('MYSQL_DATABASE', '');        // MySQL Database (Use the file sql.sql for create sql requirement)
    //
    // define('CRYPTED_KEY', '');
    //
    // define('KRYPTO_DATA_API_KEY', '');
    // define('KRYPTO_RSS2JSON_API_KEY', '');
    // define('KRYPTO_ETHERSCAN_API_KEY', '');

    require_once __DIR__.'/../app/src/bootstrap_paths.php';
?>
