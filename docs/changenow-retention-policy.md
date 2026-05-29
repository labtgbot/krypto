# ChangeNOW Retention Policy

ChangeNOW retention is handled by `scripts/changenow_retention.php`. Run it
from cron in the same environment as the application, for example once per
hour or once per day:

```bash
php scripts/changenow_retention.php --json
```

Use `--dry-run` before enabling a new schedule to count rows without changing
data. Local database checks can run through `php scripts/run_tests.php --db`.

## Defaults

- `changenow_retention_anonymous_days`: defaults to `30`. Anonymous lookup
  tokens are replaced after the transaction expiry time is older than this
  window. If a row has no provider expiry, `created_at_changenow_transaction`
  is used as the fallback clock.
- `changenow_retention_completed_days`: defaults to `365`. Terminal
  transactions are kept for audit and support during this window, then deleted.
- `changenow_quote_cache_krypto`: expired quote-cache rows are deleted on every
  retention run because quote responses are short lived and refreshable.

Both retention settings are seeded for fresh installs and added to upgraded
databases by `install/assets/sql/changenow-cn13-retention-migration.sql`.

## What Changes

Expired quote cache:

- Deletes rows from `changenow_quote_cache_krypto` where
  `expires_at_changenow_quote_cache` is in the past.

Expired anonymous transactions:

- Ensures anonymous lookup tokens are not retained past the configured
  `changenow_retention_anonymous_days` window.
- Replaces the original anonymous lookup token hash with a deterministic
  retained hash that cannot be derived from the visitor token.
- Replaces the session key hash with a deterministic retained hash.
- Clears pay-in, payout, refund, extra-id, raw provider payload, raw action, and
  support-note fields that can contain transaction-specific private data.
- Deletes related `changenow_transaction_events_krypto` rows because raw event
  payloads can include addresses and provider-side details.
- Keeps non-identifying audit fields such as provider id, pair, amounts, status,
  timestamps, and referral attribution until the completed-retention policy
  deletes the transaction.

Completed transactions:

- Deletes terminal rows after `changenow_retention_completed_days` based on
  `updated_at_changenow_transaction`.
- Deletes related `changenow_transaction_events_krypto` rows in the same run.
- Terminal statuses include `finished`, `completed`, `complete`, `success`,
  `failed`, `refunded`, `expired`, `overdue`, and `rejected`.

The script is idempotent. Re-running it after a successful pass does not
re-anonymize retained anonymous rows or delete additional active records.

## CLI Overrides

The CLI can override settings for one run:

```bash
php scripts/changenow_retention.php --anonymous-days=14 --completed-days=730 --dry-run
```

For local or staging DB experiments that use `KRYPTO_ENV_CONFIG`, `--db=NAME`
sets both `KRYPTO_DB_NAME` and `KRYPTO_TEST_DB_NAME` before the app config is
loaded.
