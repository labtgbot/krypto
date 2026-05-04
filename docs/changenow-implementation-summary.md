# ChangeNOW Implementation Summary

Date: 2026-05-04

Related issue: https://github.com/labtgbot/krypto/issues/35

## Scope

This document summarizes the ChangeNOW migration work completed in PRs #20 through #34 and the follow-up CI investigation from issue #35. The migration moved Krypto from a legacy direct-exchange product toward a public ChangeNOW swap application with optional accounts, admin controls, release checks, and rollback flags.

## Completed Work

| PR | Area | Result |
| --- | --- | --- |
| [#20](https://github.com/labtgbot/krypto/pull/20) | Provider boundary | Added the ChangeNOW provider module boundary, feature flags, and ADR for product modes and legacy fallback. |
| [#21](https://github.com/labtgbot/krypto/pull/21) | Secure settings | Added encrypted ChangeNOW settings, masked admin saves, default disabled state, and provider validation. |
| [#22](https://github.com/labtgbot/krypto/pull/22) | API client | Added a server-side ChangeNOW v2 client with retries, error mapping, redacted debug logging, and mocked coverage. |
| [#23](https://github.com/labtgbot/krypto/pull/23) | Market data | Added asset, network, pair, limit, quote-cache, and sync primitives plus schema coverage. |
| [#24](https://github.com/labtgbot/krypto/pull/24) | Public swap | Replaced the account-first home path with anonymous quote, create, status, validation, and optional account linking flows. |
| [#25](https://github.com/labtgbot/krypto/pull/25) | Transaction lifecycle | Added status refresh, refund, continue, support notes, admin lookup, public redaction, and event history. |
| [#26](https://github.com/labtgbot/krypto/pull/26) | Widget | Added configurable ChangeNOW widget rendering, admin preview settings, sanitized embed URLs, and preview screenshots. |
| [#27](https://github.com/labtgbot/krypto/pull/27) | Legacy UX removal | Hid direct exchange and wallet setup from user-facing flows behind a disabled rollback flag. |
| [#28](https://github.com/labtgbot/krypto/pull/28) | Optional registration | Kept public swap open without upfront signup while preserving account-only boundaries for identity and history. |
| [#29](https://github.com/labtgbot/krypto/pull/29) | Referrals | Added referral and UTM capture, ChangeNOW attribution payloads, admin reporting, and account referral views. |
| [#30](https://github.com/labtgbot/krypto/pull/30) | Admin panel | Added ChangeNOW provider settings, widget controls, health state, support actions, and swap search/management. |
| [#31](https://github.com/labtgbot/krypto/pull/31) | Schema | Added fresh-install schema, additive migration SQL, defaults, retention notes, and rollback guidance. |
| [#32](https://github.com/labtgbot/krypto/pull/32) | UI redesign | Made ChangeNOW swap the public and dashboard first path while preserving legacy order-book access outside the default swap view. |
| [#33](https://github.com/labtgbot/krypto/pull/33) | Guardrails | Added redacted logging helpers, request IDs, rate limiting, eligibility states, compliance copy, and access checks. |
| [#34](https://github.com/labtgbot/krypto/pull/34) | Release checks | Added GitHub Actions CI, first-party PHP linting, a lightweight test runner, fixtures, and release-readiness documentation. |

## CI Investigation

Issue #35 referenced failed GitHub Actions run [25337596091](https://github.com/labtgbot/krypto/actions/runs/25337596091) on `main` at commit `01495f9f586f223e0c55d74f17766312d4326411`.

Findings from the downloaded CI log:

- `php scripts/lint_php.php` passed for 401 first-party PHP files.
- `php scripts/run_tests.php` failed only in `tests/changenow_release_readiness_test.php`.
- The release-readiness test rejected harmless `changenow.io` strings in `tests/ChangeNowWidgetTest.php` and `tests/changenow_market_data_test.php`.
- Those strings were widget host assertions and mocked image metadata, not live API calls or real network access.

The fix keeps the release gate focused on executable live-call risks:

- PHP tests still fail if they call `curl_exec()`.
- PHP tests still fail if they fetch `http` or `https` URLs with `file_get_contents()`.
- PHP tests now also fail if they instantiate `ChangeNowApiClient` against a real ChangeNOW host or invoke internal request helpers with a ChangeNOW host.
- Static widget URL assertions and mocked provider metadata are allowed because they do not perform network I/O.

## Current Verification

Local verification after the issue #35 fix:

```text
php tests/changenow_release_readiness_test.php
php scripts/lint_php.php
php scripts/run_tests.php
```

Results:

- ChangeNOW release readiness checks passed.
- PHP syntax validation passed for 401 first-party files.
- Test suite passed for 14 checks.

## Remaining Release Notes

ChangeNOW remains disabled by default until maintainers configure partner credentials and complete manual live testing. The default rollback path is configuration-based: disable `changenow_provider_enabled`, keep `legacy_exchange_connections_enabled` controlled by policy, and retain ChangeNOW tables for audit/history unless data removal is explicitly approved.

