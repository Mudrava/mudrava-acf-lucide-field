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
 * Text Domain: mudrava-lucide-field
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
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
 * Lucide CDN base URL for icon SVGs.
 *
 * @var string
 */
define('MUDRAVA_LUCIDE_CDN_URL', 'https://unpkg.com/lucide-static@latest/icons/');

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
    require_once MUDRAVA_LUCIDE_FIELD_PATH . 'includes/class-mudrava-acf-field-lucide-icon.php';

    acf_register_field_type('Mudrava_ACF_Field_Lucide_Icon');
}
add_action('acf/include_field_types', 'mudrava_lucide_field_register');

/**
 * Initialize GitHub updater.
 *
 * @since 1.0.0
 *
 * @return void
 */
function mudrava_lucide_field_updater_init(): void
{
    if (!is_admin()) {
        return;
    }

    require_once MUDRAVA_LUCIDE_FIELD_PATH . 'includes/class-mudrava-github-updater.php';

    new Mudrava_GitHub_Updater(
        __FILE__,
        'Mudrava',
        'Lucide-ACF-Field-Free'
    );
}
add_action('plugins_loaded', 'mudrava_lucide_field_updater_init');

/**
 * Loads plugin text domain for translations.
 *
 * @since 1.0.0
 *
 * @return void
 */
function mudrava_lucide_field_load_textdomain(): void
{
    load_plugin_textdomain(
        'mudrava-lucide-field',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
}
add_action('plugins_loaded', 'mudrava_lucide_field_load_textdomain');

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
        __('Docs', 'mudrava-lucide-field')
    );

    // Force target="_blank" on "Visit plugin site" if present
    foreach ($links as $key => $link) {
        if (strpos($link, 'Visit plugin site') !== false) {
            $links[$key] = str_replace('<a href=', '<a target="_blank" rel="noopener noreferrer" href=', $link);
        }
    }

    return $links;
}
add_filter('plugin_row_meta', 'mudrava_lucide_field_plugin_links', 10, 2);

/**
 * Retrieves the SVG markup for a Lucide icon.
 *
 * This helper function can be used in templates to render a Lucide icon
 * based on its name. Returns an empty string if the icon cannot be retrieved.
 *
 * @since 1.0.0
 *
 * @param string $icon_name The name of the Lucide icon (e.g., 'rocket', 'settings').
 * @param array  $args      Optional. Arguments to customize the SVG output.
 *                          - 'class'  (string) Additional CSS classes.
 *                          - 'width'  (int)    Icon width in pixels. Default 24.
 *                          - 'height' (int)    Icon height in pixels. Default 24.
 *                          - 'stroke' (string) Stroke color. Default 'currentColor'.
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
    );

    $args = wp_parse_args($args, $defaults);

    $transient_key = 'mudrava_lucide_' . $icon_name;
    $svg_content = get_transient($transient_key);

    if (false === $svg_content) {
        $response = wp_remote_get(
            MUDRAVA_LUCIDE_CDN_URL . $icon_name . '.svg',
            array(
                'timeout' => 5,
            )
        );

        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
            return '';
        }

        $svg_content = wp_remote_retrieve_body($response);

        if (empty($svg_content)) {
            return '';
        }

        set_transient($transient_key, $svg_content, WEEK_IN_SECONDS);
    }

    $class_attr = !empty($args['class']) ? ' class="' . esc_attr($args['class']) . '"' : '';

    $svg_content = preg_replace(
        '/<svg([^>]*)>/',
        '<svg$1' . $class_attr . ' width="' . esc_attr((string) $args['width']) . '" height="' . esc_attr((string) $args['height']) . '" stroke="' . esc_attr($args['stroke']) . '">',
        $svg_content,
        1
    );

    return $svg_content;
}
