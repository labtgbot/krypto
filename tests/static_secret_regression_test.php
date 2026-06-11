<?php

/**
 * Regression coverage for issue #96: first-party modules must not ship live
 * secrets, internal API keys must be compared in constant time, and OAuth
 * accounts must authenticate by provider subject id instead of a shared
 * provider-name password.
 */

$root = dirname(__DIR__);

function assert_static_secret($condition, $message) {
    if (!$condition) {
        throw new Exception($message);
    }
}

function static_secret_source($root, $relative) {
    $path = $root.'/'.$relative;
    $source = file_get_contents($path);
    assert_static_secret($source !== false, 'Cannot read '.$relative);
    return $source;
}

$moduleRoot = $root.'/app/modules';
$forbiddenSecretHashes = [
    '85f49f0f9f4d13096329fdce8440f9693aaa9e37f1f6839d886995d08f5eef00' => 'legacy data API key',
    'cf1b37ba682afabc7970efded638dd0e9d7ef4c78011ac28b98999e364243e45' => 'legacy rss2json key',
    '597b0c2825090d5262dc03a0a1e8e7f95ed3d707cb84dec456d235c03f331128' => 'legacy Etherscan key',
];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($moduleRoot, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if (!$file->isFile()) {
        continue;
    }

    $extension = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
    if (!in_array($extension, ['php', 'json', 'js', 'css', 'tpl'], true)) {
        continue;
    }

    $source = file_get_contents($file->getPathname());
    assert_static_secret($source !== false, 'Cannot read module source: '.$file->getPathname());

    preg_match_all('/[A-Za-z0-9_=-]{16,}/', $source, $matches);
    foreach ($matches[0] as $token) {
        $tokenHash = hash('sha256', $token);
        if (array_key_exists($tokenHash, $forbiddenSecretHashes)) {
            throw new Exception('Hardcoded '.$forbiddenSecretHashes[$tokenHash].' must not be present in app/modules: '.$file->getPathname());
        }
    }
}

$apiSource = static_secret_source($root, 'app/modules/kr-api/src/Api.php');
assert_static_secret(strpos($apiSource, 'KRYPTO_DATA_API_KEY') !== false, 'Data API key must come from runtime configuration.');
assert_static_secret(strpos($apiSource, 'hash_equals(') !== false, 'Data API key comparison must use hash_equals.');
assert_static_secret(strpos($apiSource, 'private $api_key') === false, 'Data API key must not be a hardcoded class property.');

$rssSource = static_secret_source($root, 'app/modules/kr-news/src/RssFeed.php');
assert_static_secret(strpos($rssSource, 'KRYPTO_RSS2JSON_API_KEY') !== false, 'rss2json key must come from runtime configuration.');
assert_static_secret(strpos($rssSource, 'http_build_query') !== false, 'rss2json request query must be built from structured parameters.');

$etherSource = static_secret_source($root, 'app/modules/kr-blocksexplorer/src/Etherblock.php');
assert_static_secret(strpos($etherSource, 'KRYPTO_ETHERSCAN_API_KEY') !== false, 'Etherscan key must come from runtime configuration.');
assert_static_secret(strpos($etherSource, 'https://api.etherscan.io/api') !== false, 'Etherscan API calls must use HTTPS.');

$googleSource = static_secret_source($root, 'app/modules/kr-googleoauth/src/GoogleOauth.php');
assert_static_secret(strpos($googleSource, 'public function _getId(') !== false, 'Google OAuth must expose the provider subject id.');
assert_static_secret(strpos($googleSource, '_oauthCallbackID($this)') !== false, 'Google OAuth callback must authenticate by provider subject id.');

$userSource = static_secret_source($root, 'app/src/User/User.php');
assert_static_secret(strpos($userSource, '_generateOauthAccountSecret') !== false, 'OAuth fallback credentials must be random per account.');
assert_static_secret(strpos($userSource, '_migrateLegacyOauthIdentity') !== false, 'Legacy provider-name OAuth records must be migrated to subject id.');
assert_static_secret(
    strpos($userSource, "'password_user' => (\$oauth == 'standard' ? self::_hashPassword(\$password) : (\$setpwd ? \$password : \$oauth))") === false,
    'OAuth account creation must not store the provider name as password_user.'
);

foreach (['MYSQL_HOST', 'MYSQL_USER', 'MYSQL_DATABASE', 'MYSQL_PASSWD', 'MYSQL_PORT'] as $const) {
    if (!defined($const)) {
        define($const, '');
    }
}

require_once $root.'/app/src/MySQL/MySQL.php';
require_once $root.'/app/modules/kr-api/src/Api.php';

class StaticSecretRegressionApp {
    private $apiKey;

    public function __construct($apiKey) {
        $this->apiKey = $apiKey;
    }

    public function _getDataApiKey() {
        return $this->apiKey;
    }
}

try {
    new Api(new StaticSecretRegressionApp(''), 'anything');
    throw new Exception('Data API must reject every request when KRYPTO_DATA_API_KEY/data_api_key is empty.');
} catch (Exception $e) {
    assert_static_secret($e->getMessage() === 'Permission denied', 'Empty data API key must fail closed.');
}

try {
    new Api(new StaticSecretRegressionApp('rotated-data-api-key'), 'wrong-key');
    throw new Exception('Data API must reject a mismatched runtime key.');
} catch (Exception $e) {
    assert_static_secret($e->getMessage() === 'Permission denied', 'Mismatched data API key must fail closed.');
}

$api = new Api(new StaticSecretRegressionApp('rotated-data-api-key'), 'rotated-data-api-key');
assert_static_secret($api instanceof Api, 'Data API must accept the configured runtime key.');

echo "Static secret and OAuth identity checks passed.\n";

?>
