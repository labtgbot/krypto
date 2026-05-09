# Authenticated Encryption Migration

Issue #55 replaces new reversible secret writes with a versioned authenticated
encryption envelope while preserving reads of existing AES-CBC values.

## Production Use Inventory

### Secrets and tokens

These values must use `App::_encryptSecret()` for new writes and
`App::_decryptSecret()` or the compatibility decrypt path for reads:

- `settings_krypto` rows marked `encrypted_settings = 1`, including SMTP,
  OAuth, reCAPTCHA, calendar, POEditor, payment gateway credentials, and
  ChangeNOW credentials.
- Payment gateway credentials submitted by `app/modules/kr-admin/src/actions/savePayment.php`.
- User and global exchange API credentials submitted by
  `app/modules/kr-trade/src/actions/saveThirdpartySettings.php`.
- Google 2FA secrets in `googletfs_krypto.secret_googletfs`.
- Password reset links, account activation links, and withdraw confirmation
  links that currently need to recover plaintext routing data.

### Opaque UI identifiers

These values continue to use `App::encrypt_decrypt('encrypt', ...)` for now
because the legacy helper is deterministic and several flows rely on stable
path fragments or form identifiers:

- User, chat, dashboard, payment, manager, identity, blockfolio, and balance UI
  identifiers.
- Public upload directory fragments under `public/identity`, `public/proof`,
  and `public/bank-proof`.
- Payment redirect metadata where the value is an opaque request identifier
  rather than a stored long-lived secret.

These identifiers are not treated as secret storage by this migration. Replacing
them should be handled separately with signed, purpose-scoped identifiers or
server-side lookup tokens.

### Legacy compatibility

Existing AES-CBC strings remain readable through `App::_decryptSecret()` and
`App::encrypt_decrypt('decrypt', ...)`. New secret writes should produce
`krypto:v2:*` ciphertext only.

## Versioned Ciphertext Format

New ciphertext uses this text envelope:

```text
krypto:v2:<cipher>:<base64url(nonce || tag || ciphertext)>
```

When Sodium is available the cipher id is `sodium-xchacha20poly1305` and the
payload is `nonce || ciphertext_with_tag` from
`sodium_crypto_aead_xchacha20poly1305_ietf_encrypt()`.

When Sodium is unavailable, OpenSSL `aes-256-gcm` is used with a 12-byte nonce
and 16-byte tag. The payload is `nonce || tag || ciphertext`.

The AEAD associated data is the envelope prefix plus cipher id, so ciphertext
cannot be silently moved between supported algorithms.

## Lazy Migration Plan

1. Deploy the compatibility reader before changing production writes. This is
   included in `App::_decryptSecret()`, which routes `krypto:v2:*` and legacy
   CBC values.
2. All new settings writes with `$encrypt = true` now use
   `App::_encryptSecret()` and update `encrypted_settings` on both INSERT and
   UPDATE.
3. Admin payment and exchange credential saves now write AEAD ciphertext. Rows
   that are edited through those forms migrate naturally on save.
4. Google 2FA, password reset, activation, and withdraw confirmation flows write
   AEAD tokens/secrets immediately. Existing links and rows continue to decrypt
   through the legacy fallback until they expire or are overwritten.
5. For a proactive database migration, run a controlled script that reads each
   encrypted row through `App::_decryptSecret()`, writes it back through
   `App::_encryptSecret()`, and leaves rows unchanged when decrypt returns
   `null`. Do this per table with backups and row counts.

## Rollback Notes

Do not roll back to code that only understands AES-CBC after AEAD ciphertext has
been written. If rollback is unavoidable, first restore a database backup from
before the AEAD deployment, or run a reverse migration that decrypts each
`krypto:v2:*` value with the current code and writes the old CBC envelope with
`App::encrypt_decrypt('encrypt', ...)`.

Keep the same `CRYPTED_KEY` during rollback and forward migration. Changing the
key makes both legacy CBC values and AEAD values unreadable.

## Token Storage Follow-up

Some current links recover plaintext routing data from encrypted tokens. That is
compatible with AEAD, but not ideal for one-time tokens that only need server
validation. A follow-up should move password reset, activation, and withdraw
confirmation tokens to purpose-scoped random tokens stored as HMAC or password
hash values with expiry and single-use semantics.
