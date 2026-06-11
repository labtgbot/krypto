<?php

/**
 * Reset password action
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
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/CryptoApi/CryptoApi.php";

try {

    // Load app modules
    $App = new App(true);
    $App->_loadModulesControllers();

Krypto_Csrf::validateRequest();

    // Init lang object
    $Lang = new Lang(null, $App);

    // Check post
    if (empty($_POST)) {
        throw new Exception("Format error", 1);
    }

    // Init user object
    $User = new User();

    // Check new password change require or email need to be send
    if (!empty($_POST) && isset($_POST['kr_usr_pwdr_token'])) { // Password change
        if (empty($_POST['kr_usr_pwdr']) || empty($_POST['kr_usr_pwdr_rep'])) {
          die(json_encode([
            'error' => 2,
            'msg' => 'Password is empty'
          ]));
        }

        // Check reset token
        if (!$User->_parseToken($App, $_POST['kr_usr_pwdr_token'])) {
            throw new Exception("Error : Wrong token", 1);
        }

        // Check password match
        if ($_POST['kr_usr_pwdr'] != $_POST['kr_usr_pwdr_rep']) {
            die(json_encode([
              'error' => 2,
              'msg' => 'Password not matching'
            ]));
        }

        // Change user password
        $User->_validResetPassword($_POST['kr_usr_pwdr_token'], $App, $_POST['kr_usr_pwdr']);

        die(json_encode([
          'error' => 0,
          'msg' => 'Done !'
        ]));

    } else { // Send email

        // Check post
        if (empty($_POST) || empty($_POST['kr_usr_email'])) {
          die(json_encode([
            'error' => 2,
            'msg' => 'Please enter an email'
          ]));
        }

        // Check email
        if (!filter_var($_POST['kr_usr_email'], FILTER_VALIDATE_EMAIL)) {
          die(json_encode([
            'error' => 2,
            'msg' => 'Email not valid'
          ]));
        }

        $authBucket = 'reset_password';
        $authLimiter = $App->_getAuthRateLimiter();
        $authConfig = $App->_getAuthRateLimitConfig($authBucket);
        $authCaptchaEnabled = KryptoAuthRateLimit::captchaEnabled($App);
        $authDecision = KryptoAuthRateLimit::check($authBucket, $_POST['kr_usr_email'], $_SERVER, $authLimiter, $authConfig);

        if(!$authDecision['allowed']){
          die(json_encode(KryptoAuthRateLimit::failurePayload($authDecision, KryptoAuthRateLimit::RATE_LIMIT_MESSAGE, $authCaptchaEnabled)));
        }

        if(KryptoAuthRateLimit::captchaRequired($authDecision, $App) && !KryptoAuthRateLimit::verifyCaptcha($App, $_POST, $_SERVER)){
          $authDecision = KryptoAuthRateLimit::recordFailure($authBucket, $_POST['kr_usr_email'], $_SERVER, $authLimiter, $authConfig);
          die(json_encode(KryptoAuthRateLimit::failurePayload($authDecision, KryptoAuthRateLimit::RATE_LIMIT_MESSAGE, $authCaptchaEnabled)));
        }

        // Reset user password (send mail)
        $User->_resetPassword($_POST['kr_usr_email'], $App);
        $authDecision = KryptoAuthRateLimit::recordFailure($authBucket, $_POST['kr_usr_email'], $_SERVER, $authLimiter, $authConfig);

        echo json_encode(KryptoAuthRateLimit::resetPasswordPayload($authDecision, $authCaptchaEnabled));
    }
} catch (Exception $e) {
    die(json_encode([
    'error' => 1,
    'msg' => $e->getMessage()
  ]));
}
