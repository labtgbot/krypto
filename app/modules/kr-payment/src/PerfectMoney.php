<?php

/**
 * Perfect Money admin setting helpers.
 *
 * @package Krypto
 */
class PerfectMoneySettings {

  const SECRET_MASK = '*********************';

  public static function _encryptedKeys(){
    return ['perfectmoney_alternate_passphrase'];
  }

  public static function _adminPostToSettings($post){
    if(!is_array($post)) $post = [];

    $settings = [
      'perfectmoney_enabled' => (array_key_exists('kr-adm-chk-enableperfectmoney', $post) && $post['kr-adm-chk-enableperfectmoney'] == 'on' ? '1' : '0'),
      'perfectmoney_payee_account' => self::_sanitizeAccount(self::_postString($post, 'kr-adm-perfectmoneypayeeaccount')),
      'perfectmoney_payee_name' => self::_sanitizeText(self::_postString($post, 'kr-adm-perfectmoneypayeename'), 120)
    ];

    if(array_key_exists('kr-adm-perfectmoneyalternatepassphrase', $post)){
      $alternatePassphrase = self::_postString($post, 'kr-adm-perfectmoneyalternatepassphrase');
      if($alternatePassphrase !== self::SECRET_MASK){
        $settings['perfectmoney_alternate_passphrase'] = self::_sanitizeSecret($alternatePassphrase, 200);
      }
    }

    return $settings;
  }

  private static function _postString($post, $key){
    if(!array_key_exists($key, $post) || !is_scalar($post[$key])) return '';
    return trim((string) $post[$key]);
  }

  private static function _sanitizeAccount($value){
    return substr(preg_replace('/[^A-Za-z0-9]/', '', (string) $value), 0, 64);
  }

  private static function _sanitizeText($value, $maxLength){
    $value = trim(strip_tags((string) $value));
    $value = preg_replace('/[[:cntrl:]]/', '', $value);
    return substr($value, 0, $maxLength);
  }

  private static function _sanitizeSecret($value, $maxLength){
    $value = trim((string) $value);
    $value = preg_replace('/[[:cntrl:]]/', '', $value);
    return substr($value, 0, $maxLength);
  }
}

/**
 * Perfect Money IPN verification and deposit confirmation.
 *
 * @package Krypto
 */
class PerfectMoneyIpnProcessor {

  private $repository;
  private $config;

  public function __construct($repository, $config = []){
    $this->repository = $repository;
    $this->config = array_merge([
      'enabled' => false,
      'payee_account' => '',
      'alternate_passphrase' => '',
      'confirmation_status' => '1',
      'allowed_currencies' => ['USD', 'EUR', 'OAU']
    ], (is_array($config) ? $config : []));
  }

  public static function _requiredFields(){
    return [
      'PAYMENT_ID',
      'PAYEE_ACCOUNT',
      'PAYMENT_AMOUNT',
      'PAYMENT_UNITS',
      'PAYMENT_BATCH_NUM',
      'PAYER_ACCOUNT',
      'TIMESTAMPGMT',
      'V2_HASH'
    ];
  }

  public static function _expectedV2Hash($payload, $alternatePassphrase){
    $alternatePhraseHash = strtoupper(md5((string) $alternatePassphrase));
    $hash = implode(':', [
      self::_scalarPayloadValue($payload, 'PAYMENT_ID'),
      self::_scalarPayloadValue($payload, 'PAYEE_ACCOUNT'),
      self::_scalarPayloadValue($payload, 'PAYMENT_AMOUNT'),
      strtoupper(self::_scalarPayloadValue($payload, 'PAYMENT_UNITS')),
      self::_scalarPayloadValue($payload, 'PAYMENT_BATCH_NUM'),
      self::_scalarPayloadValue($payload, 'PAYER_ACCOUNT'),
      $alternatePhraseHash,
      self::_scalarPayloadValue($payload, 'TIMESTAMPGMT')
    ]);

    return strtoupper(md5($hash));
  }

  public function _process($payload){
    if(!$this->config['enabled']) throw new Exception('Perfect Money is disabled.', 1);

    $data = self::_validatedPayload($payload);
    $alternatePassphrase = trim((string) $this->config['alternate_passphrase']);
    if($alternatePassphrase === '') throw new Exception('Perfect Money alternate passphrase is not configured.', 1);

    $expectedHash = self::_expectedV2Hash($data, $alternatePassphrase);
    if(!hash_equals($expectedHash, $data['V2_HASH'])){
      throw new Exception('Perfect Money V2_HASH mismatch.', 1);
    }

    $expectedPayeeAccount = trim((string) $this->config['payee_account']);
    if($expectedPayeeAccount === '' || !hash_equals($expectedPayeeAccount, $data['PAYEE_ACCOUNT'])){
      throw new Exception('Perfect Money payee account mismatch.', 1);
    }

    $allowedCurrencies = array_map('strtoupper', (array) $this->config['allowed_currencies']);
    if(!in_array($data['PAYMENT_UNITS'], $allowedCurrencies, true)){
      throw new Exception('Perfect Money currency is not allowed.', 1);
    }

    $processed = $this->repository->_findProcessedPerfectMoneyBatch($data['PAYMENT_BATCH_NUM']);
    if(!is_null($processed)){
      return [
        'status' => 'duplicate',
        'payment_id' => $data['PAYMENT_ID'],
        'payment_batch_num' => $data['PAYMENT_BATCH_NUM']
      ];
    }

    $deposit = $this->repository->_findPendingPerfectMoneyDeposit($data['PAYMENT_ID']);
    if(!is_array($deposit)) throw new Exception('Perfect Money pending deposit not found.', 1);

    $this->_assertDepositMatchesPayload($deposit, $data);

    $auditPayload = json_encode(self::_auditPayload($data));
    if($auditPayload === false) throw new Exception('Perfect Money audit payload encoding failed.', 1);

    $confirmed = $this->repository->_confirmPerfectMoneyDeposit($deposit, $auditPayload, $this->config['confirmation_status']);
    if(!$confirmed) throw new Exception('Perfect Money deposit confirmation failed.', 1);

    return [
      'status' => 'confirmed',
      'payment_id' => $data['PAYMENT_ID'],
      'payment_batch_num' => $data['PAYMENT_BATCH_NUM']
    ];
  }

  public static function _auditPayload($data){
    return [
      'provider' => 'perfectmoney',
      'payment_id' => $data['PAYMENT_ID'],
      'payee_account' => $data['PAYEE_ACCOUNT'],
      'payment_amount' => $data['PAYMENT_AMOUNT'],
      'payment_units' => $data['PAYMENT_UNITS'],
      'payment_batch_num' => $data['PAYMENT_BATCH_NUM'],
      'timestamp_gmt' => $data['TIMESTAMPGMT'],
      'verified_at' => time()
    ];
  }

  private static function _validatedPayload($payload){
    if(!is_array($payload) || count($payload) == 0) throw new Exception('Perfect Money payload is empty.', 1);

    $data = [];
    foreach (self::_requiredFields() as $field) {
      if(!array_key_exists($field, $payload) || !is_scalar($payload[$field])){
        throw new Exception('Perfect Money payload missing '.$field.'.', 1);
      }
      $value = trim((string) $payload[$field]);
      if($value === '') throw new Exception('Perfect Money payload has empty '.$field.'.', 1);
      if(strpos($value, ':') !== false) throw new Exception('Perfect Money payload has invalid '.$field.'.', 1);
      $data[$field] = $value;
    }

    $data['PAYMENT_UNITS'] = strtoupper($data['PAYMENT_UNITS']);
    $data['V2_HASH'] = strtoupper($data['V2_HASH']);

    if(!preg_match('/^[A-Za-z0-9._-]{1,128}$/', $data['PAYMENT_ID'])){
      throw new Exception('Perfect Money payment id is invalid.', 1);
    }
    if(!preg_match('/^[A-Za-z][0-9]{3,32}$/', $data['PAYEE_ACCOUNT'])){
      throw new Exception('Perfect Money payee account is invalid.', 1);
    }
    if(!is_numeric($data['PAYMENT_AMOUNT']) || floatval($data['PAYMENT_AMOUNT']) <= 0){
      throw new Exception('Perfect Money amount is invalid.', 1);
    }
    if(!preg_match('/^[A-Z]{3}$/', $data['PAYMENT_UNITS'])){
      throw new Exception('Perfect Money currency is invalid.', 1);
    }
    if(!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $data['PAYMENT_BATCH_NUM'])){
      throw new Exception('Perfect Money batch number is invalid.', 1);
    }
    if(!preg_match('/^[A-Za-z][0-9]{3,32}$/', $data['PAYER_ACCOUNT'])){
      throw new Exception('Perfect Money payer account is invalid.', 1);
    }
    if(!preg_match('/^[0-9]{1,14}$/', $data['TIMESTAMPGMT'])){
      throw new Exception('Perfect Money timestamp is invalid.', 1);
    }
    if(!preg_match('/^[A-F0-9]{32}$/', $data['V2_HASH'])){
      throw new Exception('Perfect Money V2_HASH is invalid.', 1);
    }

    return $data;
  }

  private static function _scalarPayloadValue($payload, $key){
    if(!is_array($payload) || !array_key_exists($key, $payload) || !is_scalar($payload[$key])) return '';
    return trim((string) $payload[$key]);
  }

  private function _assertDepositMatchesPayload($deposit, $data){
    if(strtolower((string) $deposit['payment_type_deposit_history']) !== 'perfectmoney'){
      throw new Exception('Perfect Money deposit type mismatch.', 1);
    }

    if((string) $deposit['payment_status_deposit_history'] !== '0'){
      throw new Exception('Perfect Money deposit is not pending.', 1);
    }

    $expectedAmount = floatval($deposit['amount_deposit_history']);
    if(array_key_exists('fees_deposit_history', $deposit)){
      $expectedAmount += floatval($deposit['fees_deposit_history']);
    }

    if(!self::_amountsMatch($expectedAmount, $data['PAYMENT_AMOUNT'])){
      throw new Exception('Perfect Money amount mismatch.', 1);
    }

    if(strtoupper(trim((string) $deposit['currency_deposit_history'])) !== $data['PAYMENT_UNITS']){
      throw new Exception('Perfect Money currency mismatch.', 1);
    }
  }

  private static function _amountsMatch($expected, $actual){
    return abs(floatval($expected) - floatval($actual)) < 0.00000001;
  }
}

/**
 * PerfectMoney class
 *
 * @package Krypto
 * @author Ovrley <hello@ovrley.com>
 */
class PerfectMoney extends MySQL
{
    /**
     * App object
     * @var App
     */
    private $App = null;

    /**
     * Perfect Money constructor
     * @param App $App App object
     */
    public function __construct($App = null)
    {
        if (is_null($App)) {
            throw new Exception("Error PerfectMoney : App need to be given", 1);
        }
        $this->App = $App;

    }

    /**
     * Get app object
     * @return App App object
     */
    private function _getApp()
    {
        if (is_null($this->App)) {
            throw new Exception("Error PerfectMoney : App not defined", 1);
        }
        return $this->App;
    }

    public function _getListCurrencyAvailable(){
      return ['USD', 'EUR', 'OAU'];
    }

    public function _createDeposit($User, $Amount, $Balance, $currency = 'USD'){

      if(!$this->_getApp()->_getPerfectMoneyEnabled()) throw new Exception("Error : Perfect money is not enabled", 1);

      $refDeposit = $Balance->_generatePaymentReference();

      $currency = strtoupper((string) $currency);
      if(!in_array($currency, $this->_getListCurrencyAvailable(), true)) throw new Exception("Error : Symbol not available", 1);

      $Balance->_addDeposit($Amount, 'perfectmoney', null, $currency, "", 0, $currency, $refDeposit);

      return $refDeposit;


    }

    public function _checkPayment($infos){
      $processor = new PerfectMoneyIpnProcessor($this, [
        'enabled' => $this->_getApp()->_getPerfectMoneyEnabled(),
        'payee_account' => $this->_getApp()->_getPerfectMoneyPayeeAccount(),
        'alternate_passphrase' => $this->_getApp()->_getPerfectMoneyAlternatePassphrase(),
        'confirmation_status' => '1',
        'allowed_currencies' => $this->_getListCurrencyAvailable()
      ]);

      return $processor->_process($infos);
    }

    public function _findProcessedPerfectMoneyBatch($paymentBatchNum){
      $batchPattern = '%"payment_batch_num":"'.self::_escapeSqlLike($paymentBatchNum).'"%';
      $r = parent::querySqlRequest("SELECT * FROM deposit_history_krypto
                                    WHERE payment_type_deposit_history=:payment_type_deposit_history
                                      AND payment_status_deposit_history<>:payment_status_deposit_history
                                      AND payment_data_deposit_history LIKE :payment_data_deposit_history
                                    ORDER BY id_deposit_history DESC LIMIT 1",
                                  [
                                    'payment_type_deposit_history' => 'perfectmoney',
                                    'payment_status_deposit_history' => '0',
                                    'payment_data_deposit_history' => $batchPattern
                                  ]);

      return (count($r) > 0 ? $r[0] : null);
    }

    public function _findPendingPerfectMoneyDeposit($paymentId){
      $r = parent::querySqlRequest("SELECT * FROM deposit_history_krypto
                                    WHERE ref_deposit_history=:ref_deposit_history
                                      AND payment_type_deposit_history=:payment_type_deposit_history
                                      AND payment_status_deposit_history=:payment_status_deposit_history
                                    ORDER BY id_deposit_history DESC LIMIT 1",
                                  [
                                    'ref_deposit_history' => $paymentId,
                                    'payment_type_deposit_history' => 'perfectmoney',
                                    'payment_status_deposit_history' => '0'
                                  ]);

      return (count($r) > 0 ? $r[0] : null);
    }

    public function _confirmPerfectMoneyDeposit($deposit, $auditPayload, $status){
      if(!is_array($deposit) || !array_key_exists('id_deposit_history', $deposit)) return false;

      $req = self::getSqlConnexion()->prepare("UPDATE deposit_history_krypto
                                                SET payment_status_deposit_history=:payment_status_deposit_history,
                                                    payment_data_deposit_history=:payment_data_deposit_history
                                                WHERE id_deposit_history=:id_deposit_history
                                                  AND payment_type_deposit_history=:payment_type_deposit_history
                                                  AND payment_status_deposit_history=:pending_status");
      $ok = $req->execute([
        'payment_status_deposit_history' => (string) $status,
        'payment_data_deposit_history' => $auditPayload,
        'id_deposit_history' => $deposit['id_deposit_history'],
        'payment_type_deposit_history' => 'perfectmoney',
        'pending_status' => '0'
      ]);
      $updatedRows = $req->rowCount();
      $req->closeCursor();

      return $ok && $updatedRows === 1;
    }

    private static function _escapeSqlLike($value){
      return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], (string) $value);
    }
}
