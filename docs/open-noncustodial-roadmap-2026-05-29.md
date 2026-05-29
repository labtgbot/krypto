# Open Non-Custodial Swap Platform — Completion Roadmap

Analysis date: 2026-05-29
Completion update: 2026-05-29

Related issue: https://github.com/labtgbot/krypto/issues/67
Parent tracker: https://github.com/labtgbot/krypto/issues/76

## Purpose

Issue #67 asks to review the whole project so that Krypto is an open platform
for cross-currency exchange, without mandatory registration, and fully
non-custodial — and to create the concrete follow-up tasks needed to finish
that transformation professionally. This document records the initial analysis
and the final status of tracker #76 after the follow-up tasks were completed.

## Method

- Re-read the closed migration and audit issues/PRs:
  - ChangeNOW migration: issues #3, #5–#19; PRs #4, #20–#34, #36, #38.
  - Production/security audit: issues #49, #51, #53–#59; PRs #50, #52, #60–#66.
- Read the current code for the entry points, the `kr-changenow` module, the
  guardrail helpers, the installer schema, the tests, and the CI workflow.
- Verified every claimed gap against the actual source instead of trusting the
  high-level summaries. Several first-pass "gaps" turned out to be already
  implemented and were discarded (see "Already done" below).

## Current state — verified as DONE

- Public swap is the default landing surface. `index.php:126` includes
  `app/modules/kr-changenow/views/publicSwap.php`; no login gate blocks it.
- The authenticated dashboard defaults to the ChangeNOW swap view
  (`dashboard.php` body class `kr-view-changenow-swap`, left-nav
  `kr-module="changenow" kr-view="swap"`).
- A complete provider boundary, server-side API client, market-data sync,
  transaction lifecycle, referral attribution, admin panel, and widget live
  under `app/modules/kr-changenow/`.
- Feature flags `changenow_provider_enabled` and
  `legacy_exchange_connections_enabled` both default OFF and gate the runtime
  behaviour (`app/src/App/App.php:313-325`).
- PHP tests, shell guards, Playwright browser coverage, a release-readiness
  gate, and CI workflow (`.github/workflows/ci.yml`) cover the primary
  ChangeNOW paths on every PR.
- Referral attribution is wired through to ChangeNOW: the flow sets
  `userId` and `payload.kryptoReferralAttribution`
  (`ChangeNowPublicSwapFlow.php:147,507-510`) and the client forwards both
  (`ChangeNowApiClient.php:430-431`). This was a false positive in the first
  analysis pass and needs no new task.
- Public quote, validation, and transaction creation paths enforce configured
  ChangeNOW rate limits before calling the provider.
- Server-side regional eligibility checks block unsupported countries before
  transaction creation while preserving the default allow-all behaviour when no
  unsupported countries are configured.
- ChangeNOW transaction, event, and quote-cache retention is documented and can
  be executed through `scripts/changenow_retention.php`.
- Fresh installs no longer include the retired custodial exchange connector
  runtime or custodial tables. Existing installs have an explicit archive/drop
  path in `install/assets/sql/changenow-open05-decommission-legacy-custody.sql`.

## Tracker #76 completion update

All OPEN-01 through OPEN-07 follow-up issues are closed. The parent tracker #76
is ready to close once the final tracker PR lands.

- #69 — OPEN-01: completed in PR #77. Public quote, validation, and create
  actions now call the configured `ChangeNowRateLimiter`; excess requests return
  a structured `rate_limited` JSON error.
- #70 — OPEN-02: completed in PR #78. Public swap creation performs
  server-side country eligibility checks and returns the configured unsupported
  region message before any ChangeNOW transaction call.
- #71 — OPEN-03: completed in PR #79. `scripts/changenow_retention.php`,
  retention settings, DB-backed tests, and `docs/changenow-retention-policy.md`
  define and verify cleanup for expired anonymous transaction data and quote
  cache rows.
- #72 — OPEN-04: completed in PR #80. Playwright e2e tests cover the mocked
  public swap flow on desktop and mobile portrait, with screenshots committed
  under `docs/screenshots/`.
- #73 — OPEN-05: completed in PR #81. Legacy custodial exchange connectors,
  user routes, assets, installer tables, and retired cron endpoints were removed;
  existing installs have the documented archive/drop SQL path.
- #74 — OPEN-06: completed in PR #82. Gap 6 was addressed by issue #74. README and Composer metadata now present Krypto as an open, non-custodial ChangeNOW swap product. The README also explains that the Composer package remains marked `proprietary` until maintainers publish an explicit source license.
- #75 — OPEN-07: completed in PR #83. `composer.json` now retains only the
  runtime packages still used by ChangeNOW, OAuth, CAPTCHA, SMTP, POEditor,
  dashboard, template, 2FA, and currency-rate code; legacy exchange, payment,
  Omnipay, RSS, QR, and socket SDKs were removed from the lock file and
  committed vendor tree.
