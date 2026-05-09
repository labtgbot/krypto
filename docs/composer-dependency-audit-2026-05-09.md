# Composer Dependency Audit 2026-05-09

This report covers issue #53. Composer was installed as a temporary verified
`/tmp/composer.phar` binary for the audit and was not committed to the
repository.

## Environment

- PHP CLI: 8.3.30
- Composer: 2.9.7
- Composer platform override in `composer.json`: PHP 7.4.33
- Installer runtime requirement: PHP 7.4.0 or newer

## Validation

Initial `composer validate --strict` failed with exit code 2:

- missing `name`
- missing `description`
- missing `license`
- exact root constraint: `omnipay/paypal` `2.6.4`
- unbounded root constraint: `codename065/coinbase-commerce` `*`

After the metadata changes and lock refresh, `composer validate --strict`
passes with exit code 0:

```text
./composer.json is valid
```

## Security Audit

Initial `composer audit --locked --abandoned=report` found 17 advisories across
6 packages:

- `guzzlehttp/guzzle`: 5 advisories
- `guzzlehttp/psr7`: 2 advisories
- `nesbot/carbon`: 1 advisory
- `phpmailer/phpmailer`: 4 advisories
- `symfony/http-foundation`: 4 advisories
- `symfony/polyfill-php55`: 1 advisory

The same audit reported 2 abandoned packages:

- `aferrandini/phpqrcode`, suggested replacement `endroid/qr-code`
- `guzzle/guzzle`, suggested replacement `guzzlehttp/guzzle`

After the dependency updates, `composer audit --locked --abandoned=report`
finds 5 remaining advisories across 2 packages:

- `nesbot/carbon`: `PKSA-csyb-yc4p-mnbs`
- `symfony/http-foundation`: `PKSA-365x-2zjk-pt47`
- `symfony/http-foundation`: `PKSA-b35n-565h-rs4q`
- `symfony/http-foundation`: `PKSA-9w98-4rwq-spxr`
- `symfony/http-foundation`: `PKSA-324d-pqmd-hptz`

The 2 abandoned package warnings remain and are documented below.

`composer outdated --direct --locked` also reports abandoned direct
requirements from current Packagist metadata:

- `aferrandini/phpqrcode`, suggested replacement `endroid/qr-code`
- `coinbase/coinbase`, no suggested replacement
- `facebook/graph-sdk`, no suggested replacement
- `hanischit/kraken-api`, no suggested replacement
- `milqmedia/poeditor-api-client`, no suggested replacement
- `paypal/rest-api-sdk-php`, suggested replacement `paypal/paypal-server-sdk`
- `php-http/guzzle6-adapter`, suggested replacement `guzzlehttp/guzzle` or
  `php-http/guzzle7-adapter`
- `php-http/message-factory`, suggested replacement `psr/http-factory`
- `sonata-project/google-authenticator`, no suggested replacement

## Updated Dependencies

The safe patch-level dependency update reduced the active advisory count from
17 to 5 without changing first-party application code:

- `guzzlehttp/guzzle`: `6.3.3` to `6.5.8`
- `guzzlehttp/psr7`: `1.4.2` to `1.9.1`
- `guzzlehttp/promises`: `1.3.1` to `1.5.3`
- `phpmailer/phpmailer`: `6.0.5` to `6.12.0`
- `symfony/polyfill-mbstring`: `1.9.0` to `1.37.0`
- `symfony/polyfill-php54`: `1.9.0` to `1.20.0`
- `symfony/polyfill-php55`: `1.9.0` to `1.20.0`
- `psr/http-message`: `1.0.1` to `1.1`
- `paragonie/random_compat`: `2.0.17` to `2.0.21`

Composer also added transitive packages required by the updated dependency
graph:

- `ralouphie/getallheaders`: `3.0.3`
- `symfony/polyfill-intl-idn`: `1.37.0`
- `symfony/polyfill-intl-normalizer`: `1.37.0`

## Accepted Temporary Risks

The remaining advisories are not ignored in Composer config. They are accepted
temporarily because the required fixes are major dependency migrations that need
payment, RSS, and QR-code regression coverage.

### `symfony/http-foundation`

Current version: `v2.8.45`

Root cause:

- `omnipay/common` `v2.3.4` requires `symfony/http-foundation ~2.1`.
- The root package also requires the Omnipay 2.x metapackage
  `league/omnipay`, `omnipay/paypal`, `omnipay/stripe`,
  `aleksandrzhiliaev/omnipay-advcash`, and `coingate/omnipay-coingate`.
- Composer 2.9 blocks updates within the Symfony 2.x line because every 2.x
  candidate is still affected by active security advisories.

Safe rollout plan:

1. Inventory whether any first-party code still instantiates Omnipay classes.
2. If Omnipay is unused, remove the Omnipay 2.x packages and committed vendor
   tree in a dedicated PR.
3. If Omnipay is still required, migrate gateways to Omnipay 3-compatible
   packages and verify PayPal, Stripe, CoinGate, and Advcash flows.
4. Re-run `composer audit` and remove this accepted risk once
   `symfony/http-foundation` can move to a fixed supported branch.

### `nesbot/carbon`

Current version: `1.33.0`

Root cause:

- `illuminate/support` `v5.5.43` requires `nesbot/carbon ^1.24.1`.
- `illuminate/support` 5.x is held by `milon/barcode` 5.x and `vinelab/rss`
  1.x.

Safe rollout plan:

1. Add focused coverage for barcode and RSS/news behavior.
2. Move `vinelab/rss` to 2.x and `milon/barcode` to a maintained major line.
3. Move `illuminate/support` to a version that allows Carbon 2.
4. Update `nesbot/carbon` to at least `2.72.6` and re-run `composer audit`.

### Abandoned Packages

`aferrandini/phpqrcode` remains because direct-deposit QR rendering uses the
legacy `QRcode` API. Replacing it with `endroid/qr-code` needs a targeted
runtime change and screenshot/manual verification of deposit QR screens.

`guzzle/guzzle` remains as a transitive dependency of `omnipay/common` 2.x. It
should disappear as part of the Omnipay migration described above.

The additional abandoned direct dependencies reported by
`composer outdated --direct --locked` are not changed in this PR because they
map to legacy payment, OAuth, exchange, POEditor, and HTTP adapter integrations.
Each replacement needs a feature-specific migration and live/API-compatible
test plan rather than a blind package swap.

### Dev-Master Constraints

The following root requirements still use `dev-master` and are accepted
temporarily:

- `codename065/coinbase-commerce`: Packagist exposes only `dev-master`.
- `coingate/omnipay-coingate`: the committed lock uses the legacy Omnipay 2.x
  branch; the current branch metadata has moved to Omnipay 3 and must be
  handled with the Omnipay migration.
- `php-curl-class/php-curl-class`: `infiniweb/fixer-api-php` requires
  `php-curl-class/php-curl-class dev-master`, so this cannot be stabilized
  without replacing or removing the Fixer wrapper.

## Commands

```bash
php /tmp/composer.phar validate --strict
php /tmp/composer.phar audit --locked --abandoned=report
php scripts/lint_php.php
php scripts/run_tests.php
```
