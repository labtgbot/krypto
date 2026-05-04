<?php

/**
 * Database adapter for ChangeNOW admin transaction support.
 *
 * @package Krypto
 */
class ChangeNowAdminRepository extends MySQL {

  private $columns = null;

  public function _transactionsAvailable(){
    return $this->_tableExists(ChangeNowAdminPanel::TRANSACTION_TABLE);
  }

  public function _eventsAvailable(){
    return $this->_tableExists(ChangeNowAdminPanel::EVENT_TABLE);
  }

  public function _listTransactions($filters = [], $limit = 100){
    if(!$this->_transactionsAvailable()) return [];
    $query = ChangeNowAdminPanel::_buildTransactionSearchQuery($filters, $limit, $this->_transactionColumns());
    return $this->_mapRows(parent::querySqlRequest($query['sql'], $query['params']));
  }

  public function _findTransactionByProviderId($providerId){
    if(!$this->_transactionsAvailable()) return null;
    $providerId = trim((string) $providerId);
    if($providerId == '') return null;

    $rows = parent::querySqlRequest('SELECT * FROM '.ChangeNowAdminPanel::TRANSACTION_TABLE.'
                                     WHERE provider_id_changenow_transaction=:provider_id
                                     LIMIT 1',
                                     ['provider_id' => $providerId]);
    if(count($rows) == 0) return null;
    return $this->_mapRow($rows[0]);
  }

  public function _saveSupportNote($providerId, $note, $actorUserId = null){
    if(!$this->_transactionsAvailable()) return false;
    $providerId = trim((string) $providerId);
    if($providerId == '') return false;

    $updates = [];
    $params = [
      'provider_id' => $providerId,
      'updated_at' => time()
    ];

    if($this->_hasColumn('support_note_changenow_transaction')){
      $updates[] = 'support_note_changenow_transaction=:support_note';
      $params['support_note'] = trim((string) $note);
    }
    if($this->_hasColumn('updated_at_changenow_transaction')){
      $updates[] = 'updated_at_changenow_transaction=:updated_at';
    }

    if(count($updates) > 0){
      parent::execSqlRequest('UPDATE '.ChangeNowAdminPanel::TRANSACTION_TABLE.' SET '.implode(', ', $updates).'
                              WHERE provider_id_changenow_transaction=:provider_id',
                              $params);
    }

    $this->_recordEvent($providerId, 'support_note', 'saved', $actorUserId, trim((string) $note), []);
    return true;
  }

  public function _recordSupportAction($providerId, $action, $actorUserId = null, $payload = []){
    $providerId = trim((string) $providerId);
    $action = strtolower(trim((string) $action));
    $transaction = $this->_findTransactionByProviderId($providerId);
    if(!is_array($transaction)) throw new Exception('ChangeNOW transaction was not found.', 1);
    if(!ChangeNowAdminPanel::_supportActionAllowed($transaction, $action)){
      throw new Exception('This ChangeNOW support action is not available for the selected transaction.', 1);
    }

    $this->_recordEvent($providerId, 'support_action', $action.'_requested', $actorUserId, '', $payload);
    return true;
  }

  public function _recordEvent($providerId, $eventType, $eventStatus, $actorUserId = null, $note = '', $rawEvent = []){
    if(!$this->_eventsAvailable()) return false;
    $transaction = $this->_findTransactionByProviderId($providerId);
    parent::execSqlRequest('INSERT INTO '.ChangeNowAdminPanel::EVENT_TABLE.'
                            (id_changenow_transaction, provider_id_changenow_transaction, actor_user_id_changenow_transaction_event,
                             actor_type_changenow_transaction_event, event_type_changenow_transaction_event,
                             event_status_changenow_transaction_event, event_note_changenow_transaction_event,
                             raw_event_changenow_transaction_event, created_at_changenow_transaction_event)
                            VALUES (:transaction_id, :provider_id, :actor_user_id, :actor_type, :event_type,
                                    :event_status, :event_note, :raw_event, :created_at)',
                            [
                              'transaction_id' => (is_array($transaction) ? $transaction['id'] : null),
                              'provider_id' => trim((string) $providerId),
                              'actor_user_id' => $actorUserId,
                              'actor_type' => 'admin',
                              'event_type' => trim((string) $eventType),
                              'event_status' => trim((string) $eventStatus),
                              'event_note' => trim((string) $note),
                              'raw_event' => json_encode($rawEvent),
                              'created_at' => time()
                            ]);
    return true;
  }

  private function _transactionColumns(){
    if(is_array($this->columns)) return $this->columns;
    if(!$this->_transactionsAvailable()) return [];

    $columns = [];
    foreach (parent::querySqlRequest('SHOW COLUMNS FROM '.ChangeNowAdminPanel::TRANSACTION_TABLE, []) as $column) {
      if(array_key_exists('Field', $column)) $columns[] = $column['Field'];
    }
    $this->columns = $columns;
    return $columns;
  }

  private function _hasColumn($column){
    return in_array($column, $this->_transactionColumns(), true);
  }

  private function _tableExists($table){
    try {
      $table = trim((string) $table);
      if(!preg_match('/^[a-z0-9_]+$/i', $table)) return false;
      $rows = parent::querySqlRequest("SHOW TABLES LIKE '".$table."'", []);
      return count($rows) > 0;
    } catch (Exception $e) {
      return false;
    }
  }

  private function _mapRows($rows){
    $mapped = [];
    foreach ($rows as $row) {
      $mapped[] = $this->_mapRow($row);
    }
    return $mapped;
  }

  private function _mapRow($row){
    $referral = json_decode($this->_value($row, ['referral_attribution_changenow_transaction'], ''), true);
    if(!is_array($referral)) $referral = [];

    return [
      'id' => $this->_value($row, ['id_changenow_transaction'], ''),
      'providerId' => $this->_value($row, ['provider_id_changenow_transaction'], ''),
      'userId' => $this->_value($row, ['id_user'], ''),
      'userEmail' => $this->_value($row, ['email_user'], ''),
      'flow' => $this->_value($row, ['flow_changenow_transaction'], ''),
      'fromCurrency' => $this->_value($row, ['from_currency_changenow_transaction'], ''),
      'fromNetwork' => $this->_value($row, ['from_network_changenow_transaction'], ''),
      'toCurrency' => $this->_value($row, ['to_currency_changenow_transaction'], ''),
      'toNetwork' => $this->_value($row, ['to_network_changenow_transaction'], ''),
      'fromAmount' => $this->_value($row, ['from_amount_changenow_transaction'], ''),
      'toAmount' => $this->_value($row, ['to_amount_changenow_transaction'], ''),
      'status' => $this->_value($row, ['status_changenow_transaction'], ''),
      'refundAvailable' => $this->_boolValue($this->_value($row, ['refund_available_changenow_transaction'], 0)),
      'continueAvailable' => $this->_boolValue($this->_value($row, ['continue_available_changenow_transaction'], 0)),
      'referralCode' => $this->_referralCode($referral),
      'payoutAddressFingerprint' => $this->_value($row, ['payout_address_fingerprint_changenow_transaction'], ''),
      'lookupTokenFragment' => $this->_value($row, ['lookup_token_fragment_changenow_transaction', 'anonymous_lookup_token_fragment_changenow_transaction'], ''),
      'supportNote' => $this->_value($row, ['support_note_changenow_transaction'], ''),
      'createdAt' => $this->_value($row, ['created_at_changenow_transaction'], ''),
      'updatedAt' => $this->_value($row, ['updated_at_changenow_transaction'], '')
    ];
  }

  private function _referralCode($referral){
    if(!is_array($referral)) return '';
    foreach ([
      ['internal', 'code'],
      ['changeNow', 'referralLinkId'],
      ['changenow', 'referralLinkId'],
      ['utm', 'campaign']
    ] as $path) {
      $value = $referral;
      foreach ($path as $part) {
        if(!is_array($value) || !array_key_exists($part, $value)){
          $value = '';
          break;
        }
        $value = $value[$part];
      }
      if(trim((string) $value) != '') return (string) $value;
    }
    return '';
  }

  private function _boolValue($value){
    if(is_bool($value)) return $value;
    if(is_numeric($value)) return intval($value) == 1;
    return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on', 'available'], true);
  }

  private function _value($source, $keys, $default = null){
    if(!is_array($source)) return $default;
    foreach ($keys as $key) {
      if(array_key_exists($key, $source)) return $source[$key];
    }
    return $default;
  }

}

?>
