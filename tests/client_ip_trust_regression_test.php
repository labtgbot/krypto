<?php

/**
 * Regression coverage for issue #99 (SEC-12): client IP security decisions must
 * not trust spoofable request headers unless REMOTE_ADDR is a configured proxy.
 */

$root = dirname(__DIR__);

if(!class_exists('MySQL')){
  class MySQL {}
}

require_once $root.'/app/src/App/App.php';
require_once $root.'/app/src/User/User.php';

function assert_client_ip_trust($condition, $message) {
  if(!$condition) {
    throw new Exception($message);
  }
}

function assert_client_ip_equals($expected, $actual, $message) {
  if($expected !== $actual) {
    throw new Exception($message.' Expected '.var_export($expected, true).', got '.var_export($actual, true));
  }
}

function client_ip_source_between($source, $startNeedle, $endNeedle) {
  $start = strpos($source, $startNeedle);
  assert_client_ip_trust($start !== false, 'Cannot find source start: '.$startNeedle);
  $end = strpos($source, $endNeedle, $start);
  assert_client_ip_trust($end !== false, 'Cannot find source end: '.$endNeedle);
  return substr($source, $start, $end - $start);
}

function client_ip_with_server($server, $callback) {
  $previousServer = $_SERVER;
  $_SERVER = $server;

  try {
    return call_user_func($callback);
  } finally {
    $_SERVER = $previousServer;
  }
}

function client_ip_with_trusted_proxies($trustedProxies, $callback) {
  $hadEnv = getenv('KRYPTO_TRUSTED_PROXIES') !== false;
  $previous = getenv('KRYPTO_TRUSTED_PROXIES');
  putenv('KRYPTO_TRUSTED_PROXIES='.$trustedProxies);
  $_ENV['KRYPTO_TRUSTED_PROXIES'] = $trustedProxies;

  try {
    return call_user_func($callback);
  } finally {
    if($hadEnv) {
      putenv('KRYPTO_TRUSTED_PROXIES='.$previous);
      $_ENV['KRYPTO_TRUSTED_PROXIES'] = $previous;
    } else {
      putenv('KRYPTO_TRUSTED_PROXIES');
      unset($_ENV['KRYPTO_TRUSTED_PROXIES']);
    }
  }
}

$spoofedServer = [
  'REMOTE_ADDR' => '203.0.113.10',
  'HTTP_CLIENT_IP' => '10.0.0.44',
  'HTTP_X_FORWARDED_FOR' => '198.51.100.45, 198.51.100.46',
  'HTTP_X_REAL_IP' => '198.51.100.47',
  'HTTP_USER_AGENT' => 'Krypto regression test'
];

client_ip_with_trusted_proxies('', function() use ($spoofedServer) {
  client_ip_with_server($spoofedServer, function() {
    assert_client_ip_equals('203.0.113.10', App::_getVisitorIP(), 'Visitor IP must ignore spoofed client headers without a trusted proxy.');

    $_SESSION = [];
    $user = new User();
    assert_client_ip_equals('203.0.113.10', $user->_getUserIP(), 'User IP must ignore spoofed client headers without a trusted proxy.');
  });
});

client_ip_with_trusted_proxies('203.0.113.10', function() use ($spoofedServer) {
  client_ip_with_server($spoofedServer, function() {
    assert_client_ip_equals('198.51.100.45', App::_getVisitorIP(), 'Visitor IP should use the first forwarded IP from a trusted proxy.');

    $_SESSION = [];
    $user = new User();
    assert_client_ip_equals('198.51.100.45', $user->_getUserIP(), 'User IP should use the first forwarded IP from a trusted proxy.');
  });
});

$appSource = file_get_contents($root.'/app/src/App/App.php');
$userSource = file_get_contents($root.'/app/src/User/User.php');
assert_client_ip_trust($appSource !== false, 'Cannot read App.php.');
assert_client_ip_trust($userSource !== false, 'Cannot read User.php.');

$visitorIpMethod = client_ip_source_between($appSource, 'public static function _getVisitorIP()', 'private $visitorLocation');
$userIpMethod = client_ip_source_between($userSource, 'public function _getUserIP()', '  /**'."\n".'   * Add user visit');
$loginHistoryMethod = client_ip_source_between($userSource, 'public function _saveUserLoginHistory()', 'public function _getHistoryLoginUser()');

foreach ([
  'App::_getVisitorIP' => $visitorIpMethod,
  'User::_getUserIP' => $userIpMethod
] as $method => $source) {
  assert_client_ip_trust(strpos($source, 'ChangeNowRequestRegion::clientIp') !== false, $method.' must use the shared trusted-proxy client IP resolver.');
  assert_client_ip_trust(strpos($source, 'HTTP_CLIENT_IP') === false, $method.' must not read HTTP_CLIENT_IP directly.');
  assert_client_ip_trust(strpos($source, 'HTTP_X_FORWARDED_FOR') === false, $method.' must not read HTTP_X_FORWARDED_FOR directly.');
}

$currentIpPosition = strpos($loginHistoryMethod, '$CurrentUserIP = App::_getVisitorIP();');
$firstReturnPosition = strpos($loginHistoryMethod, 'return true;');
assert_client_ip_trust($currentIpPosition !== false, 'Login history must resolve the current visitor IP.');
assert_client_ip_trust($firstReturnPosition === false || $currentIpPosition < $firstReturnPosition, 'Login history must not return before recording the login.');
assert_client_ip_trust(strpos($loginHistoryMethod, 'INSERT INTO user_login_history_krypto') !== false, 'Login history must insert audit rows.');
assert_client_ip_trust(strpos($loginHistoryMethod, '_sendMail') !== false, 'Login history must keep the new-IP alert path.');

echo "Client IP trust regression checks passed.\n";

?>
