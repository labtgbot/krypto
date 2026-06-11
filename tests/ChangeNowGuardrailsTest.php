<?php

require __DIR__.'/../app/src/ChangeNow/ChangeNowGuardrails.php';

function kr_assert($condition, $message) {
  if(!$condition) {
    throw new Exception($message);
  }
}

function kr_assert_equals($expected, $actual, $message) {
  if($expected !== $actual) {
    throw new Exception($message.' Expected '.var_export($expected, true).', got '.var_export($actual, true));
  }
}

$payload = [
  'request_id' => 'cnreq_abc123',
  'provider_transaction_id' => 'tx-provider-123',
  'api_key' => 'live-api-key-secret',
  'x-api-key' => 'live-header-api-key-secret',
  'privateKey' => 'private-key-secret',
  'payinAddress' => 'bc1qexampleuseraddress0000000000000000',
  'memo' => '123456',
  'payload' => [
    'destinationAddress' => '0xabcdefabcdefabcdefabcdefabcdefabcdefabcd',
    'note' => 'keep this diagnostic note'
  ],
  'quote' => [
    'from' => 'BTC',
    'to' => 'ETH'
  ]
];

$redacted = ChangeNowRedactor::redact($payload);

kr_assert_equals('cnreq_abc123', $redacted['request_id'], 'Request IDs must survive redaction.');
kr_assert_equals('tx-provider-123', $redacted['provider_transaction_id'], 'Provider transaction IDs must survive redaction.');
kr_assert_equals('[redacted]', $redacted['api_key'], 'API keys must be redacted.');
kr_assert_equals('[redacted]', $redacted['x-api-key'], 'API key headers must be redacted.');
kr_assert_equals('[redacted]', $redacted['privateKey'], 'Private keys must be redacted.');
kr_assert(strpos($redacted['payinAddress'], 'address#') === 0, 'Raw pay-in addresses must be fingerprinted.');
kr_assert_equals('[redacted]', $redacted['memo'], 'Memos must be redacted.');
kr_assert_equals('[redacted]', $redacted['payload'], 'User payloads must be redacted.');
kr_assert_equals('BTC', $redacted['quote']['from'], 'Non-sensitive quote fields should remain readable.');

$logger = new ChangeNowLogger(true, false);
$entry = $logger->buildEntry('INFO', 'quote_failed', [
  'request' => $payload,
  'authorization' => 'Bearer test-secret'
], 'cnreq_abc123', 'tx-provider-123');

kr_assert_equals('cnreq_abc123', $entry['request_id'], 'Log entries must include the request ID.');
kr_assert_equals('tx-provider-123', $entry['provider_transaction_id'], 'Log entries must include the provider transaction ID.');
kr_assert_equals('[redacted]', $entry['context']['request']['api_key'], 'Log context must be redacted.');
kr_assert_equals('[redacted]', $entry['context']['authorization'], 'Authorization headers must be redacted.');

$rateLimitPath = sys_get_temp_dir().'/krypto-changenow-rate-test-'.uniqid('', true);
$rateLimiter = new ChangeNowRateLimiter($rateLimitPath);
$first = $rateLimiter->check('quote', '198.51.100.7', 2, 60, 1000);
$second = $rateLimiter->check('quote', '198.51.100.7', 2, 60, 1001);
$third = $rateLimiter->check('quote', '198.51.100.7', 2, 60, 1002);
$nextWindow = $rateLimiter->check('quote', '198.51.100.7', 2, 60, 1020);
$otherIdentity = $rateLimiter->check('quote', '198.51.100.8', 2, 60, 1002);

kr_assert($first['allowed'], 'First request in a rate window should be allowed.');
kr_assert($second['allowed'], 'Second request in a rate window should be allowed.');
kr_assert(!$third['allowed'], 'Third request in a two-request window should be blocked.');
kr_assert($nextWindow['allowed'], 'A new rate-limit window should allow requests again.');
kr_assert($otherIdentity['allowed'], 'A different identity should have an independent rate-limit bucket.');

$eligibility = ChangeNowEligibility::countryState('us', ['US'], ['unsupported_region' => 'Custom unsupported-region copy.']);
kr_assert(!$eligibility['allowed'], 'Blocked countries must be rejected.');
kr_assert_equals('unsupported_region', $eligibility['state'], 'Blocked country state should be unsupported_region.');
kr_assert_equals('Custom unsupported-region copy.', $eligibility['message'], 'Eligibility copy must be configurable.');

$allowedEligibility = ChangeNowEligibility::countryState('ca', ['US'], ['unsupported_region' => 'Custom unsupported-region copy.']);
kr_assert($allowedEligibility['allowed'], 'Allowed countries must pass eligibility checks.');
kr_assert_equals('allowed', $allowedEligibility['state'], 'Allowed country state should be allowed.');

$unknownCountryEligibility = ChangeNowEligibility::countryState('', ['US'], ['unsupported_region' => 'Custom unsupported-region copy.']);
kr_assert(!$unknownCountryEligibility['allowed'], 'Unknown countries must fail closed when an unsupported-country list is configured.');
kr_assert_equals('unsupported_region', $unknownCountryEligibility['state'], 'Unknown country state should use the regional block state.');
kr_assert_equals('Custom unsupported-region copy.', $unknownCountryEligibility['message'], 'Unknown country blocks should use configured compliance copy.');

$emptyBlocklistEligibility = ChangeNowEligibility::countryState('us', [], ['unsupported_region' => 'Custom unsupported-region copy.']);
kr_assert($emptyBlocklistEligibility['allowed'], 'Empty unsupported-country lists must preserve existing public swap behavior.');
kr_assert_equals('allowed', $emptyBlocklistEligibility['state'], 'Empty unsupported-country lists should not produce a regional block.');

$trustedCountryServer = [
  'REMOTE_ADDR' => '203.0.113.10',
  'HTTP_CF_IPCOUNTRY' => 'US'
];
kr_assert_equals('', ChangeNowRequestRegion::countryCode($trustedCountryServer, null, []), 'Untrusted proxy country headers must be ignored.');
kr_assert_equals('US', ChangeNowRequestRegion::countryCode($trustedCountryServer, null, ['203.0.113.10']), 'Trusted proxy country headers should determine request country.');

$geoIpCountry = ChangeNowRequestRegion::countryCode([
  'REMOTE_ADDR' => '198.51.100.45'
], function($ip) {
  return [
    'country' => [
      'code' => ($ip == '198.51.100.45' ? 'CA' : '')
    ]
  ];
});
kr_assert_equals('CA', $geoIpCountry, 'GeoIP resolver country should be used when no trusted country header is present.');
kr_assert_equals('DE', ChangeNowRequestRegion::countryCodeFromGeoIpPayload([
  'country_code' => 'de'
]), 'GeoIP payloads with a top-level country_code should be normalized.');

$providerDown = ChangeNowEligibility::providerState(false);
kr_assert(!$providerDown['available'], 'Unavailable provider should return an outage state.');
kr_assert_equals('provider_down', $providerDown['state'], 'Unavailable provider state should be provider_down.');

$owner = ['id_user' => 42, 'is_admin' => false, 'is_manager' => false];
$otherUser = ['id_user' => 24, 'is_admin' => false, 'is_manager' => false];
$admin = ['id_user' => 1, 'is_admin' => true, 'is_manager' => false];
$transaction = ['id_user' => 42];

kr_assert(ChangeNowAccessPolicy::canViewTransaction($owner, $transaction), 'Transaction owner should be allowed.');
kr_assert(!ChangeNowAccessPolicy::canViewTransaction($otherUser, $transaction), 'Unrelated users should be denied.');
kr_assert(ChangeNowAccessPolicy::canViewTransaction($admin, $transaction), 'Admins should be allowed.');

$lookupToken = 'anonymous-token';
$anonymousTransaction = ['id_user' => null, 'lookup_token_hash' => ChangeNowAccessPolicy::hashLookupToken($lookupToken)];
kr_assert(ChangeNowAccessPolicy::canViewTransaction(null, $anonymousTransaction, $lookupToken), 'Anonymous lookup token should allow matching transaction access.');
kr_assert(!ChangeNowAccessPolicy::canViewTransaction(null, $anonymousTransaction, 'wrong-token'), 'Wrong anonymous lookup token should be denied.');

echo "ChangeNOW guardrails tests passed\n";
