<?php
/**
 * Mudrava ACF Field: Lucide Icon
 *
 * A custom ACF field type for selecting Lucide icons and brand icons
 * with a visual picker interface.
 *
 * @package Mudrava\LucideField
 * @since   1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ACF Field Type: Lucide Icon
 *
 * Provides a visual icon picker for selecting Lucide icons and Simple Icons
 * brand icons. The field stores the icon value (e.g. 'rocket' or
 * 'simple:facebook') in the database.
 *
 * @since 1.0.0
 */
class Mudrava_ACF_Field_Lucide_Icon extends acf_field {

	/**
	 * Transient holding icon values that are not part of the bundled set.
	 *
	 * @var string
	 */
	public const UNKNOWN_TRANSIENT = 'mudrava_lucide_unknown_values';

	/**
	 * Whether admin assets have already been enqueued this request.
	 *
	 * @var bool
	 */
	private static $assets_enqueued = false;

	/**
	 * Initializes the field type.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function initialize(): void {
		$this->name     = 'lucide_icon';
		$this->label    = __( 'Lucide Icon', 'mudrava-acf-lucide-field' );
		$this->category = 'choice';

		$this->defaults = array(
			'allow_null'    => 0,
			'on_unknown'    => 'warn',
			'default_value' => '',
			'return_format' => 'name',
			'placeholder'   => '',
		);
	}

	/**
	 * Enqueue admin scripts and styles for the field.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function input_admin_enqueue_scripts(): void {
		if ( self::$assets_enqueued ) {
			return;
		}

		self::$assets_enqueued = true;

		$js_path       = MUDRAVA_LUCIDE_FIELD_PATH . 'assets/js/field.js';
		$css_path      = MUDRAVA_LUCIDE_FIELD_PATH . 'assets/css/field.css';
		$js_filemtime  = is_readable( $js_path ) ? filemtime( $js_path ) : false;
		$css_filemtime = is_readable( $css_path ) ? filemtime( $css_path ) : false;
		$js_version    = false !== $js_filemtime ? (string) $js_filemtime : MUDRAVA_LUCIDE_FIELD_VERSION;
		$css_version   = false !== $css_filemtime ? (string) $css_filemtime : MUDRAVA_LUCIDE_FIELD_VERSION;

		wp_enqueue_style(
			'mudrava-acf-lucide-field',
			MUDRAVA_LUCIDE_FIELD_URL . 'assets/css/field.css',
			array( 'acf-input' ),
			$css_version
		);

		wp_enqueue_script(
			'mudrava-acf-lucide-field',
			MUDRAVA_LUCIDE_FIELD_URL . 'assets/js/field.js',
			array( 'acf-input' ),
			$js_version,
			true
		);

		$config = array(
			'dataUrl'       => rest_url( 'mudrava-lucide/v1/icons' ),
			'nonce'         => wp_create_nonce( 'wp_rest' ),
			'placeholder'   => __( 'Search icons or brand logos...', 'mudrava-acf-lucide-field' ),
			'noResults'     => __( 'No icons found', 'mudrava-acf-lucide-field' ),
			'emptyLabel'    => __( 'No icon selected', 'mudrava-acf-lucide-field' ),
			'clear'         => __( 'Clear selection', 'mudrava-acf-lucide-field' ),
			'unknownNotice' => __( 'Stored icon is not in the bundled icon set', 'mudrava-acf-lucide-field' ),
			/* Translators: %d is the number of matching icons in the picker. */
			'resultsLabel'  => __( '%d icons available', 'mudrava-acf-lucide-field' ),
			/* Translators: %s is the name of the icon chosen in the picker. */
			'selectedLabel' => __( '%s selected', 'mudrava-acf-lucide-field' ),
		);

		// Pass config via an inline script instead of wp_localize_script:
		// the value is passed through wp_json_encode() so it is safe to embed.
		wp_add_inline_script( 'mudrava-acf-lucide-field', sprintf( 'var mudravaLucideField = %s;', wp_json_encode( $config ) ), 'before' );
	}

	/**
	 * Apply the field default value when no stored value exists.
	 *
	 * @since 1.2.0
	 *
	 * @param mixed $value   Raw value.
	 * @param mixed $post_id Post ID or entity key.
	 * @param array $field   Field settings.
	 * @return mixed
	 */
	public function load_value( $value, $post_id, $field ) {
		// Only a missing value gets the default; an explicitly cleared field
		// stores an empty string, which must survive the round-trip.
		if ( null === $value ) {
			$default = isset( $field['default_value'] ) ? (string) $field['default_value'] : '';

			if ( '' !== $default ) {
				return mudrava_lucide_field_sanitize_icon_value( $default );
			}
		}

		return $value;
	}

	/**
	 * Render the field HTML interface.
	 *
	 * The icon preview is rendered by JavaScript once the icon data is
	 * available; server-side output stays free of icon markup so newly
	 * added rows (e.g. in a repeater) do not leak default values.
	 *
	 * @since 1.0.0
	 *
	 * @param array $field The field settings array.
	 * @return void
	 */
	public function render_field( array $field ): void {
		$value         = isset( $field['value'] ) ? (string) $field['value'] : '';
		$placeholder   = ! empty( $field['placeholder'] ) ? (string) $field['placeholder'] : __( 'Search icons or brand logos...', 'mudrava-acf-lucide-field' );
		$field_id      = esc_attr( (string) $field['id'] );
		$field_name    = esc_attr( (string) $field['name'] );
		$preview_label = '' !== $value ? esc_html( $value ) : esc_html__( 'No icon selected', 'mudrava-acf-lucide-field' );
		?>
		<div class="mudrava-lucide-picker" data-allow-null="<?php echo esc_attr( (string) ( $field['allow_null'] ?? '' ) ); ?>">
			<div class="mudrava-lucide-selected" role="button" tabindex="0" aria-haspopup="listbox" aria-expanded="false">
				<div class="mudrava-lucide-preview">
					<span class="mudrava-lucide-preview-name"><?php echo $preview_label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				</div>
				<?php if ( ! empty( $field['allow_null'] ) && '' !== $value ) : ?>
					<button type="button" class="mudrava-lucide-clear"
						title="<?php esc_attr_e( 'Clear selection', 'mudrava-acf-lucide-field' ); ?>">
						<span class="dashicons dashicons-no-alt"></span>
					</button>
				<?php endif; ?>
			</div>

			<div class="mudrava-lucide-dropdown" role="listbox">
				<div class="mudrava-lucide-search-wrap">
					<input type="text" class="mudrava-lucide-search" placeholder="<?php echo esc_attr( $placeholder ); ?>"
						autocomplete="off"
						role="combobox"
						aria-expanded="false"
						aria-controls="<?php echo esc_attr( $field_id ); ?>-grid"
						aria-label="<?php esc_attr_e( 'Search icons or brand logos...', 'mudrava-acf-lucide-field' ); ?>" />
				</div>
				<div class="mudrava-lucide-grid-wrap">
					<div class="mudrava-lucide-grid" id="<?php echo esc_attr( $field_id ); ?>-grid" role="listbox" aria-label="<?php esc_attr_e( 'Select an icon', 'mudrava-acf-lucide-field' ); ?>"></div>
					<div class="mudrava-lucide-no-results" style="display: none;">
						<?php esc_html_e( 'No icons found', 'mudrava-acf-lucide-field' ); ?>
					</div>
				</div>
			</div>

			<input type="hidden" id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $field_name ); ?>"
				value="<?php echo esc_attr( $value ); ?>" class="mudrava-lucide-input" data-default="<?php echo esc_attr( (string) ( $field['default_value'] ?? '' ) ); ?>" />
			<span class="screen-reader-text mudrava-lucide-live" role="status" aria-live="polite"></span>
		</div>
		<?php
	}

	/**
	 * Render the field settings in the ACF field group editor.
	 *
	 * @since 1.0.0
	 *
	 * @param array $field The field settings array.
	 * @return void
	 */
	public function render_field_settings( array $field ): void {
		acf_render_field_setting(
			$field,
			array(
				'label'        => __( 'Default Value', 'mudrava-acf-lucide-field' ),
				'instructions' => __( 'Enter the icon value to be selected by default (e.g., "rocket" or "simple:facebook").', 'mudrava-acf-lucide-field' ),
				'type'         => 'text',
				'name'         => 'default_value',
			)
		);

		acf_render_field_setting(
			$field,
			array(
				'label'        => __( 'Return Format', 'mudrava-acf-lucide-field' ),
				'instructions' => __( 'Specify the format returned by get_field().', 'mudrava-acf-lucide-field' ),
				'type'         => 'radio',
				'name'         => 'return_format',
				'choices'      => array(
					'name' => __( 'Icon Value (e.g., "rocket" or "simple:facebook")', 'mudrava-acf-lucide-field' ),
					'svg'  => __( 'SVG Markup', 'mudrava-acf-lucide-field' ),
				),
				'layout'       => 'horizontal',
			)
		);

		acf_render_field_setting(
			$field,
			array(
				'label'        => __( 'Placeholder', 'mudrava-acf-lucide-field' ),
				'instructions' => __( 'Placeholder text for the search input.', 'mudrava-acf-lucide-field' ),
				'type'         => 'text',
				'name'         => 'placeholder',
			)
		);
	}

	/**
	 * Render the validation settings for the field.
	 *
	 * @since 1.0.0
	 *
	 * @param array $field The field settings array.
	 * @return void
	 */
	public function render_field_validation_settings( array $field ): void {
		acf_render_field_setting(
			$field,
			array(
				'label'        => __( 'Allow Null', 'mudrava-acf-lucide-field' ),
				'instructions' => __( 'Allow the field to have no icon selected.', 'mudrava-acf-lucide-field' ),
				'name'         => 'allow_null',
				'type'         => 'true_false',
				'ui'           => 1,
			)
		);

		acf_render_field_setting(
			$field,
			array(
				'label'         => __( 'Unknown Icon Values', 'mudrava-acf-lucide-field' ),
				'instructions'  => __( 'How to handle values that are not part of the bundled icon set (e.g. icons imported from another site or values left over after an icon library update).', 'mudrava-acf-lucide-field' ),
				'name'          => 'on_unknown',
				'type'          => 'select',
				'choices'       => array(
					'warn'  => __( 'Save and show an admin notice (default)', 'mudrava-acf-lucide-field' ),
					'error' => __( 'Block saving with a validation error', 'mudrava-acf-lucide-field' ),
				),
				'default_value' => 'warn',
				'return_format' => 'value',
			)
		);
	}

	/**
	 * Format the field value for use in templates.
	 *
	 * Based on the return_format setting, returns either the icon value
	 * or the full SVG markup.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value   The field value from the database.
	 * @param int   $post_id The post ID.
	 * @param array $field   The field settings array.
	 * @return string The formatted value.
	 */
	public function format_value( $value, $post_id, $field ): string {
		if ( empty( $value ) ) {
			return '';
		}

		$value = (string) $value;

		if ( 'svg' === ( $field['return_format'] ?? 'name' ) ) {
			$args = apply_filters(
				'mudrava_lucide_field_svg_args',
				array(),
				$field,
				$value
			);

			return mudrava_get_lucide_icon( $value, is_array( $args ) ? $args : array() );
		}

		return $value;
	}

	/**
	 * Sanitize the field value before saving to the database.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value   The value to save.
	 * @param int   $post_id The post ID.
	 * @param array $field   The field settings array.
	 * @return string The sanitized value.
	 */
	public function update_value( $value, $post_id, $field ): string {
		if ( empty( $value ) ) {
			return '';
		}

		return mudrava_lucide_field_sanitize_icon_value( (string) $value );
	}

	/**
	 * Validate the field value.
	 *
	 * @since 1.0.0
	 *
	 * @param bool|string $valid Whether the value is valid.
	 * @param mixed       $value The field value.
	 * @param array       $field The field settings array.
	 * @param string      $input The input element name.
	 * @return bool|string True if valid, error message string if invalid.
	 */
	public function validate_value( $valid, $value, $field, $input ) {
		if ( empty( $field['allow_null'] ) && empty( $value ) ) {
			return __( 'Please select an icon.', 'mudrava-acf-lucide-field' );
		}

		if ( ! empty( $value ) && ! mudrava_lucide_field_icon_exists( (string) $value ) ) {
			$mode = isset( $field['on_unknown'] ) ? (string) $field['on_unknown'] : 'warn';

			if ( 'error' === $mode ) {
				return __( 'Please select a valid icon.', 'mudrava-acf-lucide-field' );
			}

			// Legacy or foreign values must not block saving existing content;
			// surface a dismissible notice instead.
			$unknown   = get_transient( self::UNKNOWN_TRANSIENT );
			$unknown   = is_array( $unknown ) ? $unknown : array();
			$unknown[] = (string) $value;

			set_transient( self::UNKNOWN_TRANSIENT, array_values( array_unique( $unknown ) ), 120 );
		}

		return $valid;
	}
}

/**
 * Validate default values of Lucide icon fields when a field group is saved.
 *
 * Invalid defaults are surfaced via a dismissible admin notice on the next
 * page load instead of blocking the save.
 *
 * @since 1.2.0
 *
 * @param array $field_group Saved field group.
 * @return void
 */
function mudrava_lucide_field_validate_field_group_defaults( array $field_group ): void {
	$invalid = array();

	foreach ( isset( $field_group['fields'] ) && is_array( $field_group['fields'] ) ? $field_group['fields'] : array() as $field ) {
		if ( ! isset( $field['type'] ) || 'lucide_icon' !== $field['type'] ) {
			continue;
		}

		$default = isset( $field['default_value'] ) ? trim( (string) $field['default_value'] ) : '';

		if ( '' !== $default && ! mudrava_lucide_field_icon_exists( $default ) ) {
			$invalid[] = isset( $field['label'] ) && '' !== (string) $field['label'] ? (string) $field['label'] : (string) ( $field['name'] ?? '' );
		}
	}

	if ( ! empty( $invalid ) ) {
		set_transient( 'mudrava_lucide_invalid_defaults', $invalid, 120 );
	}
}
add_action( 'acf/updated_field_group', 'mudrava_lucide_field_validate_field_group_defaults' );

/**
 * Show a notice about unknown icon values saved for Lucide icon fields.
 *
 * Covers both invalid field group defaults and post values that are not
 * part of the bundled icon set.
 *
 * @since 1.2.0
 *
 * @return void
 */
function mudrava_lucide_field_invalid_defaults_notice(): void {
	$capability = function_exists( 'acf_get_setting' ) ? acf_get_setting( 'capability' ) : 'manage_options';
	$capability = is_string( $capability ) && '' !== $capability ? $capability : 'manage_options';

	// The transients are global: without this gate any admin-area page view
	// by a low-privileged user would consume notices meant for editors.
	if ( ! current_user_can( $capability ) ) {
		return;
	}

	$invalid = get_transient( 'mudrava_lucide_invalid_defaults' );
	$invalid = is_array( $invalid ) ? $invalid : array();

	$unknown = get_transient( Mudrava_ACF_Field_Lucide_Icon::UNKNOWN_TRANSIENT );
	$unknown = is_array( $unknown ) ? $unknown : array();

	delete_transient( 'mudrava_lucide_invalid_defaults' );
	delete_transient( Mudrava_ACF_Field_Lucide_Icon::UNKNOWN_TRANSIENT );

	$items = array_values( array_unique( array_merge( $invalid, $unknown ) ) );

	if ( empty( $items ) ) {
		return;
	}

	$rows = '';

	foreach ( $items as $item ) {
		$rows .= '<li><code>' . esc_html( (string) $item ) . '</code></li>';
	}

	printf(
		'<div class="notice notice-warning is-dismissible"><p>%s</p><ul class="ul-disc">%s</ul></div>',
		esc_html__( 'Mudrava Icon Field: some icon values are not part of the bundled icon set and will render as empty markup:', 'mudrava-acf-lucide-field' ),
		$rows // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	);
}
add_action( 'admin_notices', 'mudrava_lucide_field_invalid_defaults_notice' );
