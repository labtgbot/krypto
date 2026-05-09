# System Audit 2026-05-09

This audit covers the current Krypto PHP application state for issue #51. It focuses on high-impact security and reliability risks that can be verified in this repository without live production credentials or a deployed database.

## Scope

- First-party PHP entry points under `app/`, `install/`, `public/`, `index.php`, and `dashboard.php`.
- Existing automated checks in `scripts/lint_php.php` and `scripts/run_tests.php`.
- Public upload flows for profile pictures, logos, chat attachments, identity documents, payment proofs, and bank-transfer proofs.
- Static review of Composer metadata, GitHub Actions CI, public writable directories, and recently added ChangeNOW guardrails.

## Baseline

- `php scripts/lint_php.php` passed for 402 first-party PHP files before the hardening changes.
- `php scripts/run_tests.php` passed for 18 checks before the hardening changes.
- Composer CLI is not available in the prepared environment, so `composer validate` and dependency advisory checks could not be run locally.

## Public Upload Hardening

The audit found several upload paths that wrote user-controlled filenames below `public/*`. The chat attachment flow accepted any extension, while identity and payment proof flows did not consistently use the shared allowlist helper. These paths are high risk on Apache/PHP hosting because a published PHP-like file can become executable content when upload directories are served directly.

Implemented mitigations:

- Added `App::_assertUploadedFileIsSafe()` to validate upload errors and extension allowlists before `move_uploaded_file()`.
- Added `App::_getSafeUploadedFileName()` to strip path separators, parent path fragments, and executable inner extensions from stored basenames.
- Updated profile pictures, admin logos, chat attachments, identity documents, payment proofs, and bank-transfer proofs to use the shared upload guard.
- Removed SVG from admin logo uploads until an SVG sanitizer exists. Existing checked-in SVG assets are unchanged.
- Added `public/.htaccess` to deny PHP-like files and directory listings under public upload storage on Apache-compatible deployments.

## Automated Verification

`tests/upload_security_hardening_test.php` verifies:

- Safe upload filenames preserve allowed final extensions but do not preserve path fragments or executable inner extensions.
- Extension checks are case-insensitive for legitimate documents.
- PHP-like uploads are rejected before file movement.
- Each first-party upload path using `move_uploaded_file()` also calls the shared validator and safe filename helper.
- Public upload storage has an Apache `.htaccess` guard for PHP-like extensions.
- This audit report remains present for issue #51 traceability.

## Remaining Audit Backlog

- Run `composer validate` and `composer audit` in an environment with Composer installed, then update or replace vulnerable dependencies.
- Add server-level Nginx/IIS equivalents for the Apache upload execution guard.
- Replace deterministic AES-CBC helper usage with authenticated encryption for newly written secrets and tokens, with a migration plan for existing encrypted rows.
- Review every AJAX/action endpoint for CSRF coverage and add a shared token check where state changes occur.
- Add content validation for images and PDFs, including MIME sniffing and image decoding, rather than relying only on extensions.
- Review direct public access to `install/`, `config/`, `vendor/`, and mutable upload directories in deployment documentation.
- Add a reproducible local container for PHP/MySQL so database-backed security tests can run without production credentials.
