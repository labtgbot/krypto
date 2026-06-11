<?php

class BitcoinExplorer extends MySQL {

  private $type = "bitcoin";

  private $App = null;


  public function __construct($App, $User = null){
    $this->App = $App;
  }

  public function _getApp(){
    return $this->App;
  }

  private function _getApiKey(){

  }

  private function _validateBitcoinAddress($address){
    if(!is_string($address) && !is_numeric($address)) throw new InvalidArgumentException('Invalid Bitcoin address.');
    $address = trim((string) $address);
    if(preg_match('/\A(?:[13][a-km-zA-HJ-NP-Z1-9]{25,34}|bc1[a-z0-9]{11,87})\z/i', $address) !== 1){
      throw new InvalidArgumentException('Invalid Bitcoin address.');
    }
    return $address;
  }

  private function _validateBitcoinTransactionHash($tx){
    if(!is_string($tx) && !is_numeric($tx)) throw new InvalidArgumentException('Invalid Bitcoin transaction hash.');
    $tx = trim((string) $tx);
    if(preg_match('/\A[a-fA-F0-9]{64}\z/', $tx) !== 1){
      throw new InvalidArgumentException('Invalid Bitcoin transaction hash.');
    }
    return $tx;
  }

  private function _encodePathSegments($args){
    if(!is_array($args)) throw new InvalidArgumentException('Invalid blockchain.info path.');
    $encoded = [];
    foreach ($args as $arg) {
      if(!is_string($arg) && !is_numeric($arg)) throw new InvalidArgumentException('Invalid blockchain.info path segment.');
      $arg = trim((string) $arg);
      if(preg_match('/\A[A-Za-z0-9]{1,128}\z/', $arg) !== 1){
        throw new InvalidArgumentException('Invalid blockchain.info path segment.');
      }
      $encoded[] = rawurlencode($arg);
    }
    return $encoded;
  }

  public function _call($args){
    $ch =  curl_init("https://blockchain.info/".implode('/', $this->_encodePathSegments($args)));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_ENCODING,  '');
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 );
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));

    $s = json_decode(curl_exec($ch), true);

    curl_close($ch);

    if(is_null($s)) throw new Exception("Error : Fail to fetch etherblock", 1);

    return $s;


  }

  public function _getHistoryTransaction($address){
    $address = $this->_validateBitcoinAddress($address);
    $transactionList = $this->_call(['rawaddr', $address])['txs'];
    $receiveTransaction = [];
    foreach ($transactionList as $key => $value) {
      foreach ($value['out'] as $keyVInput => $valueVInput) {
        if($valueVInput['addr'] == $address){
          $receiveTransaction[] = $value;
          break;
        }
      }
    }

    foreach ($receiveTransaction as $key => $value) {
      // code...
    }

    return $receiveTransaction;
  }

  private $CurrentBlockHeight = null;
  public function _getBlockHeight(){
    if(!is_null($this->CurrentBlockHeight)) return $this->CurrentBlockHeight;
    $this->CurrentBlockHeight = $this->_call(['q', 'getblockcount']);
    return $this->CurrentBlockHeight;
  }

  public function _getNumberConfirmation($block){
    return $this->_getBlockHeight() - $block + 1;
  }

  public function _getTransactionInfos($tx){

    $tx = $this->_validateBitcoinTransactionHash($tx);
    $transactionInfos = $this->_call(['rawtx', $tx]);
    $transactionInfos['confirmation'] = $this->_getNumberConfirmation($transactionInfos['block_height']);
    return $transactionInfos;
    //https://blockchain.info/rawtx/

  }

  public function _hexArrayToDecimal($arr){

  }


}

?>
