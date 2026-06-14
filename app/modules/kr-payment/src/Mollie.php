<?php

/**
 * Mollie deposit webhook verification and idempotent confirmation.
 *
 * @package Krypto
 */
class MollieDepositProcessor {

  private $repository;

  public function __construct($repository){
    $this->repository = $repository;
  }

  public function _process($paymentCheck){
    if(!$paymentCheck){
      return [
        'status' => 'ignored'
      ];
    }

    $payment = self::_validatedPayment($paymentCheck);

    $processed = $this->repository->_findProcessedMollieDeposit($payment['order_id']);
    if(!is_null($processed)){
      return [
        'status' => 'duplicate',
        'payment_id' => $payment['order_id']
      ];
    }

    $deposit = $this->repository->_findPendingMollieDeposit($payment['order_id'], $payment['user_id']);
    if(!is_array($deposit)) throw new Exception('Mollie pending deposit not found.', 1);

    $this->_assertDepositMatchesPayment($deposit, $payment);

    $auditPayload = json_encode(self::_auditPayload($payment));
    if($auditPayload === false) throw new Exception('Mollie audit payload encoding failed.', 1);

    $confirmed = $this->repository->_confirmMollieDeposit($deposit, $auditPayload, '1');
    if(!$confirmed) throw new Exception('Mollie deposit confirmation failed.', 1);

    return [
      'status' => 'confirmed',
      'payment_id' => $payment['order_id']
    ];
  }

  public static function _pendingAuditPayload($paymentId, $cid, $userId, $amount, $currency){
    return [
      'provider' => 'mollie',
      'payment_id' => (string) $paymentId,
      'cid' => (string) $cid,
      'user_id' => (string) $userId,
      'amount' => (string) $amount,
      'currency' => strtoupper((string) $currency),
      'created_at' => time()
    ];
  }

  public static function _auditPayload($payment){
    return [
      'provider' => 'mollie',
      'payment_id' => $payment['order_id'],
      'cid' => $payment['cid'],
      'user_id' => $payment['user_id'],
      'amount' => $payment['amount'],
      'currency' => $payment['currency'],
      'verified_at' => time()
    ];
  }

  private static function _validatedPayment($paymentCheck){
    if(!is_array($paymentCheck)) throw new Exception('Mollie payment check is invalid.', 1);

    $data = [];
    foreach (['cid', 'order_id', 'user_id', 'amount', 'currency'] as $field) {
      if(!array_key_exists($field, $paymentCheck) || !is_scalar($paymentCheck[$field])){
        throw new Exception('Mollie payment check missing '.$field.'.', 1);
      }

      $value = trim((string) $paymentCheck[$field]);
      if($value === '') throw new Exception('Mollie payment check has empty '.$field.'.', 1);
      $data[$field] = $value;
    }

    if(!preg_match('/^[A-Za-z0-9._-]{1,128}$/', $data['order_id'])){
      throw new Exception('Mollie payment id is invalid.', 1);
    }

    if(!preg_match('/^[0-9]{1,20}$/', $data['user_id'])){
      throw new Exception('Mollie user id is invalid.', 1);
    }

    if(!is_numeric($data['amount']) || floatval($data['amount']) <= 0){
      throw new Exception('Mollie amount is invalid.', 1);
    }

    $data['currency'] = strtoupper($data['currency']);
    if(!preg_match('/^[A-Z]{3}$/', $data['currency'])){
      throw new Exception('Mollie currency is invalid.', 1);
    }

    return $data;
  }

  private function _assertDepositMatchesPayment($deposit, $payment){
    if((string) $deposit['id_user'] !== (string) $payment['user_id']){
      throw new Exception('Mollie deposit user mismatch.', 1);
    }

    if(strtolower((string) $deposit['payment_type_deposit_history']) !== 'mollie'){
      throw new Exception('Mollie deposit type mismatch.', 1);
    }

    if((string) $deposit['payment_status_deposit_history'] !== '0'){
      throw new Exception('Mollie deposit is not pending.', 1);
    }

    $expectedAmount = floatval($deposit['amount_deposit_history']);
    if(array_key_exists('fees_deposit_history', $deposit)){
      $expectedAmount += floatval($deposit['fees_deposit_history']);
    }

    if(!self::_amountsMatch($expectedAmount, $payment['amount'])){
      throw new Exception('Mollie amount mismatch.', 1);
    }

    if(strtoupper(trim((string) $deposit['currency_deposit_history'])) !== $payment['currency']){
      throw new Exception('Mollie currency mismatch.', 1);
    }
  }

  private static function _amountsMatch($expected, $actual){
    return abs(floatval($expected) - floatval($actual)) < 0.00000001;
  }
}

/**
 * Mollie class
 *
 * @package Krypto
 * @author Ovrley <hello@ovrley.com>
 */
class Mollie extends MySQL
{
    /**
     * App object
     * @var App
     */
    private $App = null;

    /**
     * Mollie object
     * @var Mollie
     */
    private $Mollie = null;

    /**
     * Paypal constructor
     * @param App $App          App object
     * @param String $keycharge Charge key
     */
    public function __construct($App = null)
    {
        if (is_null($App)) {
            throw new Exception("Error Mollie : App need to be given", 1);
        }
        $this->App = $App;

        if(!$this->_getApp()->_mollieEnabled() || empty($this->_getApp()->_getMollieKey())) throw new Exception("Error : Mollie not enabled", 1);

        $this->_initMollie();

    }

    /**
     * Get app object
     * @return App App object
     */
    private function _getApp()
    {
        if (is_null($this->App)) {
            throw new Exception("Error Fortumo : App not defined", 1);
        }
        return $this->App;
    }

    /**
     * Init mollie payment
     */
    private function _initMollie(){

      $this->Mollie = new \Mollie\Api\MollieApiClient();

      $this->Mollie->setApiKey($this->_getApp()->_getMollieKey());

    }

    public static function _getCurrencyAvailable(){
      return ['AUD', 'BGN', 'CAD', 'BRL',
              'HRK', 'CZK', 'DKK', 'HKD',
              'HUF', 'ISK', 'ILS', 'JPY',
              'NOK', 'PLN', 'GBP', 'RON',
              'SEK', 'CHF', 'USD', 'EUR',
              'CAD', 'ISK', 'MXN', 'MYR',
              'NZD', 'PHP', 'RUB', 'SGD',
              'THB', 'TWD'];
    }

    /**
     * Get Mollie object
     * @return Mollie
     */
    private function _getMollieObj(){
      if(is_null($this->Mollie)) throw new Exception("Error : Mollie not init", 1);
      return $this->Mollie;
    }

    private static function _paymentIdFromPaymentObject($payment){
      if(is_object($payment)){
        if(isset($payment->id) && trim((string) $payment->id) !== ''){
          return trim((string) $payment->id);
        }

        if(method_exists($payment, 'getId')){
          $paymentId = trim((string) $payment->getId());
          if($paymentId !== '') return $paymentId;
        }
      }

      throw new Exception("Error Mollie : Missing payment id", 1);
    }

    public function _createPayment($User, $ChargePlan){

      $ChargeID = $User->_getUserID().'-'.$ChargePlan->_getPlanID().'-'.uniqid();

      return $this->_getMollieObj()->payments->create(array(
          "amount"      => round($ChargePlan->_getPrice() / 100, 2),
          "description" => $ChargePlan->_getName(),
          "redirectUrl" => APP_URL.'/dashboard.php?k='.App::encrypt_decrypt('encrypt', $ChargeID).'&c=mollie&t='.(time() + 100000),
          "webhookUrl"  => APP_URL.'/app/modules/kr-payment/src/actions/processMollie.php',
          "metadata" => [
            "cid" => App::encrypt_decrypt('encrypt', $ChargeID)
          ]
      ));
    }

    public function _createDeposit($User, $amount, $currency_deposit){

      $currency_deposit = strtoupper((string) $currency_deposit);

      $ChargeID = $User->_getUserID().'-'.base64_encode($amount).'-'.($this->_getApp()->_getFeesDeposit() > 0 ? base64_encode(($amount * ($this->_getApp()->_getFeesDeposit() / 100))) : base64_encode('0'));
      $metadataCid = App::encrypt_decrypt('encrypt', $ChargeID);

      $payment = $this->_getMollieObj()->payments->create(array(
          "amount" => [
            "value" => number_format($amount, 2, '.', ''),
            "currency" => $currency_deposit
          ],
          "description" => $User->_getUserID().' - Deposit '.$this->_getApp()->_formatNumber($amount, 2).' '.$currency_deposit.' (+'.$this->_getApp()->_formatNumber(($amount * ($this->_getApp()->_getFeesDeposit() / 100)), 2).' '.$currency_deposit.' fees)',
          "redirectUrl" => APP_URL.'/dashboard.php?v='.App::encrypt_decrypt('encrypt', $ChargeID).'&c=mollie&t='.(time() + 100000),
          "webhookUrl"  => APP_URL.'/app/modules/kr-payment/src/actions/deposit/processMollie.php',
          "metadata" => [
            "cid" => $metadataCid
          ]
      ));

      $paymentId = self::_paymentIdFromPaymentObject($payment);
      $pendingPayload = json_encode(MollieDepositProcessor::_pendingAuditPayload($paymentId, $metadataCid, $User->_getUserID(), $amount, $currency_deposit));
      if($pendingPayload === false) throw new Exception("Error Mollie : Pending payload encoding failed", 1);

      $Balance = new Balance($User, $this->_getApp(), 'real');
      $Balance->_addDeposit($amount, 'mollie', 'Mollie deposit', $currency_deposit, $pendingPayload, 0, strtoupper($currency_deposit), $paymentId);

      return $payment;
    }

    /**
     * Check payment mollie
     * @param  String Patyment id
     */
    public function _checkPayment($orderid){

      $payment  = $this->_getMollieObj()->payments->get($orderid);

      //error_log(App::encrypt_decrypt('decrypt', $payment->metadata->cid));

      if(!$payment->isPaid()) return false;

      $dataPayment = explode('-', App::encrypt_decrypt('decrypt', $payment->metadata->cid));
      if(count($dataPayment) != 3) throw new Exception("Error Mollie : Invalid CID", 1);
      error_log(json_encode($payment));


      return [
        'cid' => $payment->metadata->cid,
        'payment_data' => $payment,
        'order_id' => $orderid,
        'user_id' => $dataPayment[0],
        'plan' => $dataPayment[1],
        'uniq' => $dataPayment[2],
        'amount' => $payment->amount->value,
        "currency" => $payment->amount->currency
      ];

    }

    public function _processDepositPayment($paymentCheck){
      $processor = new MollieDepositProcessor($this);
      return $processor->_process($paymentCheck);
    }

    public function _findProcessedMollieDeposit($paymentId){
      $paymentPattern = '%"payment_id":"'.self::_escapeSqlLike($paymentId).'"%';
      $r = parent::querySqlRequest("SELECT * FROM deposit_history_krypto
                                    WHERE payment_type_deposit_history=:payment_type_deposit_history
                                      AND payment_status_deposit_history<>:pending_status
                                      AND (ref_deposit_history=:ref_deposit_history OR payment_data_deposit_history LIKE :payment_data_deposit_history)
                                    ORDER BY id_deposit_history DESC LIMIT 1",
                                  [
                                    'payment_type_deposit_history' => 'mollie',
                                    'pending_status' => '0',
                                    'ref_deposit_history' => $paymentId,
                                    'payment_data_deposit_history' => $paymentPattern
                                  ]);

      return (count($r) > 0 ? $r[0] : null);
    }

    public function _findPendingMollieDeposit($paymentId, $userId){
      $r = parent::querySqlRequest("SELECT * FROM deposit_history_krypto
                                    WHERE ref_deposit_history=:ref_deposit_history
                                      AND id_user=:id_user
                                      AND payment_type_deposit_history=:payment_type_deposit_history
                                      AND payment_status_deposit_history=:payment_status_deposit_history
                                    ORDER BY id_deposit_history DESC LIMIT 1",
                                  [
                                    'ref_deposit_history' => $paymentId,
                                    'id_user' => $userId,
                                    'payment_type_deposit_history' => 'mollie',
                                    'payment_status_deposit_history' => '0'
                                  ]);

      return (count($r) > 0 ? $r[0] : null);
    }

    public function _confirmMollieDeposit($deposit, $auditPayload, $status){
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
        'payment_type_deposit_history' => 'mollie',
        'pending_status' => '0'
      ]);
      $updatedRows = $req->rowCount();
      $req->closeCursor();

      return $ok && $updatedRows === 1;
    }

    public function _checkPaymentUser($orderid, $user){

      $r = parent::querySqlRequest("SELECT * FROM charges_krypto WHERE id_user=:id_user AND key_charges=:key_charges",
                                    [
                                      'id_user' => $user->_getUserID(),
                                      'key_charges' => $orderid
                                    ]);

      if(count($r) == 0) return false;
      if(count($r) > 0 && $r[0]['status_charges'] == "1") return true;
      return false;

    }

    public function _checkDepositUser($orderid, $user){
      $r = parent::querySqlRequest("SELECT * FROM deposit_history_krypto WHERE id_user=:id_user AND (payment_data_deposit_history LIKE :payment_data_deposit_history OR ref_deposit_history=:ref_deposit_history)",
                                    [
                                      'id_user' => $user->_getUserID(),
                                      'payment_data_deposit_history' => '%'.$orderid.'%',
                                      'ref_deposit_history' => $orderid
                                    ]);

      if(count($r) == 0) return false;
      if(count($r) > 0 && $r[0]['payment_status_deposit_history'] == "1") return true;
      return false;
    }

    private static function _escapeSqlLike($value){
      return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], (string) $value);
    }
}
