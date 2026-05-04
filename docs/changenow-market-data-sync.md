# ChangeNOW Market Data Sync

The ChangeNOW module stores provider assets, network-specific token variants, supported swap pairs, pair limits, quote responses, and sync status in local `changenow_*_krypto` tables. This keeps swap listing and quote lookups independent from the legacy CCXT exchange connection tables.

The cron action is `app/modules/kr-changenow/src/actions/syncMarketData.php`. It runs only when the ChangeNOW provider is enabled and a public API key is configured. Disabled or unconfigured installs mark the sync as `skipped` instead of failing the cron page.

`ChangeNowMarketData::_sync()` fetches active currencies and available pairs for enabled flows, then replaces provider-active flags without resetting admin-enabled overrides. `ChangeNowMarketData::_getQuote()` validates the selected source asset, destination asset, and flow against local sync data before calling ChangeNOW range, quote, and network-fee endpoints. Quote responses are cached for `changenow_quote_cache_ttl` seconds, defaulting to 30.

The focused test coverage lives in `tests/changenow_market_data_test.php` and `tests/changenow_schema_test.php`.
