<?php

require_once "../app/src/bootstrap_paths.php";
require_once "../app/src/App/Csrf.php";

krypto_session_start();

require("app/src/Install.php");

require("../app/src/Lang/Lang.php");

$Install = new Install();

if($Install->_isInstalled()){
  http_response_code(403);
  ?>
  <!DOCTYPE html>
  <html>
    <head>
      <meta charset="utf-8">
      <title>Krypto installer locked</title>
      <link href="https://fonts.googleapis.com/css?family=Roboto+Mono:300,500|Roboto:300,400,500,700" rel="stylesheet">
      <link rel="stylesheet" href="../assets/bower/animate.css/animate.min.css">
      <link rel="stylesheet" href="assets/css/install.css">
    </head>
    <body>
      <form class="kr-finish" method="post">
        <header>
          <img src="../assets/img/logo_black.svg" alt="">
        </header>
        <section>
          <h3>Installer locked</h3>
          <section class="kr-msg kr-msg-warning" style="display:block;">
            <?php echo htmlspecialchars($Install->_installedLockMessage(), ENT_QUOTES, 'UTF-8'); ?>
          </section>
        </section>
      </form>
    </body>
  </html>
  <?php
  exit;
}

if(!empty($_POST)){
  $resultPost = $Install->_post($_GET['s']);
  if($Install->_getForward() != null && $resultPost == true) header('Location: '.$Install->_getForward());
}

?>
<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8">
    <title></title>
    <link href="https://fonts.googleapis.com/css?family=Roboto+Mono:300,500|Roboto:300,400,500,700" rel="stylesheet">
    <script src="https://cdn.linearicons.com/free/1.0.0/svgembedder.min.js"></script>

    <link rel="stylesheet" href="../assets/bower/animate.css/animate.min.css">

    <link rel="stylesheet" href="assets/css/install.css">
    <script src="../assets/bower/jquery/dist/jquery.min.js" charset="utf-8"></script>
  </head>
  <body>
    <form class="kr-<?php echo $Install->_getStates(); ?>" action="index.php?s=<?php echo $Install->_getStates(); ?>" method="post">
      <?php echo Krypto_Csrf::input(); ?>
      <input type="hidden" name="states" value="<?php echo $Install->_getStates(); ?>">
      <header>
        <img src="../assets/img/logo_black.svg" alt="">
      </header>
      <?php $Install->_loadPage(); ?>
    </form>
    <footer>
      <a href="http://community.ovrley.com/category/7/guides" target="_blank">Need help for installation ?</a>
    </footer>
  </body>
  <script src="../assets/bower/jquery/dist/jquery.min.js" charset="utf-8"></script>
  <script src="assets/js/install.js" charset="utf-8"></script>
</html>
