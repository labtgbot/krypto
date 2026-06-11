<?php

/**
 * Regression coverage for issue #98 (SEC-11): legacy order-book and block
 * explorer endpoints must fail closed on untrusted market/address/tx input, and
 * the dormant RSS parser must not allow external XML entity resolution.
 */

$root = dirname(__DIR__);
$failures = [];

function sec11_fail($message) {
    global $failures;
    $failures[] = $message;
}

function sec11_assert($condition, $message) {
    if (!$condition) {
        sec11_fail($message);
    }
}

function sec11_read($root, $relativePath) {
    $path = $root.'/'.$relativePath;
    sec11_assert(file_exists($path), 'Missing required file: '.$relativePath);
    if (!file_exists($path)) {
        return '';
    }

    $source = file_get_contents($path);
    sec11_assert($source !== false && trim($source) !== '', 'Cannot read '.$relativePath);
    return (string) $source;
}

function sec11_expect_invalid_argument($callback, $message) {
    try {
        call_user_func($callback);
    } catch (InvalidArgumentException $e) {
        return;
    } catch (Throwable $e) {
        sec11_fail($message.' Expected InvalidArgumentException, got '.get_class($e).': '.$e->getMessage());
        return;
    }

    sec11_fail($message.' Expected InvalidArgumentException.');
}

$orderBookHelperPath = $root.'/app/modules/kr-dashboard/src/OrderBookRequest.php';
sec11_assert(file_exists($orderBookHelperPath), 'getOrderBook.php must use a dedicated request validator/helper.');

if (file_exists($orderBookHelperPath)) {
    require_once $orderBookHelperPath;

    sec11_assert(class_exists('KryptoOrderBookRequest'), 'Order-book helper class must be loadable.');
    if (class_exists('KryptoOrderBookRequest')) {
        sec11_assert(
            KryptoOrderBookRequest::exchangeClassName('cexio') === '\\ccxt\\cex',
            'cexio must normalize to the whitelisted ccxt cex adapter.'
        );
        sec11_assert(
            KryptoOrderBookRequest::pairSymbol('eth', 'btc') === 'ETH/BTC',
            'Order-book symbols must normalize to an uppercase ccxt pair.'
        );
        sec11_expect_invalid_argument(function() {
            KryptoOrderBookRequest::exchangeClassName('Some\\Injected\\Class');
        }, 'Order-book market whitelist must reject arbitrary class names.');
        sec11_expect_invalid_argument(function() {
            KryptoOrderBookRequest::pairSymbol('ETH/BTC', 'USD');
        }, 'Order-book symbol validation must reject embedded pair separators.');
        sec11_expect_invalid_argument(function() {
            KryptoOrderBookRequest::pairSymbol('ETH', 'USD?x=1');
        }, 'Order-book currency validation must reject query delimiters.');
    }
}

$getOrderBook = sec11_read($root, 'app/modules/kr-dashboard/src/actions/getOrderBook.php');
sec11_assert(
    strpos($getOrderBook, "'\\\\ccxt\\\\'.strtolower(\$_GET['market'])") === false,
    'getOrderBook.php must not concatenate $_GET["market"] into a ccxt class name.'
);
sec11_assert(
    strpos($getOrderBook, 'KryptoOrderBookRequest::exchangeClassName') !== false,
    'getOrderBook.php must route market input through the whitelist helper.'
);
sec11_assert(
    strpos($getOrderBook, 'catch (Throwable ') !== false,
    'getOrderBook.php must catch Throwable so missing ccxt classes do not become fatal errors.'
);
sec11_assert(
    strpos($getOrderBook, 'class_exists($exchangeClass') !== false,
    'getOrderBook.php must check the whitelisted exchange class before instantiation.'
);

$bitcoinExplorer = sec11_read($root, 'app/modules/kr-blocksexplorer/src/BitcoinExplorer.php');
$chainSo = sec11_read($root, 'app/modules/kr-blocksexplorer/src/ChainSo.php');
$etherblock = sec11_read($root, 'app/modules/kr-blocksexplorer/src/Etherblock.php');

foreach ([
    'BitcoinExplorer.php' => $bitcoinExplorer,
    'ChainSo.php' => $chainSo,
    'Etherblock.php' => $etherblock,
] as $label => $source) {
    sec11_assert(strpos($source, 'rawurlencode') !== false || strpos($source, 'http_build_query') !== false, $label.' must URL-encode outbound path/query parameters.');
}

sec11_assert(
    strpos($bitcoinExplorer, 'join(\'/\', $args)') === false,
    'BitcoinExplorer must not concatenate raw path segments with join("/", $args).'
);
sec11_assert(
    strpos($chainSo, 'join(\'/\', $args)') === false,
    'ChainSo must not concatenate raw path segments with join("/", $args).'
);

$explorerValidationReady = (
    strpos($bitcoinExplorer, '_validateBitcoinAddress') !== false
    && strpos($bitcoinExplorer, '_validateBitcoinTransactionHash') !== false
    && strpos($chainSo, '_normalizeSymbol') !== false
    && strpos($etherblock, '_validateAddress') !== false
    && strpos($etherblock, '_validateTransactionHash') !== false
);
sec11_assert($explorerValidationReady, 'Explorer classes must validate address, transaction, and symbol input before network calls.');

if ($explorerValidationReady) {
    if (!class_exists('MySQL')) {
        class MySQL {}
    }

    require_once $root.'/app/modules/kr-blocksexplorer/src/BitcoinExplorer.php';
    require_once $root.'/app/modules/kr-blocksexplorer/src/ChainSo.php';
    require_once $root.'/app/modules/kr-blocksexplorer/src/Etherblock.php';

    sec11_expect_invalid_argument(function() {
        $explorer = new BitcoinExplorer(null);
        $explorer->_getHistoryTransaction('https://169.254.169.254/latest/meta-data');
    }, 'BitcoinExplorer must reject non-address SSRF payloads.');

    sec11_expect_invalid_argument(function() {
        $explorer = new BitcoinExplorer(null);
        $explorer->_getTransactionInfos('../'.str_repeat('a', 64));
    }, 'BitcoinExplorer must reject tx hashes with path traversal.');

    sec11_expect_invalid_argument(function() {
        new ChainSo(null, 'LTC/../../metadata');
    }, 'ChainSo must reject unsafe network symbols.');

    sec11_expect_invalid_argument(function() {
        $explorer = new Etherblock(null);
        $explorer->_getHistoryTransaction('0x123?module=account', 'ETH');
    }, 'Etherblock must reject malformed Ethereum addresses.');

    sec11_expect_invalid_argument(function() {
        $explorer = new Etherblock(null);
        $explorer->_getTransactionInfos('0x'.str_repeat('f', 63).'/');
    }, 'Etherblock must reject tx hashes with path delimiters.');
}

$feed = sec11_read($root, 'app/modules/kr-news/src/Feed.php');
sec11_assert(
    strpos($feed, 'LIBXML_NONET') !== false,
    'Feed.php must parse XML with LIBXML_NONET.'
);
sec11_assert(
    strpos($feed, 'libxml_disable_entity_loader') !== false || strpos($feed, 'libxml_set_external_entity_loader') !== false,
    'Feed.php must disable external entity loading around SimpleXMLElement parsing.'
);

if (count($failures) > 0) {
    fwrite(STDERR, "SEC-11 SSRF/input validation regression test failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- '.$failure."\n");
    }
    exit(1);
}

echo "SEC-11 SSRF/input validation regression checks passed.\n";

?>
