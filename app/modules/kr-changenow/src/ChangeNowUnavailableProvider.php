<?php

/**
 * Disabled ChangeNOW provider placeholder.
 *
 * This keeps the provider boundary loadable while later tasks add the HTTP
 * client and real provider implementation behind configuration flags.
 *
 * @package Krypto
 */
class ChangeNowUnavailableProvider implements ChangeNowSwapProviderInterface {

  public function _getProviderCode(){
    return 'changenow';
  }

  public function _getProductModes(){
    return ChangeNowProviderMode::_list();
  }

  public function _listCurrencies($filters = []){
    $this->_throwUnavailable();
  }

  public function _listPairs($filters = []){
    $this->_throwUnavailable();
  }

  public function _getQuote($quoteRequest){
    $this->_throwUnavailable();
  }

  public function _createSwap($swapRequest){
    $this->_throwUnavailable();
  }

  public function _getSwapStatus($transactionId){
    $this->_throwUnavailable();
  }

  public function _validateAddress($currency, $address, $network = null){
    $this->_throwUnavailable();
  }

  private function _throwUnavailable(){
    throw new Exception('ChangeNOW provider is not configured', 1);
  }

}

?>
