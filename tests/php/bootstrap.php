<?php
/**
 * PHPUnit bootstrap with minimal WordPress/ACF stubs.
 *
 * Loads the plugin outside of WordPress so pure data, sanitization and
 * resolution logic can be unit tested without a full WP install.
 */

define('ABSPATH', sys_get_temp_dir() . '/mudrava-acf-lucide-test/');
define('FS_CHMOD_FILE', 0644);

if (!is_dir(ABSPATH)) {
    mkdir(ABSPATH, 0777, true);
}

$GLOBALS['mudrava_test_transients'] = array();
$GLOBALS['mudrava_test_shortcodes'] = array();
$GLOBALS['mudrava_test_filters'] = array();
$GLOBALS['mudrava_test_actions'] = array();
$GLOBALS['mudrava_test_rest_routes'] = array();
$GLOBALS['mudrava_test_registered_types'] = array();
$GLOBALS['mudrava_test_logged_in'] = true;

/**
 * Stub gettext.
 */
function __($text, $domain = 'default') // phpcs:ignore
{
    return $text;
}

function _e($text, $domain = 'default') // phpcs:ignore
{
    echo $text;
}

function esc_html__($text, $domain = 'default') // phpcs:ignore
{
    return esc_html($text);
}

function esc_html_e($text, $domain = 'default') // phpcs:ignore
{
    echo esc_html($text);
}

function esc_attr__($text, $domain = 'default') // phpcs:ignore
{
    return esc_attr($text);
}

function esc_attr_e($text, $domain = 'default') // phpcs:ignore
{
    echo esc_attr($text);
}

function esc_html($text) // phpcs:ignore
{
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

function esc_attr($text) // phpcs:ignore
{
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

function esc_url($url) // phpcs:ignore
{
    return filter_var((string) $url, FILTER_SANITIZE_URL);
}

function sanitize_text_field($str) // phpcs:ignore
{
    $str = (string) $str;
    $str = preg_replace('/<[^>]*>/', '', $str);
    $str = preg_replace('/[\r\n\t ]+/', ' ', $str);

    return trim($str);
}

function sanitize_file_name($name) // phpcs:ignore
{
    return preg_replace('/[^a-zA-Z0-9_\-\.]/', '', (string) $name);
}

function wp_json_encode($data, $options = 0, $depth = 512) // phpcs:ignore
{
    return json_encode($data, $options, $depth);
}

function wp_parse_args($args, $defaults = array()) // phpcs:ignore
{
    return array_merge($defaults, (array) $args);
}

function is_user_logged_in() // phpcs:ignore
{
    return !empty($GLOBALS['mudrava_test_logged_in']);
}

function wp_create_nonce($action = -1) // phpcs:ignore
{
    return 'test_nonce_' . md5((string) $action);
}

function rest_url($path = '') // phpcs:ignore
{
    return 'http://example.test/wp-json/' . ltrim((string) $path, '/');
}

function plugin_dir_url($file) // phpcs:ignore
{
    return 'http://example.test/wp-content/plugins/' . basename(dirname($file)) . '/';
}

function plugin_dir_path($file) // phpcs:ignore
{
    return rtrim(dirname($file), '/') . '/';
}

function get_transient($name) // phpcs:ignore
{
    return isset($GLOBALS['mudrava_test_transients'][$name]) ? $GLOBALS['mudrava_test_transients'][$name] : false;
}

function set_transient($name, $value, $expiration = 0) // phpcs:ignore
{
    $GLOBALS['mudrava_test_transients'][$name] = $value;

    return true;
}

function delete_transient($name) // phpcs:ignore
{
    unset($GLOBALS['mudrava_test_transients'][$name]);

    return true;
}

/**
 * Minimal whitelist-aware kses replacement for tests.
 */
function wp_kses($string, $allowed_html, $allowed_protocols = array()) // phpcs:ignore
{
    return PHP_kses_Test_Kses::sanitize($string, $allowed_html);
}

function add_filter($tag, $callback, $priority = 10, $args = 1) // phpcs:ignore
{
    $GLOBALS['mudrava_test_filters'][$tag][] = $callback;

    return true;
}

function apply_filters($tag, $value, ...$args) // phpcs:ignore
{
    if (empty($GLOBALS['mudrava_test_filters'][$tag])) {
        return $value;
    }

    foreach ($GLOBALS['mudrava_test_filters'][$tag] as $callback) {
        $value = call_user_func_array($callback, array_merge(array($value), $args));
    }

    return $value;
}

function add_action($tag, $callback, $priority = 10, $args = 1) // phpcs:ignore
{
    $GLOBALS['mudrava_test_actions'][$tag][] = $callback;

    return true;
}

function _deprecated_function($function, $version, $replacement = null) // phpcs:ignore
{
}

function current_user_can($capability, ...$args) // phpcs:ignore
{
    return !empty($GLOBALS['mudrava_test_can']);
}

function acf_get_setting($name, $value = null) // phpcs:ignore
{
    return 'manage_options';
}

function has_action($tag, $callback = false) // phpcs:ignore
{
    if (empty($GLOBALS['mudrava_test_actions'][$tag])) {
        return false;
    }

    if (false === $callback) {
        return true;
    }

    return in_array($callback, $GLOBALS['mudrava_test_actions'][$tag], true);
}

function remove_filter($tag, $callback, $priority = 10) // phpcs:ignore
{
    if (empty($GLOBALS['mudrava_test_filters'][$tag])) {
        return false;
    }

    $key = array_search($callback, $GLOBALS['mudrava_test_filters'][$tag], true);

    if (false === $key) {
        return false;
    }

    unset($GLOBALS['mudrava_test_filters'][$tag][$key]);

    return true;
}

function do_action($tag, ...$args) // phpcs:ignore
{
    if (empty($GLOBALS['mudrava_test_actions'][$tag])) {
        return;
    }

    foreach ($GLOBALS['mudrava_test_actions'][$tag] as $callback) {
        call_user_func_array($callback, $args);
    }
}

function add_shortcode($tag, $callback) // phpcs:ignore
{
    $GLOBALS['mudrava_test_shortcodes'][$tag] = $callback;

    return true;
}

function plugin_basename($file) // phpcs:ignore
{
    return basename(dirname($file)) . '/' . basename($file);
}

function absint($value) // phpcs:ignore
{
    return abs((int) $value);
}

function shortcode_atts($defaults, $atts, $shortcode = '') // phpcs:ignore
{
    $atts = (array) $atts;
    $out = array();

    foreach ($defaults as $name => $default) {
        $out[$name] = array_key_exists($name, $atts) ? $atts[$name] : $default;
    }

    return $out;
}

function rest_ensure_response($response) // phpcs:ignore
{
    if ($response instanceof WP_REST_Response) {
        return $response;
    }

    return new WP_REST_Response($response);
}

function register_rest_route($namespace, $route, $args = array()) // phpcs:ignore
{
    $GLOBALS['mudrava_test_rest_routes'][$namespace . $route] = $args;

    return true;
}

function acf_register_field_type($class) // phpcs:ignore
{
    $GLOBALS['mudrava_test_registered_types'][] = $class;
}

$GLOBALS['mudrava_test_field_settings'] = array();
$GLOBALS['mudrava_test_enqueued'] = array();

function wp_enqueue_style($handle, $src = '', $deps = array(), $ver = false) // phpcs:ignore
{
    $GLOBALS['mudrava_test_enqueued']['style'][] = func_get_args();

    return true;
}

function wp_enqueue_script($handle, $src = '', $deps = array(), $ver = false, $in_footer = false) // phpcs:ignore
{
    $GLOBALS['mudrava_test_enqueued']['script'][] = func_get_args();

    return true;
}

function wp_add_inline_script($handle, $data, $position = 'after') // phpcs:ignore
{
    $GLOBALS['mudrava_test_enqueued']['inline'][] = array($handle, $data, $position);

    return true;
}

function load_plugin_textdomain($domain, $deprecated = false, $plugin_rel_path = false) // phpcs:ignore
{
    return true;
}

class WP_REST_Response // phpcs:ignore
{
    public $data;
    public $status;

    public function __construct($data = null, $status = 200)
    {
        $this->data = $data;
        $this->status = $status;
    }

    public function get_data()
    {
        return $this->data;
    }

    public function get_status()
    {
        return $this->status;
    }
}

class ACF // phpcs:ignore
{
}

define('ACF_VERSION', '6.8.9');

if (!class_exists('acf_field')) {
    /**
     * Minimal ACF field base class stub.
     *
     * Mirrors acf_field 6.x: value hooks are not base methods, they are
     * registered in the constructor only when the subclass defines them.
     */
    class acf_field // phpcs:ignore
    {
        public $name = '';
        public $label = '';
        public $category = '';
        public $defaults = array();
        public $l10n = array();
        public $filters = '';

        public function __construct()
        {
            $this->initialize();

            foreach (array('load_value', 'update_value', 'format_value', 'validate_value', 'render_field', 'render_field_settings', 'input_admin_enqueue_scripts') as $method) {
                if (method_exists($this, $method)) {
                    $GLOBALS['mudrava_test_acf_hooks'][$method] = true;
                }
            }
        }

        public function initialize()
        {
        }
    }
}

/**
 * acf_render_field_setting stub.
 */
function acf_render_field_setting($field, $setting) // phpcs:ignore
{
    $GLOBALS['mudrava_test_field_settings'][] = $setting;
}

// Register an SVG sanitizer that mirrors enough of WP's kses behavior for tests.
require_once __DIR__ . '/class-php-kses-kses.php';

require_once dirname(__DIR__, 2) . '/mudrava-acf-lucide-field.php';
require_once dirname(__DIR__, 2) . '/includes/class-mudrava-acf-field-lucide-icon.php';

/**
 * WP_Filesystem stub for the direct-read helpers.
 */
class Test_Filesystem_Stub
{
    public function exists($file) // phpcs:ignore
    {
        return file_exists($file);
    }

    public function get_contents($file) // phpcs:ignore
    {
        $content = file_get_contents($file);

        return false === $content ? '' : $content;
    }

    public function put_contents($file, $contents, $mode = false) // phpcs:ignore
    {
        return false !== file_put_contents($file, $contents);
    }

    public function delete($file, $recursive = false, $type = false) // phpcs:ignore
    {
        return is_file($file) && unlink($file);
    }

    public function chmod($file, $mode = false, $recursive = false) // phpcs:ignore
    {
        return true;
    }
}

$GLOBALS['mudrava_test_options'] = array();

/**
 * Stub get_option.
 */
function get_option($name, $default = false) // phpcs:ignore
{
    return array_key_exists($name, $GLOBALS['mudrava_test_options']) ? $GLOBALS['mudrava_test_options'][$name] : $default;
}

/**
 * Stub update_option.
 */
function update_option($name, $value, $autoload = null) // phpcs:ignore
{
    $GLOBALS['mudrava_test_options'][$name] = $value;

    return true;
}

/**
 * Stub wp_upload_dir.
 */
function wp_upload_dir() // phpcs:ignore
{
    $base = rtrim(ABSPATH, '/\/') . '/uploads';

    return array('basedir' => $base, 'baseurl' => 'http://example.test/wp-content/uploads');
}

/**
 * Stub wp_mkdir_p.
 */
function wp_mkdir_p($target) // phpcs:ignore
{
    return is_dir($target) || mkdir($target, 0777, true);
}

/**
 * Stub trailingslashit.
 */
function trailingslashit($string) // phpcs:ignore
{
    return rtrim($string, '/\\') . '/';
}

/**
 * Stub is_wp_error.
 */
function is_wp_error($thing) // phpcs:ignore
{
    return $thing instanceof WP_Error;
}

/**
 * Stub size_format.
 */
function size_format($bytes, $decimals = 0) // phpcs:ignore
{
    return round(((float) $bytes) / 1024, max(0, (int) $decimals)) . ' KB';
}

$GLOBALS['wp_filesystem'] = new Test_Filesystem_Stub();
