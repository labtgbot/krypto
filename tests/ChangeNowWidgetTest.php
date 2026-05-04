<?php

require __DIR__.'/../app/modules/kr-changenow/src/ChangeNowWidget.php';

function assertSameValue($expected, $actual, $message){
  if($expected !== $actual){
    throw new Exception($message.' Expected '.var_export($expected, true).', got '.var_export($actual, true));
  }
}

function assertTrueValue($condition, $message){
  if(!$condition){
    throw new Exception($message);
  }
}

function assertArrayKeysSame($expected, $actual, $message){
  sort($expected);
  sort($actual);
  assertSameValue($expected, $actual, $message);
}

$rawConfig = [
  'enabled' => '1',
  'amount' => '1<script>',
  'amount_fiat' => '1500.50',
  'from' => 'BTC<script>',
  'to' => 'ETH',
  'from_fiat' => 'USD',
  'to_fiat' => 'BTC',
  'fiat_mode' => '1',
  'lang' => 'en-US<script>',
  'dark_mode' => 'on',
  'logo' => '1',
  'faq' => '0',
  'locales' => '1',
  'primary_color' => '#123abc',
  'background_color' => 'not-a-color',
  'horizontal' => '1',
  'link_id' => 'partner_42<script>',
  'fallback_url' => 'javascript:alert(1)',
  'place_dashboard' => '1',
  'unexpected' => '<script>alert(1)</script>'
];

$iframeUrl = ChangeNowWidget::_buildIframeUrl($rawConfig);
$urlParts = parse_url($iframeUrl);
parse_str($urlParts['query'], $query);

assertSameValue('https', $urlParts['scheme'], 'Widget URL must use HTTPS.');
assertSameValue('changenow.io', $urlParts['host'], 'Widget URL must target ChangeNOW.');
assertSameValue('/embeds/exchange-widget/v2/widget.html', $urlParts['path'], 'Widget URL must use the v2 iframe path.');
assertArrayKeysSame(ChangeNowWidget::_getExpectedQueryKeys(), array_keys($query), 'Widget URL must include only expected query keys.');

assertSameValue('0.1', $query['amount'], 'Invalid amounts must fall back to the default.');
assertSameValue('1500.50', $query['amountFiat'], 'Valid fiat amount must be preserved.');
assertSameValue('btc', $query['from'], 'Invalid source asset must fall back to the default.');
assertSameValue('eth', $query['to'], 'Asset symbols must be normalized.');
assertSameValue('usd', $query['fromFiat'], 'Fiat source must be normalized.');
assertSameValue('btc', $query['toFiat'], 'Fiat target must be normalized.');
assertSameValue('true', $query['isFiat'], 'Fiat mode must render as true when enabled.');
assertSameValue('en-US', $query['lang'], 'Invalid language must fall back to the default.');
assertSameValue('true', $query['darkMode'], 'Dark mode must be normalized.');
assertSameValue('false', $query['FAQ'], 'FAQ visibility must be normalized.');
assertSameValue('123ABC', $query['primaryColor'], 'Hex colors must be normalized.');
assertSameValue('FFFFFF', $query['backgroundColor'], 'Invalid background colors must fall back to default.');
assertSameValue('partner_42script', $query['link_id'], 'Link IDs must be sanitized without losing attribution.');
assertTrueValue(strpos($iframeUrl, '<') === false && strpos($iframeUrl, '>') === false, 'Widget URL must not contain HTML control characters.');
assertTrueValue(!array_key_exists('unexpected', $query), 'Unexpected params must not be rendered.');

$fallbackUrl = ChangeNowWidget::_buildFallbackUrl($rawConfig);
$fallbackParts = parse_url($fallbackUrl);
parse_str($fallbackParts['query'], $fallbackQuery);
assertSameValue('https', $fallbackParts['scheme'], 'Fallback URL must use HTTPS.');
assertSameValue('changenow.io', $fallbackParts['host'], 'Fallback URL must target ChangeNOW.');
assertSameValue('partner_42script', $fallbackQuery['link_id'], 'Fallback URL must preserve sanitized partner attribution.');

$disabledRender = ChangeNowWidget::_render(['enabled' => '0', 'place_dashboard' => '1'], 'dashboard');
assertSameValue('', $disabledRender, 'Disabled widget must not render.');

$enabledRender = ChangeNowWidget::_render(array_merge($rawConfig, ['amount' => '0.5']), 'dashboard');
assertTrueValue(strpos($enabledRender, '<iframe') !== false, 'Enabled placement must render an iframe.');
assertTrueValue(strpos($enabledRender, 'stepper-connector.js') !== false, 'Rendered widget must include the ChangeNOW connector script.');
assertTrueValue(strpos($enabledRender, 'Open ChangeNOW') !== false, 'Rendered widget must include fallback messaging.');

echo "ChangeNowWidgetTest OK\n";
