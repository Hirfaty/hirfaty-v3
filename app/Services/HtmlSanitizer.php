<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Allowlist sanitiser for the rich-text HTML the blog editor produces.
 *
 * The editor is admin-only, so this is not the first line of defence — but the
 * output is stored verbatim and injected into a public page with
 * dangerouslySetInnerHTML, which means a single compromised staff account (or
 * a hand-rolled POST to the update route) would otherwise turn into stored XSS
 * for every visitor. Anything not explicitly listed below is stripped.
 */
class HtmlSanitizer
{
    /**
     * Tags Tiptap can emit, mapped to the attributes each may keep.
     *
     * @var array<string, array<int, string>>
     */
    private const ALLOWED = [
        'p' => ['style'],
        'br' => [],
        'strong' => [],
        'b' => [],
        'em' => [],
        'i' => [],
        'u' => [],
        's' => [],
        'h1' => ['style'],
        'h2' => ['style'],
        'h3' => ['style'],
        'h4' => ['style'],
        'ul' => [],
        'ol' => ['start'],
        'li' => [],
        'blockquote' => [],
        'hr' => [],
        'code' => [],
        'pre' => [],
        'a' => ['href', 'target', 'rel'],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
    ];

    /**
     * URL schemes a href/src may use. Notably excludes `javascript:` and
     * `data:` — the latter because an SVG data URI executes script.
     */
    private const ALLOWED_SCHEMES = ['http', 'https', 'mailto'];

    public function clean(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $document = new DOMDocument;

        // Wrap in a container with an explicit charset: without the meta,
        // DOMDocument assumes ISO-8859-1 and mangles every accented French and
        // Arabic character in the article.
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="hirfaty-root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('hirfaty-root');

        if (! $root instanceof DOMElement) {
            return null;
        }

        // Comments can carry conditional-comment payloads and are never
        // meaningful in article copy.
        $xpath = new DOMXPath($document);

        foreach (iterator_to_array($xpath->query('//comment()') ?: []) as $comment) {
            $comment->parentNode?->removeChild($comment);
        }

        $this->cleanChildren($root);

        $out = '';

        foreach ($root->childNodes as $child) {
            $out .= $document->saveHTML($child);
        }

        return trim($out) === '' ? null : trim($out);
    }

    /**
     * clean(), but null when nothing readable is left: the editor's empty
     * document is `<p></p>`, which is markup, not content.
     */
    public function cleanProse(?string $html): ?string
    {
        $clean = $this->clean($html);

        return self::toText($clean) === null ? null : $clean;
    }

    /**
     * Editor HTML as plain text, one line per paragraph, list item or <br> —
     * for places that cannot render markup (a card snippet, a meta tag).
     */
    public static function toText(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $text = preg_replace('#<br\s*/?>|</(p|li|h[1-6]|blockquote|pre|div)>#i', "\n", $html);
        $text = html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return preg_match('/\S/u', str_replace("\u{00A0}", ' ', $text)) === 1 ? rtrim($text) : null;
    }

    /**
     * Walk a snapshot of the children — the live NodeList shifts underneath us
     * as disallowed elements are unwrapped or removed.
     */
    private function cleanChildren(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                $this->cleanElement($child);

                continue;
            }

            // Keep text, drop everything exotic (CDATA, processing
            // instructions, doctypes).
            if ($child->nodeType !== XML_TEXT_NODE) {
                $parent->removeChild($child);
            }
        }
    }

    private function cleanElement(DOMElement $element): void
    {
        $tag = strtolower($element->tagName);

        if (! array_key_exists($tag, self::ALLOWED)) {
            // <script>/<style> contents are markup, not prose: unwrapping them
            // would paste raw JS into the article body as visible text.
            if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed'], true)) {
                $element->parentNode?->removeChild($element);

                return;
            }

            $this->unwrap($element);

            return;
        }

        $allowedAttributes = self::ALLOWED[$tag];

        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->nodeName);

            if (! in_array($name, $allowedAttributes, true)) {
                $element->removeAttribute($attribute->nodeName);

                continue;
            }

            $this->cleanAttribute($element, $name, $attribute->nodeValue ?? '');
        }

        // An anchor that opens a new tab without noopener hands the opener
        // window to the destination page.
        if ($tag === 'a' && $element->getAttribute('target') !== '') {
            $element->setAttribute('rel', 'noopener noreferrer');
        }

        $this->cleanChildren($element);
    }

    private function cleanAttribute(DOMElement $element, string $name, string $value): void
    {
        if ($name === 'href' || $name === 'src') {
            if (! $this->isSafeUrl($value)) {
                $element->removeAttribute($name);
            }

            return;
        }

        // TextAlign is the only inline style the editor produces; anything else
        // in the style attribute (position, background-image: url(...)) is not
        // ours and goes.
        if ($name === 'style') {
            if (preg_match('/^\s*text-align:\s*(left|right|center|justify);?\s*$/i', $value) !== 1) {
                $element->removeAttribute('style');
            }
        }
    }

    /**
     * Relative URLs (our own uploads under /storage/...) are fine; absolute
     * ones must use a known-safe scheme.
     */
    private function isSafeUrl(string $url): bool
    {
        $url = trim($url);

        if ($url === '') {
            return false;
        }

        // Reject control characters first — "java\0script:" and friends sneak
        // past a naive scheme check but are re-joined by the browser.
        if (preg_match('/[\x00-\x1F\x7F]/', $url) === 1) {
            return false;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        if ($scheme === null || $scheme === false) {
            // No scheme: relative path or protocol-relative. The latter can
            // point anywhere, so only allow plain paths and fragments.
            return ! str_starts_with($url, '//');
        }

        return in_array(strtolower($scheme), self::ALLOWED_SCHEMES, true);
    }

    /** Replace an element with its (recursively cleaned) children. */
    private function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;

        if ($parent === null) {
            return;
        }

        $this->cleanChildren($element);

        foreach (iterator_to_array($element->childNodes) as $child) {
            $parent->insertBefore($child, $element);
        }

        $parent->removeChild($element);
    }
}
