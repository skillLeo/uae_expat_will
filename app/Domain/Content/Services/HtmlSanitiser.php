<?php

namespace App\Domain\Content\Services;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Strips anything from stored HTML that could execute.
 *
 * Article bodies are written in the admin and rendered with v-html, so
 * whatever is saved runs in every visitor's browser. That is fine while the
 * only people who can write are trusted — but `content.edit` is a permission,
 * and a permission can be given to a coordinator, an agency or a temporary
 * account. Script stored by any of them would then run for every reader and,
 * worse, for a Super Administrator reading the same page while signed in.
 *
 * So the rule is an allowlist, not a blocklist: anything not named here is
 * removed. Blocklists are a losing game — there is always another vector.
 *
 * This runs on save rather than on render, so the stored value is already
 * safe and nothing depends on remembering to sanitise at every read.
 */
class HtmlSanitiser
{
    /** Tags a writer needs, and nothing else. No script, style, iframe, form. */
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 'a', 'ul', 'ol', 'li',
        'h2', 'h3', 'h4', 'blockquote', 'table', 'thead', 'tbody', 'tr',
        'th', 'td', 'hr', 'span', 'sup', 'sub',
    ];

    /** @var array<string, list<string>> */
    private const ALLOWED_ATTRIBUTES = [
        'a' => ['href', 'title', 'rel', 'target'],
        'th' => ['colspan', 'rowspan'],
        'td' => ['colspan', 'rowspan'],
    ];

    /** Anything else — javascript:, data:, vbscript: — is a way to run code. */
    private const ALLOWED_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    public function clean(?string $html): string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return '';
        }

        $document = new DOMDocument;

        $previous = libxml_use_internal_errors(true);
        // Wrapped so a fragment parses, and marked UTF-8 so Arabic and the
        // typographic quotes used across this site survive the round trip.
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('root');

        if ($root === null) {
            return '';
        }

        $this->cleanNode($root);

        $out = '';

        foreach ($root->childNodes as $child) {
            $out .= $document->saveHTML($child);
        }

        return trim($out);
    }

    private function cleanNode(DOMNode $node): void
    {
        // Reversed, because removing a child while iterating forwards skips
        // the next sibling.
        for ($i = $node->childNodes->length - 1; $i >= 0; $i--) {
            $child = $node->childNodes->item($i);

            if ($child === null) {
                continue;
            }

            if ($child instanceof DOMElement) {
                if (! in_array(strtolower($child->nodeName), self::ALLOWED_TAGS, true)) {
                    // The tag goes; its text stays, so removing a stray <div>
                    // does not silently delete a paragraph of somebody's work.
                    $this->unwrap($child);

                    continue;
                }

                $this->cleanAttributes($child);
                $this->cleanNode($child);

                continue;
            }

            // Comments can carry payloads in some parsers and are never wanted.
            if ($child->nodeType === XML_COMMENT_NODE) {
                $node->removeChild($child);
            }
        }
    }

    private function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;

        if ($parent === null) {
            return;
        }

        // A script or style tag's *contents* are code, not prose, so they are
        // dropped rather than unwrapped into the page as text.
        if (in_array(strtolower($element->nodeName), ['script', 'style', 'iframe', 'object', 'embed'], true)) {
            $parent->removeChild($element);

            return;
        }

        while ($element->firstChild !== null) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }

    private function cleanAttributes(DOMElement $element): void
    {
        $tag = strtolower($element->nodeName);
        $allowed = self::ALLOWED_ATTRIBUTES[$tag] ?? [];

        for ($i = $element->attributes->length - 1; $i >= 0; $i--) {
            $attribute = $element->attributes->item($i);

            if ($attribute === null) {
                continue;
            }

            $name = strtolower($attribute->nodeName);

            // This removes every on* handler in one rule, which is the point:
            // onclick, onerror, onmouseover and whatever is invented next.
            if (! in_array($name, $allowed, true)) {
                $element->removeAttribute($attribute->nodeName);

                continue;
            }

            if ($name === 'href' && ! $this->isSafeUrl($attribute->nodeValue)) {
                $element->removeAttribute('href');
            }
        }

        // A link opening a new tab without this can reach back into the page
        // that opened it.
        if ($tag === 'a' && $element->getAttribute('target') === '_blank') {
            $element->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private function isSafeUrl(?string $url): bool
    {
        $url = trim((string) $url);

        if ($url === '') {
            return false;
        }

        // Relative and anchor links are fine and carry no scheme.
        if (str_starts_with($url, '/') || str_starts_with($url, '#')) {
            return true;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, self::ALLOWED_SCHEMES, true);
    }
}
