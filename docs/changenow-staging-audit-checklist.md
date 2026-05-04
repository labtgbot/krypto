# ChangeNOW Staging Audit Checklist

Use this checklist before enabling `changenow_provider_enabled` outside a controlled staging environment. Record evidence in the PR, release notes, or an internal QA record without exposing API keys, wallet addresses, emails, lookup tokens, internal IPs, or raw provider payloads.

## Preconditions

- Deploy the release candidate to staging with `changenow_provider_enabled = 0`.
- Run `php scripts/lint_php.php` and `php scripts/run_tests.php` on the exact commit under test.
- Configure ChangeNOW public/private keys, callback secret if issued, referral link id, support email, enabled flows, default assets, and quote cache TTL through the admin panel.
- Keep `changenow_debug_logging_enabled = 0` unless a short debug window is approved; scrub logs before attaching evidence.
- Use a low-fee live pair approved by the maintainer because ChangeNOW does not provide a dedicated test environment.

## P1 Integration And Data Flow

- Currency/network mapping: run the market-data sync, then verify network-specific assets such as USDT on Ethereum and Tron remain distinct in source/destination lists.
- Missing or deprecated pairs: disable or remove one synced pair in staging data and verify quote creation returns a user-safe unavailable-pair state without PHP warnings.
- Network error handling: simulate timeout, HTTP `429`, and HTTP `5xx` responses with a proxy or mocked transport. Confirm idempotent GET requests retry with exponential backoff, `Retry-After` is honored for 429 responses, and transaction creation is not retried automatically.
- Quote cache expiry: request a quote twice inside the TTL and once after expiry. Confirm the second response is cached and the expired response is refreshed before swap creation.
- Manual market refresh: run the admin or cron market-data refresh and confirm updated assets/pairs are reflected without clearing admin-disabled overrides.
- Status lifecycle: create a minimal live swap and verify status refresh records the expected provider statuses from `waiting` through terminal `finished`, `failed`, or `refunded` as applicable.
- Webhook or callback handling: if ChangeNOW has issued callback signing details for the partner account, verify signature validation and duplicate delivery handling. If callback signing is unavailable, record the limitation and verify support/admin status refresh remains the fallback.

## P2 Security And Privacy

- Redacted logging: with debug enabled briefly, trigger quote, validation, status, and error paths. Confirm logs redact API keys, wallet addresses, refund addresses, extra IDs, user IDs, payloads, emails, and forwarded IPs.
- Widget URL sanitization: test malicious `primaryColor`, `link_id`, amount, language, and fallback URL values. Confirm generated iframe and fallback URLs contain only whitelisted query keys and escaped attributes.
- Client-side leakage: view page source and loaded JavaScript for public swap, widget, admin, and history pages. Confirm API keys, private payloads, referral attribution internals, emails, lookup token hashes, and raw provider snapshots are absent.
- Access control: verify anonymous lookup tokens only reveal their own public transaction view, logged-in history only returns that user's records, and support actions require admin/manager context.
- Referral and UTM handling: confirm referral/UTM data is captured server-side, sent only in server-side ChangeNOW payloads where configured, and omitted from public transaction responses.

## P3 Resilience And Rollback

- Feature flag off: set `changenow_provider_enabled = 0` and verify public quote/create actions fail safely, admin pages load, cron marks sync as skipped, and no PHP warnings are emitted.
- Legacy fallback policy: verify `legacy_exchange_connections_enabled` matches the release policy and that preserved legacy data remains accessible for support, audit, and rollback workflows.
- Database compatibility: test a fresh install and an upgraded database with `install/assets/sql/changenow-cn12-migration.sql`; confirm ChangeNOW tables and defaults exist without modifying legacy exchange tables.
- Rollback simulation: disable ChangeNOW, keep ChangeNOW tables intact, restore the previous provider flags, and confirm dashboards, menus, cron, and public pages still load.
- Provider outage: simulate ChangeNOW API unavailability and confirm public UI shows a maintenance/error state without stack traces or internal paths.

## Current Automated Coverage

- `tests/changenow_api_client_test.php` covers server-side API calls, 429/5xx retry behavior, no automatic create retry, error mapping, transaction listing, and debug redaction.
- `tests/changenow_market_data_test.php` covers currency/network normalization, pair availability, quote caching, cache expiry, disabled flows, and no CCXT dependency.
- `tests/changenow_transaction_lifecycle_test.php` covers anonymous lookup, account history boundaries, duplicate polling without duplicate status events, refund/continue permissions, and public redaction.
- `tests/ChangeNowWidgetTest.php` covers widget URL/query sanitization and rendered markup escaping.
- `tests/changenow_release_readiness_test.php` blocks live ChangeNOW calls in automated tests and verifies release documentation exists.

## Known Limitations To Record

- Live staging swaps require real partner credentials and a low-fee live pair; do not run them in CI.
- ChangeNOW callback signing details are partner-account dependent. If `X-CHANGENOW-SIGNATURE` is unavailable for the account, record that webhook validation could not be completed and rely on status refresh as the fallback verification.
- Visual checks should include desktop and mobile screenshots only when UI markup or CSS changes are part of the release candidate.
