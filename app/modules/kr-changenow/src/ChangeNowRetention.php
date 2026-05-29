<?php

/**
 * Retention and cleanup policy for ChangeNOW storage.
 *
 * @package Krypto
 */
class ChangeNowRetention {

  const DEFAULT_ANONYMOUS_RETENTION_DAYS = 30;
  const DEFAULT_COMPLETED_RETENTION_DAYS = 365;
  const DEFAULT_BATCH_SIZE = 500;
  const ANONYMIZED_SUPPORT_NOTE = 'Anonymized by ChangeNOW retention policy.';

  private $Pdo;
  private $Options;

  public function __construct($pdo = null, $options = []){
    if(is_null($pdo)){
      if(!class_exists('MySQL')) throw new Exception('MySQL class is required when no PDO connection is supplied.', 1);
      $pdo = MySQL::getSqlConnexion();
    }

    $this->Pdo = $pdo;
    $this->Options = self::_normalizeOptions($options);
  }

  public function _run($options = []){
    $options = self::_normalizeOptions(array_merge($this->Options, (is_array($options) ? $options : [])));

    $result = [
      'dryRun' => $options['dry_run'],
      'now' => $options['now'],
      'anonymousRetentionDays' => $options['anonymous_retention_days'],
      'completedRetentionDays' => $options['completed_retention_days'],
      'quoteCacheDeleted' => 0,
      'anonymousTransactionsAnonymized' => 0,
      'anonymousEventsDeleted' => 0,
      'completedTransactionsDeleted' => 0,
      'completedEventsDeleted' => 0
    ];

    $result['quoteCacheDeleted'] = $this->_deleteExpiredQuoteCache($options['now'], $options['dry_run']);

    $completedCutoff = $options['now'] - ($options['completed_retention_days'] * 86400);
    $completedResult = $this->_deleteCompletedTransactions($completedCutoff, $options);
    $result['completedTransactionsDeleted'] = $completedResult['transactions'];
    $result['completedEventsDeleted'] = $completedResult['events'];

    $anonymousCutoff = $options['now'] - ($options['anonymous_retention_days'] * 86400);
    $anonymousResult = $this->_anonymizeExpiredAnonymousTransactions($anonymousCutoff, $options);
    $result['anonymousTransactionsAnonymized'] = $anonymousResult['transactions'];
    $result['anonymousEventsDeleted'] = $anonymousResult['events'];

    return $result;
  }

  public static function _optionsFromSettings($settings, $overrides = []){
    if(!is_array($settings)) $settings = [];
    if(!is_array($overrides)) $overrides = [];

    $options = [
      'anonymous_retention_days' => self::_positiveInt(self::_value($settings, 'changenow_retention_anonymous_days', self::DEFAULT_ANONYMOUS_RETENTION_DAYS), self::DEFAULT_ANONYMOUS_RETENTION_DAYS),
      'completed_retention_days' => self::_positiveInt(self::_value($settings, 'changenow_retention_completed_days', self::DEFAULT_COMPLETED_RETENTION_DAYS), self::DEFAULT_COMPLETED_RETENTION_DAYS)
    ];

    foreach ($overrides as $key => $value) {
      if(!is_null($value)) $options[$key] = $value;
    }

    return self::_normalizeOptions($options);
  }

  public static function _terminalStatuses(){
    return ['finished', 'completed', 'complete', 'success', 'failed', 'refunded', 'expired', 'overdue', 'rejected'];
  }

  public static function _retainedLookupHash($transactionId){
    return hash('sha256', 'changenow-retained-lookup:'.intval($transactionId));
  }

  public static function _retainedSessionHash($transactionId){
    return hash('sha256', 'changenow-retained-session:'.intval($transactionId));
  }

  public static function _normalizeOptions($options){
    if(!is_array($options)) $options = [];

    $now = self::_positiveInt(self::_value($options, 'now', time()), time());
    $batchSize = self::_positiveInt(self::_value($options, 'batch_size', self::DEFAULT_BATCH_SIZE), self::DEFAULT_BATCH_SIZE);
    if($batchSize > 5000) $batchSize = 5000;

    return [
      'anonymous_retention_days' => self::_positiveInt(self::_value($options, 'anonymous_retention_days', self::DEFAULT_ANONYMOUS_RETENTION_DAYS), self::DEFAULT_ANONYMOUS_RETENTION_DAYS),
      'completed_retention_days' => self::_positiveInt(self::_value($options, 'completed_retention_days', self::DEFAULT_COMPLETED_RETENTION_DAYS), self::DEFAULT_COMPLETED_RETENTION_DAYS),
      'now' => $now,
      'batch_size' => $batchSize,
      'dry_run' => self::_boolValue(self::_value($options, 'dry_run', false))
    ];
  }

  private function _deleteExpiredQuoteCache($now, $dryRun){
    $params = ['now_value' => (string) $now];
    if($dryRun){
      return $this->_countSql("SELECT COUNT(*) FROM changenow_quote_cache_krypto
                               WHERE CAST(expires_at_changenow_quote_cache AS UNSIGNED) <= :now_value",
                              $params);
    }

    return $this->_executeSql("DELETE FROM changenow_quote_cache_krypto
                               WHERE CAST(expires_at_changenow_quote_cache AS UNSIGNED) <= :now_value",
                              $params);
  }

  private function _anonymizeExpiredAnonymousTransactions($cutoff, $options){
    $result = ['transactions' => 0, 'events' => 0];
    $afterId = 0;

    do {
      $transactions = $this->_fetchAnonymousCandidates($cutoff, $options['batch_size'], $afterId);
      if(count($transactions) == 0) break;

      $result['events'] += $this->_deleteEventsForTransactions($transactions, $options['dry_run']);

      foreach ($transactions as $transaction) {
        $afterId = max($afterId, intval($transaction['id_changenow_transaction']));
        if($options['dry_run']){
          $result['transactions']++;
          continue;
        }
        $result['transactions'] += $this->_anonymizeTransaction($transaction);
      }
    } while (count($transactions) == $options['batch_size']);

    return $result;
  }

  private function _deleteCompletedTransactions($cutoff, $options){
    $result = ['transactions' => 0, 'events' => 0];
    $afterId = 0;

    do {
      $transactions = $this->_fetchCompletedCandidates($cutoff, $options['batch_size'], $afterId);
      if(count($transactions) == 0) break;

      foreach ($transactions as $transaction) {
        $afterId = max($afterId, intval($transaction['id_changenow_transaction']));
      }

      $result['events'] += $this->_deleteEventsForTransactions($transactions, $options['dry_run']);
      $result['transactions'] += $this->_deleteTransactions($transactions, $options['dry_run']);
    } while (count($transactions) == $options['batch_size']);

    return $result;
  }

  private function _fetchAnonymousCandidates($cutoff, $limit, $afterId){
    $limit = intval($limit);
    return $this->_fetchSql("SELECT id_changenow_transaction, provider_id_changenow_transaction, lookup_token_hash_changenow_transaction
                              FROM changenow_transactions_krypto
                             WHERE id_changenow_transaction > :after_id
                              AND (id_user IS NULL OR id_user = 0)
                              AND (
                                lookup_token_hash_changenow_transaction IS NULL
                                OR lookup_token_hash_changenow_transaction <> SHA2(CONCAT('changenow-retained-lookup:', id_changenow_transaction), 256)
                              )
                              AND (
                                (CAST(expires_at_changenow_transaction AS UNSIGNED) > 0
                                  AND CAST(expires_at_changenow_transaction AS UNSIGNED) <= :cutoff)
                                OR
                                ((expires_at_changenow_transaction IS NULL
                                  OR expires_at_changenow_transaction = ''
                                  OR expires_at_changenow_transaction = '0')
                                  AND CAST(created_at_changenow_transaction AS UNSIGNED) <= :cutoff_created)
                              )
                             ORDER BY id_changenow_transaction ASC
                             LIMIT ".$limit,
                            [
                              'after_id' => $afterId,
                              'cutoff' => (string) $cutoff,
                              'cutoff_created' => (string) $cutoff
                            ]);
  }

  private function _fetchCompletedCandidates($cutoff, $limit, $afterId){
    $params = [
      'after_id' => $afterId,
      'cutoff' => (string) $cutoff
    ];

    $statusPlaceholders = [];
    foreach (self::_terminalStatuses() as $index => $status) {
      $placeholder = 'status_'.$index;
      $statusPlaceholders[] = ':'.$placeholder;
      $params[$placeholder] = $status;
    }

    $limit = intval($limit);
    return $this->_fetchSql("SELECT id_changenow_transaction, provider_id_changenow_transaction
                             FROM changenow_transactions_krypto
                             WHERE id_changenow_transaction > :after_id
                              AND LOWER(status_changenow_transaction) IN (".implode(', ', $statusPlaceholders).")
                              AND CAST(updated_at_changenow_transaction AS UNSIGNED) <= :cutoff
                             ORDER BY id_changenow_transaction ASC
                             LIMIT ".$limit,
                            $params);
  }

  private function _anonymizeTransaction($transaction){
    $transactionId = intval($transaction['id_changenow_transaction']);
    return $this->_executeSql("UPDATE changenow_transactions_krypto SET
                                 lookup_token_hash_changenow_transaction=:lookup_hash,
                                 session_key_changenow_transaction=:session_hash,
                                 payin_address_changenow_transaction='',
                                 payin_extra_id_changenow_transaction='',
                                 payout_address_changenow_transaction='',
                                 payout_extra_id_changenow_transaction='',
                                 payout_address_fingerprint_changenow_transaction=NULL,
                                 refund_address_changenow_transaction='',
                                 refund_extra_id_changenow_transaction='',
                                 raw_create_changenow_transaction='',
                                 raw_status_changenow_transaction='',
                                 raw_actions_changenow_transaction='',
                                 support_note_changenow_transaction=:support_note,
                                 refund_available_changenow_transaction=0,
                                 continue_available_changenow_transaction=0
                               WHERE id_changenow_transaction=:transaction_id
                                AND (lookup_token_hash_changenow_transaction IS NULL
                                  OR lookup_token_hash_changenow_transaction<>:lookup_hash_match)",
                              [
                                'lookup_hash' => self::_retainedLookupHash($transactionId),
                                'session_hash' => self::_retainedSessionHash($transactionId),
                                'support_note' => self::ANONYMIZED_SUPPORT_NOTE,
                                'transaction_id' => $transactionId,
                                'lookup_hash_match' => self::_retainedLookupHash($transactionId)
                              ]);
  }

  private function _deleteEventsForTransactions($transactions, $dryRun){
    $where = $this->_eventWhereForTransactions($transactions, $params);
    if($where == '') return 0;

    if($dryRun){
      return $this->_countSql("SELECT COUNT(*) FROM changenow_transaction_events_krypto WHERE ".$where, $params);
    }

    return $this->_executeSql("DELETE FROM changenow_transaction_events_krypto WHERE ".$where, $params);
  }

  private function _deleteTransactions($transactions, $dryRun){
    $ids = $this->_transactionIds($transactions);
    if(count($ids) == 0) return 0;

    $params = [];
    $placeholders = $this->_placeholders('transaction_id', $ids, $params);

    if($dryRun){
      return $this->_countSql("SELECT COUNT(*) FROM changenow_transactions_krypto
                               WHERE id_changenow_transaction IN (".implode(', ', $placeholders).")",
                              $params);
    }

    return $this->_executeSql("DELETE FROM changenow_transactions_krypto
                               WHERE id_changenow_transaction IN (".implode(', ', $placeholders).")",
                              $params);
  }

  private function _eventWhereForTransactions($transactions, &$params){
    $params = [];
    $clauses = [];
    $ids = $this->_transactionIds($transactions);
    $providerIds = $this->_providerIds($transactions);

    if(count($ids) > 0){
      $idPlaceholders = $this->_placeholders('event_transaction_id', $ids, $params);
      $clauses[] = 'id_changenow_transaction IN ('.implode(', ', $idPlaceholders).')';
    }

    if(count($providerIds) > 0){
      $providerPlaceholders = $this->_placeholders('event_provider_id', $providerIds, $params);
      $clauses[] = 'provider_id_changenow_transaction IN ('.implode(', ', $providerPlaceholders).')';
    }

    if(count($clauses) == 0) return '';
    return '('.implode(' OR ', $clauses).')';
  }

  private function _transactionIds($transactions){
    $ids = [];
    foreach ($transactions as $transaction) {
      if(!is_array($transaction) || !array_key_exists('id_changenow_transaction', $transaction)) continue;
      $id = intval($transaction['id_changenow_transaction']);
      if($id > 0 && !in_array($id, $ids, true)) $ids[] = $id;
    }
    return $ids;
  }

  private function _providerIds($transactions){
    $providerIds = [];
    foreach ($transactions as $transaction) {
      if(!is_array($transaction) || !array_key_exists('provider_id_changenow_transaction', $transaction)) continue;
      $providerId = trim((string) $transaction['provider_id_changenow_transaction']);
      if($providerId != '' && !in_array($providerId, $providerIds, true)) $providerIds[] = $providerId;
    }
    return $providerIds;
  }

  private function _placeholders($prefix, $values, &$params){
    $placeholders = [];
    foreach (array_values($values) as $index => $value) {
      $key = $prefix.'_'.$index;
      $placeholders[] = ':'.$key;
      $params[$key] = $value;
    }
    return $placeholders;
  }

  private function _fetchSql($sql, $params = []){
    $statement = $this->Pdo->prepare($sql);
    $statement->execute($params);
    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
    $statement->closeCursor();
    return $rows;
  }

  private function _countSql($sql, $params = []){
    $statement = $this->Pdo->prepare($sql);
    $statement->execute($params);
    $count = intval($statement->fetchColumn());
    $statement->closeCursor();
    return $count;
  }

  private function _executeSql($sql, $params = []){
    $statement = $this->Pdo->prepare($sql);
    $statement->execute($params);
    $count = $statement->rowCount();
    $statement->closeCursor();
    return $count;
  }

  private static function _value($source, $key, $default = null){
    if(!is_array($source) || !array_key_exists($key, $source) || is_null($source[$key])) return $default;
    return $source[$key];
  }

  private static function _positiveInt($value, $default){
    if(is_int($value) && $value > 0) return $value;
    $value = trim((string) $value);
    if(!preg_match('/^[0-9]+$/', $value)) return intval($default);
    $value = intval($value);
    return ($value > 0 ? $value : intval($default));
  }

  private static function _boolValue($value){
    if(is_bool($value)) return $value;
    if(is_numeric($value)) return intval($value) == 1;
    return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
  }

}

?>
