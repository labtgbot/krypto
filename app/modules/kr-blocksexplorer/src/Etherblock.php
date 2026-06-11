<?php

class Etherblock extends MySQL {

  private $type = "ether";

  private $App = null;


  public function __construct($App, $User = null){
    $this->App = $App;
  }

  public function _getApp(){
    return $this->App;
  }

  private function _getApiKey(){
    if(!is_null($this->_getApp()) && method_exists($this->_getApp(), '_getEtherscanApiKey')) return $this->_getApp()->_getEtherscanApiKey();
    if(defined('KRYPTO_ETHERSCAN_API_KEY')) return (string) KRYPTO_ETHERSCAN_API_KEY;
    if(function_exists('krypto_env_config_value')) return (string) krypto_env_config_value('KRYPTO_ETHERSCAN_API_KEY', '');
    $value = getenv('KRYPTO_ETHERSCAN_API_KEY');
    return ($value === false ? '' : (string) $value);
  }

  private function _validateAddress($address){
    if(!is_string($address) && !is_numeric($address)) throw new InvalidArgumentException('Invalid Ethereum address.');
    $address = trim((string) $address);
    if(preg_match('/\A0x[a-fA-F0-9]{40}\z/', $address) !== 1){
      throw new InvalidArgumentException('Invalid Ethereum address.');
    }
    return $address;
  }

  private function _validateTransactionHash($tx){
    if(!is_string($tx) && !is_numeric($tx)) throw new InvalidArgumentException('Invalid Ethereum transaction hash.');
    $tx = trim((string) $tx);
    if(preg_match('/\A0x[a-fA-F0-9]{64}\z/', $tx) !== 1){
      throw new InvalidArgumentException('Invalid Ethereum transaction hash.');
    }
    return $tx;
  }

  private function _normalizeSymbol($symbol){
    if(is_null($symbol)) return null;
    if(!is_string($symbol) && !is_numeric($symbol)) throw new InvalidArgumentException('Invalid Ethereum symbol.');
    $symbol = strtoupper(trim((string) $symbol));
    if(preg_match('/\A[A-Z0-9]{2,32}\z/', $symbol) !== 1){
      throw new InvalidArgumentException('Invalid Ethereum symbol.');
    }
    return $symbol;
  }

  private function _normalizeBlockTag($block){
    if(is_int($block) || (is_string($block) && preg_match('/\A[0-9]+\z/', $block) === 1)){
      $block = (int) $block;
      if($block < 0) throw new InvalidArgumentException('Invalid Ethereum block.');
      return '0x'.dechex($block);
    }

    if(is_string($block)){
      $block = trim($block);
      if(in_array($block, ['latest', 'earliest', 'pending'], true) || preg_match('/\A0x[a-fA-F0-9]+\z/', $block) === 1){
        return $block;
      }
    }

    throw new InvalidArgumentException('Invalid Ethereum block.');
  }

  public function _call($args){

    $argsString = "?".http_build_query($args, '', '&');
    $ch =  curl_init("https://api.etherscan.io/api".$argsString);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_ENCODING,  '');
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 );
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));

    $s = json_decode(curl_exec($ch), true);

    curl_close($ch);

    if(!is_array($s)) throw new Exception("Error Etherblock : invalid response", 1);
    if(array_key_exists('status', $s) && $s['status'] != "1") throw new Exception("Error Etherblock : ".$s['message'], 1);

    if(array_key_exists('result', $s)) return $s['result'];
    return $s;
    //https://api.etherscan.io/api?module=logs&action=getLogs&fromBlock=0&toBlock=latest&address=0x33990122638b9132ca29c723bdf037f1a891a70c

  }

  public function _getHistoryTransaction($address = null, $symbol = null){
    $address = $this->_validateAddress($address);
    $symbol = $this->_normalizeSymbol($symbol);
    $transactionList = $this->_call([
      'module' => 'account',
      'action' => 'txlist',
      'startblock' => 0,
      'endblock' => '99999999',
      "sort" => "desc",
      'address' => $address,
      'apikey' => $this->_getApiKey()
    ]);

    $TransactionFormated = [];
    foreach ($transactionList as $key => $value) {
      if($value['to'] != $address) continue;
      $TransactionFormated[$value['hash']] = [
        'date' => $value['timeStamp'],
        'hash' => $value['hash'],
        'from' => $value['from'],
        'to' => $value['to'],
        'value' => $value['value'] / 1000000000000000000,
        'symbol' => $symbol,
        'confirmations' => $value['confirmations']
      ];
    }

    return $TransactionFormated;

  }

  public function _getTransactionInfos($tx){

    $tx = $this->_validateTransactionHash($tx);
    $transactionInfos = $this->_call([
      'module' => 'proxy',
      'action' => 'eth_getTransactionByHash',
      'txhash' => $tx,
      'apikey' => $this->_getApiKey()
    ]);
    $transactionInfos['sub_infos'] = $this->_getTransactionInfosSub($transactionInfos['to'], hexdec($transactionInfos['blockNumber']));
    return $transactionInfos;
    return $this->_hexArrayToDecimal($transactionInfos);

  }

  public function _getBlockInfos($block){

    $block = $this->_normalizeBlockTag($block);
    $transactionInfos = $this->_call([
      'module' => 'proxy',
      'action' => 'eth_getBlockByNumber',
      "boolean" => "true",
      'tag' => $block,
      'apikey' => $this->_getApiKey()
    ]);

    return $this->_hexArrayToDecimal($transactionInfos);

  }

  public function _hexArrayToDecimal($arr){
    $res = [];
    foreach ($arr as $key => $value) {
      if(is_array($value)){
        $res[$key] = $this->_hexArrayToDecimal($value);
      } else {
        $res[$key] = number_format(hexdec($value), 10, '.', '');
      }

    }
    return $res;
  }

  public function _getTransactionInfosSub($address, $block){

    $address = $this->_validateAddress($address);
    if(!is_int($block) && !(is_string($block) && preg_match('/\A[0-9]+\z/', $block) === 1)){
      throw new InvalidArgumentException('Invalid Ethereum block.');
    }
    $block = (int) $block;
    if($block < 0) throw new InvalidArgumentException('Invalid Ethereum block.');

    $transactionInfos = $this->_call([
      'module' => 'account',
      'action' => 'txlist',
      "address" => $address,
      'startblock' => $block,
      'endblock' => $block,
      'sort' => 'desc',
      'apikey' => $this->_getApiKey()
    ]);

    if(count($transactionInfos) == 0) return [];
    return $transactionInfos[0];

  }


}

?>
