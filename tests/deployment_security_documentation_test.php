<?php

/**
 * Regression coverage for issue #58 deployment documentation.
 *
 * Production operators need a single checklist that prevents direct web access
 * to installer, config, vendor metadata/tests, and mutable upload storage while
 * preserving the install-time write permissions required by the legacy
 * installer.
 */

$root = dirname(__DIR__);
$doc = $root.'/docs/production-deployment-security.md';

function assertDeploymentDoc($condition, $message) {
    if (!$condition) {
        throw new Exception($message);
    }
}

assertDeploymentDoc(
    file_exists($doc),
    'Production deployment security documentation must exist.'
);

$source = file_get_contents($doc);
assertDeploymentDoc($source !== false, 'Cannot read production deployment security documentation.');

foreach ([
    'Production Deployment Security Checklist',
    'Document Root',
    'install/',
    'config/',
    'config/config.settings.php',
    'vendor/',
    'app/',
    'app/src/',
    'app/modules/kr-api/src/Api.php',
    'app/modules/kr-api/config.json',
    'composer.json',
    'composer.lock',
    'public/user',
    'public/logo',
    'public/chat',
    'public/identity',
    'public/proof',
    'public/bank-proof',
    'public/qrcode',
    'Install-Time Permissions',
    'Runtime Permissions',
    'writable during installation',
    'read-only after installation',
    'Apache',
    'Nginx',
    'IIS',
    'deny all',
    'return 404',
    'requestFiltering',
    'directoryBrowse enabled="false"',
    'remove or block install/',
    'KRYPTO_DATA_API_KEY',
    'KRYPTO_RSS2JSON_API_KEY',
    'KRYPTO_ETHERSCAN_API_KEY',
    'docs/upload-storage-deployment.md',
] as $needle) {
    assertDeploymentDoc(
        strpos($source, $needle) !== false,
        'Production deployment security documentation missing: '.$needle
    );
}

$readme = file_get_contents($root.'/README.md');
assertDeploymentDoc($readme !== false, 'Cannot read README.md.');
assertDeploymentDoc(
    strpos($readme, 'docs/production-deployment-security.md') !== false,
    'README.md must link to the production deployment security checklist.'
);

$rootHtaccess = file_get_contents($root.'/.htaccess');
assertDeploymentDoc($rootHtaccess !== false, 'Cannot read root .htaccess.');
foreach ([
    'Options -Indexes',
    'RewriteRule ^app/src/ - [F,L]',
    'RewriteRule ^app/modules/[^/]+/src/(?!actions(?:/|$)) - [F,L]',
    'RewriteRule ^app/modules/[^/]+/config\.json$ - [F,L]',
] as $needle) {
    assertDeploymentDoc(
        strpos($rootHtaccess, $needle) !== false,
        'Root .htaccess missing app source guard: '.$needle
    );
}

echo "Production deployment security documentation checks passed.\n";

?>
