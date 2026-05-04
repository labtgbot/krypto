<?php

/**
 * Database-backed ChangeNOW market data repository.
 *
 * @package Krypto
 */
class ChangeNowMarketRepository extends MySQL {

  private $SchemaReady = false;

  public function __construct($ensureSchema = true){
    if($ensureSchema) $this->_ensureSchema();
  }

  public function _ensureSchema(){
    if($this->SchemaReady) return true;

    foreach ($this->_schemaSql() as $sql) {
      parent::execSqlRequest($sql);
    }

    $this->SchemaReady = true;
    return true;
  }

  public function _replaceAssets($assets, $syncedAt = null){
    $this->_ensureSchema();
    $syncedAt = (is_null($syncedAt) ? time() : $syncedAt);
    parent::execSqlRequest("UPDATE changenow_assets_krypto SET provider_active_changenow_asset=0");

    foreach ($assets as $asset) {
      $this->_upsertAsset($asset, $syncedAt);
    }

    return true;
  }

  public function _replacePairs($pairs, $syncedAt = null, $flows = []){
    $this->_ensureSchema();
    $syncedAt = (is_null($syncedAt) ? time() : $syncedAt);
    parent::execSqlRequest("UPDATE changenow_pairs_krypto SET provider_active_changenow_pair=0");

    foreach ($pairs as $pair) {
      $this->_upsertPair($pair, $syncedAt);
    }

    return true;
  }

  public function _recordSyncStart($syncKey, $startedAt){
    $this->_ensureSchema();
    return parent::execSqlRequest("INSERT INTO changenow_sync_status_krypto
                                  (sync_key_changenow_sync, status_changenow_sync, message_changenow_sync, assets_count_changenow_sync,
                                   pairs_count_changenow_sync, started_at_changenow_sync, finished_at_changenow_sync)
                                  VALUES (:sync_key, 'running', '', 0, 0, :started_at, 0)
                                  ON DUPLICATE KEY UPDATE
                                    status_changenow_sync='running',
                                    message_changenow_sync='',
                                    started_at_changenow_sync=:started_at_update,
                                    finished_at_changenow_sync=0",
                                  [
                                    'sync_key' => $syncKey,
                                    'started_at' => $startedAt,
                                    'started_at_update' => $startedAt
                                  ]);
  }

  public function _recordSyncFinish($syncKey, $status, $message, $assetsCount, $pairsCount, $finishedAt){
    $this->_ensureSchema();
    return parent::execSqlRequest("INSERT INTO changenow_sync_status_krypto
                                  (sync_key_changenow_sync, status_changenow_sync, message_changenow_sync, assets_count_changenow_sync,
                                   pairs_count_changenow_sync, started_at_changenow_sync, finished_at_changenow_sync)
                                  VALUES (:sync_key, :status_sync, :message_sync, :assets_count, :pairs_count, :started_at, :finished_at)
                                  ON DUPLICATE KEY UPDATE
                                    status_changenow_sync=:status_sync_update,
                                    message_changenow_sync=:message_sync_update,
                                    assets_count_changenow_sync=:assets_count_update,
                                    pairs_count_changenow_sync=:pairs_count_update,
                                    finished_at_changenow_sync=:finished_at_update",
                                  [
                                    'sync_key' => $syncKey,
                                    'status_sync' => $status,
                                    'message_sync' => $message,
                                    'assets_count' => $assetsCount,
                                    'pairs_count' => $pairsCount,
                                    'started_at' => $finishedAt,
                                    'finished_at' => $finishedAt,
                                    'status_sync_update' => $status,
                                    'message_sync_update' => $message,
                                    'assets_count_update' => $assetsCount,
                                    'pairs_count_update' => $pairsCount,
                                    'finished_at_update' => $finishedAt
                                  ]);
  }

  public function _listSourceAssets($filters = []){
    $this->_ensureSchema();
    $params = [];
    $flowFilter = "";
    if(array_key_exists('flow', $filters) && $filters['flow'] != ''){
      $flowFilter = " AND p.flow_changenow_pair=:flow_changenow_pair";
      $params['flow_changenow_pair'] = $filters['flow'];
    }

    $rows = parent::querySqlRequest("SELECT DISTINCT a.* FROM changenow_assets_krypto a
                                     INNER JOIN changenow_pairs_krypto p
                                      ON p.from_currency_changenow_pair=a.ticker_changenow_asset
                                      AND p.from_network_changenow_pair=a.network_changenow_asset
                                     WHERE a.provider_active_changenow_asset=1
                                      AND a.admin_enabled_changenow_asset=1
                                      AND a.sell_changenow_asset=1
                                      AND p.provider_active_changenow_pair=1
                                      AND p.admin_enabled_changenow_pair=1".$flowFilter."
                                     ORDER BY a.featured_changenow_asset DESC, a.ticker_changenow_asset, a.network_changenow_asset",
                                     $params);

    return $this->_mapAssetRows($rows);
  }

  public function _listDestinationAssets($fromCurrency, $fromNetwork, $flow = null){
    $this->_ensureSchema();
    $params = [
      'from_currency' => $fromCurrency,
      'from_network' => $fromNetwork
    ];
    $flowFilter = "";
    if(!is_null($flow) && $flow != ''){
      $flowFilter = " AND p.flow_changenow_pair=:flow_changenow_pair";
      $params['flow_changenow_pair'] = $flow;
    }

    $rows = parent::querySqlRequest("SELECT DISTINCT a.* FROM changenow_assets_krypto a
                                     INNER JOIN changenow_pairs_krypto p
                                      ON p.to_currency_changenow_pair=a.ticker_changenow_asset
                                      AND p.to_network_changenow_pair=a.network_changenow_asset
                                     WHERE p.from_currency_changenow_pair=:from_currency
                                      AND p.from_network_changenow_pair=:from_network
                                      AND a.provider_active_changenow_asset=1
                                      AND a.admin_enabled_changenow_asset=1
                                      AND a.buy_changenow_asset=1
                                      AND p.provider_active_changenow_pair=1
                                      AND p.admin_enabled_changenow_pair=1".$flowFilter."
                                     ORDER BY a.featured_changenow_asset DESC, a.ticker_changenow_asset, a.network_changenow_asset",
                                     $params);

    return $this->_mapAssetRows($rows);
  }

  public function _isAssetEnabled($ticker, $network){
    $this->_ensureSchema();
    $rows = parent::querySqlRequest("SELECT id_changenow_asset FROM changenow_assets_krypto
                                     WHERE ticker_changenow_asset=:ticker
                                      AND network_changenow_asset=:network
                                      AND provider_active_changenow_asset=1
                                      AND admin_enabled_changenow_asset=1
                                     LIMIT 1",
                                     [
                                      'ticker' => $ticker,
                                      'network' => $network
                                     ]);
    return count($rows) > 0;
  }

  public function _isPairEnabled($fromCurrency, $fromNetwork, $toCurrency, $toNetwork, $flow){
    $this->_ensureSchema();
    $rows = parent::querySqlRequest("SELECT id_changenow_pair FROM changenow_pairs_krypto
                                     WHERE from_currency_changenow_pair=:from_currency
                                      AND from_network_changenow_pair=:from_network
                                      AND to_currency_changenow_pair=:to_currency
                                      AND to_network_changenow_pair=:to_network
                                      AND flow_changenow_pair=:flow_pair
                                      AND provider_active_changenow_pair=1
                                      AND admin_enabled_changenow_pair=1
                                     LIMIT 1",
                                     [
                                      'from_currency' => $fromCurrency,
                                      'from_network' => $fromNetwork,
                                      'to_currency' => $toCurrency,
                                      'to_network' => $toNetwork,
                                      'flow_pair' => $flow
                                     ]);
    return count($rows) > 0;
  }

  public function _savePairLimits($fromCurrency, $fromNetwork, $toCurrency, $toNetwork, $flow, $minAmount, $maxAmount, $updatedAt = null){
    $this->_ensureSchema();
    return parent::execSqlRequest("UPDATE changenow_pairs_krypto SET
                                    min_amount_changenow_pair=:min_amount,
                                    max_amount_changenow_pair=:max_amount,
                                    last_limits_update_changenow_pair=:updated_at
                                   WHERE from_currency_changenow_pair=:from_currency
                                    AND from_network_changenow_pair=:from_network
                                    AND to_currency_changenow_pair=:to_currency
                                    AND to_network_changenow_pair=:to_network
                                    AND flow_changenow_pair=:flow_pair",
                                   [
                                    'min_amount' => (is_null($minAmount) ? '' : $minAmount),
                                    'max_amount' => (is_null($maxAmount) ? '' : $maxAmount),
                                    'updated_at' => (is_null($updatedAt) ? time() : $updatedAt),
                                    'from_currency' => $fromCurrency,
                                    'from_network' => $fromNetwork,
                                    'to_currency' => $toCurrency,
                                    'to_network' => $toNetwork,
                                    'flow_pair' => $flow
                                   ]);
  }

  public function _getQuoteCache($cacheKey, $now = null){
    $this->_ensureSchema();
    $rows = parent::querySqlRequest("SELECT response_changenow_quote_cache FROM changenow_quote_cache_krypto
                                     WHERE cache_key_changenow_quote_cache=:cache_key
                                      AND expires_at_changenow_quote_cache>:expires_at
                                     LIMIT 1",
                                     [
                                      'cache_key' => $cacheKey,
                                      'expires_at' => (is_null($now) ? time() : $now)
                                     ]);
    if(count($rows) == 0) return null;
    $payload = json_decode($rows[0]['response_changenow_quote_cache'], true);
    return (is_array($payload) ? $payload : null);
  }

  public function _saveQuoteCache($cacheKey, $request, $payload, $expiresAt, $createdAt = null){
    $this->_ensureSchema();
    $createdAt = (is_null($createdAt) ? time() : $createdAt);
    return parent::execSqlRequest("INSERT INTO changenow_quote_cache_krypto
                                  (cache_key_changenow_quote_cache, from_currency_changenow_quote_cache, from_network_changenow_quote_cache,
                                   to_currency_changenow_quote_cache, to_network_changenow_quote_cache, flow_changenow_quote_cache,
                                   amount_changenow_quote_cache, request_changenow_quote_cache, response_changenow_quote_cache,
                                   expires_at_changenow_quote_cache, created_at_changenow_quote_cache)
                                  VALUES (:cache_key, :from_currency, :from_network, :to_currency, :to_network, :flow_quote,
                                          :amount_quote, :request_quote, :response_quote, :expires_at, :created_at)
                                  ON DUPLICATE KEY UPDATE
                                    request_changenow_quote_cache=:request_quote_update,
                                    response_changenow_quote_cache=:response_quote_update,
                                    expires_at_changenow_quote_cache=:expires_at_update,
                                    created_at_changenow_quote_cache=:created_at_update",
                                  [
                                    'cache_key' => $cacheKey,
                                    'from_currency' => $request['fromCurrency'],
                                    'from_network' => $request['fromNetwork'],
                                    'to_currency' => $request['toCurrency'],
                                    'to_network' => $request['toNetwork'],
                                    'flow_quote' => $request['flow'],
                                    'amount_quote' => (array_key_exists('fromAmount', $request) ? $request['fromAmount'] : (array_key_exists('toAmount', $request) ? $request['toAmount'] : '')),
                                    'request_quote' => json_encode($request),
                                    'response_quote' => json_encode($payload),
                                    'expires_at' => $expiresAt,
                                    'created_at' => $createdAt,
                                    'request_quote_update' => json_encode($request),
                                    'response_quote_update' => json_encode($payload),
                                    'expires_at_update' => $expiresAt,
                                    'created_at_update' => $createdAt
                                  ]);
  }

  public function _setAssetAdminEnabled($ticker, $network, $enabled){
    $this->_ensureSchema();
    return parent::execSqlRequest("UPDATE changenow_assets_krypto SET admin_enabled_changenow_asset=:enabled
                                  WHERE ticker_changenow_asset=:ticker AND network_changenow_asset=:network",
                                  [
                                    'enabled' => ($enabled ? 1 : 0),
                                    'ticker' => $ticker,
                                    'network' => $network
                                  ]);
  }

  public function _setPairAdminEnabled($fromCurrency, $fromNetwork, $toCurrency, $toNetwork, $flow, $enabled){
    $this->_ensureSchema();
    return parent::execSqlRequest("UPDATE changenow_pairs_krypto SET admin_enabled_changenow_pair=:enabled
                                  WHERE from_currency_changenow_pair=:from_currency
                                   AND from_network_changenow_pair=:from_network
                                   AND to_currency_changenow_pair=:to_currency
                                   AND to_network_changenow_pair=:to_network
                                   AND flow_changenow_pair=:flow_pair",
                                  [
                                    'enabled' => ($enabled ? 1 : 0),
                                    'from_currency' => $fromCurrency,
                                    'from_network' => $fromNetwork,
                                    'to_currency' => $toCurrency,
                                    'to_network' => $toNetwork,
                                    'flow_pair' => $flow
                                  ]);
  }

  private function _upsertAsset($asset, $syncedAt){
    return parent::execSqlRequest("INSERT INTO changenow_assets_krypto
                                  (ticker_changenow_asset, network_changenow_asset, legacy_ticker_changenow_asset, name_changenow_asset,
                                   image_changenow_asset, token_contract_changenow_asset, buy_changenow_asset, sell_changenow_asset,
                                   fiat_changenow_asset, stable_changenow_asset, featured_changenow_asset, fixed_rate_changenow_asset,
                                   provider_active_changenow_asset, admin_enabled_changenow_asset, raw_changenow_asset,
                                   synced_at_changenow_asset, updated_at_changenow_asset)
                                  VALUES (:ticker, :network_asset, :legacy_ticker, :name_asset, :image_asset, :token_contract,
                                          :buy_asset, :sell_asset, :fiat_asset, :stable_asset, :featured_asset, :fixed_rate_asset,
                                          1, 1, :raw_asset, :synced_at, :updated_at)
                                  ON DUPLICATE KEY UPDATE
                                    legacy_ticker_changenow_asset=:legacy_ticker_update,
                                    name_changenow_asset=:name_asset_update,
                                    image_changenow_asset=:image_asset_update,
                                    token_contract_changenow_asset=:token_contract_update,
                                    buy_changenow_asset=:buy_asset_update,
                                    sell_changenow_asset=:sell_asset_update,
                                    fiat_changenow_asset=:fiat_asset_update,
                                    stable_changenow_asset=:stable_asset_update,
                                    featured_changenow_asset=:featured_asset_update,
                                    fixed_rate_changenow_asset=:fixed_rate_asset_update,
                                    provider_active_changenow_asset=1,
                                    raw_changenow_asset=:raw_asset_update,
                                    synced_at_changenow_asset=:synced_at_update,
                                    updated_at_changenow_asset=:updated_at_update",
                                  [
                                    'ticker' => $asset['ticker'],
                                    'network_asset' => $asset['network'],
                                    'legacy_ticker' => $asset['legacyTicker'],
                                    'name_asset' => $asset['name'],
                                    'image_asset' => $asset['image'],
                                    'token_contract' => $asset['tokenContract'],
                                    'buy_asset' => ($asset['buy'] ? 1 : 0),
                                    'sell_asset' => ($asset['sell'] ? 1 : 0),
                                    'fiat_asset' => ($asset['isFiat'] ? 1 : 0),
                                    'stable_asset' => ($asset['isStable'] ? 1 : 0),
                                    'featured_asset' => ($asset['featured'] ? 1 : 0),
                                    'fixed_rate_asset' => ($asset['supportsFixedRate'] ? 1 : 0),
                                    'raw_asset' => json_encode($asset['raw']),
                                    'synced_at' => $syncedAt,
                                    'updated_at' => time(),
                                    'legacy_ticker_update' => $asset['legacyTicker'],
                                    'name_asset_update' => $asset['name'],
                                    'image_asset_update' => $asset['image'],
                                    'token_contract_update' => $asset['tokenContract'],
                                    'buy_asset_update' => ($asset['buy'] ? 1 : 0),
                                    'sell_asset_update' => ($asset['sell'] ? 1 : 0),
                                    'fiat_asset_update' => ($asset['isFiat'] ? 1 : 0),
                                    'stable_asset_update' => ($asset['isStable'] ? 1 : 0),
                                    'featured_asset_update' => ($asset['featured'] ? 1 : 0),
                                    'fixed_rate_asset_update' => ($asset['supportsFixedRate'] ? 1 : 0),
                                    'raw_asset_update' => json_encode($asset['raw']),
                                    'synced_at_update' => $syncedAt,
                                    'updated_at_update' => time()
                                  ]);
  }

  private function _upsertPair($pair, $syncedAt){
    return parent::execSqlRequest("INSERT INTO changenow_pairs_krypto
                                  (from_currency_changenow_pair, from_network_changenow_pair, to_currency_changenow_pair,
                                   to_network_changenow_pair, flow_changenow_pair, provider_active_changenow_pair,
                                   admin_enabled_changenow_pair, min_amount_changenow_pair, max_amount_changenow_pair,
                                   last_limits_update_changenow_pair, raw_changenow_pair, synced_at_changenow_pair, updated_at_changenow_pair)
                                  VALUES (:from_currency, :from_network, :to_currency, :to_network, :flow_pair, 1, 1,
                                          :min_amount, :max_amount, 0, :raw_pair, :synced_at, :updated_at)
                                  ON DUPLICATE KEY UPDATE
                                    provider_active_changenow_pair=1,
                                    raw_changenow_pair=:raw_pair_update,
                                    synced_at_changenow_pair=:synced_at_update,
                                    updated_at_changenow_pair=:updated_at_update",
                                  [
                                    'from_currency' => $pair['fromCurrency'],
                                    'from_network' => $pair['fromNetwork'],
                                    'to_currency' => $pair['toCurrency'],
                                    'to_network' => $pair['toNetwork'],
                                    'flow_pair' => $pair['flow'],
                                    'min_amount' => (is_null($pair['minAmount']) ? '' : $pair['minAmount']),
                                    'max_amount' => (is_null($pair['maxAmount']) ? '' : $pair['maxAmount']),
                                    'raw_pair' => json_encode($pair['raw']),
                                    'synced_at' => $syncedAt,
                                    'updated_at' => time(),
                                    'raw_pair_update' => json_encode($pair['raw']),
                                    'synced_at_update' => $syncedAt,
                                    'updated_at_update' => time()
                                  ]);
  }

  private function _mapAssetRows($rows){
    $assets = [];
    foreach ($rows as $row) {
      $assets[] = [
        'ticker' => $row['ticker_changenow_asset'],
        'network' => $row['network_changenow_asset'],
        'name' => $row['name_changenow_asset'],
        'legacyTicker' => $row['legacy_ticker_changenow_asset'],
        'image' => $row['image_changenow_asset'],
        'tokenContract' => $row['token_contract_changenow_asset'],
        'buy' => $row['buy_changenow_asset'] == 1,
        'sell' => $row['sell_changenow_asset'] == 1,
        'isFiat' => $row['fiat_changenow_asset'] == 1,
        'isStable' => $row['stable_changenow_asset'] == 1,
        'featured' => $row['featured_changenow_asset'] == 1,
        'supportsFixedRate' => $row['fixed_rate_changenow_asset'] == 1,
        'providerActive' => $row['provider_active_changenow_asset'] == 1,
        'adminEnabled' => $row['admin_enabled_changenow_asset'] == 1,
        'syncedAt' => $row['synced_at_changenow_asset']
      ];
    }
    return $assets;
  }

  private function _schemaSql(){
    return [
      "CREATE TABLE IF NOT EXISTS changenow_assets_krypto (
        id_changenow_asset int(11) NOT NULL AUTO_INCREMENT,
        ticker_changenow_asset varchar(32) NOT NULL,
        network_changenow_asset varchar(32) NOT NULL,
        legacy_ticker_changenow_asset varchar(32) DEFAULT NULL,
        name_changenow_asset varchar(120) NOT NULL,
        image_changenow_asset text,
        token_contract_changenow_asset varchar(255) DEFAULT NULL,
        buy_changenow_asset tinyint(1) NOT NULL DEFAULT '0',
        sell_changenow_asset tinyint(1) NOT NULL DEFAULT '0',
        fiat_changenow_asset tinyint(1) NOT NULL DEFAULT '0',
        stable_changenow_asset tinyint(1) NOT NULL DEFAULT '0',
        featured_changenow_asset tinyint(1) NOT NULL DEFAULT '0',
        fixed_rate_changenow_asset tinyint(1) NOT NULL DEFAULT '0',
        provider_active_changenow_asset tinyint(1) NOT NULL DEFAULT '1',
        admin_enabled_changenow_asset tinyint(1) NOT NULL DEFAULT '1',
        raw_changenow_asset longtext,
        synced_at_changenow_asset varchar(15) NOT NULL DEFAULT '0',
        updated_at_changenow_asset varchar(15) NOT NULL DEFAULT '0',
        PRIMARY KEY (id_changenow_asset),
        UNIQUE KEY ticker_network_changenow_asset (ticker_changenow_asset, network_changenow_asset),
        KEY active_changenow_asset (provider_active_changenow_asset, admin_enabled_changenow_asset)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
      "CREATE TABLE IF NOT EXISTS changenow_pairs_krypto (
        id_changenow_pair int(11) NOT NULL AUTO_INCREMENT,
        from_currency_changenow_pair varchar(32) NOT NULL,
        from_network_changenow_pair varchar(32) NOT NULL,
        to_currency_changenow_pair varchar(32) NOT NULL,
        to_network_changenow_pair varchar(32) NOT NULL,
        flow_changenow_pair varchar(20) NOT NULL DEFAULT 'standard',
        provider_active_changenow_pair tinyint(1) NOT NULL DEFAULT '1',
        admin_enabled_changenow_pair tinyint(1) NOT NULL DEFAULT '1',
        min_amount_changenow_pair varchar(40) NOT NULL DEFAULT '',
        max_amount_changenow_pair varchar(40) NOT NULL DEFAULT '',
        last_limits_update_changenow_pair varchar(15) NOT NULL DEFAULT '0',
        raw_changenow_pair longtext,
        synced_at_changenow_pair varchar(15) NOT NULL DEFAULT '0',
        updated_at_changenow_pair varchar(15) NOT NULL DEFAULT '0',
        PRIMARY KEY (id_changenow_pair),
        UNIQUE KEY pair_flow_changenow_pair (from_currency_changenow_pair, from_network_changenow_pair, to_currency_changenow_pair, to_network_changenow_pair, flow_changenow_pair),
        KEY from_asset_changenow_pair (from_currency_changenow_pair, from_network_changenow_pair, flow_changenow_pair),
        KEY to_asset_changenow_pair (to_currency_changenow_pair, to_network_changenow_pair, flow_changenow_pair),
        KEY active_changenow_pair (provider_active_changenow_pair, admin_enabled_changenow_pair)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
      "CREATE TABLE IF NOT EXISTS changenow_quote_cache_krypto (
        id_changenow_quote_cache int(11) NOT NULL AUTO_INCREMENT,
        cache_key_changenow_quote_cache char(64) NOT NULL,
        from_currency_changenow_quote_cache varchar(32) NOT NULL,
        from_network_changenow_quote_cache varchar(32) NOT NULL,
        to_currency_changenow_quote_cache varchar(32) NOT NULL,
        to_network_changenow_quote_cache varchar(32) NOT NULL,
        flow_changenow_quote_cache varchar(20) NOT NULL DEFAULT 'standard',
        amount_changenow_quote_cache varchar(40) NOT NULL DEFAULT '',
        request_changenow_quote_cache longtext NOT NULL,
        response_changenow_quote_cache longtext NOT NULL,
        expires_at_changenow_quote_cache varchar(15) NOT NULL,
        created_at_changenow_quote_cache varchar(15) NOT NULL,
        PRIMARY KEY (id_changenow_quote_cache),
        UNIQUE KEY cache_key_changenow_quote_cache (cache_key_changenow_quote_cache),
        KEY expires_at_changenow_quote_cache (expires_at_changenow_quote_cache),
        KEY pair_changenow_quote_cache (from_currency_changenow_quote_cache, from_network_changenow_quote_cache, to_currency_changenow_quote_cache, to_network_changenow_quote_cache, flow_changenow_quote_cache)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
      "CREATE TABLE IF NOT EXISTS changenow_sync_status_krypto (
        id_changenow_sync int(11) NOT NULL AUTO_INCREMENT,
        sync_key_changenow_sync varchar(64) NOT NULL,
        status_changenow_sync varchar(20) NOT NULL,
        message_changenow_sync text,
        assets_count_changenow_sync int(11) NOT NULL DEFAULT '0',
        pairs_count_changenow_sync int(11) NOT NULL DEFAULT '0',
        started_at_changenow_sync varchar(15) NOT NULL DEFAULT '0',
        finished_at_changenow_sync varchar(15) NOT NULL DEFAULT '0',
        PRIMARY KEY (id_changenow_sync),
        UNIQUE KEY sync_key_changenow_sync (sync_key_changenow_sync),
        KEY status_changenow_sync (status_changenow_sync)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];
  }

}

?>
