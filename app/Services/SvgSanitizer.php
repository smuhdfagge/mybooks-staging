<?php

namespace App\Services;

/**
 * Sanitize SVG content to prevent XSS attacks.
 *
 * Strips scripting elements, event handlers, foreign objects,
 * and data URIs from SVG strings. Use this whenever rendering
 * SVG that may contain untrusted content.
 */
class SvgSanitizer
{
    /**
     * Elements that are always dangerous and must be removed entirely
     * (including children).
     */
    private const BLOCKED_ELEMENTS = [
        'script',
        'foreignObject',
        'set',
        'animate',
        'animateMotion',
        'animateTransform',
        'use',           // can reference external resources
        'iframe',
        'embed',
        'object',
        'applet',
    ];

    /**
     * Attribute prefixes that are event handlers (onclick, onerror, etc.).
     */
    private const EVENT_HANDLER_PREFIX = 'on';

    /**
     * Attributes that can contain dangerous URIs.
     */
    private const URI_ATTRIBUTES = [
        'href',
        'xlink:href',
        'src',
        'action',
        'formaction',
    ];

    /**
     * Sanitize an SVG string and return safe SVG markup.
     */
    public static function sanitize(string $svg): string
    {
        if (empty($svg)) {
            return '';
        }

        // Suppress XML errors
        $previousErrors = libxml_use_internal_errors(true);

        $dom = new \DOMDocument;
        $loaded = $dom->loadXML($svg, LIBXML_NONET);

        if (! $loaded || ! $dom->documentElement) {
            libxml_use_internal_errors($previousErrors);

            return '';
        }

        static::sanitizeNode($dom->documentElement);

        $result = $dom->saveXML($dom->documentElement);
        libxml_use_internal_errors($previousErrors);

        return $result ?: '';
    }

    /**
     * Recursively sanitize a DOM node.
     */
    protected static function sanitizeNode(\DOMNode $node): void
    {
        $nodesToRemove = [];

        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $tagName = strtolower($child->localName);

                // Remove blocked elements entirely
                if (in_array($tagName, self::BLOCKED_ELEMENTS, true)) {
                    $nodesToRemove[] = $child;

                    continue;
                }

                // Sanitize attributes
                static::sanitizeAttributes($child);

                // Recurse into children
                static::sanitizeNode($child);
            } elseif ($child->nodeType === XML_PROCESSING_INSTRUCTION_NODE) {
                // Remove processing instructions (e.g. xml-stylesheet)
                $nodesToRemove[] = $child;
            }
        }

        foreach ($nodesToRemove as $remove) {
            $node->removeChild($remove);
        }
    }

    /**
     * Strip dangerous attributes from an element.
     */
    protected static function sanitizeAttributes(\DOMElement $element): void
    {
        $attributesToRemove = [];

        for ($i = 0; $i < $element->attributes->length; $i++) {
            $attr = $element->attributes->item($i);
            $name = strtolower($attr->nodeName);
            $value = $attr->nodeValue;

            // Remove event handlers (onclick, onerror, onload, etc.)
            if (str_starts_with($name, self::EVENT_HANDLER_PREFIX)) {
                $attributesToRemove[] = $attr->nodeName;

                continue;
            }

            // Check for javascript: / data: URIs in href-like attributes
            if (in_array($name, self::URI_ATTRIBUTES, true)) {
                $trimmedValue = strtolower(trim($value));
                if (static::isDangerousUri($trimmedValue)) {
                    $attributesToRemove[] = $attr->nodeName;

                    continue;
                }
            }

            // Check for javascript: in style attributes
            if ($name === 'style') {
                $lower = strtolower($value);
                if (preg_match('/expression|javascript|vbscript|url\s*\(/i', $lower)) {
                    $attributesToRemove[] = $attr->nodeName;
                }
            }
        }

        foreach ($attributesToRemove as $attrName) {
            $element->removeAttribute($attrName);
        }
    }

    /**
     * Check if a URI string is dangerous.
     */
    protected static function isDangerousUri(string $uri): bool
    {
        // Remove whitespace and null bytes
        $uri = preg_replace('/[\x00-\x1f\s]+/', '', $uri);

        // Block javascript:, vbscript:, data:text/html, etc.
        if (preg_match('/^(javascript|vbscript|data\s*:(?!image\/(png|jpeg|gif|webp)))/i', $uri)) {
            return true;
        }

        return false;
    }
}
