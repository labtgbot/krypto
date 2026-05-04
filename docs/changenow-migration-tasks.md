# ChangeNOW Migration Task Plan

Planning date: 2026-05-04

Related issue: https://github.com/labtgbot/krypto/issues/3

## Goal

Reposition Krypto as an open ChangeNOW-powered cryptocurrency swap application. The migration should remove direct user exchange and wallet-provider connections from the product experience, replace those flows with one ChangeNOW provider, make registration optional for normal use, and keep accounts only for advanced settings, history, admin workflows, and referral features.

This is intentionally split into separate implementation tasks. The current repository is a legacy PHP/MySQL monolith with exchange, payment, wallet, user, admin, and frontend behavior tightly coupled across modules. Shipping the whole migration as one patch would be unsafe.

## Current Surfaces To Replace

Primary code areas:

- `app/modules/kr-trade/src/Trade.php` lists and instantiates exchange connectors.
- `app/modules/kr-trade/src/0Exchange.php` implements CCXT-style balance, order, ticker, and order-history behavior.
- `app/modules/kr-trade/src/*.php` contains one class per legacy exchange connector.
- `app/modules/kr-trade/views/connectThirdparty.php`, `app/modules/kr-user/views/exchanges.php`, and related JavaScript expose user exchange connection UX.
- `app/modules/kr-trade/src/actions/*` handles deposits, withdrawals, balance lists, provider selection, orders, third-party setup, and transaction history.
- `app/modules/kr-payment/src/*` and `app/modules/kr-payment/views/*` contain fiat, crypto, and wallet/deposit provider integrations.
- `app/modules/kr-admin/views/trading.php`, `app/modules/kr-admin/views/payment.php`, and admin save actions expose provider configuration.
- `install/assets/sql/krypto.sql` seeds exchange, wallet, payment, referral, settings, order, and balance tables.
- `app/views/login/*` and `app/modules/kr-user/src/actions/signup.php` enforce the current account-first flow.

Important non-goals unless product explicitly expands scope:

- Do not remove unrelated operational integrations such as SMTP, OAuth, CAPTCHA, POEditor, RSS, and language services.
- Do not replace market charts or CryptoCompare data in the same task as ChangeNOW swap integration unless a later task explicitly owns market-data migration.
- Do not store user private keys or custody funds inside Krypto.

## ChangeNOW Integration Facts

References checked on 2026-05-04:

- ChangeNOW API overview: https://changenow.io/api
- Postman API documentation: https://documenter.getpostman.com/view/8180765/SVfTPnM8?version=latest
- ChangeNOW widget: https://changenow.io/widget
- Integration guide: https://support.changenow.io/hc/en-us/articles/20066260886556-ChangeNOW-Integration-guide
- API setup and customization: https://support.changenow.io/hc/en-us/articles/22686553746204-Integration-API-setup-and-customization

Implementation assumptions from those references:

- Partner setup provides a public API key through the ChangeNOW business/partner account.
- Some account or reporting APIs may require a private API key; keep all API keys server-side unless ChangeNOW-generated widget code explicitly requires public embed parameters.
- Special partner fields such as `userId` and `payload` may require ChangeNOW enablement and should be planned for referral and attribution.
- API rate limits are documented as 1800 calls per minute and 30 calls per second per API key.
- ChangeNOW does not provide a dedicated test environment; low-fee live pairs such as XRP-XLM are suggested for standard-flow testing.
- Standard and fixed-rate flows both exist. The UI should display timing and expiry values returned by the API instead of hard-coding a fixed deposit window.
- The widget can be embedded with an iframe and a `stepper-connector.js` script and supports customization such as colors, default currencies, language, logo, and fiat options.

Common API endpoints to design around:

- `GET /v2/exchange/currencies`
- `GET /v2/exchange/available-pairs`
- `GET /v2/exchange/min-amount`
- `GET /v2/exchange/range`
- `GET /v2/exchange/estimated-amount`
- `POST /v2/exchange`
- `GET /v2/exchange/by-id`
- `GET /v2/validate/address`
- `GET /v2/exchange/network-fee`
- `GET /v2/exchanges`
- `POST /v2/exchange/continue`
- `POST /v2/exchange/refund`

## Delivery Sequence

Recommended order:

1. Add the provider boundary and ChangeNOW client behind feature flags.
2. Build ChangeNOW asset, quote, and transaction primitives with mocked tests.
3. Add the public no-registration swap flow.
4. Add optional account history, advanced settings, referrals, and admin controls.
5. Decommission legacy exchange and wallet connection UX after feature parity is confirmed.
6. Complete data migration, installer defaults, observability, compliance guardrails, and release validation.

## Created Implementation Issues

The detailed implementation issues were created on 2026-05-04 from this task plan. Each issue includes objective, current code to review, scope, acceptance criteria, verification, dependency links, and relevant ChangeNOW documentation links.

| Code | Issue | Depends On | Blocks |
| --- | --- | --- | --- |
| CN-01 | [#5 Define the ChangeNOW provider boundary and product modes](https://github.com/labtgbot/krypto/issues/5) | Parent [#3](https://github.com/labtgbot/krypto/issues/3) | [#6](https://github.com/labtgbot/krypto/issues/6), [#7](https://github.com/labtgbot/krypto/issues/7), [#8](https://github.com/labtgbot/krypto/issues/8), [#9](https://github.com/labtgbot/krypto/issues/9), [#12](https://github.com/labtgbot/krypto/issues/12), [#13](https://github.com/labtgbot/krypto/issues/13), [#15](https://github.com/labtgbot/krypto/issues/15), [#16](https://github.com/labtgbot/krypto/issues/16), [#18](https://github.com/labtgbot/krypto/issues/18), [#19](https://github.com/labtgbot/krypto/issues/19) |
| CN-02 | [#6 Add secure ChangeNOW provider settings](https://github.com/labtgbot/krypto/issues/6) | [#5](https://github.com/labtgbot/krypto/issues/5) | [#7](https://github.com/labtgbot/krypto/issues/7), [#11](https://github.com/labtgbot/krypto/issues/11), [#15](https://github.com/labtgbot/krypto/issues/15), [#16](https://github.com/labtgbot/krypto/issues/16), [#18](https://github.com/labtgbot/krypto/issues/18) |
| CN-03 | [#7 Implement a server-side ChangeNOW API client](https://github.com/labtgbot/krypto/issues/7) | [#5](https://github.com/labtgbot/krypto/issues/5), [#6](https://github.com/labtgbot/krypto/issues/6) | [#8](https://github.com/labtgbot/krypto/issues/8), [#9](https://github.com/labtgbot/krypto/issues/9), [#10](https://github.com/labtgbot/krypto/issues/10), [#14](https://github.com/labtgbot/krypto/issues/14), [#18](https://github.com/labtgbot/krypto/issues/18), [#19](https://github.com/labtgbot/krypto/issues/19) |
| CN-04 | [#8 Sync ChangeNOW assets, networks, pairs, limits, and quote data](https://github.com/labtgbot/krypto/issues/8) | [#7](https://github.com/labtgbot/krypto/issues/7), [#16](https://github.com/labtgbot/krypto/issues/16) | [#9](https://github.com/labtgbot/krypto/issues/9), [#17](https://github.com/labtgbot/krypto/issues/17) |
| CN-05 | [#9 Build the public ChangeNOW swap flow without mandatory registration](https://github.com/labtgbot/krypto/issues/9) | [#5](https://github.com/labtgbot/krypto/issues/5), [#7](https://github.com/labtgbot/krypto/issues/7), [#8](https://github.com/labtgbot/krypto/issues/8), [#16](https://github.com/labtgbot/krypto/issues/16) | [#10](https://github.com/labtgbot/krypto/issues/10), [#12](https://github.com/labtgbot/krypto/issues/12), [#13](https://github.com/labtgbot/krypto/issues/13), [#14](https://github.com/labtgbot/krypto/issues/14), [#17](https://github.com/labtgbot/krypto/issues/17), [#18](https://github.com/labtgbot/krypto/issues/18), [#19](https://github.com/labtgbot/krypto/issues/19) |
| CN-06 | [#10 Implement ChangeNOW transaction status, history, refund, and continue actions](https://github.com/labtgbot/krypto/issues/10) | [#7](https://github.com/labtgbot/krypto/issues/7), [#9](https://github.com/labtgbot/krypto/issues/9), [#16](https://github.com/labtgbot/krypto/issues/16) | [#12](https://github.com/labtgbot/krypto/issues/12), [#14](https://github.com/labtgbot/krypto/issues/14), [#15](https://github.com/labtgbot/krypto/issues/15), [#18](https://github.com/labtgbot/krypto/issues/18), [#19](https://github.com/labtgbot/krypto/issues/19) |
| CN-07 | [#11 Add configurable ChangeNOW widget integration](https://github.com/labtgbot/krypto/issues/11) | [#6](https://github.com/labtgbot/krypto/issues/6) | [#15](https://github.com/labtgbot/krypto/issues/15), [#17](https://github.com/labtgbot/krypto/issues/17) |
| CN-08 | [#12 Remove direct exchange and wallet connection UX from the user product](https://github.com/labtgbot/krypto/issues/12) | [#9](https://github.com/labtgbot/krypto/issues/9), [#10](https://github.com/labtgbot/krypto/issues/10), [#13](https://github.com/labtgbot/krypto/issues/13), [#15](https://github.com/labtgbot/krypto/issues/15), [#16](https://github.com/labtgbot/krypto/issues/16) | [#17](https://github.com/labtgbot/krypto/issues/17), [#19](https://github.com/labtgbot/krypto/issues/19) |
| CN-09 | [#13 Make registration optional and reserve accounts for advanced settings](https://github.com/labtgbot/krypto/issues/13) | [#9](https://github.com/labtgbot/krypto/issues/9) | [#12](https://github.com/labtgbot/krypto/issues/12), [#14](https://github.com/labtgbot/krypto/issues/14), [#17](https://github.com/labtgbot/krypto/issues/17), [#19](https://github.com/labtgbot/krypto/issues/19) |
| CN-10 | [#14 Integrate ChangeNOW referral attribution and Krypto referral dashboards](https://github.com/labtgbot/krypto/issues/14) | [#9](https://github.com/labtgbot/krypto/issues/9), [#10](https://github.com/labtgbot/krypto/issues/10), [#13](https://github.com/labtgbot/krypto/issues/13), [#16](https://github.com/labtgbot/krypto/issues/16) | [#15](https://github.com/labtgbot/krypto/issues/15), [#18](https://github.com/labtgbot/krypto/issues/18), [#19](https://github.com/labtgbot/krypto/issues/19) |
| CN-11 | [#15 Build the ChangeNOW admin panel](https://github.com/labtgbot/krypto/issues/15) | [#6](https://github.com/labtgbot/krypto/issues/6), [#7](https://github.com/labtgbot/krypto/issues/7), [#10](https://github.com/labtgbot/krypto/issues/10), [#11](https://github.com/labtgbot/krypto/issues/11), [#14](https://github.com/labtgbot/krypto/issues/14), [#16](https://github.com/labtgbot/krypto/issues/16) | [#12](https://github.com/labtgbot/krypto/issues/12), [#17](https://github.com/labtgbot/krypto/issues/17), [#18](https://github.com/labtgbot/krypto/issues/18), [#19](https://github.com/labtgbot/krypto/issues/19) |
| CN-12 | [#16 Add ChangeNOW schema migrations and installer defaults](https://github.com/labtgbot/krypto/issues/16) | [#5](https://github.com/labtgbot/krypto/issues/5), [#6](https://github.com/labtgbot/krypto/issues/6) | [#8](https://github.com/labtgbot/krypto/issues/8), [#9](https://github.com/labtgbot/krypto/issues/9), [#10](https://github.com/labtgbot/krypto/issues/10), [#12](https://github.com/labtgbot/krypto/issues/12), [#14](https://github.com/labtgbot/krypto/issues/14), [#15](https://github.com/labtgbot/krypto/issues/15), [#18](https://github.com/labtgbot/krypto/issues/18), [#19](https://github.com/labtgbot/krypto/issues/19) |
| CN-13 | [#17 Redesign public and dashboard interfaces around ChangeNOW swaps](https://github.com/labtgbot/krypto/issues/17) | [#8](https://github.com/labtgbot/krypto/issues/8), [#9](https://github.com/labtgbot/krypto/issues/9), [#11](https://github.com/labtgbot/krypto/issues/11), [#12](https://github.com/labtgbot/krypto/issues/12), [#13](https://github.com/labtgbot/krypto/issues/13), [#15](https://github.com/labtgbot/krypto/issues/15) | [#19](https://github.com/labtgbot/krypto/issues/19) |
| CN-14 | [#18 Add ChangeNOW security, compliance, and observability guardrails](https://github.com/labtgbot/krypto/issues/18) | [#7](https://github.com/labtgbot/krypto/issues/7), [#9](https://github.com/labtgbot/krypto/issues/9), [#10](https://github.com/labtgbot/krypto/issues/10), [#14](https://github.com/labtgbot/krypto/issues/14), [#15](https://github.com/labtgbot/krypto/issues/15), [#16](https://github.com/labtgbot/krypto/issues/16) | [#19](https://github.com/labtgbot/krypto/issues/19) |
| CN-15 | [#19 Add tests and release checks for the ChangeNOW migration](https://github.com/labtgbot/krypto/issues/19) | [#5](https://github.com/labtgbot/krypto/issues/5), [#7](https://github.com/labtgbot/krypto/issues/7), [#9](https://github.com/labtgbot/krypto/issues/9), [#10](https://github.com/labtgbot/krypto/issues/10), [#12](https://github.com/labtgbot/krypto/issues/12), [#13](https://github.com/labtgbot/krypto/issues/13), [#14](https://github.com/labtgbot/krypto/issues/14), [#15](https://github.com/labtgbot/krypto/issues/15), [#16](https://github.com/labtgbot/krypto/issues/16), [#17](https://github.com/labtgbot/krypto/issues/17), [#18](https://github.com/labtgbot/krypto/issues/18) | None |

## Task CN-01: Define Provider Boundary And Product Modes

Suggested issue title: Define the ChangeNOW provider boundary and product modes

Objective:

Create a small provider abstraction so the application can route swap operations through ChangeNOW without continuing to instantiate many direct exchange classes in the user flow.

Current code to review:

- `app/modules/kr-trade/src/Trade.php`
- `app/modules/kr-trade/src/0Exchange.php`
- `app/modules/kr-trade/src/Balance.php`
- `app/modules/kr-trade/src/actions/placeTrade.php`
- `app/modules/kr-trade/src/actions/depositBalance.php`
- `app/modules/kr-trade/src/actions/transactionsHistory.php`

Implementation scope:

- Add an architecture decision document under `docs/` that defines the new product modes: public swap, optional account history, admin operations, and legacy-disabled mode.
- Define a PHP provider interface for quoting, creating swaps, checking status, validating addresses, and listing currencies/pairs.
- Decide where provider classes live, for example `app/modules/kr-changenow/src/` or `app/src/Providers/ChangeNow/`.
- Add a feature flag such as `changenow_provider_enabled` and a legacy compatibility flag such as `legacy_exchange_connections_enabled`.
- Ensure new code does not require a logged-in `User` object for public quote and swap creation.

Acceptance criteria:

- A provider boundary exists and can be used without loading CCXT exchange classes.
- Public swap operations can be represented without a registered user.
- Existing legacy trading code remains callable while the feature flag is off.
- The migration plan clearly states which old flows are retained only for rollback or data access.

Verification:

- Unit tests or small executable checks prove the provider interface can be loaded with Composer autoload/bootstrap.
- PHP syntax validation covers all touched first-party PHP files.
- Manual review confirms no API secret is added to client-rendered templates.

## Task CN-02: Add ChangeNOW Settings And Secure Configuration

Suggested issue title: Add secure ChangeNOW provider settings

Objective:

Store ChangeNOW provider configuration in the existing settings system while keeping credentials encrypted and usable by admin screens, background jobs, and server-side API calls.

Current code to review:

- `app/src/App/App.php`
- `app/modules/kr-admin/views/generalsettings.php`
- `app/modules/kr-admin/views/trading.php`
- `app/modules/kr-admin/views/payment.php`
- `app/modules/kr-admin/src/actions/saveGeneralsettings.php`
- `app/modules/kr-admin/src/actions/saveTrading.php`
- `app/modules/kr-admin/src/actions/savePayment.php`
- `install/assets/sql/krypto.sql`

Implementation scope:

- Add settings for public API key, private API key, referral link or widget link ID, default flow, enabled flows, default from/to assets, default networks, webhook/callback secret if needed, support email, and rate-limit controls.
- Mark API keys and callback secrets as encrypted settings through the existing `encrypted_settings` pattern.
- Add admin inputs that mask existing secrets with the current `*********************` convention.
- Add installer seed values with the provider disabled by default until configured.
- Document how admins obtain ChangeNOW credentials from the business/partner account.

Acceptance criteria:

- Admins can save and update ChangeNOW settings without exposing secrets.
- Empty required settings prevent live swap creation and show a clear admin-facing validation error.
- The provider can run in a disabled state without breaking the dashboard or public pages.
- Existing unrelated payment settings continue to load.

Verification:

- Unit or action-level tests for save behavior, masking behavior, and encrypted field preservation.
- PHP syntax validation of touched files.
- Manual admin form review in a browser when a runnable local environment is available.

## Task CN-03: Implement The ChangeNOW API Client

Suggested issue title: Implement a server-side ChangeNOW API client

Objective:

Create a server-side PHP client for ChangeNOW v2 endpoints used by Krypto. This client should isolate HTTP behavior, response parsing, retries, timeout handling, and error mapping from the rest of the app.

Current code to review:

- `app/src/CryptoApi/CryptoApi.php` for existing external HTTP/cache patterns.
- `app/modules/kr-payment/src/*` for provider API examples.
- `composer.json` for available HTTP dependencies such as Guzzle and php-curl-class.

Implementation scope:

- Add a ChangeNOW client class with methods for currencies, pairs, min amount, range, quote, network fee, create transaction, status lookup, address validation, refund, continue, and transaction list where supported.
- Use structured request/response arrays or small DTO classes instead of scattering raw API payloads.
- Add configurable timeout and retry behavior for transient network errors, but do not retry transaction creation unless idempotency is explicitly supported.
- Normalize ChangeNOW errors into app-level exceptions with user-safe messages and admin/debug details.
- Add optional request/response debug logging that is off by default and redacts keys, addresses where needed, and payload values that may contain user data.

Acceptance criteria:

- Client methods work against mocked responses for standard and fixed-rate flows.
- All client errors are mapped to predictable exception types.
- API keys are sent only by server-side code.
- Rate-limit handling is explicit and documented.

Verification:

- Mocked unit tests for successful quotes, create transaction, status, validation errors, rate limit errors, and malformed responses.
- Static grep confirms no API key setting is printed into HTML or JavaScript.
- PHP syntax validation of touched files.

## Task CN-04: Sync ChangeNOW Assets, Networks, Pairs, And Quotes

Suggested issue title: Sync ChangeNOW assets, networks, pairs, limits, and quote data

Objective:

Replace legacy exchange market lists with ChangeNOW-supported currencies, networks, pairs, and limits for swap screens.

Current code to review:

- `app/src/CryptoApi/actions/SyncExchanges.php`
- `app/src/CryptoApi/CryptoApi.php`
- `app/modules/kr-trade/src/Trade.php::_syncListCrypto`
- `install/assets/sql/krypto.sql` tables `exchanges_krypto`, `thirdparty_crypto_krypto`, and `coinlist_krypto`
- Dashboard and coin selectors that assume `MARKET:SYMBOL/CURRENCY`

Implementation scope:

- Add tables or cache records for ChangeNOW currencies, networks, active flags, pair availability, min/max ranges, and icon URLs.
- Add a cron/action endpoint to refresh active currencies and pairs without blocking user requests.
- Preserve enough metadata to show network-specific assets such as USDT on different chains.
- Update quote screens to use ChangeNOW min amount, range, estimate, and network fee endpoints.
- Keep a short-lived quote cache to reduce API calls while respecting rate limits and freshness.

Acceptance criteria:

- The UI can list available ChangeNOW source and destination assets without loading exchange connectors.
- Network-specific assets are selectable and validated.
- Quote results include amount, estimated receive amount, min/max constraints, flow, network fee where applicable, and expiry/rate ID where provided.
- Admins can disable specific assets or flows locally without editing code.

Verification:

- Tests for currency/pair normalization and cache expiry.
- A CLI or cron smoke test using mocked HTTP responses.
- Database migration test against a fresh schema dump.

## Task CN-05: Build Public No-Registration Swap Flow

Suggested issue title: Build the public ChangeNOW swap flow without mandatory registration

Objective:

Allow visitors to create a normal ChangeNOW swap without signing up. Registration should be offered only for optional benefits such as saved settings, history, alerts, referrals, and admin/manager access.

Current code to review:

- `index.php`
- `dashboard.php`
- `app/views/login/login.php`
- `app/views/login/signup.php`
- `app/modules/kr-user/src/actions/signup.php`
- `app/modules/kr-dashboard/views/dashboard.php`
- `app/modules/kr-coin/views/coin.php`

Implementation scope:

- Add a public swap page or make the first screen a swap interface instead of a login gate.
- Collect only fields required for a ChangeNOW transaction: source asset/network, destination asset/network, amount, destination address, optional refund address, and required extra IDs/memos.
- Support address validation before transaction creation.
- Show generated pay-in address, memo/tag, amount, status, and support instructions after creation.
- Store anonymous transaction state using a secure lookup token or session key, not a mandatory account.
- Offer account creation after transaction creation for history and advanced settings.

Acceptance criteria:

- A visitor can complete quote and transaction creation without being logged in.
- A logged-in user can use the same flow and have the transaction linked to their account.
- Required memos/tags are clearly captured and displayed.
- The flow handles validation failure, expired quote, unsupported pair, and provider outage states.

Verification:

- Browser tests for anonymous quote, address validation error, transaction creation with mocked provider, and post-creation status page.
- Manual responsive checks for desktop and mobile.
- Accessibility checks for form labels, error states, and focus order.

## Task CN-06: Implement Transaction Lifecycle And Support Actions

Suggested issue title: Implement ChangeNOW transaction status, history, refund, and continue actions

Objective:

Track ChangeNOW transaction status and expose user/admin support actions without recreating the old custodial wallet model.

Current code to review:

- `app/modules/kr-trade/src/actions/transactionsHistory.php`
- `app/modules/kr-trade/views/transactionsHistory.php`
- `app/modules/kr-manager/views/orders.php`
- `app/modules/kr-manager/views/withdraw.php`
- `app/modules/kr-admin/views/withdraw.php`
- `app/modules/kr-admin/src/actions/cancelWithdraw.php`
- `app/modules/kr-admin/src/actions/doneWithdraw.php`

Implementation scope:

- Add a `changenow_transactions` table or equivalent records for provider transaction ID, flow, pair, networks, amount, expected amount, pay-in address, payout address fingerprint, status, anonymous lookup token, user ID, referral attribution, timestamps, and raw provider status snapshot.
- Poll or fetch status with `GET /v2/exchange/by-id`.
- Add support for refund and continue actions when ChangeNOW reports them as available.
- Add admin and manager screens for transaction lookup, status, support notes, and action audit trail.
- Add user-facing status page that does not expose other users' transactions.

Acceptance criteria:

- Transactions are persisted after creation and can be restored by anonymous token or logged-in user history.
- Status updates are idempotent.
- Refund/continue actions are available only when the provider marks them available and the acting user has permission.
- Admin actions are audited.

Verification:

- Tests for anonymous lookup authorization, status transitions, duplicate polling, refund/continue permissions, and redaction.
- Manual status page smoke test with mocked provider data.

## Task CN-07: Add ChangeNOW Widget Integration

Suggested issue title: Add configurable ChangeNOW widget integration

Objective:

Provide a widget-based integration path for pages where full custom API flow is not required, while preserving partner attribution and admin customization.

Current code to review:

- `app/modules/kr-dashboard/views/custompage.php`
- `app/modules/kr-admin/views/generalsettings.php`
- `app/modules/kr-admin/views/intro.php`
- Global CSS under `assets/css/`

Implementation scope:

- Add a server-rendered widget component using the ChangeNOW iframe and `stepper-connector.js` embed pattern.
- Make widget options configurable: default amount, default from/to assets, fiat mode, language, dark mode, logo visibility, FAQ, primary/background colors, orientation, and link ID.
- Sanitize all widget parameters before rendering the iframe URL.
- Add placement options for landing page, dashboard panel, coin page, or custom page.
- Add a fallback message and direct ChangeNOW/referral link when iframe loading fails.

Acceptance criteria:

- Admins can configure and preview widget parameters.
- The rendered iframe includes only sanitized, expected query parameters.
- The widget is responsive and does not overlap existing dashboard layout.
- Referral/link attribution is preserved according to ChangeNOW requirements.

Verification:

- URL builder unit tests for sanitization and default values.
- Browser screenshot checks for configured widget placements.
- Manual iframe load test in a local browser when network is available.

## Task CN-08: Remove Legacy Exchange And Wallet Connection UX

Suggested issue title: Remove direct exchange and wallet connection UX from the user product

Objective:

Remove or hide direct exchange account setup, API key forms, and old wallet-provider connection flows from the user experience after ChangeNOW flow is ready.

Current code to review:

- `app/modules/kr-user/views/exchanges.php`
- `app/modules/kr-trade/views/connectThirdparty.php`
- `app/modules/kr-trade/src/actions/saveThirdpartySettings.php`
- `app/modules/kr-trade/src/actions/removeThirdparty.php`
- `app/modules/kr-trade/src/actions/changeMainThirdparty.php`
- `app/modules/kr-admin/views/trading.php`
- `assets/img/icons/trade/`
- Exchange credential tables in `install/assets/sql/krypto.sql`

Implementation scope:

- Remove exchange setup from user account navigation.
- Hide or remove admin controls that ask for Binance, Kraken, Coinbase Pro/GDAX, and other exchange credentials.
- Replace trading provider lists with ChangeNOW provider status.
- Keep legacy code isolated only for data migration or rollback until a later cleanup task removes files.
- Remove routes/actions from menus and JavaScript entry points to prevent accidental access.

Acceptance criteria:

- Users are never asked to connect exchange API keys to use the app.
- Admins configure only ChangeNOW for swap provider behavior.
- Direct requests to legacy setup endpoints fail closed or require an explicit legacy feature flag.
- Existing historical data remains readable until migration is complete.

Verification:

- Browser tests confirm user account exchange setup is absent.
- Route/action tests confirm legacy setup endpoints are disabled when the flag is off.
- Grep-based check for old provider labels in user-facing views.

## Task CN-09: Make Registration Optional And Preserve Advanced Accounts

Suggested issue title: Make registration optional and reserve accounts for advanced settings

Objective:

Change the product from account-first to open-first. Users should register only when they want advanced settings, persistent history, saved preferences, alerts, referral dashboards, or admin roles.

Current code to review:

- `index.php`
- `dashboard.php`
- `app/src/App/App.php::_allowSignup`
- `app/modules/kr-user/src/actions/signup.php`
- `app/modules/kr-user/views/account.php`
- `app/modules/kr-user/views/profile.php`
- `app/modules/kr-user/views/security.php`
- `app/modules/kr-identity/*`
- `install/assets/sql/krypto.sql` settings `allow_signup`, `user_activation_require`, and identity gates

Implementation scope:

- Add public routes for quote, swap, and status that do not require login.
- Keep signup enabled as an optional conversion step.
- Move advanced settings behind login: saved default wallets, notification preferences, transaction history, referrals, identity where legally required, and admin/manager features.
- Update copy and redirects so unauthenticated users are not blocked from the swap flow.
- Ensure account-only features still reject anonymous users.

Acceptance criteria:

- Fresh visitors can reach the swap interface directly.
- Signup is suggested after value is demonstrated, not required before the first swap.
- Existing admin and manager access controls remain strict.
- Identity gates are applied only to features that still require them.

Verification:

- Browser tests for anonymous public access, optional signup, and protected account/admin pages.
- Regression tests for `_isLogged`, admin checks, and signup validation.

## Task CN-10: Integrate Referral And Viral Growth Mechanics

Suggested issue title: Integrate ChangeNOW referral attribution and Krypto referral dashboards

Objective:

Use ChangeNOW partner attribution and the existing Krypto referral tables to support viral distribution without requiring every swapper to register.

Current code to review:

- `app/src/App/App.php::_saveReferal`
- `app/modules/kr-admin/views/trading.php` referral settings
- `install/assets/sql/krypto.sql` tables `referal_krypto` and `referal_histo_krypto`
- Any existing referral link/cookie handling

Implementation scope:

- Define attribution sources: ChangeNOW link ID/referral link, internal referral code, UTM parameters, and optional logged-in user ID.
- Store referral attribution on anonymous transaction records.
- Use ChangeNOW `userId` or `payload` fields if enabled for partner attribution, internal campaign IDs, and community segments.
- Add public referral links that land directly on the swap page.
- Add account dashboard for referrers showing referred swaps, status, expected commission state, and terms.
- Add admin reports for referral traffic and conversion.

Acceptance criteria:

- Anonymous and logged-in swaps can be attributed to a referral code.
- Referral attribution survives quote to transaction creation.
- Admins can distinguish ChangeNOW partner attribution from internal referral rewards.
- The implementation does not promise payout until provider/admin confirmation exists.

Verification:

- Tests for referral cookie/session capture, transaction attribution, logged-in override rules, and duplicate referral prevention.
- Manual tests for referral link landing and post-swap signup.

## Task CN-11: Build ChangeNOW Admin Panel

Suggested issue title: Build the ChangeNOW admin panel

Objective:

Give admins a single panel for ChangeNOW setup, provider health, widget configuration, swap policies, and operational support.

Current code to review:

- `app/modules/kr-admin/src/Admin.php`
- `app/modules/kr-admin/views/*`
- `app/modules/kr-admin/statics/js/admin.js`
- `app/modules/kr-manager/views/*`

Implementation scope:

- Add a ChangeNOW section to the admin navigation.
- Show configuration status, API key presence, enabled flows, last successful sync, rate-limit warning state, and provider health.
- Provide controls for allowed flows, enabled assets/networks, local disabled pairs, default pairs, widget settings, referral settings, support copy, and debug logging.
- Provide transaction search by provider ID, internal ID, user email, anonymous token fragment, status, date range, asset, and referral code.
- Add admin-only support actions for refund/continue when available.

Acceptance criteria:

- Admins can configure ChangeNOW without editing PHP or SQL directly.
- The panel clearly distinguishes missing config, provider outage, and local disabled state.
- Transaction support views expose enough information to help users without leaking secrets or unrelated user data.
- All admin actions require existing admin permissions.

Verification:

- Browser tests for admin form validation and permission checks.
- Unit tests for setting persistence and transaction search filters.
- Manual smoke test of the admin navigation.

## Task CN-12: Migrate Schema, Installer Defaults, And Legacy Data

Suggested issue title: Add ChangeNOW schema migrations and installer defaults

Objective:

Prepare database schema and installation defaults for new ChangeNOW behavior while keeping old installations upgradeable.

Current code to review:

- `install/assets/sql/krypto.sql`
- `install/app/src/Install.php`
- Any current ad hoc install/update paths
- Tables for exchange credentials, third-party crypto, balances, orders, deposit history, withdrawal history, and referrals

Implementation scope:

- Add new tables for ChangeNOW currencies, pairs/cache, transactions, transaction events, referral attribution, and provider sync status.
- Add new settings with safe disabled defaults.
- Decide which legacy tables are retained, deprecated, archived, or migrated.
- Add an upgrade script or documented SQL migration for existing installations.
- Add indexes for provider transaction ID, user ID, anonymous lookup token, status, referral code, and timestamps.

Acceptance criteria:

- A fresh install includes all ChangeNOW tables and disabled-by-default settings.
- Existing installs can apply the migration without dropping historical data.
- Legacy exchange credential tables are not used by the new flow.
- Rollback considerations are documented.

Verification:

- Apply schema to a clean MySQL/MariaDB database.
- Apply migration to a copy of the current schema.
- Tests or scripts verify required tables, indexes, and seed settings exist.

## Task CN-13: Redesign User Interfaces Around ChangeNOW

Suggested issue title: Redesign public and dashboard interfaces around ChangeNOW swaps

Objective:

Update the visual and interaction model from a trading-terminal/dashboard product to a swap-first ChangeNOW application.

Current code to review:

- `index.php`
- `dashboard.php`
- `assets/css/style.css`
- `assets/css/dashboard.css`
- `assets/css/responsive-*.css`
- `app/modules/kr-dashboard/views/dashboard.php`
- `app/modules/kr-coin/views/coin.php`
- `app/modules/kr-trade/views/*`

Implementation scope:

- Design a public swap screen as the first viewport.
- Add transaction status, transaction history, and optional signup/account surfaces.
- Remove visual prominence of exchange API setup, native trading, order books, and direct wallet management from default navigation.
- Keep market information only where it supports swap decisions.
- Update responsive behavior so the swap flow works on mobile portrait, not only landscape dashboard assumptions.
- Prepare before/after screenshots for PR review when implementation starts.

Acceptance criteria:

- The first screen clearly supports a ChangeNOW swap.
- No mandatory login or exchange setup prompt blocks the primary flow.
- Mobile users can complete quote and transaction steps.
- UI text matches the new product model and avoids legacy exchange terminology where it no longer applies.

Verification:

- Playwright screenshots for desktop and mobile.
- Browser tests for primary quote and transaction flow.
- Manual visual review of error, loading, empty, and provider-down states.

## Task CN-14: Add Security, Compliance, And Operational Guardrails

Suggested issue title: Add ChangeNOW security, compliance, and observability guardrails

Objective:

Keep the open swap flow safe to operate by adding logs, redaction, abuse prevention, eligibility checks, and support diagnostics.

Current code to review:

- `app/src/App/App.php`
- `app/src/MySQL/MySQL.php`
- Existing notification, identity, country, and admin settings
- Server logs and PHP error handling behavior

Implementation scope:

- Add request IDs and provider transaction IDs to server logs.
- Redact API keys, private keys, raw addresses where appropriate, memos, and user-provided payload fields from logs.
- Add rate limiting for quote and transaction creation endpoints.
- Add country/eligibility messaging based on ChangeNOW terms and local admin policy.
- Add provider status checks and user-friendly outage states.
- Add explicit warnings that Krypto does not custody funds and that ChangeNOW processes the exchange.

Acceptance criteria:

- Admins can diagnose failed transactions without secrets in logs.
- Public endpoints resist basic spam and high-frequency quote abuse.
- Users see clear states for unsupported region, unsupported pair, provider downtime, expired quote, and address validation failure.
- Compliance-sensitive copy is configurable by admins.

Verification:

- Tests for log redaction, rate-limit decisions, and permission checks.
- Manual error-state review in browser.
- Security review before enabling live mode.

## Task CN-15: Add Test, CI, And Release Readiness Coverage

Suggested issue title: Add tests and release checks for the ChangeNOW migration

Objective:

Make the migration safe to maintain by adding automated checks around provider behavior, public flows, admin settings, and data migrations.

Current code to review:

- Existing repository scripts and lack of root CI workflow.
- `composer.json`
- PHP first-party files under `app/`, `install/`, `dashboard.php`, and `index.php`

Implementation scope:

- Add a lightweight PHPUnit or equivalent test setup if none exists.
- Add mocked HTTP fixtures for ChangeNOW success and failure cases.
- Add browser tests for quote, transaction creation, status lookup, optional signup, and admin configuration.
- Add schema checks for fresh install and upgrade migration.
- Add GitHub Actions workflow for PHP syntax validation and the new test suite.
- Document manual live-test procedure using ChangeNOW's recommended low-fee testing approach.

Acceptance criteria:

- CI runs on pull requests and validates PHP syntax plus the new automated test suite.
- Provider tests do not call live ChangeNOW APIs or require real keys.
- Manual live-test steps are documented for maintainers with partner credentials.
- Release notes describe feature flags and rollback behavior.

Verification:

- `git diff --check`
- PHP syntax validation for touched and first-party PHP files.
- Automated tests pass locally and in CI.
- PR includes screenshots for UI tasks and clear reproduction/verification notes.

## Definition Of Done For The Whole Migration

- The default product path is a public ChangeNOW swap flow.
- Direct exchange API key connection is absent from the normal user experience.
- Registration is optional and presented only for value-added account features.
- Admins can configure ChangeNOW, widget settings, referral behavior, and operational policies.
- Transactions are persisted, searchable, auditable, and recoverable by secure lookup.
- Legacy provider code is disabled, isolated, or removed after migration data is handled.
- Tests cover provider client behavior, public UX, admin settings, schema changes, referral attribution, and security guardrails.
- Documentation explains setup, ChangeNOW partner requirements, environment variables/settings, manual live testing, and rollback.
