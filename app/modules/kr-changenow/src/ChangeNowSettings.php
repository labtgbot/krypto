<?php

/**
 * ChangeNOW provider setting names, defaults, and admin form mapping.
 *
 * @package Krypto
 */
class ChangeNowSettings {

  const SECRET_MASK = '*********************';

  public static function _defaults(){
    return [
      'changenow_provider_enabled' => '0',
      'changenow_public_api_key' => '',
      'changenow_private_api_key' => '',
      'changenow_callback_secret' => '',
      'changenow_referral_link_id' => '',
      'changenow_widget_link_id' => '',
      'changenow_enabled_flows' => 'standard,fixed-rate',
      'changenow_default_flow' => 'standard',
      'changenow_default_from_asset' => 'btc',
      'changenow_default_from_network' => 'btc',
      'changenow_default_to_asset' => 'eth',
      'changenow_default_to_network' => 'eth',
      'changenow_support_email' => '',
      'changenow_rate_limit_per_second' => '30',
      'changenow_rate_limit_per_minute' => '1800'
    ];
  }

  public static function _encryptedKeys(){
    return [
      'changenow_public_api_key',
      'changenow_private_api_key',
      'changenow_callback_secret'
    ];
  }

  public static function _allowedFlows(){
    return ['standard', 'fixed-rate'];
  }

  public static function _adminPostToSettings($post){
    $settings = [
      'changenow_provider_enabled' => (array_key_exists('kr-adm-chk-enablechangenow', $post) && $post['kr-adm-chk-enablechangenow'] == 'on' ? '1' : '0'),
      'changenow_referral_link_id' => self::_stringValue($post, 'kr-adm-changenowreferrallinkid'),
      'changenow_widget_link_id' => self::_stringValue($post, 'kr-adm-changenowwidgetlinkid'),
      'changenow_enabled_flows' => self::_normalizeEnabledFlowsFromPost($post),
      'changenow_default_flow' => self::_normalizeDefaultFlow(self::_stringValue($post, 'kr-adm-changenowdefaultflow')),
      'changenow_default_from_asset' => self::_normalizeAsset(self::_stringValue($post, 'kr-adm-changenowdefaultfromasset'), 'btc'),
      'changenow_default_from_network' => self::_normalizeAsset(self::_stringValue($post, 'kr-adm-changenowdefaultfromnetwork'), 'btc'),
      'changenow_default_to_asset' => self::_normalizeAsset(self::_stringValue($post, 'kr-adm-changenowdefaulttoasset'), 'eth'),
      'changenow_default_to_network' => self::_normalizeAsset(self::_stringValue($post, 'kr-adm-changenowdefaulttonetwork'), 'eth'),
      'changenow_support_email' => self::_stringValue($post, 'kr-adm-changenowsupportemail'),
      'changenow_rate_limit_per_second' => self::_normalizePositiveInteger(self::_stringValue($post, 'kr-adm-changenowratelimitsecond'), '30'),
      'changenow_rate_limit_per_minute' => self::_normalizePositiveInteger(self::_stringValue($post, 'kr-adm-changenowratelimitminute'), '1800')
    ];

    $secretFields = [
      'kr-adm-changenowpublicapikey' => 'changenow_public_api_key',
      'kr-adm-changenowprivateapikey' => 'changenow_private_api_key',
      'kr-adm-changenowcallbacksecret' => 'changenow_callback_secret'
    ];

    foreach ($secretFields as $postKey => $settingKey) {
      if(!array_key_exists($postKey, $post)) continue;
      $value = self::_stringValue($post, $postKey);
      if($value === self::SECRET_MASK) continue;
      $settings[$settingKey] = $value;
    }

    return $settings;
  }

  public static function _enabledFlowsToArray($value){
    if(is_array($value)) $flows = $value;
    else $flows = explode(',', (string) $value);

    $result = [];
    foreach ($flows as $flow) {
      $flow = self::_normalizeDefaultFlow($flow);
      if(!in_array($flow, $result, true)) $result[] = $flow;
    }

    if(count($result) == 0) $result[] = 'standard';
    return $result;
  }

  private static function _normalizeEnabledFlowsFromPost($post){
    $flows = [];
    if(array_key_exists('kr-adm-chk-changenowflowstandard', $post) && $post['kr-adm-chk-changenowflowstandard'] == 'on') $flows[] = 'standard';
    if(array_key_exists('kr-adm-chk-changenowflowfixedrate', $post) && $post['kr-adm-chk-changenowflowfixedrate'] == 'on') $flows[] = 'fixed-rate';
    if(count($flows) == 0) $flows[] = 'standard';
    return join(',', $flows);
  }

  private static function _normalizeDefaultFlow($flow){
    $flow = strtolower(trim((string) $flow));
    if($flow == 'fixed') $flow = 'fixed-rate';
    if(!in_array($flow, self::_allowedFlows(), true)) return 'standard';
    return $flow;
  }

  private static function _normalizeAsset($value, $default){
    $value = strtolower(trim((string) $value));
    if($value == '') return $default;
    $value = preg_replace('/[^a-z0-9_-]/', '', $value);
    return ($value == '' ? $default : $value);
  }

  private static function _normalizePositiveInteger($value, $default){
    $value = trim((string) $value);
    if(!preg_match('/^[0-9]+$/', $value)) return $default;
    if(intval($value) < 1) return $default;
    return (string) intval($value);
  }

  private static function _stringValue($post, $key){
    if(!array_key_exists($key, $post)) return '';
    return trim((string) $post[$key]);
  }

}

?>
