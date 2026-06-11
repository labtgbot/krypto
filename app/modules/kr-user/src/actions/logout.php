<?php

/**
 * Logout user action
 *
 * @package Krypto
 * @author Ovrley <hello@ovrley.com>
 */

require "../../../../../config/config.settings.php";

krypto_session_start();

require_once "../../../../../app/src/bootstrap_paths.php";
require_once "../../../../../app/src/App/Csrf.php";

Krypto_Csrf::validateRequest(['methods' => ['GET', 'POST']]);

// Destroy user session
unset($_SESSION);
session_destroy();

// Redirect user
header('Location: '.APP_URL);

?>
