<?php

$root = dirname(__DIR__);
require_once $root.'/tests/support/db_fixtures.php';

if (getenv('KRYPTO_RUN_DB_TESTS') !== '1' || !krypto_db_is_enabled()) {
    krypto_db_skip('ChangeNOW retention DB test disabled. Run php scripts/run_tests.php --db or --only-db inside the local DB environment.');
}

$retentionFile = $root.'/app/modules/kr-changenow/src/ChangeNowRetention.php';
if (!file_exists($retentionFile)) {
    throw new Exception('Missing ChangeNOW retention helper: '.$retentionFile);
}

require_once $retentionFile;

function assertRetentionDbSame($expected, $actual, $message) {
    if ($expected !== $actual) {
        throw new Exception($message.' Expected '.var_export($expected, true).', got '.var_export($actual, true));
    }
}

function assertRetentionDbTrue($condition, $message) {
    if (!$condition) {
        throw new Exception($message);
    }
}

function retention_db_insert_quote($pdo, $cacheKey, $expiresAt, $createdAt) {
    $statement = $pdo->prepare('INSERT INTO changenow_quote_cache_krypto
        (cache_key_changenow_quote_cache, from_currency_changenow_quote_cache, from_network_changenow_quote_cache,
         to_currency_changenow_quote_cache, to_network_changenow_quote_cache, flow_changenow_quote_cache,
         amount_changenow_quote_cache, request_changenow_quote_cache, response_changenow_quote_cache,
         expires_at_changenow_quote_cache, created_at_changenow_quote_cache)
        VALUES
        (:cache_key, "btc", "btc", "eth", "eth", "standard", "0.1", "{}", "{}", :expires_at, :created_at)');
    $statement->execute([
        'cache_key' => $cacheKey,
        'expires_at' => (string) $expiresAt,
        'created_at' => (string) $createdAt,
    ]);
}

function retention_db_insert_transaction($pdo, $providerId, $lookupToken, $userId, $status, $createdAt, $updatedAt, $expiresAt) {
    $statement = $pdo->prepare('INSERT INTO changenow_transactions_krypto
        (provider_id_changenow_transaction, lookup_token_hash_changenow_transaction, session_key_changenow_transaction,
         id_user, flow_changenow_transaction, from_currency_changenow_transaction, from_network_changenow_transaction,
         to_currency_changenow_transaction, to_network_changenow_transaction, from_amount_changenow_transaction,
         to_amount_changenow_transaction, payin_address_changenow_transaction, payin_extra_id_changenow_transaction,
         payout_address_changenow_transaction, payout_extra_id_changenow_transaction, payout_address_fingerprint_changenow_transaction,
         refund_address_changenow_transaction, refund_extra_id_changenow_transaction, status_changenow_transaction,
         refund_available_changenow_transaction, continue_available_changenow_transaction, referral_attribution_changenow_transaction,
         raw_create_changenow_transaction, raw_status_changenow_transaction, raw_actions_changenow_transaction,
         support_note_changenow_transaction, created_at_changenow_transaction, updated_at_changenow_transaction,
         expires_at_changenow_transaction)
        VALUES
        (:provider_id, :lookup_hash, :session_hash, :id_user, "standard", "btc", "btc", "eth", "eth", "0.1", "1.5",
         "payin-address", "payin-extra", "payout-address", "payout-extra", :payout_fingerprint,
         "refund-address", "refund-extra", :status_swap, 1, 1, :referral_attribution,
         :raw_create, :raw_status, :raw_actions, "support note with private details", :created_at, :updated_at, :expires_at)');
    $statement->execute([
        'provider_id' => $providerId,
        'lookup_hash' => hash('sha256', $lookupToken),
        'session_hash' => hash('sha256', 'session-'.$lookupToken),
        'id_user' => $userId,
        'payout_fingerprint' => hash('sha256', 'payout-address'),
        'status_swap' => $status,
        'referral_attribution' => json_encode(['utm' => ['campaign' => 'retention-test']]),
        'raw_create' => json_encode(['payoutAddress' => 'payout-address']),
        'raw_status' => json_encode(['refundAddress' => 'refund-address']),
        'raw_actions' => json_encode(['refund' => true, 'continue' => true]),
        'created_at' => (string) $createdAt,
        'updated_at' => (string) $updatedAt,
        'expires_at' => (string) $expiresAt,
    ]);

    $transactionId = (int) $pdo->lastInsertId();
    $event = $pdo->prepare('INSERT INTO changenow_transaction_events_krypto
        (id_changenow_transaction, provider_id_changenow_transaction, actor_type_changenow_transaction_event,
         event_type_changenow_transaction_event, event_status_changenow_transaction_event, event_note_changenow_transaction_event,
         raw_event_changenow_transaction_event, created_at_changenow_transaction_event)
        VALUES
        (:transaction_id, :provider_id, "system", "status", :status_swap, "event note", :raw_event, :created_at)');
    $event->execute([
        'transaction_id' => $transactionId,
        'provider_id' => $providerId,
        'status_swap' => $status,
        'raw_event' => json_encode(['payoutAddress' => 'payout-address']),
        'created_at' => (string) $updatedAt,
    ]);

    return $transactionId;
}

function retention_db_fetch_transaction($pdo, $providerId) {
    $statement = $pdo->prepare('SELECT * FROM changenow_transactions_krypto WHERE provider_id_changenow_transaction = :provider_id LIMIT 1');
    $statement->execute(['provider_id' => $providerId]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    return ($row === false ? null : $row);
}

function retention_db_count($pdo, $sql, $params = []) {
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    return (int) $statement->fetchColumn();
}

$pdo = krypto_db_pdo();
$prefix = 'retention-test-'.bin2hex(random_bytes(4));
$now = 2000000000;
$day = 86400;

$cleanup = function() use ($pdo, $prefix) {
    $pdo->prepare('DELETE FROM changenow_transaction_events_krypto WHERE provider_id_changenow_transaction LIKE :provider_prefix')
        ->execute(['provider_prefix' => $prefix.'%']);
    $pdo->prepare('DELETE FROM changenow_transactions_krypto WHERE provider_id_changenow_transaction LIKE :provider_prefix')
        ->execute(['provider_prefix' => $prefix.'%']);
    $pdo->prepare('DELETE FROM changenow_quote_cache_krypto WHERE cache_key_changenow_quote_cache IN (:expired_cache, :active_cache)')
        ->execute([
            'expired_cache' => hash('sha256', $prefix.'expired-cache'),
            'active_cache' => hash('sha256', $prefix.'active-cache'),
        ]);
};

$cleanup();

try {
    $expiredCacheKey = hash('sha256', $prefix.'expired-cache');
    $activeCacheKey = hash('sha256', $prefix.'active-cache');
    retention_db_insert_quote($pdo, $expiredCacheKey, $now - 60, $now - $day);
    retention_db_insert_quote($pdo, $activeCacheKey, $now + 60, $now);

    $oldAnonymousProvider = $prefix.'anon-old';
    $freshAnonymousProvider = $prefix.'anon-fresh';
    $oldCompletedProvider = $prefix.'completed-old';
    $oldCompletedAnonymousProvider = $prefix.'completed-anon-old';
    $freshCompletedProvider = $prefix.'completed-fresh';

    $oldAnonymousId = retention_db_insert_transaction($pdo, $oldAnonymousProvider, $prefix.'lookup-old', null, 'waiting', $now - (70 * $day), $now - (60 * $day), $now - (31 * $day));
    retention_db_insert_transaction($pdo, $freshAnonymousProvider, $prefix.'lookup-fresh', null, 'waiting', $now - (20 * $day), $now - (20 * $day), $now - (10 * $day));
    retention_db_insert_transaction($pdo, $oldCompletedProvider, $prefix.'lookup-completed-old', 42, 'finished', $now - (420 * $day), $now - (400 * $day), $now - (400 * $day));
    retention_db_insert_transaction($pdo, $oldCompletedAnonymousProvider, $prefix.'lookup-completed-anon-old', null, 'finished', $now - (420 * $day), $now - (400 * $day), $now - (400 * $day));
    retention_db_insert_transaction($pdo, $freshCompletedProvider, $prefix.'lookup-completed-fresh', 42, 'finished', $now - (120 * $day), $now - (100 * $day), $now - (100 * $day));

    $retention = new ChangeNowRetention($pdo, [
        'anonymous_retention_days' => 30,
        'completed_retention_days' => 365,
        'now' => $now,
        'batch_size' => 50,
    ]);
    $result = $retention->_run();

    assertRetentionDbSame(1, $result['quoteCacheDeleted'], 'Expired quote cache row should be deleted.');
    assertRetentionDbSame(1, $result['anonymousTransactionsAnonymized'], 'Expired anonymous transaction should be anonymized.');
    assertRetentionDbSame(1, $result['anonymousEventsDeleted'], 'Expired anonymous transaction events should be removed.');
    assertRetentionDbSame(2, $result['completedTransactionsDeleted'], 'Completed transactions beyond retention should be deleted.');
    assertRetentionDbSame(2, $result['completedEventsDeleted'], 'Completed transaction events beyond retention should be removed.');

    $oldAnonymous = retention_db_fetch_transaction($pdo, $oldAnonymousProvider);
    assertRetentionDbTrue(is_array($oldAnonymous), 'Anonymized anonymous transaction should remain as a non-identifying audit record.');
    assertRetentionDbSame(ChangeNowRetention::_retainedLookupHash($oldAnonymousId), $oldAnonymous['lookup_token_hash_changenow_transaction'], 'Original anonymous lookup token hash should be replaced.');
    assertRetentionDbSame('', $oldAnonymous['payout_address_changenow_transaction'], 'Payout address should be cleared during anonymization.');
    assertRetentionDbSame('', $oldAnonymous['raw_create_changenow_transaction'], 'Raw create payload should be cleared during anonymization.');
    assertRetentionDbSame(0, retention_db_count($pdo, 'SELECT COUNT(*) FROM changenow_transactions_krypto WHERE lookup_token_hash_changenow_transaction = :lookup_hash', [
        'lookup_hash' => hash('sha256', $prefix.'lookup-old'),
    ]), 'Original anonymous lookup token hash should no longer be queryable.');
    assertRetentionDbSame(0, retention_db_count($pdo, 'SELECT COUNT(*) FROM changenow_transaction_events_krypto WHERE provider_id_changenow_transaction = :provider_id', [
        'provider_id' => $oldAnonymousProvider,
    ]), 'Anonymous event history should be removed after anonymization.');

    assertRetentionDbSame(0, retention_db_count($pdo, 'SELECT COUNT(*) FROM changenow_transactions_krypto WHERE provider_id_changenow_transaction = :provider_id', [
        'provider_id' => $oldCompletedProvider,
    ]), 'Old completed transaction should be deleted.');
    assertRetentionDbSame(0, retention_db_count($pdo, 'SELECT COUNT(*) FROM changenow_transactions_krypto WHERE provider_id_changenow_transaction = :provider_id', [
        'provider_id' => $oldCompletedAnonymousProvider,
    ]), 'Old completed anonymous transaction should be deleted instead of retained.');
    assertRetentionDbSame(0, retention_db_count($pdo, 'SELECT COUNT(*) FROM changenow_transactions_krypto WHERE lookup_token_hash_changenow_transaction = :lookup_hash', [
        'lookup_hash' => hash('sha256', $prefix.'lookup-completed-anon-old'),
    ]), 'Old completed anonymous lookup token hash should not remain after retention.');
    assertRetentionDbSame(1, retention_db_count($pdo, 'SELECT COUNT(*) FROM changenow_transactions_krypto WHERE provider_id_changenow_transaction = :provider_id', [
        'provider_id' => $freshAnonymousProvider,
    ]), 'Fresh anonymous transaction should be preserved.');
    assertRetentionDbSame(1, retention_db_count($pdo, 'SELECT COUNT(*) FROM changenow_transactions_krypto WHERE provider_id_changenow_transaction = :provider_id', [
        'provider_id' => $freshCompletedProvider,
    ]), 'Recent completed transaction should be preserved for audit.');
    assertRetentionDbSame(0, retention_db_count($pdo, 'SELECT COUNT(*) FROM changenow_quote_cache_krypto WHERE cache_key_changenow_quote_cache = :cache_key', [
        'cache_key' => $expiredCacheKey,
    ]), 'Expired quote cache should no longer exist.');
    assertRetentionDbSame(1, retention_db_count($pdo, 'SELECT COUNT(*) FROM changenow_quote_cache_krypto WHERE cache_key_changenow_quote_cache = :cache_key', [
        'cache_key' => $activeCacheKey,
    ]), 'Active quote cache should be preserved.');

    $secondRun = $retention->_run();
    assertRetentionDbSame(0, $secondRun['quoteCacheDeleted'], 'Second retention run should not delete extra quote cache rows.');
    assertRetentionDbSame(0, $secondRun['anonymousTransactionsAnonymized'], 'Second retention run should not re-anonymize rows.');
    assertRetentionDbSame(0, $secondRun['completedTransactionsDeleted'], 'Second retention run should not delete additional transactions.');
} finally {
    $cleanup();
}

echo "ChangeNOW retention DB check passed.\n";

?>
