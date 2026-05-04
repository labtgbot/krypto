<?php

$root = dirname(__DIR__);
$previewAppUrl = getenv('PREVIEW_APP_URL') ?: 'file://'.$root;
define('APP_URL', rtrim($previewAppUrl, '/'));

function render_swap_panel($context) {
  global $root;
  $changeNowSwapContext = $context;
  ob_start();
  require $root.'/app/views/changenow/swap_panel.php';
  return ob_get_clean();
}

$css = '';
foreach ([
  '/assets/css/style.css',
  '/assets/css/login.css',
  '/app/modules/kr-changenow/statics/css/swap.css'
] as $file) {
  $css .= file_get_contents($root.$file)."\n";
}

$publicPanel = render_swap_panel('public');
$logo = APP_URL.'/assets/img/logo_black.svg';
$jqueryScript = APP_URL.'/assets/bower/jquery/dist/jquery.min.js';
$swapScript = APP_URL.'/app/modules/kr-changenow/statics/js/swap.js';

$html = '<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>'.$css.'</style>
</head>
<body class="kr-login kr-swap-first">
  <form>
    <section class="kr-public-swap-panel">'.$publicPanel.'</section>
    <section class="kr-login-view">
      <header><img src="'.$logo.'" alt=""></header>
      <section>
        <section class="kr-login-field">
          <input type="text" placeholder="Your e-mail address">
          <div><span></span></div>
          <input type="password" placeholder="Your password">
          <div><span></span></div>
          <footer><a>Forgot password ?</a><button class="btn-shadow" type="button">LOGIN</button></footer>
        </section>
        <section class="kr-login-separator"><div></div><span>or</span><div></div></section>
        <section class="kr-login-oauth"><a class="btn-shadow btn-black"><div class="kr-login-oauth-name">Create a new account</div></a></section>
      </section>
    </section>
  </form>
  <script src="'.$jqueryScript.'"></script>
  <script src="'.$swapScript.'"></script>
</body>
</html>';

file_put_contents($root.'/experiments/changenow-ui-preview.html', $html);
