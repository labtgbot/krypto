<?php

/**
 * Regression coverage for issue #105.
 *
 * Module enablement must come from config.json, and module controllers/actions
 * must be exposed through an explicit allowlist instead of directory scanning.
 */

$root = dirname(__DIR__);
$tmpRoot = sys_get_temp_dir().'/krypto-module-gating-'.getmypid();

function module_gating_assert($condition, $message) {
  if(!$condition) {
    throw new Exception($message);
  }
}

function module_gating_write_file($path, $contents) {
  $directory = dirname($path);
  if(!is_dir($directory) && !mkdir($directory, 0777, true)) {
    throw new Exception('Unable to create test directory: '.$directory);
  }
  if(file_put_contents($path, $contents) === false) {
    throw new Exception('Unable to write test file: '.$path);
  }
}

function module_gating_remove_tree($path) {
  if(!is_dir($path)) return;

  $iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
  );

  foreach ($iterator as $file) {
    if($file->isDir()) rmdir($file->getPathname());
    else unlink($file->getPathname());
  }

  rmdir($path);
}

function module_gating_relative_path($root, $path) {
  return str_replace('\\', '/', substr($path, strlen($root) + 1));
}

function module_gating_collect_php_files($directory) {
  if(!is_dir($directory)) return [];

  $files = [];
  $iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
  );

  foreach ($iterator as $file) {
    if($file->isFile() && substr($file->getFilename(), -4) === '.php') {
      $files[] = module_gating_relative_path($directory, $file->getPathname());
    }
  }

  sort($files);
  return $files;
}

$policyPath = $root.'/app/src/App/module_policy.php';
module_gating_assert(file_exists($policyPath), 'Module routing policy must exist.');

$policy = require $policyPath;
module_gating_assert(is_array($policy), 'Module routing policy must return an array.');
module_gating_assert(isset($policy['controllers']) && is_array($policy['controllers']), 'Policy must define controller allowlists.');
module_gating_assert(isset($policy['actions']) && is_array($policy['actions']), 'Policy must define action allowlists.');

foreach (glob($root.'/app/modules/*', GLOB_ONLYDIR) as $modulePath) {
  $module = basename($modulePath);

  $expectedControllers = [];
  foreach (glob($modulePath.'/src/*.php') as $controllerPath) {
    $expectedControllers[] = module_gating_relative_path($modulePath, $controllerPath);
  }
  sort($expectedControllers);

  $expectedActions = [];
  foreach (['src/actions', 'actions'] as $actionDirectory) {
    foreach (module_gating_collect_php_files($modulePath.'/'.$actionDirectory) as $actionPath) {
      $expectedActions[] = $actionDirectory.'/'.$actionPath;
    }
  }
  sort($expectedActions);

  $policyControllers = (isset($policy['controllers'][$module]) ? $policy['controllers'][$module] : []);
  $policyActions = (isset($policy['actions'][$module]) ? $policy['actions'][$module] : []);
  sort($policyControllers);
  sort($policyActions);

  module_gating_assert($policyControllers === $expectedControllers, $module.' controller allowlist must match current routeable controllers.');
  module_gating_assert($policyActions === $expectedActions, $module.' action allowlist must match current routeable actions.');

  foreach (array_merge($policyControllers, $policyActions) as $relativePath) {
    module_gating_assert(is_string($relativePath), $module.' policy entries must be strings.');
    module_gating_assert($relativePath !== '' && $relativePath[0] !== '/', $module.' policy entries must be module-relative.');
    module_gating_assert(strpos($relativePath, '..') === false, $module.' policy entries must not traverse directories.');
    module_gating_assert(file_exists($modulePath.'/'.$relativePath), $module.' policy entry must exist: '.$relativePath);
    module_gating_assert(substr($relativePath, -4) === '.php', $module.' policy entry must be a PHP file: '.$relativePath);
  }
}

module_gating_remove_tree($tmpRoot);

$_SERVER['DOCUMENT_ROOT'] = '';
define('APP_URL', 'https://example.test');
define('FILE_PATH', $tmpRoot);
define('MYSQL_HOST', 'localhost');
define('MYSQL_USER', 'krypto');
define('MYSQL_PASSWD', 'krypto');
define('MYSQL_PORT', '3306');
define('MYSQL_DATABASE', 'krypto');

require_once $root.'/app/src/MySQL/MySQL.php';
require_once $root.'/app/src/App/App.php';
require_once $root.'/app/src/App/AppModule.php';

module_gating_write_file($tmpRoot.'/app/modules/kr-enabled/config.json', json_encode([
  'name' => 'Enabled module',
  'enable' => true,
  'controllers' => [
    'src/AllowedController.php'
  ],
  'actions' => [
    'src/actions/allowedAction.php',
    'src/actions/nested/allowedNestedAction.php'
  ]
], JSON_PRETTY_PRINT));
module_gating_write_file($tmpRoot.'/app/modules/kr-enabled/src/AllowedController.php', "<?php\nclass AllowedController {}\n");
module_gating_write_file($tmpRoot.'/app/modules/kr-enabled/src/AutoLoadedController.php', "<?php\nclass AutoLoadedController {}\n");
module_gating_write_file($tmpRoot.'/app/modules/kr-enabled/src/actions/allowedAction.php', "<?php\n");
module_gating_write_file($tmpRoot.'/app/modules/kr-enabled/src/actions/nested/allowedNestedAction.php', "<?php\n");
module_gating_write_file($tmpRoot.'/app/modules/kr-enabled/src/actions/blockedAction.php', "<?php\n");

module_gating_write_file($tmpRoot.'/app/modules/kr-disabled/config.json', json_encode([
  'name' => 'Disabled module',
  'enable' => false,
  'controllers' => [
    'src/DisabledController.php'
  ],
  'actions' => [
    'src/actions/disabledAction.php'
  ]
], JSON_PRETTY_PRINT));
module_gating_write_file($tmpRoot.'/app/modules/kr-disabled/src/DisabledController.php', "<?php\nclass DisabledController {}\n");
module_gating_write_file($tmpRoot.'/app/modules/kr-disabled/src/actions/disabledAction.php', "<?php\n");

try {
  $enabled = new AppModule('kr-enabled');
  module_gating_assert($enabled->_checkConfig(), 'Enabled module config should be valid.');
  module_gating_assert($enabled->_isEnable(), 'Enabled module should read enable=true from config.json.');
  module_gating_assert(
    $enabled->_loadControllers() === ['src/AllowedController.php'],
    'Controller loading must use the explicit allowlist and skip unlisted files.'
  );
  module_gating_assert(
    $enabled->_loadActions() === ['src/actions/allowedAction.php', 'src/actions/nested/allowedNestedAction.php'],
    'Action loading must use the explicit allowlist.'
  );
  module_gating_assert(
    $enabled->_isActionAllowed($tmpRoot.'/app/modules/kr-enabled/src/actions/allowedAction.php'),
    'Allowlisted action should be routeable.'
  );
  module_gating_assert(
    !$enabled->_isActionAllowed($tmpRoot.'/app/modules/kr-enabled/src/actions/blockedAction.php'),
    'Unlisted action in an enabled module must not be routeable.'
  );

  $appReflection = new ReflectionClass('App');
  $app = $appReflection->newInstanceWithoutConstructor();
  $actionAllowlist = $appReflection->getProperty('moduleActionAllowlist');
  $actionAllowlist->setAccessible(true);
  $actionAllowlist->setValue($app, [
    realpath($tmpRoot.'/app/modules/kr-enabled/src/actions/allowedAction.php') => [
      'module' => 'kr-enabled',
      'action' => 'src/actions/allowedAction.php'
    ]
  ]);

  module_gating_assert(
    $app->_isModuleActionRequest($tmpRoot.'/app/modules/kr-enabled/src/actions/allowedAction.php'),
    'App should recognize direct module action requests.'
  );
  module_gating_assert(
    !$app->_isModuleActionRequest($tmpRoot.'/app/modules/kr-enabled/src/AllowedController.php'),
    'App should not treat module controllers as direct action requests.'
  );
  module_gating_assert(
    $app->_isModuleActionAllowed($tmpRoot.'/app/modules/kr-enabled/src/actions/allowedAction.php'),
    'App should allow direct requests only when action is registered.'
  );
  module_gating_assert(
    !$app->_isModuleActionAllowed($tmpRoot.'/app/modules/kr-enabled/src/actions/blockedAction.php'),
    'App should deny direct requests for unregistered actions.'
  );

  $disabled = new AppModule('kr-disabled');
  module_gating_assert(!$disabled->_isEnable(), 'Disabled module should read enable=false from config.json.');
  module_gating_assert($disabled->_loadControllers() === [], 'Disabled module controllers must not load.');
  module_gating_assert($disabled->_loadActions() === [], 'Disabled module actions must not be routeable.');
  module_gating_assert(
    !$disabled->_isActionAllowed($tmpRoot.'/app/modules/kr-disabled/src/actions/disabledAction.php'),
    'Actions from disabled modules must be denied.'
  );
} finally {
  module_gating_remove_tree($tmpRoot);
}

echo "Module gating regression checks passed.\n";

?>
