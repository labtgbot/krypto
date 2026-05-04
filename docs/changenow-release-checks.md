# ChangeNOW Release Checks

This document defines the release checks for the ChangeNOW migration. It is intentionally lightweight because the current repository does not have PHPUnit, Composer CLI, a root package manifest, or a reliable database-backed browser environment in CI.

## Automated release checks

Pull requests run `.github/workflows/ci.yml`, which validates first-party PHP syntax and then runs every `tests/*_test.php` and `tests/*.sh` check through `scripts/run_tests.php`.

The syntax check is limited to application-owned PHP files under `app/`, `config/`, `install/`, `public/`, `dashboard.php`, and `index.php`. It excludes committed vendor, Bower, and node module code so CI failures stay tied to Krypto changes.

New ChangeNOW implementation PRs should add focused standalone tests beside the code they introduce. The runner executes each test in a separate process, so tests can keep simple helper functions without cross-file naming conflicts.

## Mocked provider fixtures

Provider behavior tests must use local fixtures under `tests/fixtures/changenow/` or in-test fake transports. They must not require a public API key, private API key, partner account, or outbound calls to ChangeNOW.

Current baseline fixtures cover:

- Standard quote success through `estimated_amount_standard_success.json`.
- Transaction creation success through `exchange_create_success.json`.
- Finished transaction status lookup through `exchange_status_finished.json`.
- Validation failure through `validation_error.json`.

When fixed-rate, refund, continue, network-fee, or transaction-list behavior lands, add matching success and failure fixtures before wiring the feature into release readiness.

## Manual live-test procedure

ChangeNOW does not provide a dedicated test environment in the migration plan, so maintainers should run live verification only after mocked CI checks pass and partner credentials are available.

Use `docs/changenow-staging-audit-checklist.md` for the full P1-P3 staging audit. It includes evidence prompts for integration/data flow, security/privacy, resilience/rollback, current automated coverage, and known limitations.

Use low-fee pairs such as XRP to XLM or another currently low-cost pair approved by the maintainer. Keep amounts minimal, record the provider transaction id, and verify the complete flow without exposing credentials in browser source, logs, screenshots, or PR comments.

Manual live verification should cover:

- Public quote lookup for standard and fixed-rate flows when enabled.
- Transaction creation and deposit instruction rendering.
- Status lookup from the anonymous lookup token and from admin history.
- Optional signup after swap creation, with history linked only when the user chooses to create an account.
- Admin settings save behavior, including masked secret preservation and disabled-provider validation.
- Refund and continue actions when the provider response marks those actions available.

## Feature flags and rollback

Release notes for the migration must list the active flags and their rollback behavior:

- `changenow_provider_enabled`: keep disabled by default until credentials and live testing are complete.
- `legacy_exchange_connections_enabled`: keep enabled until the ChangeNOW public flow, history, admin, and rollback paths are verified.
- `changenow_debug_logging_enabled`: keep disabled by default; when temporarily enabled, logs must redact API keys, addresses, user ids, payloads, and callback secrets.

Rollback should prefer configuration changes over schema reversal. Disable ChangeNOW, re-enable legacy exchange connections, leave ChangeNOW tables intact for audit/history, and preserve transaction lookup records unless a maintainer explicitly approves data removal.

## Provider tests must not call live ChangeNOW APIs

Automated provider tests must fail if they embed `changenow.io`, call `curl_exec()`, or fetch live HTTP URLs directly. The test suite enforces this for PHP tests so CI remains deterministic and does not spend real funds.

Any live-test notes belong in PR descriptions or release notes, not in automated tests. Use fake transports, local fixtures, and deterministic timestamps for CI coverage.
