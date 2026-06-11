<?php

/**
 * Login user action
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

    // Check if user is already logged
    $User = new User();
    if ($User->_isLogged()) {
      // Redirect user
      die(json_encode([
        'error' => 0,
        'href' => APP_URL.'/dashboard'.($App->_rewriteDashBoardName() ? '' : '.php')
      ]));
    }

    // Init lang object
    $Lang = new Lang(null, $App);


    $tfsCode = null;
    if(isset($_POST['kr_login_code'])) $tfsCode = $_POST['kr_login_code'];

    if(!is_null($tfsCode)){
      $_POST['kr_usr_email'] = App::encrypt_decrypt('decrypt', $_POST['kr_usr_email']);
      $_POST['kr_usr_pwd'] = App::encrypt_decrypt('decrypt', $_POST['kr_usr_pwd']);
    }

    // Check post given
    if (empty($_POST) || (empty($_POST['kr_usr_email']) && empty($_POST['kr_usr_pwd']))) {
        die(json_encode(['error' => 2, 'fields' => ['kr_usr_email' => '', 'kr_usr_pwd' => '']]));
    } elseif (empty($_POST['kr_usr_email'])) {
        die(json_encode(['error' => 2, 'fields' => ['kr_usr_email' => '']]));
    } elseif (empty($_POST['kr_usr_pwd'])) {
        die(json_encode(['error' => 2, 'fields' => ['kr_usr_pwd' => '']]));
    }

    // Check email format
    if (!filter_var($_POST['kr_usr_email'], FILTER_VALIDATE_EMAIL)) {
        die(json_encode(['error' => 2, 'fields' => ['kr_usr_email' => $Lang->tr('Email not valid')]]));
    }



    // Login user
    $authBucket = (!is_null($tfsCode) ? 'totp' : 'login');
    $authLimiter = $App->_getAuthRateLimiter();
    $authConfig = $App->_getAuthRateLimitConfig($authBucket);
    $authCaptchaEnabled = KryptoAuthRateLimit::captchaEnabled($App);
    $authDecision = KryptoAuthRateLimit::check($authBucket, $_POST['kr_usr_email'], $_SERVER, $authLimiter, $authConfig);

    if(!$authDecision['allowed']){
      $payload = KryptoAuthRateLimit::failurePayload($authDecision, KryptoAuthRateLimit::RATE_LIMIT_MESSAGE, $authCaptchaEnabled);
      if($authBucket == 'totp') $payload['error'] = 4;
      die(json_encode($payload));
    }

    if(KryptoAuthRateLimit::captchaRequired($authDecision, $App) && !KryptoAuthRateLimit::verifyCaptcha($App, $_POST, $_SERVER)){
      $authDecision = KryptoAuthRateLimit::recordFailure($authBucket, $_POST['kr_usr_email'], $_SERVER, $authLimiter, $authConfig);
      $payload = KryptoAuthRateLimit::failurePayload($authDecision, KryptoAuthRateLimit::RATE_LIMIT_MESSAGE, $authCaptchaEnabled);
      if($authBucket == 'totp') $payload['error'] = 4;
      die(json_encode($payload));
    }

    try {
      $loginResult = $User->_login($_POST['kr_usr_email'], $_POST['kr_usr_pwd'], 'standard', $tfsCode);
    } catch (Exception $loginException) {
      $authDecision = KryptoAuthRateLimit::recordFailure($authBucket, $_POST['kr_usr_email'], $_SERVER, $authLimiter, $authConfig);
      $payload = KryptoAuthRateLimit::failurePayload($authDecision, KryptoAuthRateLimit::GENERIC_AUTH_MESSAGE, $authCaptchaEnabled);
      if($authBucket == 'totp') $payload['error'] = 4;
      die(json_encode($payload));
    }

    if ($loginResult == 1) {
        KryptoAuthRateLimit::recordSuccess($authBucket, $_POST['kr_usr_email'], $_SERVER, $authLimiter, $authConfig);
        // Ok --> redirect user
        die(json_encode(['error' => 0, 'href' => APP_URL.'/dashboard'.($App->_rewriteDashBoardName() ? '' : '.php')]));
    } else if($loginResult == 2){
      KryptoAuthRateLimit::recordSuccess('login', $_POST['kr_usr_email'], $_SERVER, $authLimiter, $App->_getAuthRateLimitConfig('login'));
      die(json_encode(['error' => 3, 'user' => App::encrypt_decrypt('encrypt', $_POST['kr_usr_email']), 'pwd' => App::encrypt_decrypt('encrypt', $_POST['kr_usr_pwd'])]));
    } else if($loginResult == 4){
      $authDecision = KryptoAuthRateLimit::recordFailure('totp', $_POST['kr_usr_email'], $_SERVER, $authLimiter, $App->_getAuthRateLimitConfig('totp'));
      $payload = KryptoAuthRateLimit::failurePayload($authDecision, KryptoAuthRateLimit::GENERIC_AUTH_MESSAGE, $authCaptchaEnabled);
      $payload['error'] = 4;
      die(json_encode($payload));
    }

    throw new Exception("Error : Login fail", 1);


} catch (Exception $e) {
    die(json_encode([
    'error' => 1,
    'msg' => $e->getMessage()
  ]));
}
