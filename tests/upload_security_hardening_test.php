<?php

/**
 * Regression coverage for issue #51 audit findings around public uploads.
 *
 * User-controlled files are stored below public/* in several legacy flows. The
 * app must validate allowed extensions before moving the file and generate a
 * server-side safe basename so PHP or path-control payloads cannot be published
 * as executable web content.
 */

$root = dirname(__DIR__);

foreach ([
    'MYSQL_HOST' => 'localhost',
    'MYSQL_USER' => 'root',
    'MYSQL_PASSWD' => '',
    'MYSQL_PORT' => '3306',
    'MYSQL_DATABASE' => 'krypto_test',
    'CRYPTED_KEY' => 'upload-security-test-key',
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

function assertTrueUploadSecurity($condition, $message) {
    if (!$condition) {
        throw new Exception($message);
    }
}

function assertThrowsUploadSecurity($callable, $message) {
    try {
        $callable();
    } catch (Exception $e) {
        return;
    }

    throw new Exception($message);
}

$safeName = App::_getSafeUploadedFileName(['name' => '../avatar.php.jpg'], 'prefix.with.dot');
assertTrueUploadSecurity(strpos($safeName, '/') === false, 'Safe upload name must not contain path separators.');
assertTrueUploadSecurity(strpos($safeName, '..') === false, 'Safe upload name must not contain parent path fragments.');
assertTrueUploadSecurity(strpos($safeName, '.php.') === false, 'Safe upload name must not preserve executable inner extensions.');
assertTrueUploadSecurity(substr($safeName, -4) === '.jpg', 'Safe upload name must preserve the final allowed extension.');

assertTrueUploadSecurity(
    App::_getFileExtensionAllowed(['name' => 'Invoice.PDF'], ['pdf']),
    'Extension checks must be case-insensitive for legitimate documents.'
);
assertTrueUploadSecurity(
    !App::_getFileExtensionAllowed(['name' => 'shell.php'], ['pdf', 'jpg', 'jpeg', 'png', 'gif']),
    'Executable PHP extensions must not pass the public upload allowlist.'
);

assertThrowsUploadSecurity(function() {
    App::_assertUploadedFileIsSafe(['name' => 'payload.php', 'tmp_name' => '/tmp/payload', 'error' => UPLOAD_ERR_OK], ['jpg']);
}, 'Unsafe executable upload should be rejected before move_uploaded_file().');

foreach ([
    'app/src/User/User.php',
    'app/src/App/App.php',
    'app/modules/kr-chat/src/actions/roomSendMessage.php',
    'app/modules/kr-identity/src/Identity.php',
    'app/modules/kr-manager/src/Manager.php',
    'app/modules/kr-payment/src/Banktransfert.php',
] as $relativePath) {
    $source = file_get_contents($root.'/'.$relativePath);
    assertTrueUploadSecurity($source !== false, 'Cannot read '.$relativePath);

    if (strpos($source, 'move_uploaded_file(') !== false) {
        assertTrueUploadSecurity(
            strpos($source, '_assertUploadedFileIsSafe') !== false,
            $relativePath.' must validate uploads before move_uploaded_file().'
        );
        assertTrueUploadSecurity(
            strpos($source, '_getSafeUploadedFileName') !== false,
            $relativePath.' must use server-side safe upload filenames.'
        );
    }
}

$publicHtaccess = $root.'/public/.htaccess';
assertTrueUploadSecurity(file_exists($publicHtaccess), 'public/.htaccess must exist to block script execution in upload storage.');
$publicHtaccessSource = file_get_contents($publicHtaccess);
assertTrueUploadSecurity(strpos($publicHtaccessSource, 'FilesMatch') !== false, 'public/.htaccess must declare a FilesMatch guard.');
assertTrueUploadSecurity(stripos($publicHtaccessSource, 'php') !== false, 'public/.htaccess must explicitly cover PHP-like extensions.');

$auditDoc = $root.'/docs/system-audit-2026-05-09.md';
assertTrueUploadSecurity(file_exists($auditDoc), 'System audit report should document issue #51 scope and findings.');
$auditDocSource = file_get_contents($auditDoc);
foreach ([
    'Public Upload Hardening',
    'Automated Verification',
    'Remaining Audit Backlog',
] as $needle) {
    assertTrueUploadSecurity(strpos($auditDocSource, $needle) !== false, 'Audit report missing section: '.$needle);
}

echo "Upload security hardening checks passed.\n";

?>
