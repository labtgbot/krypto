<?php

require __DIR__.'/../app/modules/kr-changenow/src/ChangeNowWidget.php';

$config = [
  'enabled' => '1',
  'place_dashboard' => '1',
  'amount' => '0.1',
  'from' => 'btc',
  'to' => 'eth',
  'link_id' => 'preview_partner'
];

?>
<!doctype html>
<html>
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ChangeNOW widget preview</title>
    <link rel="stylesheet" href="../app/modules/kr-changenow/statics/css/widget.css">
    <style>
      body {
        margin      : 0;
        background  : #1d2435;
        font-family : Roboto, Arial, sans-serif;
      }

      .preview-shell {
        display         : flex;
        box-sizing      : border-box;
        width           : 100%;
        min-height      : 760px;
        padding         : 32px;
        align-items     : flex-start;
        justify-content : center;
      }

      .preview-card {
        width : 420px;
      }
    </style>
  </head>
  <body>
    <main class="preview-shell">
      <div class="preview-card">
        <?php echo ChangeNowWidget::_render($config, 'dashboard', true); ?>
      </div>
    </main>
  </body>
</html>
