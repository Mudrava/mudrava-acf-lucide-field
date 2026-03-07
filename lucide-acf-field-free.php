<?php
/**
 * Plugin Name: Lucide ACF Field Free
 * Plugin URI:  https://github.com/Mudrava/Lucide-ACF-Field-Free
 * Description: A custom ACF field type for selecting Lucide icons with a visual picker interface.
 * Version:     1.0.0
 * Author:      Mudrava
 * Author URI:  https://mudrava.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: lucide-acf-field-free
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
define('MUDRAVA_LUCIDE_FIELD_VERSION', '1.0.0');

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
 * Check if ACF Pro is active.
 *
 * @since 1.0.0
 *
 * @return bool True if ACF Pro is active, false otherwise.
 */
function mudrava_lucide_field_check_acf(): bool
{
    return class_exists('ACF');
}

/**
 * Display admin notice if ACF Pro is not active.
 *
 * @since 1.0.0
 *
 * @return void
 */
function mudrava_lucide_field_acf_notice(): void
{
    if (!mudrava_lucide_field_check_acf()) {
        echo '<div class="error"><p><strong>Lucide ACF Field:</strong> This plugin requires <a href="https://www.advancedcustomfields.com/" target="_blank" rel="noopener noreferrer">Advanced Custom Fields Pro</a> version 6.0 or higher to function.</p></div>';
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
 * Adds "Does" link and ensures external links open in new tab.
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
        'https://github.com/Mudrava/Lucide-ACF-Field-Free/blob/main/README.md',
        __('Docs', 'lucide-acf-field-free')
    );

    return $links;
}
add_filter('plugin_row_meta', 'mudrava_lucide_field_plugin_links', 10, 2);

/**
 * Retrieves the SVG markup for a Lucide icon.
 *
 * This helper function can be used in templates to render a Lucide icon
 * based on its name. Returns an empty string if the icon cannot be retrieved.
 * All icons are loaded from the local sprite.svg file bundled with the plugin.
 *
 * @since 1.0.0
 *
 * @param string $icon_name The name of the Lucide icon (e.g., 'rocket', 'settings').
 * @param array  $args      Optional. Arguments to customize the SVG output.
 *                          - 'class'  (string) Additional CSS classes.
 *                          - 'width'  (int)    Icon width in pixels. Default 24.
 *                          - 'height' (int)    Icon height in pixels. Default 24.
 *                          - 'stroke' (string) Stroke color. Default 'currentColor'.
 *                          - 'stroke_width' (int) Stroke width. Default 2.
 * @return string The SVG markup or empty string on failure.
 */
function mudrava_get_lucide_icon(string $icon_name, array $args = array()): string
{
    if (empty($icon_name)) {
        return '';
    }

    $icon_name = sanitize_file_name($icon_name);

    $defaults = array(
        'class' => '',
        'width' => 24,
        'height' => 24,
        'stroke' => 'currentColor',
        'stroke_width' => 2,
    );

    $args = wp_parse_args($args, $defaults);

    // Check if icon exists in the bundled icons list
    $transient_key = 'mudrava_lucide_sprite_symbols';
    $available_icons = get_transient($transient_key);

    if (false === $available_icons) {
        $sprite_path = MUDRAVA_LUCIDE_FIELD_PATH . 'assets/sprite.svg';

        if (!file_exists($sprite_path)) {
            return '';
        }

        // Parse sprite.svg to get available icon IDs
        $sprite_content = file_get_contents($sprite_path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

        if (false === $sprite_content) {
            return '';
        }

        // Extract all symbol IDs
        preg_match_all('/<symbol[^>]+id="([^"]+)"/', $sprite_content, $matches);
        $available_icons = !empty($matches[1]) ? $matches[1] : array();

        set_transient($transient_key, $available_icons, WEEK_IN_SECONDS);
    }

    // Verify icon exists in sprite
    if (!in_array($icon_name, $available_icons, true)) {
        return '';
    }

    // Build SVG with use reference to sprite
    $class_attr = !empty($args['class']) ? ' class="' . esc_attr($args['class']) . '"' : '';

    $svg = sprintf(
        '<svg%s width="%s" height="%s" viewBox="0 0 24 24" fill="none" stroke="%s" stroke-width="%s" stroke-linecap="round" stroke-linejoin="round"><use href="%s#%s"></use></svg>',
        $class_attr,
        esc_attr((string) $args['width']),
        esc_attr((string) $args['height']),
        esc_attr($args['stroke']),
        esc_attr((string) $args['stroke_width']),
        esc_url(MUDRAVA_LUCIDE_FIELD_URL . 'assets/sprite.svg'),
        esc_attr($icon_name)
    );

    return $svg;
}
