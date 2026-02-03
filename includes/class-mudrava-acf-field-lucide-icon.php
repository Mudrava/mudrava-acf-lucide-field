<?php
/**
 * Mudrava ACF Field: Lucide Icon
 *
 * A custom ACF field type for selecting Lucide icons with a visual picker interface.
 *
 * @package Mudrava\LucideField
 * @since   1.0.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ACF Field Type: Lucide Icon
 *
 * Provides a visual icon picker for selecting Lucide icons.
 * The field stores the icon name (e.g., 'rocket') in the database.
 *
 * @since 1.0.0
 */
class Mudrava_ACF_Field_Lucide_Icon extends acf_field
{

    /**
     * Cached icon data from icons.json.
     *
     * @var array<string, array<string>>|null
     */
    private ?array $icons_cache = null;

    /**
     * Initializes the field type.
     *
     * Sets up the field name, label, category, and default settings.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function initialize(): void
    {
        $this->name = 'lucide_icon';
        $this->label = __('Lucide Icon', 'mudrava-lucide-field');
        $this->category = 'choice';

        $this->defaults = array(
            'allow_null' => 0,
            'default_value' => '',
            'return_format' => 'name',
            'placeholder' => '',
        );
    }

    /**
     * Enqueues admin scripts and styles for the field.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function input_admin_enqueue_scripts(): void
    {
        $version = MUDRAVA_LUCIDE_FIELD_VERSION;
        $url = MUDRAVA_LUCIDE_FIELD_URL;

        wp_enqueue_style(
            'mudrava-lucide-field',
            $url . 'assets/css/field.css',
            array('acf-input'),
            $version
        );

        wp_enqueue_script(
            'mudrava-lucide-field',
            $url . 'assets/js/field.js',
            array('acf-input'),
            $version,
            true
        );

        wp_localize_script(
            'mudrava-lucide-field',
            'mudravaLucideField',
            array(
                'icons' => $this->get_icons(),
                'spriteUrl' => MUDRAVA_LUCIDE_FIELD_URL . 'assets/sprite.svg',
                'placeholder' => __('Search icons...', 'mudrava-lucide-field'),
                'noResults' => __('No icons found', 'mudrava-lucide-field'),
                'clear' => __('Clear selection', 'mudrava-lucide-field'),
            )
        );
    }

    /**
     * Retrieves the icon data from the bundled JSON file.
     *
     * Returns an associative array where keys are icon names and values
     * are arrays of search tags.
     *
     * @since 1.0.0
     *
     * @return array<string, array<string>> Icon data array.
     */
    private function get_icons(): array
    {
        if (null !== $this->icons_cache) {
            return $this->icons_cache;
        }

        $json_path = MUDRAVA_LUCIDE_FIELD_PATH . 'data/icons.json';

        if (!file_exists($json_path)) {
            $this->icons_cache = array();
            return $this->icons_cache;
        }

        $json_content = file_get_contents($json_path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

        if (false === $json_content) {
            $this->icons_cache = array();
            return $this->icons_cache;
        }

        $icons = json_decode($json_content, true);

        if (!is_array($icons)) {
            $this->icons_cache = array();
            return $this->icons_cache;
        }

        $this->icons_cache = $icons;
        return $this->icons_cache;
    }

    /**
     * Renders the field HTML interface.
     *
     * Creates the icon picker UI with search input, icon grid,
     * and hidden input for storing the selected value.
     *
     * @since 1.0.0
     *
     * @param array $field The field settings array.
     * @return void
     */
    public function render_field(array $field): void
    {
        $value = $field['value'] ?? '';
        $placeholder = $field['placeholder'] ?: __('Search icons...', 'mudrava-lucide-field');
        $field_id = esc_attr($field['id']);
        $field_name = esc_attr($field['name']);
        ?>
        <div class="mudrava-lucide-picker" data-allow-null="<?php echo esc_attr((string) $field['allow_null']); ?>">
            <div class="mudrava-lucide-selected">
                <div class="mudrava-lucide-preview">
                    <?php if ($value): ?>
                        <span class="mudrava-lucide-preview-name"><?php echo esc_html($value); ?></span>
                    <?php else: ?>
                        <span
                            class="mudrava-lucide-preview-empty"><?php esc_html_e('No icon selected', 'mudrava-lucide-field'); ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($field['allow_null'] && $value): ?>
                    <button type="button" class="mudrava-lucide-clear"
                        title="<?php esc_attr_e('Clear selection', 'mudrava-lucide-field'); ?>">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                <?php endif; ?>
            </div>

            <div class="mudrava-lucide-dropdown">
                <div class="mudrava-lucide-search-wrap">
                    <input type="text" class="mudrava-lucide-search" placeholder="<?php echo esc_attr($placeholder); ?>"
                        autocomplete="off" />
                </div>
                <div class="mudrava-lucide-grid-wrap">
                    <div class="mudrava-lucide-grid"></div>
                    <div class="mudrava-lucide-no-results" style="display: none;">
                        <?php esc_html_e('No icons found', 'mudrava-lucide-field'); ?>
                    </div>
                </div>
            </div>

            <input type="hidden" id="<?php echo $field_id; ?>" name="<?php echo $field_name; ?>"
                value="<?php echo esc_attr($value); ?>" class="mudrava-lucide-input" />
        </div>
        <?php
    }

    /**
     * Renders the field settings in the ACF field group editor.
     *
     * @since 1.0.0
     *
     * @param array $field The field settings array.
     * @return void
     */
    public function render_field_settings(array $field): void
    {
        acf_render_field_setting(
            $field,
            array(
                'label' => __('Default Value', 'mudrava-lucide-field'),
                'instructions' => __('Enter the icon name to be selected by default (e.g., "rocket").', 'mudrava-lucide-field'),
                'type' => 'text',
                'name' => 'default_value',
            )
        );

        acf_render_field_setting(
            $field,
            array(
                'label' => __('Return Format', 'mudrava-lucide-field'),
                'instructions' => __('Specify the format returned by get_field().', 'mudrava-lucide-field'),
                'type' => 'radio',
                'name' => 'return_format',
                'choices' => array(
                    'name' => __('Icon Name (e.g., "rocket")', 'mudrava-lucide-field'),
                    'svg' => __('SVG Markup', 'mudrava-lucide-field'),
                ),
                'layout' => 'horizontal',
            )
        );

        acf_render_field_setting(
            $field,
            array(
                'label' => __('Placeholder', 'mudrava-lucide-field'),
                'instructions' => __('Placeholder text for the search input.', 'mudrava-lucide-field'),
                'type' => 'text',
                'name' => 'placeholder',
            )
        );
    }

    /**
     * Renders the validation settings for the field.
     *
     * @since 1.0.0
     *
     * @param array $field The field settings array.
     * @return void
     */
    public function render_field_validation_settings(array $field): void
    {
        acf_render_field_setting(
            $field,
            array(
                'label' => __('Allow Null', 'mudrava-lucide-field'),
                'instructions' => __('Allow the field to have no icon selected.', 'mudrava-lucide-field'),
                'name' => 'allow_null',
                'type' => 'true_false',
                'ui' => 1,
            )
        );
    }

    /**
     * Formats the field value for use in templates.
     *
     * Based on the return_format setting, returns either the icon name
     * or the full SVG markup.
     *
     * @since 1.0.0
     *
     * @param mixed $value   The field value from the database.
     * @param int   $post_id The post ID.
     * @param array $field   The field settings array.
     * @return string The formatted value.
     */
    public function format_value($value, $post_id, $field): string
    {
        if (empty($value)) {
            return '';
        }

        $value = (string) $value;

        if ('svg' === ($field['return_format'] ?? 'name')) {
            return mudrava_get_lucide_icon($value);
        }

        return $value;
    }

    /**
     * Sanitizes the field value before saving to the database.
     *
     * @since 1.0.0
     *
     * @param mixed $value   The value to save.
     * @param int   $post_id The post ID.
     * @param array $field   The field settings array.
     * @return string The sanitized value.
     */
    public function update_value($value, $post_id, $field): string
    {
        if (empty($value)) {
            return '';
        }

        return sanitize_file_name((string) $value);
    }

    /**
     * Validates the field value.
     *
     * @since 1.0.0
     *
     * @param bool|string $valid   Whether the value is valid.
     * @param mixed       $value   The field value.
     * @param array       $field   The field settings array.
     * @param string      $input   The input element name.
     * @return bool|string True if valid, error message string if invalid.
     */
    public function validate_value($valid, $value, $field, $input)
    {
        if (!$field['allow_null'] && empty($value)) {
            return __('Please select an icon.', 'mudrava-lucide-field');
        }

        return $valid;
    }
}
