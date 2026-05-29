# ChangeNOW Schema Migration

Issue: https://github.com/labtgbot/krypto/issues/16

## Scope

Fresh installs now receive ChangeNOW tables from `install/assets/sql/krypto.sql`. Existing installs can apply `install/assets/sql/changenow-cn12-migration.sql`, then `install/assets/sql/changenow-cn13-retention-migration.sql` for the retention policy settings.

The ChangeNOW migration creates local ChangeNOW storage for:

- Assets and network-specific currency variants: `changenow_assets_krypto`
- Pair availability, local enablement, and provider limits: `changenow_pairs_krypto`
- Short-lived quote responses: `changenow_quote_cache_krypto`
- Public and account-linked swap transactions: `changenow_transactions_krypto`
- Status/support/audit events: `changenow_transaction_events_krypto`
- Referral attribution records: `changenow_referral_attribution_krypto`
- Provider sync status: `changenow_sync_status_krypto`

## Installer Defaults

The provider is disabled on install with `changenow_provider_enabled = 0`. Current installs also default `legacy_exchange_connections_enabled = 0`, and the legacy exchange/wallet connection runtime has been removed.

Fresh installs no longer create legacy custodial tables such as `balance_krypto`, `order_krypto`, `internal_order_krypto`, `widthdraw_history_krypto`, `thirdparty_crypto_krypto`, `user_widthdraw_krypto`, `user_thirdparty_selected_krypto`, or exchange credential tables such as `binance_krypto`, `bitbank_krypto`, `bitmex_krypto`, `bittrex_krypto`, `cex_krypto`, `coinex_krypto`, `coinspot_krypto`, `ethfinex_krypto`, `exmo_krypto`, `gateio_krypto`, `gdax_krypto`, `gemini_krypto`, `hitbtc2_krypto`, `kraken_krypto`, `kucoin_krypto`, `livecoin_krypto`, `luno_krypto`, `okcoinusd_krypto`, `okex_krypto`, `poloniex_krypto`, `quoinex_krypto`, and `yobit_krypto`.

API keys and callback secrets are seeded as encrypted settings:

- `changenow_public_api_key`
- `changenow_private_api_key`
- `changenow_callback_secret`

Operational defaults are seeded for standard and fixed-rate flows, BTC to ETH default assets/networks, the documented ChangeNOW rate limits of 30 requests per second and 1800 requests per minute, a 30 second quote cache TTL, 30 day anonymous retention, 365 day completed transaction retention, and disabled debug logging.

## Legacy Data Decision

OPEN-05 decommissions the legacy custodial exchange and wallet model. The old exchange credential tables such as `binance_krypto`, `bitbank_krypto`, `bitmex_krypto`, `bittrex_krypto`, `cex_krypto`, `coinex_krypto`, `coinspot_krypto`, `ethfinex_krypto`, `exmo_krypto`, `gateio_krypto`, `gdax_krypto`, `gemini_krypto`, `hitbtc2_krypto`, `kraken_krypto`, `kucoin_krypto`, `livecoin_krypto`, `luno_krypto`, `okcoinusd_krypto`, `okex_krypto`, `poloniex_krypto`, `quoinex_krypto`, and `yobit_krypto` should be exported from existing production databases before they are dropped.

Legacy custodial market, balance, order, and withdraw tables such as `thirdparty_crypto_krypto`, `balance_krypto`, `order_krypto`, `internal_order_krypto`, and `widthdraw_history_krypto` are no longer part of the active schema. The new ChangeNOW flow reads and writes the `changenow_*_krypto` tables instead of those legacy exchange tables.

Two historical tables are intentionally retained in the active installer: `exchanges_krypto` for public market/search metadata and `deposit_history_krypto` for payment ledger history. They are not used as custodial exchange connector state.

Existing referral tables such as `referal_krypto` and `referal_histo_krypto` are retained. ChangeNOW-specific attribution is stored separately in `changenow_referral_attribution_krypto` and linked to transactions through `id_changenow_referral_attribution`.

## Existing Install Procedure

1. Back up the database.
2. Apply `install/assets/sql/changenow-cn12-migration.sql` to the existing Krypto database.
3. Apply `install/assets/sql/changenow-cn13-retention-migration.sql` to seed retention settings when they are missing.
4. Archive the legacy custody and exchange tables from the existing database backup according to the site's retention policy.
5. Apply `install/assets/sql/changenow-open05-decommission-legacy-custody.sql`. This creates `legacy_custody_archive_manifest_krypto`, records the table archive/drop path, and drops the retired legacy custody tables.
6. Confirm the provider remains disabled by checking `settings_krypto.key_settings = 'changenow_provider_enabled'`.
7. Run `php tests/changenow_schema_migration_test.php` and `php tests/changenow_legacy_decommission_test.php` from the repository root to verify required schema, indexes, settings, decommission SQL, and documentation are present.

Existing installs should treat the OPEN-05 SQL as a post-archive cleanup step. It removes retired custodial storage after backups have been captured; it does not copy table contents into a separate archive database.

## Rollback

If the migration must be rolled back before ChangeNOW traffic is enabled, leave the new tables in place and keep `changenow_provider_enabled = 0`. Restore the legacy custodial tables from the database archive created before running `changenow-open05-decommission-legacy-custody.sql` if old exchange/wallet data must be inspected.

If a site owner must remove the unused ChangeNOW schema from a database copy, first verify that no production traffic has written rows to the `changenow_*_krypto` tables. Then remove only the ChangeNOW tables and settings from that copy. Keep the legacy archive and `legacy_custody_archive_manifest_krypto` long enough to satisfy the site's audit and retention policy.
