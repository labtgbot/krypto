<?php

/**
 * Save ChangeNOW admin panel settings.
 *
 * @package Krypto
 */

session_start();
require "../../../../../config/config.settings.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/vendor/autoload.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/MySQL/MySQL.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/App/App.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/App/AppModule.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/User/User.php";

header('Content-Type: application/json');

$App = new App(true);
$App->_loadModulesControllers();

try {
  $User = new User();
  if(!$User->_isLogged()) throw new Exception("Your are not logged", 1);
  if(!$User->_isAdmin()) throw new Exception("Error : Permission denied", 1);
  if($App->_isDemoMode()) throw new Exception("App currently in demo mode", 1);
  if(empty($_POST)) throw new Exception("Error : Args not valid", 1);

  $App->_saveChangeNowSettings($_POST);

  die(json_encode([
    'error' => 0,
    'msg' => 'Done',
    'title' => 'Success'
  ]));
} catch (Exception $e) {
  die(json_encode([
    'error' => 1,
    'msg' => $e->getMessage()
  ]));
}

?>
