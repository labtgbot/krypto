<?php

require_once __DIR__.'/ChangeNowApiException.php';

/**
 * Server-side ChangeNOW v2 API client.
 *
 * @package Krypto
 */
class ChangeNowApiClient {

  const DEFAULT_BASE_URL = 'https://api.changenow.io';

  private $BaseUrl = self::DEFAULT_BASE_URL;
  private $PublicApiKey = '';
  private $PrivateApiKey = '';
  private $Timeout = 10;
  private $ConnectTimeout = 5;
  private $RetryCount = 2;
  private $RetryDelayMs = 250;
  private $Debug = false;
  private $DebugLogger = null;
  private $UserIp = null;
  private $Transport = null;

  public function __construct($config = [], $transport = null){
    $this->BaseUrl = rtrim($this->_configValue($config, ['base_url', 'baseUrl'], self::DEFAULT_BASE_URL), '/');
    $this->PublicApiKey = (string) $this->_configValue($config, ['public_api_key', 'api_key', 'apiKey', 'changenow_public_api_key'], '');
    $this->PrivateApiKey = (string) $this->_configValue($config, ['private_api_key', 'privateApiKey', 'changenow_private_api_key'], '');
    $this->Timeout = $this->_positiveInteger($this->_configValue($config, ['timeout'], 10), 10);
    $this->ConnectTimeout = $this->_positiveInteger($this->_configValue($config, ['connect_timeout', 'connectTimeout'], 5), 5);
    $this->RetryCount = max(0, intval($this->_configValue($config, ['retry_count', 'retryCount'], 2)));
    $this->RetryDelayMs = max(0, intval($this->_configValue($config, ['retry_delay_ms', 'retryDelayMs'], 250)));
    $this->Debug = $this->_toBoolean($this->_configValue($config, ['debug'], false));
    $this->DebugLogger = $this->_configValue($config, ['debug_logger', 'debugLogger'], null);
    $this->UserIp = $this->_configValue($config, ['user_ip', 'userIp'], null);
    $this->Transport = $transport;
  }

  public static function _fromApp($App, $config = [], $transport = null){
    $appConfig = [
      'public_api_key' => $App->_getChangeNowPublicApiKey(),
      'private_api_key' => $App->_getChangeNowPrivateApiKey(),
      'retry_count' => 2,
      'timeout' => 10,
      'connect_timeout' => 5
    ];

    if(method_exists($App, '_getChangeNowRateLimitPerSecond')) $appConfig['rate_limit_per_second'] = $App->_getChangeNowRateLimitPerSecond();
    if(method_exists($App, '_getChangeNowRateLimitPerMinute')) $appConfig['rate_limit_per_minute'] = $App->_getChangeNowRateLimitPerMinute();

    return new self(array_merge($appConfig, $config), $transport);
  }

  public function _listCurrencies($filters = []){
    $params = $this->_filterQuery($filters, ['active', 'flow', 'buy', 'sell']);
    $data = $this->_request('GET', '/v2/exchange/currencies', $params, null, ['apiKey' => 'optional']);
    return $this->_normalizeCurrencies($data);
  }

  public function _listPairs($filters = []){
    $params = $this->_filterQuery($filters, ['fromCurrency', 'toCurrency', 'fromNetwork', 'toNetwork', 'flow']);
    $apiKey = $this->_requirePublicApiKey();
    $data = $this->_request('GET', '/v2/exchange/available-pairs', $params, null, [
      'headers' => ['x-api-key' => $apiKey]
    ]);
    return $this->_normalizePairs($data);
  }

  public function _getMinAmount($request){
    $params = $this->_pairParams($request);
    $data = $this->_request('GET', '/v2/exchange/min-amount', $params);
    return $this->_normalizeLimit($data);
  }

  public function _getRange($request){
    $params = $this->_pairParams($request);
    $data = $this->_request('GET', '/v2/exchange/range', $params);
    return $this->_normalizeRange($data);
  }

  public function _getEstimatedAmount($request){
    $params = $this->_pairParams($request);
    $fromAmount = $this->_param($request, ['fromAmount', 'from_amount'], null);
    $toAmount = $this->_param($request, ['toAmount', 'to_amount'], null);

    if($this->_isBlank($fromAmount) && $this->_isBlank($toAmount)){
      throw new ChangeNowApiValidationException('The ChangeNOW request is incomplete.', 'ChangeNOW estimated amount request requires fromAmount or toAmount.');
    }

    if(!$this->_isBlank($fromAmount)) $params['fromAmount'] = $fromAmount;
    if(!$this->_isBlank($toAmount)) $params['toAmount'] = $toAmount;
    $this->_copyOptionalParams($request, $params, ['type', 'useRateId', 'userId']);

    $data = $this->_request('GET', '/v2/exchange/estimated-amount', $params);
    return $this->_normalizeQuote($data);
  }

  public function _getQuote($quoteRequest){
    return $this->_getEstimatedAmount($quoteRequest);
  }

  public function _getNetworkFee($request){
    $params = $this->_pairParams($request);
    $this->_copyOptionalParams($request, $params, ['fromAmount', 'toAmount', 'convertedCurrency', 'convertedNetwork']);
    $data = $this->_request('GET', '/v2/exchange/network-fee', $params);
    return $this->_normalizeNetworkFee($data);
  }

  public function _createTransaction($request){
    $body = $this->_transactionBody($request);
    $headers = [];
    $userIp = $this->_param($request, ['userIp', 'user_ip'], $this->UserIp);
    if(!$this->_isBlank($userIp)) $headers['x-forwarded-for'] = $userIp;

    $data = $this->_request('POST', '/v2/exchange', [], $body, [
      'headers' => $headers,
      'retry' => false
    ]);
    return $this->_normalizeTransaction($data);
  }

  public function _createExchangeTransaction($request){
    return $this->_createTransaction($request);
  }

  public function _createSwap($swapRequest){
    return $this->_createTransaction($swapRequest);
  }

  public function _getTransactionStatus($transactionId){
    if($this->_isBlank($transactionId)) throw new ChangeNowApiValidationException('The ChangeNOW request is incomplete.', 'Transaction id is required for status lookup.');
    $data = $this->_request('GET', '/v2/exchange/by-id', ['id' => $transactionId]);
    return $this->_normalizeStatus($data);
  }

  public function _getSwapStatus($transactionId){
    return $this->_getTransactionStatus($transactionId);
  }

  public function _validateAddress($currency, $address, $network = null){
    if($this->_isBlank($currency) || $this->_isBlank($address)){
      throw new ChangeNowApiValidationException('The ChangeNOW request is incomplete.', 'Currency and address are required for address validation.');
    }

    $params = ['currency' => $currency, 'address' => $address];
    if(!$this->_isBlank($network)) $params['network'] = $network;
    $data = $this->_request('GET', '/v2/validate/address', $params, null, ['apiKey' => false]);
    return $this->_normalizeAddressValidation($data);
  }

  public function _getAvailableActions($transactionId){
    if($this->_isBlank($transactionId)) throw new ChangeNowApiValidationException('The ChangeNOW request is incomplete.', 'Transaction id is required for available actions lookup.');
    return $this->_request('GET', '/v2/exchange/actions', ['id' => $transactionId]);
  }

  public function _continueTransaction($transactionId){
    if($this->_isBlank($transactionId)) throw new ChangeNowApiValidationException('The ChangeNOW request is incomplete.', 'Transaction id is required to continue an exchange.');
    return $this->_request('POST', '/v2/exchange/continue', [], ['id' => $transactionId], ['retry' => false]);
  }

  public function _refundTransaction($transactionId, $address, $extraId = null){
    if($this->_isBlank($transactionId) || $this->_isBlank($address)){
      throw new ChangeNowApiValidationException('The ChangeNOW request is incomplete.', 'Transaction id and refund address are required for refund.');
    }

    $body = ['id' => $transactionId, 'address' => $address];
    if(!$this->_isBlank($extraId)) $body['extraId'] = $extraId;
    return $this->_request('POST', '/v2/exchange/refund', [], $body, ['retry' => false]);
  }

  public function _listTransactions($filters = []){
    $params = $this->_filterQuery($filters, ['limit', 'offset', 'sortDirection', 'sortField', 'dateField', 'dateFrom', 'dateTo', 'statuses']);
    if(array_key_exists('statuses', $params) && is_array($params['statuses'])) $params['statuses'] = join(',', $params['statuses']);

    $data = $this->_request('GET', '/v2/exchanges', $params, null, ['apiKey' => 'private']);
    return $this->_normalizeTransactionList($data);
  }

  private function _request($method, $path, $query = [], $body = null, $options = []){
    $url = $this->_buildUrl($path, $query);
    $headers = $this->_buildHeaders($body, $options);
    $retry = (array_key_exists('retry', $options) ? $options['retry'] : strtoupper($method) == 'GET');
    $maxAttempts = ($retry ? $this->RetryCount + 1 : 1);
    $attempt = 0;

    while($attempt < $maxAttempts){
      $attempt++;
      $encodedBody = (is_null($body) ? null : json_encode($body));

      $this->_debugLog('request', [
        'attempt' => $attempt,
        'method' => $method,
        'url' => $url,
        'headers' => $headers,
        'body' => $body
      ]);

      try {
        $response = $this->_send($method, $url, $headers, $encodedBody);
      } catch (ChangeNowApiException $e) {
        throw $e;
      } catch (Exception $e) {
        if($retry && $attempt < $maxAttempts){
          $this->_sleepBeforeRetry($attempt);
          continue;
        }
        throw new ChangeNowApiNetworkException('ChangeNOW transport failure: '.$e->getMessage(), ['method' => $method, 'url' => $url], $e);
      }

      $status = intval($response['status']);
      $responseHeaders = (array_key_exists('headers', $response) && is_array($response['headers']) ? $response['headers'] : []);
      $responseBody = (array_key_exists('body', $response) ? $response['body'] : '');

      $this->_debugLog('response', [
        'attempt' => $attempt,
        'method' => $method,
        'url' => $url,
        'status' => $status,
        'headers' => $responseHeaders,
        'body' => $this->_decodeForDebug($responseBody)
      ]);

      if($status >= 200 && $status < 300){
        return $this->_decodeJsonResponse($responseBody, $status, $method, $url);
      }

      if($retry && $attempt < $maxAttempts && $this->_isRetryableStatus($status)){
        $this->_sleepBeforeRetry($attempt);
        continue;
      }

      $this->_throwHttpException($status, $responseBody, $responseHeaders, $method, $url);
    }

    throw new ChangeNowApiNetworkException('ChangeNOW request failed without a response.', ['method' => $method, 'url' => $url]);
  }

  private function _send($method, $url, $headers, $body){
    if(is_callable($this->Transport)){
      return call_user_func($this->Transport, strtoupper($method), $url, $headers, $body, $this->Timeout, $this->ConnectTimeout);
    }

    return $this->_curlTransport(strtoupper($method), $url, $headers, $body);
  }

  private function _curlTransport($method, $url, $headers, $body){
    if(!function_exists('curl_init')) throw new ChangeNowApiConfigurationException('PHP cURL extension is required for ChangeNOW API calls.');

    $ch = curl_init($url);
    $headerLines = [];
    foreach ($headers as $key => $value) {
      if($this->_isBlank($value)) continue;
      $headerLines[] = $key.': '.$value;
    }

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->ConnectTimeout);
    curl_setopt($ch, CURLOPT_TIMEOUT, $this->Timeout);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headerLines);
    curl_setopt($ch, CURLOPT_ENCODING, '');

    if(!is_null($body)){
      curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    $rawResponse = curl_exec($ch);
    if($rawResponse === false){
      $error = curl_error($ch);
      curl_close($ch);
      throw new Exception($error);
    }

    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    $rawHeaders = substr($rawResponse, 0, $headerSize);
    $responseBody = substr($rawResponse, $headerSize);

    return [
      'status' => $status,
      'headers' => $this->_parseResponseHeaders($rawHeaders),
      'body' => $responseBody
    ];
  }

  private function _buildUrl($path, $query){
    $query = $this->_cleanQuery($query);
    $url = $this->BaseUrl.$path;
    if(count($query) > 0) $url .= '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    return $url;
  }

  private function _buildHeaders($body, $options){
    $headers = ['Accept' => 'application/json'];
    if(!is_null($body)) $headers['Content-Type'] = 'application/json';

    $apiKeyMode = (array_key_exists('apiKey', $options) ? $options['apiKey'] : 'public');
    if($apiKeyMode === 'private'){
      $headers['x-changenow-api-key'] = $this->_requirePrivateApiKey();
    } elseif($apiKeyMode === 'public'){
      $headers['x-changenow-api-key'] = $this->_requirePublicApiKey();
    } elseif($apiKeyMode === 'optional' && strlen($this->PublicApiKey) > 0){
      $headers['x-changenow-api-key'] = $this->PublicApiKey;
    }

    if(array_key_exists('headers', $options) && is_array($options['headers'])){
      foreach ($options['headers'] as $key => $value) {
        if(!$this->_isBlank($value)) $headers[$key] = $value;
      }
    }

    return $headers;
  }

  private function _decodeJsonResponse($body, $status, $method, $url){
    if(trim((string) $body) == '') return [];

    $data = json_decode($body, true);
    if(json_last_error() !== JSON_ERROR_NONE){
      throw new ChangeNowApiMalformedResponseException('ChangeNOW returned malformed JSON: '.json_last_error_msg(), $status, [
        'method' => $method,
        'url' => $url,
        'bodyPreview' => substr((string) $body, 0, 500)
      ]);
    }

    return $data;
  }

  private function _decodeForDebug($body){
    if(trim((string) $body) == '') return '';
    $data = json_decode($body, true);
    return (json_last_error() === JSON_ERROR_NONE ? $data : substr((string) $body, 0, 500));
  }

  private function _throwHttpException($status, $body, $headers, $method, $url){
    $decoded = json_decode((string) $body, true);
    $message = '';
    if(json_last_error() === JSON_ERROR_NONE && is_array($decoded)){
      if(array_key_exists('message', $decoded)) $message = $decoded['message'];
      elseif(array_key_exists('error', $decoded)) $message = $decoded['error'];
      elseif(array_key_exists('code', $decoded)) $message = $decoded['code'];
    } else {
      $message = trim(substr((string) $body, 0, 300));
    }
    if($message == '') $message = 'HTTP '.$status;

    $debugContext = $this->_redact([
      'method' => $method,
      'url' => $url,
      'status' => $status,
      'headers' => $headers,
      'body' => (is_array($decoded) ? $decoded : $message)
    ]);

    if($status == 429){
      $debugContext['retryAfter'] = $this->_headerValue($headers, 'Retry-After');
      throw new ChangeNowApiRateLimitException('ChangeNOW rate limit response: '.$message, $status, $debugContext);
    }

    if($status == 400){
      throw new ChangeNowApiValidationException('ChangeNOW rejected the request. Please check the swap parameters.', 'ChangeNOW validation response: '.$message, $status, $debugContext);
    }

    if($status == 401 || $status == 403){
      throw new ChangeNowApiAuthException('ChangeNOW authentication response: '.$message, $status, $debugContext);
    }

    if($status == 404){
      throw new ChangeNowApiNotFoundException('ChangeNOW not-found response: '.$message, $status, $debugContext);
    }

    if($status >= 500){
      throw new ChangeNowApiServerException('ChangeNOW server response: '.$message, $status, $debugContext);
    }

    throw new ChangeNowApiException('api_error', 'ChangeNOW request failed.', 'ChangeNOW HTTP '.$status.' response: '.$message, $status, $debugContext);
  }

  private function _pairParams($request){
    $fromCurrency = $this->_param($request, ['fromCurrency', 'from_currency'], null);
    $toCurrency = $this->_param($request, ['toCurrency', 'to_currency'], null);

    if($this->_isBlank($fromCurrency) || $this->_isBlank($toCurrency)){
      throw new ChangeNowApiValidationException('The ChangeNOW request is incomplete.', 'fromCurrency and toCurrency are required.');
    }

    $params = [
      'fromCurrency' => $fromCurrency,
      'toCurrency' => $toCurrency,
      'flow' => $this->_param($request, ['flow'], 'standard')
    ];

    $this->_copyOptionalParams($request, $params, ['fromNetwork', 'toNetwork']);
    return $params;
  }

  private function _transactionBody($request){
    $body = $this->_pairParams($request);
    $address = $this->_param($request, ['address', 'payoutAddress', 'payout_address'], null);
    $fromAmount = $this->_param($request, ['fromAmount', 'from_amount'], null);
    $toAmount = $this->_param($request, ['toAmount', 'to_amount'], null);

    if($this->_isBlank($address)){
      throw new ChangeNowApiValidationException('The ChangeNOW request is incomplete.', 'Payout address is required to create a ChangeNOW transaction.');
    }
    if($this->_isBlank($fromAmount) && $this->_isBlank($toAmount)){
      throw new ChangeNowApiValidationException('The ChangeNOW request is incomplete.', 'fromAmount or toAmount is required to create a ChangeNOW transaction.');
    }

    $body['address'] = $address;
    if(!$this->_isBlank($fromAmount)) $body['fromAmount'] = $fromAmount;
    if(!$this->_isBlank($toAmount)) $body['toAmount'] = $toAmount;

    $this->_copyOptionalParams($request, $body, [
      'extraId',
      'refundAddress',
      'refundExtraId',
      'type',
      'rateId',
      'userId',
      'payload',
      'contactEmail'
    ]);

    return $body;
  }

  private function _normalizeCurrencies($data){
    if(!is_array($data) || !$this->_isList($data)) throw new ChangeNowApiMalformedResponseException('ChangeNOW currencies response must be a list.');
    $result = [];
    foreach ($data as $currency) {
      if(!is_array($currency)) throw new ChangeNowApiMalformedResponseException('ChangeNOW currency item must be an object.');
      $result[] = $this->_pick($currency, [
        'ticker',
        'name',
        'network',
        'legacyTicker',
        'image',
        'isFiat',
        'featured',
        'isStable',
        'supportsFixedRate',
        'tokenContract',
        'buy',
        'sell'
      ]);
    }
    return $result;
  }

  private function _normalizePairs($data){
    if(!is_array($data) || !$this->_isList($data)) throw new ChangeNowApiMalformedResponseException('ChangeNOW pairs response must be a list.');
    $result = [];
    foreach ($data as $pair) {
      if(!is_array($pair)) throw new ChangeNowApiMalformedResponseException('ChangeNOW pair item must be an object.');
      $result[] = $this->_pick($pair, ['fromCurrency', 'fromNetwork', 'toCurrency', 'toNetwork', 'flow']);
    }
    return $result;
  }

  private function _normalizeLimit($data){
    return $this->_pickObject($data, ['fromCurrency', 'fromNetwork', 'toCurrency', 'toNetwork', 'flow', 'minAmount']);
  }

  private function _normalizeRange($data){
    return $this->_pickObject($data, ['fromCurrency', 'fromNetwork', 'toCurrency', 'toNetwork', 'flow', 'minAmount', 'maxAmount']);
  }

  private function _normalizeQuote($data){
    return $this->_pickObject($data, [
      'fromCurrency',
      'fromNetwork',
      'toCurrency',
      'toNetwork',
      'flow',
      'type',
      'rateId',
      'validUntil',
      'transactionSpeedForecast',
      'warningMessage',
      'depositFee',
      'withdrawalFee',
      'userId',
      'fromAmount',
      'toAmount'
    ]);
  }

  private function _normalizeNetworkFee($data){
    return $this->_pickObject($data, ['estimatedFee']);
  }

  private function _normalizeTransaction($data){
    return $this->_pickObject($data, [
      'id',
      'fromAmount',
      'toAmount',
      'flow',
      'type',
      'payinAddress',
      'payoutAddress',
      'fromCurrency',
      'fromNetwork',
      'toCurrency',
      'toNetwork',
      'refundAddress',
      'payinExtraId',
      'payoutExtraId',
      'refundExtraId',
      'validUntil'
    ]);
  }

  private function _normalizeStatus($data){
    return $this->_pickObject($data, [
      'id',
      'status',
      'actionsAvailable',
      'fromCurrency',
      'fromNetwork',
      'toCurrency',
      'toNetwork',
      'expectedAmountFrom',
      'expectedAmountTo',
      'amountFrom',
      'amountTo',
      'payinAddress',
      'payoutAddress',
      'payinExtraId',
      'payoutExtraId',
      'refundAddress',
      'refundExtraId',
      'createdAt',
      'updatedAt',
      'validUntil',
      'depositReceivedAt'
    ]);
  }

  private function _normalizeAddressValidation($data){
    return $this->_pickObject($data, ['result', 'message', 'isActivated']);
  }

  private function _normalizeTransactionList($data){
    $data = $this->_requireObject($data, 'ChangeNOW transaction list response must be an object.');
    $exchanges = [];
    $rawExchanges = (array_key_exists('exchanges', $data) && is_array($data['exchanges']) ? $data['exchanges'] : []);
    foreach ($rawExchanges as $exchange) {
      if(!is_array($exchange)) continue;
      $normalized = $this->_pick($exchange, ['status', 'flow', 'validUntil', 'createdAt', 'updatedAt']);
      $normalized['id'] = (array_key_exists('exchangeId', $exchange) ? $exchange['exchangeId'] : (array_key_exists('id', $exchange) ? $exchange['id'] : null));
      $exchanges[] = $normalized;
    }

    return [
      'count' => (array_key_exists('count', $data) ? $data['count'] : count($exchanges)),
      'exchanges' => $exchanges
    ];
  }

  private function _pickObject($data, $fields){
    $data = $this->_requireObject($data, 'ChangeNOW response must be an object.');
    return $this->_pick($data, $fields);
  }

  private function _pick($data, $fields){
    $result = [];
    foreach ($fields as $field) {
      $result[$field] = (array_key_exists($field, $data) ? $data[$field] : null);
    }
    return $result;
  }

  private function _requireObject($data, $message){
    if(!is_array($data) || $this->_isList($data)) throw new ChangeNowApiMalformedResponseException($message);
    return $data;
  }

  private function _copyOptionalParams($source, &$target, $keys){
    foreach ($keys as $key) {
      $value = $this->_param($source, [$key, $this->_camelToSnake($key)], null);
      if(!$this->_isBlank($value)) $target[$key] = $value;
    }
  }

  private function _filterQuery($source, $keys){
    $result = [];
    foreach ($keys as $key) {
      $value = $this->_param($source, [$key, $this->_camelToSnake($key)], null);
      if(!$this->_isBlank($value)) $result[$key] = $value;
    }
    return $result;
  }

  private function _cleanQuery($query){
    $result = [];
    foreach ($query as $key => $value) {
      if($this->_isBlank($value)) continue;
      if(is_bool($value)) $value = ($value ? 'true' : 'false');
      if(is_array($value)) $value = join(',', $value);
      $result[$key] = $value;
    }
    return $result;
  }

  private function _param($source, $keys, $default = null){
    if(!is_array($source)) return $default;
    foreach ($keys as $key) {
      if(array_key_exists($key, $source)) return $source[$key];
    }
    return $default;
  }

  private function _configValue($config, $keys, $default = null){
    foreach ($keys as $key) {
      if(is_array($config) && array_key_exists($key, $config)) return $config[$key];
    }
    return $default;
  }

  private function _requirePublicApiKey(){
    if(strlen($this->PublicApiKey) == 0) throw new ChangeNowApiConfigurationException('ChangeNOW public API key is required for this endpoint.');
    return $this->PublicApiKey;
  }

  private function _requirePrivateApiKey(){
    if(strlen($this->PrivateApiKey) == 0) throw new ChangeNowApiConfigurationException('ChangeNOW private API key is required for transaction list endpoint.');
    return $this->PrivateApiKey;
  }

  private function _parseResponseHeaders($rawHeaders){
    $headers = [];
    $blocks = preg_split("/\r\n\r\n|\n\n|\r\r/", trim((string) $rawHeaders));
    $lastBlock = '';
    foreach ($blocks as $block) {
      if(trim($block) != '') $lastBlock = $block;
    }

    foreach (preg_split("/\r\n|\n|\r/", $lastBlock) as $line) {
      if(strpos($line, ':') === false) continue;
      list($key, $value) = explode(':', $line, 2);
      $headers[trim($key)] = trim($value);
    }

    return $headers;
  }

  private function _headerValue($headers, $name){
    foreach ($headers as $key => $value) {
      if(strtolower($key) == strtolower($name)) return $value;
    }
    return null;
  }

  private function _isRetryableStatus($status){
    return in_array(intval($status), [408, 500, 502, 503, 504], true);
  }

  private function _sleepBeforeRetry($attempt){
    if($this->RetryDelayMs < 1) return;
    usleep($this->RetryDelayMs * $attempt * 1000);
  }

  private function _debugLog($event, $context){
    if(!$this->Debug) return;
    $message = 'ChangeNOW '.$event.': '.json_encode($this->_redact($context));
    if(is_callable($this->DebugLogger)){
      call_user_func($this->DebugLogger, $message);
      return;
    }
    error_log($message);
  }

  private function _redact($value, $keyName = ''){
    $lowerKey = strtolower((string) $keyName);
    if($lowerKey != '' && $this->_shouldRedactKey($lowerKey)) return '[redacted]';
    if($lowerKey == 'url' && is_string($value)) return $this->_redactUrl($value);

    if(is_array($value)){
      $result = [];
      foreach ($value as $key => $item) {
        $result[$key] = $this->_redact($item, $key);
      }
      return $result;
    }

    if(is_string($value) && ($value === $this->PublicApiKey || $value === $this->PrivateApiKey)) return '[redacted]';
    return $value;
  }

  private function _redactUrl($url){
    $parts = parse_url($url);
    if(!is_array($parts) || !array_key_exists('query', $parts)) return $url;

    $query = [];
    parse_str($parts['query'], $query);
    $redactedQuery = http_build_query($this->_redact($query), '', '&', PHP_QUERY_RFC3986);
    $redactedQuery = str_replace('%5Bredacted%5D', '[redacted]', $redactedQuery);

    $result = '';
    if(array_key_exists('scheme', $parts)) $result .= $parts['scheme'].'://';
    if(array_key_exists('user', $parts)) $result .= $parts['user'].(array_key_exists('pass', $parts) ? ':'.$parts['pass'] : '').'@';
    if(array_key_exists('host', $parts)) $result .= $parts['host'];
    if(array_key_exists('port', $parts)) $result .= ':'.$parts['port'];
    if(array_key_exists('path', $parts)) $result .= $parts['path'];
    $result .= '?'.$redactedQuery;
    if(array_key_exists('fragment', $parts)) $result .= '#'.$parts['fragment'];
    return $result;
  }

  private function _shouldRedactKey($lowerKey){
    foreach (['api-key', 'apikey', 'api_key', 'authorization', 'secret', 'token', 'address', 'extraid', 'extra_id', 'userid', 'user_id', 'payload', 'forwarded-for', 'ip'] as $needle) {
      if(strpos($lowerKey, $needle) !== false) return true;
    }
    return false;
  }

  private function _positiveInteger($value, $default){
    $value = intval($value);
    return ($value > 0 ? $value : $default);
  }

  private function _toBoolean($value){
    if(is_bool($value)) return $value;
    return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
  }

  private function _isBlank($value){
    if(is_null($value)) return true;
    if(is_string($value) && trim($value) == '') return true;
    if(is_array($value) && count($value) == 0) return true;
    return false;
  }

  private function _isList($array){
    if(!is_array($array)) return false;
    if(count($array) == 0) return true;
    return array_keys($array) === range(0, count($array) - 1);
  }

  private function _camelToSnake($value){
    return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $value));
  }

}

?>
