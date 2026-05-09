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

$uploadSecurityTempFiles = [];
function createUploadSecurityTempFile($name, $contents) {
    global $uploadSecurityTempFiles;

    $path = tempnam(sys_get_temp_dir(), 'krypto-upload-');
    if ($path === false) {
        throw new Exception('Cannot create temporary upload fixture.');
    }

    if (file_put_contents($path, $contents) === false) {
        throw new Exception('Cannot write temporary upload fixture.');
    }

    $uploadSecurityTempFiles[] = $path;
    return [
        'name' => $name,
        'tmp_name' => $path,
        'error' => UPLOAD_ERR_OK,
        'size' => strlen($contents),
    ];
}

register_shutdown_function(function() use (&$uploadSecurityTempFiles) {
    foreach ($uploadSecurityTempFiles as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }
});

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

$uploadMimeMap = App::_getUploadMimeTypeMap();
foreach ([
    'avatar/logo/chat image uploads' => ['jpg', 'jpeg', 'png', 'gif'],
    'identity/proof/bank-proof uploads' => ['pdf', 'jpg', 'jpeg', 'png'],
] as $flow => $extensions) {
    foreach ($extensions as $extension) {
        assertTrueUploadSecurity(
            array_key_exists($extension, $uploadMimeMap),
            $flow.' must have a MIME/content rule for .'.$extension
        );
    }
}
foreach (['application/pdf', 'image/jpeg', 'image/png'] as $mimeType) {
    assertTrueUploadSecurity(
        in_array($mimeType, App::_getAllowedUploadMimeTypes(['pdf', 'jpg', 'jpeg', 'png']), true),
        'Shared MIME allowlist must include '.$mimeType
    );
}

$validPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=');
$validJpeg = base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABBQJ//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPwF//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPwF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQAGPwJ//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPyF//9k=');
$validPdf = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";
$phpPayload = "<?php echo 'owned'; ?>\n";

assertTrueUploadSecurity(
    App::_assertUploadedFileIsSafe(createUploadSecurityTempFile('avatar.png', $validPng), ['png', 'jpg', 'jpeg', 'gif'], 'Image'),
    'Valid PNG images must pass upload content validation.'
);
assertTrueUploadSecurity(
    App::_assertUploadedFileIsSafe(createUploadSecurityTempFile('logo.jpg', $validJpeg), ['png', 'jpg', 'jpeg', 'gif'], 'Logo'),
    'Valid JPEG images must pass upload content validation.'
);
assertTrueUploadSecurity(
    App::_assertUploadedFileIsSafe(createUploadSecurityTempFile('identity.pdf', $validPdf), ['pdf', 'jpg', 'jpeg', 'png'], 'Identity document'),
    'Valid PDF documents must pass upload content validation.'
);

assertThrowsUploadSecurity(function() use ($phpPayload) {
    App::_assertUploadedFileIsSafe(createUploadSecurityTempFile('payload.jpg', $phpPayload), ['jpg', 'jpeg', 'png'], 'Image');
}, 'PHP payload with a .jpg extension must be rejected by content validation.');
assertThrowsUploadSecurity(function() use ($phpPayload) {
    App::_assertUploadedFileIsSafe(createUploadSecurityTempFile('payload.pdf', $phpPayload), ['pdf'], 'PDF');
}, 'PHP payload with a .pdf extension must be rejected by content validation.');
assertThrowsUploadSecurity(function() use ($validPng) {
    App::_assertUploadedFileIsSafe(createUploadSecurityTempFile('mismatch.jpg', $validPng), ['jpg', 'jpeg', 'png'], 'Image');
}, 'Upload guard must reject content whose MIME type does not match its extension.');
assertThrowsUploadSecurity(function() use ($validPdf) {
    App::_assertUploadedFileIsSafe(createUploadSecurityTempFile('mismatch.png', $validPdf), ['pdf', 'jpg', 'jpeg', 'png'], 'Identity document');
}, 'Upload guard must reject PDFs uploaded with image extensions.');

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

$identitySource = file_get_contents($root.'/app/modules/kr-identity/src/Identity.php');
assertTrueUploadSecurity(
    strpos($identitySource, 'Identity camera image') !== false && strpos($identitySource, '_assertUploadedFileIsSafe') !== false,
    'Identity webcam PNG writes must use the shared upload content validator.'
);

$publicHtaccess = $root.'/public/.htaccess';
assertTrueUploadSecurity(file_exists($publicHtaccess), 'public/.htaccess must exist to block script execution in upload storage.');
$publicHtaccessSource = file_get_contents($publicHtaccess);
assertTrueUploadSecurity(strpos($publicHtaccessSource, 'FilesMatch') !== false, 'public/.htaccess must declare a FilesMatch guard.');
assertTrueUploadSecurity(stripos($publicHtaccessSource, 'php') !== false, 'public/.htaccess must explicitly cover PHP-like extensions.');

$deploymentDoc = $root.'/docs/upload-storage-deployment.md';
assertTrueUploadSecurity(file_exists($deploymentDoc), 'Deployment docs must describe upload execution guards for non-Apache environments.');
$deploymentDocSource = file_get_contents($deploymentDoc);

foreach ([
    'public/user',
    'public/logo',
    'public/chat',
    'public/identity',
    'public/proof',
    'public/bank-proof',
] as $uploadDirectory) {
    assertTrueUploadSecurity(
        strpos($deploymentDocSource, $uploadDirectory) !== false,
        'Deployment docs must list current public upload directory: '.$uploadDirectory
    );
}

foreach ([
    'Apache',
    'public/.htaccess',
    'Nginx',
    'autoindex off',
    'try_files $uri =404',
    'php[0-9]?|phtml|phar',
    'IIS',
    'directoryBrowse enabled="false"',
    'requestFiltering',
    'fileExtension=".php" allowed="false"',
    'Reverse Proxy',
    'PHP-FPM',
    'Allowed Upload Reads',
    'MIME Sniffing',
    'finfo_file()',
    'getimagesize()',
    '%PDF-',
    '%%EOF',
    'PDF sanitizer',
] as $needle) {
    assertTrueUploadSecurity(
        strpos($deploymentDocSource, $needle) !== false,
        'Deployment docs missing upload execution guard detail: '.$needle
    );
}

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
