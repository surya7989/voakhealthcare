<?php

declare(strict_types=1);

namespace Voak\Health\Security;

/**
 * Enterprise Stored-XSS Protection HTML Sanitizer.
 * Enforces an allowlist of HTML formatting elements while strictly stripping
 * active script tags, executable event handlers, and unsafe URI schemes.
 */
class HtmlSanitizer
{
    private const ALLOWED_TAGS = [
        'p', 'b', 'i', 'strong', 'em', 'u', 's', 'strike',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'ul', 'ol', 'li', 'blockquote', 'pre', 'code',
        'a', 'img', 'table', 'thead', 'tbody', 'tr', 'th', 'td',
        'div', 'span', 'br', 'hr', 'sub', 'sup', 'figure', 'figcaption',
    ];

    private const ALLOWED_ATTRIBUTES = [
        'class', 'id', 'title', 'alt', 'width', 'height', 'align', 'colspan', 'rowspan',
    ];

    private const ALLOWED_URI_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    /**
     * Sanitizes raw HTML input to prevent Stored XSS.
     */
    public function sanitize(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        // Fast reject if no HTML tags
        if (!str_contains($html, '<')) {
            return htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        // Suppress warnings from malformed HTML fragments
        $prevErrors = libxml_use_internal_errors(true);

        // Wrap fragment in a root wrapper with UTF-8 encoding declaration
        $wrapped = '<?xml encoding="UTF-8"><div>' . $html . '</div>';
        $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        libxml_clear_errors();
        libxml_use_internal_errors($prevErrors);

        // Get the wrapper div
        $wrapper = null;
        foreach ($dom->getElementsByTagName('div') as $node) {
            $wrapper = $node;
            break;
        }

        if ($wrapper === null) {
            return '';
        }

        $this->sanitizeNode($wrapper);

        // Extract inner HTML of wrapper
        $result = '';
        foreach ($wrapper->childNodes as $child) {
            $result .= $dom->saveHTML($child);
        }

        return trim($result);
    }

    private function sanitizeNode(\DOMNode $node): void
    {
        // Collect child nodes to array since removing nodes modifies live NodeList
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if ($child instanceof \DOMElement) {
                $tagName = strtolower($child->tagName);

                // Reject tags not in allowlist (e.g. script, iframe, object, svg, form)
                if (!in_array($tagName, self::ALLOWED_TAGS, true)) {
                    $node->removeChild($child);
                    continue;
                }

                // Clean attributes
                $attributesToRemove = [];
                if ($child->hasAttributes()) {
                    foreach ($child->attributes as $attr) {
                        $attrName = strtolower($attr->name);
                        $attrValue = trim($attr->value);

                        // Strip ALL on* event handlers (onerror, onload, onclick, etc.)
                        if (str_starts_with($attrName, 'on')) {
                            $attributesToRemove[] = $attr->name;
                            continue;
                        }

                        // Check URL attributes
                        if ($attrName === 'href' || $attrName === 'src') {
                            if (!$this->isSafeUrl($attrValue)) {
                                $attributesToRemove[] = $attr->name;
                                continue;
                            }
                        } elseif ($attrName === 'target') {
                            if ($attrValue === '_blank') {
                                $child->setAttribute('rel', 'noopener noreferrer');
                            }
                        } elseif (!in_array($attrName, self::ALLOWED_ATTRIBUTES, true)) {
                            // Attribute not in allowlist
                            $attributesToRemove[] = $attr->name;
                        }
                    }

                    foreach ($attributesToRemove as $attrName) {
                        $child->removeAttribute($attrName);
                    }
                }

                // Recursively clean children
                $this->sanitizeNode($child);
            } elseif ($child instanceof \DOMComment) {
                // Strip comments
                $node->removeChild($child);
            }
        }
    }

    public function isSafeUrl(string $url): bool
    {
        $trimmed = trim($url);
        if ($trimmed === '' || $trimmed === '#') {
            return true;
        }

        // Allow relative URLs starting with / or #
        if (str_starts_with($trimmed, '/') || str_starts_with($trimmed, '#')) {
            return true;
        }

        // Prevent control characters and null bytes
        if (preg_match('/[\x00-\x1F\x7F]/', $trimmed)) {
            return false;
        }

        // Extract scheme
        $colonPos = strpos($trimmed, ':');
        if ($colonPos === false) {
            // Relative URL without leading slash (e.g. "path/to/page")
            return !str_contains($trimmed, '\\');
        }

        $scheme = strtolower(substr($trimmed, 0, $colonPos));
        // Remove whitespace inside scheme
        $scheme = (string)preg_replace('/\s+/', '', $scheme);

        return in_array($scheme, self::ALLOWED_URI_SCHEMES, true);
    }

    /**
     * Recursively sanitizes any HTML string values in a JSON/array payload.
     */
    public function sanitizePayload(mixed $data): mixed
    {
        if (is_string($data)) {
            // Check if string contains HTML tags
            if (str_contains($data, '<') && str_contains($data, '>')) {
                return $this->sanitize($data);
            }
            return $data;
        }

        if (is_array($data)) {
            $cleaned = [];
            foreach ($data as $key => $value) {
                $cleaned[$key] = $this->sanitizePayload($value);
            }
            return $cleaned;
        }

        return $data;
    }

    /**
     * Clean HTML or structured data (alias to sanitize / sanitizePayload).
     */
    public function clean(mixed $input): mixed
    {
        if (is_string($input)) {
            return $this->sanitize($input);
        }
        return $this->sanitizePayload($input);
    }
}
