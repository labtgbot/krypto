<?php

/**
 * Remove PushBullet action
 *
 * @package Krypto
 * @author Ovrley <hello@ovrley.com>
 */

require "../../../../../config/config.settings.php";

krypto_session_start();

require_once "../../../../../app/src/bootstrap_paths.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/vendor/autoload.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/MySQL/MySQL.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/App/App.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/App/AppModule.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/User/User.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/Lang/Lang.php";

try {

    // Load app modules
    $App = new App(true);
    $App->_loadModulesControllers();

Krypto_Csrf::validateRequest();

    // Check if user is logged
    $User = new User();
    if (!$User->_isLogged()) {
        throw new Exception("User not logged", 1);
    }

    // Init lang object
    $Lang = new Lang($User->_getLang(), $App);

    if(empty($_POST) || !isset($_POST['kr-user-id-c']) || empty($_POST['kr-user-id-c']) || $_POST['kr-user-id-c'] != $User->_getUserID(true)) throw new Exception("Access denied", 1);

    $User->_confirmGoogleTFSDisable(
      (isset($_POST['google_tfs_code']) ? $_POST['google_tfs_code'] : null),
      (isset($_POST['kr-user-current-pwd']) ? $_POST['kr-user-current-pwd'] : null)
    );

    $User->_disableGoogleTFS();
    $User->_sendAccountSecurityNotification($App, $User->_getEmail(), 'Google Authenticator was removed from your account.');

    die(json_encode([
      'error' => 0,
      'msg' => 'Done !'
    ]));

} catch (Exception $e) {
    die(json_encode([
    'error' => 1,
    'msg' => $e->getMessage()
  ]));
}
