<?php

class KryptoOrderBookRequest {

  private static $marketClassMap = [
    'binance' => '\\ccxt\\binance',
    'bitbank' => '\\ccxt\\bitbank',
    'bitfinex' => '\\ccxt\\bitfinex',
    'bitmex' => '\\ccxt\\bitmex',
    'bitstamp' => '\\ccxt\\bitstamp',
    'bittrex' => '\\ccxt\\bittrex',
    'btcmarkets' => '\\ccxt\\btcmarkets',
    'cex' => '\\ccxt\\cex',
    'coinex' => '\\ccxt\\coinex',
    'coinspot' => '\\ccxt\\coinspot',
    'ethfinex' => '\\ccxt\\ethfinex',
    'exmo' => '\\ccxt\\exmo',
    'gateio' => '\\ccxt\\gateio',
    'gdax' => '\\ccxt\\gdax',
    'gemini' => '\\ccxt\\gemini',
    'hitbtc2' => '\\ccxt\\hitbtc2',
    'kraken' => '\\ccxt\\kraken',
    'kucoin' => '\\ccxt\\kucoin',
    'livecoin' => '\\ccxt\\livecoin',
    'luno' => '\\ccxt\\luno',
    'okcoinusd' => '\\ccxt\\okcoinusd',
    'okex' => '\\ccxt\\okex',
    'poloniex' => '\\ccxt\\poloniex',
    'quoinex' => '\\ccxt\\quoinex',
    'yobit' => '\\ccxt\\yobit',
  ];

  private static $marketAliases = [
    'cexio' => 'cex',
    'coinbase' => 'gdax',
    'coinbasepro' => 'gdax',
    'hitbtc' => 'hitbtc2',
  ];

  public static function exchangeClassName($market){
    $market = self::normalizeMarket($market);
    return self::$marketClassMap[$market];
  }

  public static function pairSymbol($symbol, $currency){
    return self::normalizeAssetCode($symbol, 'symbol').'/'.self::normalizeAssetCode($currency, 'currency');
  }

  public static function normalizeMarket($market){
    if(!is_string($market) && !is_numeric($market)){
      throw new InvalidArgumentException('Invalid market.');
    }

    $market = strtolower(trim((string) $market));
    if(array_key_exists($market, self::$marketAliases)){
      $market = self::$marketAliases[$market];
    }

    if(!array_key_exists($market, self::$marketClassMap)){
      throw new InvalidArgumentException('Unsupported market.');
    }

    return $market;
  }

  private static function normalizeAssetCode($value, $fieldName){
    if(!is_string($value) && !is_numeric($value)){
      throw new InvalidArgumentException('Invalid '.$fieldName.'.');
    }

    $value = strtoupper(trim((string) $value));
    if(!preg_match('/\A[A-Z0-9]{2,32}\z/', $value)){
      throw new InvalidArgumentException('Invalid '.$fieldName.'.');
    }

    return $value;
  }

}

?>
