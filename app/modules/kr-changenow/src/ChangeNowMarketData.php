<?php

/**
 * ChangeNOW asset, pair, limit, and quote orchestration.
 *
 * This service keeps ChangeNOW market data independent from legacy exchange
 * connectors so swap screens can list and quote provider-supported assets.
 *
 * @package Krypto
 */
class ChangeNowMarketData {

  const SYNC_KEY_MARKET_DATA = 'changenow_market_data';
  const DEFAULT_QUOTE_CACHE_TTL = 30;

  private $Client = null;
  private $Repository = null;
  private $App = null;
  private $Options = [];

  public function __construct($client = null, $repository = null, $App = null, $options = []){
    $this->Client = $client;
    $this->Repository = $repository;
    $this->App = $App;
    $this->Options = (is_array($options) ? $options : []);

    if(is_null($this->Client) && !is_null($this->App) && class_exists('ChangeNowApiClient')){
      $this->Client = ChangeNowApiClient::_fromApp($this->App);
    }

    if(is_null($this->Repository) && class_exists('ChangeNowMarketRepository')){
      $this->Repository = new ChangeNowMarketRepository();
    }

    if(is_null($this->Client)) throw new ChangeNowApiConfigurationException('ChangeNOW API client is required for market data sync.');
    if(is_null($this->Repository)) throw new ChangeNowApiConfigurationException('ChangeNOW market data repository is required.');
  }

  public function _sync($flows = null){
    $startedAt = time();
    $this->Repository->_recordSyncStart(self::SYNC_KEY_MARKET_DATA, $startedAt);

    try {
      $flows = $this->_normalizeFlows($flows);

      $assets = [];
      foreach ($this->Client->_listCurrencies(['active' => true]) as $currency) {
        $asset = self::_normalizeCurrency($currency);
        if(is_null($asset)) continue;
        $assets[] = $asset;
      }

      $pairs = [];
      foreach ($flows as $flow) {
        foreach ($this->Client->_listPairs(['flow' => $flow]) as $pair) {
          $normalizedPair = self::_normalizePair($pair, $flow);
          if(is_null($normalizedPair)) continue;
          $pairs[] = $normalizedPair;
        }
      }

      $syncedAt = time();
      $this->Repository->_replaceAssets($assets, $syncedAt);
      $this->Repository->_replacePairs($pairs, $syncedAt, $flows);
      $this->Repository->_recordSyncFinish(self::SYNC_KEY_MARKET_DATA, 'success', '', count($assets), count($pairs), time());

      return [
        'status' => 'success',
        'assets' => count($assets),
        'pairs' => count($pairs),
        'flows' => $flows,
        'syncedAt' => $syncedAt
      ];
    } catch (Exception $e) {
      $this->Repository->_recordSyncFinish(self::SYNC_KEY_MARKET_DATA, 'failed', $e->getMessage(), 0, 0, time());
      throw $e;
    }
  }

  public function _listSourceAssets($filters = []){
    $filters = (is_array($filters) ? $filters : []);
    if(array_key_exists('flow', $filters)) $filters['flow'] = self::_normalizeFlow($filters['flow']);
    return $this->Repository->_listSourceAssets($filters);
  }

  public function _listDestinationAssets($fromCurrency, $fromNetwork = null, $flow = null){
    $fromCurrency = self::_normalizeCode($fromCurrency);
    $fromNetwork = self::_normalizeCode((is_null($fromNetwork) ? $fromCurrency : $fromNetwork));
    if(is_null($flow)) $flow = $this->_getDefaultFlow();
    $flow = self::_normalizeFlow($flow);
    return $this->Repository->_listDestinationAssets($fromCurrency, $fromNetwork, $flow);
  }

  public function _getQuote($quoteRequest){
    $request = self::_normalizeQuoteRequest($quoteRequest);
    $this->_assertFlowEnabled($request['flow']);
    $this->_assertMarketSelectionEnabled($request);

    $now = time();
    $cacheQuote = ($request['flow'] != 'fixed-rate');
    $cacheKey = null;
    if($cacheQuote){
      $cacheKey = self::_quoteCacheKey($request);
      $cached = $this->Repository->_getQuoteCache($cacheKey, $now);
      if(is_array($cached)){
        $cached['cached'] = true;
        return $cached;
      }
    }

    $range = $this->Client->_getRange($request);
    $quote = $this->Client->_getQuote($request);

    $networkFee = null;
    try {
      $networkFee = $this->Client->_getNetworkFee($request);
    } catch (ChangeNowApiException $e) {
      $networkFee = null;
    }

    $result = self::_normalizeQuoteResult($request, $range, $quote, $networkFee);
    $this->Repository->_savePairLimits(
      $request['fromCurrency'],
      $request['fromNetwork'],
      $request['toCurrency'],
      $request['toNetwork'],
      $request['flow'],
      $result['minAmount'],
      $result['maxAmount'],
      $now
    );
    if($cacheQuote) $this->Repository->_saveQuoteCache($cacheKey, $request, $result, $now + $this->_getQuoteCacheTtl(), $now);

    $result['cached'] = false;
    return $result;
  }

  public function _setAssetEnabled($ticker, $network, $enabled){
    return $this->Repository->_setAssetAdminEnabled(self::_normalizeCode($ticker), self::_normalizeCode($network), $enabled ? true : false);
  }

  public function _setPairEnabled($fromCurrency, $fromNetwork, $toCurrency, $toNetwork, $flow, $enabled){
    return $this->Repository->_setPairAdminEnabled(
      self::_normalizeCode($fromCurrency),
      self::_normalizeCode($fromNetwork),
      self::_normalizeCode($toCurrency),
      self::_normalizeCode($toNetwork),
      self::_normalizeFlow($flow),
      $enabled ? true : false
    );
  }

  public static function _normalizeCurrency($currency){
    if(!is_array($currency)) throw new ChangeNowApiMalformedResponseException('ChangeNOW currency item must be an object.');

    $ticker = self::_normalizeCode(self::_value($currency, ['ticker'], ''));
    if($ticker == '') return null;

    $network = self::_normalizeCode(self::_value($currency, ['network'], $ticker));
    if($network == '') $network = $ticker;

    $name = trim((string) self::_value($currency, ['name'], strtoupper($ticker)));
    if($name == '') $name = strtoupper($ticker);

    return [
      'ticker' => $ticker,
      'network' => $network,
      'name' => $name,
      'legacyTicker' => self::_normalizeCode(self::_value($currency, ['legacyTicker', 'legacy_ticker'], '')),
      'image' => trim((string) self::_value($currency, ['image', 'icon', 'iconUrl'], '')),
      'isFiat' => self::_boolValue(self::_value($currency, ['isFiat', 'is_fiat'], false)),
      'featured' => self::_boolValue(self::_value($currency, ['featured'], false)),
      'isStable' => self::_boolValue(self::_value($currency, ['isStable', 'is_stable'], false)),
      'supportsFixedRate' => self::_boolValue(self::_value($currency, ['supportsFixedRate', 'supports_fixed_rate'], false)),
      'tokenContract' => trim((string) self::_value($currency, ['tokenContract', 'token_contract'], '')),
      'buy' => self::_boolValue(self::_value($currency, ['buy'], true)),
      'sell' => self::_boolValue(self::_value($currency, ['sell'], true)),
      'providerActive' => true,
      'adminEnabled' => true,
      'raw' => $currency
    ];
  }

  public static function _normalizePair($pair, $defaultFlow = 'standard'){
    if(!is_array($pair)) throw new ChangeNowApiMalformedResponseException('ChangeNOW pair item must be an object.');

    $fromCurrency = self::_normalizeCode(self::_value($pair, ['fromCurrency', 'from_currency'], ''));
    $toCurrency = self::_normalizeCode(self::_value($pair, ['toCurrency', 'to_currency'], ''));
    if($fromCurrency == '' || $toCurrency == '') return null;

    $fromNetwork = self::_normalizeCode(self::_value($pair, ['fromNetwork', 'from_network'], $fromCurrency));
    $toNetwork = self::_normalizeCode(self::_value($pair, ['toNetwork', 'to_network'], $toCurrency));
    if($fromNetwork == '') $fromNetwork = $fromCurrency;
    if($toNetwork == '') $toNetwork = $toCurrency;

    return [
      'fromCurrency' => $fromCurrency,
      'fromNetwork' => $fromNetwork,
      'toCurrency' => $toCurrency,
      'toNetwork' => $toNetwork,
      'flow' => self::_normalizeFlow(self::_value($pair, ['flow'], $defaultFlow)),
      'providerActive' => true,
      'adminEnabled' => true,
      'minAmount' => self::_amountValue(self::_value($pair, ['minAmount', 'min_amount'], null)),
      'maxAmount' => self::_amountValue(self::_value($pair, ['maxAmount', 'max_amount'], null)),
      'raw' => $pair
    ];
  }

  public static function _normalizeQuoteRequest($request){
    if(!is_array($request)) throw new ChangeNowApiValidationException('The ChangeNOW quote request is incomplete.', 'Quote request must be an array.');

    $fromCurrency = self::_normalizeCode(self::_value($request, ['fromCurrency', 'from_currency'], ''));
    $toCurrency = self::_normalizeCode(self::_value($request, ['toCurrency', 'to_currency'], ''));
    if($fromCurrency == '' || $toCurrency == ''){
      throw new ChangeNowApiValidationException('The ChangeNOW quote request is incomplete.', 'fromCurrency and toCurrency are required.');
    }

    $fromNetwork = self::_normalizeCode(self::_value($request, ['fromNetwork', 'from_network'], $fromCurrency));
    $toNetwork = self::_normalizeCode(self::_value($request, ['toNetwork', 'to_network'], $toCurrency));
    $fromAmount = self::_amountValue(self::_value($request, ['fromAmount', 'from_amount'], null));
    $toAmount = self::_amountValue(self::_value($request, ['toAmount', 'to_amount'], null));

    if($fromAmount === null && $toAmount === null){
      throw new ChangeNowApiValidationException('The ChangeNOW quote request is incomplete.', 'fromAmount or toAmount is required.');
    }

    $normalized = [
      'fromCurrency' => $fromCurrency,
      'fromNetwork' => ($fromNetwork == '' ? $fromCurrency : $fromNetwork),
      'toCurrency' => $toCurrency,
      'toNetwork' => ($toNetwork == '' ? $toCurrency : $toNetwork),
      'flow' => self::_normalizeFlow(self::_value($request, ['flow'], 'standard')),
    ];

    if(!is_null($fromAmount)) $normalized['fromAmount'] = $fromAmount;
    if(!is_null($toAmount)) $normalized['toAmount'] = $toAmount;

    foreach (['type', 'useRateId', 'userId'] as $optionalKey) {
      $value = self::_value($request, [$optionalKey, self::_camelToSnake($optionalKey)], null);
      if($value === null || $value === '') continue;
      if(is_bool($value)) $value = ($value ? 'true' : 'false');
      $normalized[$optionalKey] = (string) $value;
    }

    return $normalized;
  }

  public static function _quoteCacheKey($request){
    $normalized = self::_normalizeQuoteRequest($request);
    ksort($normalized);
    return hash('sha256', json_encode($normalized));
  }

  public static function _normalizeQuoteResult($request, $range, $quote, $networkFee = null){
    $range = (is_array($range) ? $range : []);
    $quote = (is_array($quote) ? $quote : []);
    $networkFee = (is_array($networkFee) ? $networkFee : []);

    $fromAmount = self::_amountValue(self::_value($quote, ['fromAmount', 'from_amount'], self::_value($request, ['fromAmount'], null)));
    $toAmount = self::_amountValue(self::_value($quote, ['toAmount', 'to_amount'], self::_value($request, ['toAmount'], null)));

    return [
      'fromCurrency' => self::_normalizeCode(self::_value($quote, ['fromCurrency'], $request['fromCurrency'])),
      'fromNetwork' => self::_normalizeCode(self::_value($quote, ['fromNetwork'], $request['fromNetwork'])),
      'toCurrency' => self::_normalizeCode(self::_value($quote, ['toCurrency'], $request['toCurrency'])),
      'toNetwork' => self::_normalizeCode(self::_value($quote, ['toNetwork'], $request['toNetwork'])),
      'flow' => self::_normalizeFlow(self::_value($quote, ['flow'], $request['flow'])),
      'type' => self::_value($quote, ['type'], null),
      'amount' => (!is_null($fromAmount) ? $fromAmount : self::_amountValue(self::_value($request, ['toAmount'], null))),
      'fromAmount' => $fromAmount,
      'toAmount' => $toAmount,
      'estimatedReceiveAmount' => $toAmount,
      'minAmount' => self::_amountValue(self::_value($range, ['minAmount', 'min_amount'], null)),
      'maxAmount' => self::_amountValue(self::_value($range, ['maxAmount', 'max_amount'], null)),
      'networkFee' => self::_amountValue(self::_value($networkFee, ['estimatedFee', 'networkFee', 'network_fee'], null)),
      'depositFee' => self::_amountValue(self::_value($quote, ['depositFee', 'deposit_fee'], null)),
      'withdrawalFee' => self::_amountValue(self::_value($quote, ['withdrawalFee', 'withdrawal_fee'], null)),
      'rateId' => self::_value($quote, ['rateId', 'rate_id'], null),
      'validUntil' => self::_value($quote, ['validUntil', 'valid_until'], null),
      'transactionSpeedForecast' => self::_value($quote, ['transactionSpeedForecast', 'transaction_speed_forecast'], null),
      'warningMessage' => self::_value($quote, ['warningMessage', 'warning_message'], null),
      'cached' => false
    ];
  }

  private function _assertMarketSelectionEnabled($request){
    if(!$this->Repository->_isAssetEnabled($request['fromCurrency'], $request['fromNetwork'])){
      throw new ChangeNowApiValidationException('The selected source asset is not available.', 'Source asset is disabled or missing in ChangeNOW cache.');
    }

    if(!$this->Repository->_isAssetEnabled($request['toCurrency'], $request['toNetwork'])){
      throw new ChangeNowApiValidationException('The selected destination asset is not available.', 'Destination asset is disabled or missing in ChangeNOW cache.');
    }

    if(!$this->Repository->_isPairEnabled($request['fromCurrency'], $request['fromNetwork'], $request['toCurrency'], $request['toNetwork'], $request['flow'])){
      throw new ChangeNowApiValidationException('The selected ChangeNOW pair is not available.', 'Pair is disabled or missing in ChangeNOW cache.');
    }
  }

  private function _assertFlowEnabled($flow){
    if(!in_array(self::_normalizeFlow($flow), $this->_getEnabledFlows(), true)){
      throw new ChangeNowApiValidationException('The selected ChangeNOW flow is disabled.', 'Flow is disabled by local settings.');
    }
  }

  private function _getEnabledFlows(){
    if(array_key_exists('enabled_flows', $this->Options)) return $this->_normalizeFlows($this->Options['enabled_flows']);
    if(!is_null($this->App) && method_exists($this->App, '_getChangeNowEnabledFlows')) return $this->_normalizeFlows($this->App->_getChangeNowEnabledFlows());
    if(class_exists('ChangeNowSettings')) return $this->_normalizeFlows(ChangeNowSettings::_enabledFlowsToArray(ChangeNowSettings::_defaults()['changenow_enabled_flows']));
    return ['standard', 'fixed-rate'];
  }

  private function _getDefaultFlow(){
    $flows = $this->_getEnabledFlows();
    if(!is_null($this->App) && method_exists($this->App, '_getChangeNowDefaultFlow')){
      $flow = self::_normalizeFlow($this->App->_getChangeNowDefaultFlow());
      if(in_array($flow, $flows, true)) return $flow;
    }
    return $flows[0];
  }

  private function _getQuoteCacheTtl(){
    if(array_key_exists('quote_cache_ttl', $this->Options)){
      $ttl = intval($this->Options['quote_cache_ttl']);
      return ($ttl > 0 ? $ttl : self::DEFAULT_QUOTE_CACHE_TTL);
    }

    if(!is_null($this->App) && method_exists($this->App, '_getChangeNowQuoteCacheTtl')) return $this->App->_getChangeNowQuoteCacheTtl();
    return self::DEFAULT_QUOTE_CACHE_TTL;
  }

  private function _normalizeFlows($flows = null){
    if(is_null($flows)) $flows = $this->_getEnabledFlows();
    if(!is_array($flows)) $flows = explode(',', (string) $flows);

    $result = [];
    foreach ($flows as $flow) {
      $flow = self::_normalizeFlow($flow);
      if(!in_array($flow, $result, true)) $result[] = $flow;
    }

    if(count($result) == 0) $result[] = 'standard';
    return $result;
  }

  private static function _normalizeFlow($flow){
    $flow = strtolower(trim((string) $flow));
    if($flow == 'fixed') $flow = 'fixed-rate';
    if(!in_array($flow, ['standard', 'fixed-rate'], true)) return 'standard';
    return $flow;
  }

  private static function _normalizeCode($value){
    $value = strtolower(trim((string) $value));
    $value = preg_replace('/[^a-z0-9_-]/', '', $value);
    return $value;
  }

  private static function _amountValue($value){
    if($value === null || $value === '') return null;
    if(is_float($value) || is_int($value)) return (string) $value;
    return trim((string) $value);
  }

  private static function _boolValue($value){
    if(is_bool($value)) return $value;
    if(is_int($value)) return $value == 1;
    $value = strtolower(trim((string) $value));
    return in_array($value, ['1', 'true', 'yes', 'on'], true);
  }

  private static function _value($source, $keys, $default = null){
    if(!is_array($source)) return $default;
    foreach ($keys as $key) {
      if(array_key_exists($key, $source)) return $source[$key];
    }
    return $default;
  }

  private static function _camelToSnake($value){
    return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $value));
  }

}

?>
