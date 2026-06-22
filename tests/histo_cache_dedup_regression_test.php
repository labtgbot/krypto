<?php

// Regression coverage for SEC-33 (#148): history cache refreshes must update
// the existing market-scoped row instead of inserting duplicates every minute.

$root = dirname(__DIR__);

if(!class_exists('MySQL')) {
  class MySQL {
    public static $histoRows = [];
    public static $queries = [];
    public static $execs = [];

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
        if(array_key_exists('last_update_histo', $def) && $row['last_update_histo'] !== $def['last_update_histo']) continue;
        $matches[] = $row;
      }

      return $matches;
    }

    public static function execSqlRequest($query, $def = []) {
      self::$execs[] = ['query' => $query, 'params' => $def];

      if(strpos($query, 'INSERT INTO histo_krypto') !== false) {
        self::$histoRows[] = [
          'coin_histo' => $def['coin_histo'],
          'currency_histo' => $def['currency_histo'],
          'type_histo' => $def['type_histo'],
          'last_update_histo' => $def['last_update_histo'],
          'data_histo' => $def['data_histo'],
        ];
        return true;
      }

      if(strpos($query, 'UPDATE histo_krypto') !== false) {
        foreach (self::$histoRows as $index => $row) {
          if($row['coin_histo'] !== $def['coin_histo']) continue;
          if($row['currency_histo'] !== $def['currency_histo']) continue;
          if($row['type_histo'] !== $def['type_histo']) continue;

          self::$histoRows[$index]['last_update_histo'] = $def['last_update_histo'];
          self::$histoRows[$index]['data_histo'] = $def['data_histo'];
        }
        return true;
      }

      return true;
    }
  }
}

require_once $root.'/app/src/CryptoApi/CryptoHisto.php';
require_once $root.'/app/src/CryptoApi/CryptoCoin.php';

function histo_cache_assert($condition, $message) {
  if(!$condition) {
    throw new Exception($message);
  }
}

function histo_cache_assert_same($expected, $actual, $message) {
  if($expected !== $actual) {
    throw new Exception($message.' Expected '.var_export($expected, true).', got '.var_export($actual, true));
  }
}

class HistoCacheDedupFakeApi {
  public $calls = 0;

  public function _getCurrency() {
    return 'USD';
  }

  public function _getData($type, $options) {
    $this->calls++;

    histo_cache_assert_same('histominute', $type, 'History refresh should request minute data.');
    histo_cache_assert_same('BTC', $options['fsym'], 'History refresh should request the coin symbol.');
    histo_cache_assert_same('USD', $options['tsym'], 'History refresh should request the API currency.');

    return [
      [
        'time' => 1710000000,
        'open' => 10,
        'high' => 12,
        'low' => 9,
        'close' => 11,
        'volumefrom' => 1,
        'volumeto' => 11,
      ],
    ];
  }
}

$currentMinute = new DateTime('now');
$currentMinute->setTime(date('G'), date('i'), 0);
$staleMinute = $currentMinute->getTimestamp() - 60;

MySQL::$histoRows = [
  [
    'coin_histo' => 'BTC',
    'currency_histo' => 'USD',
    'type_histo' => 'histominute/Kraken',
    'last_update_histo' => $staleMinute,
    'data_histo' => json_encode([
      [
        'time' => 1709999940,
        'open' => 8,
        'high' => 9,
        'low' => 7,
        'close' => 8,
        'volumefrom' => 1,
        'volumeto' => 8,
      ],
    ]),
  ],
];
MySQL::$queries = [];
MySQL::$execs = [];

$api = new HistoCacheDedupFakeApi();
$coin = new CryptoCoin($api, 'BTC', ['Symbol' => 'BTC'], 'Kraken');
$history = $coin->_getHistoMin(1);

histo_cache_assert_same(1, $api->calls, 'A stale cache row should trigger exactly one API refresh.');
histo_cache_assert_same(1, count($history), 'History refresh should return the refreshed history point.');

$dedupSelects = [];
foreach (MySQL::$queries as $query) {
  if(strpos($query['query'], 'FROM histo_krypto') === false) continue;
  if(array_key_exists('last_update_histo', $query['params'])) continue;
  $dedupSelects[] = $query;
}

histo_cache_assert_same(1, count($dedupSelects), 'History refresh should run one dedup SELECT before writing.');
histo_cache_assert_same(
  'histominute/Kraken',
  $dedupSelects[0]['params']['type_histo'],
  'Dedup SELECT must use the same market-scoped type_histo key as INSERT and UPDATE.'
);

histo_cache_assert_same(1, count(MySQL::$histoRows), 'History refresh must not insert a duplicate market-scoped cache row.');
histo_cache_assert(strpos(MySQL::$execs[0]['query'], 'UPDATE histo_krypto') !== false, 'Stale market-scoped cache row should be updated.');
histo_cache_assert_same('histominute/Kraken', MySQL::$histoRows[0]['type_histo'], 'Updated cache row should keep the market-scoped key.');
histo_cache_assert_same($currentMinute->getTimestamp(), MySQL::$histoRows[0]['last_update_histo'], 'Updated cache row should receive the current minute timestamp.');

echo "Histo cache dedup regression checks passed.\n";

?>
