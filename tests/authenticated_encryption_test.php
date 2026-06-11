<?php

/**
 * Regression coverage for authenticated encryption of secrets and tokens.
 *
 * The retired App::encrypt_decrypt('encrypt', ...) CBC path was deterministic
 * and had no authentication tag. New reversible writes must use a versioned
 * AEAD envelope while old CBC values remain readable until rows are migrated.
 */

$root = dirname(__DIR__);

foreach ([
    'MYSQL_HOST' => 'localhost',
    'MYSQL_USER' => 'root',
    'MYSQL_PASSWD' => '',
    'MYSQL_PORT' => '3306',
    'MYSQL_DATABASE' => 'krypto_test',
    'CRYPTED_KEY' => 'authenticated-encryption-test-key',
    'APP_URL' => 'https://example.test',
    'APP_URL_FORCE' => false,
    'FILE_PATH' => '',
] as $constant => $value) {
    if (!defined($constant)) {
        define($constant, $value);
    }
}

require_once $root.'/app/src/MySQL/MySQL.php';
require_once $root.'/app/src/App/App.php';

function assertTrueAuthenticatedEncryption($condition, $message) {
    if (!$condition) {
        throw new Exception($message);
    }
}

function assertSameAuthenticatedEncryption($expected, $actual, $message) {
    if ($expected !== $actual) {
        throw new Exception($message.' Expected '.var_export($expected, true).', got '.var_export($actual, true));
    }
}

function mutateAuthenticatedCiphertext($ciphertext) {
    $payloadOffset = strrpos($ciphertext, ':') + 1;
    $current = substr($ciphertext, $payloadOffset, 1);
    return substr($ciphertext, 0, $payloadOffset).($current === 'A' ? 'B' : 'A').substr($ciphertext, $payloadOffset + 1);
}

function legacyAuthenticatedEncryptionCiphertext($plaintext) {
    $output = openssl_encrypt(
        (string) $plaintext,
        'AES-256-CBC',
        hash('sha256', CRYPTED_KEY),
        0,
        substr(hash('sha256', strrev(CRYPTED_KEY)), 0, 16)
    );

    if ($output === false) {
        throw new Exception('Unable to build legacy CBC fixture.');
    }

    return base64_encode($output);
}

$plaintext = 'secret-api-key-'.bin2hex(random_bytes(6));
$ciphertext = App::_encryptSecret($plaintext);
$ciphertextAgain = App::_encryptSecret($plaintext);
$compatCiphertext = App::encrypt_decrypt('encrypt', $plaintext);
$compatCiphertextAgain = App::encrypt_decrypt('encrypt', $plaintext);

assertTrueAuthenticatedEncryption(strpos($ciphertext, 'krypto:v2:') === 0, 'New secret ciphertext must be versioned.');
assertTrueAuthenticatedEncryption($ciphertext !== $ciphertextAgain, 'New secret encryption must use a unique nonce.');
assertSameAuthenticatedEncryption($plaintext, App::_decryptSecret($ciphertext), 'Versioned secret must decrypt through the secret helper.');
assertSameAuthenticatedEncryption($plaintext, App::encrypt_decrypt('decrypt', $ciphertext), 'Compatibility decrypt entry point must route versioned values.');
assertTrueAuthenticatedEncryption(strpos($compatCiphertext, 'krypto:v2:') === 0, 'Compatibility encrypt entry point must create versioned AEAD ciphertext.');
assertTrueAuthenticatedEncryption($compatCiphertext !== $compatCiphertextAgain, 'Compatibility encrypt entry point must use a unique nonce.');
assertSameAuthenticatedEncryption($plaintext, App::encrypt_decrypt('decrypt', $compatCiphertext), 'Compatibility decrypt entry point must read AEAD ciphertext.');

$tampered = mutateAuthenticatedCiphertext($ciphertext);
assertSameAuthenticatedEncryption(null, App::_decryptSecret($tampered), 'Tampered versioned ciphertext must fail authentication.');

$legacyCiphertext = legacyAuthenticatedEncryptionCiphertext('legacy-cbc-secret');
assertTrueAuthenticatedEncryption(strpos($legacyCiphertext, 'krypto:v2:') !== 0, 'Legacy fixture must keep the old CBC envelope.');
assertSameAuthenticatedEncryption('legacy-cbc-secret', App::_decryptSecret($legacyCiphertext), 'Old CBC settings must remain readable.');
assertSameAuthenticatedEncryption('legacy-cbc-secret', App::encrypt_decrypt('decrypt', $legacyCiphertext), 'Compatibility decrypt entry point must keep reading old CBC values.');

$wrongKeyPhp = <<<'PHP'
foreach ([
    'MYSQL_HOST' => 'localhost',
    'MYSQL_USER' => 'root',
    'MYSQL_PASSWD' => '',
    'MYSQL_PORT' => '3306',
    'MYSQL_DATABASE' => 'krypto_test',
    'CRYPTED_KEY' => 'authenticated-encryption-wrong-key',
    'APP_URL' => 'https://example.test',
    'APP_URL_FORCE' => false,
    'FILE_PATH' => '',
] as $constant => $value) {
    if (!defined($constant)) {
        define($constant, $value);
    }
}
require_once '__KRYPTO_ROOT__/app/src/MySQL/MySQL.php';
require_once '__KRYPTO_ROOT__/app/src/App/App.php';
echo App::_encryptSecret('wrong-key-secret');
PHP;

$wrongKeyPhp = str_replace('__KRYPTO_ROOT__', addslashes($root), $wrongKeyPhp);
$wrongKeyCiphertext = shell_exec(escapeshellarg(PHP_BINARY).' -r '.escapeshellarg($wrongKeyPhp));
assertTrueAuthenticatedEncryption(is_string($wrongKeyCiphertext) && trim($wrongKeyCiphertext) !== '', 'Wrong-key fixture ciphertext must be generated.');
assertSameAuthenticatedEncryption(null, App::_decryptSecret(trim($wrongKeyCiphertext)), 'Ciphertext from another CRYPTED_KEY must not decrypt.');

$appSource = file_get_contents($root.'/app/src/App/App.php');
assertTrueAuthenticatedEncryption(strpos($appSource, 'function _encryptSecret(') !== false, 'App must expose a secret encryption helper.');
assertTrueAuthenticatedEncryption(strpos($appSource, 'function _decryptSecret(') !== false, 'App must expose a secret decryption helper.');
assertTrueAuthenticatedEncryption(strpos($appSource, "if(\$action == 'encrypt') return self::_encryptSecret(\$string);") !== false, 'Compatibility encrypt entry point must route new writes to AEAD.');
assertTrueAuthenticatedEncryption(strpos($appSource, 'function _encryptLegacyValue(') === false, 'App must not keep a legacy CBC encryption path.');
assertTrueAuthenticatedEncryption(strpos($appSource, '@deprecated') !== false && strpos($appSource, 'function _decryptLegacyValue(') !== false, 'Legacy CBC decrypt fallback must be marked deprecated for migration only.');
assertTrueAuthenticatedEncryption(strpos($appSource, 'App::_decryptSecret($vSettings') !== false, 'Encrypted settings must decrypt through the versioned secret helper.');
assertTrueAuthenticatedEncryption(strpos($appSource, 'encrypted_settings=:encrypted_settings') !== false, 'Settings updates must preserve the encrypted_settings flag.');

$savePaymentSource = file_get_contents($root.'/app/modules/kr-admin/src/actions/savePayment.php');
assertTrueAuthenticatedEncryption(strpos($savePaymentSource, "App::encrypt_decrypt('encrypt', \$_POST['kr-adm-") === false, 'Payment credentials must not be newly written with legacy CBC.');
assertTrueAuthenticatedEncryption(strpos($savePaymentSource, 'App::_encryptSecret') !== false, 'Payment credentials must use authenticated encryption.');

$legacyThirdpartyAction = $root.'/app/modules/kr-trade/src/actions/saveThirdpartySettings.php';
assertTrueAuthenticatedEncryption(!file_exists($legacyThirdpartyAction), 'Legacy exchange credential action must be removed after OPEN-05.');

$userSource = file_get_contents($root.'/app/src/User/User.php');
assertTrueAuthenticatedEncryption(strpos($userSource, "secret_googletfs' => App::_encryptSecret") !== false, 'Google 2FA secrets must use authenticated encryption.');
assertTrueAuthenticatedEncryption(strpos($userSource, 'App::_decryptSecret($r[0][\'secret_googletfs\'])') !== false, 'Google 2FA secrets must decrypt through authenticated helper with legacy fallback.');
assertTrueAuthenticatedEncryption(strpos($userSource, 'USER_RESET_LINK') !== false && strpos($userSource, 'App::_encryptSecret($Email') !== false, 'Password reset links must use authenticated encryption.');
assertTrueAuthenticatedEncryption(strpos($userSource, '$activationCode = App::_encryptSecret') !== false, 'Activation links must use authenticated encryption.');

$balanceSource = file_get_contents($root.'/app/modules/kr-trade/src/Balance.php');
assertTrueAuthenticatedEncryption(strpos($balanceSource, 'App::encrypt_decrypt(\'encrypt\', $token') === false, 'Retired withdraw confirmation tokens must not be newly written with legacy CBC.');
assertTrueAuthenticatedEncryption(strpos($balanceSource, 'App::_encryptSecret($token)') === false, 'Legacy withdraw confirmation tokens are retired with custodial withdrawals.');

$migrationDoc = $root.'/docs/authenticated-encryption-migration.md';
assertTrueAuthenticatedEncryption(file_exists($migrationDoc), 'Authenticated encryption migration plan must be documented.');
$migrationDocSource = file_get_contents($migrationDoc);
assertTrueAuthenticatedEncryption(strpos($migrationDocSource, 'legacy custodial exchange runtime') !== false, 'Migration documentation must note that legacy exchange credential writes were retired.');
foreach ([
    'Production Use Inventory',
    'Versioned Ciphertext Format',
    'Lazy Migration Plan',
    'Rollback Notes',
    'Token Storage Follow-up',
] as $needle) {
    assertTrueAuthenticatedEncryption(strpos($migrationDocSource, $needle) !== false, 'Migration documentation missing section: '.$needle);
}

echo "Authenticated encryption checks passed.\n";

?>
