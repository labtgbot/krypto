<?php

/**
 * ChangeNOW widget URL and rendering helpers.
 *
 * @package Krypto
 */
class ChangeNowWidget {

  const WIDGET_BASE_URL = 'https://changenow.io/embeds/exchange-widget/v2/widget.html';
  const CONNECTOR_SCRIPT_URL = 'https://changenow.io/embeds/exchange-widget/v2/stepper-connector.js';
  const DIRECT_BASE_URL = 'https://changenow.io/exchange';

  private static $storageDefaults = [
    'enabled' => '0',
    'amount' => '0.1',
    'amount_fiat' => '1500',
    'from' => 'btc',
    'to' => 'eth',
    'from_fiat' => 'eur',
    'to_fiat' => 'eth',
    'fiat_mode' => '0',
    'lang' => 'en-US',
    'dark_mode' => '0',
    'logo' => '1',
    'faq' => '1',
    'locales' => '1',
    'primary_color' => '00C26F',
    'background_color' => 'FFFFFF',
    'horizontal' => '0',
    'link_id' => '',
    'fallback_url' => '',
    'place_landing' => '0',
    'place_dashboard' => '0',
    'place_coin' => '0',
    'place_custom_page' => '0',
    'to_the_moon' => '1'
  ];

  private static $expectedQueryKeys = [
    'FAQ',
    'amount',
    'amountFiat',
    'backgroundColor',
    'darkMode',
    'from',
    'fromFiat',
    'horizontal',
    'isFiat',
    'lang',
    'link_id',
    'locales',
    'logo',
    'primaryColor',
    'to',
    'toFiat',
    'toTheMoon'
  ];

  /**
   * Get all settings defaults for storage and form rendering.
   * @return Array
   */
  public static function _getStorageDefaults(){
    return self::$storageDefaults;
  }

  /**
   * Get prefixed settings key for the widget setting.
   * @param String $key
   * @return String
   */
  public static function _getStorageKey($key){
    return 'changenow_widget_'.$key;
  }

  /**
   * Get the exact query parameters allowed in the iframe URL.
   * @return Array
   */
  public static function _getExpectedQueryKeys(){
    return self::$expectedQueryKeys;
  }

  /**
   * Sanitize user/admin supplied widget configuration.
   * @param Array $rawConfig
   * @return Array
   */
  public static function _sanitizeConfig($rawConfig = []){
    if(!is_array($rawConfig)) $rawConfig = [];

    $config = [];
    foreach (self::$storageDefaults as $key => $defaultValue) {
      $config[$key] = (array_key_exists($key, $rawConfig) ? $rawConfig[$key] : $defaultValue);
    }

    foreach (['enabled', 'fiat_mode', 'dark_mode', 'logo', 'faq', 'locales', 'horizontal',
              'place_landing', 'place_dashboard', 'place_coin', 'place_custom_page', 'to_the_moon'] as $boolKey) {
      $config[$boolKey] = (self::_sanitizeBool($config[$boolKey]) ? '1' : '0');
    }

    $config['amount'] = self::_sanitizeAmount($config['amount'], self::$storageDefaults['amount']);
    $config['amount_fiat'] = self::_sanitizeAmount($config['amount_fiat'], self::$storageDefaults['amount_fiat']);
    $config['from'] = self::_sanitizeCurrencyCode($config['from'], self::$storageDefaults['from']);
    $config['to'] = self::_sanitizeCurrencyCode($config['to'], self::$storageDefaults['to']);
    $config['from_fiat'] = self::_sanitizeCurrencyCode($config['from_fiat'], self::$storageDefaults['from_fiat']);
    $config['to_fiat'] = self::_sanitizeCurrencyCode($config['to_fiat'], self::$storageDefaults['to_fiat']);
    $config['lang'] = self::_sanitizeLanguage($config['lang'], self::$storageDefaults['lang']);
    $config['primary_color'] = self::_sanitizeColor($config['primary_color'], self::$storageDefaults['primary_color']);
    $config['background_color'] = self::_sanitizeColor($config['background_color'], self::$storageDefaults['background_color']);
    $config['link_id'] = self::_sanitizeLinkId($config['link_id']);
    $config['fallback_url'] = self::_sanitizeFallbackUrl($config['fallback_url']);

    return $config;
  }

  /**
   * Build the whitelisted ChangeNOW iframe query.
   * @param Array $config
   * @return Array
   */
  public static function _getSanitizedWidgetQuery($config = []){
    $config = self::_sanitizeConfig($config);
    return [
      'FAQ' => self::_boolQuery($config['faq']),
      'amount' => $config['amount'],
      'amountFiat' => $config['amount_fiat'],
      'backgroundColor' => $config['background_color'],
      'darkMode' => self::_boolQuery($config['dark_mode']),
      'from' => $config['from'],
      'fromFiat' => $config['from_fiat'],
      'horizontal' => self::_boolQuery($config['horizontal']),
      'isFiat' => ($config['fiat_mode'] == '1' ? 'true' : ''),
      'lang' => $config['lang'],
      'link_id' => $config['link_id'],
      'locales' => self::_boolQuery($config['locales']),
      'logo' => self::_boolQuery($config['logo']),
      'primaryColor' => $config['primary_color'],
      'to' => $config['to'],
      'toFiat' => $config['to_fiat'],
      'toTheMoon' => self::_boolQuery($config['to_the_moon'])
    ];
  }

  /**
   * Build the ChangeNOW iframe URL.
   * @param Array $config
   * @return String
   */
  public static function _buildIframeUrl($config = []){
    return self::WIDGET_BASE_URL.'?'.http_build_query(self::_getSanitizedWidgetQuery($config), '', '&');
  }

  /**
   * Build fallback URL used when the iframe cannot load.
   * @param Array $config
   * @return String
   */
  public static function _buildFallbackUrl($config = []){
    $config = self::_sanitizeConfig($config);
    if(strlen($config['fallback_url']) > 0) return $config['fallback_url'];

    $query = [
      'from' => $config['from'],
      'to' => $config['to'],
      'amount' => $config['amount']
    ];
    if(strlen($config['link_id']) > 0) $query['link_id'] = $config['link_id'];

    return self::DIRECT_BASE_URL.'?'.http_build_query($query, '', '&');
  }

  /**
   * Check if a sanitized config is enabled for a given placement.
   * @param Array $config
   * @param String $placement
   * @return Boolean
   */
  public static function _isEnabledForPlacement($config, $placement){
    $config = self::_sanitizeConfig($config);
    if($config['enabled'] != '1') return false;

    $placementKey = self::_getPlacementKey($placement);
    if(is_null($placementKey)) return false;

    return $config[$placementKey] == '1';
  }

  /**
   * Render iframe widget markup.
   * @param Array $config
   * @param String $placement
   * @param Boolean $force
   * @return String
   */
  public static function _render($config = [], $placement = 'dashboard', $force = false){
    $config = self::_sanitizeConfig($config);
    if(!$force && !self::_isEnabledForPlacement($config, $placement)) return '';

    $iframeUrl = self::_escapeAttribute(self::_buildIframeUrl($config));
    $fallbackUrl = self::_escapeAttribute(self::_buildFallbackUrl($config));
    $placementClass = self::_escapeAttribute(preg_replace('/[^a-z0-9_-]/i', '', $placement));

    return '<section class="kr-changenow-widget kr-changenow-widget-'.$placementClass.'" data-kr-changenow-widget="1" data-loaded="0">'.
             '<iframe id="iframe-widget" title="ChangeNOW exchange widget" src="'.$iframeUrl.'" frameborder="0" allow="clipboard-write" loading="lazy" onload="this.parentNode.setAttribute(\'data-loaded\', \'1\');"></iframe>'.
             '<div class="kr-changenow-widget-fallback">'.
               '<span>ChangeNOW widget is not available right now.</span>'.
               '<a href="'.$fallbackUrl.'" target="_blank" rel="noopener">Open ChangeNOW</a>'.
             '</div>'.
             '<noscript><a href="'.$fallbackUrl.'" target="_blank" rel="noopener">Open ChangeNOW</a></noscript>'.
             '<script>(function(widget){if(!widget)return;setTimeout(function(){if(widget.getAttribute("data-loaded")!=="1")widget.className+=" kr-changenow-widget-failed";},8000);})(document.currentScript.parentNode);</script>'.
             '<script defer type="text/javascript" src="'.self::CONNECTOR_SCRIPT_URL.'"></script>'.
           '</section>';
  }

  /**
   * Render widget from app settings.
   * @param App $App
   * @param String $placement
   * @param Boolean $force
   * @return String
   */
  public static function _renderFromApp($App, $placement = 'dashboard', $force = false){
    if(is_null($App) || !method_exists($App, '_getChangeNowWidgetConfig')) return '';
    return self::_render($App->_getChangeNowWidgetConfig(), $placement, $force);
  }

  private static function _getPlacementKey($placement){
    $placement = strtolower(trim($placement));
    $map = [
      'landing' => 'place_landing',
      'dashboard' => 'place_dashboard',
      'coin' => 'place_coin',
      'custom_page' => 'place_custom_page',
      'custompage' => 'place_custom_page'
    ];
    return (array_key_exists($placement, $map) ? $map[$placement] : null);
  }

  private static function _sanitizeBool($value){
    if(is_bool($value)) return $value;
    $value = strtolower(trim(strval($value)));
    return in_array($value, ['1', 'true', 'on', 'yes'], true);
  }

  private static function _boolQuery($value){
    return ($value == '1' ? 'true' : 'false');
  }

  private static function _sanitizeAmount($value, $defaultValue){
    $value = str_replace(',', '.', trim(strval($value)));
    if(!preg_match('/^[0-9]+(\.[0-9]{1,12})?$/', $value)) return $defaultValue;
    if(floatval($value) <= 0) return $defaultValue;
    return substr($value, 0, 32);
  }

  private static function _sanitizeCurrencyCode($value, $defaultValue){
    $value = strtolower(trim(strval($value)));
    if(!preg_match('/^[a-z0-9-]{2,30}$/', $value)) return $defaultValue;
    return $value;
  }

  private static function _sanitizeLanguage($value, $defaultValue){
    $value = trim(strval($value));
    if(!preg_match('/^[a-z]{2}(-[A-Z]{2})?$/', $value)) return $defaultValue;
    return $value;
  }

  private static function _sanitizeColor($value, $defaultValue){
    $value = strtoupper(trim(strval($value)));
    $value = ltrim($value, '#');
    if(!preg_match('/^[A-F0-9]{6}$/', $value)) return $defaultValue;
    return $value;
  }

  private static function _sanitizeLinkId($value){
    $value = preg_replace('/[^A-Za-z0-9_-]/', '', strval($value));
    return substr($value, 0, 128);
  }

  private static function _sanitizeFallbackUrl($value){
    $value = trim(strval($value));
    if(strlen($value) == 0) return '';
    if(!filter_var($value, FILTER_VALIDATE_URL)) return '';
    $parts = parse_url($value);
    if(!is_array($parts) || !array_key_exists('scheme', $parts)) return '';
    if(!in_array(strtolower($parts['scheme']), ['http', 'https'], true)) return '';
    return $value;
  }

  private static function _escapeAttribute($value){
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
  }
}

?>
