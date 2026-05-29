# Open Non-Custodial Swap Platform — Deep Analysis And Remaining Roadmap

Analysis date: 2026-05-29

Related issue: https://github.com/labtgbot/krypto/issues/67

## Purpose

Issue #67 asks to review the whole project so that Krypto is an open platform
for cross-currency exchange, without mandatory registration, and fully
non-custodial — and to create the concrete follow-up tasks needed to finish that
transformation professionally. This document records the current verified state
and the gaps that justify the new task issues.

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
- 27 PHP tests plus shell guards, a release-readiness gate, and a CI workflow
  (`.github/workflows/ci.yml`) run lint + tests on every PR.
- Referral attribution is wired through to ChangeNOW: the flow sets
  `userId` and `payload.kryptoReferralAttribution`
  (`ChangeNowPublicSwapFlow.php:147,507-510`) and the client forwards both
  (`ChangeNowApiClient.php:430-431`). This was a false positive in the first
  analysis pass and needs no new task.
- ChangeNOW transactions carry an `expires_at_changenow_transaction` column
  (`ChangeNowPublicSwapRepository.php:738`); the quote cache has its own
  `expires_at` (`ChangeNowMarketRepository.php`). Expiry data exists — only the
  pruning job is missing (see gap 3).

## Verified remaining gaps at analysis time

Each gap below was confirmed in source and maps to one new task issue.

1. **Rate limiting is built but never enforced.** `ChangeNowRateLimiter`
   (`app/src/ChangeNow/ChangeNowGuardrails.php:244`) and
   `App::_getChangeNowRateLimiter()` (`app/src/App/App.php:2090`) exist, but no
   request entry point instantiates or calls the limiter. The public endpoint
   `app/modules/kr-changenow/src/actions/publicSwap.php` performs quote, create,
   and status with no throttle. Abuse/DoS and ChangeNOW rate-limit exhaustion
   risk.

2. **Regional eligibility is not enforced server-side.**
   `ChangeNowGuardrails::countryState()` (`ChangeNowGuardrails.php:338`) and the
   admin "unsupported countries" setting exist, but the public swap
   flow/action never reference country or eligibility. Compliance risk for a
   live deployment.

3. **No retention / cleanup job.** Transactions, transaction events, and the
   quote cache accumulate forever. Expiry columns exist but nothing prunes
   expired or stale rows, and there is no documented retention policy. Privacy
   and storage-growth debt.

4. **No end-to-end browser coverage.** All 27 tests are PHP/shell; there are no
   Playwright/browser tests even though CN-05 and CN-13 acceptance criteria
   explicitly call for desktop and mobile browser tests of the swap flow.

5. **Legacy custodial code has an OPEN-05 cleanup path.** The connector classes
   under `app/modules/kr-trade/src/` and the custodial balance/order/withdraw
   tables were removed from the fresh installer. Existing installs must archive
   old table contents before running
   `install/assets/sql/changenow-open05-decommission-legacy-custody.sql`.

6. **Product documentation and packaging needed a refresh.** Gap 6 was addressed by issue #74. README and Composer metadata now present Krypto as an open, non-custodial ChangeNOW swap product. The README also explains that the Composer package remains marked `proprietary` until maintainers publish an explicit source license.

7. **Unused legacy dependencies inflate the attack surface.** `composer.json`
   still requires dozens of exchange/payment SDKs (CCXT, Binance, Kraken, GDAX,
   Stripe, PayPal, Coinbase, …) that the swap product no longer uses. Pruning
   them reduces maintenance and security exposure.

## New task issues

The following issues were filed from this analysis and linked to #67. The
parent tracker is #76.

- #69 — OPEN-01: Enforce rate limiting on the public swap endpoints (gap 1).
- #70 — OPEN-02: Enforce regional/eligibility gating server-side (gap 2).
- #71 — OPEN-03: Add retention and cleanup job for ChangeNOW data (gap 3).
- #72 — OPEN-04: Add end-to-end browser tests for the public swap (gap 4).
- #73 — OPEN-05: Decommission legacy custodial exchange/wallet code and tables (gap 5).
- #74 — OPEN-06: Refresh README, docs, and Composer metadata for the open product (gap 6, addressed).
- #75 — OPEN-07: Prune unused legacy Composer dependencies (gap 7).
