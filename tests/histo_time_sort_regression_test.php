<?php

// Regression coverage for SEC-34 (#149): price history points must be sorted
// by ascending timestamp before graph data is keyed and rendered.

$root = dirname(__DIR__);

if(!class_exists('MySQL')) {
  class MySQL {
    public static $histoRows = [];
    public static $queries = [];

    public static function querySqlRequest($query, $def = []) {
      self::$queries[] = ['query' => $query, 'params' => $def];

      if(strpos($query, 'FROM histo_krypto') === false) {
        return [];
      }

      $matches = [];
      foreach (self::$histoRows as $row) {
        if($row['coin_histo'] !== $def['coin_histo']) continue;
        if($row['currency_histo'] !== $def['currency_histo']) continue;
        if($row['type_histo'] !== $def['type_histo']) continue;
        $matches[] = $row;
      }

      return $matches;
    }

    public static function execSqlRequest($query, $def = []) {
      throw new Exception('Cached history sort test should not write histo rows.');
    }
  }
}

require_once $root.'/app/src/CryptoApi/CryptoHisto.php';
require_once $root.'/app/src/CryptoApi/CryptoCoin.php';

function histo_time_sort_assert_same($expected, $actual, $message) {
  if($expected !== $actual) {
    throw new Exception($message.' Expected '.var_export($expected, true).', got '.var_export($actual, true));
  }
}

class HistoTimeSortFakeApi {
  public $calls = 0;

  public function _getCurrency() {
    return 'USD';
  }

  public function _getData($type, $options) {
    $this->calls++;
    throw new Exception('Cached history sort test should not call the remote API.');
  }
}

function histo_time_sort_point($time) {
  return [
    'time' => $time,
    'open' => $time + 1,
    'high' => $time + 2,
    'low' => $time - 1,
    'close' => $time,
    'volumefrom' => 1,
    'volumeto' => $time,
  ];
}

$currentMinute = new DateTime('now');
$currentMinute->setTime(date('G'), date('i'), 0);

MySQL::$histoRows = [
  [
    'coin_histo' => 'BTC',
    'currency_histo' => 'USD',
    'type_histo' => 'histominute/Kraken',
    'last_update_histo' => $currentMinute->getTimestamp(),
    'data_histo' => json_encode([
      histo_time_sort_point(300),
      histo_time_sort_point(200),
      histo_time_sort_point(100),
      histo_time_sort_point(400),
    ]),
  ],
];
MySQL::$queries = [];

$api = new HistoTimeSortFakeApi();
$coin = new CryptoCoin($api, 'BTC', ['Symbol' => 'BTC'], 'Kraken');
$history = $coin->_getHistoMin(4);

histo_time_sort_assert_same(0, $api->calls, 'Fresh cached history should not call the remote API.');
histo_time_sort_assert_same([100, 200, 300, 400], array_keys($history), 'History points should be sorted by ascending time.');

echo "Histo time sort regression checks passed.\n";

?>
