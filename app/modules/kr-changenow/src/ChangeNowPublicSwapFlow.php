<?php

require_once __DIR__.'/ChangeNowApiException.php';
require_once __DIR__.'/ChangeNowReferralAttribution.php';
require_once __DIR__.'/../../../src/ChangeNow/ChangeNowGuardrails.php';

/**
 * Public no-registration ChangeNOW swap orchestration.
 *
 * The flow accepts plain arrays so anonymous requests do not need a User
 * object. When a logged-in user id is passed, the saved transaction is linked
 * to that account for later history features.
 *
 * @package Krypto
 */
class ChangeNowPublicSwapFlow {

  const SESSION_KEY = 'kr_changenow_session_key';
  const QUOTE_SESSION_KEY = 'kr_changenow_issued_quotes';

  private $Client = null;
  private $MarketData = null;
  private $Repository = null;
  private $App = null;
  private $User = null;
  private $Options = [];
  private $IssuedQuotes = [];
  private $AnonymousSessionKey = '';

  public function __construct($client = null, $marketData = null, $repository = null, $App = null, $User = null, $options = []){
    $this->Client = $client;
    $this->MarketData = $marketData;
    $this->Repository = $repository;
    $this->App = $App;
    $this->User = $User;
    $this->Options = (is_array($options) ? $options : []);

    if(is_null($this->Client) && !is_null($this->App) && class_exists('ChangeNowApiClient')){
      $this->Client = ChangeNowApiClient::_fromApp($this->App);
    }

    if(is_null($this->MarketData) && class_exists('ChangeNowMarketData')){
      $this->MarketData = new ChangeNowMarketData($this->Client, null, $this->App);
    }

    if(is_null($this->Repository) && class_exists('ChangeNowPublicSwapRepository')){
      $this->Repository = new ChangeNowPublicSwapRepository();
    }

    if(is_null($this->Client)) throw new ChangeNowApiConfigurationException('ChangeNOW API client is required for public swaps.');
    if(is_null($this->MarketData)) throw new ChangeNowApiConfigurationException('ChangeNOW market data service is required for public swaps.');
    if(is_null($this->Repository)) throw new ChangeNowApiConfigurationException('ChangeNOW public swap repository is required.');
  }

  public function _getInitialState(){
    $flow = $this->_getDefaultFlow();
    $eligibility = $this->_getEligibilityState();
    $sourceAssets = [];
    $destinationAssets = [];
    $defaultFrom = [
      'currency' => $this->_getDefaultFromCurrency(),
      'network' => $this->_getDefaultFromNetwork()
    ];
    $defaultTo = [
      'currency' => $this->_getDefaultToCurrency(),
      'network' => $this->_getDefaultToNetwork()
    ];

    if(is_array($eligibility) && array_key_exists('allowed', $eligibility) && $eligibility['allowed'] === false){
      return [
        'providerEnabled' => $this->_providerEnabled(),
        'missingSettings' => $this->_missingSettings(),
        'enabledFlows' => $this->_getEnabledFlows(),
        'defaultFlow' => $flow,
        'defaultFrom' => $defaultFrom,
        'defaultTo' => $defaultTo,
        'sourceAssets' => [],
        'destinationAssets' => [],
        'eligibility' => $eligibility,
        'supportEmail' => $this->_getSupportEmail()
      ];
    }

    try {
      $sourceAssets = $this->MarketData->_listSourceAssets(['flow' => $flow]);
    } catch (Exception $e) {
      $sourceAssets = [];
    }

    if(count($sourceAssets) > 0 && !$this->_assetInList($defaultFrom['currency'], $defaultFrom['network'], $sourceAssets)){
      $defaultFrom = [
        'currency' => $sourceAssets[0]['ticker'],
        'network' => $sourceAssets[0]['network']
      ];
    }

    try {
      $destinationAssets = $this->MarketData->_listDestinationAssets($defaultFrom['currency'], $defaultFrom['network'], $flow);
    } catch (Exception $e) {
      $destinationAssets = [];
    }

    if(count($destinationAssets) > 0 && !$this->_assetInList($defaultTo['currency'], $defaultTo['network'], $destinationAssets)){
      $defaultTo = [
        'currency' => $destinationAssets[0]['ticker'],
        'network' => $destinationAssets[0]['network']
      ];
    }

    return [
      'providerEnabled' => $this->_providerEnabled(),
      'missingSettings' => $this->_missingSettings(),
      'enabledFlows' => $this->_getEnabledFlows(),
      'defaultFlow' => $flow,
      'defaultFrom' => $defaultFrom,
      'defaultTo' => $defaultTo,
      'sourceAssets' => $this->_publicAssets($sourceAssets),
      'destinationAssets' => $this->_publicAssets($destinationAssets),
      'eligibility' => $eligibility,
      'supportEmail' => $this->_getSupportEmail()
    ];
  }

  public function _getQuote($request, $sessionKey = null){
    $this->_assertRegionAllowed();
    $this->_validateLiveSettings();
    $quoteRequest = $this->_quoteRequestFromPublic($request);
    $quote = $this->MarketData->_getQuote($quoteRequest);
    return $this->_issueServerQuote($quoteRequest, $quote, $sessionKey);
  }

  public function _getDestinationAssets($request){
    $flow = $this->_normalizeFlow($this->_value($request, ['flow'], $this->_getDefaultFlow()));
    if(!$this->_flowEnabled($flow)){
      throw new ChangeNowApiValidationException('The selected ChangeNOW flow is disabled.', 'Public swap requested disabled destination flow '.$flow.'.');
    }

    $fromAsset = $this->_extractAsset($request, 'from', $this->_getDefaultFromCurrency(), $this->_getDefaultFromNetwork());
    return $this->_publicAssets($this->MarketData->_listDestinationAssets($fromAsset['currency'], $fromAsset['network'], $flow));
  }

  public function _validateDestinationAddress($request){
    $normalized = $this->_normalizePublicRequest($request, false);
    if($this->_isBlank($normalized['destinationAddress'])){
      throw new ChangeNowApiValidationException('Destination address is required.', 'Destination address is required for ChangeNOW address validation.');
    }

    return $this->Client->_validateAddress($normalized['toCurrency'], $normalized['destinationAddress'], $normalized['toNetwork']);
  }

  public function _createSwap($request, $sessionKey = null, $userId = null){
    $this->_assertRegionAllowed();
    $this->_validateLiveSettings();
    $normalized = $this->_normalizePublicRequest($request, true);
    $sessionKey = $this->_effectiveSessionKey($sessionKey);
    $serverQuote = $this->_serverQuoteForRequest($normalized, $sessionKey);
    $normalized = $this->_applyServerQuote($normalized, $serverQuote);
    $this->_assertQuoteNotExpired($normalized);

    if($normalized['flow'] == 'fixed-rate' && $this->_isBlank($normalized['rateId'])){
      throw new ChangeNowApiValidationException('The fixed-rate quote expired. Request a new quote before creating the swap.', 'Fixed-rate create request requires a rateId.');
    }

    $validation = $this->Client->_validateAddress($normalized['toCurrency'], $normalized['destinationAddress'], $normalized['toNetwork']);
    if(!is_array($validation) || !array_key_exists('result', $validation) || $validation['result'] !== true){
      $message = (is_array($validation) && array_key_exists('message', $validation) && $validation['message'] != '' ? $validation['message'] : 'ChangeNOW rejected the destination address.');
      throw new ChangeNowApiValidationException('Destination address is not valid.', 'ChangeNOW address validation failed: '.$message);
    }

    $referralAttribution = $this->_referralAttributionForRequest($request, $userId);
    if(count($referralAttribution) > 0) $normalized['referralAttribution'] = $referralAttribution;

    $swapRequest = $this->_swapRequestFromPublic($normalized);
    if(!is_null($userId) && $userId !== '') $swapRequest['userId'] = (string) $userId;

    $transaction = $this->Client->_createSwap($swapRequest);
    $lookupToken = $this->_generateLookupToken();
    $record = $this->Repository->_saveCreatedSwap($normalized, $transaction, $lookupToken, $sessionKey, $userId);
    $this->_forgetServerQuote($serverQuote['quoteId']);

    return [
      'lookupToken' => $lookupToken,
      'statusUrl' => $this->_statusUrl($lookupToken),
      'transaction' => $this->_publicTransaction(array_merge($transaction, $record)),
      'supportEmail' => $this->_getSupportEmail()
    ];
  }

  public function _getStatus($lookupToken){
    $lookupToken = trim((string) $lookupToken);
    if($lookupToken == ''){
      throw new ChangeNowApiValidationException('Swap lookup token is required.', 'Public swap status requires a lookup token.');
    }

    $record = $this->Repository->_findByLookupToken($lookupToken);
    if(!is_array($record)){
      throw new ChangeNowApiNotFoundException('No ChangeNOW public swap record matched the lookup token.');
    }

    $providerId = $this->_value($record, ['providerId', 'id'], '');
    if($providerId == ''){
      return [
        'transaction' => $this->_publicTransaction($record),
        'statusWarning' => 'The saved swap is missing a provider transaction id.',
        'supportEmail' => $this->_getSupportEmail()
      ];
    }

    try {
      $status = $this->_fetchStatusWithActions($providerId);
      $record = $this->Repository->_updateStatusSnapshot($lookupToken, $status);
      return [
        'transaction' => $this->_publicTransaction(array_merge($record, $status)),
        'supportEmail' => $this->_getSupportEmail()
      ];
    } catch (ChangeNowApiException $e) {
      return [
        'transaction' => $this->_publicTransaction($record),
        'statusWarning' => $e->_getUserMessage(),
        'supportEmail' => $this->_getSupportEmail()
      ];
    }
  }

  public function _getUserHistory($userId, $limit = 50){
    if($this->_isBlank($userId)){
      throw new ChangeNowApiValidationException('User account is required for ChangeNOW history.', 'ChangeNOW user history requires a user id.');
    }

    $records = [];
    if(method_exists($this->Repository, '_listByUser')) $records = $this->Repository->_listByUser($userId, $limit);

    $transactions = [];
    foreach ($records as $record) {
      $transactions[] = $this->_publicTransaction($record);
    }

    return [
      'transactions' => $transactions,
      'supportEmail' => $this->_getSupportEmail()
    ];
  }

  public function _requestRefund($lookupToken, $refundAddress = '', $refundExtraId = '', $actorUserId = null, $actorType = 'user'){
    $lookupToken = trim((string) $lookupToken);
    if($lookupToken == ''){
      throw new ChangeNowApiValidationException('Swap lookup token is required.', 'ChangeNOW refund requires a lookup token.');
    }

    $record = $this->_recordByLookupToken($lookupToken);
    $this->_assertActionActorAllowed($record, $actorUserId, $actorType);
    $record = $this->_refreshLookupRecord($lookupToken, $record);

    if(!$this->_actionAvailable($record, 'refund')){
      throw new ChangeNowApiValidationException('Refund is not available for this ChangeNOW transaction.', 'ChangeNOW refund requested when provider action is unavailable.');
    }

    $providerId = $this->_value($record, ['providerId'], '');
    $refundAddress = trim((string) $refundAddress);
    if($refundAddress == '') $refundAddress = trim((string) $this->_value($record, ['refundAddress'], ''));
    if($refundAddress == ''){
      throw new ChangeNowApiValidationException('Refund address is required.', 'ChangeNOW refund action requires a refund address.');
    }

    $refundExtraId = trim((string) $refundExtraId);
    if($refundExtraId == '') $refundExtraId = trim((string) $this->_value($record, ['refundExtraId'], ''));

    $result = $this->Client->_refundTransaction($providerId, $refundAddress, ($refundExtraId == '' ? null : $refundExtraId));
    if(method_exists($this->Repository, '_recordEvent')){
      $this->Repository->_recordEvent($providerId, 'refund_requested', 'submitted', $actorUserId, $this->_normalizeActorType($actorType), '', $result);
    }

    return [
      'transaction' => $this->_publicTransaction($record),
      'lastAction' => $result,
      'supportEmail' => $this->_getSupportEmail()
    ];
  }

  public function _continueSwap($lookupToken, $actorUserId = null, $actorType = 'user'){
    $lookupToken = trim((string) $lookupToken);
    if($lookupToken == ''){
      throw new ChangeNowApiValidationException('Swap lookup token is required.', 'ChangeNOW continue requires a lookup token.');
    }

    $record = $this->_recordByLookupToken($lookupToken);
    $this->_assertActionActorAllowed($record, $actorUserId, $actorType);
    $record = $this->_refreshLookupRecord($lookupToken, $record);

    if(!$this->_actionAvailable($record, 'continue')){
      throw new ChangeNowApiValidationException('Continue is not available for this ChangeNOW transaction.', 'ChangeNOW continue requested when provider action is unavailable.');
    }

    $providerId = $this->_value($record, ['providerId'], '');
    $result = $this->Client->_continueTransaction($providerId);
    if(method_exists($this->Repository, '_recordEvent')){
      $this->Repository->_recordEvent($providerId, 'continue_requested', 'submitted', $actorUserId, $this->_normalizeActorType($actorType), '', $result);
    }

    return [
      'transaction' => $this->_publicTransaction($record),
      'lastAction' => $result,
      'supportEmail' => $this->_getSupportEmail()
    ];
  }

  public function _refreshProviderStatus($providerId, $actorUserId = null, $actorType = 'support'){
    $record = $this->_recordByProviderId($providerId);
    $status = $this->_fetchStatusWithActions($this->_value($record, ['providerId'], ''));
    if(method_exists($this->Repository, '_updateStatusSnapshotByProviderId')){
      $updated = $this->Repository->_updateStatusSnapshotByProviderId($this->_value($record, ['providerId'], ''), $status);
      $record = (is_array($updated) ? $updated : array_merge($record, $status));
    } else {
      $record = array_merge($record, $status);
    }

    if(method_exists($this->Repository, '_recordEvent')){
      $this->Repository->_recordEvent($this->_value($record, ['providerId'], $providerId), 'status_refreshed', 'completed', $actorUserId, $this->_normalizeActorType($actorType), '', $status);
    }

    return [
      'transaction' => $this->_publicTransaction(array_merge($record, $status)),
      'supportEmail' => $this->_getSupportEmail()
    ];
  }

  public function _requestRefundByProviderId($providerId, $refundAddress = '', $refundExtraId = '', $actorUserId = null, $actorType = 'support'){
    $record = $this->_recordByProviderId($providerId);
    $record = $this->_refreshProviderRecord($record);

    if(!$this->_actionAvailable($record, 'refund')){
      throw new ChangeNowApiValidationException('Refund is not available for this ChangeNOW transaction.', 'ChangeNOW support refund requested when provider action is unavailable.');
    }

    $refundAddress = trim((string) $refundAddress);
    if($refundAddress == '') $refundAddress = trim((string) $this->_value($record, ['refundAddress'], ''));
    if($refundAddress == ''){
      throw new ChangeNowApiValidationException('Refund address is required.', 'ChangeNOW support refund action requires a refund address.');
    }

    $refundExtraId = trim((string) $refundExtraId);
    if($refundExtraId == '') $refundExtraId = trim((string) $this->_value($record, ['refundExtraId'], ''));

    $result = $this->Client->_refundTransaction($this->_value($record, ['providerId'], ''), $refundAddress, ($refundExtraId == '' ? null : $refundExtraId));
    if(method_exists($this->Repository, '_recordEvent')){
      $this->Repository->_recordEvent($this->_value($record, ['providerId'], ''), 'refund_requested', 'submitted', $actorUserId, $this->_normalizeActorType($actorType), '', $result);
    }

    return [
      'transaction' => $this->_publicTransaction($record),
      'lastAction' => $result,
      'supportEmail' => $this->_getSupportEmail()
    ];
  }

  public function _continueSwapByProviderId($providerId, $actorUserId = null, $actorType = 'support'){
    $record = $this->_recordByProviderId($providerId);
    $record = $this->_refreshProviderRecord($record);

    if(!$this->_actionAvailable($record, 'continue')){
      throw new ChangeNowApiValidationException('Continue is not available for this ChangeNOW transaction.', 'ChangeNOW support continue requested when provider action is unavailable.');
    }

    $result = $this->Client->_continueTransaction($this->_value($record, ['providerId'], ''));
    if(method_exists($this->Repository, '_recordEvent')){
      $this->Repository->_recordEvent($this->_value($record, ['providerId'], ''), 'continue_requested', 'submitted', $actorUserId, $this->_normalizeActorType($actorType), '', $result);
    }

    return [
      'transaction' => $this->_publicTransaction($record),
      'lastAction' => $result,
      'supportEmail' => $this->_getSupportEmail()
    ];
  }

  public function _saveSupportNoteByProviderId($providerId, $note, $actorUserId = null, $actorType = 'support'){
    if(!method_exists($this->Repository, '_saveSupportNote')){
      throw new ChangeNowApiConfigurationException('ChangeNOW transaction repository cannot save support notes.');
    }
    $record = $this->Repository->_saveSupportNote($providerId, $note, $actorUserId, $this->_normalizeActorType($actorType));
    if(!is_array($record)) throw new ChangeNowApiNotFoundException('No ChangeNOW transaction matched the provider id for support note.');

    return [
      'transaction' => $this->_publicTransaction($record),
      'supportEmail' => $this->_getSupportEmail()
    ];
  }

  public static function _sessionKeyFromSession(&$session){
    if(!array_key_exists(self::SESSION_KEY, $session) || trim((string) $session[self::SESSION_KEY]) == ''){
      $session[self::SESSION_KEY] = self::_randomToken();
    }
    return $session[self::SESSION_KEY];
  }

  public static function _randomToken(){
    if(function_exists('random_bytes')) return bin2hex(random_bytes(32));
    if(function_exists('openssl_random_pseudo_bytes')) return bin2hex(openssl_random_pseudo_bytes(32));
    return hash('sha256', uniqid('', true).mt_rand());
  }

  private function _recordByLookupToken($lookupToken){
    $record = $this->Repository->_findByLookupToken($lookupToken);
    if(!is_array($record)){
      throw new ChangeNowApiNotFoundException('No ChangeNOW public swap record matched the lookup token.');
    }
    return $record;
  }

  private function _recordByProviderId($providerId){
    if(!method_exists($this->Repository, '_findByProviderId')){
      throw new ChangeNowApiConfigurationException('ChangeNOW transaction repository cannot look up provider transactions.');
    }

    $record = $this->Repository->_findByProviderId($providerId);
    if(!is_array($record)){
      throw new ChangeNowApiNotFoundException('No ChangeNOW transaction matched the provider id.');
    }
    return $record;
  }

  private function _refreshLookupRecord($lookupToken, $record){
    $providerId = $this->_value($record, ['providerId', 'id'], '');
    if($providerId == '') return $record;
    $status = $this->_fetchStatusWithActions($providerId);
    $updated = $this->Repository->_updateStatusSnapshot($lookupToken, $status);
    return (is_array($updated) ? $updated : array_merge($record, $status));
  }

  private function _refreshProviderRecord($record){
    $providerId = $this->_value($record, ['providerId', 'id'], '');
    if($providerId == '') return $record;
    $status = $this->_fetchStatusWithActions($providerId);
    if(method_exists($this->Repository, '_updateStatusSnapshotByProviderId')){
      $updated = $this->Repository->_updateStatusSnapshotByProviderId($providerId, $status);
      return (is_array($updated) ? $updated : array_merge($record, $status));
    }
    return array_merge($record, $status);
  }

  private function _fetchStatusWithActions($providerId){
    $status = $this->Client->_getSwapStatus($providerId);
    if(is_array($status) && array_key_exists('actionsAvailable', $status) && $status['actionsAvailable'] === true && method_exists($this->Client, '_getAvailableActions')){
      try {
        $status['actionsAvailable'] = $this->Client->_getAvailableActions($providerId);
      } catch (ChangeNowApiException $e) {
        $status['actionsWarning'] = $e->_getUserMessage();
      }
    }
    return $status;
  }

  private function _assertActionActorAllowed($record, $actorUserId, $actorType){
    $actorType = $this->_normalizeActorType($actorType);
    if(in_array($actorType, ['admin', 'manager', 'support', 'system'], true)) return true;

    $recordUserId = $this->_value($record, ['userId'], null);
    if($recordUserId === null || $recordUserId === '') return true;

    if(!$this->_isBlank($actorUserId) && (string) $actorUserId === (string) $recordUserId) return true;
    throw new ChangeNowApiNotFoundException('ChangeNOW action denied for transaction owner mismatch.');
  }

  private function _actionAvailable($record, $action){
    $availableActions = $this->_value($record, ['availableActions'], []);
    if(is_array($availableActions) && array_key_exists($action, $availableActions)) return $this->_boolValue($availableActions[$action]);
    if($action == 'refund') return $this->_boolValue($this->_value($record, ['refundAvailable'], false));
    if($action == 'continue') return $this->_boolValue($this->_value($record, ['continueAvailable'], false));
    return false;
  }

  private function _publicAvailableActions($payload){
    $actions = [
      'refund' => $this->_boolValue($this->_value($payload, ['refundAvailable'], false)),
      'continue' => $this->_boolValue($this->_value($payload, ['continueAvailable'], false))
    ];

    $availableActions = $this->_value($payload, ['availableActions'], null);
    if(is_array($availableActions)){
      if(array_key_exists('refund', $availableActions)) $actions['refund'] = $this->_boolValue($availableActions['refund']);
      if(array_key_exists('continue', $availableActions)) $actions['continue'] = $this->_boolValue($availableActions['continue']);
    }

    return $actions;
  }

  private function _normalizeActorType($actorType){
    $actorType = strtolower(trim((string) $actorType));
    if($actorType == '') return 'user';
    if($actorType == 'anonymous') return 'anonymous';
    if($actorType == 'admin') return 'admin';
    if($actorType == 'manager') return 'manager';
    if($actorType == 'support') return 'support';
    if($actorType == 'system') return 'system';
    return 'user';
  }

  private function _quoteRequestFromPublic($request){
    $normalized = $this->_normalizePublicRequest($request, false);
    $quoteRequest = [
      'fromCurrency' => $normalized['fromCurrency'],
      'fromNetwork' => $normalized['fromNetwork'],
      'toCurrency' => $normalized['toCurrency'],
      'toNetwork' => $normalized['toNetwork'],
      'fromAmount' => $normalized['amount'],
      'flow' => $normalized['flow']
    ];

    if($normalized['flow'] == 'fixed-rate') $quoteRequest['useRateId'] = 'true';
    return $quoteRequest;
  }

  private function _issueServerQuote($quoteRequest, $quote, $sessionKey = null){
    $quote = (is_array($quote) ? $quote : []);
    $now = $this->_now();
    $expiresAt = $this->_serverQuoteExpiresAt($quote, $now);
    $quoteId = $this->_generateQuoteId();
    $sessionKey = $this->_effectiveSessionKey($sessionKey);

    $record = [
      'quoteId' => $quoteId,
      'sessionKey' => $sessionKey,
      'fromCurrency' => $this->_normalizeCode($this->_value($quote, ['fromCurrency'], $quoteRequest['fromCurrency'])),
      'fromNetwork' => $this->_normalizeCode($this->_value($quote, ['fromNetwork'], $quoteRequest['fromNetwork'])),
      'toCurrency' => $this->_normalizeCode($this->_value($quote, ['toCurrency'], $quoteRequest['toCurrency'])),
      'toNetwork' => $this->_normalizeCode($this->_value($quote, ['toNetwork'], $quoteRequest['toNetwork'])),
      'flow' => $this->_normalizeFlow($this->_value($quote, ['flow'], $quoteRequest['flow'])),
      'amount' => $this->_amountValue($this->_value($quote, ['fromAmount', 'amount'], $this->_value($quoteRequest, ['fromAmount'], ''))),
      'fromAmount' => $this->_amountValue($this->_value($quote, ['fromAmount', 'amount'], $this->_value($quoteRequest, ['fromAmount'], ''))),
      'toAmount' => $this->_amountValue($this->_value($quote, ['toAmount', 'estimatedReceiveAmount'], null)),
      'estimatedReceiveAmount' => $this->_amountValue($this->_value($quote, ['estimatedReceiveAmount', 'toAmount'], null)),
      'rateId' => trim((string) $this->_value($quote, ['rateId', 'rate_id'], '')),
      'rate' => $this->_value($quote, ['rate', 'exchangeRate', 'exchange_rate'], null),
      'validUntil' => gmdate('c', $expiresAt),
      'expiresAt' => $expiresAt,
      'createdAt' => $now
    ];

    $store =& $this->_quoteStore();
    $this->_pruneServerQuotes($store, $now);
    $store[$quoteId] = $record;

    $quote['quoteId'] = $quoteId;
    $quote['validUntil'] = $record['validUntil'];
    foreach (['fromCurrency', 'fromNetwork', 'toCurrency', 'toNetwork', 'flow', 'amount', 'fromAmount', 'toAmount', 'estimatedReceiveAmount', 'rateId'] as $key) {
      if(!array_key_exists($key, $quote) || $quote[$key] === null || $quote[$key] === '') $quote[$key] = $record[$key];
    }

    return $quote;
  }

  private function _serverQuoteForRequest($normalized, $sessionKey){
    if($this->_isBlank($normalized['quoteId'])){
      throw new ChangeNowApiValidationException('The ChangeNOW quote expired. Request a new quote before creating the swap.', 'Public swap create request is missing a server-issued quote id.');
    }

    $now = $this->_now();
    $store =& $this->_quoteStore();
    $this->_pruneServerQuotes($store, $now);
    $quoteId = $normalized['quoteId'];

    if(!array_key_exists($quoteId, $store) || !is_array($store[$quoteId])){
      throw new ChangeNowApiValidationException('The ChangeNOW quote expired. Request a new quote before creating the swap.', 'Public swap create request used an unknown or expired server quote id.');
    }

    $record = $store[$quoteId];
    if($this->_value($record, ['sessionKey'], '') !== $sessionKey){
      throw new ChangeNowApiValidationException('The ChangeNOW quote expired. Request a new quote before creating the swap.', 'Public swap create request used a quote from a different session.');
    }

    if(intval($this->_value($record, ['expiresAt'], 0)) <= $now){
      unset($store[$quoteId]);
      throw new ChangeNowApiValidationException('The ChangeNOW quote expired. Request a new quote before creating the swap.', 'Public swap create request used an expired server quote.');
    }

    $this->_assertServerQuoteMatches($normalized, $record);
    return $record;
  }

  private function _applyServerQuote($normalized, $serverQuote){
    foreach (['fromCurrency', 'fromNetwork', 'toCurrency', 'toNetwork', 'flow'] as $key) {
      $normalized[$key] = $serverQuote[$key];
    }

    $normalized['amount'] = $serverQuote['amount'];
    $normalized['rateId'] = $serverQuote['rateId'];
    $normalized['validUntil'] = $serverQuote['validUntil'];
    return $normalized;
  }

  private function _assertServerQuoteMatches($normalized, $serverQuote){
    foreach (['fromCurrency', 'fromNetwork', 'toCurrency', 'toNetwork', 'flow'] as $key) {
      if((string) $normalized[$key] !== (string) $serverQuote[$key]){
        throw new ChangeNowApiValidationException('The ChangeNOW quote no longer matches the swap details. Request a new quote before creating the swap.', 'Public swap create request did not match the server quote '.$key.'.');
      }
    }

    if(!$this->_amountsMatch($normalized['amount'], $serverQuote['amount'])){
      throw new ChangeNowApiValidationException('The ChangeNOW quote no longer matches the swap details. Request a new quote before creating the swap.', 'Public swap create amount did not match the server quote amount.');
    }

    return true;
  }

  private function &_quoteStore(){
    if(array_key_exists('quote_store', $this->Options) && is_array($this->Options['quote_store'])){
      return $this->Options['quote_store'];
    }

    if(isset($_SESSION) && is_array($_SESSION)){
      if(!array_key_exists(self::QUOTE_SESSION_KEY, $_SESSION) || !is_array($_SESSION[self::QUOTE_SESSION_KEY])){
        $_SESSION[self::QUOTE_SESSION_KEY] = [];
      }
      return $_SESSION[self::QUOTE_SESSION_KEY];
    }

    return $this->IssuedQuotes;
  }

  private function _pruneServerQuotes(&$store, $now){
    foreach ($store as $quoteId => $record) {
      if(!is_array($record) || intval($this->_value($record, ['expiresAt'], 0)) <= $now){
        unset($store[$quoteId]);
      }
    }
  }

  private function _forgetServerQuote($quoteId){
    $store =& $this->_quoteStore();
    if(array_key_exists($quoteId, $store)) unset($store[$quoteId]);
  }

  private function _generateQuoteId(){
    if(array_key_exists('quote_token_factory', $this->Options) && is_callable($this->Options['quote_token_factory'])){
      $quoteId = call_user_func($this->Options['quote_token_factory']);
      if(!$this->_isBlank($quoteId)) return (string) $quoteId;
    }

    return self::_randomToken();
  }

  private function _serverQuoteExpiresAt($quote, $now){
    $expiresAt = $now + $this->_getServerQuoteTtl();
    $providerExpiresAt = $this->_timestampFromProviderValue($this->_value($quote, ['validUntil', 'valid_until'], null));
    if(!is_null($providerExpiresAt)) $expiresAt = min($expiresAt, $providerExpiresAt);
    return intval($expiresAt);
  }

  private function _timestampFromProviderValue($value){
    if($this->_isBlank($value)) return null;
    if(is_int($value) || is_float($value) || is_numeric($value)){
      $timestamp = intval($value);
      if($timestamp > 20000000000) $timestamp = intval(floor($timestamp / 1000));
      return $timestamp;
    }

    $timestamp = strtotime((string) $value);
    return ($timestamp === false ? null : $timestamp);
  }

  private function _getServerQuoteTtl(){
    if(array_key_exists('server_quote_ttl', $this->Options)){
      $ttl = intval($this->Options['server_quote_ttl']);
      if($ttl > 0) return $ttl;
    }

    if(!is_null($this->App) && method_exists($this->App, '_getChangeNowQuoteCacheTtl')){
      $ttl = intval($this->App->_getChangeNowQuoteCacheTtl());
      if($ttl > 0) return $ttl;
    }

    if(class_exists('ChangeNowMarketData')) return ChangeNowMarketData::DEFAULT_QUOTE_CACHE_TTL;
    return 30;
  }

  private function _now(){
    if(array_key_exists('time_factory', $this->Options) && is_callable($this->Options['time_factory'])){
      return intval(call_user_func($this->Options['time_factory']));
    }

    return time();
  }

  private function _amountsMatch($left, $right){
    $left = trim((string) $left);
    $right = trim((string) $right);
    if($left === $right) return true;
    if(is_numeric($left) && is_numeric($right)) return abs(floatval($left) - floatval($right)) <= 0.000000000001;
    return false;
  }

  private function _swapRequestFromPublic($normalized){
    $swapRequest = [
      'fromCurrency' => $normalized['fromCurrency'],
      'fromNetwork' => $normalized['fromNetwork'],
      'toCurrency' => $normalized['toCurrency'],
      'toNetwork' => $normalized['toNetwork'],
      'fromAmount' => $normalized['amount'],
      'address' => $normalized['destinationAddress'],
      'flow' => $normalized['flow']
    ];

    foreach ([
      'destinationExtraId' => 'extraId',
      'refundAddress' => 'refundAddress',
      'refundExtraId' => 'refundExtraId',
      'rateId' => 'rateId',
      'contactEmail' => 'contactEmail'
    ] as $sourceKey => $targetKey) {
      if(!$this->_isBlank($normalized[$sourceKey])) $swapRequest[$targetKey] = $normalized[$sourceKey];
    }

    if(array_key_exists('referralAttribution', $normalized) && is_array($normalized['referralAttribution']) && count($normalized['referralAttribution']) > 0){
      $swapRequest['payload'] = [
        'kryptoReferralAttribution' => $normalized['referralAttribution']
      ];
    }

    return $swapRequest;
  }

  private function _referralAttributionForRequest($request, $userId){
    if(!class_exists('ChangeNowReferralAttribution')) return [];

    $session = [];
    if(array_key_exists('referral_session', $this->Options) && is_array($this->Options['referral_session'])) $session = $this->Options['referral_session'];
    elseif(isset($_SESSION) && is_array($_SESSION)) $session = $_SESSION;

    $options = [
      'loggedInUserId' => $userId,
      'changeNowReferralLinkId' => $this->_getChangeNowReferralLinkId(),
      'referralCodeOwnerResolver' => $this->_referralCodeOwnerResolver()
    ];

    if(array_key_exists('attribution_time_factory', $this->Options) && is_callable($this->Options['attribution_time_factory'])){
      $options['now'] = call_user_func($this->Options['attribution_time_factory']);
    }

    return ChangeNowReferralAttribution::_fromRequest($request, $session, $options);
  }

  private function _normalizePublicRequest($request, $requireAddress){
    if(!is_array($request)) throw new ChangeNowApiValidationException('The ChangeNOW swap request is incomplete.', 'Public swap request must be an array.');

    $fromAsset = $this->_extractAsset($request, 'from', $this->_getDefaultFromCurrency(), $this->_getDefaultFromNetwork());
    $toAsset = $this->_extractAsset($request, 'to', $this->_getDefaultToCurrency(), $this->_getDefaultToNetwork());
    $amount = $this->_amountValue($this->_value($request, ['amount', 'fromAmount', 'from_amount'], ''));
    $flow = $this->_normalizeFlow($this->_value($request, ['flow'], $this->_getDefaultFlow()));

    if(!$this->_flowEnabled($flow)){
      throw new ChangeNowApiValidationException('The selected ChangeNOW flow is disabled.', 'Public swap requested disabled flow '.$flow.'.');
    }

    if($amount === null || floatval($amount) <= 0){
      throw new ChangeNowApiValidationException('Swap amount must be greater than zero.', 'Public swap amount must be numeric and positive.');
    }

    $destinationAddress = trim((string) $this->_value($request, ['destinationAddress', 'address', 'payoutAddress', 'payout_address'], ''));
    if($requireAddress && $destinationAddress == ''){
      throw new ChangeNowApiValidationException('Destination address is required.', 'Destination address is required to create a ChangeNOW swap.');
    }

    $contactEmail = trim((string) $this->_value($request, ['contactEmail', 'contact_email'], ''));
    $contactEmail = $this->_validateContactEmail($contactEmail);

    return [
      'fromCurrency' => $fromAsset['currency'],
      'fromNetwork' => $fromAsset['network'],
      'toCurrency' => $toAsset['currency'],
      'toNetwork' => $toAsset['network'],
      'amount' => $amount,
      'flow' => $flow,
      'destinationAddress' => $destinationAddress,
      'destinationExtraId' => trim((string) $this->_value($request, ['destinationExtraId', 'extraId', 'payoutExtraId', 'payout_extra_id'], '')),
      'refundAddress' => trim((string) $this->_value($request, ['refundAddress', 'refund_address'], '')),
      'refundExtraId' => trim((string) $this->_value($request, ['refundExtraId', 'refund_extra_id'], '')),
      'quoteId' => trim((string) $this->_value($request, ['quoteId', 'quote_id', 'serverQuoteId', 'server_quote_id'], '')),
      'rateId' => trim((string) $this->_value($request, ['rateId', 'rate_id'], '')),
      'validUntil' => trim((string) $this->_value($request, ['validUntil', 'valid_until', 'quoteValidUntil', 'quote_valid_until'], '')),
      'contactEmail' => $contactEmail
    ];
  }

  private function _validateContactEmail($email){
    $email = trim((string) $email);
    if($email == '') return '';
    if(strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)){
      throw new ChangeNowApiValidationException('Contact email is not valid.', 'Public ChangeNOW swap request included an invalid contactEmail.');
    }
    return $email;
  }

  private function _extractAsset($request, $prefix, $defaultCurrency, $defaultNetwork){
    $asset = trim((string) $this->_value($request, [$prefix.'Asset', $prefix.'_asset'], ''));
    $currency = $this->_normalizeCode($this->_value($request, [$prefix.'Currency', $prefix.'_currency'], ''));
    $network = $this->_normalizeCode($this->_value($request, [$prefix.'Network', $prefix.'_network'], ''));

    if($asset != ''){
      $parts = explode(':', $asset);
      if(count($parts) > 0 && $currency == '') $currency = $this->_normalizeCode($parts[0]);
      if(count($parts) > 1 && $network == '') $network = $this->_normalizeCode($parts[1]);
    }

    if($currency == '') $currency = $this->_normalizeCode($defaultCurrency);
    if($network == '') $network = $this->_normalizeCode($defaultNetwork);
    if($network == '') $network = $currency;

    if($currency == ''){
      throw new ChangeNowApiValidationException('The ChangeNOW swap request is incomplete.', ucfirst($prefix).' asset is required.');
    }

    return [
      'currency' => $currency,
      'network' => $network
    ];
  }

  private function _validateLiveSettings(){
    if(!is_null($this->App) && method_exists($this->App, '_validateChangeNowLiveSwapSettings')){
      $this->App->_validateChangeNowLiveSwapSettings();
      return true;
    }

    if(!$this->_providerEnabled()) throw new ChangeNowApiConfigurationException('ChangeNOW provider is disabled.');
    return true;
  }

  private function _assertRegionAllowed(){
    $state = $this->_getEligibilityState();
    if(is_array($state) && array_key_exists('allowed', $state) && $state['allowed'] === false){
      $message = $this->_value($state, ['message'], '');
      if($message == ''){
        $copy = ChangeNowGuardrails::messages();
        $message = $copy['unsupported_region'];
      }

      throw new ChangeNowApiValidationException(
        $message,
        'ChangeNOW public swap denied for request country '.$this->_value($state, ['country'], '').'.',
        403,
        [
          'state' => $this->_value($state, ['state'], 'unsupported_region'),
          'country' => $this->_value($state, ['country'], '')
        ]
      );
    }

    return true;
  }

  private function _getEligibilityState(){
    $blockedCountries = $this->_getBlockedCountries();
    if(count($blockedCountries) == 0){
      return [
        'allowed' => true,
        'state' => 'allowed',
        'message' => '',
        'country' => ''
      ];
    }

    $countryCode = $this->_getRequestCountryCode();
    if(!is_null($this->App) && method_exists($this->App, '_getChangeNowEligibilityForCountry')){
      $state = $this->App->_getChangeNowEligibilityForCountry($countryCode);
    } else {
      $state = ChangeNowEligibility::countryState($countryCode, $blockedCountries, $this->_getComplianceCopy());
    }

    if(!is_array($state)){
      $state = [
        'allowed' => true,
        'state' => 'allowed',
        'message' => ''
      ];
    }

    $state['country'] = $countryCode;
    return $state;
  }

  private function _getBlockedCountries(){
    if(!is_null($this->App) && method_exists($this->App, '_getChangeNowBlockedCountries')){
      return ChangeNowEligibility::normalizeCountryList($this->App->_getChangeNowBlockedCountries());
    }

    if(array_key_exists('blocked_countries', $this->Options)){
      return ChangeNowEligibility::normalizeCountryList($this->Options['blocked_countries']);
    }

    return [];
  }

  private function _getComplianceCopy(){
    if(!is_null($this->App) && method_exists($this->App, '_getChangeNowComplianceCopy')){
      return ChangeNowGuardrails::mergeComplianceCopy($this->App->_getChangeNowComplianceCopy());
    }

    if(array_key_exists('compliance_copy', $this->Options)){
      return ChangeNowGuardrails::mergeComplianceCopy($this->Options['compliance_copy']);
    }

    return ChangeNowGuardrails::messages();
  }

  private function _getRequestCountryCode(){
    foreach (['request_country', 'country_code'] as $optionKey) {
      if(array_key_exists($optionKey, $this->Options)){
        return ChangeNowRequestRegion::normalizeCountryCode($this->Options[$optionKey]);
      }
    }

    $server = (array_key_exists('server', $this->Options) && is_array($this->Options['server']) ? $this->Options['server'] : (isset($_SERVER) ? $_SERVER : []));
    $geoIpResolver = null;
    if(array_key_exists('geoip_resolver', $this->Options)) $geoIpResolver = $this->Options['geoip_resolver'];
    elseif(array_key_exists('country_resolver', $this->Options)) $geoIpResolver = $this->Options['country_resolver'];

    if(!is_null($this->App) && method_exists($this->App, '_getChangeNowRequestCountry')){
      return ChangeNowRequestRegion::normalizeCountryCode($this->App->_getChangeNowRequestCountry($server, $geoIpResolver));
    }

    return ChangeNowRequestRegion::countryCode($server, $geoIpResolver);
  }

  private function _assertQuoteNotExpired($request){
    if($this->_isBlank($request['validUntil'])) return true;
    $expiresAt = strtotime($request['validUntil']);
    if($expiresAt !== false && $expiresAt <= $this->_now()){
      throw new ChangeNowApiValidationException('The ChangeNOW quote expired. Request a new quote before creating the swap.', 'Public swap create request used an expired quote.');
    }
    return true;
  }

  private function _publicTransaction($payload){
    $providerId = $this->_value($payload, ['providerId', 'id'], null);
    return [
      'id' => $providerId,
      'providerId' => $providerId,
      'status' => $this->_value($payload, ['status'], 'waiting'),
      'flow' => $this->_value($payload, ['flow'], null),
      'fromCurrency' => $this->_value($payload, ['fromCurrency'], null),
      'fromNetwork' => $this->_value($payload, ['fromNetwork'], null),
      'toCurrency' => $this->_value($payload, ['toCurrency'], null),
      'toNetwork' => $this->_value($payload, ['toNetwork'], null),
      'fromAmount' => $this->_value($payload, ['fromAmount', 'amountFrom', 'expectedAmountFrom'], null),
      'toAmount' => $this->_value($payload, ['toAmount', 'amountTo', 'expectedAmountTo'], null),
      'payinAddress' => $this->_value($payload, ['payinAddress'], null),
      'payinExtraId' => $this->_value($payload, ['payinExtraId'], null),
      'payoutAddress' => $this->_value($payload, ['payoutAddress'], null),
      'payoutExtraId' => $this->_value($payload, ['payoutExtraId'], null),
      'payoutAddressFingerprint' => $this->_value($payload, ['payoutAddressFingerprint'], null),
      'refundAddress' => $this->_value($payload, ['refundAddress'], null),
      'refundExtraId' => $this->_value($payload, ['refundExtraId'], null),
      'availableActions' => $this->_publicAvailableActions($payload),
      'validUntil' => $this->_value($payload, ['validUntil'], null),
      'createdAt' => $this->_value($payload, ['createdAt'], null),
      'updatedAt' => $this->_value($payload, ['updatedAt'], null)
    ];
  }

  private function _publicAssets($assets){
    $result = [];
    foreach ($assets as $asset) {
      if(!is_array($asset)) continue;
      $ticker = $this->_normalizeCode($this->_value($asset, ['ticker'], ''));
      if($ticker == '') continue;
      $network = $this->_normalizeCode($this->_value($asset, ['network'], $ticker));
      $result[] = [
        'ticker' => $ticker,
        'network' => ($network == '' ? $ticker : $network),
        'name' => trim((string) $this->_value($asset, ['name'], strtoupper($ticker))),
        'image' => trim((string) $this->_value($asset, ['image'], ''))
      ];
    }
    return $result;
  }

  private function _assetInList($ticker, $network, $assets){
    $ticker = $this->_normalizeCode($ticker);
    $network = $this->_normalizeCode($network);
    foreach ($assets as $asset) {
      if($this->_normalizeCode($this->_value($asset, ['ticker'], '')) == $ticker && $this->_normalizeCode($this->_value($asset, ['network'], '')) == $network) return true;
    }
    return false;
  }

  private function _generateLookupToken(){
    if(array_key_exists('token_factory', $this->Options) && is_callable($this->Options['token_factory'])){
      $token = call_user_func($this->Options['token_factory']);
      if(!$this->_isBlank($token)) return (string) $token;
    }
    return self::_randomToken();
  }

  private function _normalizeSessionKey($sessionKey){
    $sessionKey = trim((string) $sessionKey);
    return ($sessionKey == '' ? self::_randomToken() : $sessionKey);
  }

  private function _effectiveSessionKey($sessionKey){
    if(!$this->_isBlank($sessionKey)) return $this->_normalizeSessionKey($sessionKey);
    if(isset($_SESSION) && is_array($_SESSION)) return self::_sessionKeyFromSession($_SESSION);

    if($this->AnonymousSessionKey == '') $this->AnonymousSessionKey = self::_randomToken();
    return $this->AnonymousSessionKey;
  }

  private function _statusUrl($lookupToken){
    $baseUrl = '';
    if(array_key_exists('status_base_url', $this->Options)) $baseUrl = rtrim($this->Options['status_base_url'], '/').'/';
    elseif(defined('APP_URL')) $baseUrl = rtrim(APP_URL, '/').'/';
    return $baseUrl.'?swap_token='.rawurlencode($lookupToken);
  }

  private function _providerEnabled(){
    if(array_key_exists('provider_enabled', $this->Options)) return $this->_boolValue($this->Options['provider_enabled']);
    if(!is_null($this->App) && method_exists($this->App, '_changeNowProviderEnabled')) return $this->App->_changeNowProviderEnabled();
    return true;
  }

  private function _missingSettings(){
    if(!is_null($this->App) && method_exists($this->App, '_getChangeNowMissingRequiredSettings')) return $this->App->_getChangeNowMissingRequiredSettings();
    return [];
  }

  private function _getEnabledFlows(){
    if(array_key_exists('enabled_flows', $this->Options)) return $this->_normalizeFlows($this->Options['enabled_flows']);
    if(!is_null($this->App) && method_exists($this->App, '_getChangeNowEnabledFlows')) return $this->_normalizeFlows($this->App->_getChangeNowEnabledFlows());
    return ['standard'];
  }

  private function _getDefaultFlow(){
    $flow = 'standard';
    if(array_key_exists('default_flow', $this->Options)) $flow = $this->Options['default_flow'];
    elseif(!is_null($this->App) && method_exists($this->App, '_getChangeNowDefaultFlow')) $flow = $this->App->_getChangeNowDefaultFlow();
    $flow = $this->_normalizeFlow($flow);
    $enabledFlows = $this->_getEnabledFlows();
    return ($this->_flowEnabled($flow) ? $flow : $enabledFlows[0]);
  }

  private function _getDefaultFromCurrency(){
    if(array_key_exists('default_from_asset', $this->Options)) return $this->_normalizeCode($this->Options['default_from_asset']);
    if(!is_null($this->App) && method_exists($this->App, '_getChangeNowDefaultFromAsset')) return $this->_normalizeCode($this->App->_getChangeNowDefaultFromAsset());
    return 'btc';
  }

  private function _getDefaultFromNetwork(){
    if(array_key_exists('default_from_network', $this->Options)) return $this->_normalizeCode($this->Options['default_from_network']);
    if(!is_null($this->App) && method_exists($this->App, '_getChangeNowDefaultFromNetwork')) return $this->_normalizeCode($this->App->_getChangeNowDefaultFromNetwork());
    return 'btc';
  }

  private function _getDefaultToCurrency(){
    if(array_key_exists('default_to_asset', $this->Options)) return $this->_normalizeCode($this->Options['default_to_asset']);
    if(!is_null($this->App) && method_exists($this->App, '_getChangeNowDefaultToAsset')) return $this->_normalizeCode($this->App->_getChangeNowDefaultToAsset());
    return 'eth';
  }

  private function _getDefaultToNetwork(){
    if(array_key_exists('default_to_network', $this->Options)) return $this->_normalizeCode($this->Options['default_to_network']);
    if(!is_null($this->App) && method_exists($this->App, '_getChangeNowDefaultToNetwork')) return $this->_normalizeCode($this->App->_getChangeNowDefaultToNetwork());
    return 'eth';
  }

  private function _getSupportEmail(){
    if(array_key_exists('support_email', $this->Options)) return trim((string) $this->Options['support_email']);
    if(!is_null($this->App) && method_exists($this->App, '_getChangeNowSupportEmail')) return $this->App->_getChangeNowSupportEmail();
    return '';
  }

  private function _getChangeNowReferralLinkId(){
    if(array_key_exists('change_now_referral_link_id', $this->Options)) return trim((string) $this->Options['change_now_referral_link_id']);
    if(array_key_exists('changenow_referral_link_id', $this->Options)) return trim((string) $this->Options['changenow_referral_link_id']);
    if(!is_null($this->App) && method_exists($this->App, '_getChangeNowReferralLinkId')) return trim((string) $this->App->_getChangeNowReferralLinkId());
    return '';
  }

  private function _referralCodeOwnerResolver(){
    if(array_key_exists('referral_owner_resolver', $this->Options) && is_callable($this->Options['referral_owner_resolver'])){
      return $this->Options['referral_owner_resolver'];
    }

    if(!is_null($this->App) && method_exists($this->App, '_getReferalCodeOwnerId')){
      $App = $this->App;
      return function($code) use ($App) {
        return $App->_getReferalCodeOwnerId($code);
      };
    }

    return null;
  }

  private function _flowEnabled($flow){
    return in_array($this->_normalizeFlow($flow), $this->_getEnabledFlows(), true);
  }

  private function _normalizeFlows($flows){
    if(!is_array($flows)) $flows = explode(',', (string) $flows);
    $result = [];
    foreach ($flows as $flow) {
      $flow = $this->_normalizeFlow($flow);
      if(!in_array($flow, $result, true)) $result[] = $flow;
    }
    if(count($result) == 0) $result[] = 'standard';
    return $result;
  }

  private function _normalizeFlow($flow){
    $flow = strtolower(trim((string) $flow));
    if($flow == 'fixed') $flow = 'fixed-rate';
    if(!in_array($flow, ['standard', 'fixed-rate'], true)) return 'standard';
    return $flow;
  }

  private function _normalizeCode($value){
    $value = strtolower(trim((string) $value));
    return preg_replace('/[^a-z0-9_-]/', '', $value);
  }

  private function _amountValue($value){
    if($value === null || $value === '') return null;
    $value = trim((string) $value);
    if(!is_numeric($value)) return null;
    return $value;
  }

  private function _boolValue($value){
    if(is_bool($value)) return $value;
    if(is_int($value)) return $value == 1;
    return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
  }

  private function _isBlank($value){
    return $value === null || trim((string) $value) == '';
  }

  private function _value($source, $keys, $default = null){
    if(!is_array($source)) return $default;
    foreach ($keys as $key) {
      if(array_key_exists($key, $source)) return $source[$key];
    }
    return $default;
  }

}

?>
