<?php
/**
 * Compile bundled icon sprites into fast runtime data files.
 *
 * Reads the bundled sprite SVGs, sanitizes each symbol's shape markup
 * against the same whitelist the plugin applies at runtime, and emits
 * two data files per icon source:
 *
 *   data/<source>-index.php   icon name => [byte offset, byte length] into the sprite
 *   data/<source>-tags.php    icon name => search tags
 *
 * The byte index lets the frontend renderer fetch a single icon with
 * fseek/fread instead of regex-parsing the full sprite on every request.
 *
 * Usage:
 *   php scripts/build-assets.php          # (re)generate data files
 *   php scripts/build-assets.php --check  # verify data files are in sync (CI)
 *
 * Build aborts on malformed sprite markup or on a mismatch between the
 * sprite symbol set and the JSON tag set (upstream asset governance).
 *
 * @package Mudrava\LucideField
 */

declare(strict_types=1);

if ('cli' !== PHP_SAPI) {
    exit("This script must be run from the command line.\n");
}

define('SCRIPT_ROOT', dirname(__DIR__));

/**
 * Allowed SVG elements and attributes inside icon symbols.
 *
 * Keep in sync with mudrava_lucide_field_allowed_svg_children().
 *
 * @return array<string, array<string, bool>>
 */
function mudrava_build_allowed(): array
{
    $shape_attrs = array(
        'd' => true,
        'cx' => true,
        'cy' => true,
        'r' => true,
        'x' => true,
        'y' => true,
        'x1' => true,
        'x2' => true,
        'y1' => true,
        'y2' => true,
        'rx' => true,
        'ry' => true,
        'width' => true,
        'height' => true,
        'points' => true,
        'fill' => true,
        'stroke' => true,
        'stroke-width' => true,
        'stroke-linecap' => true,
        'stroke-linejoin' => true,
        'stroke-miterlimit' => true,
        'fill-rule' => true,
        'clip-rule' => true,
    );

    return array(
        'path' => $shape_attrs,
        'circle' => $shape_attrs,
        'ellipse' => $shape_attrs,
        'line' => $shape_attrs,
        'polygon' => $shape_attrs,
        'polyline' => $shape_attrs,
        'rect' => $shape_attrs,
    );
}

/**
 * Sanitize a symbol markup fragment; abort the build on unexpected markup.
 *
 * @param string $markup  Symbol inner markup.
 * @param string $icon_id Symbol id (for error messages).
 * @return string Sanitized markup.
 */
function mudrava_build_sanitize(string $markup, string $icon_id): string
{
    $allowed = mudrava_build_allowed();

    libxml_use_internal_errors(true);

    $doc = new DOMDocument();
    $ok = $doc->loadXML('<svg xmlns="http://www.w3.org/2000/svg">' . $markup . '</svg>', LIBXML_NOENT | LIBXML_NONET);
    $errors = libxml_get_errors();
    libxml_clear_errors();

    if (!$ok || !empty($errors) || null === $doc->documentElement || 0 === $doc->documentElement->childNodes->length) {
        fwrite(STDERR, sprintf("ABORT: malformed markup in symbol '%s': %s\n", $icon_id, trim($errors[0]->message ?? 'unparseable XML')));
        exit(1);
    }

    $out = '';

    foreach ($doc->documentElement->childNodes as $node) {
        if (!$node instanceof DOMElement) {
            if ($node instanceof DOMText && '' === trim($node->nodeValue ?? '')) {
                continue;
            }

            fwrite(STDERR, sprintf("ABORT: unexpected node type in symbol '%s'\n", $icon_id));
            exit(1);
        }

        $tag = $node->tagName;

        if (!isset($allowed[$tag])) {
            fwrite(STDERR, sprintf("ABORT: element <%s> not allowed in symbol '%s'\n", $tag, $icon_id));
            exit(1);
        }

        foreach ($node->attributes as $attr) {
            if (!isset($allowed[$tag][$attr->name])) {
                fwrite(STDERR, sprintf("ABORT: attribute '%s' not allowed on <%s> in symbol '%s'\n", $attr->name, $tag, $icon_id));
                exit(1);
            }
        }

        $out .= $doc->saveXML($node);
    }

    return trim($out);
}

/**
 * Parse a sprite into sanitized markup and a byte index.
 *
 * @param string $path   Sprite file path.
 * @param string $prefix Symbol id prefix to strip ('' or 'simple-').
 * @return array{markup: array<string,string>, index: array<string,array{int,int}>}
 */
function mudrava_build_parse_sprite(string $path, string $prefix): array
{
    $content = file_get_contents($path);

    if (false === $content) {
        fwrite(STDERR, "ABORT: cannot read sprite {$path}\n");
        exit(1);
    }

    $markup = array();
    $index = array();

    if (!preg_match_all('/<symbol\b[^>]*\bid="([^"]+)"[^>]*>(.*?)<\/symbol>/is', $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
        fwrite(STDERR, "ABORT: no symbols found in {$path}\n");
        exit(1);
    }

    foreach ($matches as $match) {
        $icon_id = html_entity_decode($match[1][0], ENT_QUOTES, 'UTF-8');

        if ('' !== $prefix && 0 !== strpos($icon_id, $prefix)) {
            fwrite(STDERR, sprintf("ABORT: symbol '%s' in %s lacks the expected '%s' prefix\n", $icon_id, basename($path), $prefix));
            exit(1);
        }

        $slug = '' !== $prefix ? substr($icon_id, strlen($prefix)) : $icon_id;

        if ('' === $slug || isset($markup[$slug])) {
            fwrite(STDERR, sprintf("ABORT: duplicate or empty symbol id '%s' in %s\n", $icon_id, basename($path)));
            exit(1);
        }

        $inner = $match[2][0];

        if ('' === trim($inner)) {
            fwrite(STDERR, sprintf("ABORT: symbol '%s' has empty markup\n", $icon_id));
            exit(1);
        }

        $markup[$slug] = mudrava_build_sanitize($inner, $icon_id);
        $index[$slug] = array($match[2][1], strlen($inner));
    }

    ksort($markup);
    ksort($index);

    return array('markup' => $markup, 'index' => $index);
}

/**
 * Render a PHP data file for a sorted array.
 *
 * @param array<string,mixed> $data        Data to render.
 * @param string              $description File description.
 * @return string PHP file contents.
 */
function mudrava_build_render_php(array $data, string $description): string
{
    $lines = array();
    $lines[] = '<?php';
    $lines[] = '/**';
    $lines[] = ' * ' . $description . '.';
    $lines[] = ' *';
    $lines[] = ' * Generated by scripts/build-assets.php - do not edit by hand.';
    $lines[] = ' */';
    $lines[] = '';
    $lines[] = 'declare(strict_types=1);';
    $lines[] = '';
    $lines[] = 'return array(';

    foreach ($data as $key => $value) {
        $key_export = var_export((string) $key, true);

        if (is_array($value)) {
            $items = array();

            foreach ($value as $item) {
                $items[] = var_export($item, true);
            }

            $lines[] = '    ' . $key_export . ' => array(' . implode(', ', $items) . '),';
        } else {
            $lines[] = '    ' . $key_export . ' => ' . var_export((string) $value, true) . ',';
        }
    }

    $lines[] = ');';
    $lines[] = '';

    return implode("\n", $lines);
}

$check = in_array('--check', $argv, true);
$drift = false;

$sets = array(
    array(
        'sprite' => SCRIPT_ROOT . '/assets/sprite.svg',
        'json' => SCRIPT_ROOT . '/data/icons.json',
        'prefix' => '',
        'slug' => 'lucide',
        'label' => 'Lucide',
    ),
    array(
        'sprite' => SCRIPT_ROOT . '/assets/brand-sprite.svg',
        'json' => SCRIPT_ROOT . '/data/brand-icons.json',
        'prefix' => 'simple-',
        'slug' => 'simple',
        'label' => 'Simple Icons',
    ),
);

foreach ($sets as $set) {
    $parsed = mudrava_build_parse_sprite($set['sprite'], $set['prefix']);

    $json_raw = file_get_contents($set['json']);

    if (false === $json_raw) {
        fwrite(STDERR, "ABORT: cannot read {$set['json']}\n");
        exit(1);
    }

    $tags = json_decode($json_raw, true);

    if (!is_array($tags)) {
        fwrite(STDERR, "ABORT: invalid JSON in {$set['json']}\n");
        exit(1);
    }

    $sprite_keys = array_keys($parsed['markup']);
    $tag_keys = array_keys($tags);
    sort($sprite_keys);
    sort($tag_keys);

    if ($sprite_keys !== $tag_keys) {
        fwrite(STDERR, "ABORT: {$set['json']} and {$set['sprite']} are out of sync for {$set['label']}\n");
        exit(1);
    }

    $outputs = array(
        SCRIPT_ROOT . '/data/' . $set['slug'] . '-index.php' => mudrava_build_render_php($parsed['index'], $set['label'] . ' symbol byte index into the sprite file'),
        SCRIPT_ROOT . '/data/' . $set['slug'] . '-tags.php' => mudrava_build_render_php($tags, $set['label'] . ' icon search tags'),
    );

    foreach ($outputs as $file => $contents) {
        $existing = is_readable($file) ? (string) file_get_contents($file) : '';

        if ($existing === $contents) {
            continue;
        }

        if ($check) {
            fwrite(STDERR, sprintf("CHECK FAILED: %s is out of sync (run php scripts/build-assets.php and commit).\n", $file));
            $drift = true;
            continue;
        }

        file_put_contents($file, $contents);
    }

    printf("%s: %d symbols compiled\n", $set['label'], count($parsed['markup']));
}

if ($check) {
    if ($drift) {
        exit(1);
    }

    echo "All data files are in sync.\n";
} else {
    echo "Build complete.\n";
}
