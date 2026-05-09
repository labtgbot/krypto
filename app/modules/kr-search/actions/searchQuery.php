<?php

session_start();

require "../../../../config/config.settings.php";

require_once "../../../../app/src/bootstrap_paths.php";
require_once "../../../../app/src/App/Csrf.php";

Krypto_Csrf::validateRequest();

?>
