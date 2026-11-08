<?php

declare(strict_types=1);

namespace CivicPulse\Framework\View;

use CivicPulse\Framework\View\Exceptions\ViewException;
use Dom\Element;
use Dom\HTMLDocument;
use Dom\Node;

final class HtmlSanitizer
{
    private const MAX_INPUT_LENGTH = 102400;

    private const ALLOWED_ELEMENTS = [
        'p',
        'div',
        'span',
        'br',
        'hr',
        'h2',
        'h3',
        'h4',
        'strong',
        'b',
        'em',
        'i',
        'u',
        's',
        'small',
        'ul',
        'ol',
        'li',
        'blockquote',
        'code',
        'pre',
        'a',
    ];

    private const DANGEROUS_ELEMENTS = [
        'script',
        'style',
        'iframe',
        'object',
        'embed',
        'svg',
        'math',
        'form',
        'input',
        'button',
        'textarea',
        'select',
        'option',
        'video',
        'audio',
        'source',
        'link',
        'meta',
        'base',
        'applet',
        'canvas',
    ];

    /**
     * Sanitize untrusted HTML and return safe markup.
     */
    public function sanitize(string $html): string
    {
        if ($html === '') {
            return '';
        }

        if (strlen($html) > self::MAX_INPUT_LENGTH) {
            throw new ViewException(
                'HTML content exceeds the maximum allowed size.'
            );
        }

        if (preg_match('//u', $html) !== 1) {
            throw new ViewException(
                'HTML content must contain valid UTF-8.'
            );
        }

        $documentHtml = '<!DOCTYPE html>'
            . '<html>'
            . '<head></head>'
            . '<body>'
            . $html
            . '</body>'
            . '</html>';

        try {
            $document = HTMLDocument::createFromString(
                $documentHtml,
                0,
                'UTF-8'
            );
        } catch (\Throwable $exception) {
            throw new ViewException(
                'Unable to parse HTML content.',
                previous: $exception
            );
        }

        $body = $document->body;

        if (!$body instanceof Element) {
            return '';
        }

        $this->sanitizeChildren($body);

        $result = '';

        foreach ($body->childNodes as $node) {
            $serialized = $document->saveHtml($node);

            if ($serialized === false) {
                throw new ViewException(
                    'Unable to serialize sanitized HTML.'
                );
            }

            $result .= $serialized;
        }

        return $result;
    }

    /**
     * Sanitize the children of a DOM node.
     */
    private function sanitizeChildren(Node $parent): void
    {
        // Snapshot the children because sanitization can modify the DOM.
        $children = iterator_to_array($parent->childNodes);

        foreach ($children as $child) {
            if ($child instanceof Element) {
                $this->sanitizeElement($parent, $child);
                continue;
            }

            // Preserve text nodes; remove comments and other node types.
            if ($child->nodeType !== XML_TEXT_NODE) {
                $parent->removeChild($child);
            }
        }
    }

    /**
     * Sanitize one element and its descendants.
     */
    private function sanitizeElement(
        Node $parent,
        Element $element
    ): void {
        $name = strtolower($element->tagName);

        // Dangerous elements and all their descendants are removed.
        if (in_array($name, self::DANGEROUS_ELEMENTS, true)) {
            $parent->removeChild($element);
            return;
        }

        // Sanitize descendants before unwrapping an unsupported element.
        $this->sanitizeChildren($element);

        // Unsupported harmless elements are unwrapped.
        if (!in_array($name, self::ALLOWED_ELEMENTS, true)) {
            $children = iterator_to_array($element->childNodes);

            foreach ($children as $child) {
                $parent->insertBefore($child, $element);
            }

            $parent->removeChild($element);
            return;
        }

        $this->sanitizeAttributes($element);
    }

    /**
     * Keep only explicitly permitted attributes.
     */
    private function sanitizeAttributes(Element $element): void
    {
        $tagName = strtolower($element->tagName);

        $allowedAttributes = $tagName === 'a'
            ? ['href', 'title']
            : [];

        // Snapshot attribute names before modifying the element.
        $attributeNames = [];

        foreach ($element->attributes as $attribute) {
            $attributeNames[] = $attribute->name;
        }

        foreach ($attributeNames as $attributeName) {
            $name = strtolower($attributeName);

            if (!in_array($name, $allowedAttributes, true)) {
                $element->removeAttribute($attributeName);
                continue;
            }

            if (
                $tagName === 'a'
                && $name === 'href'
                && !$this->isSafeUrl($element->getAttribute($attributeName))
            ) {
                $element->removeAttribute($attributeName);
            }
        }
    }

    /**
     * Determine whether a URL follows the permitted URL policy.
     */
    private function isSafeUrl(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        // Reject whitespace, control characters and backslash ambiguity.
        if (
            preg_match('/[\x00-\x20\x7F]/', $url) === 1
            || str_contains($url, '\\')
        ) {
            return false;
        }

        // Reject malformed percent-encoding.
        if (preg_match('/%(?![a-fA-F0-9]{2})/', $url) === 1) {
            return false;
        }

        // Reject percent-encoded control characters.
        $decodedUrl = rawurldecode($url);

        if (preg_match('/[\x00-\x20\x7F]/', $decodedUrl) === 1) {
            return false;
        }

        // Protocol-relative URLs are not permitted.
        if (str_starts_with($url, '//')) {
            return false;
        }

        // Fragment references are safe.
        if (str_starts_with($url, '#')) {
            return true;
        }

        // Root-relative URLs are safe.
        if (str_starts_with($url, '/')) {
            return true;
        }

        // Query-only relative URLs are permitted.
        if (str_starts_with($url, '?')) {
            return true;
        }

        $parts = parse_url($url);

        if ($parts === false) {
            return false;
        }

        // Absolute URLs must use HTTP or HTTPS and have a host.
        if (isset($parts['scheme'])) {
            $scheme = strtolower($parts['scheme']);

            if (!in_array($scheme, ['http', 'https'], true)) {
                return false;
            }

            if (
                empty($parts['host'])
                || isset($parts['user'])
                || isset($parts['pass'])
            ) {
                return false;
            }

            return true;
        }

        // Reject ambiguous scheme-like relative references.
        $firstSegment = explode('/', $url, 2)[0];

        if (str_contains($firstSegment, ':')) {
            return false;
        }

        return true;
    }
}
