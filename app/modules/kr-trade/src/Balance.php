<?php

/**
 * Compatibility facade for legacy payment callbacks after OPEN-05.
 *
 * Legacy custodial balances, orders, withdrawals, and exchange connectors were
 * removed. Some payment providers still record status in deposit_history_krypto,
 * so this class keeps those ledger writes alive without touching retired
 * custody tables.
 */
class Balance extends MySQL {

  private $User = null;
  private $App = null;
  private $Type = 'real';

  public function __construct($User = null, $App = null, $type = 'real', $IDBalance = null){
    $this->User = $User;
    $this->App = $App;
    if(!is_null($type)) $this->Type = $type;
  }

  public function _getUser(){
    if(is_null($this->User)) $this->User = new User();
    return $this->User;
  }

  public function _getApp(){
    if(is_null($this->App)) $this->App = new App();
    return $this->App;
  }

  public function _getType(){ return $this->Type; }
  public function _getBalanceType(){ return $this->_getType(); }
  public function _isPractice(){ return $this->_getType() === 'practice'; }
  public function _getCurrentBalance(){ return $this; }
  public function _getBalanceByID($bid){ return $this; }
  public function _getBalanceID($encrypted = false){
    return $encrypted ? App::encrypt_decrypt('encrypt', '0') : 0;
  }

  public function _getBalanceList(){ return [$this]; }
  public function _getBalanceListResum(){
    $symbols = $this->_getDepositListAvailable();
    if(count($symbols) === 0) $symbols = ['USD'];
    return array_fill_keys($symbols, 0);
  }

  public function _getBalanceValue(){ return 0; }
  public function _getAmountCrypto($crypto){ return 0; }
  public function _getBalanceInvestisment(){ return 0; }
  public function _getEstimationBalance($symbol = null){ return 0; }
  public function _getEstimationPayBalance(){ return 0; }
  public function _getEstimationSymbol($raw = false){
    try {
      return $this->_getApp()->_getBalanceEstimationSymbol();
    } catch (Exception $e) {
      return 'USD';
    }
  }

  private function _currencyRows(){
    try {
      return parent::querySqlRequest("SELECT * FROM currency_krypto ORDER BY code_iso_currency");
    } catch (Exception $e) {
      return [];
    }
  }

  public function _getListMoney(){
    $list = [];
    foreach ($this->_currencyRows() as $currency) {
      $list[] = $currency['code_iso_currency'];
    }
    return count($list) > 0 ? $list : ['USD', 'EUR', 'GBP'];
  }

  public function _getInfosMoney($codeiso){
    $codeiso = strtoupper($codeiso);
    foreach ($this->_currencyRows() as $currency) {
      if(strtoupper($currency['code_iso_currency']) === $codeiso) return $currency;
    }
    return [
      'code_iso_currency' => $codeiso,
      'symbol_currency' => $codeiso,
      'name_currency' => $codeiso,
      'usd_rate_currency' => 1
    ];
  }

  public function _getInfoCryptoCurrency($codeiso){
    $r = parent::querySqlRequest("SELECT * FROM coinlist_krypto WHERE symbol_coinlist=:symbol_coinlist", [
      'symbol_coinlist' => strtoupper($codeiso)
    ]);
    if(count($r) > 0) return $r[0];
    return [
      'symbol_coinlist' => strtoupper($codeiso),
      'coinname_coinlist' => strtoupper($codeiso)
    ];
  }

  public function _symbolIsMoney($symbol){
    return in_array(strtoupper($symbol), array_map('strtoupper', $this->_getListMoney()));
  }

  public function _symbolAbrev($symbol){
    foreach ($this->_currencyRows() as $currency) {
      if($currency['symbol_currency'] === $symbol) return $currency['code_iso_currency'];
    }
    return strtoupper($symbol);
  }

  public function _getDepositListAvailable(){
    $configured = $this->_getApp()->_getListCurrencyDepositAvailable();
    if(is_array($configured) && count($configured) > 0) {
      return array_values(array_unique(array_map('strtoupper', $configured)));
    }
    return $this->_getListMoney();
  }

  public function _convertCurrency($value, $from, $to, $market = null){
    if(strtoupper($from) === strtoupper($to)) return $value;
    try {
      $fromInfo = $this->_getInfosMoney($from);
      $toInfo = $this->_getInfosMoney($to);
      if(isset($fromInfo['usd_rate_currency']) && isset($toInfo['usd_rate_currency']) && floatval($toInfo['usd_rate_currency']) > 0){
        return (floatval($value) / floatval($fromInfo['usd_rate_currency'])) * floatval($toInfo['usd_rate_currency']);
      }
    } catch (Exception $e) { }
    return $value;
  }

  public function _getPaymentGatewayFee($paymentGateway = null){
    if(is_null($paymentGateway)) return 0;
    $map = [
      'coingate' => '_getCoingatePaymentFees',
      'blockonomics' => '_getBlockonomicsPaymentFees',
      'coinbasecommerce' => '_getCoinbaseCommercePaymentFees',
      'coinpayments' => '_getCoinpaymentPaymentFees',
      'payeer' => '_getPayeerPaymentFees',
      'mollie' => '_getMolliePaymentFees',
      'raveflutterwave' => '_getRaveflutterwavePaymentFees',
      'banktransfert' => '_getBankTransfertPaymentFees',
      'paystack' => '_getPaystackFees',
      'polipayments' => '_getPolipaymentsFees'
    ];
    if(isset($map[$paymentGateway]) && method_exists($this->_getApp(), $map[$paymentGateway])){
      return $this->_getApp()->{$map[$paymentGateway]}();
    }
    return 0;
  }

  private function _randomReference($pattern){
    $result = str_split($pattern);
    foreach ($result as $key => $value) {
      if($value === '$') $result[$key] = mt_rand(0, 9);
      if($value === '*') $result[$key] = chr(mt_rand(65, 90));
    }
    return join('', $result);
  }

  public function _generatePaymentReference(){
    $ref = $this->_randomReference($this->_getApp()->_paymentReferencePattern());
    $r = parent::querySqlRequest("SELECT * FROM deposit_history_krypto WHERE ref_deposit_history=:ref_deposit_history", [
      'ref_deposit_history' => $ref
    ]);
    if(count($r) > 0) return $this->_generatePaymentReference();
    return $ref;
  }

  public function _addDeposit($amount, $payment_type = 'referal', $description = null, $currency = 'USD', $datapayment = "", $payment_status = 1, $wallet_target = null, $payment_reference = null){
    $fees = 0;
    if($payment_type !== 'referal' && $payment_type !== 'Initial' && $payment_type !== 'Manager_update'){
      $fees = floatval($amount) * (($this->_getApp()->_getFeesDeposit() + $this->_getPaymentGatewayFee($payment_type)) / 100);
      $amount = floatval($amount) - $fees;
    }

    if(is_null($wallet_target)) $wallet_target = strtoupper($currency);
    if(is_null($payment_reference)) $payment_reference = $this->_generatePaymentReference();

    $r = parent::execSqlRequest("INSERT INTO deposit_history_krypto (id_user, amount_deposit_history, date_deposit_history, balance_deposit_history, payment_status_deposit_history, payment_type_deposit_history, description_deposit_history, currency_deposit_history, fees_deposit_history, payment_data_deposit_history, wallet_deposit_history, ref_deposit_history) VALUES
                                 (:id_user, :amount_deposit_history, :date_deposit_history, :balance_deposit_history, :payment_status_deposit_history, :payment_type_deposit_history, :description_deposit_history, :currency_deposit_history, :fees_deposit_history, :payment_data_deposit_history, :wallet_deposit_history, :ref_deposit_history)",
                                [
                                  'id_user' => $this->_getUser()->_getUserID(),
                                  'amount_deposit_history' => number_format(floatval($amount), 8, '.', ''),
                                  'date_deposit_history' => time(),
                                  'balance_deposit_history' => $this->_getBalanceID(),
                                  'payment_status_deposit_history' => $payment_status,
                                  'payment_type_deposit_history' => $payment_type,
                                  'description_deposit_history' => (!is_null($description) ? $description : 'Deposit '.rtrim($amount, '0').' '.$currency.' ('.rtrim($fees, '0').' '.$currency.' fees)'),
                                  'currency_deposit_history' => strtoupper($currency),
                                  'fees_deposit_history' => number_format($fees, 8, '.', ''),
                                  'payment_data_deposit_history' => $datapayment,
                                  'wallet_deposit_history' => $wallet_target,
                                  'ref_deposit_history' => $payment_reference
                                ]);

    if(!$r) throw new Exception("Error SQL : Fail to add deposit in database", 1);
    return $payment_reference;
  }

  public function _validateDeposit($keycharge, $status, $amount, $typepayment, $datapayment, $fees = 0){
    return $this->_addDeposit($amount, $typepayment, ucfirst($typepayment).' payment', 'USD', json_encode($datapayment), $status, 'USD', $keycharge);
  }

  public function _depositAlreadyDone($datapayment){
    $r = parent::querySqlRequest("SELECT * FROM deposit_history_krypto WHERE payment_data_deposit_history LIKE :payment_data_deposit_history AND id_user=:id_user", [
      'id_user' => $this->_getUser()->_getUserID(),
      'payment_data_deposit_history' => '%'.$datapayment.'%'
    ]);
    return count($r) > 0;
  }

  public function _getDepositInfosByRef($datapayment){
    $r = parent::querySqlRequest("SELECT * FROM deposit_history_krypto WHERE payment_data_deposit_history LIKE :payment_data_deposit_history OR ref_deposit_history=:ref_deposit_history", [
      'payment_data_deposit_history' => '%'.$datapayment.'%',
      'ref_deposit_history' => $datapayment
    ]);
    if(count($r) === 0) throw new Exception('Fail to receive payment : '.$datapayment);
    return $r[0];
  }

  public function _getDepositHistory($lastDepositF = false){
    return parent::querySqlRequest("SELECT * FROM deposit_history_krypto WHERE id_user=:id_user ORDER BY date_deposit_history DESC", [
      'id_user' => $this->_getUser()->_getUserID()
    ]);
  }

  public function _changeDepositStatus($datapayment, $new_status = 1){
    $req = self::getSqlConnexion()->prepare("UPDATE deposit_history_krypto
                                             SET payment_status_deposit_history=:payment_status_deposit_history
                                             WHERE id_user=:id_user
                                               AND payment_data_deposit_history=:payment_data_deposit_history
                                               AND payment_status_deposit_history=:pending_status");
    $ok = $req->execute([
      'id_user' => $this->_getUser()->_getUserID(),
      'payment_data_deposit_history' => $datapayment,
      'payment_status_deposit_history' => (string) $new_status,
      'pending_status' => '0'
    ]);
    $updatedRows = $req->rowCount();
    $req->closeCursor();

    if(!$ok || $updatedRows !== 1) throw new Exception("Error : Fail to change status deposit", 1);
    return true;
  }

  public function _updateDepositPaymentData($deposit_ref, $datapayment){
    $r = parent::execSqlRequest("UPDATE deposit_history_krypto SET payment_data_deposit_history=:payment_data_deposit_history WHERE ref_deposit_history=:ref_deposit_history AND id_user=:id_user", [
      'ref_deposit_history' => $deposit_ref,
      'id_user' => $this->_getUser()->_getUserID(),
      'payment_data_deposit_history' => $datapayment
    ]);
    if(!$r) throw new Exception("Error SQL : Fail to update deposit payment data", 1);
  }

  public function _validDeposit($orderid, $paymentgateway = 'coingate'){
    $r = parent::execSqlRequest("UPDATE deposit_history_krypto SET payment_status_deposit_history=:payment_status_deposit_history WHERE id_user=:id_user AND payment_data_deposit_history LIKE :payment_data_deposit_history AND payment_type_deposit_history=:payment_type_deposit_history", [
      'payment_status_deposit_history' => '1',
      'id_user' => $this->_getUser()->_getUserID(),
      'payment_data_deposit_history' => '%'.$orderid.'%',
      'payment_type_deposit_history' => $paymentgateway
    ]);
    if(!$r) throw new Exception("Error : Fail to change order status (".$orderid.")", 1);
    return true;
  }

  public function _getPaymentStatus($type, $time){
    $r = parent::querySqlRequest("SELECT * FROM deposit_history_krypto WHERE payment_type_deposit_history=:payment_type_deposit_history AND id_user=:id_user AND date_deposit_history > :date_deposit_history ORDER BY date_deposit_history DESC LIMIT 1", [
      'payment_type_deposit_history' => strtolower($type),
      'id_user' => $this->_getUser()->_getUserID(),
      'date_deposit_history' => $time
    ]);
    if(count($r) === 0) throw new Exception("Error : Payment not found");
    $r = $r[0];
    return [
      'ref' => $r['ref_deposit_history'],
      'type' => $r['payment_type_deposit_history'],
      'amount' => $r['amount_deposit_history'],
      'fees' => $r['fees_deposit_history'],
      'currency' => $r['currency_deposit_history'],
      'wallet' => $r['wallet_deposit_history'],
      'enc_ref' => App::encrypt_decrypt('encrypt', $r['ref_deposit_history'])
    ];
  }

  public function _checkPaymentResult(){ return true; }

  public function _getWidthdrawHistory($onlyapproved = false, $all = false){ return []; }
  public function _getOrderHistory($side = null, $symbol = null, $currency = null){ return []; }
  public function _getOrderInfos($id){ throw new Exception('Legacy custody orders are retired.', 1); }
  public function _getTransactionsHistory(){ return []; }
  public function _getTradedPair(){ return []; }
  public function _getListTrade($symbol = null, $date = null){ return []; }
  public function _changeActiveBalance($bid){ return true; }
  public function _setDoneWithdraw($id){ throw new Exception('Legacy custody withdrawals are retired.', 1); }
  public function _setCancelWithdraw($id){ throw new Exception('Legacy custody withdrawals are retired.', 1); }
  public function _askWidthdraw(){ throw new Exception('Legacy custody withdrawals are retired.', 1); }
  public function _askWidthdrawApprove(){ throw new Exception('Legacy custody withdrawals are retired.', 1); }
  public function _saveOrder(){ throw new Exception('Legacy custody orders are retired.', 1); }
  public function _updateOrder(){ throw new Exception('Legacy custody orders are retired.', 1); }
  public function _cancelOrder(){ throw new Exception('Legacy custody orders are retired.', 1); }

}

?>
