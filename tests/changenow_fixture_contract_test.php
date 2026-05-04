<?php

$root = dirname(__DIR__);
$fixtureRoot = $root.'/tests/fixtures/changenow';
$failures = [];

function fail_if($condition, $message) {
    global $failures;
    if ($condition) {
        $failures[] = $message;
    }
}

function read_fixture($fixtureRoot, $name) {
    $path = $fixtureRoot.'/'.$name;
    fail_if(!file_exists($path), 'Missing fixture: '.$name);
    if (!file_exists($path)) {
        return [];
    }

    $decoded = json_decode(file_get_contents($path), true);
    fail_if(json_last_error() !== JSON_ERROR_NONE, $name.' should contain valid JSON.');
    fail_if(!is_array($decoded), $name.' should decode to an array.');

    return (is_array($decoded) ? $decoded : []);
}

function assert_has_keys($payload, $keys, $label) {
    foreach ($keys as $key) {
        fail_if(!array_key_exists($key, $payload), $label.' should include '.$key.'.');
    }
}

$quote = read_fixture($fixtureRoot, 'estimated_amount_standard_success.json');
assert_has_keys($quote, [
    'fromCurrency',
    'fromNetwork',
    'toCurrency',
    'toNetwork',
    'flow',
    'fromAmount',
    'toAmount',
], 'Standard quote fixture');
fail_if($quote['flow'] !== 'standard', 'Standard quote fixture should use the standard flow.');

$createdExchange = read_fixture($fixtureRoot, 'exchange_create_success.json');
assert_has_keys($createdExchange, [
    'id',
    'fromCurrency',
    'fromNetwork',
    'toCurrency',
    'toNetwork',
    'fromAmount',
    'toAmount',
    'payinAddress',
    'payoutAddress',
    'flow',
], 'Exchange creation fixture');

$finishedExchange = read_fixture($fixtureRoot, 'exchange_status_finished.json');
assert_has_keys($finishedExchange, [
    'id',
    'status',
    'actionsAvailable',
    'fromCurrency',
    'toCurrency',
    'amountFrom',
    'amountTo',
    'payinAddress',
    'payoutAddress',
], 'Exchange status fixture');
fail_if($finishedExchange['status'] !== 'finished', 'Status fixture should represent a finished transaction.');

$validationError = read_fixture($fixtureRoot, 'validation_error.json');
assert_has_keys($validationError, ['error', 'message'], 'Validation error fixture');
fail_if($validationError['error'] === '', 'Validation error fixture should include a non-empty error code.');

if (count($failures) > 0) {
    fwrite(STDERR, "ChangeNOW fixture contract test failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "- ".$failure."\n");
    }
    exit(1);
}

echo "ChangeNOW fixture contract checks passed.\n";

?>
