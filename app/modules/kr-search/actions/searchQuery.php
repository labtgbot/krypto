<?php

require "../../../../config/config.settings.php";

krypto_session_start();

require_once "../../../../app/src/bootstrap_paths.php";
require_once "../../../../app/src/App/Csrf.php";

Krypto_Csrf::validateRequest();

?>
