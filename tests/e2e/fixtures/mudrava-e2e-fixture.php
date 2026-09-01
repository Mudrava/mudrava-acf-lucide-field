<?php
/**
 * E2E fixture: local field group + seed posts with legacy/unknown values.
 *
 * Mirrored in the test site's wp-content/mu-plugins/. IDs are exposed via
 * REST slugs so the Playwright suite never hardcodes IDs.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'use_block_editor_for_post', '__return_false' );
add_filter( 'use_block_editor_for_post_type', '__return_false' );

add_action(
	'acf/init',
	static function () {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		acf_add_local_field_group(
			array(
				'key'        => 'group_e2e_icons',
				'title'      => 'E2E Icons',
				'menu_order' => 0,
				'fields'     => array(
					array(
						'key'           => 'field_icon',
						'label'         => 'Feature Icon',
						'name'          => 'icon',
						'type'          => 'lucide_icon',
						'allow_null'    => 1,
						'ui'            => 1,
						'return_format' => 'name',
						'placeholder'   => 'Search icons…',
					),
					array(
						'key'           => 'field_badge',
						'label'         => 'Brand Badge',
						'name'          => 'badge',
						'type'          => 'lucide_icon',
						'allow_null'    => 1,
						'ui'            => 1,
						'return_format' => 'svg',
					),
					array(
						'key'           => 'field_strict',
						'label'         => 'Strict Icon',
						'name'          => 'strict_icon',
						'type'          => 'lucide_icon',
						'allow_null'    => 1,
						'ui'            => 1,
						'on_unknown'    => 'error',
						'return_format' => 'name',
					),
				),
				'location'   => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'post',
						),
					),
				),
			)
		);
	}
);

add_action(
	'init',
	static function () {
		if ( get_option( 'mudrava_e2e_seeded' ) ) {
			return;
		}

		$cases = array(
			'e2e-rocket'       => array( 'E2E rocket', 'rocket' ),
			'e2e-legacy-smile' => array( 'E2E legacy smile', 'smile' ),
			'e2e-legacy-history' => array( 'E2E legacy history', 'history' ),
			'e2e-simple-brand' => array( 'E2E simple brand', 'simple:facebook' ),
			'e2e-legacy-brand' => array( 'E2E legacy brand', 'youtube' ),
			'e2e-unknown'      => array( 'E2E unknown', 'totally-removed-icon' ),
			'e2e-prefixed'     => array( 'E2E prefixed', 'lucide:arrow-left' ),
		);

		foreach ( $cases as $slug => $case ) {
			$post_id = wp_insert_post(
				array(
					'post_type'    => 'post',
					'post_status'  => 'publish',
					'post_name'    => $slug,
					'post_title'   => $case[0],
					'post_content' => '[lucide_icon name="' . $case[1] . '" size="32"]',
				)
			);

			if ( $post_id && ! is_wp_error( $post_id ) ) {
				update_post_meta( $post_id, 'icon', $case[1] );
				update_post_meta( $post_id, '_icon', 'field_icon' );
				update_post_meta( $post_id, 'badge', 'simple:github' );
				update_post_meta( $post_id, '_badge', 'field_badge' );
			}
		}

		$strict_id = wp_insert_post(
			array(
				'post_type'    => 'post',
				'post_status'  => 'publish',
				'post_name'    => 'e2e-strict-unknown',
				'post_title'   => 'E2E strict unknown',
				'post_content' => 'strict',
			)
		);

		if ( $strict_id && ! is_wp_error( $strict_id ) ) {
			update_post_meta( $strict_id, 'icon', 'rocket' );
			update_post_meta( $strict_id, '_icon', 'field_icon' );
			update_post_meta( $strict_id, 'strict_icon', 'totally-removed-icon' );
			update_post_meta( $strict_id, '_strict_icon', 'field_strict' );
		}

		wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_name'    => 'e2e-shortcodes',
				'post_title'   => 'E2E Shortcodes',
				'post_content' => '<p>' .
					'[lucide_icon name="rocket" size="32" title="Launch"] ' .
					'[lucide_icon name="smile"] ' .
					'[lucide_icon name="simple:github" fill="#24292f"] ' .
					'[lucide_icon name="rocket" mode="sprite"]' .
					'</p>',
			)
		);

		update_option( 'mudrava_e2e_seeded', 1 );
	},
	30
);
