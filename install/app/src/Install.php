<?php

class Install {

  private $states = ["welcome", "languages", "server_check", "bdd", "configure", "admin", "loadcron", "finish"];
  private $appRoot;
  private $installRoot;
  private $configPath;

  public function __construct($appRoot = null){
    $this->appRoot = $this->normalizePath(is_null($appRoot) ? dirname(__DIR__, 3) : $appRoot);
    $this->installRoot = $this->appRoot.'/install';
    $this->configPath = $this->appRoot.'/config/config.settings.php';
  }

  public function _getStates(){
    if(empty($_GET['s']) || !in_array($_GET['s'], $this->states)) return $this->states[0];
    return $_GET['s'];
  }

  public function _loadPage(){
    require($this->installRoot."/app/views/".$this->_getStates().".php");
  }

  public function _getBack(){
    $pos = array_search($this->_getStates(), $this->states);
    if($pos == 0) return null;
    return "?s=".$this->states[$pos - 1];
  }

  public function _getForward(){
    $pos = array_search($this->_getStates(), $this->states);
    if($pos == count($this->states) - 1) return null;
    return "?s=".$this->states[$pos + 1];
  }

  public function _getRefresh(){
    return "?s=".$this->_getStates();
  }

  public function _getServerRequirement(){

    return [
      "php_version" => [
        "title" => "PHP Version",
        "description" => "Need to be 7.4.0 or more",
        "valid" => version_compare(PHP_VERSION, '7.4.0') >= 0
      ],
      "curl_extension" => [
        "title" => "CURL Available",
        "description" => "CURL extension need to be enabled",
        "valid" => function_exists('curl_version')
      ],
      "pdo_available" => [
        "title" => "PDO Available",
        "description" => "PDO need to be enabled",
        "valid" => defined('PDO::ATTR_DRIVER_NAME')
      ],
      "openssl_extension" => [
        "title" => "OpenSSL Available",
        "description" => "OpenSSL need to be enabled",
        "valid" => extension_loaded('openssl') || function_exists('openssl_encrypt')
      ],
      "config_file" => [
        "title" => "Config file writable",
        "description" => "Config file (/config/config.settings.php) writable",
        "valid" => is_writable($this->configPath) || (!file_exists($this->configPath) && is_writable(dirname($this->configPath)))
      ],
      "public_dir" => [
        "title" => "Public directory writable",
        "description" => "Public directoruy (public) writable",
        "valid" => is_writable('../public')
      ],
      "allow_url_fopen" => [
        "title" => "Allow url fopen",
        "description" => "You can follow the guide here : <a target=_bank href='http://community.ovrley.com/topic/41/enable-allow-url-fopen'>Allow url fopen guide</a>",
        "valid" => ini_get('allow_url_fopen')
      ]
    ];

  }

  public function _getListPageCalled(){

    return [
      'app/src/App/actions/cronCleanCache.php' => 'Clear cache',
      'app/src/CryptoApi/actions/SyncExchanges.php' => 'Exchanges sync',
      'app/modules/kr-changenow/src/actions/syncMarketData.php' => 'ChangeNOW market data sync',
      'app/src/CryptoApi/actions/SyncCoin.php' => 'Coins sync',
      'app/modules/kr-search/src/actions/searchQuery.php?request=LT' => 'Load search engine'
    ];

  }

  public function _post($state){
    if($this->_isInstalled()) return $this->_installedLockMessage();
    if(empty($_POST)) return true;
    $this->validateCsrf();
    $_SESSION[$state] = $_POST;
    if($state == "bdd") return $this->_generateBDD();
    if($state == "admin") return $this->_createAdmin();
    return true;
  }

  public function _generateBDD(){

    try {
      $sqlPort = !empty($_SESSION['bdd']['sql_port']) ? $_SESSION['bdd']['sql_port'] : '3306';
      $bdd = new PDO('mysql:host='.$_SESSION['bdd']['sql_host'].';port='.$sqlPort.';dbname='.$_SESSION['bdd']['sql_database_name'], $_SESSION['bdd']['sql_user'], $_SESSION['bdd']['sql_password'], array(\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4'));

      $sqlStructure = file_get_contents($this->installRoot.'/assets/sql/krypto.sql');

      $status = $bdd->exec($sqlStructure);

      if($status === false) throw new Exception("Error : Fail to create database structure", 1);

      return true;
    } catch (\Exception $e) {
      return $e->getMessage();
    }

  }

  private function generateScretkey() {
      return bin2hex(random_bytes(32));
  }

  public function _getConfigureContent(){
    $websitepath = str_replace(['install/app/src', $_SERVER['DOCUMENT_ROOT']], ['', ''], dirname(__FILE__));
    if($websitepath != "/") $websitepath = substr($websitepath, 0, -1);
    return [
      'website_url' => [
        'title' => 'Website url',
        'precontent' => str_replace('/install/index.php?s=configure', '', sprintf( "%s://%s%s", isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http', $_SERVER['SERVER_NAME'], $_SERVER['REQUEST_URI'] )),
        'disabled' => false,
        'require' => true
      ],
      'website_path' => [
        'title' => 'Website path',
        'precontent' => $websitepath,
        'disabled' => false,
        'require' => false
      ]
    ];

  }

  public function _getLoginFields(){
    return [
      'admin_name' => [
        'title' => 'Name',
        'placeholder' => 'John Miller',
        'disabled' => false
      ],
      'admin_email' => [
        'title' => 'Email',
        'placeholder' => 'admin@domain.tld',
        'disabled' => false
      ],
      'admin_password' => [
        'title' => 'Password',
        'placeholder' => 'Your password',
        'disabled' => false
      ]
    ];
  }

  public function _createAdmin(){
    $sqlPort = !empty($_SESSION['bdd']['sql_port']) ? $_SESSION['bdd']['sql_port'] : '3306';
    $bdd = new PDO('mysql:host='.$_SESSION['bdd']['sql_host'].';port='.$sqlPort.';dbname='.$_SESSION['bdd']['sql_database_name'], $_SESSION['bdd']['sql_user'], $_SESSION['bdd']['sql_password'], array(\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4'));
    $req = $bdd->prepare('INSERT INTO user_krypto (email_user, name_user, password_user, created_date_user, admin_user)
                            VALUES (:email_user, :name_user, :password_user, :created_date_user, :admin_user)');

    $status = $req->execute([
          'email_user' => $_SESSION['admin']['admin_email'],
          'name_user' => $_SESSION['admin']['admin_name'],
          'password_user' => password_hash($_SESSION['admin']['admin_password'], PASSWORD_DEFAULT),
          'created_date_user' => time(),
          'admin_user' => 1
        ]);
    $req->closeCursor();

    $this->_saveSettings();

    return $status;
  }

  public function _saveSettings(){

    $sqlPort = !empty($_SESSION['bdd']['sql_port']) ? $_SESSION['bdd']['sql_port'] : '3306';
    $bdd = new PDO('mysql:host='.$_SESSION['bdd']['sql_host'].';port='.$sqlPort.';dbname='.$_SESSION['bdd']['sql_database_name'], $_SESSION['bdd']['sql_user'], $_SESSION['bdd']['sql_password'], array(\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4'));
    $req = $bdd->prepare('UPDATE settings_krypto SET value_settings=:value_settings WHERE key_settings=:key_settings');

    $status = $req->execute([
      'value_settings' => $_SESSION['languages']['language_select'],
      'key_settings' => 'default_language'
    ]);

    $req->closeCursor();

    $fileConfig = "<?php
	    define('KRYPTO_INSTALLED', true);

	    define('APP_URL', '".addslashes($_SESSION['configure']['website_url'])."');
	    define('APP_URL_FORCE', false);

    define('FILE_PATH', '".addslashes($_SESSION['configure']['website_path'])."');

    define('MYSQL_HOST', '".addslashes($_SESSION['bdd']['sql_host'])."');  // MySQL Database host (localhost, 127.0.0.1, X.X.X.X, domain.tld)
    define('MYSQL_USER', '".addslashes($_SESSION['bdd']['sql_user'])."');   // MySQL User (Please not use 'root', create a dedicated user with full permision user --> go doc)
    define('MYSQL_PASSWD', '".addslashes($_SESSION['bdd']['sql_password'])."'); // MySQL Password
    define('MYSQL_PORT', '".addslashes(!empty($_SESSION['bdd']['sql_port']) ? $_SESSION['bdd']['sql_port'] : '3306')."');        // MySQL Port (Set empty for not specify port)
    define('MYSQL_DATABASE', '".addslashes($_SESSION['bdd']['sql_database_name'])."');        // MySQL Database (Use the file sql.sql for create sql requirement)

    define('CRYPTED_KEY', '".$this->generateScretkey()."');

    require_once __DIR__.'/../app/src/bootstrap_paths.php';
	?>";

    file_put_contents($this->configPath, $fileConfig);

  }

  public function _isInstalled(){
    if(!is_file($this->configPath)) return false;

    $source = file_get_contents($this->configPath);
    if($source === false || trim($source) === '') return false;

    $source = $this->stripPhpComments($source);
    if($this->configDefinesInstalledFlag($source)) return true;

    foreach (['APP_URL', 'MYSQL_HOST', 'MYSQL_USER', 'MYSQL_DATABASE', 'CRYPTED_KEY'] as $requiredDefine) {
      if(!$this->configDefinesNonEmptyLiteral($source, $requiredDefine)) return false;
    }

    return true;
  }

  public function _installedLockMessage(){
    return 'Krypto is already installed. Remove the install directory instead of running the installer again.';
  }

  private function validateCsrf(){
    if(!class_exists('Krypto_Csrf')){
      require_once $this->appRoot.'/app/src/App/Csrf.php';
    }

    Krypto_Csrf::validateRequest();
  }

  private function normalizePath($path){
    $path = rtrim((string) $path, "/\\");
    return ($path === '' ? DIRECTORY_SEPARATOR : $path);
  }

  private function stripPhpComments($source){
    $tokens = token_get_all($source);
    $stripped = '';

    foreach ($tokens as $token) {
      if(is_array($token)){
        if($token[0] == T_COMMENT || $token[0] == T_DOC_COMMENT) continue;
        $stripped .= $token[1];
        continue;
      }

      $stripped .= $token;
    }

    return $stripped;
  }

  private function configDefinesInstalledFlag($source){
    return preg_match('/define\s*\(\s*([\'"])KRYPTO_INSTALLED\1\s*,\s*true\s*\)\s*;/i', $source) === 1;
  }

  private function configDefinesNonEmptyLiteral($source, $name){
    $name = preg_quote($name, '/');
    if(preg_match('/define\s*\(\s*([\'"])'.$name.'\1\s*,\s*([\'"])(.*?)\2\s*\)\s*;/s', $source, $matches) !== 1) return false;
    return trim(stripslashes($matches[3])) !== '';
  }

}

?>
