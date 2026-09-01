<?php
/**
 * Minimal whitelist sanitizer used as a wp_kses() test double.
 *
 * Mirrors the contract exercised by the plugin: disallowed elements are
 * unwrapped (text kept), disallowed attributes are dropped.
 */

if (!class_exists('PHP_kses_Test_Kses')) {
    /**
     * Test double for WordPress kses.
     */
    class PHP_kses_Test_Kses
    {
        /**
         * Sanitize an XML/HTML fragment against an element/attribute allowlist.
         *
         * @param string $string       Markup to sanitize.
         * @param array  $allowed_html Element => attribute map.
         * @return string Sanitized markup.
         */
        public static function sanitize($string, $allowed_html)
        {
            $document = new DOMDocument();

            $previous = libxml_use_internal_errors(true);

            $loaded = $document->loadXML(
                '<root>' . $string . '</root>',
                LIBXML_NOENT | LIBXML_NONET
            );

            libxml_clear_errors();
            libxml_use_internal_errors($previous);

            if (!$loaded) {
                return '';
            }

            self::walk($document->documentElement, $allowed_html, $document);

            $inner = '';

            foreach ($document->documentElement->childNodes as $child) {
                $inner .= $document->saveXML($child);
            }

            return $inner;
        }

        /**
         * Recursively filter nodes against the allowlist.
         *
         * @param DOMElement $node        Current node.
         * @param array      $allowed_html Allowlist.
         * @param DOMDocument $document   Owner document.
         * @return void
         */
        private static function walk(DOMElement $node, array $allowed_html, DOMDocument $document)
        {
            $children = array();

            foreach ($node->childNodes as $child) {
                $children[] = $child;
            }

            foreach ($children as $child) {
                if ($child instanceof DOMComment || $child instanceof DOMProcessingInstruction) {
                    $node->removeChild($child);

                    continue;
                }

                if (!$child instanceof DOMElement) {
                    continue;
                }

                $tag = strtolower($child->nodeName);

                if (!isset($allowed_html[$tag])) {
                    while ($child->firstChild) {
                        $node->insertBefore($child->firstChild, $child);
                    }

                    $node->removeChild($child);

                    continue;
                }

                $allowed_attrs = $allowed_html[$tag];

                foreach (iterator_to_array($child->attributes) as $attribute) {
                    $name = strtolower($attribute->nodeName);

                    $allowed = isset($allowed_attrs[$name]) || (isset($allowed_attrs['*']) && $allowed_attrs['*']);

                    if (!$allowed) {
                        $child->removeAttribute($attribute->nodeName);
                    }
                }

                self::walk($child, $allowed_html, $document);
            }
        }
    }
}
