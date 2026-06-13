# Audit wave 3 — verified findings (2026-06-13)

Numbering continues from SEC-23. New codes start at SEC-24.
Labels available: security, severity: critical|high|medium|low, audit-2026-06.
Milestones: Stage 1 (Critical & High security), Stage 2 (Medium hardening),
Stage 3 (Supply-chain), Stage 4 (Cleanup & robustness).

## PAYMENTS (verified by direct read)

### P-A — Mollie deposit webhook: no idempotency / replay → double-credit (HIGH, Stage 1)
- `app/modules/kr-payment/src/actions/deposit/processMollie.php:21-46`
- `app/modules/kr-payment/src/Mollie.php:126-150`
- `app/modules/kr-trade/src/Balance.php:171-200`
processMollie.php fetches the Mollie payment by `$_POST['id']`, then unconditionally
calls `$Balance->_addDeposit(...)`. `_addDeposit` is an INSERT with no dedup. Replaying
the same paid Mollie id credits the user N times. No check vs `_depositAlreadyDone`,
no pending-deposit binding. Also: when `_checkPayment` returns false (not paid) the
code still runs `new User($paymentCheck['user_id'])` (false['user_id'] = null) and
inserts a status=0 row. Mollie subscription twin (processMollie.php top-level) has the
same _addDeposit class but goes through Charges; deposit path is the exploitable one.

### P-B — Coinbase Commerce webhook broken + false CSRF compensating control (MEDIUM, Stage 1)
- `app/modules/kr-payment/src/CoinbaseCommerce.php:143-157` (`_validateRequest`)
- `app/modules/kr-payment/src/CoinbaseCommerce.php:124-141` (`_confirmTransaction`)
- `app/src/App/csrf_policy.php:60-63`
- `app/src/App/App.php:840` (only `_getCoinbaseCommerceAPIKey`, NO webhook-secret getter)
(a) Signature HMAC uses the Coinbase **API key** as the secret, but Coinbase signs
webhooks with a dedicated **shared webhook secret**; no such setting/getter exists.
=> signature never matches → legitimate webhooks rejected (fails closed; deposits
never confirmed). (b) csrf_policy.php claims "validated with the configured Coinbase
Commerce shared secret" — false; no shared secret is configured. (c) `_confirmTransaction`
calls `_changeDepositStatus($payload['event']['id'], '1')` — the **event id**, but the
stored payment_data is the **charge id** (`data->id` saved via `_updateDepositPaymentData`),
so even past the signature it would match the wrong/no row; and `_changeDepositStatus`
is unscoped (no id_user, no status precondition — see Balance.php:229-236).

### P-C — Blockonomics credits wrong amount via dead code (MEDIUM, Stage 1)
- `app/modules/kr-payment/src/Blockonomics.php:109-116`
```
public function _calcAmountPayment($PaymentDetail){
  $amount = 0;
  return $this->_convertSatoshiToStandard($PaymentDetail->vin[0]->value); // <- early return
  foreach ($PaymentDetail->vout as $key => $value) { $amount += $value->value; } // dead
  return $amount;
}
```
Returns the first **input** (vin[0], the sender's spent UTXO value) instead of the
delivered output amount. The dead foreach also (incorrectly) summed ALL vout. The
credited deposit amount (`_validPayment`, Blockonomics.php:132) is therefore wrong —
typically far larger than what was actually sent to the deposit address.

## CHANGENOW (verified by direct read)

### P-D — Fixed-rate quote cached with single-use rateId (MEDIUM, Stage 2)
- `app/modules/kr-changenow/src/ChangeNowMarketData.php:94-132` (`_getQuote`),
  `:245-249` (`_quoteCacheKey`), `:275` (rateId in cached result), `:14` (TTL=30s)
Quote results (including `rateId`) are cached for both `standard` and `fixed-rate`
flows under a key derived only from the normalized request. For `fixed-rate`,
ChangeNOW issues a single-use rateId; all requests within the 30s TTL get the SAME
rateId, so after the first `_createSwap` consumes it the rest fail with
"fixed-rate quote expired". Fix: skip cache (or shorten/disable) for fixed-rate.

### P-E — Market-data sync is non-atomic → market can go fully dark (MEDIUM, Stage 2)
- `app/modules/kr-changenow/src/ChangeNowMarketRepository.php:30` & `:42`
- `app/modules/kr-changenow/src/ChangeNowMarketData.php:62-65`
`_replaceAssets`/`_replacePairs` first `UPDATE ... SET provider_active=0` for ALL rows,
then loop-upsert with NO transaction. A mid-loop failure leaves every asset/pair
deactivated → no swap pairs available. Fix: wrap in a transaction or stage into a temp
set and swap atomically.

### P-F — Refund path skips local address validation (LOW, Stage 4)
- `app/modules/kr-changenow/src/ChangeNowPublicSwapFlow.php:259-268` (refund),
  vs create path `:164` which calls `_validateAddress`. Support twin `:335-344` same gap.
Refund address is only trimmed/non-empty checked, not validated via `_validateAddress`.
Inconsistent with create; a wrong-network refund address can be submitted to provider.

### P-G — `destinations` action exempt from rate limiting (LOW, Stage 2)
- `app/modules/kr-changenow/src/ChangeNowPublicRateLimit.php:16-24` returns null for
  `destinations`; `publicSwap.php:276-281` dispatches it with default-allow.
(Note: status/refund/continue are NOW bucketed — that was SEC-20/#129, do not re-report.)

### P-H — `_checkReferalSource($_POST)` ignores its argument (LOW, Stage 4)
- `app/src/App/App.php:2094-2103` (signature takes no args, reads only `$_GET['ref']`)
- called as `$App->_checkReferalSource($_POST)` at `publicSwap.php:240`
POST-only referral attribution is silently dropped on the public swap endpoint.

## CORRECTNESS (verified by direct read)

### P-I — Blockfolio profit computed on a thousands-formatted string (MEDIUM, Stage 4)
- `app/modules/kr-blockfolio/views/blockfolio.php:156-157`
`$holdingSize = $App->_formatNumber($holdingSize, $DecimalShown)` (number_format with a
thousands separator, App.php:2082-2088) is then multiplied by price at line 157.
For any holding >= 1000, PHP casts e.g. "1,234.56" to float 1.0 → profit display
collapses to ~price×1. Only the `_hiddenThirdpartyActive` branch; the else branch
(line 159) correctly uses the raw numeric size. Display-only (no funds moved). Fix:
keep raw numeric for arithmetic, format only for output. The `kr-holding-size`
attribute (line 163) also emits the formatted string.

### P-J — Histo cache dedup key never matches → duplicate rows accumulate forever (MEDIUM, Stage 4)
- `app/src/CryptoApi/CryptoCoin.php:359-404`
Read-cache SELECT (:359) and INSERT/UPDATE (:386/:397) use `type_histo = $type.'/'.market`,
but the "row already exists?" dedup SELECT (:378) uses bare `type_histo = $type`. The
stored rows are always `$type/$market`, so the dedup SELECT returns 0 every time → the
INSERT branch runs every minute → `histo_krypto` grows by one duplicate row per
coin/currency/type/minute instead of updating the existing row. Fix: use the same
`$type.'/'.market` key at :378.

### P-K — Inverted/invalid usort comparator on histo time sort (LOW, Stage 4)
- `app/src/CryptoApi/CryptoCoin.php:416-420`
Equal times return -1 (should be 0) and `a < b` returns 0 (should be -1): the ascending
time sort of the price history feeding the graph is incorrect. Fix: `return $a['time'] <=> $b['time'];`.

### DROPPED as false positives (verified):
- Charges::_checkPaymentResult "5-second window" — redirect URLs set `t=time()` (paypal,
  processPaypal.php:60) or `t=time()+100000` (Mollie), so `time()-t > 5` does NOT reject
  real returns; it is a freshness/replay guard. NOT a bug.
- Balance::_validateDeposit "positional mixup" — recount: args are
  (amount, type, desc, 'USD'=currency, json, status, 'USD'=wallet_target, $keycharge=payment_reference).
  `$keycharge` IS correctly the payment_reference; wallet_target is the literal 'USD'. NOT a bug.
- ChargesPlan::_getDiscountPercentage recursion / per-month cents — discount math cancels
  out correctly; speculative. SKIP.
- rtrim-in-description (Balance.php:190) and low/high % out-of-band (CryptoCoin.php:331) —
  cosmetic display robustness only. Mention as informational, do not file.

## SQLi: none confirmed (exhaustive trace of concatenation sinks). Hardening only.

## PENDING from subagents:
- auth/admin/manager/chat access-control audit (still running)
