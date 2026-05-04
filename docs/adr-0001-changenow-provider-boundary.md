# ADR 0001: ChangeNOW Provider Boundary

Date: 2026-05-04

Related issue: https://github.com/labtgbot/krypto/issues/5

## Status

Accepted for the ChangeNOW migration sequence.

## Context

Krypto currently routes trading through direct exchange connectors under `app/modules/kr-trade/src/`. Those connectors rely on CCXT or provider-specific classes, per-user exchange credentials, and logged-in user flows. The ChangeNOW migration needs a new swap boundary that can support public quote and swap creation without requiring a registered account.

## Decision

The ChangeNOW provider boundary lives in `app/modules/kr-changenow/src/` as a first-party module loaded by the existing module loader.

The boundary starts with:

- `ChangeNowSwapProviderInterface` for listing currencies and pairs, retrieving quotes, creating swaps, checking swap status, and validating destination addresses.
- `ChangeNowProviderMode` for the product modes that later tasks will route through.
- `ChangeNowUnavailableProvider` as a disabled placeholder so the boundary can be loaded before API settings and HTTP behavior are implemented.

Quote and swap creation accept request arrays instead of a `User` object. Authenticated features can include an optional account identifier in those arrays later, but the public flow must not depend on a logged-in user.

## Product Modes

- `public_swap`: anonymous quote, address validation, and swap creation through ChangeNOW.
- `optional_account_history`: saved transaction history, preferences, alerts, and referral attribution for users who choose to sign in.
- `admin_operations`: settings, provider health checks, asset controls, transaction support lookup, and operational reports.
- `legacy_disabled`: ChangeNOW is the active product route and direct exchange connection UX is hidden from normal users.

## Feature Flags

- `changenow_provider_enabled`: defaults to `0`; later tasks turn on ChangeNOW-backed routing only when the provider is configured.
- `legacy_exchange_connections_enabled`: defaults to `1`; legacy exchange flows remain available until feature parity and rollback requirements are satisfied.

When `changenow_provider_enabled = 1` and `legacy_exchange_connections_enabled = 0`, the application is in legacy-disabled mode. Later UI and action tasks should use that mode to remove direct exchange connection flows from the user product.

## Legacy Retention

Legacy trading code remains callable while `legacy_exchange_connections_enabled` is on. After ChangeNOW routing is complete, old exchange classes, user credential tables, balance history, and order history are retained only for rollback, support, audit, and data-access workflows. New public swap paths must not instantiate the legacy direct exchange classes.

## Security Notes

Provider credentials remain server-side. API keys and operational secrets must not be rendered into PHP templates, HTML, JavaScript, translations, or static assets. Public pages receive only non-secret provider metadata and transaction fields required to complete the swap.

## Consequences

Later ChangeNOW tasks can build the HTTP client, settings screens, schemas, public swap UI, history, and admin tooling against one provider interface instead of touching every legacy exchange connector. The first boundary is intentionally small and does not make live API calls.
