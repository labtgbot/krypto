# Composer Dependency Audit 2026-05-09

This report covers the Composer metadata hardening from issue #53 and the
legacy dependency cleanup from OPEN-07 / issue #75. Composer was installed as a
temporary verified `/tmp/composer.phar` binary for the audit and was not
committed to the repository.

## Environment

- PHP CLI: 8.3.30
- Composer: 2.10.0
- Composer platform override in `composer.json`: PHP 7.4.33
- Installer runtime requirement: PHP 7.4.0 or newer

## Validation

`composer validate --strict` passes with exit code 0:

```text
./composer.json is valid
```

Root Composer constraints are bounded, stable, and compatible with the PHP 7.4
platform override. The previous `dev-master` root exceptions were removed with
the legacy packages that required them.

## Security Audit

Before OPEN-07, `composer audit --locked --abandoned=report` reported active
advisories in the legacy dependency graph, including:

- `nesbot/carbon`
- `symfony/http-foundation`
- `symfony/polyfill-intl-idn`

It also reported abandoned legacy packages such as `aferrandini/phpqrcode` and
`guzzle/guzzle`.

After the OPEN-07 cleanup, `composer audit --locked --abandoned=report` reports
no security vulnerability advisories:

```text
No security vulnerability advisories found.
```

Composer still reports abandoned packages that are retained by active runtime
integrations:

- `facebook/graph-sdk`, used by `app/modules/kr-facebookoauth/src/FacebookOauth.php`.
- `milqmedia/poeditor-api-client`, used by `app/src/Lang/Lang.php`.
- `sonata-project/google-authenticator`, used by two-factor authentication.
- `container-interop/container-interop` and `zendframework/*`, retained
  transitively by `milqmedia/poeditor-api-client`.

Those retained abandoned packages are not part of the retired exchange,
payment, RSS, QR, or Omnipay attack surface. Replacing them should be handled as
separate feature migrations with OAuth, POEditor language sync, and 2FA coverage.

## OPEN-07 cleanup results

The active Composer root requirements were reduced from 65 Composer packages to
11 Composer packages. The lock file was rebuilt with
`composer update --no-interaction --with-all-dependencies`.

- `composer.lock` package count: 119 to 27.
- Composer update operations: 3 installs, 21 updates, 95 removals.
- The committed `vendor/` tree was refreshed to match the new lock file.
- The ChangeNOW API client remains first-party code using the PHP cURL
  extension, not a Composer HTTP SDK.

## Removed legacy Composer packages

The following root requirements were removed because they are outside the
ChangeNOW non-custodial runtime boundary, are no longer referenced by the primary
runtime paths, or existed only to support the removed legacy graph.

| Package | Removal rationale |
| --- | --- |
| `sigismund/coinpayments` | Legacy custodial/deposit payment integration, not used by the ChangeNOW swap flow. |
| `bert-w/coinpayments-api` | Legacy CoinPayments API helper, not used by the ChangeNOW swap flow. |
| `stripe/stripe-php` | Legacy hosted payment gateway SDK, outside the non-custodial ChangeNOW flow. |
| `samrap/gemini` | Legacy exchange SDK from the removed trading connector surface. |
| `react/stream` | Async socket helper retained only by legacy exchange/websocket packages. |
| `react/socket` | Async socket helper retained only by legacy exchange/websocket packages. |
| `react/promise-timer` | Async helper retained only by legacy exchange/websocket packages. |
| `react/promise` | Async helper retained only by legacy exchange/websocket packages. |
| `react/event-loop` | Async event loop retained only by legacy exchange/websocket packages. |
| `react/dns` | Async DNS helper retained only by legacy exchange/websocket packages. |
| `react/cache` | Async cache helper retained only by legacy exchange/websocket packages. |
| `ratchet/rfc6455` | Websocket protocol package retained only by legacy exchange clients. |
| `ratchet/pawl` | Websocket client package retained only by legacy exchange clients. |
| `psr/log` | Root requirement was unused after removing legacy SDKs that pulled logging adapters. |
| `php-http/promise` | HTTP abstraction from legacy payment and API wrappers. |
| `php-http/message-factory` | HTTP abstraction from legacy payment and API wrappers. |
| `php-http/message` | HTTP abstraction from legacy payment and API wrappers. |
| `php-http/httplug` | HTTP abstraction from legacy payment and API wrappers. |
| `php-http/guzzle6-adapter` | Legacy HTTPlug Guzzle 6 adapter, no longer required by active integrations. |
| `php-http/discovery` | Legacy HTTPlug discovery package, no longer required by active integrations. |
| `paypal/rest-api-sdk-php` | Legacy PayPal SDK, outside the non-custodial ChangeNOW flow. |
| `paragonie/random_compat` | PHP 5 compatibility shim, unnecessary for the PHP >=7.4 runtime. |
| `mrteye/gdax` | Legacy Coinbase/GDAX exchange SDK from the removed trading connector surface. |
| `mollie/mollie-api-php` | Legacy hosted payment gateway SDK, outside the non-custodial ChangeNOW flow. |
| `jaggedsoft/php-binance-api` | Legacy Binance exchange SDK from the removed trading connector surface. |
| `hanischit/kraken-api` | Legacy Kraken exchange SDK from the removed trading connector surface. |
| `evenement/evenement` | Event emitter package retained only by the legacy async/websocket graph. |
| `curl/curl` | Third-party cURL wrapper from retired SDKs; ChangeNOW uses first-party cURL code. |
| `coingate/coingate-php` | Legacy CoinGate payment SDK, outside the non-custodial ChangeNOW flow. |
| `coinbase/coinbase` | Legacy Coinbase wallet SDK, outside the non-custodial ChangeNOW flow. |
| `clue/stream-filter` | Stream helper retained only by the legacy async/websocket graph. |
| `ccxt/ccxt` | Legacy exchange aggregation SDK from the removed trading connector surface. |
| `guzzlehttp/guzzle` | No longer a root requirement; Guzzle remains only where active OAuth clients require it transitively. |
| `league/omnipay` | Legacy payment abstraction, outside the non-custodial ChangeNOW flow. |
| `coingate/omnipay-coingate` | Legacy Omnipay gateway, outside the non-custodial ChangeNOW flow. |
| `omnipay/stripe` | Legacy Omnipay Stripe gateway, outside the non-custodial ChangeNOW flow. |
| `omnipay/common` | Legacy Omnipay base package, removed with the gateway graph. |
| `omnipay/paypal` | Legacy Omnipay PayPal gateway, outside the non-custodial ChangeNOW flow. |
| `aferrandini/phpqrcode` | Legacy QR renderer from deposit/payment views, not used by ChangeNOW. |
| `milon/barcode` | Legacy barcode helper from deposit/payment views, not used by ChangeNOW. |
| `payer/sdk` | Legacy hosted payment gateway SDK, outside the non-custodial ChangeNOW flow. |
| `cmpayments/iban` | Legacy bank/payment validation helper, outside the non-custodial ChangeNOW flow. |
| `fadion/fixerio` | Legacy exchange-rate wrapper replaced by the retained CurrencyLayer client. |
| `php-curl-class/php-curl-class` | Legacy cURL wrapper pulled by retired API clients. |
| `infiniweb/fixer-api-php` | Legacy Fixer API wrapper replaced by the retained CurrencyLayer client. |
| `gladcodes/ravephp` | Legacy hosted payment gateway SDK, outside the non-custodial ChangeNOW flow. |
| `aleksandrzhiliaev/omnipay-advcash` | Legacy Omnipay gateway, outside the non-custodial ChangeNOW flow. |
| `codename065/coinbase-commerce` | Legacy Coinbase Commerce SDK, outside the non-custodial ChangeNOW flow. |
| `dg/rss-php` | Legacy RSS parser package; primary news/runtime paths do not require it. |
| `vinelab/rss` | Legacy RSS parser package; primary news/runtime paths do not require it. |
| `simplepie/simplepie` | Legacy RSS parser package; primary news/runtime paths do not require it. |
| `ronmelkhior/coinpayments-ipn` | Legacy CoinPayments IPN helper, outside the non-custodial ChangeNOW flow. |
| `yabacon/paystack-php` | Legacy hosted payment gateway SDK, outside the non-custodial ChangeNOW flow. |
| `ziplr/php-qr-code` | Legacy QR renderer from deposit/payment views, not used by ChangeNOW. |

Removing those root requirements also removed their transitive attack surface,
including the vulnerable `symfony/http-foundation` and `nesbot/carbon` branches.

## Retained runtime dependencies

These packages remain as root requirements because active runtime code still
references them.

| Package | Runtime usage |
| --- | --- |
| `symfony/polyfill-mbstring` | PHP 7.4-compatible mbstring polyfill support. |
| `sonata-project/google-authenticator` | Two-factor authentication in `app/src/User/User.php` and login views. |
| `phpmailer/phpmailer` | SMTP delivery in `app/src/App/App.php` and admin SMTP settings. |
| `mobiledetect/mobiledetectlib` | Device detection in `dashboard.php` and dashboard rendering. |
| `liquid/liquid` | Template rendering in user and identity flows. |
| `league/oauth2-google` | Google OAuth login integration. |
| `league/oauth2-client` | Shared OAuth client base for Google OAuth. |
| `google/recaptcha` | Signup CAPTCHA validation. |
| `facebook/graph-sdk` | Facebook OAuth login integration. |
| `oceanapplications/currencylayer-php-client` | Currency rate synchronization in `App::_syncCurrencyListRate`. |
| `milqmedia/poeditor-api-client` | POEditor language synchronization in `app/src/Lang/Lang.php`. |

## Commands

```bash
php /tmp/composer.phar validate --strict
php /tmp/composer.phar audit --locked --abandoned=report
php /tmp/composer.phar install --no-interaction
php scripts/lint_php.php
php scripts/run_tests.php
```
