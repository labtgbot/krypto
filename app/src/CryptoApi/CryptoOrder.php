<?php
/**
 * CryptoOrder class
 *
 * @package Krypto
 * @author Ovrley <hello@ovrley.com>
 */
class CryptoOrder extends MySQL {

  /**
   * Historic data
   * @var Array
   */
  private $CryptoCoin = null;

  /**
   * CryptoOrder constructor
   * @param CryptoCoin Coin
   */
  public function __construct($CryptoCoin = null){
    if(is_null($CryptoCoin)) throw new Exception("Error : CryptoOrder coin not given", 1);
    $this->CryptoCoin = $CryptoCoin;
  }

  /**
   * Get cryptocoin associate to CrypoOrder
   * @return CryptoCoin
   */
  public function _getCryptoCoin(){
    if(is_null($this->CryptoCoin)) throw new Exception("Error : CryptoOrder, coin is null", 1);
    return $this->CryptoCoin;
  }

  public function _getOrderList($Currency, $User = null){
    return [];

  }

  public function _createOrder($User, $date, $type, $amount, $currency){
    throw new Exception("Legacy exchange orders are retired. Use ChangeNOW swaps.", 1);


  }


}

?>
