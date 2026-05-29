# Krypto

Krypto is an open ChangeNOW-powered cross-currency swap application. Visitors can request quotes and create swaps without mandatory registration, while accounts remain optional for saved settings, history, referral features, and admin or manager workflows.

Krypto is non-custodial. Krypto does not store customer funds, run exchange liquidity, or hold private keys for swaps. ChangeNOW executes each exchange, and users send funds only to the ChangeNOW deposit instructions for the selected transaction.

## Quick Start

1. Serve the repository from a PHP-capable web server with MySQL or MariaDB.
2. Open `/install/`, complete the installer checks, database setup, URL/path setup, and admin account creation.
3. Remove or block direct access to `/install/` before production use.
4. Keep `changenow_provider_enabled` disabled until ChangeNOW credentials, default assets, regional policy, and rate limits are configured.
5. Run the local checks before release:

```sh
php scripts/lint_php.php
php scripts/run_tests.php
```

Browser regression coverage for the public swap flow is available when Node dependencies and Playwright browsers are installed:

```sh
npm ci
npx playwright install chromium
npm run test:e2e
```

## ChangeNOW Provider Setup

Create or sign in to a ChangeNOW Business or partner account, then configure the ChangeNOW provider in the Krypto admin payment settings. Store API keys and callback secrets only in server-side settings. Fresh installs should leave the provider disabled until credentials and live operational defaults are ready.

See [docs/changenow-provider-settings.md](docs/changenow-provider-settings.md) for required ChangeNOW credentials, flow settings, referral/widget fields, rate limits, and regional restrictions.

## Key Documentation

- [docs/changenow-provider-settings.md](docs/changenow-provider-settings.md): ChangeNOW admin setup and live swap guard.
- [docs/changenow-release-checks.md](docs/changenow-release-checks.md): automated release checks, mocked provider fixtures, live-test guidance, and rollback flags.
- [docs/changenow-staging-audit-checklist.md](docs/changenow-staging-audit-checklist.md): staging audit checklist for integration, security, privacy, resilience, and rollback.
- [docs/local-db-tests.md](docs/local-db-tests.md): reproducible PHP/MariaDB environment, schema bootstrap, DB smoke tests, logs, and reset commands.
- [docs/production-deployment-security.md](docs/production-deployment-security.md): production checklist for installer, config, vendor, and mutable storage paths.
- [docs/upload-storage-deployment.md](docs/upload-storage-deployment.md): Apache, Nginx, IIS, and reverse-proxy upload storage guards.
- [docs/open-noncustodial-roadmap-2026-05-29.md](docs/open-noncustodial-roadmap-2026-05-29.md): roadmap from the legacy custodial terminal toward the open non-custodial swap product.
- [docs/platform-analysis.md](docs/platform-analysis.md): historical architecture inventory for the legacy PHP platform and current product-positioning note.

## Product Boundaries

The default product path is the public ChangeNOW swap. Legacy trading-terminal, portfolio, subscription, payment, and market-data modules may still exist in the codebase for migration, rollback, admin, audit, or follow-up cleanup context. They should not be presented as the primary product unless a current feature flag and product decision explicitly enable that path.

## License

The Composer package remains marked `proprietary` until maintainers publish an explicit source license. That source-license state is separate from the open-access product model: public visitors can use the swap flow without mandatory registration, and Krypto remains non-custodial for user funds.
