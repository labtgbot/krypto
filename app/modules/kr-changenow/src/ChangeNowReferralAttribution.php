<?php

/**
 * Normalizes referral attribution for ChangeNOW swaps.
 *
 * The attribution payload is intentionally explicit so support can separate
 * ChangeNOW partner attribution from Krypto's internal referral rewards.
 *
 * @package Krypto
 */
class ChangeNowReferralAttribution {

  const SESSION_REFERRAL_CODE_KEY = 'referal_source_krypto';
  const SESSION_REFERRAL_CAPTURED_AT_KEY = 'referal_source_captured_at_krypto';
  const SESSION_UTM_KEY = 'changenow_referral_utm_krypto';
  const COOKIE_REFERRAL_CODE_KEY = 'referal_source_krypto';
  const COOKIE_TTL = 2592000;

  public static function _captureLanding($request, &$session, $referralCodeOwnerResolver = null, $now = null){
    if(!is_array($request)) $request = [];
    if(!is_array($session)) $session = [];

    $now = self::_timestamp($now);
    $result = [
      'captured' => false,
      'referralCode' => '',
      'utm' => []
    ];

    $code = self::_referralCodeFromSource($request);
    if($code == '' && !array_key_exists(self::SESSION_REFERRAL_CODE_KEY, $session) && isset($_COOKIE[self::COOKIE_REFERRAL_CODE_KEY])){
      $code = self::_sanitizeReferralCode($_COOKIE[self::COOKIE_REFERRAL_CODE_KEY]);
    }

    if($code != '' && self::_codeAllowed($code, $referralCodeOwnerResolver)){
      if(!array_key_exists(self::SESSION_REFERRAL_CODE_KEY, $session) || $session[self::SESSION_REFERRAL_CODE_KEY] !== $code){
        $session[self::SESSION_REFERRAL_CODE_KEY] = $code;
        $session[self::SESSION_REFERRAL_CAPTURED_AT_KEY] = $now;
      }
      $result['captured'] = true;
      $result['referralCode'] = $code;

      if(!headers_sent()){
        setcookie(self::COOKIE_REFERRAL_CODE_KEY, $code, $now + self::COOKIE_TTL, '/');
      }
    }

    $utm = self::_utmFromSource($request);
    if(count($utm) > 0){
      $session[self::SESSION_UTM_KEY] = $utm;
      $result['captured'] = true;
      $result['utm'] = $utm;
    }

    return $result;
  }

  public static function _fromRequest($request, $session = [], $options = []){
    if(!is_array($request)) $request = [];
    if(!is_array($session)) $session = [];
    if(!is_array($options)) $options = [];

    $now = self::_timestamp(self::_value($options, ['now'], null));
    $loggedInUserId = trim((string) self::_value($options, ['loggedInUserId', 'userId', 'logged_in_user_id'], ''));
    $ownerResolver = self::_value($options, ['referralCodeOwnerResolver', 'referral_owner_resolver'], null);

    $attribution = [
      'sources' => [],
      'capturedAt' => $now,
      'commissionState' => 'pending_provider_confirmation'
    ];

    $requestCode = self::_referralCodeFromSource($request);
    $sessionCode = self::_sanitizeReferralCode(self::_value($session, [self::SESSION_REFERRAL_CODE_KEY], ''));
    $selectedInternal = null;
    $blockedInternal = null;
    $requestCodeBlocked = false;

    if($requestCode != ''){
      $selectedInternal = self::_internalReferral($requestCode, 'request', $loggedInUserId, $ownerResolver, $blockedInternal);
      $requestCodeBlocked = is_array($blockedInternal);
    }

    if(!is_array($selectedInternal) && !$requestCodeBlocked && $sessionCode != ''){
      $selectedInternal = self::_internalReferral($sessionCode, 'session', $loggedInUserId, $ownerResolver, $blockedInternal);
    }

    if(is_array($selectedInternal)){
      $attribution['internal'] = $selectedInternal;
      $attribution['sources'][] = 'internal_referral';
    }

    if(is_array($blockedInternal)){
      $attribution['blockedInternalReferral'] = $blockedInternal;
    }

    $utm = self::_utmFromSource($session);
    $requestUtm = self::_utmFromSource($request);
    foreach ($requestUtm as $key => $value) {
      $utm[$key] = $value;
    }

    if(count($utm) > 0){
      $attribution['utm'] = $utm;
      $attribution['sources'][] = 'utm';
    }

    $changeNowReferralLinkId = self::_sanitizeText(self::_value($options, ['changeNowReferralLinkId', 'changenowReferralLinkId', 'change_now_referral_link_id', 'changenow_referral_link_id'], ''));
    if($changeNowReferralLinkId == ''){
      $changeNowReferralLinkId = self::_sanitizeText(self::_value($request, ['changeNowReferralLinkId', 'changenowReferralLinkId', 'change_now_referral_link_id', 'changenow_referral_link_id'], ''));
    }

    if($changeNowReferralLinkId != ''){
      $attribution['changeNow'] = [
        'referralLinkId' => $changeNowReferralLinkId
      ];
      $attribution['sources'][] = 'changenow_partner';
    }

    if($loggedInUserId != '' && (count($attribution['sources']) > 0 || array_key_exists('blockedInternalReferral', $attribution))){
      $attribution['loggedInUserId'] = (string) $loggedInUserId;
      $attribution['sources'][] = 'logged_in_user';
    }

    $attribution['sources'] = array_values(array_unique($attribution['sources']));
    if(count($attribution['sources']) == 0 && !array_key_exists('blockedInternalReferral', $attribution)) return [];
    return $attribution;
  }

  public static function _internalCode($attribution){
    if(!is_array($attribution)) return '';
    return self::_sanitizeReferralCode(self::_value($attribution, ['internal.code'], ''));
  }

  public static function _changeNowReferralLinkId($attribution){
    if(!is_array($attribution)) return '';
    return self::_sanitizeText(self::_value($attribution, ['changeNow.referralLinkId', 'changenow.referralLinkId'], ''));
  }

  public static function _utmCampaign($attribution){
    if(!is_array($attribution)) return '';
    return self::_sanitizeText(self::_value($attribution, ['utm.campaign'], ''));
  }

  public static function _hasAttribution($attribution){
    if(!is_array($attribution) || count($attribution) == 0) return false;
    return self::_internalCode($attribution) != ''
      || self::_changeNowReferralLinkId($attribution) != ''
      || self::_utmCampaign($attribution) != ''
      || array_key_exists('blockedInternalReferral', $attribution);
  }

  public static function _commissionStateForStatus($status){
    $status = strtolower(trim((string) $status));
    if(in_array($status, ['finished', 'completed', 'complete', 'success'], true)) return 'pending_admin_review';
    if(in_array($status, ['failed', 'refunded', 'expired', 'overdue', 'rejected'], true)) return 'not_eligible';
    return 'pending_provider_confirmation';
  }

  public static function _commissionStateLabel($state){
    $state = strtolower(trim((string) $state));
    if($state == 'pending_admin_review') return 'Pending admin review';
    if($state == 'not_eligible') return 'Not eligible';
    return 'Pending provider confirmation';
  }

  public static function _sanitizeReferralCode($value){
    $value = strtolower(trim((string) $value));
    $value = preg_replace('/[^a-z0-9_-]/', '', $value);
    return substr($value, 0, 200);
  }

  private static function _internalReferral($code, $source, $loggedInUserId, $ownerResolver, &$blockedInternal){
    $code = self::_sanitizeReferralCode($code);
    if($code == '' || !self::_codeAllowed($code, $ownerResolver)) return null;

    $known = false;
    $ownerUserId = self::_ownerForCode($code, $ownerResolver, $known);
    if($known && trim((string) $ownerUserId) != '' && trim((string) $ownerUserId) === trim((string) $loggedInUserId)){
      $blockedInternal = [
        'code' => $code,
        'ownerUserId' => $ownerUserId,
        'reason' => 'self_referral',
        'source' => $source
      ];
      return null;
    }

    $internal = [
      'code' => $code,
      'source' => $source,
      'rewardState' => 'pending_provider_confirmation'
    ];

    if($known && trim((string) $ownerUserId) != '') $internal['ownerUserId'] = $ownerUserId;
    return $internal;
  }

  private static function _codeAllowed($code, $ownerResolver){
    $code = self::_sanitizeReferralCode($code);
    if($code == '') return false;
    if(!is_callable($ownerResolver)) return true;

    $known = false;
    $owner = self::_ownerForCode($code, $ownerResolver, $known);
    return $known && !is_null($owner) && trim((string) $owner) != '';
  }

  private static function _ownerForCode($code, $ownerResolver, &$known){
    $known = false;
    if(!is_callable($ownerResolver)) return null;

    $known = true;
    return call_user_func($ownerResolver, $code);
  }

  private static function _referralCodeFromSource($source){
    $code = self::_value($source, ['ref', 'referal', 'referral', 'referralCode', 'referral_code', 'code_referal'], '');
    return self::_sanitizeReferralCode($code);
  }

  private static function _utmFromSource($source){
    if(!is_array($source)) return [];
    if(array_key_exists(self::SESSION_UTM_KEY, $source) && is_array($source[self::SESSION_UTM_KEY])){
      $source = $source[self::SESSION_UTM_KEY];
    }

    $utm = [];
    foreach ([
      'source' => ['utm_source', 'source'],
      'medium' => ['utm_medium', 'medium'],
      'campaign' => ['utm_campaign', 'campaign'],
      'term' => ['utm_term', 'term'],
      'content' => ['utm_content', 'content']
    ] as $target => $keys) {
      $value = self::_sanitizeText(self::_value($source, $keys, ''));
      if($value != '') $utm[$target] = $value;
    }
    return $utm;
  }

  private static function _sanitizeText($value, $maxLength = 255){
    $value = trim(strip_tags((string) $value));
    $value = preg_replace('/[\x00-\x1F\x7F]/', '', $value);
    return substr($value, 0, $maxLength);
  }

  private static function _timestamp($value){
    if(is_null($value) || trim((string) $value) == '') return time();
    return intval($value);
  }

  private static function _value($source, $keys, $default = null){
    if(!is_array($source)) return $default;
    foreach ($keys as $key) {
      if(strpos($key, '.') !== false){
        $parts = explode('.', $key);
        $current = $source;
        $found = true;
        foreach ($parts as $part) {
          if(!is_array($current) || !array_key_exists($part, $current)){
            $found = false;
            break;
          }
          $current = $current[$part];
        }
        if($found) return $current;
        continue;
      }

      if(array_key_exists($key, $source)) return $source[$key];
    }
    return $default;
  }

}

?>
