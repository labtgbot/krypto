<?php

/**
 * Regression coverage for issue #90 (audit findings B2, B3, B7, B8): stored XSS
 * caused by persisting user/third-party content unsanitized and rendering it
 * without context-aware encoding, including inside privileged panels.
 *
 * The checks are a mix of:
 *   - static source assertions (a regression that re-introduces a raw echo or
 *     drops the escaping fails fast without a live server or database), and
 *   - behavioural assertions against the HtmlSanitizer helper.
 *
 * Covered sinks:
 *   - B2 signup name        -> sanitized on input (htmlspecialchars)
 *   - B3 chat messages      -> escaped in PHP (loadRoom/loadChat) and JS
 *   - B7 news feed          -> scalar fields escaped, HTML allowlist-sanitized
 *   - B8 calendar event     -> fields escaped, URLs scheme-validated
 */

$root = dirname(__DIR__);

function assert_sxss($condition, $message)
{
    if (!$condition) {
        throw new Exception($message);
    }
}

function read_source_sxss($root, $relative)
{
    $source = @file_get_contents($root.'/'.$relative);
    assert_sxss($source !== false && trim($source) !== '', 'Cannot read '.$relative);
    return $source;
}

// --- B2: account creation must sanitize the display name on input ------------
// The sanitization is centralized in User::_createUser so every creation path
// (signup, OAuth callbacks, admin-created users) is covered by one choke point.
$user = read_source_sxss($root, 'app/src/User/User.php');
assert_sxss(
    preg_match('/\$name\s*=\s*htmlspecialchars\(\s*trim\(\$name\)\s*,\s*ENT_QUOTES/', $user) === 1,
    'User::_createUser must sanitize the display name with htmlspecialchars(trim(...), ENT_QUOTES) (XSS B2).'
);
$signup = read_source_sxss($root, 'app/modules/kr-user/src/actions/signup.php');
assert_sxss(
    strpos($signup, '_createUser(') !== false,
    'signup.php must create the account through User::_createUser so the name is sanitized (XSS B2).'
);

// --- B3: chat message text/metadata escaped in PHP ---------------------------
$loadRoom = read_source_sxss($root, 'app/modules/kr-chat/src/actions/loadRoom.php');
assert_sxss(
    preg_match('/htmlspecialchars\(\$Message->_getValueMessage\(\)\s*,\s*ENT_QUOTES/', $loadRoom) === 1,
    'loadRoom.php must escape the chat message value with htmlspecialchars(..., ENT_QUOTES) (XSS B3).'
);
assert_sxss(
    strpos($loadRoom, '<div><?php echo $Message->_getValueMessage(); ?></div>') === false,
    'loadRoom.php must not echo the chat message value raw (XSS B3).'
);
assert_sxss(
    preg_match('/htmlspecialchars\(\$Message->_getFileName\(\)\s*,\s*ENT_QUOTES/', $loadRoom) === 1,
    'loadRoom.php must escape the chat file name (XSS B3).'
);

$loadChat = read_source_sxss($root, 'app/modules/kr-chat/src/actions/loadChat.php');
assert_sxss(
    preg_match('/htmlspecialchars\(\$Room->_getLastMsgText\(\)\s*,\s*ENT_QUOTES/', $loadChat) === 1,
    'loadChat.php must escape the last message text with htmlspecialchars(..., ENT_QUOTES) (XSS B3).'
);

// --- B3: chat JS escapes values before DOM injection -------------------------
$chatJs = read_source_sxss($root, 'app/modules/kr-chat/statics/js/chat.js');
assert_sxss(
    strpos($chatJs, 'krEscapeHtml') !== false,
    'chat.js must define/use an escapeHtml helper before injecting values (XSS B3).'
);
assert_sxss(
    strpos($chatJs, "'<div kr-chat-msg-id=\"' + msg_data.id_encrypted + '\"><div>' + msg_data.value_msg_room_chat + '</div></div>'") === false,
    'chat.js must not concatenate the raw message value into the DOM (XSS B3).'
);
assert_sxss(
    strpos($chatJs, 'krEscapeHtml(msg_data.value_msg_room_chat)') !== false,
    'chat.js must escape msg_data.value_msg_room_chat before injection (XSS B3).'
);
assert_sxss(
    strpos($chatJs, 'krEscapeHtml(user_data.name)') !== false,
    'chat.js must escape the sender name before injection (XSS B3).'
);

$barJs = read_source_sxss($root, 'app/modules/kr-chat/statics/js/bar.js');
assert_sxss(
    strpos($barJs, 'krEscapeHtml') !== false,
    'bar.js must escape room metadata before DOM injection (XSS B3).'
);
assert_sxss(
    strpos($barJs, "'background-image:url(\\'' + infos_room.picture + '\\')'") === false,
    'bar.js must not concatenate the raw room picture URL into the DOM (XSS B3).'
);

// --- B7: news scalar fields escaped, content allowlist-sanitized -------------
$loadNews = read_source_sxss($root, 'app/modules/kr-news/src/actions/loadNews.php');
foreach (['_getTitle', '_getAuthor', '_getFrom'] as $getter) {
    assert_sxss(
        preg_match('/htmlspecialchars\(\$ArticleSelected->'.$getter.'\(\)\s*,\s*ENT_QUOTES/', $loadNews) === 1,
        'loadNews.php must escape '.$getter.'() output with htmlspecialchars(..., ENT_QUOTES) (XSS B7).'
    );
}
assert_sxss(
    strpos($loadNews, 'htmlspecialchars(HtmlSanitizer::safeUrl($ArticleSelected->_getUrl())') !== false,
    'loadNews.php must scheme-validate and escape the article URL (XSS B7).'
);
assert_sxss(
    strpos($loadNews, 'htmlspecialchars(HtmlSanitizer::safeUrl($ArticleSelected->_getPicture())') !== false,
    'loadNews.php must scheme-validate and escape the article picture URL (XSS B7).'
);
assert_sxss(
    strpos($loadNews, "echo \$ArticleSelected->_getTitle();") === false,
    'loadNews.php must not echo the article title raw (XSS B7).'
);

$rssArticle = read_source_sxss($root, 'app/modules/kr-news/src/RssFeedArticle.php');
assert_sxss(
    strpos($rssArticle, 'HtmlSanitizer::sanitize($content)') !== false,
    'RssFeedArticle::_getContent must run feed HTML through HtmlSanitizer::sanitize (XSS B7).'
);
assert_sxss(
    strpos($rssArticle, "str_replace('<a', '<a target=_bank', \$content)") === false,
    'RssFeedArticle::_getContent must drop the unsafe hand-rolled <a> rewriting (XSS B7).'
);

// --- B8: calendar fields escaped, URLs scheme-validated ----------------------
$calendar = read_source_sxss($root, 'app/modules/kr-news/src/actions/loadSideCalendarItem.php');
foreach (["title", "description", "formate_date"] as $field) {
    assert_sxss(
        preg_match('/htmlspecialchars\(\$Event\[\''.$field.'\'\]\s*,\s*ENT_QUOTES/', $calendar) === 1,
        'loadSideCalendarItem.php must escape $Event["'.$field.'"] (XSS B8).'
    );
}
assert_sxss(
    strpos($calendar, "htmlspecialchars(HtmlSanitizer::safeUrl(\$Event['source'])") !== false,
    'loadSideCalendarItem.php must scheme-validate and escape the event source URL (XSS B8).'
);
assert_sxss(
    strpos($calendar, "htmlspecialchars(HtmlSanitizer::safeUrl(\$Event['proof'])") !== false,
    'loadSideCalendarItem.php must scheme-validate and escape the event proof URL (XSS B8).'
);
assert_sxss(
    strpos($calendar, "echo \$Event['description'];") === false,
    'loadSideCalendarItem.php must not echo the event description raw (XSS B8).'
);

// --- HtmlSanitizer behaviour -------------------------------------------------
require_once $root.'/app/src/Security/HtmlSanitizer.php';

assert_sxss(class_exists('HtmlSanitizer'), 'HtmlSanitizer class must be defined.');

$dropsScript = HtmlSanitizer::sanitize('<script>alert(1)</script>safe');
assert_sxss(
    strpos($dropsScript, '<script') === false && strpos($dropsScript, 'safe') !== false,
    'HtmlSanitizer must drop <script> tags while keeping surrounding text.'
);

$dropsHandler = HtmlSanitizer::sanitize('<p onclick="alert(1)">x</p>');
assert_sxss(
    strpos($dropsHandler, 'onclick') === false && strpos($dropsHandler, '<p>') !== false,
    'HtmlSanitizer must drop inline event handlers but keep allowed tags.'
);

$dropsJsHref = HtmlSanitizer::sanitize('<a href="javascript:alert(1)">link</a>');
assert_sxss(
    stripos($dropsJsHref, 'javascript:') === false,
    'HtmlSanitizer must drop javascript: hrefs.'
);

$keepsHttp = HtmlSanitizer::sanitize('<a href="https://example.com">ok</a>');
assert_sxss(
    strpos($keepsHttp, 'href="https://example.com"') !== false
        && strpos($keepsHttp, 'rel="noopener noreferrer nofollow"') !== false,
    'HtmlSanitizer must keep safe https hrefs and harden the rel attribute.'
);

$dropsImg = HtmlSanitizer::sanitize('<img src="x" onerror="alert(1)">tail');
assert_sxss(
    strpos($dropsImg, '<img') === false && strpos($dropsImg, 'tail') !== false,
    'HtmlSanitizer must drop <img> tags (feed pictures handled separately).'
);

$keepsUnicode = HtmlSanitizer::sanitize('Привет <b>мир</b>');
assert_sxss(
    strpos($keepsUnicode, 'Привет') !== false && strpos($keepsUnicode, '<b>мир</b>') !== false,
    'HtmlSanitizer must preserve multibyte (UTF-8) text.'
);

assert_sxss(HtmlSanitizer::safeUrl('https://ok.com') === 'https://ok.com', 'safeUrl must allow https.');
assert_sxss(HtmlSanitizer::safeUrl('mailto:a@b.com') === 'mailto:a@b.com', 'safeUrl must allow mailto.');
assert_sxss(HtmlSanitizer::safeUrl('/relative') === '/relative', 'safeUrl must allow relative URLs.');
assert_sxss(HtmlSanitizer::safeUrl('javascript:alert(1)') === '', 'safeUrl must reject javascript:.');
assert_sxss(HtmlSanitizer::safeUrl('data:text/html,x') === '', 'safeUrl must reject data:.');
assert_sxss(HtmlSanitizer::safeUrl("java\tscript:alert(1)") === '', 'safeUrl must reject whitespace-obfuscated schemes.');

echo "Stored XSS encoding regression check passed\n";
