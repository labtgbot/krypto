<?php

/**
 * Allowlist HTML sanitizer and URL scheme validator.
 *
 * Third-party content (RSS news feeds, coinmarketcal calendar events) is
 * rendered inside privileged dashboards. Before this helper the news module
 * only stripped <img>/<a> tags by hand, which left every other tag and every
 * `on*`/`javascript:` vector intact (stored XSS findings B7/B8 of the
 * 2026-06-01 audit).
 *
 * {@see HtmlSanitizer::sanitize()} keeps a small, safe allowlist of formatting
 * tags and drops everything else (scripts, event handlers, dangerous schemes).
 * {@see HtmlSanitizer::safeUrl()} validates the scheme of a single URL used in
 * `href`/`src`/CSS `url(...)` contexts.
 *
 * @package Krypto
 */
class HtmlSanitizer
{
    /**
     * Formatting tags allowed to survive sanitization. Anything else is either
     * dropped entirely (dangerous tags) or unwrapped (kept as text content).
     */
    const ALLOWED_TAGS = [
        'a', 'b', 'strong', 'i', 'em', 'u', 's', 'strike', 'sub', 'sup',
        'p', 'br', 'hr', 'span', 'div', 'blockquote', 'pre', 'code',
        'ul', 'ol', 'li', 'dl', 'dt', 'dd',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th', 'caption',
        'figure', 'figcaption',
    ];

    /**
     * Per-tag attribute allowlist. Attributes not listed here are removed,
     * which strips every `on*` handler and inline `style` vector.
     */
    const ALLOWED_ATTRS = [
        'a' => ['href', 'title'],
    ];

    /**
     * Tags removed together with their content (never unwrapped).
     */
    const DANGEROUS_TAGS = [
        'script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed',
        'applet', 'form', 'input', 'button', 'textarea', 'select', 'option',
        'link', 'meta', 'base', 'svg', 'math', 'template', 'noscript',
    ];

    /**
     * URL schemes considered safe for href/src contexts.
     */
    const ALLOWED_SCHEMES = ['http', 'https', 'mailto'];

    /**
     * Sanitize an untrusted HTML fragment against the allowlist above.
     *
     * @param  string|null $html Untrusted HTML.
     * @return string            Safe HTML fragment (may be empty).
     */
    public static function sanitize($html)
    {
        $html = (string) $html;
        if (trim($html) === '') {
            return '';
        }

        // Without the DOM extension we cannot parse safely; fall back to a
        // fully-escaped plain-text rendering rather than emit raw markup.
        if (!class_exists('DOMDocument')) {
            return htmlspecialchars(strip_tags($html), ENT_QUOTES, 'UTF-8');
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);

        // The XML encoding hint keeps multibyte characters intact, and the
        // <body> wrapper gives us a stable node to read the fragment back from.
        $loaded = $dom->loadHTML(
            '<?xml encoding="UTF-8"?><html><body>'.$html.'</body></html>',
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            return '';
        }

        $body = $dom->getElementsByTagName('body')->item(0);
        if ($body === null) {
            return '';
        }

        self::cleanChildren($body);

        $out = '';
        foreach ($body->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }

        return $out;
    }

    /**
     * Validate a single URL by scheme. Relative URLs and anchors are allowed;
     * scheme-bearing URLs must use an allowlisted scheme, otherwise an empty
     * string is returned so the caller can drop the attribute.
     *
     * @param  string|null $url      Untrusted URL.
     * @param  array       $schemes  Allowed schemes (defaults to ALLOWED_SCHEMES).
     * @return string                The original URL if safe, otherwise ''.
     */
    public static function safeUrl($url, $schemes = self::ALLOWED_SCHEMES)
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }

        // Strip control/whitespace characters that are used to smuggle schemes
        // such as "java\tscript:" or "java\nscript:" past naive checks.
        $probe = preg_replace('/[\x00-\x20\x7f]+/', '', $url);
        if ($probe === null) {
            return '';
        }

        if (preg_match('/^([a-z][a-z0-9+.\-]*):/i', $probe, $matches)) {
            $scheme = strtolower($matches[1]);
            if (!in_array($scheme, $schemes, true)) {
                return '';
            }
        }

        return $url;
    }

    /**
     * Recursively sanitize the children of a DOM node in place.
     *
     * @param DOMNode $node Parent node.
     */
    private static function cleanChildren($node)
    {
        // Snapshot the live NodeList because we mutate it while iterating.
        $children = iterator_to_array($node->childNodes);

        foreach ($children as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $tag = strtolower($child->nodeName);

                if (in_array($tag, self::DANGEROUS_TAGS, true)) {
                    $node->removeChild($child);
                    continue;
                }

                if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                    // Unknown but not inherently dangerous: keep the text by
                    // unwrapping the element after cleaning its descendants.
                    self::cleanChildren($child);
                    while ($child->firstChild !== null) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);
                    continue;
                }

                self::cleanAttributes($child, $tag);
                self::cleanChildren($child);
                continue;
            }

            // Strip comments (can hide conditional-comment scripts in old IE).
            if ($child->nodeType === XML_COMMENT_NODE
                || $child->nodeType === XML_PI_NODE) {
                $node->removeChild($child);
            }

            // Text nodes are serialized with entity encoding by saveHTML().
        }
    }

    /**
     * Remove every attribute not allowed for the given tag and validate URLs.
     *
     * @param DOMElement $element The element to clean.
     * @param string     $tag     Lower-cased tag name.
     */
    private static function cleanAttributes($element, $tag)
    {
        $allowed = isset(self::ALLOWED_ATTRS[$tag]) ? self::ALLOWED_ATTRS[$tag] : [];
        $attributes = iterator_to_array($element->attributes);

        foreach ($attributes as $attribute) {
            $name = strtolower($attribute->name);

            if (!in_array($name, $allowed, true)) {
                $element->removeAttribute($attribute->name);
                continue;
            }

            if ($name === 'href' || $name === 'src') {
                $safe = self::safeUrl($attribute->value);
                if ($safe === '') {
                    $element->removeAttribute($attribute->name);
                } else {
                    $element->setAttribute($attribute->name, $safe);
                }
            }
        }

        // Force links to open safely in a new tab without leaking the opener.
        if ($tag === 'a' && $element->hasAttribute('href')) {
            $element->setAttribute('target', '_blank');
            $element->setAttribute('rel', 'noopener noreferrer nofollow');
        }
    }
}
