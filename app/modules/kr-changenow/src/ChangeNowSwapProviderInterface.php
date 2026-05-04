<?php

/**
 * Provider contract for ChangeNOW-backed swap operations.
 *
 * Public quote and swap creation requests are array payloads so they can be
 * built by unauthenticated flows without constructing a User object.
 *
 * @package Krypto
 */
interface ChangeNowSwapProviderInterface {

  public function _getProviderCode();

  public function _getProductModes();

  public function _listCurrencies($filters = []);

  public function _listPairs($filters = []);

  public function _getQuote($quoteRequest);

  public function _createSwap($swapRequest);

  public function _getSwapStatus($transactionId);

  public function _validateAddress($currency, $address, $network = null);

}

?>
