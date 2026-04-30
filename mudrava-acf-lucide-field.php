<?php
/**
 * Plugin Name: Mudrava Icon Field for ACF with Lucide
 * Plugin URI:  https://wordpress.org/plugins/mudrava-acf-lucide-field/
 * Description: A custom ACF field type for selecting Lucide icons and Simple Icons brand icons with a visual picker interface.
 * Version:     1.1.0
 * Author:      Mudrava
 * Author URI:  https://mudrava.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: mudrava-acf-lucide-field
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * @package Mudrava\LucideField
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin version constant.
 *
 * @var string
 */
define('MUDRAVA_LUCIDE_FIELD_VERSION', '1.1.0');

/**
 * Bundled Lucide icon asset version.
 *
 * @var string
 */
define('MUDRAVA_LUCIDE_FIELD_LUCIDE_VERSION', '1.14.0');

/**
 * Bundled Simple Icons asset version.
 *
 * @var string
 */
define('MUDRAVA_LUCIDE_FIELD_SIMPLE_ICONS_VERSION', '16.18.0');

/**
 * Plugin directory path.
 *
 * @var string
 */
define('MUDRAVA_LUCIDE_FIELD_PATH', plugin_dir_path(__FILE__));

/**
 * Plugin directory URL.
 *
 * @var string
 */
define('MUDRAVA_LUCIDE_FIELD_URL', plugin_dir_url(__FILE__));

/**
 * Check if a supported ACF version is active.
 *
 * @since 1.0.0
 *
 * @return bool True if ACF 6.0+ is active, false otherwise.
 */
function mudrava_lucide_field_check_acf(): bool
{
    return class_exists('ACF') && defined('ACF_VERSION') && version_compare((string) ACF_VERSION, '6.0', '>=');
}

/**
 * Display admin notice if a supported ACF version is not active.
 *
 * @since 1.0.0
 *
 * @return void
 */
function mudrava_lucide_field_acf_notice(): void
{
    if (!mudrava_lucide_field_check_acf()) {
        $message = sprintf(
            /* translators: 1: Advanced Custom Fields link, 2: required ACF version */
            __('This plugin requires %1$s version %2$s or higher to function.', 'mudrava-acf-lucide-field'),
            '<a href="https://www.advancedcustomfields.com/" target="_blank" rel="noopener noreferrer">Advanced Custom Fields</a>',
            '6.0'
        );

        echo '<div class="notice notice-error"><p><strong>' . esc_html__('Mudrava Icon Field:', 'mudrava-acf-lucide-field') . '</strong> ';
        echo wp_kses($message, array('a' => array('href' => true, 'target' => true, 'rel' => true))) . '</p></div>';
    }
}
add_action('admin_notices', 'mudrava_lucide_field_acf_notice');

/**
 * Registers the Lucide Icon field type with ACF.
 *
 * This function includes the field class file and registers it with ACF.
 * Hooked to 'acf/include_field_types' to ensure ACF is loaded first.
 *
 * @since 1.0.0
 *
 * @return void
 */
function mudrava_lucide_field_register(): void
{
    if (!mudrava_lucide_field_check_acf()) {
        return;
    }

    require_once MUDRAVA_LUCIDE_FIELD_PATH . 'includes/class-mudrava-acf-field-lucide-icon.php';

    acf_register_field_type('Mudrava_ACF_Field_Lucide_Icon');
}
add_action('acf/include_field_types', 'mudrava_lucide_field_register');

/**
 * Modifies plugin row links.
 *
 * Adds "Docs" link and ensures external links open in new tab.
 *
 * @since 1.0.0
 *
 * @param array  $links       Current plugin links.
 * @param string $plugin_file Plugin file name.
 * @return array Modified links.
 */
function mudrava_lucide_field_plugin_links(array $links, string $plugin_file): array
{
    if (plugin_basename(__FILE__) !== $plugin_file) {
        return $links;
    }

    // Add Docs link
    $links[] = sprintf(
        '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
        'https://github.com/Mudrava/mudrava-acf-lucide-field/blob/main/README.md',
        __('Docs', 'mudrava-acf-lucide-field')
    );

    return $links;
}
add_filter('plugin_row_meta', 'mudrava_lucide_field_plugin_links', 10, 2);

/**
 * Get safe SVG child markup for icons from the bundled sprite file.
 *
 * @since 1.0.1
 *
 * @return array<string, array<string, bool>>
 */
function mudrava_lucide_field_allowed_svg_children(): array
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
 * Parse a local sprite once per request and return symbol bodies by icon ID.
 *
 * @since 1.0.1
 *
 * @param string $sprite_path Absolute path to the sprite file.
 * @return array<string, string>
 */
function mudrava_lucide_field_parse_sprite_symbols(string $sprite_path): array
{
    $symbols = array();

    if (!is_readable($sprite_path)) {
        return $symbols;
    }

    $sprite_content = file_get_contents($sprite_path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

    if (false === $sprite_content) {
        return $symbols;
    }

    preg_match_all('/<symbol\b[^>]*\bid="([^"]+)"[^>]*>(.*?)<\/symbol>/is', $sprite_content, $matches, PREG_SET_ORDER);

    foreach ($matches as $match) {
        $icon_name = sanitize_file_name(html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'));
        $markup = trim($match[2]);

        if ('' === $icon_name || '' === $markup) {
            continue;
        }

        $symbols[$icon_name] = wp_kses($markup, mudrava_lucide_field_allowed_svg_children());
    }

    return $symbols;
}

/**
 * Get Lucide symbol bodies from the bundled local sprite.
 *
 * @since 1.0.1
 *
 * @return array<string, string>
 */
function mudrava_lucide_field_get_sprite_symbols(): array
{
    static $symbols = null;

    if (null !== $symbols) {
        return $symbols;
    }

    $symbols = mudrava_lucide_field_parse_sprite_symbols(MUDRAVA_LUCIDE_FIELD_PATH . 'assets/sprite.svg');

    return $symbols;
}

/**
 * Get Simple Icons brand symbol bodies from the bundled local sprite.
 *
 * @since 1.1.0
 *
 * @return array<string, string>
 */
function mudrava_lucide_field_get_brand_sprite_symbols(): array
{
    static $symbols = null;

    if (null !== $symbols) {
        return $symbols;
    }

    $symbols = array();
    $raw_symbols = mudrava_lucide_field_parse_sprite_symbols(MUDRAVA_LUCIDE_FIELD_PATH . 'assets/brand-sprite.svg');

    foreach ($raw_symbols as $symbol_id => $markup) {
        if (0 !== strpos($symbol_id, 'simple-')) {
            continue;
        }

        $slug = substr($symbol_id, 7);

        if ('' === $slug) {
            continue;
        }

        $symbols[$slug] = $markup;
    }

    return $symbols;
}

/**
 * Get bundled Simple Icons search tags.
 *
 * @since 1.1.0
 *
 * @return array<string, array<string>>
 */
function mudrava_lucide_field_get_brand_icon_tags(): array
{
    static $tags = null;

    if (null !== $tags) {
        return $tags;
    }

    $tags = array();
    $json_path = MUDRAVA_LUCIDE_FIELD_PATH . 'data/brand-icons.json';

    if (!is_readable($json_path)) {
        return $tags;
    }

    $json_content = file_get_contents($json_path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

    if (false === $json_content) {
        return $tags;
    }

    $decoded = json_decode($json_content, true);

    if (is_array($decoded)) {
        $tags = $decoded;
    }

    return $tags;
}

/**
 * Normalize icon values for matching user input and legacy saved values.
 *
 * @since 1.1.0
 *
 * @param string $value Raw value.
 * @return string Normalized value.
 */
function mudrava_lucide_field_normalize_icon_token(string $value): string
{
    $value = strtolower(trim($value));
    $value = (string) preg_replace('/[^a-z0-9]+/', '-', $value);

    return trim($value, '-');
}

/**
 * Get a map of Simple Icons aliases to canonical slugs.
 *
 * @since 1.1.0
 *
 * @return array<string, string>
 */
function mudrava_lucide_field_get_brand_alias_map(): array
{
    static $aliases = null;

    if (null !== $aliases) {
        return $aliases;
    }

    $aliases = array();
    $brand_symbols = mudrava_lucide_field_get_brand_sprite_symbols();

    foreach (mudrava_lucide_field_get_brand_icon_tags() as $slug => $tags) {
        if (!isset($brand_symbols[$slug])) {
            continue;
        }

        $aliases[$slug] = $slug;

        foreach ((array) $tags as $tag) {
            $token = mudrava_lucide_field_normalize_icon_token((string) $tag);

            if ('' !== $token && !isset($aliases[$token])) {
                $aliases[$token] = $slug;
            }
        }
    }

    return $aliases;
}

/**
 * Parse an icon value into source and icon name.
 *
 * @since 1.1.0
 *
 * @param string $icon_name Icon value.
 * @return array{source:string,name:string}
 */
function mudrava_lucide_field_parse_icon_value(string $icon_name): array
{
    $icon_name = trim($icon_name);

    if ('' === $icon_name) {
        return array('source' => '', 'name' => '');
    }

    $source = 'auto';
    $name = $icon_name;

    if (preg_match('/^([a-z]+):(.+)$/i', $icon_name, $matches)) {
        $source = strtolower($matches[1]);
        $name = $matches[2];
    }

    if ('brand' === $source) {
        $source = 'simple';
    }

    if (!in_array($source, array('auto', 'lucide', 'simple'), true)) {
        $source = 'auto';
        $name = $icon_name;
    }

    $name = mudrava_lucide_field_normalize_icon_token(sanitize_file_name($name));

    if ('' === $name) {
        return array('source' => '', 'name' => '');
    }

    return array('source' => $source, 'name' => $name);
}

/**
 * Sanitize an icon value while preserving the Simple Icons source prefix.
 *
 * @since 1.1.0
 *
 * @param string $icon_name Icon value.
 * @return string Sanitized value.
 */
function mudrava_lucide_field_sanitize_icon_value(string $icon_name): string
{
    $parsed = mudrava_lucide_field_parse_icon_value($icon_name);

    if ('' === $parsed['name']) {
        return '';
    }

    if ('simple' === $parsed['source']) {
        return 'simple:' . $parsed['name'];
    }

    return $parsed['name'];
}

/**
 * Resolve a Simple Icons slug from a direct slug or exact alias match.
 *
 * @since 1.1.0
 *
 * @param string $icon_name Brand icon slug or alias.
 * @return string Brand slug, or an empty string when not found.
 */
function mudrava_lucide_field_resolve_brand_slug(string $icon_name): string
{
    $icon_name = mudrava_lucide_field_normalize_icon_token(sanitize_file_name($icon_name));
    $brand_symbols = mudrava_lucide_field_get_brand_sprite_symbols();

    if (isset($brand_symbols[$icon_name])) {
        return $icon_name;
    }

    $needle = mudrava_lucide_field_normalize_icon_token($icon_name);

    if ('' === $needle) {
        return '';
    }

    $aliases = mudrava_lucide_field_get_brand_alias_map();

    return $aliases[$needle] ?? '';
}

/**
 * Resolve an icon value to a concrete bundled source and name.
 *
 * @since 1.1.0
 *
 * @param string $icon_name Icon value.
 * @return array{source:string,name:string}
 */
function mudrava_lucide_field_resolve_icon(string $icon_name): array
{
    $parsed = mudrava_lucide_field_parse_icon_value($icon_name);

    if ('' === $parsed['name']) {
        return array('source' => '', 'name' => '');
    }

    $lucide_symbols = mudrava_lucide_field_get_sprite_symbols();

    if ('simple' === $parsed['source']) {
        $brand_slug = mudrava_lucide_field_resolve_brand_slug($parsed['name']);

        return '' !== $brand_slug
            ? array('source' => 'simple', 'name' => $brand_slug)
            : array('source' => '', 'name' => '');
    }

    if ('lucide' === $parsed['source']) {
        return isset($lucide_symbols[$parsed['name']])
            ? array('source' => 'lucide', 'name' => $parsed['name'])
            : array('source' => '', 'name' => '');
    }

    if (isset($lucide_symbols[$parsed['name']])) {
        return array('source' => 'lucide', 'name' => $parsed['name']);
    }

    $brand_slug = mudrava_lucide_field_resolve_brand_slug($parsed['name']);

    return '' !== $brand_slug
        ? array('source' => 'simple', 'name' => $brand_slug)
        : array('source' => '', 'name' => '');
}

/**
 * Determine whether an icon exists in the bundled local icon sets.
 *
 * @since 1.0.1
 *
 * @param string $icon_name Icon name.
 * @return bool
 */
function mudrava_lucide_field_icon_exists(string $icon_name): bool
{
    $resolved = mudrava_lucide_field_resolve_icon($icon_name);

    return '' !== $resolved['source'] && '' !== $resolved['name'];
}

/**
 * Retrieves the SVG markup for a Lucide icon.
 *
 * This helper function can be used in templates to render a Lucide icon
 * based on its name. Returns an empty string if the icon cannot be retrieved.
 * Lucide icons use unprefixed values (e.g., 'rocket'); Simple Icons brand
 * icons use the 'simple:' prefix (e.g., 'simple:facebook').
 *
 * @since 1.0.0
 *
 * @param string $icon_name The icon value (e.g., 'rocket', 'simple:facebook').
 * @param array  $args      Optional. Arguments to customize the SVG output.
 *                          - 'class'  (string) Additional CSS classes.
 *                          - 'width'  (int)    Icon width in pixels. Default 24.
 *                          - 'height' (int)    Icon height in pixels. Default 24.
 *                          - 'stroke' (string) Stroke color. Default 'currentColor'.
 *                          - 'stroke_width' (int) Stroke width. Default 2.
 *                          - 'fill' (string) Fill color for brand icons. Default stroke color.
 * @return string The SVG markup or empty string on failure.
 */
function mudrava_get_lucide_icon(string $icon_name, array $args = array()): string
{
    if (empty($icon_name)) {
        return '';
    }

    $defaults = array(
        'class' => '',
        'width' => 24,
        'height' => 24,
        'stroke' => 'currentColor',
        'stroke_width' => 2,
        'fill' => '',
    );

    $args = wp_parse_args($args, $defaults);
    $resolved = mudrava_lucide_field_resolve_icon($icon_name);

    if ('' === $resolved['source'] || '' === $resolved['name']) {
        return '';
    }

    // Inline the symbol contents instead of referencing an external sprite.
    // This avoids frontend breakage from CDN domains, mixed content, CSP, MIME,
    // or browser quirks around external SVG <use> references.
    $class_attr = !empty($args['class']) ? ' class="' . esc_attr($args['class']) . '"' : '';

    if ('simple' === $resolved['source']) {
        $symbols = mudrava_lucide_field_get_brand_sprite_symbols();

        if (!isset($symbols[$resolved['name']])) {
            return '';
        }

        $fill = '' !== (string) $args['fill'] ? (string) $args['fill'] : (string) $args['stroke'];

        return sprintf(
            '<svg%s xmlns="http://www.w3.org/2000/svg" width="%s" height="%s" viewBox="0 0 24 24" fill="%s" stroke="none" aria-hidden="true" focusable="false">%s</svg>',
            $class_attr,
            esc_attr((string) $args['width']),
            esc_attr((string) $args['height']),
            esc_attr($fill),
            $symbols[$resolved['name']]
        );
    }

    $symbols = mudrava_lucide_field_get_sprite_symbols();

    if (!isset($symbols[$resolved['name']])) {
        return '';
    }

    return sprintf(
        '<svg%s xmlns="http://www.w3.org/2000/svg" width="%s" height="%s" viewBox="0 0 24 24" fill="none" stroke="%s" stroke-width="%s" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%s</svg>',
        $class_attr,
        esc_attr((string) $args['width']),
        esc_attr((string) $args['height']),
        esc_attr($args['stroke']),
        esc_attr((string) $args['stroke_width']),
        $symbols[$resolved['name']]
    );
}
