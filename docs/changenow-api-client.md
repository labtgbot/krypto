# ChangeNOW API Client

`app/modules/kr-changenow/src/ChangeNowApiClient.php` is the server-side wrapper for the ChangeNOW v2 API. It keeps API keys in PHP code paths, normalizes responses into predictable arrays, and maps provider failures into `ChangeNowApiException` subclasses with user-safe and admin/debug messages.

## Methods

- `_listCurrencies($filters)`
- `_listPairs($filters)`
- `_getMinAmount($request)`
- `_getRange($request)`
- `_getEstimatedAmount($request)` / `_getQuote($request)`
- `_getNetworkFee($request)`
- `_createTransaction($request)` / `_createSwap($request)`
- `_getTransactionStatus($id)` / `_getSwapStatus($id)`
- `_validateAddress($currency, $address, $network = null)`
- `_getAvailableActions($id)`
- `_continueTransaction($id)`
- `_refundTransaction($id, $address, $extraId = null)`
- `_listTransactions($filters)`

## Auth And Keys

Most endpoints send the public partner key through the `x-changenow-api-key` header. Available pairs also sends `x-api-key` for compatibility with the current Postman documentation. `_listTransactions()` uses the private API key because ChangeNOW documents the private key as required for `/v2/exchanges`.

Do not print the public or private API key into templates or JavaScript. Construct the client server-side, preferably from `App`:

```php
$Client = ChangeNowApiClient::_fromApp($App);
```

## Retries And Rate Limits

The default retry policy retries idempotent `GET` requests on transport failures and HTTP `408`, `500`, `502`, `503`, and `504`. Transaction creation is never retried automatically because ChangeNOW does not document idempotency for `POST /v2/exchange`.

HTTP `429` is mapped to `ChangeNowApiRateLimitException` without an automatic retry. The exception debug context captures `Retry-After` when ChangeNOW sends it. Current ChangeNOW partner documentation lists the default limit as 1800 requests per minute and 30 requests per second per API key.

## Debug Logging

Debug logging is off by default. When `debug` is enabled, pass a `debug_logger` callable or the client will use `error_log()`. Logs redact API keys, address fields, extra IDs, user IDs, forwarded IPs, and partner `payload` values.

```php
$Client = new ChangeNowApiClient([
  'public_api_key' => $App->_getChangeNowPublicApiKey(),
  'debug' => true,
  'debug_logger' => function($message) {
    error_log($message);
  }
]);
```

## Testing

`tests/changenow_api_client_test.php` uses a mocked transport and does not call the live ChangeNOW API. ChangeNOW does not provide a dedicated test environment, so live verification should use partner credentials and a low-fee standard-flow pair only when maintainers intentionally run a manual integration check.
