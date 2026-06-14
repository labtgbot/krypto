<?php

/**
 * Payeer class
 *
 * @package Krypto
 * @author Ovrley <hello@ovrley.com>
 */
class Payeer extends MySQL
{
    /**
     * App object
     * @var App
     */
    private $App = null;

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

    public function _getListCurrencyAvailable(){
      return ['USD' => 2, 'EUR' => 2, 'RUB' => 2, 'BTC' => 8, 'ETH' => 8, 'LTC' => 8, 'BCH' => 8, 'DASH' => 8];
    }

    public function _generateOrderSignature($order_infos){

      $arHash = array(
        $order_infos['m_operation_id'],
    		$order_infos['m_operation_ps'],
    		$order_infos['m_operation_date'],
    		$order_infos['m_operation_pay_date'],
    		$order_infos['m_shop'],
    		$order_infos['m_orderid'],
    		number_format($order_infos['m_amount'], $this->_getListCurrencyAvailable()[$order_infos['m_curr']], '.', ''),
    		$order_infos['m_curr'],
    		$order_infos['m_desc'],
    		$order_infos['m_status'],
        $this->_getApp()->_getPayeerAPIKey()
      );

      //return $arHash;

      return strtoupper(hash('sha256', implode(":", $arHash)));

    }

    public function _generateSignature($order_id, $amount, $currency, $description = 'Test'){


      $arHash = array(
      	$this->_getApp()->_getPayeerShopID(),
      	$order_id,
      	number_format($amount, $this->_getListCurrencyAvailable()[$currency], '.', ''),
      	$currency,
      	base64_encode($description),
        $this->_getApp()->_getPayeerAPIKey()
      );

      return [
        'signature' => strtoupper(hash('sha256', implode(":", $arHash))),
        'infos' => $arHash,
        'url' => 'https://payeer.com/merchant/?m_shop='.$this->_getApp()->_getPayeerShopID()
                                                       .'&m_orderid='.$order_id
                                                       .'&m_amount='.number_format($amount, $this->_getListCurrencyAvailable()[$currency], '.', '')
                                                       .'&m_curr='.$currency
                                                       .'&m_desc='.base64_encode($description)
                                                       .'&m_sign='.strtoupper(hash('sha256', implode(":", $arHash))).'&lang=en'
      ];
    }

    public function _createDeposit($User, $Amount, $Balance, $currency = 'USD'){

      $refDeposit = $Balance->_generatePaymentReference();

      $signature = $this->_generateSignature($refDeposit, $Amount, $currency, 'Deposit '.$refDeposit.' - '.number_format($Amount, 8).' '.$currency);
      $Balance->_addDeposit($Amount, 'payeer', null, $currency, $signature['signature'], 0, $currency, $refDeposit);


      return $signature['url'];


    }

    public function _checkPayment($infos){

      $signature = $this->_generateOrderSignature($infos);

      if($signature != $infos['m_sign']) throw new Exception("Error : Worng signature", 1);
      if($infos['m_status'] != "success") throw new Exception("Error : Payment not paid", 1);

      $infosPayment = parent::querySqlRequest("SELECT * FROM deposit_history_krypto
                                                WHERE ref_deposit_history=:ref_deposit_history
                                                  AND payment_data_deposit_history=:payment_data_deposit_history
                                                  AND payment_type_deposit_history=:payment_type_deposit_history
                                                  AND payment_status_deposit_history=:payment_status_deposit_history
                                                ORDER BY id_deposit_history DESC LIMIT 1",
                                              [
                                                'ref_deposit_history' => $infos['m_orderid'],
                                                'payment_data_deposit_history' => $signature,
                                                'payment_type_deposit_history' => 'payeer',
                                                'payment_status_deposit_history' => '0'
                                              ]);

      if(count($infosPayment) == 0) throw new Exception("Error : Payment not found", 1);

      $UserPayment = new User($infosPayment[0]['id_user']);
      $Balance = new Balance($UserPayment, $this->_getApp(), null);
      $Balance->_changeDepositStatus($signature, '1');


    }



}
