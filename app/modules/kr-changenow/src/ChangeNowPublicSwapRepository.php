<?php

/**
 * Anonymous ChangeNOW transaction storage for the public swap flow.
 *
 * Lookup tokens are stored only as SHA-256 hashes. The plaintext token is
 * returned to the visitor once and can be kept in the session or URL.
 *
 * @package Krypto
 */
class ChangeNowPublicSwapRepository extends MySQL {

  private $SchemaReady = false;

  public function __construct($ensureSchema = true){
    if($ensureSchema) $this->_ensureSchema();
  }

  public function _ensureSchema(){
    if($this->SchemaReady) return true;

    foreach ($this->_schemaSql() as $sql) {
      parent::execSqlRequest($sql);
    }

    foreach ($this->_upgradeSql() as $sql) {
      $this->_trySchemaSql($sql);
    }

    $this->SchemaReady = true;
    return true;
  }

  public function _saveCreatedSwap($request, $transaction, $lookupToken, $sessionKey, $userId = null, $createdAt = null){
    $this->_ensureSchema();

    $providerId = $this->_value($transaction, ['id'], '');
    if(trim((string) $providerId) == '') throw new Exception('ChangeNOW transaction id is required before saving public swap state.', 1);

    $createdAt = (is_null($createdAt) ? time() : $createdAt);
    $expiresAt = $this->_timestampFromProviderValue($this->_value($transaction, ['validUntil'], $this->_value($request, ['validUntil'], null)));
    $status = $this->_value($transaction, ['status'], 'waiting');
    $payoutAddress = $this->_value($transaction, ['payoutAddress'], $this->_value($request, ['destinationAddress'], ''));
    $actions = $this->_availableActionsFromPayload($transaction);
    $referralAttribution = $this->_referralAttributionFromRequest($request);

    parent::execSqlRequest("INSERT INTO changenow_transactions_krypto
                            (provider_id_changenow_transaction, lookup_token_hash_changenow_transaction, session_key_changenow_transaction,
                             id_user, flow_changenow_transaction, from_currency_changenow_transaction, from_network_changenow_transaction,
                             to_currency_changenow_transaction, to_network_changenow_transaction, from_amount_changenow_transaction,
                             to_amount_changenow_transaction, payin_address_changenow_transaction, payin_extra_id_changenow_transaction,
                             payout_address_changenow_transaction, payout_extra_id_changenow_transaction,
                             payout_address_fingerprint_changenow_transaction, refund_address_changenow_transaction,
                             refund_extra_id_changenow_transaction, status_changenow_transaction, refund_available_changenow_transaction,
                             continue_available_changenow_transaction, referral_attribution_changenow_transaction,
                             raw_create_changenow_transaction, raw_status_changenow_transaction, raw_actions_changenow_transaction,
                             support_note_changenow_transaction, created_at_changenow_transaction, updated_at_changenow_transaction,
                             expires_at_changenow_transaction)
                            VALUES (:provider_id, :lookup_hash, :session_key, :id_user, :flow_swap, :from_currency, :from_network,
                                    :to_currency, :to_network, :from_amount, :to_amount, :payin_address, :payin_extra_id,
                                    :payout_address, :payout_extra_id, :payout_fingerprint, :refund_address, :refund_extra_id,
                                    :status_swap, :refund_available, :continue_available, :referral_attribution, :raw_create,
                                    :raw_status, :raw_actions, :support_note, :created_at, :updated_at, :expires_at)
                            ON DUPLICATE KEY UPDATE
                              status_changenow_transaction=:status_update,
                              payout_address_fingerprint_changenow_transaction=:payout_fingerprint_update,
                              refund_available_changenow_transaction=:refund_available_update,
                              continue_available_changenow_transaction=:continue_available_update,
                              referral_attribution_changenow_transaction=:referral_attribution_update,
                              raw_create_changenow_transaction=:raw_create_update,
                              raw_actions_changenow_transaction=:raw_actions_update,
                              updated_at_changenow_transaction=:updated_at_update",
                            [
                              'provider_id' => $providerId,
                              'lookup_hash' => self::_lookupTokenHash($lookupToken),
                              'session_key' => self::_sessionKeyHash($sessionKey),
                              'id_user' => $userId,
                              'flow_swap' => $this->_value($transaction, ['flow'], $this->_value($request, ['flow'], 'standard')),
                              'from_currency' => $this->_value($transaction, ['fromCurrency'], $this->_value($request, ['fromCurrency'], '')),
                              'from_network' => $this->_value($transaction, ['fromNetwork'], $this->_value($request, ['fromNetwork'], '')),
                              'to_currency' => $this->_value($transaction, ['toCurrency'], $this->_value($request, ['toCurrency'], '')),
                              'to_network' => $this->_value($transaction, ['toNetwork'], $this->_value($request, ['toNetwork'], '')),
                              'from_amount' => $this->_value($transaction, ['fromAmount'], $this->_value($request, ['amount'], '')),
                              'to_amount' => $this->_value($transaction, ['toAmount'], ''),
                              'payin_address' => $this->_value($transaction, ['payinAddress'], ''),
                              'payin_extra_id' => $this->_value($transaction, ['payinExtraId'], ''),
                              'payout_address' => $payoutAddress,
                              'payout_extra_id' => $this->_value($transaction, ['payoutExtraId'], $this->_value($request, ['destinationExtraId'], '')),
                              'payout_fingerprint' => $this->_addressFingerprint($payoutAddress),
                              'refund_address' => $this->_value($transaction, ['refundAddress'], $this->_value($request, ['refundAddress'], '')),
                              'refund_extra_id' => $this->_value($transaction, ['refundExtraId'], $this->_value($request, ['refundExtraId'], '')),
                              'status_swap' => $status,
                              'refund_available' => ($actions['refund'] ? 1 : 0),
                              'continue_available' => ($actions['continue'] ? 1 : 0),
                              'referral_attribution' => $this->_jsonEncode($referralAttribution),
                              'raw_create' => $this->_jsonEncode($transaction),
                              'raw_status' => '',
                              'raw_actions' => $this->_jsonEncode($this->_rawActionsFromPayload($transaction)),
                              'support_note' => '',
                              'created_at' => $createdAt,
                              'updated_at' => $createdAt,
                              'expires_at' => $expiresAt,
                              'status_update' => $status,
                              'payout_fingerprint_update' => $this->_addressFingerprint($payoutAddress),
                              'refund_available_update' => ($actions['refund'] ? 1 : 0),
                              'continue_available_update' => ($actions['continue'] ? 1 : 0),
                              'referral_attribution_update' => $this->_jsonEncode($referralAttribution),
                              'raw_create_update' => $this->_jsonEncode($transaction),
                              'raw_actions_update' => $this->_jsonEncode($this->_rawActionsFromPayload($transaction)),
                              'updated_at_update' => $createdAt
                            ]);

    return $this->_findByLookupToken($lookupToken);
  }

  public function _findByLookupToken($lookupToken){
    $this->_ensureSchema();
    $rows = parent::querySqlRequest("SELECT * FROM changenow_transactions_krypto
                                     WHERE lookup_token_hash_changenow_transaction=:lookup_hash
                                     LIMIT 1",
                                     [
                                      'lookup_hash' => self::_lookupTokenHash($lookupToken)
                                     ]);
    if(count($rows) == 0) return null;
    return $this->_mapRow($rows[0]);
  }

  public function _findByProviderId($providerId){
    $this->_ensureSchema();
    $providerId = trim((string) $providerId);
    if($providerId == '') return null;

    $rows = parent::querySqlRequest("SELECT * FROM changenow_transactions_krypto
                                     WHERE provider_id_changenow_transaction=:provider_id
                                     LIMIT 1",
                                     [
                                      'provider_id' => $providerId
                                     ]);
    if(count($rows) == 0) return null;
    return $this->_mapRow($rows[0]);
  }

  public function _listByUser($userId, $limit = 50){
    $this->_ensureSchema();
    $limit = $this->_safeLimit($limit, 50, 200);
    $rows = parent::querySqlRequest("SELECT * FROM changenow_transactions_krypto
                                     WHERE id_user=:id_user
                                     ORDER BY updated_at_changenow_transaction DESC, id_changenow_transaction DESC
                                     LIMIT ".$limit,
                                     [
                                      'id_user' => $userId
                                     ]);
    return $this->_mapRows($rows);
  }

  public function _listForSupport($filters = [], $limit = 100){
    $this->_ensureSchema();
    $limit = $this->_safeLimit($limit, 100, 500);
    if(!is_array($filters)) $filters = [];

    $where = [];
    $params = [];
    $query = trim((string) $this->_value($filters, ['query', 'q', 'search'], ''));
    $status = trim((string) $this->_value($filters, ['status'], ''));
    $userId = trim((string) $this->_value($filters, ['userId', 'id_user', 'user'], ''));

    if($query != ''){
      $where[] = "(provider_id_changenow_transaction LIKE :query_search
                  OR CAST(id_user AS CHAR) LIKE :query_search
                  OR from_currency_changenow_transaction LIKE :query_search
                  OR to_currency_changenow_transaction LIKE :query_search
                  OR status_changenow_transaction LIKE :query_search)";
      $params['query_search'] = '%'.$query.'%';
    }

    if($status != ''){
      $where[] = "status_changenow_transaction=:status_swap";
      $params['status_swap'] = $status;
    }

    if($userId != ''){
      $where[] = "id_user=:id_user";
      $params['id_user'] = $userId;
    }

    $sql = "SELECT * FROM changenow_transactions_krypto";
    if(count($where) > 0) $sql .= " WHERE ".implode(" AND ", $where);
    $sql .= " ORDER BY updated_at_changenow_transaction DESC, id_changenow_transaction DESC LIMIT ".$limit;

    return $this->_mapRows(parent::querySqlRequest($sql, $params));
  }

  public function _updateStatusSnapshot($lookupToken, $statusPayload, $updatedAt = null){
    $lookupToken = trim((string) $lookupToken);
    $previous = $this->_findByLookupToken($lookupToken);
    if(!is_array($previous)) return null;

    $this->_updateStatusSnapshotByColumn('lookup_token_hash_changenow_transaction', self::_lookupTokenHash($lookupToken), $previous, $statusPayload, $updatedAt);
    return $this->_findByLookupToken($lookupToken);
  }

  public function _updateStatusSnapshotByProviderId($providerId, $statusPayload, $updatedAt = null){
    $providerId = trim((string) $providerId);
    $previous = $this->_findByProviderId($providerId);
    if(!is_array($previous)) return null;

    $this->_updateStatusSnapshotByColumn('provider_id_changenow_transaction', $providerId, $previous, $statusPayload, $updatedAt);
    return $this->_findByProviderId($providerId);
  }

  public function _saveSupportNote($providerId, $note, $actorUserId = null, $actorType = 'support'){
    $this->_ensureSchema();
    $providerId = trim((string) $providerId);
    if($providerId == '') return false;

    parent::execSqlRequest("UPDATE changenow_transactions_krypto SET
                              support_note_changenow_transaction=:support_note,
                              updated_at_changenow_transaction=:updated_at
                            WHERE provider_id_changenow_transaction=:provider_id",
                            [
                              'support_note' => trim((string) $note),
                              'updated_at' => time(),
                              'provider_id' => $providerId
                            ]);

    $this->_recordEvent($providerId, 'support_note', 'saved', $actorUserId, $actorType, trim((string) $note), []);
    return $this->_findByProviderId($providerId);
  }

  public function _recordEvent($providerIdOrRecord, $eventType, $eventStatus, $actorUserId = null, $actorType = 'system', $note = '', $rawEvent = []){
    $this->_ensureSchema();
    $record = (is_array($providerIdOrRecord) ? $providerIdOrRecord : $this->_findByProviderId($providerIdOrRecord));
    $providerId = (is_array($record) ? $this->_value($record, ['providerId'], '') : trim((string) $providerIdOrRecord));
    if($providerId == '') return false;

    parent::execSqlRequest("INSERT INTO changenow_transaction_events_krypto
                            (id_changenow_transaction, provider_id_changenow_transaction, actor_user_id_changenow_transaction_event,
                             actor_type_changenow_transaction_event, event_type_changenow_transaction_event,
                             event_status_changenow_transaction_event, event_note_changenow_transaction_event,
                             raw_event_changenow_transaction_event, created_at_changenow_transaction_event)
                            VALUES (:transaction_id, :provider_id, :actor_user_id, :actor_type, :event_type,
                                    :event_status, :event_note, :raw_event, :created_at)",
                            [
                              'transaction_id' => (is_array($record) ? $this->_value($record, ['id'], null) : null),
                              'provider_id' => $providerId,
                              'actor_user_id' => $actorUserId,
                              'actor_type' => trim((string) $actorType),
                              'event_type' => trim((string) $eventType),
                              'event_status' => trim((string) $eventStatus),
                              'event_note' => trim((string) $note),
                              'raw_event' => $this->_jsonEncode($rawEvent),
                              'created_at' => time()
                            ]);
    return true;
  }

  public function _listEventsForProvider($providerId, $limit = 25){
    $this->_ensureSchema();
    $limit = $this->_safeLimit($limit, 25, 200);
    $rows = parent::querySqlRequest("SELECT * FROM changenow_transaction_events_krypto
                                     WHERE provider_id_changenow_transaction=:provider_id
                                     ORDER BY id_changenow_transaction_event DESC
                                     LIMIT ".$limit,
                                     [
                                      'provider_id' => trim((string) $providerId)
                                     ]);
    return $this->_mapEventRows($rows);
  }

  public static function _lookupTokenHash($lookupToken){
    return hash('sha256', trim((string) $lookupToken));
  }

  public static function _sessionKeyHash($sessionKey){
    return hash('sha256', trim((string) $sessionKey));
  }

  private function _updateStatusSnapshotByColumn($column, $columnValue, $previous, $statusPayload, $updatedAt = null){
    $this->_ensureSchema();
    $updatedAt = (is_null($updatedAt) ? time() : $updatedAt);
    $status = $this->_value($statusPayload, ['status'], $this->_value($previous, ['status'], 'waiting'));
    $providerId = $this->_value($statusPayload, ['id'], '');
    $expiresAt = $this->_timestampFromProviderValue($this->_value($statusPayload, ['validUntil'], null));
    $payoutAddress = $this->_value($statusPayload, ['payoutAddress'], '');
    $actions = $this->_availableActionsFromPayload($statusPayload);

    $params = [
      'column_value' => $columnValue,
      'status_swap' => $status,
      'raw_status' => $this->_jsonEncode($statusPayload),
      'raw_actions' => $this->_jsonEncode($this->_rawActionsFromPayload($statusPayload)),
      'refund_available' => ($actions['refund'] ? 1 : 0),
      'continue_available' => ($actions['continue'] ? 1 : 0),
      'updated_at' => $updatedAt,
      'provider_id' => $providerId,
      'from_amount' => $this->_value($statusPayload, ['amountFrom', 'expectedAmountFrom'], ''),
      'to_amount' => $this->_value($statusPayload, ['amountTo', 'expectedAmountTo'], ''),
      'payin_address' => $this->_value($statusPayload, ['payinAddress'], ''),
      'payin_extra_id' => $this->_value($statusPayload, ['payinExtraId'], ''),
      'payout_address' => $payoutAddress,
      'payout_fingerprint' => $this->_addressFingerprint($payoutAddress),
      'payout_extra_id' => $this->_value($statusPayload, ['payoutExtraId'], ''),
      'refund_address' => $this->_value($statusPayload, ['refundAddress'], ''),
      'refund_extra_id' => $this->_value($statusPayload, ['refundExtraId'], ''),
      'expires_at' => $expiresAt
    ];

    parent::execSqlRequest("UPDATE changenow_transactions_krypto SET
                              status_changenow_transaction=:status_swap,
                              raw_status_changenow_transaction=:raw_status,
                              raw_actions_changenow_transaction=:raw_actions,
                              refund_available_changenow_transaction=:refund_available,
                              continue_available_changenow_transaction=:continue_available,
                              updated_at_changenow_transaction=:updated_at,
                              provider_id_changenow_transaction=COALESCE(NULLIF(:provider_id, ''), provider_id_changenow_transaction),
                              from_amount_changenow_transaction=COALESCE(NULLIF(:from_amount, ''), from_amount_changenow_transaction),
                              to_amount_changenow_transaction=COALESCE(NULLIF(:to_amount, ''), to_amount_changenow_transaction),
                              payin_address_changenow_transaction=COALESCE(NULLIF(:payin_address, ''), payin_address_changenow_transaction),
                              payin_extra_id_changenow_transaction=COALESCE(NULLIF(:payin_extra_id, ''), payin_extra_id_changenow_transaction),
                              payout_address_changenow_transaction=COALESCE(NULLIF(:payout_address, ''), payout_address_changenow_transaction),
                              payout_address_fingerprint_changenow_transaction=COALESCE(NULLIF(:payout_fingerprint, ''), payout_address_fingerprint_changenow_transaction),
                              payout_extra_id_changenow_transaction=COALESCE(NULLIF(:payout_extra_id, ''), payout_extra_id_changenow_transaction),
                              refund_address_changenow_transaction=COALESCE(NULLIF(:refund_address, ''), refund_address_changenow_transaction),
                              refund_extra_id_changenow_transaction=COALESCE(NULLIF(:refund_extra_id, ''), refund_extra_id_changenow_transaction),
                              expires_at_changenow_transaction=COALESCE(NULLIF(:expires_at, '0'), expires_at_changenow_transaction)
                            WHERE ".$column."=:column_value",
                            $params);

    if($this->_value($previous, ['status'], '') != $status){
      $this->_recordEvent($providerId == '' ? $previous : $providerId, 'status', $status, null, 'system', '', $statusPayload);
    }

    return true;
  }

  private function _mapRows($rows){
    $result = [];
    foreach ($rows as $row) {
      $result[] = $this->_mapRow($row);
    }
    return $result;
  }

  private function _mapRow($row){
    $rawCreate = json_decode($this->_value($row, ['raw_create_changenow_transaction'], ''), true);
    $rawStatus = json_decode($this->_value($row, ['raw_status_changenow_transaction'], ''), true);
    $rawActions = json_decode($this->_value($row, ['raw_actions_changenow_transaction'], ''), true);
    $referralAttribution = json_decode($this->_value($row, ['referral_attribution_changenow_transaction'], ''), true);
    $refundAvailable = $this->_boolValue($this->_value($row, ['refund_available_changenow_transaction'], 0));
    $continueAvailable = $this->_boolValue($this->_value($row, ['continue_available_changenow_transaction'], 0));

    return [
      'id' => $this->_value($row, ['id_changenow_transaction'], null),
      'providerId' => $this->_value($row, ['provider_id_changenow_transaction'], ''),
      'userId' => $this->_value($row, ['id_user'], null),
      'flow' => $this->_value($row, ['flow_changenow_transaction'], ''),
      'fromCurrency' => $this->_value($row, ['from_currency_changenow_transaction'], ''),
      'fromNetwork' => $this->_value($row, ['from_network_changenow_transaction'], ''),
      'toCurrency' => $this->_value($row, ['to_currency_changenow_transaction'], ''),
      'toNetwork' => $this->_value($row, ['to_network_changenow_transaction'], ''),
      'fromAmount' => $this->_value($row, ['from_amount_changenow_transaction'], ''),
      'toAmount' => $this->_value($row, ['to_amount_changenow_transaction'], ''),
      'payinAddress' => $this->_value($row, ['payin_address_changenow_transaction'], ''),
      'payinExtraId' => $this->_value($row, ['payin_extra_id_changenow_transaction'], ''),
      'payoutAddress' => $this->_value($row, ['payout_address_changenow_transaction'], ''),
      'payoutExtraId' => $this->_value($row, ['payout_extra_id_changenow_transaction'], ''),
      'payoutAddressFingerprint' => $this->_value($row, ['payout_address_fingerprint_changenow_transaction'], ''),
      'refundAddress' => $this->_value($row, ['refund_address_changenow_transaction'], ''),
      'refundExtraId' => $this->_value($row, ['refund_extra_id_changenow_transaction'], ''),
      'status' => $this->_value($row, ['status_changenow_transaction'], 'waiting'),
      'refundAvailable' => $refundAvailable,
      'continueAvailable' => $continueAvailable,
      'availableActions' => [
        'refund' => $refundAvailable,
        'continue' => $continueAvailable
      ],
      'supportNote' => $this->_value($row, ['support_note_changenow_transaction'], ''),
      'createdAt' => $this->_value($row, ['created_at_changenow_transaction'], ''),
      'updatedAt' => $this->_value($row, ['updated_at_changenow_transaction'], ''),
      'expiresAt' => $this->_value($row, ['expires_at_changenow_transaction'], ''),
      'rawCreate' => (is_array($rawCreate) ? $rawCreate : []),
      'rawStatus' => (is_array($rawStatus) ? $rawStatus : []),
      'rawActions' => (is_array($rawActions) ? $rawActions : []),
      'referralAttribution' => (is_array($referralAttribution) ? $referralAttribution : [])
    ];
  }

  private function _mapEventRows($rows){
    $result = [];
    foreach ($rows as $row) {
      $rawEvent = json_decode($this->_value($row, ['raw_event_changenow_transaction_event'], ''), true);
      $result[] = [
        'id' => $this->_value($row, ['id_changenow_transaction_event'], null),
        'transactionId' => $this->_value($row, ['id_changenow_transaction'], null),
        'providerId' => $this->_value($row, ['provider_id_changenow_transaction'], ''),
        'actorUserId' => $this->_value($row, ['actor_user_id_changenow_transaction_event'], null),
        'actorType' => $this->_value($row, ['actor_type_changenow_transaction_event'], ''),
        'eventType' => $this->_value($row, ['event_type_changenow_transaction_event'], ''),
        'eventStatus' => $this->_value($row, ['event_status_changenow_transaction_event'], ''),
        'note' => $this->_value($row, ['event_note_changenow_transaction_event'], ''),
        'rawEvent' => (is_array($rawEvent) ? $rawEvent : []),
        'createdAt' => $this->_value($row, ['created_at_changenow_transaction_event'], '')
      ];
    }
    return $result;
  }

  private function _timestampFromProviderValue($value){
    if(is_null($value) || trim((string) $value) == '') return 0;
    if(is_numeric($value)) return intval($value);
    $timestamp = strtotime($value);
    return ($timestamp === false ? 0 : $timestamp);
  }

  private function _referralAttributionFromRequest($request){
    if(!is_array($request)) return [];
    foreach (['referralAttribution', 'referral_attribution', 'referral', 'affiliate', 'campaign'] as $key) {
      if(array_key_exists($key, $request) && $request[$key] !== '') return [$key => $request[$key]];
    }
    return [];
  }

  private function _availableActionsFromPayload($payload){
    $actions = [
      'refund' => false,
      'continue' => false
    ];

    if(!is_array($payload)) return $actions;

    foreach ([
      'refundAvailable',
      'isRefundAvailable',
      'canRefund'
    ] as $key) {
      if(array_key_exists($key, $payload)) $actions['refund'] = $this->_boolValue($payload[$key]);
    }

    foreach ([
      'continueAvailable',
      'isContinueAvailable',
      'canContinue'
    ] as $key) {
      if(array_key_exists($key, $payload)) $actions['continue'] = $this->_boolValue($payload[$key]);
    }

    foreach (['actionsAvailable', 'availableActions', 'actions'] as $key) {
      if(array_key_exists($key, $payload)) $this->_mergeActionAvailability($actions, $payload[$key], '');
    }

    return $actions;
  }

  private function _mergeActionAvailability(&$actions, $source, $sourceKey = ''){
    $sourceKey = strtolower((string) $sourceKey);

    if(is_string($source)){
      $value = strtolower(trim($source));
      if($this->_looksLikeRefundAction($value)) $actions['refund'] = true;
      if($this->_looksLikeContinueAction($value)) $actions['continue'] = true;
      return true;
    }

    if(is_bool($source) || is_numeric($source)){
      if($this->_looksLikeRefundAction($sourceKey)) $actions['refund'] = $this->_boolValue($source);
      if($this->_looksLikeContinueAction($sourceKey)) $actions['continue'] = $this->_boolValue($source);
      return true;
    }

    if(!is_array($source)) return false;

    foreach ($source as $key => $value) {
      $keyName = strtolower((string) $key);
      if($this->_looksLikeRefundAction($keyName) && $this->_actionEntryAvailable($value)) $actions['refund'] = true;
      if($this->_looksLikeContinueAction($keyName) && $this->_actionEntryAvailable($value)) $actions['continue'] = true;

      if(is_array($value)){
        if(array_key_exists('action', $value)) $this->_mergeActionAvailability($actions, $value['action'], $keyName);
        if(array_key_exists('type', $value)) $this->_mergeActionAvailability($actions, $value['type'], $keyName);
        if(array_key_exists('name', $value)) $this->_mergeActionAvailability($actions, $value['name'], $keyName);
        $this->_mergeActionAvailability($actions, $value, $keyName);
      } else {
        $this->_mergeActionAvailability($actions, $value, $keyName);
      }
    }

    return true;
  }

  private function _rawActionsFromPayload($payload){
    if(!is_array($payload)) return [];
    foreach (['actionsAvailable', 'availableActions', 'actions'] as $key) {
      if(array_key_exists($key, $payload)) return (is_array($payload[$key]) ? $payload[$key] : ['available' => $payload[$key]]);
    }
    return [];
  }

  private function _actionEntryAvailable($entry){
    if(is_bool($entry) || is_numeric($entry)) return $this->_boolValue($entry);
    if(is_string($entry)){
      $entry = strtolower(trim($entry));
      if(in_array($entry, ['0', 'false', 'no', 'off', 'unavailable', 'disabled'], true)) return false;
      return $this->_boolValue($entry) || $this->_looksLikeRefundAction($entry) || $this->_looksLikeContinueAction($entry);
    }
    if(!is_array($entry)) return false;
    foreach (['available', 'isAvailable', 'enabled', 'active'] as $key) {
      if(array_key_exists($key, $entry)) return $this->_boolValue($entry[$key]);
    }
    return true;
  }

  private function _looksLikeRefundAction($value){
    $value = strtolower(trim((string) $value));
    return in_array($value, ['refund', 'refundavailable', 'canrefund', 'request_refund', 'request-refund'], true);
  }

  private function _looksLikeContinueAction($value){
    $value = strtolower(trim((string) $value));
    return in_array($value, ['continue', 'continueavailable', 'cancontinue', 'resume', 'retry', 'continue_transaction', 'continue-transaction'], true);
  }

  private function _addressFingerprint($address){
    $address = trim((string) $address);
    return ($address == '' ? '' : hash('sha256', $address));
  }

  private function _safeLimit($limit, $default, $max){
    $limit = intval($limit);
    if($limit <= 0) $limit = $default;
    if($limit > $max) $limit = $max;
    return $limit;
  }

  private function _jsonEncode($value){
    $encoded = json_encode($value);
    return ($encoded === false ? '' : $encoded);
  }

  private function _boolValue($value){
    if(is_bool($value)) return $value;
    if(is_int($value) || is_float($value)) return intval($value) == 1;
    return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on', 'available'], true);
  }

  private function _value($source, $keys, $default = null){
    if(!is_array($source)) return $default;
    foreach ($keys as $key) {
      if(array_key_exists($key, $source)) return $source[$key];
    }
    return $default;
  }

  private function _trySchemaSql($sql){
    try {
      parent::execSqlRequest($sql);
    } catch (Exception $e) {
      return false;
    }
    return true;
  }

  private function _upgradeSql(){
    return [
      "ALTER TABLE changenow_transactions_krypto ADD COLUMN payout_address_fingerprint_changenow_transaction char(64) DEFAULT NULL AFTER payout_extra_id_changenow_transaction",
      "ALTER TABLE changenow_transactions_krypto ADD COLUMN refund_available_changenow_transaction tinyint(1) NOT NULL DEFAULT '0' AFTER status_changenow_transaction",
      "ALTER TABLE changenow_transactions_krypto ADD COLUMN continue_available_changenow_transaction tinyint(1) NOT NULL DEFAULT '0' AFTER refund_available_changenow_transaction",
      "ALTER TABLE changenow_transactions_krypto ADD COLUMN referral_attribution_changenow_transaction longtext AFTER continue_available_changenow_transaction",
      "ALTER TABLE changenow_transactions_krypto ADD COLUMN raw_actions_changenow_transaction longtext AFTER raw_status_changenow_transaction",
      "ALTER TABLE changenow_transactions_krypto ADD COLUMN support_note_changenow_transaction text AFTER raw_actions_changenow_transaction",
      "ALTER TABLE changenow_transactions_krypto ADD KEY action_changenow_transaction (refund_available_changenow_transaction, continue_available_changenow_transaction)"
    ];
  }

  private function _schemaSql(){
    return [
      "CREATE TABLE IF NOT EXISTS changenow_transactions_krypto (
        id_changenow_transaction int(11) NOT NULL AUTO_INCREMENT,
        provider_id_changenow_transaction varchar(120) NOT NULL,
        lookup_token_hash_changenow_transaction char(64) NOT NULL,
        session_key_changenow_transaction char(64) NOT NULL,
        id_user int(11) DEFAULT NULL,
        flow_changenow_transaction varchar(20) NOT NULL DEFAULT 'standard',
        from_currency_changenow_transaction varchar(32) NOT NULL,
        from_network_changenow_transaction varchar(32) NOT NULL,
        to_currency_changenow_transaction varchar(32) NOT NULL,
        to_network_changenow_transaction varchar(32) NOT NULL,
        from_amount_changenow_transaction varchar(40) NOT NULL,
        to_amount_changenow_transaction varchar(40) DEFAULT NULL,
        payin_address_changenow_transaction text,
        payin_extra_id_changenow_transaction varchar(255) DEFAULT NULL,
        payout_address_changenow_transaction text,
        payout_extra_id_changenow_transaction varchar(255) DEFAULT NULL,
        payout_address_fingerprint_changenow_transaction char(64) DEFAULT NULL,
        refund_address_changenow_transaction text,
        refund_extra_id_changenow_transaction varchar(255) DEFAULT NULL,
        status_changenow_transaction varchar(40) NOT NULL DEFAULT 'waiting',
        refund_available_changenow_transaction tinyint(1) NOT NULL DEFAULT '0',
        continue_available_changenow_transaction tinyint(1) NOT NULL DEFAULT '0',
        referral_attribution_changenow_transaction longtext,
        raw_create_changenow_transaction longtext,
        raw_status_changenow_transaction longtext,
        raw_actions_changenow_transaction longtext,
        support_note_changenow_transaction text,
        created_at_changenow_transaction varchar(15) NOT NULL,
        updated_at_changenow_transaction varchar(15) NOT NULL,
        expires_at_changenow_transaction varchar(15) NOT NULL DEFAULT '0',
        PRIMARY KEY (id_changenow_transaction),
        UNIQUE KEY provider_id_changenow_transaction (provider_id_changenow_transaction),
        UNIQUE KEY lookup_token_hash_changenow_transaction (lookup_token_hash_changenow_transaction),
        KEY session_key_changenow_transaction (session_key_changenow_transaction),
        KEY user_changenow_transaction (id_user),
        KEY status_changenow_transaction (status_changenow_transaction),
        KEY action_changenow_transaction (refund_available_changenow_transaction, continue_available_changenow_transaction),
        KEY pair_changenow_transaction (from_currency_changenow_transaction, from_network_changenow_transaction, to_currency_changenow_transaction, to_network_changenow_transaction)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
      "CREATE TABLE IF NOT EXISTS changenow_transaction_events_krypto (
        id_changenow_transaction_event int(11) NOT NULL AUTO_INCREMENT,
        id_changenow_transaction int(11) DEFAULT NULL,
        provider_id_changenow_transaction varchar(120) NOT NULL,
        actor_user_id_changenow_transaction_event int(11) DEFAULT NULL,
        actor_type_changenow_transaction_event varchar(30) NOT NULL DEFAULT 'system',
        event_type_changenow_transaction_event varchar(40) NOT NULL,
        event_status_changenow_transaction_event varchar(40) NOT NULL,
        event_note_changenow_transaction_event text,
        raw_event_changenow_transaction_event longtext,
        created_at_changenow_transaction_event varchar(15) NOT NULL,
        PRIMARY KEY (id_changenow_transaction_event),
        KEY transaction_changenow_transaction_event (id_changenow_transaction),
        KEY provider_changenow_transaction_event (provider_id_changenow_transaction),
        KEY actor_changenow_transaction_event (actor_user_id_changenow_transaction_event, actor_type_changenow_transaction_event),
        KEY type_changenow_transaction_event (event_type_changenow_transaction_event, event_status_changenow_transaction_event)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];
  }

}

?>
