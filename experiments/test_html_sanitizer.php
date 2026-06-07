<?php
require __DIR__.'/../app/src/Security/HtmlSanitizer.php';

$cases = [
    '<p>Hello <b>world</b></p>',
    '<script>alert(1)</script>safe',
    '<p onclick="alert(1)">x</p>',
    '<a href="javascript:alert(1)">link</a>',
    '<a href="https://example.com">ok</a>',
    '<img src="x" onerror="alert(1)">',
    '<div style="background:url(javascript:alert(1))">styled</div>',
    '<a href="java&#09;script:alert(1)">obf</a>',
    '<iframe src="https://evil.com"></iframe>after',
    'Привет <b>мир</b> & co <script>bad()</script>',
    '<a href="//cdn.example.com/x">proto-rel</a>',
    '<a href="data:text/html,<script>alert(1)</script>">data</a>',
];

foreach ($cases as $i => $c) {
    echo "[$i] IN : $c\n";
    echo "    OUT: ".HtmlSanitizer::sanitize($c)."\n";
}

echo "\n--- safeUrl ---\n";
$urls = ['https://ok.com', 'http://ok.com', 'javascript:alert(1)', 'data:text/html,x',
         '/relative/path', '#anchor', 'mailto:a@b.com', "java\tscript:alert(1)", 'vbscript:msgbox(1)'];
foreach ($urls as $u) {
    echo str_pad($u, 30)." => '".HtmlSanitizer::safeUrl($u)."'\n";
}
