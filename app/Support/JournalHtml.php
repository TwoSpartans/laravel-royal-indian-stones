<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

class JournalHtml
{
    /**
     * @var list<string>
     */
    private const ALLOWED = [
        'p', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'strong', 'em', 'a', 'img',
        'blockquote', 'br', 'figure', 'figcaption', 'table', 'thead', 'tbody', 'tr', 'th', 'td',
    ];

    public static function containsDangerousMarkup(string $html): bool
    {
        return preg_match('/<\s*(script|iframe|object|embed)\b|javascript\s*:|\bon[a-z]+\s*=/i', $html) === 1;
    }

    public static function clean(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body><div id="journal-root">'.$html.'</div></body></html>',
            LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementsByTagName('div')->item(0);
        if (! $root instanceof DOMElement) {
            return '';
        }

        self::sanitizeChildren($root);

        $result = '';
        foreach ($root->childNodes as $child) {
            $result .= $document->saveHTML($child);
        }

        return trim($result);
    }

    public static function isBlank(string $html): bool
    {
        $text = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $text === '' && ! str_contains(strtolower($html), '<img');
    }

    private static function sanitizeChildren(DOMElement $element): void
    {
        $children = [];
        foreach ($element->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            self::sanitizeNode($child);
        }
    }

    private static function sanitizeNode(DOMNode $node): void
    {
        if ($node->nodeType === XML_COMMENT_NODE || $node->nodeType === XML_PI_NODE) {
            $node->parentNode?->removeChild($node);

            return;
        }

        if (! $node instanceof DOMElement) {
            return;
        }

        self::sanitizeChildren($node);

        $tag = strtolower($node->nodeName);
        $current = match ($tag) {
            'h1' => self::rename($node, 'h2'),
            'div' => self::rename($node, 'p'),
            'b' => self::rename($node, 'strong'),
            'i' => self::rename($node, 'em'),
            default => $node,
        };
        $tag = strtolower($current->nodeName);

        if (! in_array($tag, self::ALLOWED, true)) {
            self::unwrap($current);

            return;
        }

        self::filterAttributes($current, $tag);
        if ($tag === 'img' && ! $current->hasAttribute('src')) {
            $current->parentNode?->removeChild($current);

            return;
        }
        if ($tag === 'a' && ! $current->hasAttribute('href')) {
            self::unwrap($current);
        }
    }

    private static function filterAttributes(DOMElement $element, string $tag): void
    {
        $allowed = match ($tag) {
            'a' => ['href'],
            'img' => ['src', 'alt'],
            'th', 'td' => ['colspan', 'rowspan'],
            default => [],
        };

        $kept = [];
        foreach ($element->attributes ?? [] as $attribute) {
            $name = strtolower($attribute->name);
            if (! in_array($name, $allowed, true)) {
                continue;
            }
            $value = trim($attribute->value);
            if ($name === 'href' && self::safeHref($value) === null) {
                continue;
            }
            if ($name === 'src' && self::safeImageSource($value) === null) {
                continue;
            }
            if (in_array($name, ['colspan', 'rowspan'], true) && preg_match('/^[1-9]\d?$/', $value) !== 1) {
                continue;
            }
            $kept[$name] = $value;
        }

        while ($element->attributes?->length) {
            $element->removeAttribute($element->attributes->item(0)->name);
        }
        foreach ($kept as $name => $value) {
            $element->setAttribute($name, $value);
        }
    }

    private static function safeHref(string $href): ?string
    {
        if (preg_match('#^(https://|/)#i', $href) !== 1 || str_starts_with($href, '//')) {
            return null;
        }

        return $href;
    }

    private static function safeImageSource(string $src): ?string
    {
        if (str_contains($src, '..')) {
            return null;
        }
        if (preg_match('#^(https://|assets/)#i', $src) !== 1) {
            return null;
        }

        return $src;
    }

    private static function rename(DOMElement $element, string $tag): DOMElement
    {
        $replacement = $element->ownerDocument->createElement($tag);
        while ($element->firstChild instanceof DOMNode) {
            $replacement->appendChild($element->firstChild);
        }
        $element->parentNode?->replaceChild($replacement, $element);

        return $replacement;
    }

    private static function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;
        if ($parent === null) {
            return;
        }
        while ($element->firstChild instanceof DOMNode) {
            $parent->insertBefore($element->firstChild, $element);
        }
        $parent->removeChild($element);
    }
}
