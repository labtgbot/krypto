# ChangeNOW Schema Migration

Issue: https://github.com/labtgbot/krypto/issues/16

## Scope

Fresh installs now receive ChangeNOW tables from `install/assets/sql/krypto.sql`. Existing installs can apply `install/assets/sql/changenow-cn12-migration.sql`.

The migration is additive. It creates local ChangeNOW storage for:

- Assets and network-specific currency variants: `changenow_assets_krypto`
- Pair availability, local enablement, and provider limits: `changenow_pairs_krypto`
- Short-lived quote responses: `changenow_quote_cache_krypto`
- Public and account-linked swap transactions: `changenow_transactions_krypto`
- Status/support/audit events: `changenow_transaction_events_krypto`
- Referral attribution records: `changenow_referral_attribution_krypto`
- Provider sync status: `changenow_sync_status_krypto`

## Installer Defaults

The provider is disabled on install with `changenow_provider_enabled = 0`. Legacy exchange flows remain available with `legacy_exchange_connections_enabled = 1` until later product tasks finish feature parity and explicitly disable old exchange connection UX.

API keys and callback secrets are seeded as encrypted settings:

- `changenow_public_api_key`
- `changenow_private_api_key`
- `changenow_callback_secret`

Operational defaults are seeded for standard and fixed-rate flows, BTC to ETH default assets/networks, the documented ChangeNOW rate limits of 30 requests per second and 1800 requests per minute, a 30 second quote cache TTL, and disabled debug logging.

## Legacy Data Decision

Legacy exchange credential tables such as `binance_krypto`, `bitbank_krypto`, `bitmex_krypto`, `bittrex_krypto`, `cex_krypto`, `coinex_krypto`, `coinspot_krypto`, `ethfinex_krypto`, `exmo_krypto`, `gateio_krypto`, `gdax_krypto`, `gemini_krypto`, `hitbtc2_krypto`, `kraken_krypto`, `kucoin_krypto`, `livecoin_krypto`, `luno_krypto`, `okcoinusd_krypto`, `okex_krypto`, `poloniex_krypto`, `quoinex_krypto`, and `yobit_krypto` are retained. They are historical/rollback data and are not part of the new ChangeNOW schema.

Legacy market and balance tables such as `thirdparty_crypto_krypto`, `exchanges_krypto`, `balance_krypto`, `order_krypto`, `internal_order_krypto`, `deposit_history_krypto`, and `widthdraw_history_krypto` are retained for historical records, support, audit, and rollback. The new ChangeNOW flow should read and write the `changenow_*_krypto` tables instead of those legacy exchange tables.

Existing referral tables such as `referal_krypto` and `referal_histo_krypto` are retained. ChangeNOW-specific attribution is stored separately in `changenow_referral_attribution_krypto` and linked to transactions through `id_changenow_referral_attribution`.

## Existing Install Procedure

1. Back up the database.
2. Apply `install/assets/sql/changenow-cn12-migration.sql` to the existing Krypto database.
3. Confirm the provider remains disabled by checking `settings_krypto.key_settings = 'changenow_provider_enabled'`.
4. Run `php tests/changenow_schema_migration_test.php` from the repository root to verify required schema, indexes, settings, and documentation are present.

The migration uses `CREATE TABLE IF NOT EXISTS` and only inserts missing settings. It does not modify, clear, or remove legacy tables.

## Rollback

If the migration must be rolled back before ChangeNOW traffic is enabled, leave the new tables in place and keep `changenow_provider_enabled = 0`. This is the lowest-risk rollback because it preserves historical data and avoids changing legacy tables.

If a site owner must remove the unused ChangeNOW schema from a database copy, first verify that no production traffic has written rows to the `changenow_*_krypto` tables. Then remove only the ChangeNOW tables and settings from that copy. Do not remove legacy Krypto tables unless a separate data-retention plan explicitly authorizes it.
