# Krypto Platform Analysis

Analysis date: 2026-05-04

## Summary

Krypto is a self-hosted cryptocurrency web platform. It is not a blockchain node, wallet daemon, or standalone exchange engine. It is a PHP web application that combines market data, dashboards, alerts, portfolios, trading integrations, payment gateways, subscriptions, identity verification, admin tools, and chat around external cryptocurrency services.

The repository is a legacy full-stack PHP application with most runtime dependencies committed into the repository. The first-party PHP code is about 47,500 lines, with additional vendored Composer packages, Bower frontend packages, static assets, translations, and an installer.

## What The Platform Does

Primary user-facing capabilities:

- Public login, signup, password reset, Google OAuth, Facebook OAuth, and optional Google Authenticator two-factor flow.
- Authenticated dashboard with configurable market panels, charts, order book views, watchlist, notifications, calculator, portfolio, news, social feeds, and chat.
- Cryptocurrency data retrieval through CryptoCompare REST endpoints and the CryptoCompare Socket.IO streaming endpoint.
- Technical analysis indicators and chart tooling for market data.
- Practice and real trading workflows through connected exchange accounts.
- Subscription, deposit, withdrawal, payment, and referral workflows.
- Identity verification workflows for deposit, trade, and withdrawal gating.
- Admin UI for settings, users, coins, currencies, mail, payment gateways, subscription plans, trading, cron status, extra pages, bank accounts, identity, templates, and intro content.

Important distinction: Krypto orchestrates external APIs and local database records. It does not itself provide blockchain consensus, exchange liquidity, custody infrastructure, or fiat banking rails without the configured third-party services.

## What It Is Written In

Backend:

- PHP, written in a PHP 7-era style with global entry points, manual `require` statements, sessions, procedural action scripts, and lightweight classes.
- MySQL access through `PDO` in `app/src/MySQL/MySQL.php`.
- Composer dependencies declared in `composer.json` and committed in `vendor/`.
- SQL schema and seed data in `install/assets/sql/krypto.sql`.

Frontend:

- Server-rendered PHP templates, HTML, CSS, and JavaScript.
- jQuery-driven browser interactions.
- Bower-era frontend libraries committed under `assets/bower/`, including jQuery, Chart.js, Dropzone, SweetAlert2, Selectize, CodeMirror, Moment, date/time pickers, and other UI utilities.
- CryptoCompare Socket.IO client integration in `app/modules/kr-socket/statics/js/streamer.js`.
- Static assets for logos, language icons, payment icons, exchange icons, crypto icons, sounds, and responsive styling.

Utility tooling:

- `push.js` is a Node.js helper script for pushing the project to GitHub with `simple-git` and `dotenv`.
- The repository does not include a root `package.json`, so `push.js` is not wired as a reproducible Node project command in its current form.

Localization:

- Runtime JSON translations are stored in `public/lang/`.
- POEditor export files are stored in `assets/poeditor/po/`.
- The repository currently includes 23 JSON language files and 22 PO files.

## Main Runtime Entry Points

- `index.php`: public landing/login shell. Starts the PHP session, loads configuration, Composer autoload, core classes, modules, language handling, OAuth hooks, and redirects logged-in users to the dashboard.
- `dashboard.php`: authenticated application shell. Loads core crypto classes, all enabled module controllers, user subscription state, dashboard modules, real/practice balance state, and the page-level frontend asset bundle.
- `install/index.php`: installation wizard for language choice, server requirement checks, database setup, app URL/path configuration, admin creation, cron loading, and finish screen.
- `app/modules/*/src/actions/*.php`: AJAX/action endpoints for module-specific behavior.
- `app/src/*/actions/*.php`: core maintenance and cron endpoints.

## Application Structure

Top-level directories:

- `app/src`: core application, database, language, user, and crypto API classes.
- `app/modules`: feature modules. Each module has a `config.json`; many have `src`, `views`, `statics/css`, and `statics/js` folders.
- `assets`: global CSS, JavaScript, Bower packages, Node runtime fragments, images, icons, sounds, and translation source files.
- `config`: generated PHP constants for app URL, document path, MySQL credentials, and encryption key.
- `install`: installer code and SQL schema.
- `public`: mutable/public runtime assets such as translations, logos, and uploaded user content.
- `vendor`: committed Composer dependencies.

The module loader is implemented in `app/src/App/App.php` and `app/src/App/AppModule.php`. It scans `app/modules`, checks each module directory, loads module assets, and requires PHP controllers from module `src` directories. In the current implementation, module configuration parsing is effectively bypassed and `_isEnable()` always returns `true`, so all module directories are treated as enabled.

## Module Inventory

Enabled-by-directory modules:

- `kr-admin`: admin settings, users, coins, currencies, mail, payments, subscriptions, cron, trading, identity, templates.
- `kr-api`: lightweight internal API routes for coins, history, news, symbols, and currency info.
- `kr-blockfolio`: portfolio/holding tracking.
- `kr-blocksexplorer`: Bitcoin, Litecoin, Ethereum, and Chain.so block explorer helpers.
- `kr-calculator`: conversion and rate calculator.
- `kr-chat`: chat rooms, messages, attachments, blocking, and right-bar UI.
- `kr-coin`: coin detail view.
- `kr-dashboard`: dashboard layouts, charts, top lists, indicators, alerts, notifications, and export actions.
- `kr-facebookoauth`: Facebook login integration.
- `kr-googleoauth`: Google login integration.
- `kr-identity`: identity verification steps, document assets, and admin approval flow.
- `kr-manager`: manager-facing users, orders, payments, withdrawals, identity, and statistics views.
- `kr-marketanalysis`: market lists, coin lists, top movers, and heatmap UI.
- `kr-news`: RSS news, social feed, and calendar sidebars.
- `kr-notifications`: notification center and notification list endpoints.
- `kr-payment`: payment gateway integrations and deposit proof flows.
- `kr-search`: search UI and search endpoint.
- `kr-socket`: browser-side streaming market data utilities.
- `kr-trade`: exchange connectors, balances, deposit/withdrawal flows, orders, internal/practice trading, leaderboard, and limit-order cron.
- `kr-user`: account, profile, security, subscription, exchanges, notification, and auth actions.
- `kr-watchinglist`: user watchlist management.

## Data Storage

The platform is stateful and depends on MySQL or a compatible MariaDB deployment.

The schema in `install/assets/sql/krypto.sql` defines tables for:

- Users, user settings, login history, visits, status, room membership, and intro/news-popup state.
- Settings, cron status, currencies, countries, coins, market history, cache, dashboard layouts, graphs, indicators, top lists, and watchlists.
- Exchange credentials/state for supported trading providers.
- Internal orders, balance, deposits, withdrawals, payment gateways, bank transfers, proofs, and referral history.
- Identity documents, identity steps, identity assets, and identity status.
- News RSS feeds, social feeds, notifications, chat rooms, chat messages, blocked users, and additional pages.

Configuration is split between:

- PHP constants in `config/config.settings.php`, generated by the installer.
- Runtime settings rows in `settings_krypto`, many of which are editable through admin screens.

Sensitive settings can be marked encrypted in `settings_krypto`; encryption depends on `CRYPTED_KEY` from the generated PHP config file.

## External Integrations

Market data and analytics:

- CryptoCompare REST API and streaming Socket.IO endpoint.
- Technical indicators implemented in PHP and also provided through committed frontend `technicalindicators` assets.

Trading/exchange integrations:

- CCXT-based or provider-specific exchange wrappers for Binance, Bitbank, Bitfinex, Bitmex, Bitstamp, Bittrex, BTC Markets, CEX, Coinex, Coinspot, Ethfinex, Exmo, Gate.io, GDAX/Coinbase Pro, Gemini, HitBTC, Kraken, KuCoin, Livecoin, Luno, OKCoin, OKEx, Poloniex, Quoinex, and Yobit.

Payment and deposit integrations:

- Stripe/credit card, PayPal, CoinGate, Coinbase Commerce, CoinPayments, Mollie, Fortumo, Payeer, Perfect Money, Paystack, Rave/Flutterwave, Polipayments, Blockonomics, PayBear-related code, bank transfer, and direct deposit flows.

Identity/auth/social:

- Google OAuth, Facebook OAuth, Google Authenticator, Google reCAPTCHA, SMTP/PHPMailer, RSS feeds, POEditor, and IP geolocation through `ip-api.com`.

Because the app is integration-heavy, production use requires outbound HTTPS access from the PHP server to market-data, exchange, payment, mail, OAuth, and feed providers.

## Where It Can Work

Krypto can work as a web application in environments that provide:

- PHP with web-server integration. The installer checks for PHP 7.0.0 or newer.
- MySQL-compatible database access through PDO.
- PHP `curl`, `openssl`, and URL fopen support.
- A writable `config/config.settings.php` during installation.
- A writable `public` directory for mutable assets and uploads.
- A web document root that matches the `FILE_PATH` assumptions used with `$_SERVER['DOCUMENT_ROOT']`.
- Cron or scheduled HTTP calls for maintenance endpoints.
- Network access to external APIs.

Suitable hosting models:

- Apache shared hosting with PHP and MySQL, especially because the repository includes an `.htaccess` rewrite for `/dashboard`.
- VPS or dedicated Linux hosting with Apache or Nginx plus PHP-FPM and MySQL/MariaDB.
- Containerized hosting, if persistent storage is provided for config/uploads and scheduled jobs are configured.
- Internal/private deployments where external API access is allowed by firewall policy.

Less suitable hosting models:

- Static hosting, because the app requires PHP execution and MySQL.
- Pure serverless edge hosting, unless adapted to provide PHP runtime, sessions, persistent files, database connectivity, and scheduled jobs.
- Environments without outbound internet access, because market data, OAuth, exchange, payment, and feed features depend on external services.
- Production setups that cannot remove or protect the `install` directory after setup. The app explicitly warns if `install` still exists.

Client/platform reach:

- The main experience runs in a browser.
- The UI includes desktop, tablet, and mobile responsive CSS.
- The dashboard asks mobile/tablet users to rotate to landscape orientation.
- There is no native iOS, Android, or desktop client in this repository.

## Operational Requirements

Installation flow:

1. Serve the repository from a PHP-capable web server.
2. Visit `/install/`.
3. Pass server checks: PHP version, cURL, PDO, OpenSSL, writable config, writable public directory, and URL fopen.
4. Provide MySQL credentials and create the schema from `install/assets/sql/krypto.sql`.
5. Configure website URL/path and create the admin user.
6. Trigger initial load/cron endpoints.
7. Remove or block the `install` directory before production use.

Recurring jobs tracked by the admin cron page:

- `app/src/CryptoApi/actions/CheckNotification.php` every 60 seconds.
- `app/modules/kr-trade/src/actions/generateLeaderBoard.php` every 18,000 seconds.
- `app/src/App/actions/cronCleanCache.php` every 3,600 seconds.
- `app/src/CryptoApi/actions/SyncExchanges.php` every 43,200 seconds.
- `app/src/CryptoApi/actions/SyncCoin.php` every 43,200 seconds.
- `app/modules/kr-trade/src/actions/CronLimitOrder.php` every 60 seconds.

## Current Repository Health

Observed during this analysis:

- No GitHub issue comments, PR comments, inline review comments, or PR reviews existed for issue #1 / PR #2 at analysis time.
- No root CI workflow or contribution guide was present in the working tree.
- No root `.gitignore` was present.
- `vendor/`, `assets/bower/`, and `assets/node_modules/` are committed, making the repository large and tightly coupled to vendored dependencies.
- `composer.json` has no explicit PHP platform requirement even though the installer checks for PHP 7.0.0 or newer.
- Composer CLI was not available in the local execution environment, so Composer validation and platform requirement checks could not be run locally.
- PHP CLI 8.3.30 syntax validation completed for 370 first-party PHP files with no parse errors.
- PHP 8.3 emitted deprecation notices for optional parameters declared before required parameters in:
  - `app/modules/kr-trade/src/0Exchange.php`
  - `app/modules/kr-payment/src/Banktransfert.php`
  - `app/src/Lang/Lang.php`
  - `app/src/CryptoApi/CryptoOrder.php`
  - `app/src/User/User.php`

## Main Risks And Constraints

- The app suppresses runtime error display by default in `App::_loadPlatform()`, which can hide operational faults unless server logs are monitored.
- Many action endpoints manually include bootstrap files and depend on `$_SERVER['DOCUMENT_ROOT']` plus `FILE_PATH`; deployments with unusual document roots need careful configuration.
- Module `config.json` files are present, but current module loader methods return `true` for config checks and enablement, so module toggling by config is not active.
- There are several legacy dependency patterns: Bower assets, committed Composer vendor files, old PHP libraries, and no reproducible root Node install.
- The dashboard and integration code make many external service assumptions; provider API changes can break features independently of this repository.
- Several features handle money, trading, withdrawals, KYC, and user-uploaded documents, so production deployment needs a separate security and compliance review before real customer use.
- The default installer seed data includes many disabled provider settings, placeholder support data, demo text, and example wallet/donation addresses that should be reviewed before launch.

## Practical Conclusion

Krypto is best understood as a monolithic PHP/MySQL cryptocurrency SaaS script. It can run on conventional PHP hosting or a VPS/container stack with MySQL, writable runtime directories, scheduled jobs, and outbound API access. It is written primarily in PHP with jQuery-era frontend assets and a large set of Composer/Bower dependencies committed to the repository.

For evaluation or production planning, the next useful work would be to add a reproducible local environment, document exact PHP/MySQL versions, add a `.gitignore`, decide whether vendored dependencies should stay committed, and address PHP 8.x deprecations before relying on newer runtimes.
