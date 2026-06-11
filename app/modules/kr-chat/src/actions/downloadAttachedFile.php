<?php

/**
 * Download attached file
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

try {


    // Load app modules
    $App = new App(true);
    $App->_loadModulesControllers();

Krypto_Csrf::validateRequest();

    // Check if user is logged
    $User = new User();
    if (!$User->_isLogged()) {
        throw new Exception("User is not logged", 1);
    }

    if(empty($_GET) || !isset($_GET['p']) || empty($_GET['p'])) throw new Exception("Permission denied", 1);

    $file_url = App::encrypt_decrypt('decrypt', $_GET['p']);
    if(strlen($file_url) == 0) throw new Exception("Permission denied", 1);

    $downloadFile = ChatRoom::_resolveAttachedFile($file_url);
    $ChatRoom = new ChatRoom($downloadFile['room_id'], $User);
    $ChatRoom->_requireUserAccess($User);

    $file_name = explode('-', $downloadFile['filename']);
    header("Content-Transfer-Encoding: Binary");
    header("Content-Length: ".filesize($downloadFile['path']));
    header("Content-disposition: attachment; filename=\"" . join('-', array_slice($file_name, 1)) . "\"");
    readfile($downloadFile['path']);

} catch (\Exception $e) {
  header('Location: '.APP_URL);
}
