<?php

class ChainSo extends MySQL {


  private $App = null;

  private $Symbol = null;


  public function __construct($App, $Symbol = "LTC"){
    $this->App = $App;
    $this->Symbol = $this->_normalizeSymbol($Symbol);
  }

  public function _getApp(){
    return $this->App;
  }

  public function _getSymbol(){
    return $this->Symbol;
  }

  private function _getApiKey(){
    //https://chain.so/api/v2/get_confidence/LTC/2ae79b79b82b545b43cde08d8a22950b5e7b8da7c619aef14f49b0e9fda1f248
  }

  private function _normalizeSymbol($symbol){
    if(!is_string($symbol) && !is_numeric($symbol)) throw new InvalidArgumentException('Invalid ChainSo symbol.');
    $symbol = strtoupper(trim((string) $symbol));
    if(preg_match('/\A[A-Z0-9]{2,16}\z/', $symbol) !== 1){
      throw new InvalidArgumentException('Invalid ChainSo symbol.');
    }
    return $symbol;
  }

  private function _validateService($service){
    if(!is_string($service) && !is_numeric($service)) throw new InvalidArgumentException('Invalid ChainSo service.');
    $service = trim((string) $service);
    if(preg_match('/\A[a-zA-Z][a-zA-Z0-9_]{1,63}\z/', $service) !== 1){
      throw new InvalidArgumentException('Invalid ChainSo service.');
    }
    return $service;
  }

  private function _validateTransactionHash($tx){
    if(!is_string($tx) && !is_numeric($tx)) throw new InvalidArgumentException('Invalid ChainSo transaction hash.');
    $tx = trim((string) $tx);
    if(preg_match('/\A[a-fA-F0-9]{64}\z/', $tx) !== 1){
      throw new InvalidArgumentException('Invalid ChainSo transaction hash.');
    }
    return $tx;
  }

  private function _validatePathSegment($value){
    if(!is_string($value) && !is_numeric($value)) throw new InvalidArgumentException('Invalid ChainSo path segment.');
    $value = trim((string) $value);
    if(preg_match('/\A[A-Za-z0-9]{1,128}\z/', $value) !== 1){
      throw new InvalidArgumentException('Invalid ChainSo path segment.');
    }
    return $value;
  }

  private function _encodePathSegments($segments){
    $encoded = [];
    foreach ($segments as $segment) {
      $encoded[] = rawurlencode($segment);
    }
    return $encoded;
  }

  public function _call($service, $args){
    if(!is_array($args)) throw new InvalidArgumentException('Invalid ChainSo request arguments.');
    $service = $this->_validateService($service);
    $safeArgs = [];
    foreach ($args as $arg) {
      $safeArgs[] = (in_array($service, ['get_tx', 'get_confidence'], true) ? $this->_validateTransactionHash($arg) : $this->_validatePathSegment($arg));
    }
    $path = implode('/', $this->_encodePathSegments(array_merge([$service, $this->_getSymbol()], $safeArgs)));
    $ch =  curl_init("https://chain.so/api/v2/".$path);
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

    if(is_null($s)) throw new Exception("Error : Fail to fetch ChainSo", 1);

    if(array_key_exists('status', $s) && $s['status'] != "success") throw new Exception($s['data']['API'], 1);

    return $s['data'];

  }

  public function _getHistoryTransaction($address){
    $this->_validatePathSegment($address);
    return [];
  }

}

?>
