<?php

/**
 * Regression coverage for SEC-32 (#147).
 *
 * The hidden-thirdparty Blockfolio branch must keep the holding size numeric
 * for profit math and streamer attributes. Formatting with thousands
 * separators belongs only in the visible HTML text.
 */

$root = dirname(__DIR__);
$viewPath = $root.'/app/modules/kr-blockfolio/views/blockfolio.php';
$viewSource = @file_get_contents($viewPath);
if($viewSource === false) {
    throw new Exception('Cannot read Blockfolio view at '.$viewPath);
}

function blockfolio_hidden_thirdparty_assert($condition, $message) {
    if(!$condition) {
        throw new Exception($message);
    }
}

$formattedHoldingSize = number_format(1234.56, 8, '.', ',');
blockfolio_hidden_thirdparty_assert(
    floatval($formattedHoldingSize) === 1.0,
    'Test setup should demonstrate why formatted thousands strings cannot be used in arithmetic.'
);

blockfolio_hidden_thirdparty_assert(
    !preg_match('/\$holdingSize\s*=\s*\$App->_formatNumber\s*\(\s*\$holdingSize\s*,\s*\$DecimalShown\s*\)\s*;/s', $viewSource),
    'Blockfolio hidden-thirdparty branch must not overwrite numeric $holdingSize with a formatted string.'
);

blockfolio_hidden_thirdparty_assert(
    preg_match('/\$holdingSizeDisplay\s*=\s*\$App->_formatNumber\s*\(\s*\$holdingSize\s*,\s*\$DecimalShown\s*\)\s*;/s', $viewSource) === 1,
    'Blockfolio hidden-thirdparty branch should format the holding size into a separate display variable.'
);

blockfolio_hidden_thirdparty_assert(
    preg_match('/kr-holding-size="<\?php echo \$holdingSize; \?>"/s', $viewSource) === 1,
    'kr-holding-size must expose the raw numeric holding size for streamer recalculations.'
);

blockfolio_hidden_thirdparty_assert(
    preg_match('/<span class="kr-mono"><\?php echo \$holdingSizeDisplay; \?> <\?php echo \$Coin->_getSymbol\(\); \?><\/span>/s', $viewSource) === 1,
    'Visible Blockfolio holding text should use the formatted display value.'
);

echo "Blockfolio hidden-thirdparty profit regression checks passed.\n";

?>
