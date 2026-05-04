<?php

/**
 * Read-only helpers for the ChangeNOW admin panel and support search.
 *
 * @package Krypto
 */
class ChangeNowAdminPanel {

  const TRANSACTION_TABLE = 'changenow_transactions_krypto';
  const EVENT_TABLE = 'changenow_transaction_events_krypto';

  public static function _statusSummary($settings){
    $settings = ChangeNowSettings::_sanitizeSettings($settings);
    $publicKeyPresent = trim((string) $settings['changenow_public_api_key']) != '';
    $privateKeyPresent = trim((string) $settings['changenow_private_api_key']) != '';
    $callbackSecretPresent = trim((string) $settings['changenow_callback_secret']) != '';

    $state = 'ready';
    $label = 'Ready';
    $tagClass = '';
    $detail = 'ChangeNOW is locally enabled and required configuration is present.';

    if($settings['changenow_provider_enabled'] != '1'){
      $state = 'disabled';
      $label = 'Local disabled';
      $tagClass = 'kr-admin-lst-tag-grey';
      $detail = $settings['changenow_local_disabled_reason'];
    } elseif(!$publicKeyPresent) {
      $state = 'missing_config';
      $label = 'Missing config';
      $tagClass = 'kr-admin-lst-tag-red';
      $detail = 'A public API key is required before swaps can be enabled.';
    } elseif($settings['changenow_provider_health_status'] == 'outage') {
      $state = 'provider_outage';
      $label = 'Provider outage';
      $tagClass = 'kr-admin-lst-tag-red';
      $detail = ($settings['changenow_provider_health_message'] == '' ? 'ChangeNOW health is marked as unavailable.' : $settings['changenow_provider_health_message']);
    } elseif($settings['changenow_provider_health_status'] == 'degraded') {
      $state = 'provider_degraded';
      $label = 'Provider degraded';
      $tagClass = 'kr-admin-lst-tag-orange';
      $detail = ($settings['changenow_provider_health_message'] == '' ? 'ChangeNOW health is marked as degraded.' : $settings['changenow_provider_health_message']);
    }

    return [
      'state' => $state,
      'label' => $label,
      'tagClass' => $tagClass,
      'detail' => $detail,
      'publicKeyPresent' => $publicKeyPresent,
      'privateKeyPresent' => $privateKeyPresent,
      'callbackSecretPresent' => $callbackSecretPresent,
      'enabledFlows' => ChangeNowSettings::_enabledFlowsToArray($settings['changenow_enabled_flows']),
      'lastSuccessfulSync' => self::_formatTimestamp($settings['changenow_last_successful_sync']),
      'rateLimitWarningState' => $settings['changenow_rate_limit_warning_state'],
      'providerHealth' => $settings['changenow_provider_health_status']
    ];
  }

  public static function _transactionFilterDefaults(){
    return [
      'search' => '',
      'provider_id' => '',
      'internal_id' => '',
      'user_email' => '',
      'anonymous_token' => '',
      'status' => '',
      'date_from' => '',
      'date_to' => '',
      'asset' => '',
      'referral_code' => ''
    ];
  }

  public static function _normalizeTransactionFilters($filters){
    if(!is_array($filters)) $filters = [];
    $normalized = [];
    foreach (self::_transactionFilterDefaults() as $key => $default) {
      $normalized[$key] = self::_sanitizeFilterValue(self::_value($filters, [$key], $default), 255);
    }
    $normalized['date_from'] = self::_sanitizeDate($normalized['date_from']);
    $normalized['date_to'] = self::_sanitizeDate($normalized['date_to']);
    $normalized['internal_id'] = preg_replace('/[^0-9]/', '', $normalized['internal_id']);
    $normalized['asset'] = strtolower(preg_replace('/[^a-z0-9_-]/i', '', $normalized['asset']));
    $normalized['status'] = strtolower(preg_replace('/[^a-z0-9_-]/i', '', $normalized['status']));
    $normalized['anonymous_token'] = preg_replace('/[^a-z0-9_-]/i', '', $normalized['anonymous_token']);
    $normalized['referral_code'] = preg_replace('/[^a-z0-9_-]/i', '', strtolower($normalized['referral_code']));
    return $normalized;
  }

  public static function _buildTransactionSearchQuery($filters, $limit = 100, $columns = null){
    $filters = self::_normalizeTransactionFilters($filters);
    $limit = self::_safeLimit($limit, 100, 500);
    $columns = (is_array($columns) ? $columns : self::_knownTransactionColumns());
    $hasColumn = function($column) use ($columns) {
      return in_array($column, $columns, true);
    };

    $selectColumns = [
      't.id_changenow_transaction',
      't.provider_id_changenow_transaction',
      't.id_user',
      'u.email_user',
      't.flow_changenow_transaction',
      't.from_currency_changenow_transaction',
      't.from_network_changenow_transaction',
      't.to_currency_changenow_transaction',
      't.to_network_changenow_transaction',
      't.from_amount_changenow_transaction',
      't.to_amount_changenow_transaction',
      't.status_changenow_transaction',
      't.created_at_changenow_transaction',
      't.updated_at_changenow_transaction'
    ];

    foreach ([
      'refund_available_changenow_transaction',
      'continue_available_changenow_transaction',
      'referral_attribution_changenow_transaction',
      'support_note_changenow_transaction',
      'payout_address_fingerprint_changenow_transaction',
      'lookup_token_fragment_changenow_transaction',
      'anonymous_lookup_token_fragment_changenow_transaction'
    ] as $optionalColumn) {
      if($hasColumn($optionalColumn)) $selectColumns[] = 't.'.$optionalColumn;
    }

    $where = [];
    $params = [];

    if($filters['search'] != ''){
      $queryParts = [
        't.provider_id_changenow_transaction LIKE :query_search',
        'CAST(t.id_changenow_transaction AS CHAR) LIKE :query_search',
        'u.email_user LIKE :query_search',
        't.status_changenow_transaction LIKE :query_search',
        't.from_currency_changenow_transaction LIKE :query_search',
        't.from_network_changenow_transaction LIKE :query_search',
        't.to_currency_changenow_transaction LIKE :query_search',
        't.to_network_changenow_transaction LIKE :query_search'
      ];
      if($hasColumn('lookup_token_fragment_changenow_transaction')) $queryParts[] = 't.lookup_token_fragment_changenow_transaction LIKE :query_search';
      if($hasColumn('anonymous_lookup_token_fragment_changenow_transaction')) $queryParts[] = 't.anonymous_lookup_token_fragment_changenow_transaction LIKE :query_search';
      if($hasColumn('referral_attribution_changenow_transaction')) $queryParts[] = 't.referral_attribution_changenow_transaction LIKE :query_search';
      $where[] = '('.implode(' OR ', $queryParts).')';
      $params['query_search'] = '%'.$filters['search'].'%';
    }

    if($filters['provider_id'] != ''){
      $where[] = 't.provider_id_changenow_transaction LIKE :provider_id';
      $params['provider_id'] = '%'.$filters['provider_id'].'%';
    }

    if($filters['internal_id'] != ''){
      $where[] = 't.id_changenow_transaction=:internal_id';
      $params['internal_id'] = $filters['internal_id'];
    }

    if($filters['user_email'] != ''){
      $where[] = 'u.email_user LIKE :user_email';
      $params['user_email'] = '%'.$filters['user_email'].'%';
    }

    if($filters['anonymous_token'] != ''){
      $anonymousParts = [];
      if($hasColumn('lookup_token_fragment_changenow_transaction')) $anonymousParts[] = 't.lookup_token_fragment_changenow_transaction LIKE :anonymous_token';
      if($hasColumn('anonymous_lookup_token_fragment_changenow_transaction')) $anonymousParts[] = 't.anonymous_lookup_token_fragment_changenow_transaction LIKE :anonymous_token';
      if($hasColumn('lookup_token_hash_changenow_transaction')) $anonymousParts[] = 't.lookup_token_hash_changenow_transaction=:anonymous_token_hash';
      if($hasColumn('anonymous_lookup_token_hash_changenow_transaction')) $anonymousParts[] = 't.anonymous_lookup_token_hash_changenow_transaction=:anonymous_token_hash';
      if(count($anonymousParts) > 0){
        $where[] = '('.implode(' OR ', $anonymousParts).')';
        if($hasColumn('lookup_token_fragment_changenow_transaction') || $hasColumn('anonymous_lookup_token_fragment_changenow_transaction')){
          $params['anonymous_token'] = '%'.$filters['anonymous_token'].'%';
        }
        if($hasColumn('lookup_token_hash_changenow_transaction') || $hasColumn('anonymous_lookup_token_hash_changenow_transaction')){
          $params['anonymous_token_hash'] = hash('sha256', $filters['anonymous_token']);
        }
      }
    }

    if($filters['status'] != ''){
      $where[] = 't.status_changenow_transaction=:status';
      $params['status'] = $filters['status'];
    }

    if($filters['date_from'] != ''){
      $where[] = 'CAST(t.created_at_changenow_transaction AS UNSIGNED) >= :date_from';
      $params['date_from'] = strtotime($filters['date_from'].' 00:00:00');
    }

    if($filters['date_to'] != ''){
      $where[] = 'CAST(t.created_at_changenow_transaction AS UNSIGNED) <= :date_to';
      $params['date_to'] = strtotime($filters['date_to'].' 23:59:59');
    }

    if($filters['asset'] != ''){
      $where[] = '(t.from_currency_changenow_transaction=:asset OR t.from_network_changenow_transaction=:asset OR t.to_currency_changenow_transaction=:asset OR t.to_network_changenow_transaction=:asset)';
      $params['asset'] = $filters['asset'];
    }

    if($filters['referral_code'] != '' && $hasColumn('referral_attribution_changenow_transaction')){
      $where[] = 't.referral_attribution_changenow_transaction LIKE :referral_code';
      $params['referral_code'] = '%'.$filters['referral_code'].'%';
    }

    $sql = 'SELECT '.implode(', ', $selectColumns).' FROM '.self::TRANSACTION_TABLE.' t LEFT JOIN user_krypto u ON t.id_user=u.id_user';
    if(count($where) > 0) $sql .= ' WHERE '.implode(' AND ', $where);
    $sql .= ' ORDER BY CAST(t.updated_at_changenow_transaction AS UNSIGNED) DESC, t.id_changenow_transaction DESC LIMIT '.$limit;

    return [
      'sql' => $sql,
      'params' => $params,
      'limit' => $limit
    ];
  }

  public static function _supportActionAllowed($transaction, $action){
    if(!is_array($transaction)) return false;
    $action = strtolower(trim((string) $action));
    if($action == 'note' || $action == 'refresh') return true;
    if($action == 'refund') return self::_boolValue(self::_value($transaction, ['refundAvailable', 'refund_available_changenow_transaction'], false));
    if($action == 'continue') return self::_boolValue(self::_value($transaction, ['continueAvailable', 'continue_available_changenow_transaction'], false));
    return false;
  }

  public static function _maskSecret($value){
    return (trim((string) $value) == '' ? '' : ChangeNowSettings::SECRET_MASK);
  }

  public static function _formatTimestamp($value){
    $value = trim((string) $value);
    if($value == '' || $value == '0') return 'Never';
    if(is_numeric($value)) return date('d/m/Y H:i:s', intval($value));
    return $value;
  }

  public static function _safeLimit($limit, $default, $max){
    $limit = intval($limit);
    if($limit <= 0) $limit = $default;
    if($limit > $max) $limit = $max;
    return $limit;
  }

  public static function _knownTransactionColumns(){
    return [
      'id_changenow_transaction',
      'provider_id_changenow_transaction',
      'id_user',
      'flow_changenow_transaction',
      'from_currency_changenow_transaction',
      'from_network_changenow_transaction',
      'to_currency_changenow_transaction',
      'to_network_changenow_transaction',
      'from_amount_changenow_transaction',
      'to_amount_changenow_transaction',
      'status_changenow_transaction',
      'refund_available_changenow_transaction',
      'continue_available_changenow_transaction',
      'referral_attribution_changenow_transaction',
      'raw_status_changenow_transaction',
      'support_note_changenow_transaction',
      'created_at_changenow_transaction',
      'updated_at_changenow_transaction',
      'payout_address_fingerprint_changenow_transaction',
      'lookup_token_hash_changenow_transaction',
      'anonymous_lookup_token_hash_changenow_transaction'
    ];
  }

  private static function _sanitizeFilterValue($value, $maxLength){
    $value = trim(strip_tags((string) $value));
    $value = preg_replace('/[\x00-\x1F\x7F]/', '', $value);
    return substr($value, 0, $maxLength);
  }

  private static function _sanitizeDate($value){
    $value = trim((string) $value);
    if($value == '') return '';
    if(!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $value)) return '';
    return $value;
  }

  private static function _boolValue($value){
    if(is_bool($value)) return $value;
    if(is_numeric($value)) return intval($value) == 1;
    return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on', 'available'], true);
  }

  private static function _value($source, $keys, $default = null){
    if(!is_array($source)) return $default;
    foreach ($keys as $key) {
      if(array_key_exists($key, $source)) return $source[$key];
    }
    return $default;
  }

}

?>
