<?php

/**
 * Regression coverage for issue #89 (audit findings B1, B5, B6): reflected XSS
 * caused by echoing user-controlled input without context-aware encoding.
 *
 * The three confirmed sinks must encode their output by context, and the
 * market-analysis views that reflect the search term must validate CSRF before
 * rendering:
 *
 *   - index.php             `rmsg` -> JS string context (json_encode)
 *   - coinlist.php          `search` -> HTML attribute (htmlspecialchars)
 *   - marketlist.php        `search` -> HTML attribute (htmlspecialchars)
 *   - exportGraph.php       `container` -> HTML attribute (htmlspecialchars)
 *
 * These are static checks so a regression reintroducing a raw echo of request
 * input fails fast without a live server or database.
 */

$root = dirname(__DIR__);

function assert_xss($condition, $message) {
    if (!$condition) {
        throw new Exception($message);
    }
}

function read_source($root, $relative) {
    $source = @file_get_contents($root.'/'.$relative);
    assert_xss($source !== false && trim($source) !== '', 'Cannot read '.$relative);
    return $source;
}

// --- B1: index.php rmsg must be JS-string encoded, never raw -----------------
$index = read_source($root, 'index.php');
assert_xss(
    strpos($index, "base64_decode(\$_GET['rmsg'])") !== false,
    'index.php should still decode the rmsg payload.'
);
assert_xss(
    strpos($index, '"\'.base64_decode($_GET[\'rmsg\']).\'"') === false,
    'index.php must not echo base64_decode($_GET["rmsg"]) raw into the <script> string (XSS B1).'
);
assert_xss(
    preg_match('/json_encode\(\s*base64_decode\(\$_GET\[\'rmsg\'\]\)/', $index) === 1,
    'index.php must JS-encode the rmsg payload with json_encode before output (XSS B1).'
);

// --- B5: coinlist.php search must be HTML-attribute encoded + CSRF-gated ------
$coinlist = read_source($root, 'app/modules/kr-marketanalysis/views/coinlist.php');
assert_xss(
    strpos($coinlist, 'Krypto_Csrf::validateRequest(') !== false,
    'coinlist.php must validate CSRF before reflecting the search term (XSS B5).'
);
assert_xss(
    preg_match('/htmlspecialchars\(\$_POST\[\'search\'\]\s*,\s*ENT_QUOTES/', $coinlist) === 1,
    'coinlist.php must encode $_POST["search"] with htmlspecialchars(..., ENT_QUOTES) (XSS B5).'
);
assert_xss(
    strpos($coinlist, ": \$_POST['search']); ?>\"") === false,
    'coinlist.php must not echo $_POST["search"] raw into the value attribute (XSS B5).'
);

// --- B5 sibling: marketlist.php shares the same search reflection -------------
$marketlist = read_source($root, 'app/modules/kr-marketanalysis/views/marketlist.php');
assert_xss(
    strpos($marketlist, 'Krypto_Csrf::validateRequest(') !== false,
    'marketlist.php must validate CSRF before reflecting the search term (XSS B5).'
);
assert_xss(
    preg_match('/htmlspecialchars\(\$_POST\[\'search\'\]\s*,\s*ENT_QUOTES/', $marketlist) === 1,
    'marketlist.php must encode $_POST["search"] with htmlspecialchars(..., ENT_QUOTES) (XSS B5).'
);
assert_xss(
    strpos($marketlist, ": \$_POST['search']); ?>\"") === false,
    'marketlist.php must not echo $_POST["search"] raw into the value attribute (XSS B5).'
);

// --- B6: exportGraph.php container must be HTML-attribute encoded -------------
$exportGraph = read_source($root, 'app/modules/kr-dashboard/src/actions/exportGraph.php');
assert_xss(
    preg_match('/htmlspecialchars\(\$_POST\[\'container\'\]\s*,\s*ENT_QUOTES/', $exportGraph) === 1,
    'exportGraph.php must encode $_POST["container"] with htmlspecialchars(..., ENT_QUOTES) (XSS B6).'
);
assert_xss(
    strpos($exportGraph, 'container="<?php echo $_POST[\'container\']; ?>"') === false,
    'exportGraph.php must not echo $_POST["container"] raw into the attribute (XSS B6).'
);

echo "Reflected XSS encoding regression checks passed.\n";

?>
