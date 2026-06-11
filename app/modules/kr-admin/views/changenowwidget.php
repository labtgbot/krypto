<?php

/**
 * Admin ChangeNOW widget settings page.
 *
 * @package Krypto
 */

require "../../../../config/config.settings.php";

krypto_session_start();

require_once "../../../../app/src/bootstrap_paths.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/vendor/autoload.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/MySQL/MySQL.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/App/App.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/App/AppModule.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/User/User.php";
require $_SERVER['DOCUMENT_ROOT'].FILE_PATH."/app/src/Lang/Lang.php";

$App = new App(true);
$App->_loadModulesControllers();

$User = new User();
if(!$User->_isLogged()) throw new Exception("User are not logged", 1);
if(!$User->_isAdmin()) throw new Exception("Permission denied", 1);

$Lang = new Lang($User->_getLang(), $App);
$Admin = new Admin();
$config = $App->_getChangeNowWidgetConfig();

function krChangeNowChecked($config, $key){
  return (array_key_exists($key, $config) && $config[$key] == '1' ? 'checked' : '');
}

function krChangeNowValue($config, $key){
  return htmlspecialchars((array_key_exists($key, $config) ? $config[$key] : ''), ENT_QUOTES, 'UTF-8');
}

?>
<form class="kr-admin kr-adm-post-evs" action="<?php echo APP_URL; ?>/app/modules/kr-admin/src/actions/saveChangeNowWidget.php" enctype="multipart/form-data">
  <nav class="kr-admin-nav">
    <ul>
      <?php foreach ($Admin->_getListSection() as $key => $section) {
        echo '<li type="module" kr-module="admin" kr-view="'.strtolower(str_replace(' ', '', $section)).'" '.($section == 'ChangeNOW widget' ? 'class="kr-admin-nav-selected"' : '').'>'.$Lang->tr($section).'</li>';
      } ?>
    </ul>
  </nav>
  <div class="kr-admin-line kr-admin-line-cls kr-admin-line-changenow">
    <div class="kr-admin-field">
      <div>
        <label><?php echo $Lang->tr('Enable ChangeNOW widget'); ?></label><br/>
        <span><?php echo $Lang->tr('Render the partner widget in the selected public areas.'); ?></span>
      </div>
      <div>
        <div class="ckbx-style-14">
          <input type="checkbox" id="kr-changenow-enabled" name="enabled" value="1" <?php echo krChangeNowChecked($config, 'enabled'); ?>>
          <label for="kr-changenow-enabled"></label>
        </div>
      </div>
    </div>
    <div class="kr-admin-field">
      <div>
        <label><?php echo $Lang->tr('Widget placements'); ?></label><br/>
        <span><?php echo $Lang->tr('Choose where the iframe can be displayed.'); ?></span>
      </div>
      <div>
        <div class="ckbx-style-14">
          <input type="checkbox" id="kr-changenow-place-landing" name="place_landing" value="1" <?php echo krChangeNowChecked($config, 'place_landing'); ?>>
          <label for="kr-changenow-place-landing"></label>
        </div>
        <label for="kr-changenow-place-landing"><?php echo $Lang->tr('Landing page'); ?></label><br/><br/>
        <div class="ckbx-style-14">
          <input type="checkbox" id="kr-changenow-place-dashboard" name="place_dashboard" value="1" <?php echo krChangeNowChecked($config, 'place_dashboard'); ?>>
          <label for="kr-changenow-place-dashboard"></label>
        </div>
        <label for="kr-changenow-place-dashboard"><?php echo $Lang->tr('Dashboard panel'); ?></label><br/><br/>
        <div class="ckbx-style-14">
          <input type="checkbox" id="kr-changenow-place-coin" name="place_coin" value="1" <?php echo krChangeNowChecked($config, 'place_coin'); ?>>
          <label for="kr-changenow-place-coin"></label>
        </div>
        <label for="kr-changenow-place-coin"><?php echo $Lang->tr('Coin page'); ?></label><br/><br/>
        <div class="ckbx-style-14">
          <input type="checkbox" id="kr-changenow-place-custom-page" name="place_custom_page" value="1" <?php echo krChangeNowChecked($config, 'place_custom_page'); ?>>
          <label for="kr-changenow-place-custom-page"></label>
        </div>
        <label for="kr-changenow-place-custom-page"><?php echo $Lang->tr('Custom page'); ?></label>
      </div>
    </div>
    <div class="kr-admin-field">
      <div>
        <label><?php echo $Lang->tr('Partner link ID'); ?></label><br/>
        <span><?php echo $Lang->tr('Preserved as ChangeNOW link_id attribution.'); ?></span>
      </div>
      <div>
        <input type="text" name="link_id" placeholder="partner_id" value="<?php echo krChangeNowValue($config, 'link_id'); ?>">
      </div>
    </div>
    <div class="kr-admin-field">
      <div>
        <label><?php echo $Lang->tr('Fallback URL'); ?></label><br/>
        <span><?php echo $Lang->tr('Optional direct ChangeNOW or referral link.'); ?></span>
      </div>
      <div>
        <input type="text" name="fallback_url" placeholder="https://changenow.io/exchange" value="<?php echo krChangeNowValue($config, 'fallback_url'); ?>">
      </div>
    </div>
  </div>
  <div class="kr-admin-line kr-admin-line-cls kr-admin-line-changenow">
    <div class="kr-admin-field">
      <div>
        <label><?php echo $Lang->tr('Default crypto amount'); ?></label>
      </div>
      <div>
        <input type="text" name="amount" value="<?php echo krChangeNowValue($config, 'amount'); ?>">
      </div>
    </div>
    <div class="kr-admin-field">
      <div>
        <label><?php echo $Lang->tr('Default crypto pair'); ?></label>
      </div>
      <div>
        <input type="text" name="from" placeholder="btc" value="<?php echo krChangeNowValue($config, 'from'); ?>">
        <input type="text" name="to" placeholder="eth" value="<?php echo krChangeNowValue($config, 'to'); ?>">
      </div>
    </div>
    <div class="kr-admin-field">
      <div>
        <label><?php echo $Lang->tr('Fiat mode'); ?></label>
      </div>
      <div>
        <div class="ckbx-style-14">
          <input type="checkbox" id="kr-changenow-fiat-mode" name="fiat_mode" value="1" <?php echo krChangeNowChecked($config, 'fiat_mode'); ?>>
          <label for="kr-changenow-fiat-mode"></label>
        </div>
      </div>
    </div>
    <div class="kr-admin-field">
      <div>
        <label><?php echo $Lang->tr('Default fiat amount'); ?></label>
      </div>
      <div>
        <input type="text" name="amount_fiat" value="<?php echo krChangeNowValue($config, 'amount_fiat'); ?>">
      </div>
    </div>
    <div class="kr-admin-field">
      <div>
        <label><?php echo $Lang->tr('Default fiat pair'); ?></label>
      </div>
      <div>
        <input type="text" name="from_fiat" placeholder="eur" value="<?php echo krChangeNowValue($config, 'from_fiat'); ?>">
        <input type="text" name="to_fiat" placeholder="eth" value="<?php echo krChangeNowValue($config, 'to_fiat'); ?>">
      </div>
    </div>
  </div>
  <div class="kr-admin-line kr-admin-line-cls kr-admin-line-changenow">
    <div class="kr-admin-field">
      <div>
        <label><?php echo $Lang->tr('Language'); ?></label>
      </div>
      <div>
        <select name="lang">
          <?php foreach (['en-US', 'de', 'es', 'fr', 'it', 'pt', 'ru', 'zh-CN', 'ja', 'ko'] as $langCode): ?>
            <option value="<?php echo $langCode; ?>" <?php if($config['lang'] == $langCode) echo 'selected="selected"'; ?>><?php echo $langCode; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="kr-admin-field">
      <div>
        <label><?php echo $Lang->tr('Display options'); ?></label>
      </div>
      <div>
        <div class="ckbx-style-14">
          <input type="checkbox" id="kr-changenow-dark-mode" name="dark_mode" value="1" <?php echo krChangeNowChecked($config, 'dark_mode'); ?>>
          <label for="kr-changenow-dark-mode"></label>
        </div>
        <label for="kr-changenow-dark-mode"><?php echo $Lang->tr('Dark mode'); ?></label><br/><br/>
        <div class="ckbx-style-14">
          <input type="checkbox" id="kr-changenow-logo" name="logo" value="1" <?php echo krChangeNowChecked($config, 'logo'); ?>>
          <label for="kr-changenow-logo"></label>
        </div>
        <label for="kr-changenow-logo"><?php echo $Lang->tr('Show logo'); ?></label><br/><br/>
        <div class="ckbx-style-14">
          <input type="checkbox" id="kr-changenow-faq" name="faq" value="1" <?php echo krChangeNowChecked($config, 'faq'); ?>>
          <label for="kr-changenow-faq"></label>
        </div>
        <label for="kr-changenow-faq"><?php echo $Lang->tr('Show FAQ'); ?></label><br/><br/>
        <div class="ckbx-style-14">
          <input type="checkbox" id="kr-changenow-locales" name="locales" value="1" <?php echo krChangeNowChecked($config, 'locales'); ?>>
          <label for="kr-changenow-locales"></label>
        </div>
        <label for="kr-changenow-locales"><?php echo $Lang->tr('Language picker'); ?></label><br/><br/>
        <div class="ckbx-style-14">
          <input type="checkbox" id="kr-changenow-horizontal" name="horizontal" value="1" <?php echo krChangeNowChecked($config, 'horizontal'); ?>>
          <label for="kr-changenow-horizontal"></label>
        </div>
        <label for="kr-changenow-horizontal"><?php echo $Lang->tr('Horizontal layout'); ?></label>
      </div>
    </div>
    <div class="kr-admin-field">
      <div>
        <label><?php echo $Lang->tr('Primary color'); ?></label>
      </div>
      <div>
        <input type="color" name="primary_color" value="#<?php echo krChangeNowValue($config, 'primary_color'); ?>">
      </div>
    </div>
    <div class="kr-admin-field">
      <div>
        <label><?php echo $Lang->tr('Background color'); ?></label>
      </div>
      <div>
        <input type="color" name="background_color" value="#<?php echo krChangeNowValue($config, 'background_color'); ?>">
      </div>
    </div>
  </div>
  <div class="kr-admin-line kr-admin-line-cls kr-admin-line-changenow">
    <div class="kr-admin-field">
      <div>
        <label><?php echo $Lang->tr('Preview'); ?></label><br/>
        <span><?php echo $Lang->tr('Preview uses sanitized settings before the widget is published.'); ?></span>
      </div>
      <div class="kr-changenow-admin-preview">
        <?php echo ChangeNowWidget::_render(array_merge($config, ['enabled' => '1']), 'admin_preview', true); ?>
      </div>
    </div>
  </div>
  <footer>
    <input type="submit" class="btn btn-orange" value="<?php echo $Lang->tr('Save'); ?>">
  </footer>
</form>
