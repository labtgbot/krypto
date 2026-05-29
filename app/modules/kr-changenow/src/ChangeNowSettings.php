<?php

/**
 * ChangeNOW provider, widget, and policy setting helpers.
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
      'changenow_enabled_assets' => '',
      'changenow_enabled_networks' => '',
      'changenow_disabled_pairs' => '',
      'changenow_widget_enabled' => '0',
      'changenow_widget_amount' => '0.1',
      'changenow_widget_fiat_enabled' => '0',
      'changenow_widget_primary_color' => '00C26F',
      'changenow_widget_background_color' => 'FFFFFF',
      'changenow_widget_language' => 'en-US',
      'changenow_support_email' => '',
      'changenow_support_copy' => 'Contact support with your ChangeNOW transaction ID and the receiving asset.',
      'changenow_refund_copy' => 'Refund requests are available only when ChangeNOW marks the transaction refundable.',
      'changenow_continue_copy' => 'Continue is available only when ChangeNOW marks the transaction resumable.',
      'changenow_debug_logging_enabled' => '0',
      'changenow_rate_limit_per_second' => '30',
      'changenow_rate_limit_per_minute' => '1800',
      'changenow_quote_cache_ttl' => '30',
      'changenow_retention_anonymous_days' => '30',
      'changenow_retention_completed_days' => '365',
      'changenow_rate_limit_warning_state' => 'normal',
      'changenow_last_successful_sync' => '',
      'changenow_provider_health_status' => 'unknown',
      'changenow_provider_health_message' => '',
      'changenow_local_disabled_reason' => 'ChangeNOW swaps are disabled locally.'
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

  public static function _sanitizeSettings($settings){
    if(!is_array($settings)) $settings = [];

    $sanitized = [];
    foreach (self::_defaults() as $key => $default) {
      $sanitized[$key] = (array_key_exists($key, $settings) && !is_null($settings[$key]) ? $settings[$key] : $default);
    }

    foreach (['changenow_provider_enabled', 'changenow_widget_enabled', 'changenow_widget_fiat_enabled',
              'changenow_debug_logging_enabled'] as $boolKey) {
      $sanitized[$boolKey] = (self::_boolValue($sanitized[$boolKey]) ? '1' : '0');
    }

    $sanitized['changenow_enabled_flows'] = implode(',', self::_enabledFlowsToArray($sanitized['changenow_enabled_flows']));
    $sanitized['changenow_default_flow'] = self::_normalizeFlow($sanitized['changenow_default_flow']);
    $sanitized['changenow_default_from_asset'] = self::_normalizeAsset($sanitized['changenow_default_from_asset'], 'btc');
    $sanitized['changenow_default_from_network'] = self::_normalizeAsset($sanitized['changenow_default_from_network'], 'btc');
    $sanitized['changenow_default_to_asset'] = self::_normalizeAsset($sanitized['changenow_default_to_asset'], 'eth');
    $sanitized['changenow_default_to_network'] = self::_normalizeAsset($sanitized['changenow_default_to_network'], 'eth');
    $sanitized['changenow_enabled_assets'] = self::_normalizeList($sanitized['changenow_enabled_assets']);
    $sanitized['changenow_enabled_networks'] = self::_normalizeList($sanitized['changenow_enabled_networks']);
    $sanitized['changenow_disabled_pairs'] = self::_normalizeList($sanitized['changenow_disabled_pairs'], '/[^a-z0-9_:\\/\\-,\\r\\n ]/i');
    $sanitized['changenow_widget_amount'] = self::_normalizeAmount($sanitized['changenow_widget_amount'], '0.1');
    $sanitized['changenow_widget_primary_color'] = self::_normalizeColor($sanitized['changenow_widget_primary_color'], '00C26F');
    $sanitized['changenow_widget_background_color'] = self::_normalizeColor($sanitized['changenow_widget_background_color'], 'FFFFFF');
    $sanitized['changenow_widget_language'] = self::_normalizeLanguage($sanitized['changenow_widget_language'], 'en-US');
    $sanitized['changenow_rate_limit_per_second'] = self::_normalizePositiveInteger($sanitized['changenow_rate_limit_per_second'], '30');
    $sanitized['changenow_rate_limit_per_minute'] = self::_normalizePositiveInteger($sanitized['changenow_rate_limit_per_minute'], '1800');
    $sanitized['changenow_quote_cache_ttl'] = self::_normalizePositiveInteger($sanitized['changenow_quote_cache_ttl'], '30');
    $sanitized['changenow_retention_anonymous_days'] = self::_normalizePositiveInteger($sanitized['changenow_retention_anonymous_days'], '30');
    $sanitized['changenow_retention_completed_days'] = self::_normalizePositiveInteger($sanitized['changenow_retention_completed_days'], '365');
    $sanitized['changenow_rate_limit_warning_state'] = self::_normalizeChoice($sanitized['changenow_rate_limit_warning_state'], ['normal', 'warning', 'limited'], 'normal');
    $sanitized['changenow_provider_health_status'] = self::_normalizeChoice($sanitized['changenow_provider_health_status'], ['unknown', 'healthy', 'degraded', 'outage'], 'unknown');

    foreach ([
      'changenow_referral_link_id',
      'changenow_widget_link_id',
      'changenow_support_email',
      'changenow_support_copy',
      'changenow_refund_copy',
      'changenow_continue_copy',
      'changenow_provider_health_message',
      'changenow_local_disabled_reason'
    ] as $textKey) {
      $sanitized[$textKey] = self::_sanitizeText($sanitized[$textKey], 2000);
    }

    return $sanitized;
  }

  public static function _adminPostToSettings($post){
    if(!is_array($post)) $post = [];

    $settings = [
      'changenow_provider_enabled' => self::_checkbox($post, 'kr-adm-chk-enablechangenow'),
      'changenow_referral_link_id' => self::_postString($post, 'kr-adm-changenowreferrallinkid'),
      'changenow_widget_link_id' => self::_postString($post, 'kr-adm-changenowwidgetlinkid'),
      'changenow_enabled_flows' => self::_flowsFromPost($post),
      'changenow_default_flow' => self::_postString($post, 'kr-adm-changenowdefaultflow'),
      'changenow_default_from_asset' => self::_postString($post, 'kr-adm-changenowdefaultfromasset'),
      'changenow_default_from_network' => self::_postString($post, 'kr-adm-changenowdefaultfromnetwork'),
      'changenow_default_to_asset' => self::_postString($post, 'kr-adm-changenowdefaulttoasset'),
      'changenow_default_to_network' => self::_postString($post, 'kr-adm-changenowdefaulttonetwork'),
      'changenow_enabled_assets' => self::_postString($post, 'kr-adm-changenowenabledassets'),
      'changenow_enabled_networks' => self::_postString($post, 'kr-adm-changenowenablednetworks'),
      'changenow_disabled_pairs' => self::_postString($post, 'kr-adm-changenowdisabledpairs'),
      'changenow_widget_enabled' => self::_checkbox($post, 'kr-adm-chk-changenowwidgetenabled'),
      'changenow_widget_amount' => self::_postString($post, 'kr-adm-changenowwidgetamount'),
      'changenow_widget_fiat_enabled' => self::_checkbox($post, 'kr-adm-chk-changenowwidgetfiat'),
      'changenow_widget_primary_color' => self::_postString($post, 'kr-adm-changenowwidgetprimarycolor'),
      'changenow_widget_background_color' => self::_postString($post, 'kr-adm-changenowwidgetbackgroundcolor'),
      'changenow_widget_language' => self::_postString($post, 'kr-adm-changenowwidgetlanguage'),
      'changenow_support_email' => self::_postString($post, 'kr-adm-changenowsupportemail'),
      'changenow_support_copy' => self::_postString($post, 'kr-adm-changenowsupportcopy'),
      'changenow_refund_copy' => self::_postString($post, 'kr-adm-changenowrefundcopy'),
      'changenow_continue_copy' => self::_postString($post, 'kr-adm-changenowcontinuecopy'),
      'changenow_debug_logging_enabled' => self::_checkbox($post, 'kr-adm-chk-changenowdebuglogging'),
      'changenow_rate_limit_per_second' => self::_postString($post, 'kr-adm-changenowratelimitsecond'),
      'changenow_rate_limit_per_minute' => self::_postString($post, 'kr-adm-changenowratelimitminute'),
      'changenow_quote_cache_ttl' => self::_postString($post, 'kr-adm-changenowquotecachettl'),
      'changenow_retention_anonymous_days' => self::_postString($post, 'kr-adm-changenowretentionanonymousdays'),
      'changenow_retention_completed_days' => self::_postString($post, 'kr-adm-changenowretentioncompleteddays'),
      'changenow_rate_limit_warning_state' => self::_postString($post, 'kr-adm-changenowratelimitwarning'),
      'changenow_provider_health_status' => self::_postString($post, 'kr-adm-changenowproviderhealth'),
      'changenow_provider_health_message' => self::_postString($post, 'kr-adm-changenowproviderhealthmessage'),
      'changenow_local_disabled_reason' => self::_postString($post, 'kr-adm-changenowlocaldisabledreason')
    ];

    $postedSecretKeys = [];
    $maskedSecretKeys = [];
    foreach ([
      'kr-adm-changenowpublicapikey' => 'changenow_public_api_key',
      'kr-adm-changenowprivateapikey' => 'changenow_private_api_key',
      'kr-adm-changenowcallbacksecret' => 'changenow_callback_secret'
    ] as $postKey => $settingKey) {
      if(!array_key_exists($postKey, $post)) continue;
      $postedSecretKeys[] = $settingKey;
      $value = self::_postString($post, $postKey);
      if($value === self::SECRET_MASK){
        $maskedSecretKeys[] = $settingKey;
        continue;
      }
      $settings[$settingKey] = $value;
    }

    $settings = self::_sanitizeSettings($settings);
    foreach (self::_encryptedKeys() as $secretKey) {
      if(!in_array($secretKey, $postedSecretKeys, true) || in_array($secretKey, $maskedSecretKeys, true)){
        unset($settings[$secretKey]);
      }
    }

    return $settings;
  }

  public static function _enabledFlowsToArray($value){
    if(is_array($value)) $flows = $value;
    else $flows = explode(',', (string) $value);

    $result = [];
    foreach ($flows as $flow) {
      $flow = self::_normalizeFlow($flow);
      if(!in_array($flow, $result, true)) $result[] = $flow;
    }

    if(count($result) == 0) $result[] = 'standard';
    return $result;
  }

  public static function _hasRequiredProviderConfig($settings){
    $settings = self::_sanitizeSettings($settings);
    return trim((string) $settings['changenow_public_api_key']) != '';
  }

  private static function _flowsFromPost($post){
    $flows = [];
    if(array_key_exists('kr-adm-chk-changenowflowstandard', $post) && $post['kr-adm-chk-changenowflowstandard'] == 'on') $flows[] = 'standard';
    if(array_key_exists('kr-adm-chk-changenowflowfixedrate', $post) && $post['kr-adm-chk-changenowflowfixedrate'] == 'on') $flows[] = 'fixed-rate';
    if(count($flows) == 0) $flows[] = 'standard';
    return implode(',', $flows);
  }

  private static function _checkbox($post, $key){
    return (array_key_exists($key, $post) && $post[$key] == 'on' ? '1' : '0');
  }

  private static function _postString($post, $key){
    if(!array_key_exists($key, $post)) return '';
    return trim((string) $post[$key]);
  }

  private static function _normalizeFlow($flow){
    $flow = strtolower(trim((string) $flow));
    if($flow == 'fixed') $flow = 'fixed-rate';
    if(!in_array($flow, self::_allowedFlows(), true)) return 'standard';
    return $flow;
  }

  private static function _normalizeAsset($value, $default){
    $value = strtolower(trim((string) $value));
    $value = preg_replace('/[^a-z0-9_-]/', '', $value);
    return ($value == '' ? $default : $value);
  }

  private static function _normalizeList($value, $stripPattern = '/[^a-z0-9_\\-,\\r\\n ]/i'){
    $value = preg_replace($stripPattern, '', (string) $value);
    $lines = preg_split('/[\\r\\n,]+/', $value);
    $items = [];
    foreach ($lines as $line) {
      $line = strtolower(trim($line));
      if($line != '' && !in_array($line, $items, true)) $items[] = $line;
    }
    return implode("\n", $items);
  }

  private static function _normalizeAmount($value, $default){
    $value = str_replace(',', '.', trim((string) $value));
    if(!preg_match('/^[0-9]+(\\.[0-9]{1,12})?$/', $value)) return $default;
    if(floatval($value) <= 0) return $default;
    return substr($value, 0, 32);
  }

  private static function _normalizeColor($value, $default){
    $value = strtoupper(ltrim(trim((string) $value), '#'));
    if(!preg_match('/^[A-F0-9]{6}$/', $value)) return $default;
    return $value;
  }

  private static function _normalizeLanguage($value, $default){
    $value = trim((string) $value);
    if(!preg_match('/^[a-z]{2}(-[A-Z]{2})?$/', $value)) return $default;
    return $value;
  }

  private static function _normalizePositiveInteger($value, $default){
    $value = trim((string) $value);
    if(!preg_match('/^[0-9]+$/', $value)) return $default;
    if(intval($value) < 1) return $default;
    return (string) intval($value);
  }

  private static function _normalizeChoice($value, $allowed, $default){
    $value = strtolower(trim((string) $value));
    if(!in_array($value, $allowed, true)) return $default;
    return $value;
  }

  private static function _sanitizeText($value, $maxLength){
    $value = trim(strip_tags((string) $value));
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
    return substr($value, 0, $maxLength);
  }

  private static function _boolValue($value){
    if(is_bool($value)) return $value;
    if(is_numeric($value)) return intval($value) == 1;
    return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
  }

}

?>
