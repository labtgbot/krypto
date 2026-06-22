<?php

$root = dirname(__DIR__);

if(!defined('MYSQL_HOST')) define('MYSQL_HOST', 'localhost');
if(!defined('MYSQL_USER')) define('MYSQL_USER', 'krypto');
if(!defined('MYSQL_DATABASE')) define('MYSQL_DATABASE', 'krypto');
if(!defined('MYSQL_PASSWD')) define('MYSQL_PASSWD', '');
if(!defined('MYSQL_PORT')) define('MYSQL_PORT', 3306);

require_once $root.'/app/src/MySQL/MySQL.php';
require_once $root.'/app/src/App/App.php';

function assert_app_referral_same($expected, $actual, $message) {
    if ($expected !== $actual) {
        fwrite(STDERR, $message.' Expected '.var_export($expected, true).', got '.var_export($actual, true)."\n");
        exit(1);
    }
}

class AppReferralSourceFakeConnection {
    public $queries = [];
    private $validCodes;

    public function __construct($validCodes) {
        $this->validCodes = $validCodes;
    }

    public function prepare($query) {
        return new AppReferralSourceFakeStatement($this, $query, $this->validCodes);
    }

    public function _recordQuery($query, $params) {
        $this->queries[] = [
            'query' => $query,
            'params' => $params,
        ];
    }
}

class AppReferralSourceFakeStatement {
    private $connection;
    private $query;
    private $validCodes;
    private $params = [];

    public function __construct($connection, $query, $validCodes) {
        $this->connection = $connection;
        $this->query = $query;
        $this->validCodes = $validCodes;
    }

    public function execute($params = []) {
        $this->params = $params;
        $this->connection->_recordQuery($this->query, $params);
        return true;
    }

    public function fetchAll($mode = null) {
        if (strpos($this->query, 'FROM settings_krypto') !== false) {
            return [
                ['key_settings' => 'referal_enable', 'value_settings' => '1', 'encrypted_settings' => '0'],
                ['key_settings' => 'hidden_third_trading', 'value_settings' => '1', 'encrypted_settings' => '0'],
            ];
        }

        if (strpos($this->query, 'FROM referal_krypto') !== false) {
            $code = (array_key_exists('code_referal', $this->params) ? $this->params['code_referal'] : '');
            if (in_array($code, $this->validCodes, true)) {
                return [
                    ['code_referal' => $code],
                ];
            }
        }

        return [];
    }

    public function closeCursor() {
        return true;
    }
}

$connection = new AppReferralSourceFakeConnection(['get-code', 'post-code']);
$bddProperty = new ReflectionProperty('MySQL', 'bdd');
$bddProperty->setAccessible(true);
$bddProperty->setValue(null, $connection);

$_GET = [];
$_SESSION = [];

$app = new App(false);
$app->_checkReferalSource(['ref' => 'post-code']);

assert_app_referral_same('post-code', $_SESSION['referal_source_krypto'] ?? null, 'Explicit referral source data should be captured without relying on $_GET.');

$_GET = ['ref' => 'get-code'];
$_SESSION = [];

$app->_checkReferalSource(['ref' => 'post-code']);

assert_app_referral_same('post-code', $_SESSION['referal_source_krypto'] ?? null, 'Explicit referral source data should take precedence over a different query string ref.');

$_GET = ['ref' => 'get-code'];
$_SESSION = [];

$app->_checkReferalSource();

assert_app_referral_same('get-code', $_SESSION['referal_source_krypto'] ?? null, 'Legacy query string referral capture should continue to work.');

echo "App referral source regression checks passed\n";

?>
