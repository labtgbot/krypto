<?php

/**
 * Module class
 *
 * @package Krypto
 * @author Ovrley <hello@ovrley.com>
 */
class AppModule {

  /**
   * Module directory
   * @var String
   */
  private $moduleDirectory = null;

  /**
   * Module configuration
   * @var Array
   */
  private $moduleConfig = null;

  /**
   * Module controller/action policy
   * @var Array
   */
  private static $modulePolicy = null;

  /**
   * Module constructor
   * @param String $moduleDirectory Module directory
   */
  public function __construct($moduleDirectory = null){

    if(is_null($moduleDirectory)) throw new Exception("Error : Fail to load module (Directory is null)", 1);
    $this->moduleDirectory = $moduleDirectory;

    // Load module
    $this->_loadModule();
  }

  /**
   * Get module directory
   * @return String Module directory
   */
  public function _getDirectory(){
    if(is_null($this->moduleDirectory)) throw new Exception("Error : Module directory is empty", 1);
    return $this->moduleDirectory;
  }

  /**
   * Get module URL
   * @return String Module URL
   */
  public function _getModuleURL(){ return APP_URL.'/app/modules/'.$this->_getDirectory(); }

  /**
   * Get module PATH
   * @return String Module PATH
   */
  public function _getModulePath(){ return $_SERVER['DOCUMENT_ROOT'].FILE_PATH.'/app/modules/'.$this->_getDirectory(); }

  /**
   * Load module
   */
  private function _loadModule(){

    // Load module configuration file
    if(!file_exists($this->_getModulePath().'/config.json')) throw new Exception("Error : Config file not exist for module : ".$this->_getDirectory(), 1);

    // Parse module configuration file from JSON
    $this->moduleConfig = json_decode(file_get_contents($this->_getModulePath().'/config.json'), true);

    if(!is_array($this->moduleConfig)) throw new Exception("Error : Fail to open config file for module : ".$this->_getDirectory(), 1);

  }

  /**
   * Check if module was enabled
   * @return boolean Module enable status
   */
  public function _isEnable(){
    if(!$this->_checkConfig()) return false;
    return $this->moduleConfig['enable'] === true;
  }

  /**
   * Check module configuration file
   * @return Boolean Module configuration file was correct
   */
  public function _checkConfig(){
    if(!is_array($this->moduleConfig)) return false;
    if(!array_key_exists('enable', $this->moduleConfig)) return false;
    return is_bool($this->moduleConfig['enable']);
  }

  /**
   * Load the explicit module route policy.
   * @return Array Module policy
   */
  private static function _getModulePolicy(){
    if(!is_null(self::$modulePolicy)) return self::$modulePolicy;

    $policyPath = __DIR__.'/module_policy.php';
    if(file_exists($policyPath)){
      $policy = require $policyPath;
      self::$modulePolicy = (is_array($policy) ? $policy : []);
    } else {
      self::$modulePolicy = [];
    }

    foreach (['controllers', 'actions'] as $key) {
      if(!array_key_exists($key, self::$modulePolicy) || !is_array(self::$modulePolicy[$key])){
        self::$modulePolicy[$key] = [];
      }
    }

    return self::$modulePolicy;
  }

  /**
   * Get the configured allowlist for the module.
   * @param  String $key Policy key
   * @return Array      Allowlist entries
   */
  private function _getAllowlist($key){
    if(is_array($this->moduleConfig)
      && array_key_exists($key, $this->moduleConfig)
      && is_array($this->moduleConfig[$key])){
      return $this->moduleConfig[$key];
    }

    $policy = self::_getModulePolicy();
    if(array_key_exists($key, $policy)
      && array_key_exists($this->_getDirectory(), $policy[$key])
      && is_array($policy[$key][$this->_getDirectory()])){
      return $policy[$key][$this->_getDirectory()];
    }

    return [];
  }

  /**
   * Normalize a module-relative allowlist path.
   * @param  String $path Relative path
   * @return String|null  Normalized relative path
   */
  private function _normalizeAllowlistPath($path){
    if(!is_string($path)) return null;

    $path = trim(str_replace('\\', '/', $path));
    if($path == '' || $path[0] == '/' || strpos($path, "\0") !== false) return null;

    $segments = explode('/', $path);
    $normalized = [];
    foreach ($segments as $segment) {
      if($segment == '' || $segment == '.' || $segment == '..') return null;
      $normalized[] = $segment;
    }

    return implode('/', $normalized);
  }

  /**
   * Check if an allowlisted file exists inside the current module.
   * @param  String $relativePath Module-relative path
   * @return Boolean              File is inside module
   */
  private function _allowlistedFileExists($relativePath){
    $moduleRoot = realpath($this->_getModulePath());
    $filePath = realpath($this->_getModulePath().'/'.$relativePath);
    if($moduleRoot === false || $filePath === false || !is_file($filePath)) return false;

    $moduleRoot = rtrim($moduleRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
    return strpos($filePath, $moduleRoot) === 0;
  }

  /**
   * Load module assets
   * @param  String $type Assets type (css, js)
   * @return Array        Assets List
   */
  public function _loadAssets($type = "css"){
    if(!$this->_isEnable()) return [];

    $res = [];
    // Check if directory exist
    if(!file_exists($this->_getModulePath().'/statics/'.$type)) return [];

    // Get list assets files
    foreach (scandir($this->_getModulePath().'/statics/'.$type) as $asset) {

      // Check validity assets & is not directory
      if($asset == "." || $asset == ".." || is_dir($this->_getModulePath().'/assets/'.$type.'/'.$asset)) continue; // Check file validity

      // If assets need is CSS
      if($type == "css"){
        $res[] = '<link rel="stylesheet" href="'.$this->_getModuleURL().'/statics/'.$type.'/'.$asset.'?v='.App::_getVersion().'">'; // Load css stylesheet
      }
      if($type == "js"){ // If assets need is JS
        $res[] = '<script src="'.$this->_getModuleURL().'/statics/'.$type.'/'.$asset.'?v='.App::_getVersion().'" charset="utf-8"></script>'; // Load JS script
      }
    }
    return $res;
  }

  /**
   * Load module controllers list
   * @return Array Controllers list
   */
  public function _loadControllers(){
    $res = [];

    if(!$this->_isEnable()) return [];

    // Get explicit controllers allowlist
    foreach ($this->_getAllowlist('controllers') as $controller) {
      $controller = $this->_normalizeAllowlistPath($controller);
      if(is_null($controller)) continue;
      if(preg_match('/^src\/[^\/]+\.php$/', $controller) !== 1) continue;
      if(!$this->_allowlistedFileExists($controller)) continue;

      $res[] = $controller;
    }

    return array_values(array_unique($res));
  }

  /**
   * Load module actions list
   * @return Array Actions list
   */
  public function _loadActions(){
    $res = [];

    if(!$this->_isEnable()) return [];

    foreach ($this->_getAllowlist('actions') as $action) {
      $action = $this->_normalizeAllowlistPath($action);
      if(is_null($action)) continue;
      if(preg_match('/^(src\/actions|actions)\/.+\.php$/', $action) !== 1) continue;
      if(!$this->_allowlistedFileExists($action)) continue;

      $res[] = $action;
    }

    return array_values(array_unique($res));
  }

  /**
   * Check if an action file is routeable for this module.
   * @param  String $actionPath Action path
   * @return Boolean            Action is allowlisted
   */
  public function _isActionAllowed($actionPath){
    if(!$this->_isEnable()) return false;

    $actionRealPath = realpath($actionPath);
    if($actionRealPath === false) return false;

    foreach ($this->_loadActions() as $action) {
      $allowedPath = realpath($this->_getModulePath().'/'.$action);
      if($allowedPath !== false && $allowedPath === $actionRealPath) return true;
    }

    return false;
  }

}

?>
