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

    parent::execSqlRequest("INSERT INTO changenow_transactions_krypto
                            (provider_id_changenow_transaction, lookup_token_hash_changenow_transaction, session_key_changenow_transaction,
                             id_user, flow_changenow_transaction, from_currency_changenow_transaction, from_network_changenow_transaction,
                             to_currency_changenow_transaction, to_network_changenow_transaction, from_amount_changenow_transaction,
                             to_amount_changenow_transaction, payin_address_changenow_transaction, payin_extra_id_changenow_transaction,
                             payout_address_changenow_transaction, payout_extra_id_changenow_transaction, refund_address_changenow_transaction,
                             refund_extra_id_changenow_transaction, status_changenow_transaction, raw_create_changenow_transaction,
                             raw_status_changenow_transaction, created_at_changenow_transaction, updated_at_changenow_transaction,
                             expires_at_changenow_transaction)
                            VALUES (:provider_id, :lookup_hash, :session_key, :id_user, :flow_swap, :from_currency, :from_network,
                                    :to_currency, :to_network, :from_amount, :to_amount, :payin_address, :payin_extra_id,
                                    :payout_address, :payout_extra_id, :refund_address, :refund_extra_id, :status_swap,
                                    :raw_create, :raw_status, :created_at, :updated_at, :expires_at)
                            ON DUPLICATE KEY UPDATE
                              status_changenow_transaction=:status_update,
                              raw_create_changenow_transaction=:raw_create_update,
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
                              'payout_address' => $this->_value($transaction, ['payoutAddress'], $this->_value($request, ['destinationAddress'], '')),
                              'payout_extra_id' => $this->_value($transaction, ['payoutExtraId'], $this->_value($request, ['destinationExtraId'], '')),
                              'refund_address' => $this->_value($transaction, ['refundAddress'], $this->_value($request, ['refundAddress'], '')),
                              'refund_extra_id' => $this->_value($transaction, ['refundExtraId'], $this->_value($request, ['refundExtraId'], '')),
                              'status_swap' => $status,
                              'raw_create' => json_encode($transaction),
                              'raw_status' => '',
                              'created_at' => $createdAt,
                              'updated_at' => $createdAt,
                              'expires_at' => $expiresAt,
                              'status_update' => $status,
                              'raw_create_update' => json_encode($transaction),
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

  public function _updateStatusSnapshot($lookupToken, $statusPayload, $updatedAt = null){
    $this->_ensureSchema();
    $updatedAt = (is_null($updatedAt) ? time() : $updatedAt);
    $status = $this->_value($statusPayload, ['status'], 'waiting');
    $providerId = $this->_value($statusPayload, ['id'], '');
    $expiresAt = $this->_timestampFromProviderValue($this->_value($statusPayload, ['validUntil'], null));

    $params = [
      'lookup_hash' => self::_lookupTokenHash($lookupToken),
      'status_swap' => $status,
      'raw_status' => json_encode($statusPayload),
      'updated_at' => $updatedAt,
      'provider_id' => $providerId,
      'from_amount' => $this->_value($statusPayload, ['amountFrom', 'expectedAmountFrom'], ''),
      'to_amount' => $this->_value($statusPayload, ['amountTo', 'expectedAmountTo'], ''),
      'payin_address' => $this->_value($statusPayload, ['payinAddress'], ''),
      'payin_extra_id' => $this->_value($statusPayload, ['payinExtraId'], ''),
      'payout_address' => $this->_value($statusPayload, ['payoutAddress'], ''),
      'payout_extra_id' => $this->_value($statusPayload, ['payoutExtraId'], ''),
      'refund_address' => $this->_value($statusPayload, ['refundAddress'], ''),
      'refund_extra_id' => $this->_value($statusPayload, ['refundExtraId'], ''),
      'expires_at' => $expiresAt
    ];

    parent::execSqlRequest("UPDATE changenow_transactions_krypto SET
                              status_changenow_transaction=:status_swap,
                              raw_status_changenow_transaction=:raw_status,
                              updated_at_changenow_transaction=:updated_at,
                              provider_id_changenow_transaction=COALESCE(NULLIF(:provider_id, ''), provider_id_changenow_transaction),
                              from_amount_changenow_transaction=COALESCE(NULLIF(:from_amount, ''), from_amount_changenow_transaction),
                              to_amount_changenow_transaction=COALESCE(NULLIF(:to_amount, ''), to_amount_changenow_transaction),
                              payin_address_changenow_transaction=COALESCE(NULLIF(:payin_address, ''), payin_address_changenow_transaction),
                              payin_extra_id_changenow_transaction=COALESCE(NULLIF(:payin_extra_id, ''), payin_extra_id_changenow_transaction),
                              payout_address_changenow_transaction=COALESCE(NULLIF(:payout_address, ''), payout_address_changenow_transaction),
                              payout_extra_id_changenow_transaction=COALESCE(NULLIF(:payout_extra_id, ''), payout_extra_id_changenow_transaction),
                              refund_address_changenow_transaction=COALESCE(NULLIF(:refund_address, ''), refund_address_changenow_transaction),
                              refund_extra_id_changenow_transaction=COALESCE(NULLIF(:refund_extra_id, ''), refund_extra_id_changenow_transaction),
                              expires_at_changenow_transaction=COALESCE(NULLIF(:expires_at, '0'), expires_at_changenow_transaction)
                            WHERE lookup_token_hash_changenow_transaction=:lookup_hash",
                            $params);

    return $this->_findByLookupToken($lookupToken);
  }

  public static function _lookupTokenHash($lookupToken){
    return hash('sha256', trim((string) $lookupToken));
  }

  public static function _sessionKeyHash($sessionKey){
    return hash('sha256', trim((string) $sessionKey));
  }

  private function _mapRow($row){
    $rawCreate = json_decode($row['raw_create_changenow_transaction'], true);
    $rawStatus = json_decode($row['raw_status_changenow_transaction'], true);

    return [
      'providerId' => $row['provider_id_changenow_transaction'],
      'userId' => $row['id_user'],
      'flow' => $row['flow_changenow_transaction'],
      'fromCurrency' => $row['from_currency_changenow_transaction'],
      'fromNetwork' => $row['from_network_changenow_transaction'],
      'toCurrency' => $row['to_currency_changenow_transaction'],
      'toNetwork' => $row['to_network_changenow_transaction'],
      'fromAmount' => $row['from_amount_changenow_transaction'],
      'toAmount' => $row['to_amount_changenow_transaction'],
      'payinAddress' => $row['payin_address_changenow_transaction'],
      'payinExtraId' => $row['payin_extra_id_changenow_transaction'],
      'payoutAddress' => $row['payout_address_changenow_transaction'],
      'payoutExtraId' => $row['payout_extra_id_changenow_transaction'],
      'refundAddress' => $row['refund_address_changenow_transaction'],
      'refundExtraId' => $row['refund_extra_id_changenow_transaction'],
      'status' => $row['status_changenow_transaction'],
      'createdAt' => $row['created_at_changenow_transaction'],
      'updatedAt' => $row['updated_at_changenow_transaction'],
      'expiresAt' => $row['expires_at_changenow_transaction'],
      'rawCreate' => (is_array($rawCreate) ? $rawCreate : []),
      'rawStatus' => (is_array($rawStatus) ? $rawStatus : [])
    ];
  }

  private function _timestampFromProviderValue($value){
    if(is_null($value) || trim((string) $value) == '') return 0;
    if(is_numeric($value)) return intval($value);
    $timestamp = strtotime($value);
    return ($timestamp === false ? 0 : $timestamp);
  }

  private function _value($source, $keys, $default = null){
    if(!is_array($source)) return $default;
    foreach ($keys as $key) {
      if(array_key_exists($key, $source)) return $source[$key];
    }
    return $default;
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
        refund_address_changenow_transaction text,
        refund_extra_id_changenow_transaction varchar(255) DEFAULT NULL,
        status_changenow_transaction varchar(40) NOT NULL DEFAULT 'waiting',
        raw_create_changenow_transaction longtext,
        raw_status_changenow_transaction longtext,
        created_at_changenow_transaction varchar(15) NOT NULL,
        updated_at_changenow_transaction varchar(15) NOT NULL,
        expires_at_changenow_transaction varchar(15) NOT NULL DEFAULT '0',
        PRIMARY KEY (id_changenow_transaction),
        UNIQUE KEY provider_id_changenow_transaction (provider_id_changenow_transaction),
        UNIQUE KEY lookup_token_hash_changenow_transaction (lookup_token_hash_changenow_transaction),
        KEY session_key_changenow_transaction (session_key_changenow_transaction),
        KEY user_changenow_transaction (id_user),
        KEY status_changenow_transaction (status_changenow_transaction),
        KEY pair_changenow_transaction (from_currency_changenow_transaction, from_network_changenow_transaction, to_currency_changenow_transaction, to_network_changenow_transaction)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];
  }

}

?>
