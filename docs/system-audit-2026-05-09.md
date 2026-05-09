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
- Added MIME sniffing, image decode validation, and minimal PDF magic/EOF checks to the shared upload guard.
- Updated profile pictures, admin logos, chat attachments, identity documents, payment proofs, and bank-transfer proofs to use the shared upload guard.
- Updated identity webcam PNG writes to validate decoded image content before publishing below `public/identity`.
- Removed SVG from admin logo uploads until an SVG sanitizer exists. Existing checked-in SVG assets are unchanged.
- Added `public/.htaccess` to deny PHP-like files and directory listings under public upload storage on Apache-compatible deployments.
- Added `docs/upload-storage-deployment.md` with Apache, Nginx, IIS, and reverse proxy guards for public upload storage.

## Automated Verification

`tests/upload_security_hardening_test.php` verifies:

- Safe upload filenames preserve allowed final extensions but do not preserve path fragments or executable inner extensions.
- Extension checks are case-insensitive for legitimate documents.
- PHP-like uploads are rejected before file movement.
- Valid PNG, JPEG, and PDF fixtures pass shared MIME/content validation.
- PHP payloads using `.jpg` or `.pdf` extensions and mismatched MIME uploads are rejected.
- Upload extension allowlists have matching MIME/content rules for avatar, logo, chat, identity, proof, and bank-proof flows.
- Each first-party upload path using `move_uploaded_file()` also calls the shared validator and safe filename helper.
- Identity webcam PNG writes call the shared upload content validator before `file_put_contents()`.
- Public upload storage has an Apache `.htaccess` guard for PHP-like extensions.
- Deployment documentation lists public upload directories, includes non-Apache web-server guards, and documents the PDF validation boundary.
- This audit report remains present for issue #51 traceability.

`tests/authenticated_encryption_test.php` verifies the issue #55 encryption
migration controls:

- New secret writes produce versioned AEAD ciphertext.
- Tampered AEAD ciphertext and ciphertext encrypted with another `CRYPTED_KEY`
  fail before plaintext is returned.
- Existing AES-CBC values remain readable through the compatibility path.
- Payment credentials, exchange credentials, 2FA secrets, reset links,
  activation links, and withdraw confirmation links do not newly write secrets
  through the legacy CBC helper.
- `docs/authenticated-encryption-migration.md` documents inventory, migration,
  rollback, and token-storage follow-up notes.

## Remaining Audit Backlog

- Run `composer validate` and `composer audit` in an environment with Composer installed, then update or replace vulnerable dependencies.
- Verify the documented upload execution guards in each production web-server configuration before enabling public uploads.
- Complete the follow-up token-storage migration from reversible encrypted links to HMAC/hash-backed one-time tokens where plaintext recovery is not required.
- Review every AJAX/action endpoint for CSRF coverage and add a shared token check where state changes occur.
- Add a deep PDF sanitizer or malware scanning pipeline if production compliance requires more than MIME sniffing and minimal magic/EOF validation.
- Review direct public access to `install/`, `config/`, `vendor/`, and mutable upload directories in deployment documentation.
- Add a reproducible local container for PHP/MySQL so database-backed security tests can run without production credentials.
