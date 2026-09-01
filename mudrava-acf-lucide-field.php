<?php
/**
 * Plugin Name:       Mudrava Icon Field for ACF with Lucide
 * Plugin URI:        https://wordpress.org/plugins/mudrava-acf-lucide-field/
 * Description:       A custom ACF field type for selecting Lucide icons and Simple Icons brand icons with a visual picker interface.
 * Version:           1.2.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Mudrava
 * Author URI:        https://mudrava.com/en/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mudrava-acf-lucide-field
 * Domain Path:       /languages
 *
 * @package Mudrava\LucideField
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin version.
 *
 * Keep in sync with the "Version" plugin header above.
 *
 * @var string
 */
define( 'MUDRAVA_LUCIDE_FIELD_VERSION', '1.2.0' );

/**
 * Bundled Lucide icon asset version (upstream lucide-static release).
 *
 * Declared in scripts/upstream-versions.json; rewritten by scripts/build-sprites.mjs.
 *
 * @var string
 */
define( 'MUDRAVA_LUCIDE_FIELD_LUCIDE_VERSION', '1.38.0' );

/**
 * Bundled Simple Icons asset version (upstream simple-icons release).
 *
 * Declared in scripts/upstream-versions.json; rewritten by scripts/build-sprites.mjs.
 *
 * @var string
 */
define( 'MUDRAVA_LUCIDE_FIELD_SIMPLE_ICONS_VERSION', '16.29.0' );

/**
 * Plugin directory path.
 *
 * @var string
 */
define( 'MUDRAVA_LUCIDE_FIELD_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL.
 *
 * @var string
 */
define( 'MUDRAVA_LUCIDE_FIELD_URL', plugin_dir_url( __FILE__ ) );

/**
 * Check whether a supported ACF version is active.
 *
 * @since 1.0.0
 *
 * @return bool True if ACF 6.0+ is active, false otherwise.
 */
function mudrava_lucide_field_check_acf(): bool {
	return class_exists( 'ACF' ) && defined( 'ACF_VERSION' ) && version_compare( (string) ACF_VERSION, '6.0', '>=' );
}

/**
 * Display an admin notice if a supported ACF version is not active.
 *
 * @since 1.0.0
 *
 * @return void
 */
function mudrava_lucide_field_acf_notice(): void {
	if ( mudrava_lucide_field_check_acf() ) {
		return;
	}

	$message = sprintf(
		/* translators: 1: Advanced Custom Fields link, 2: required ACF version */
		__( 'This plugin requires %1$s version %2$s or higher to function.', 'mudrava-acf-lucide-field' ),
		'<a href="https://www.advancedcustomfields.com/" target="_blank" rel="noopener noreferrer">Advanced Custom Fields</a>',
		'6.0'
	);

	echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Mudrava Icon Field:', 'mudrava-acf-lucide-field' ) . '</strong> ';
	echo wp_kses(
		$message,
		array(
			'a' => array(
				'href'   => true,
				'target' => true,
				'rel'    => true,
			),
		)
	) . '</p></div>';
}
add_action( 'admin_notices', 'mudrava_lucide_field_acf_notice' );

/**
 * Register the Lucide Icon field type with ACF.
 *
 * Hooked to 'acf/include_field_types' to ensure ACF is loaded first.
 *
 * @since 1.0.0
 *
 * @return void
 */
function mudrava_lucide_field_register(): void {
	if ( ! mudrava_lucide_field_check_acf() ) {
		return;
	}

	require_once MUDRAVA_LUCIDE_FIELD_PATH . 'includes/class-mudrava-acf-field-lucide-icon.php';

	acf_register_field_type( 'Mudrava_ACF_Field_Lucide_Icon' );
}
add_action( 'acf/include_field_types', 'mudrava_lucide_field_register' );

/**
 * Modify plugin row links.
 *
 * @since 1.0.0
 *
 * @param array  $links       Current plugin links.
 * @param string $plugin_file Plugin file name.
 * @return array Modified links.
 */
function mudrava_lucide_field_plugin_links( array $links, string $plugin_file ): array {
	if ( plugin_basename( __FILE__ ) !== $plugin_file ) {
		return $links;
	}

	$links[] = sprintf(
		'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
		esc_url( 'https://github.com/Mudrava/mudrava-acf-lucide-field/blob/main/README.md' ),
		esc_html__( 'Docs', 'mudrava-acf-lucide-field' )
	);

	return $links;
}
add_filter( 'plugin_row_meta', 'mudrava_lucide_field_plugin_links', 10, 2 );

/**
 * Allowed SVG child elements and attributes for icon symbol markup.
 *
 * Canonical list in data/allowed-svg.json (shared with the build scripts).
 * Shapes only: no text, script, image or external references can ever
 * reach the frontend.
 *
 * @since 1.0.1
 *
 * @return array<string, array<string, bool>>
 */
function mudrava_lucide_field_allowed_svg_children(): array {
	static $allowed = null;

	if ( null !== $allowed ) {
		return $allowed;
	}

	$allowed   = array();
	$canonical = MUDRAVA_LUCIDE_FIELD_PATH . 'data/allowed-svg.json';

	$filesystem = mudrava_lucide_field_filesystem();

	if ( $filesystem && $filesystem->exists( $canonical ) ) {
		$json = json_decode( (string) $filesystem->get_contents( $canonical ), true );

		if ( is_array( $json ) && ! empty( $json['elements'] ) && ! empty( $json['attributes'] ) ) {
			$attrs = array_fill_keys( $json['attributes'], true );

			foreach ( $json['elements'] as $element ) {
				$allowed[ $element ] = $attrs;
			}

			return $allowed;
		}
	}

	$shape_attrs = array(
		'd'                 => true,
		'cx'                => true,
		'cy'                => true,
		'r'                 => true,
		'x'                 => true,
		'y'                 => true,
		'x1'                => true,
		'x2'                => true,
		'y1'                => true,
		'y2'                => true,
		'rx'                => true,
		'ry'                => true,
		'width'             => true,
		'height'            => true,
		'points'            => true,
		'fill'              => true,
		'stroke'            => true,
		'stroke-width'      => true,
		'stroke-linecap'    => true,
		'stroke-linejoin'   => true,
		'stroke-miterlimit' => true,
		'fill-rule'         => true,
		'clip-rule'         => true,
		'transform'         => true,
		'opacity'           => true,
		'fill-opacity'      => true,
		'stroke-opacity'    => true,
	);

	$allowed = array_fill_keys( array( 'circle', 'ellipse', 'g', 'line', 'path', 'polygon', 'polyline', 'rect' ), $shape_attrs );

	return $allowed;
}

/**
 * Get the WordPress filesystem API instance.
 *
 * @since 1.2.0
 *
 * @return WP_Filesystem_Direct|object|false Filesystem instance or false.
 */
function mudrava_lucide_field_filesystem() {
	static $filesystem = null;

	if ( null !== $filesystem ) {
		return $filesystem;
	}

	global $wp_filesystem;

	if ( ! is_object( $wp_filesystem ) ) {
		if ( ! function_exists( 'WP_Filesystem' ) && defined( 'ABSPATH' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( function_exists( 'WP_Filesystem' ) ) {
			WP_Filesystem();
		}
	}

	$filesystem = is_object( $GLOBALS['wp_filesystem'] ) ? $GLOBALS['wp_filesystem'] : false;

	return $filesystem;
}

/**
 * Get bundled icon data from a generated PHP file.
 *
 * @since 1.0.0
 *
 * @param string $slug Data file slug without extension, e.g. 'lucide-index'.
 * @return array<string,mixed> Empty array when the file is unavailable.
 */
function mudrava_lucide_field_data( string $slug ): array {
	static $cache = array();

	$slug = basename( $slug );

	if ( '' === $slug ) {
		return array();
	}

	if ( isset( $cache[ $slug ] ) ) {
		return $cache[ $slug ];
	}

	$file = MUDRAVA_LUCIDE_FIELD_PATH . 'data/' . $slug . '.php';

	if ( ! is_readable( $file ) ) {
		$cache[ $slug ] = array();
		return $cache[ $slug ];
	}

	$data = require $file;

	$cache[ $slug ] = is_array( $data ) ? $data : array();

	return $cache[ $slug ];
}

/**
 * Read a byte slice from a bundled sprite through the filesystem API.
 *
 * @since 1.2.0
 *
 * @param string $sprite Sprite file name inside assets/, e.g. 'sprite.svg'.
 * @param int    $offset Byte offset.
 * @param int    $length Byte length.
 * @return string Raw slice or empty string on failure.
 */
function mudrava_lucide_field_read_sprite_slice( string $sprite, int $offset, int $length ): string {
	static $contents = array();

	$sprite = basename( $sprite );

	if ( '' === $sprite ) {
		return '';
	}

	if ( ! isset( $contents[ $sprite ] ) ) {
		$filesystem          = mudrava_lucide_field_filesystem();
		$path                = MUDRAVA_LUCIDE_FIELD_PATH . 'assets/' . $sprite;
		$contents[ $sprite ] = ( $filesystem && $filesystem->exists( $path ) ) ? (string) $filesystem->get_contents( $path ) : '';
	}

	$content = $contents[ $sprite ];

	if ( '' === $content || $offset < 0 || $length <= 0 || $length > 1000000 || $offset >= strlen( $content ) ) {
		return '';
	}

	return substr( $content, $offset, $length );
}

/**
 * Get sanitized symbol markup for a concrete source and name.
 *
 * Prefers the compiled byte index (slice of a single sprite read);
 * falls back to the regex parser when generated data files are missing
 * (damaged deployment).
 *
 * @since 1.2.0
 *
 * @param string $source Icon source ('lucide' or 'simple').
 * @param string $name   Icon name or brand slug.
 * @return string Sanitized symbol inner markup, empty string when missing.
 */
function mudrava_lucide_field_get_symbol( string $source, string $name ): string {
	static $memo = array();

	if ( '' === $name ) {
		return '';
	}

	$memo_key = $source . ':' . $name;

	if ( isset( $memo[ $memo_key ] ) ) {
		return $memo[ $memo_key ];
	}

	if ( 'custom' === $source ) {
		$custom            = mudrava_lucide_field_get_custom_icon( $name );
		$memo[ $memo_key ] = $custom['inner'];

		return $custom['inner'];
	}

	$is_brand = 'simple' === $source;
	$slug     = $is_brand ? 'simple' : 'lucide';
	$index    = mudrava_lucide_field_data( $slug . '-index' );

	if ( ! empty( $index ) ) {
		if ( ! isset( $index[ $name ] ) || ! is_array( $index[ $name ] ) || count( $index[ $name ] ) < 2 ) {
			$memo[ $memo_key ] = '';

			return '';
		}

		$symbol = mudrava_lucide_field_read_sprite_slice( $is_brand ? 'brand-sprite.svg' : 'sprite.svg', (int) $index[ $name ][0], (int) $index[ $name ][1] );
	} else {
		$parsed = mudrava_lucide_field_parse_sprite_symbols(
			MUDRAVA_LUCIDE_FIELD_PATH . 'assets/' . ( $is_brand ? 'brand-sprite.svg' : 'sprite.svg' ),
			$is_brand ? 'simple-' : ''
		);

		$symbol = isset( $parsed[ $name ] ) && is_string( $parsed[ $name ] ) ? $parsed[ $name ] : '';
	}

	// Defense in depth: re-sanitize even though markup is sanitized at build time.
	$markup            = '' === $symbol ? '' : wp_kses( $symbol, mudrava_lucide_field_allowed_svg_children() );
	$memo[ $memo_key ] = $markup;

	return $markup;
}

/**
 * Fallback parser for sprite symbol inner markup, used only when the
 * generated data files are missing (e.g. damaged deployment).
 *
 * @since 1.0.1
 *
 * @param string $sprite_path   Absolute path to the sprite file.
 * @param string $symbol_prefix Symbol id prefix to strip ('' or 'simple-').
 * @return array<string, string>
 */
function mudrava_lucide_field_parse_sprite_symbols( string $sprite_path, string $symbol_prefix = '' ): array {
	static $memo = array();

	$memo_key = $sprite_path . '|' . $symbol_prefix;

	if ( isset( $memo[ $memo_key ] ) ) {
		return $memo[ $memo_key ];
	}

	$symbols = array();

	$filesystem = mudrava_lucide_field_filesystem();

	if ( ! $filesystem || ! $filesystem->exists( $sprite_path ) ) {
		$memo[ $memo_key ] = $symbols;

		return $symbols;
	}

	$sprite_content = (string) $filesystem->get_contents( $sprite_path );

	if ( '' === $sprite_content ) {
		$memo[ $memo_key ] = $symbols;

		return $symbols;
	}

	preg_match_all( '/<symbol\b[^>]*\bid="([^"]+)"[^>]*>(.*?)<\/symbol>/is', $sprite_content, $matches, PREG_SET_ORDER );

	foreach ( $matches as $match ) {
		$icon_id = html_entity_decode( $match[1], ENT_QUOTES, 'UTF-8' );

		if ( '' !== $symbol_prefix && 0 !== strpos( $icon_id, $symbol_prefix ) ) {
			continue;
		}

		$slug   = '' !== $symbol_prefix ? substr( $icon_id, strlen( $symbol_prefix ) ) : $icon_id;
		$markup = trim( $match[2] );

		if ( '' === $slug || '' === $markup ) {
			continue;
		}

		$symbols[ $slug ] = wp_kses( $markup, mudrava_lucide_field_allowed_svg_children() );
	}

	$memo[ $memo_key ] = $symbols;

	return $symbols;
}

/**
 * Back-compat shim for the pre-1.2.0 Lucide symbol map.
 *
 * @since 1.0.0
 * @deprecated 1.2.0 Use mudrava_lucide_field_parse_sprite_symbols() instead.
 *
 * @return array<string, string>
 */
function mudrava_lucide_field_get_sprite_symbols(): array {
	_deprecated_function( __FUNCTION__, '1.2.0', 'mudrava_lucide_field_parse_sprite_symbols()' );

	return mudrava_lucide_field_parse_sprite_symbols( MUDRAVA_LUCIDE_FIELD_PATH . 'assets/sprite.svg', '' );
}

/**
 * Back-compat shim for the pre-1.2.0 brand symbol map.
 *
 * @since 1.0.0
 * @deprecated 1.2.0 Use mudrava_lucide_field_parse_sprite_symbols() instead.
 *
 * @return array<string, string>
 */
function mudrava_lucide_field_get_brand_sprite_symbols(): array {
	_deprecated_function( __FUNCTION__, '1.2.0', 'mudrava_lucide_field_parse_sprite_symbols()' );

	return mudrava_lucide_field_parse_sprite_symbols( MUDRAVA_LUCIDE_FIELD_PATH . 'assets/brand-sprite.svg', 'simple-' );
}

/**
 * Whether an icon exists in a bundled source.
 *
 * @since 1.2.0
 *
 * @param string $source Icon source ('lucide' or 'simple').
 * @param string $name   Icon name or brand slug.
 * @return bool
 */
function mudrava_lucide_field_symbol_exists( string $source, string $name ): bool {
	if ( '' === $name ) {
		return false;
	}

	if ( 'custom' === $source ) {
		$custom_icons = mudrava_lucide_field_get_custom_icons();

		return isset( $custom_icons[ $name ] );
	}

	$is_brand = 'simple' === $source;
	$slug     = $is_brand ? 'simple' : 'lucide';
	$index    = mudrava_lucide_field_data( $slug . '-index' );

	if ( ! empty( $index ) ) {
		return isset( $index[ $name ] );
	}

	$parsed = mudrava_lucide_field_parse_sprite_symbols(
		MUDRAVA_LUCIDE_FIELD_PATH . 'assets/' . ( $is_brand ? 'brand-sprite.svg' : 'sprite.svg' ),
		$is_brand ? 'simple-' : ''
	);

	return isset( $parsed[ $name ] );
}

/**
 * Get bundled Simple Icons search tags.
 *
 * @since 1.1.0
 *
 * @return array<string, array<int, string>>
 */
function mudrava_lucide_field_get_brand_icon_tags(): array {
	$tags = mudrava_lucide_field_data( 'simple-tags' );

	if ( ! empty( $tags ) ) {
		return $tags;
	}

	return mudrava_lucide_field_read_json( 'brand-icons.json' );
}

/**
 * Get bundled Lucide search tags.
 *
 * @since 1.2.0
 *
 * @return array<string, array<int, string>>
 */
function mudrava_lucide_field_get_lucide_tags(): array {
	$tags = mudrava_lucide_field_data( 'lucide-tags' );

	if ( ! empty( $tags ) ) {
		return $tags;
	}

	return mudrava_lucide_field_read_json( 'icons.json' );
}

/**
 * Read a bundled JSON file from data/.
 *
 * @since 1.2.0
 *
 * @param string $name File name inside data/.
 * @return array<string,mixed>
 */
function mudrava_lucide_field_read_json( string $name ): array {
	static $cache = array();

	$name = basename( $name );

	if ( '' === $name ) {
		return array();
	}

	if ( isset( $cache[ $name ] ) ) {
		return $cache[ $name ];
	}

	$path       = MUDRAVA_LUCIDE_FIELD_PATH . 'data/' . $name;
	$filesystem = mudrava_lucide_field_filesystem();

	if ( ! $filesystem || ! $filesystem->exists( $path ) ) {
		$cache[ $name ] = array();
		return $cache[ $name ];
	}

	$content = (string) $filesystem->get_contents( $path );

	if ( '' === $content ) {
		$cache[ $name ] = array();
		return $cache[ $name ];
	}

	$decoded = json_decode( $content, true );

	$cache[ $name ] = is_array( $decoded ) ? $decoded : array();

	return $cache[ $name ];
}

/**
 * Normalize icon values for matching user input and legacy saved values.
 *
 * @since 1.1.0
 *
 * @param string $value Raw value.
 * @return string Normalized value.
 */
function mudrava_lucide_field_normalize_icon_token( string $value ): string {
	$value = strtolower( trim( $value ) );
	$value = (string) preg_replace( '/[^a-z0-9]+/', '-', $value );

	return trim( $value, '-' );
}

/**
 * Get a map of Simple Icons aliases to canonical slugs.
 *
 * @since 1.1.0
 *
 * @return array<string, string>
 */
function mudrava_lucide_field_get_brand_alias_map(): array {
	static $aliases = null;

	if ( null !== $aliases ) {
		return $aliases;
	}

	$aliases = array();

	foreach ( mudrava_lucide_field_get_brand_icon_tags() as $slug => $tags ) {
		if ( ! mudrava_lucide_field_symbol_exists( 'simple', (string) $slug ) ) {
			continue;
		}

		$aliases[ (string) $slug ] = (string) $slug;

		foreach ( (array) $tags as $tag ) {
			$token = mudrava_lucide_field_normalize_icon_token( (string) $tag );

			if ( '' !== $token && ! isset( $aliases[ $token ] ) ) {
				$aliases[ $token ] = (string) $slug;
			}
		}
	}

	return $aliases;
}

/**
 * Get compatibility aliases for icon names removed upstream by a library update.
 *
 * Values saved with older plugin versions keep resolving to the closest
 * current replacement, so existing content keeps rendering after an
 * upstream rename/removal. Stored values are never rewritten.
 *
 * @since 1.2.0
 *
 * @return array<string, string> Old name => replacement name.
 */
function mudrava_lucide_field_get_compat_aliases(): array {
	static $aliases = null;

	if ( null !== $aliases ) {
		return $aliases;
	}

	$aliases = array();
	$data    = mudrava_lucide_field_read_json( 'compat-aliases.json' );

	foreach ( $data as $old => $replacement ) {
		if ( is_string( $replacement ) && '' !== $replacement ) {
			$aliases[ mudrava_lucide_field_normalize_icon_token( (string) $old ) ] = $replacement;
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
function mudrava_lucide_field_parse_icon_value( string $icon_name ): array {
	$name = trim( $icon_name );

	if ( '' === $name || false !== strpbrk( $name, '<>"`' ) ) {
		return array(
			'source' => '',
			'name'   => '',
		);
	}

	$source = 'auto';

	if ( false !== strpos( $name, ':' ) ) {
		if ( preg_match( '/^(lucide|simple|brand|custom):(.+)$/i', $name, $matches ) ) {
			$source = strtolower( $matches[1] );
			$name   = $matches[2];

			if ( 'brand' === $source ) {
				$source = 'simple';
			}
		} else {
			return array(
				'source' => '',
				'name'   => '',
			);
		}
	}

	$name = mudrava_lucide_field_normalize_icon_token( sanitize_text_field( $name ) );

	if ( '' === $name ) {
		return array(
			'source' => '',
			'name'   => '',
		);
	}

	return array(
		'source' => $source,
		'name'   => $name,
	);
}

/**
 * Sanitize an icon value while preserving the Simple Icons source prefix.
 *
 * @since 1.1.0
 *
 * @param string $icon_name Icon value.
 * @return string Sanitized value.
 */
function mudrava_lucide_field_sanitize_icon_value( string $icon_name ): string {
	$parsed = mudrava_lucide_field_parse_icon_value( $icon_name );

	if ( '' === $parsed['name'] ) {
		return '';
	}

	if ( 'simple' === $parsed['source'] ) {
		return 'simple:' . $parsed['name'];
	}

	if ( 'lucide' === $parsed['source'] ) {
		return 'lucide:' . $parsed['name'];
	}

	if ( 'custom' === $parsed['source'] ) {
		return 'custom:' . $parsed['name'];
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
function mudrava_lucide_field_resolve_brand_slug( string $icon_name ): string {
	$icon_name = mudrava_lucide_field_normalize_icon_token( sanitize_text_field( $icon_name ) );

	if ( '' !== $icon_name && mudrava_lucide_field_symbol_exists( 'simple', $icon_name ) ) {
		return $icon_name;
	}

	$needle = mudrava_lucide_field_normalize_icon_token( $icon_name );

	if ( '' === $needle ) {
		return '';
	}

	$aliases = mudrava_lucide_field_get_brand_alias_map();

	return $aliases[ $needle ] ?? '';
}

/**
 * Resolve an icon value to a concrete bundled source and name.
 *
 * @since 1.1.0
 *
 * @param string $icon_name Icon value.
 * @return array{source:string,name:string}
 */
function mudrava_lucide_field_resolve_icon( string $icon_name ): array {
	$parsed = mudrava_lucide_field_parse_icon_value( $icon_name );

	if ( '' === $parsed['name'] ) {
		return array(
			'source' => '',
			'name'   => '',
		);
	}

	if ( 'simple' === $parsed['source'] ) {
		$brand_slug = mudrava_lucide_field_resolve_brand_slug( $parsed['name'] );

		return '' !== $brand_slug
			? array(
				'source' => 'simple',
				'name'   => $brand_slug,
			)
			: array(
				'source' => '',
				'name'   => '',
			);
	}

	if ( 'lucide' === $parsed['source'] ) {
		if ( mudrava_lucide_field_symbol_exists( 'lucide', $parsed['name'] ) ) {
			return array(
				'source' => 'lucide',
				'name'   => $parsed['name'],
			);
		}

		$alias = mudrava_lucide_field_get_compat_aliases();

		if ( isset( $alias[ $parsed['name'] ] ) && mudrava_lucide_field_symbol_exists( 'lucide', $alias[ $parsed['name'] ] ) ) {
			return array(
				'source' => 'lucide',
				'name'   => $alias[ $parsed['name'] ],
			);
		}

		return array(
			'source' => '',
			'name'   => '',
		);
	}

	if ( 'custom' === $parsed['source'] ) {
		if ( mudrava_lucide_field_symbol_exists( 'custom', $parsed['name'] ) ) {
			return array(
				'source' => 'custom',
				'name'   => $parsed['name'],
			);
		}

		return array(
			'source' => '',
			'name'   => '',
		);
	}

	if ( mudrava_lucide_field_symbol_exists( 'lucide', $parsed['name'] ) ) {
		return array(
			'source' => 'lucide',
			'name'   => $parsed['name'],
		);
	}

	$brand_slug = mudrava_lucide_field_resolve_brand_slug( $parsed['name'] );

	if ( '' !== $brand_slug ) {
		return array(
			'source' => 'simple',
			'name'   => $brand_slug,
		);
	}

	if ( mudrava_lucide_field_symbol_exists( 'custom', $parsed['name'] ) ) {
		return array(
			'source' => 'custom',
			'name'   => $parsed['name'],
		);
	}

	$alias = mudrava_lucide_field_get_compat_aliases();

	if ( isset( $alias[ $parsed['name'] ] ) && mudrava_lucide_field_symbol_exists( 'lucide', $alias[ $parsed['name'] ] ) ) {
		return array(
			'source' => 'lucide',
			'name'   => $alias[ $parsed['name'] ],
		);
	}

	return array(
		'source' => '',
		'name'   => '',
	);
}

/**
 * Determine whether an icon exists in the bundled local icon sets.
 *
 * @since 1.0.1
 *
 * @param string $icon_name Icon name.
 * @return bool
 */
function mudrava_lucide_field_icon_exists( string $icon_name ): bool {
	$resolved = mudrava_lucide_field_resolve_icon( $icon_name );

	return '' !== $resolved['source'] && '' !== $resolved['name'];
}

/**
 * Sanitize an SVG paint value (color keyword, hex or rgb()/rgba()).
 *
 * Anything else falls back to the given fallback. Output is additionally
 * esc_attr()'d by callers.
 *
 * @since 1.2.0
 *
 * @param string $value    Proposed value.
 * @param string $fallback Fallback when invalid.
 * @return string Safe paint value.
 */
function mudrava_lucide_field_sanitize_paint( string $value, string $fallback = 'currentColor' ): string {
	$value = trim( $value );

	if ( '' === $value ) {
		return $fallback;
	}

	$none_ok = 'none' === $fallback;

	if ( 'none' === $value ) {
		return $none_ok ? 'none' : $fallback;
	}

	if ( preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $value ) ) {
		return strtolower( $value );
	}

	if ( preg_match( '/^[A-Za-z][A-Za-z0-9]*$/', $value ) ) {
		return $value;
	}

	if ( preg_match( '/^rgba?\([\d\s.,%]+\)$/', $value ) ) {
		return $value;
	}

	return $fallback;
}

/**
 * Register the REST route used by the admin picker.
 *
 * @since 1.2.0
 *
 * @return void
 */
function mudrava_lucide_field_register_rest_route(): void {
	register_rest_route(
		'mudrava-lucide/v1',
		'/icons',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'permission_callback' => 'mudrava_lucide_field_icons_permission',
			'callback'            => 'mudrava_lucide_field_rest_icons',
		)
	);
}
add_action( 'rest_api_init', 'mudrava_lucide_field_register_rest_route' );

/**
 * Permission check for the icon data endpoint.
 *
 * @since 1.2.0
 *
 * @return bool
 */
function mudrava_lucide_field_icons_permission(): bool {
	/**
	 * Filters whether the current user may fetch the picker icon data.
	 *
	 * @since 1.2.0
	 *
	 * @param bool $allowed Whether the request is allowed. Default: logged in.
	 */
	return (bool) apply_filters( 'mudrava_lucide_field_icons_permission', is_user_logged_in() );
}

/**
 * REST callback returning search metadata and sprite URLs.
 *
 * Tag metadata only (no symbol markup) to keep the payload small.
 *
 * @since 1.2.0
 *
 * @return WP_REST_Response
 */
function mudrava_lucide_field_rest_icons(): WP_REST_Response {
	$allowed    = mudrava_lucide_field_allowed_svg_children();
	$attributes = array();

	foreach ( $allowed as $attrs ) {
		foreach ( array_keys( $attrs ) as $attr ) {
			$attributes[ $attr ] = true;
		}
	}

	$elements = array_keys( $allowed );
	sort( $elements );
	$attributes = array_keys( $attributes );
	sort( $attributes );

	$custom_icons   = array();
	$custom_symbols = array();

	foreach ( mudrava_lucide_field_get_custom_icons() as $custom_name => $custom_meta ) {
		$custom_icons[ $custom_name ] = array(
			'label'    => $custom_meta['title'],
			'keywords' => $custom_meta['keywords'],
		);

		$custom_symbols[ $custom_name ] = array(
			'inner'   => mudrava_lucide_field_get_symbol( 'custom', $custom_name ),
			'viewBox' => $custom_meta['viewBox'],
		);
	}

	$data = array(
		'version'           => MUDRAVA_LUCIDE_FIELD_VERSION,
		'allowedElements'   => $elements,
		'allowedAttributes' => $attributes,
		'compatAliases'     => (object) mudrava_lucide_field_get_compat_aliases(),
		'lucide'            => array(
			'libraryVersion' => MUDRAVA_LUCIDE_FIELD_LUCIDE_VERSION,
			'icons'          => (object) mudrava_lucide_field_get_lucide_tags(),
			'spriteUrl'      => MUDRAVA_LUCIDE_FIELD_URL . 'assets/sprite.svg',
		),
		'simple'            => array(
			'libraryVersion' => MUDRAVA_LUCIDE_FIELD_SIMPLE_ICONS_VERSION,
			'icons'          => (object) mudrava_lucide_field_get_brand_icon_tags(),
			'spriteUrl'      => MUDRAVA_LUCIDE_FIELD_URL . 'assets/brand-sprite.svg',
		),
		'custom'            => array(
			'icons'   => (object) $custom_icons,
			'symbols' => (object) $custom_symbols,
		),
	);

	/**
	 * Filters the icon data payload served to the admin picker.
	 *
	 * @since 1.2.0
	 *
	 * @param array $data Icon data payload.
	 */
	$data = apply_filters( 'mudrava_lucide_field_icon_data', $data );

	return rest_ensure_response( $data );
}

/**
 * Queue a symbol for output in the footer sprite.
 *
 * @since 1.2.0
 *
 * @param string $source Icon source ('lucide' or 'simple').
 * @param string $name   Symbol name.
 * @return void
 */
function mudrava_lucide_field_queue_symbol( string $source, string $name ): void {
	if ( ! isset( $GLOBALS['mudrava_lucide_used_symbols'] ) || ! is_array( $GLOBALS['mudrava_lucide_used_symbols'] ) ) {
		$GLOBALS['mudrava_lucide_used_symbols'] = array(
			'lucide' => array(),
			'simple' => array(),
		);
	}

	if ( isset( $GLOBALS['mudrava_lucide_used_symbols'][ $source ][ $name ] ) ) {
		return;
	}

	$GLOBALS['mudrava_lucide_used_symbols'][ $source ][ $name ] = true;

	if ( ! has_action( 'wp_footer', 'mudrava_lucide_field_print_sprite' ) ) {
		add_action( 'wp_footer', 'mudrava_lucide_field_print_sprite', 5 );
	}
}

/**
 * Print queued symbols as a hidden sprite in the footer.
 *
 * @since 1.2.0
 *
 * @return void
 */
function mudrava_lucide_field_print_sprite(): void {
	$used = isset( $GLOBALS['mudrava_lucide_used_symbols'] ) && is_array( $GLOBALS['mudrava_lucide_used_symbols'] )
		? $GLOBALS['mudrava_lucide_used_symbols']
		: array();

	$symbols = '';

	foreach ( array(
		'lucide' => '',
		'simple' => 'simple-',
		'custom' => 'custom-',
	) as $source => $prefix ) {
		if ( empty( $used[ $source ] ) || ! is_array( $used[ $source ] ) ) {
			continue;
		}

		foreach ( array_keys( $used[ $source ] ) as $name ) {
			$symbol = mudrava_lucide_field_get_symbol( $source, (string) $name );

			if ( '' === $symbol ) {
				continue;
			}

			$symbols .= '<symbol id="' . esc_attr( $prefix . $name ) . '" viewBox="' . esc_attr( mudrava_lucide_field_get_symbol_viewbox( $source, (string) $name ) ) . '">' . $symbol . '</symbol>';
		}
	}

	if ( '' === $symbols ) {
		return;
	}

	echo '<svg xmlns="http://www.w3.org/2000/svg" style="position:absolute;width:0;height:0;overflow:hidden" aria-hidden="true" focusable="false">' . $symbols . '</svg>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * Retrieves the SVG markup for a Lucide or Simple Icons brand icon.
 *
 * This helper can be used in templates to render an icon based on its
 * value. Returns an empty string if the icon cannot be retrieved.
 * Lucide icons use unprefixed values (e.g. 'rocket'); Simple Icons brand
 * icons use the 'simple:' prefix (e.g. 'simple:facebook').
 *
 * @since 1.0.0
 *
 * @param string $icon_name The icon value (e.g. 'rocket', 'simple:facebook').
 * @param array  $args      Optional. Arguments to customize the SVG output.
 *                          - 'class'        (string) Additional CSS classes.
 *                          - 'width'        (int)    Icon width in pixels. Default 24.
 *                          - 'height'       (int)    Icon height in pixels. Default 24.
 *                          - 'stroke'       (string) Stroke color. Default 'currentColor'.
 *                          - 'stroke_width' (int|float) Stroke width. Default 2.
 *                          - 'fill'         (string) Fill color for brand icons.
 *                                                   Default stroke color (Lucide: 'none').
 *                          - 'mode'         (string) 'inline' (default) embeds the symbol
 *                                                   markup; 'sprite' emits a <use> reference
 *                                                   and queues the symbol for the footer sprite.
 *                          - 'title'        (string) Optional accessible title
 *                                                   (replaces aria-hidden semantics).
 * @return string The SVG markup or empty string on failure.
 */
function mudrava_get_lucide_icon( string $icon_name, array $args = array() ): string {
	if ( '' === $icon_name ) {
		return '';
	}

	$defaults = array(
		'class'        => '',
		'width'        => 24,
		'height'       => 24,
		'stroke'       => 'currentColor',
		'stroke_width' => 2,
		'fill'         => '',
		'mode'         => 'inline',
		'title'        => '',
	);

	$args = wp_parse_args( $args, $defaults );

	/**
	 * Filters the arguments used when rendering an icon.
	 *
	 * @since 1.2.0
	 *
	 * @param array  $args      Render arguments.
	 * @param string $icon_name Raw icon value.
	 */
	$args = apply_filters( 'mudrava_lucide_icon_svg_args', $args, $icon_name );

	if ( ! is_array( $args ) ) {
		$args = $defaults;
	}

	$args = wp_parse_args( $args, $defaults );

	$resolved = mudrava_lucide_field_resolve_icon( $icon_name );

	if ( '' === $resolved['source'] || '' === $resolved['name'] ) {
		return '';
	}

	$is_brand  = 'simple' === $resolved['source'];
	$is_custom = 'custom' === $resolved['source'];

	$width        = max( 1, absint( $args['width'] ) );
	$height       = max( 1, absint( $args['height'] ) );
	$stroke_width = is_numeric( $args['stroke_width'] ) ? max( 0.0, (float) $args['stroke_width'] ) : 2.0;
	$stroke       = mudrava_lucide_field_sanitize_paint( (string) $args['stroke'] );
	$fill         = '' !== (string) $args['fill'] ? mudrava_lucide_field_sanitize_paint( (string) $args['fill'] ) : ( $is_brand ? $stroke : 'none' );

	$viewbox = '0 0 24 24';

	if ( $is_custom ) {
		// Custom icons ship their own paint in the stored markup: no fill or
		// stroke attributes are emitted on the wrapper at all.
		$paint = '';

		$viewbox = mudrava_lucide_field_get_symbol_viewbox( 'custom', $resolved['name'] );
	} elseif ( $is_brand ) {
		$paint = sprintf( ' fill="%s" stroke="none"', esc_attr( $fill ) );
	} else {
		$paint = sprintf( ' fill="none" stroke="%s" stroke-width="%s" stroke-linecap="round" stroke-linejoin="round"', esc_attr( $stroke ), esc_attr( (string) $stroke_width ) );
	}

	$variant_class = 'mudrava-lucide-icon-svg mudrava-lucide-icon-svg--' . ( $is_custom ? 'custom' : ( $is_brand ? 'brand' : 'lucide' ) );
	$user_class    = is_string( $args['class'] ) ? trim( $args['class'] ) : '';
	$class         = trim( $variant_class . ' ' . $user_class );
	$class_attr    = ' class="' . esc_attr( $class ) . '"';

	$title = is_string( $args['title'] ) ? trim( $args['title'] ) : '';

	if ( '' !== $title ) {
		$title_id     = 'mudrava-lucide-title-' . md5( $resolved['source'] . ':' . $resolved['name'] . '|' . $title );
		$title_attr   = ' role="img" aria-labelledby="' . esc_attr( $title_id ) . '"';
		$title_markup = '<title id="' . esc_attr( $title_id ) . '">' . esc_html( $title ) . '</title>';
	} else {
		$title_attr   = ' aria-hidden="true" focusable="false"';
		$title_markup = '';
	}

	if ( 'sprite' === $args['mode'] ) {
		mudrava_lucide_field_queue_symbol( $resolved['source'], $resolved['name'] );

		$prefix = $is_brand ? 'simple-' : ( $is_custom ? 'custom-' : '' );
		$use    = '<use href="#' . esc_attr( $prefix . $resolved['name'] ) . '"></use>';

		$svg = sprintf(
			'<svg%s xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="%s"%s%s>%s%s</svg>',
			$class_attr,
			$width,
			$height,
			esc_attr( $viewbox ),
			$paint,
			$title_attr,
			$title_markup,
			$use
		);

		/** This filter is documented in mudrava-acf-lucide-field.php */
		return (string) apply_filters( 'mudrava_lucide_icon_svg', $svg, $icon_name, $resolved, $args );
	}

	$symbol = mudrava_lucide_field_get_symbol( $resolved['source'], $resolved['name'] );

	if ( '' === $symbol ) {
		return '';
	}

	$svg = sprintf(
		'<svg%s xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="%s"%s%s>%s%s</svg>',
		$class_attr,
		$width,
		$height,
		esc_attr( $viewbox ),
		$paint,
		$title_attr,
		$title_markup,
		$symbol
	);

	/** This filter is documented in mudrava-acf-lucide-field.php */
	return (string) apply_filters( 'mudrava_lucide_icon_svg', $svg, $icon_name, $resolved, $args );
}

/**
 * Shortcode wrapper for the icon helper.
 *
 * [lucide_icon name="rocket" size="24" class="icon" mode="inline|sprite"
 *              stroke="#f00" fill="#00f" stroke_width="2" title="Rocket"]
 *
 * @since 1.2.0
 *
 * @param array|string $atts Shortcode attributes.
 * @return string SVG markup or empty string.
 */
function mudrava_lucide_field_shortcode_icon( $atts = array() ): string {
	$atts = shortcode_atts(
		array(
			'name'         => '',
			'size'         => '24',
			'width'        => '',
			'height'       => '',
			'class'        => '',
			'mode'         => 'inline',
			'stroke'       => 'currentColor',
			'fill'         => '',
			'stroke_width' => '2',
			'title'        => '',
		),
		is_array( $atts ) ? $atts : array(),
		'lucide_icon'
	);

	$name = trim( (string) $atts['name'] );

	if ( '' === $name ) {
		return '';
	}

	$size = is_numeric( $atts['size'] ) ? (int) $atts['size'] : 24;

	return mudrava_get_lucide_icon(
		$name,
		array(
			'class'        => (string) $atts['class'],
			'width'        => '' !== $atts['width'] && is_numeric( $atts['width'] ) ? (int) $atts['width'] : $size,
			'height'       => '' !== $atts['height'] && is_numeric( $atts['height'] ) ? (int) $atts['height'] : $size,
			'stroke'       => (string) $atts['stroke'],
			'stroke_width' => is_numeric( $atts['stroke_width'] ) ? (float) $atts['stroke_width'] : 2,
			'fill'         => (string) $atts['fill'],
			'mode'         => in_array( $atts['mode'], array( 'inline', 'sprite' ), true ) ? (string) $atts['mode'] : 'inline',
			'title'        => (string) $atts['title'],
		)
	);
}
add_shortcode( 'lucide_icon', 'mudrava_lucide_field_shortcode_icon' );

/**
 * Custom SVG icon library (site owner uploads).
 *
 * Uploaded files are parsed with DOMDocument (LIBXML_NONET), stripped down to
 * the allowlisted shape elements via wp_kses() and re-serialized — the raw
 * upload never reaches disk. Icons are addressable as "custom:<name>" through
 * the field, the picker and the shortcode.
 *
 * @since 1.2.0
 */

/**
 * Maximum accepted upload size, in bytes.
 */
define( 'MUDRAVA_LUCIDE_FIELD_MAX_UPLOAD_BYTES', 65536 );

/**
 * Name of the option holding the custom-icon manifest.
 *
 * @since 1.2.0
 */
define( 'MUDRAVA_LUCIDE_FIELD_CUSTOM_OPTION', 'mudrava_lucide_field_custom_icons' );

/**
 * Public capability for managing custom icons.
 *
 * Mirrors ACF's own capability setting and can be overridden by integrators.
 *
 * @since 1.2.0
 *
 * @return string
 */
function mudrava_lucide_field_upload_capability(): string {
	$cap = acf_get_setting( 'capability' );
	$cap = is_string( $cap ) && '' !== $cap ? $cap : 'manage_options';

	/**
	 * Filters the capability required to upload and delete custom icons.
	 *
	 * @since 1.2.0
	 *
	 * @param string $cap Capability name.
	 */
	return (string) apply_filters( 'mudrava_lucide_field_upload_permission', $cap );
}

/**
 * Filesystem path of the custom-icon storage directory.
 *
 * @since 1.2.0
 *
 * @return string
 */
function mudrava_lucide_field_custom_icons_dir(): string {
	$uploads = wp_upload_dir();
	$dir     = trailingslashit( $uploads['basedir'] ) . 'mudrava-lucide-icons';

	/**
	 * Filters the directory where sanitized custom icons are stored.
	 *
	 * @since 1.2.0
	 *
	 * @param string $dir Absolute filesystem path.
	 */
	return (string) apply_filters( 'mudrava_lucide_field_custom_icons_dir', $dir );
}

/**
 * Read the custom-icon manifest (name => title/keywords/viewBox), sanitized.
 *
 * @since 1.2.0
 *
 * @return array<string,array{title:string,keywords:array<int,string>,viewBox:string}>
 */
function mudrava_lucide_field_get_custom_icons(): array {
	$stored = get_option( MUDRAVA_LUCIDE_FIELD_CUSTOM_OPTION, array() );

	if ( ! is_array( $stored ) ) {
		return array();
	}

	$icons = array();

	foreach ( $stored as $name => $meta ) {
		if ( ! is_string( $name ) || ! preg_match( '/^[a-z0-9][a-z0-9_-]{0,63}$/', $name ) || ! is_array( $meta ) ) {
			continue;
		}

		$title = isset( $meta['title'] ) && is_string( $meta['title'] ) ? $meta['title'] : $name;

		$keywords = array();

		if ( isset( $meta['keywords'] ) && is_array( $meta['keywords'] ) ) {
			foreach ( $meta['keywords'] as $keyword ) {
				if ( ! is_string( $keyword ) ) {
					continue;
				}

				$keyword = trim( $keyword );

				if ( '' !== $keyword ) {
					$keywords[] = $keyword;
				}
			}
		}

		$viewbox = '0 0 24 24';

		if ( isset( $meta['viewBox'] ) && is_string( $meta['viewBox'] ) && preg_match( '/^-?[\d.]+(?:[\s,]+-?[\d.]+){3}$/', trim( $meta['viewBox'] ) ) ) {
			$viewbox = trim( $meta['viewBox'] );
		}

		$icons[ $name ] = array(
			'title'    => $title,
			'keywords' => $keywords,
			'viewBox'  => $viewbox,
		);
	}

	ksort( $icons );

	/**
	 * Filters the manifest of uploaded custom icons.
	 *
	 * @since 1.2.0
	 *
	 * @param array $icons name => { title, keywords, viewBox }.
	 */
	return (array) apply_filters( 'mudrava_lucide_field_custom_icons', $icons );
}

/**
 * Persist the custom-icon manifest.
 *
 * @since 1.2.0
 *
 * @param array<string,array> $icons Manifest keyed by icon name.
 * @return void
 */
function mudrava_lucide_field_set_custom_icons( array $icons ): void {
	$clean = array();

	foreach ( $icons as $name => $meta ) {
		if ( ! is_string( $name ) || ! preg_match( '/^[a-z0-9][a-z0-9_-]{0,63}$/', $name ) || ! is_array( $meta ) ) {
			continue;
		}

		$keywords = array();

		if ( isset( $meta['keywords'] ) && is_array( $meta['keywords'] ) ) {
			foreach ( $meta['keywords'] as $keyword ) {
				if ( ! is_string( $keyword ) ) {
					continue;
				}

				$keyword = trim( $keyword );

				if ( '' !== $keyword ) {
					$keywords[] = $keyword;
				}
			}
		}

		$viewbox = '0 0 24 24';

		if ( isset( $meta['viewBox'] ) && is_string( $meta['viewBox'] ) && preg_match( '/^-?[\d.]+(?:[\s,]+-?[\d.]+){3}$/', trim( $meta['viewBox'] ) ) ) {
			$viewbox = trim( $meta['viewBox'] );
		}

		$clean[ $name ] = array(
			'title'    => isset( $meta['title'] ) && is_string( $meta['title'] ) ? $meta['title'] : $name,
			'keywords' => $keywords,
			'viewBox'  => $viewbox,
		);
	}

	ksort( $clean );

	update_option( MUDRAVA_LUCIDE_FIELD_CUSTOM_OPTION, $clean, false );
}

/**
 * Sanitize a raw uploaded SVG into standalone markup.
 *
 * @since 1.2.0
 *
 * @param string $raw Uploaded file contents.
 * @return array{markup:string,viewBox:string}|null Null when the input is unusable.
 */
function mudrava_lucide_field_sanitize_custom_svg( string $raw ): ?array {
	if ( '' === $raw || strlen( $raw ) > MUDRAVA_LUCIDE_FIELD_MAX_UPLOAD_BYTES ) {
		return null;
	}

	$previous = libxml_use_internal_errors( true );

	$document = new DOMDocument();
	$loaded   = $document->loadXML( $raw, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING );

	libxml_clear_errors();
	libxml_use_internal_errors( $previous );

	if ( ! $loaded ) {
		return null;
	}

	$root = $document->documentElement; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM property.

	if ( ! $root instanceof DOMElement || 'svg' !== $root->localName ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM property.
		return null;
	}

	$viewbox = $root->getAttribute( 'viewBox' );

	if ( '' === $viewbox ) {
		$width  = trim( $root->getAttribute( 'width' ) );
		$height = trim( $root->getAttribute( 'height' ) );

		if ( '' !== $width && '' !== $height && is_numeric( $width ) && is_numeric( $height ) && (float) $width > 0.0 && (float) $height > 0.0 ) {
			$viewbox = sprintf( '0 0 %s %s', $width, $height );
		}
	}

	if ( '' !== $viewbox && ! preg_match( '/^-?[\d.]+(?:[\s,]+-?[\d.]+){3}$/', trim( $viewbox ) ) ) {
		$viewbox = '';
	}

	if ( '' === $viewbox ) {
		$viewbox = '0 0 24 24';
	}

	// Drop any structure that the shape allowlist cannot represent; a removed
	// wrapper would otherwise leak its children's text as bare nodes.
	foreach ( array( 'title', 'desc', 'metadata', 'defs', 'use', 'symbol', 'style', 'script', 'a', 'image', 'text', 'tspan', 'textPath', 'switch', 'filter', 'mask', 'clipPath' ) as $tag ) {
		$nodes = $document->getElementsByTagName( $tag );

		for ( $index = $nodes->length - 1; $index >= 0; $index-- ) {
			$node = $nodes->item( $index );

			if ( $node instanceof DOMElement && $node->parentNode instanceof DOMNode ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM property.
				$node->parentNode->removeChild( $node ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM property.
			}
		}
	}

	$inner = '';

	foreach ( $root->childNodes as $child ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM property.
		if ( $child instanceof DOMElement ) {
			$inner .= $document->saveXML( $child );
		}
	}

	$inner = wp_kses( $inner, mudrava_lucide_field_allowed_svg_children() );

	if ( '' === trim( $inner ) ) {
		return null;
	}

	$markup = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="' . esc_attr( $viewbox ) . '">' . $inner . '</svg>';

	return array(
		'markup'  => $markup,
		'viewBox' => $viewbox,
	);
}

/**
 * Full stored markup for a custom icon, with per-request memoization.
 *
 * @since 1.2.0
 *
 * @param string $name Custom icon name.
 * @return string Sanitized <svg> markup, empty string when missing.
 */
function mudrava_lucide_field_get_custom_icon_markup( string $name ): string {
	static $cache = array();

	if ( '' === $name ) {
		return '';
	}

	if ( isset( $cache[ $name ] ) ) {
		return $cache[ $name ];
	}

	$cache[ $name ] = '';

	$icons = mudrava_lucide_field_get_custom_icons();

	if ( ! isset( $icons[ $name ] ) ) {
		return '';
	}

	$filesystem = mudrava_lucide_field_filesystem();

	if ( ! $filesystem ) {
		return '';
	}

	$path = mudrava_lucide_field_custom_icons_dir() . '/' . $name . '.svg';

	if ( ! $filesystem->exists( $path ) ) {
		return '';
	}

	$markup = $filesystem->get_contents( $path );

	if ( ! is_string( $markup ) || '' === trim( $markup ) ) {
		return '';
	}

	// The stored file is full standalone markup; kses strips the root, so
	// extract the inner shape list first, then rebuild the wrapper from the
	// manifest viewBox.
	$body = trim( $markup );

	if ( preg_match( '#^<svg\b[^>]*>(.*)</svg>$#s', $body, $matches ) ) {
		$body = $matches[1];
	}

	$inner = wp_kses( $body, mudrava_lucide_field_allowed_svg_children() );

	if ( '' === trim( $inner ) ) {
		return '';
	}

	$cache[ $name ] = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="' . esc_attr( $icons[ $name ]['viewBox'] ) . '">' . $inner . '</svg>';

	return $cache[ $name ];
}

/**
 * Custom icon record with shape-only inner markup for sprite/inline use.
 *
 * @since 1.2.0
 *
 * @param string $name Custom icon name.
 * @return array{inner:string}
 */
function mudrava_lucide_field_get_custom_icon( string $name ): array {
	static $cache = array();

	if ( isset( $cache[ $name ] ) ) {
		return $cache[ $name ];
	}

	$markup = mudrava_lucide_field_get_custom_icon_markup( $name );
	$inner  = '';

	if ( '' !== $markup && preg_match( '#^<svg\b[^>]*>(.*)</svg>$#s', $markup, $matches ) ) {
		$inner = $matches[1];
	}

	$cache[ $name ] = array(
		'inner' => $inner,
	);

	return $cache[ $name ];
}

/**
 * viewBox of a custom icon, for symbol references and inline wrappers.
 *
 * @since 1.2.0
 *
 * @param string $source Icon source; only "custom" is meaningful here.
 * @param string $name   Icon name.
 * @return string
 */
function mudrava_lucide_field_get_symbol_viewbox( string $source, string $name ): string {
	if ( 'custom' !== $source ) {
		return '0 0 24 24';
	}

	$icons = mudrava_lucide_field_get_custom_icons();

	return isset( $icons[ $name ] ) ? $icons[ $name ]['viewBox'] : '0 0 24 24';
}

/**
 * Sanitize a user-supplied icon name.
 *
 * @since 1.2.0
 *
 * @param string $name Proposed name.
 * @return string Sanitized name, empty string when invalid.
 */
function mudrava_lucide_field_sanitize_icon_name( string $name ): string {
	$name = strtolower( sanitize_title( $name ) );

	return preg_match( '/^[a-z0-9][a-z0-9_-]{0,63}$/', $name ) ? $name : '';
}

/**
 * Store a freshly sanitized custom icon and update the manifest.
 *
 * @since 1.2.0
 *
 * @param string $name     Icon name.
 * @param string $markup   Sanitized standalone markup.
 * @param array  $meta     Manifest entry parts: title, keywords.
 * @param string $viewbox  viewBox values from the upload.
 * @return true|WP_Error
 */
function mudrava_lucide_field_store_custom_icon( string $name, string $markup, array $meta, string $viewbox ) {
	$filesystem = mudrava_lucide_field_filesystem();

	if ( ! $filesystem ) {
		return new WP_Error( 'mudrava_lucide_no_filesystem', __( 'The icon storage is not writable on this site.', 'mudrava-acf-lucide-field' ) );
	}

	$dir = mudrava_lucide_field_custom_icons_dir();

	if ( ! $filesystem->exists( $dir ) ) {
		wp_mkdir_p( $dir );

		if ( ! $filesystem->exists( $dir ) ) {
			return new WP_Error( 'mudrava_lucide_mkdir_failed', __( 'The icon storage directory could not be created.', 'mudrava-acf-lucide-field' ) );
		}

		$filesystem->put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n", FS_CHMOD_FILE );
		$filesystem->put_contents( $dir . '/.htaccess', "Require all denied\n", FS_CHMOD_FILE );
	}

	if ( ! $filesystem->put_contents( $dir . '/' . $name . '.svg', $markup, FS_CHMOD_FILE ) ) {
		return new WP_Error( 'mudrava_lucide_write_failed', __( 'The icon file could not be written.', 'mudrava-acf-lucide-field' ) );
	}

	$icons          = mudrava_lucide_field_get_custom_icons();
	$icons[ $name ] = array(
		'title'    => $meta['title'],
		'keywords' => $meta['keywords'],
		'viewBox'  => $viewbox,
	);
	mudrava_lucide_field_set_custom_icons( $icons );

	return true;
}

/**
 * Handle a custom-icon upload posted from the management page.
 *
 * @since 1.2.0
 *
 * @return void
 */
function mudrava_lucide_field_handle_upload() {
	if ( ! current_user_can( mudrava_lucide_field_upload_capability() ) ) {
		wp_die( esc_html__( 'You are not allowed to manage custom icons.', 'mudrava-acf-lucide-field' ) );
	}

	check_admin_referer( 'mudrava_lucide_upload_icon', 'mudrava_lucide_upload_nonce' );

	$redirect = admin_url( 'options-general.php?page=mudrava-lucide-icons' );

	$file = isset( $_FILES['mudrava_icon'] ) && is_array( $_FILES['mudrava_icon'] ) ? $_FILES['mudrava_icon'] : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- individual keys are sanitized before use.

	$status  = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
	$message = 'error';
	$name    = '';

	if ( UPLOAD_ERR_OK === $status ) {
		$size = isset( $file['size'] ) ? (int) $file['size'] : 0;

		if ( $size <= 0 || $size > MUDRAVA_LUCIDE_FIELD_MAX_UPLOAD_BYTES ) {
			$message = 'too_large';
		} else {
			$filesystem = mudrava_lucide_field_filesystem();
			$raw        = ! $filesystem ? false : $filesystem->get_contents( (string) $file['tmp_name'] );

			$sanitized = is_string( $raw ) ? mudrava_lucide_field_sanitize_custom_svg( $raw ) : null;

			if ( null === $sanitized ) {
				$message = 'invalid';
			} else {
				$title = isset( $_POST['icon_title'] ) ? sanitize_text_field( wp_unslash( $_POST['icon_title'] ) ) : '';

				if ( '' === $title ) {
					$title = pathinfo( sanitize_file_name( (string) $file['name'] ), PATHINFO_FILENAME );
				}

				$name = mudrava_lucide_field_sanitize_icon_name( $title );

				if ( '' === $name ) {
					$message = 'invalid';
				} else {
					$keywords_input = isset( $_POST['icon_keywords'] ) ? sanitize_text_field( wp_unslash( $_POST['icon_keywords'] ) ) : '';
					$keywords       = preg_split( '/[,;]+/', $keywords_input, -1, PREG_SPLIT_NO_EMPTY );
					$keywords       = is_array( $keywords ) ? array_values( array_filter( array_map( 'trim', $keywords ) ) ) : array();

					$icons = mudrava_lucide_field_get_custom_icons();

					if ( isset( $icons[ $name ] ) ) {
						$message = 'exists';
					} else {
						$result = mudrava_lucide_field_store_custom_icon(
							$name,
							$sanitized['markup'],
							array(
								'title'    => $title,
								'keywords' => $keywords,
							),
							$sanitized['viewBox']
						);

						$message = is_wp_error( $result ) ? 'error' : 'uploaded';
					}
				}
			}
		}
	}

	$redirect = add_query_arg( 'message', $message, $redirect );

	if ( '' !== $name ) {
		$redirect = add_query_arg( 'icon', rawurlencode( $name ), $redirect );
	}

	wp_safe_redirect( $redirect, 303 );
	exit;
}
add_action( 'admin_post_mudrava_lucide_upload_icon', 'mudrava_lucide_field_handle_upload' );

/**
 * Handle deletion of a custom icon.
 *
 * @since 1.2.0
 *
 * @return void
 */
function mudrava_lucide_field_handle_delete() {
	if ( ! current_user_can( mudrava_lucide_field_upload_capability() ) ) {
		wp_die( esc_html__( 'You are not allowed to manage custom icons.', 'mudrava-acf-lucide-field' ) );
	}

	check_admin_referer( 'mudrava_lucide_upload_icon', 'mudrava_lucide_upload_nonce' );

	$redirect = admin_url( 'options-general.php?page=mudrava-lucide-icons' );
	$name     = isset( $_POST['icon_name'] ) ? mudrava_lucide_field_sanitize_icon_name( sanitize_text_field( wp_unslash( $_POST['icon_name'] ) ) ) : '';
	$icons    = mudrava_lucide_field_get_custom_icons();
	$message  = 'not_found';

	if ( '' !== $name && isset( $icons[ $name ] ) ) {
		$filesystem = mudrava_lucide_field_filesystem();

		if ( ! $filesystem ) {
			$message = 'error';
		} else {
			$path = mudrava_lucide_field_custom_icons_dir() . '/' . $name . '.svg';

			if ( $filesystem->exists( $path ) && ! $filesystem->delete( $path ) ) {
				$message = 'error';
			} else {
				unset( $icons[ $name ] );
				mudrava_lucide_field_set_custom_icons( $icons );
				$message = 'deleted';
			}
		}
	}

	$redirect = add_query_arg( 'message', $message, $redirect );

	if ( '' !== $name ) {
		$redirect = add_query_arg( 'icon', rawurlencode( $name ), $redirect );
	}

	wp_safe_redirect( $redirect, 303 );
	exit;
}
add_action( 'admin_post_mudrava_lucide_delete_icon', 'mudrava_lucide_field_handle_delete' );

/**
 * Purge manifest entries whose files went missing (manual FTP removal).
 *
 * @since 1.2.0
 *
 * @return void
 */
function mudrava_lucide_field_maybe_prune_custom_icons() {
	if ( ! current_user_can( mudrava_lucide_field_upload_capability() ) ) {
		return;
	}

	$icons = mudrava_lucide_field_get_custom_icons();

	if ( array() === $icons ) {
		return;
	}

	$filesystem = mudrava_lucide_field_filesystem();

	if ( ! $filesystem ) {
		return;
	}

	$dir     = mudrava_lucide_field_custom_icons_dir();
	$missing = array();

	foreach ( $icons as $name => $meta ) {
		if ( ! $filesystem->exists( $dir . '/' . $name . '.svg' ) ) {
			$missing[] = $name;
		}
	}

	if ( array() !== $missing ) {
		foreach ( $missing as $name ) {
			unset( $icons[ $name ] );
		}

		mudrava_lucide_field_set_custom_icons( $icons );
	}
}

/**
 * Register the custom-icons management page under Settings.
 *
 * @since 1.2.0
 *
 * @return void
 */
function mudrava_lucide_field_register_custom_icons_page() {
	if ( ! current_user_can( mudrava_lucide_field_upload_capability() ) ) {
		return;
	}

	add_submenu_page(
		'options-general.php',
		__( 'Custom Icons', 'mudrava-acf-lucide-field' ),
		__( 'Custom Icons', 'mudrava-acf-lucide-field' ),
		mudrava_lucide_field_upload_capability(),
		'mudrava-lucide-icons',
		'mudrava_lucide_field_render_custom_icons_page'
	);
}
add_action( 'admin_menu', 'mudrava_lucide_field_register_custom_icons_page', 20 );

/**
 * Render the custom-icons management page.
 *
 * @since 1.2.0
 *
 * @return void
 */
function mudrava_lucide_field_render_custom_icons_page() {
	if ( ! current_user_can( mudrava_lucide_field_upload_capability() ) ) {
		wp_die( esc_html__( 'You are not allowed to manage custom icons.', 'mudrava-acf-lucide-field' ) );
	}

	mudrava_lucide_field_maybe_prune_custom_icons();

	$icons   = mudrava_lucide_field_get_custom_icons();
	$message = isset( $_GET['message'] ) ? sanitize_key( wp_unslash( $_GET['message'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only notice flag, sanitized.

	$notices = array(
		'uploaded'  => array(
			'updated',
			/* translators: %s: icon name. */
			__( 'Icon <strong>%s</strong> was added to the library.', 'mudrava-acf-lucide-field' ),
		),
		'deleted'   => array(
			'updated',
			/* translators: %s: icon name. */
			__( 'Icon <strong>%s</strong> was deleted.', 'mudrava-acf-lucide-field' ),
		),
		'invalid'   => array(
			'error',
			__( 'Mudrava Icon Field: that file is not a valid SVG document. Only plain shape elements (circle, ellipse, line, path, polygon, polyline, rect) inside an &lt;svg&gt; root are accepted.', 'mudrava-acf-lucide-field' ),
		),
		'too_large' => array(
			'error',
			/* translators: %s: maximum file size. */
			sprintf( /* translators: %s: maximum file size. */ __( 'Mudrava Icon Field: that file is too large. The limit is %s.', 'mudrava-acf-lucide-field' ), esc_html( size_format( MUDRAVA_LUCIDE_FIELD_MAX_UPLOAD_BYTES ) ) ),
		),
		'exists'    => array(
			'error',
			__( 'Mudrava Icon Field: an icon with that name already exists. Uploads never overwrite stored icons; delete the old icon first or use another name.', 'mudrava-acf-lucide-field' ),
		),
		'not_found' => array(
			'error',
			__( 'Mudrava Icon Field: that icon no longer exists in the library.', 'mudrava-acf-lucide-field' ),
		),
		'error'     => array(
			'error',
			__( 'Mudrava Icon Field: the icon storage is not writable. Check filesystem permissions.', 'mudrava-acf-lucide-field' ),
		),
	);

	echo '<div class="wrap"><h1>' . esc_html__( 'Mudrava Icon Field — Custom Icons', 'mudrava-acf-lucide-field' ) . '</h1>';

	if ( '' !== $message && isset( $notices[ $message ] ) ) {
		list( $class, $text ) = $notices[ $message ];

		if ( false !== strpos( $text, '%s' ) ) {
			$icon_name = isset( $_GET['icon'] ) ? sanitize_text_field( wp_unslash( $_GET['icon'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only label, escaped below.

			$text = sprintf( $text, esc_html( $icon_name ) );
		}

		$notice_class = 'updated' === $class ? 'notice-success' : 'notice-error';

		echo '<div class="notice ' . esc_attr( $notice_class ) . ' is-dismissible"><p>' . wp_kses( $text, array( 'strong' => array() ) ) . '</p></div>';
	}

	echo '<p>' . esc_html__( 'Upload plain SVG icons to extend the picker with a custom set. Files are sanitized on upload: scripts, styles, embedded images and unsupported elements are removed, and only the allowlisted shape markup is stored.', 'mudrava-acf-lucide-field' ) . '</p>';
	echo '<p>' . sprintf(
		/* translators: %s: icon size limit. */
		esc_html__( 'Accepted format: SVG with an &lt;svg&gt; root and a viewBox. Maximum size: %s. Icons are available in the picker and shortcodes as custom:&lt;name&gt;.', 'mudrava-acf-lucide-field' ),
		esc_html( size_format( MUDRAVA_LUCIDE_FIELD_MAX_UPLOAD_BYTES ) )
	) . '</p>';

	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" enctype="multipart/form-data">';
	wp_nonce_field( 'mudrava_lucide_upload_icon', 'mudrava_lucide_upload_nonce' );
	echo '<input type="hidden" name="action" value="mudrava_lucide_upload_icon">';
	echo '<table class="form-table" role="presentation"><tbody>';
	echo '<tr><th scope="row"><label for="mudrava-icon-file">' . esc_html__( 'SVG file', 'mudrava-acf-lucide-field' ) . '</label></th><td><input type="file" accept=".svg,image/svg+xml" id="mudrava-icon-file" name="mudrava_icon" required></td></tr>';
	echo '<tr><th scope="row"><label for="mudrava-icon-name">' . esc_html__( 'Name', 'mudrava-acf-lucide-field' ) . '</label></th><td><input type="text" class="regular-text" id="mudrava-icon-name" name="icon_title" placeholder="' . esc_attr__( 'Optional; defaults to the file name', 'mudrava-acf-lucide-field' ) . '"><p class="description">' . esc_html__( 'The storage name is derived from this title: lowercase, dashes, max 64 characters.', 'mudrava-acf-lucide-field' ) . '</p></td></tr>';
	echo '<tr><th scope="row"><label for="mudrava-icon-keywords">' . esc_html__( 'Search keywords', 'mudrava-acf-lucide-field' ) . '</label></th><td><input type="text" class="regular-text" id="mudrava-icon-keywords" name="icon_keywords" placeholder="' . esc_attr__( 'logo, company, brand', 'mudrava-acf-lucide-field' ) . '"><p class="description">' . esc_html__( 'Comma-separated terms that make the icon findable in the picker search.', 'mudrava-acf-lucide-field' ) . '</p></td></tr>';
	echo '</tbody></table>';
	submit_button( __( 'Add icon', 'mudrava-acf-lucide-field' ), 'primary', 'mudrava_lucide_upload_submit', true );
	echo '</form>';

	if ( array() !== $icons ) {
		echo '<hr><h2>' . esc_html__( 'Stored icons', 'mudrava-acf-lucide-field' ) . '</h2>';
		echo '<style>.mudrava-lucide-icon-preview{display:inline-flex;width:32px;height:32px;align-items:center;justify-content:center;background:#fff;border:1px solid #dcdcde;border-radius:4px}.mudrava-lucide-icon-preview svg{width:20px;height:20px;display:block}</style>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Name', 'mudrava-acf-lucide-field' ) . '</th><th>' . esc_html__( 'Preview', 'mudrava-acf-lucide-field' ) . '</th><th>' . esc_html__( 'Title', 'mudrava-acf-lucide-field' ) . '</th><th>' . esc_html__( 'Keywords', 'mudrava-acf-lucide-field' ) . '</th><th>' . esc_html__( 'Value', 'mudrava-acf-lucide-field' ) . '</th><th>' . esc_html__( 'Actions', 'mudrava-acf-lucide-field' ) . '</th></tr></thead><tbody>';

		foreach ( $icons as $name => $meta ) {
			$markup = mudrava_lucide_field_get_custom_icon_markup( $name );

			echo '<tr><td><code>' . esc_html( $name ) . '</code></td><td>';

			if ( '' !== $markup ) {
				echo '<span class="mudrava-lucide-icon-preview" aria-hidden="true">' . $markup . '</span>';
			} else {
				echo '<span class="dashicons dashicons-format-image" aria-hidden="true"></span>';
			}

			echo '</td><td>' . esc_html( $meta['title'] ) . '</td><td>' . esc_html( implode( ', ', $meta['keywords'] ) ) . '</td><td><code>custom:' . esc_html( $name ) . '</code></td><td>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'' . esc_js( __( 'Delete this icon? Existing fields will fall back to their default behavior.', 'mudrava-acf-lucide-field' ) ) . '\');">';
			wp_nonce_field( 'mudrava_lucide_upload_icon', 'mudrava_lucide_upload_nonce' );
			echo '<input type="hidden" name="action" value="mudrava_lucide_delete_icon">';
			echo '<input type="hidden" name="icon_name" value="' . esc_attr( $name ) . '">';
			echo '<button type="submit" class="button button-small button-link-delete">' . esc_html__( 'Delete', 'mudrava-acf-lucide-field' ) . '</button>';
			echo '</form></td></tr>';
		}

		echo '</tbody></table>';
	} else {
		echo '<p><em>' . esc_html__( 'No custom icons yet.', 'mudrava-acf-lucide-field' ) . '</em></p>';
	}

	echo '</div>';
}
