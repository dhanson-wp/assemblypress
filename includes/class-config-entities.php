<?php
/**
 * Internal configuration entities.
 *
 * @package AssemblyPress
 */

namespace AssemblyPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers private configuration entities for fields, groups, and forms.
 */
final class Config_Entities {
	const FIELD_GROUP_POST_TYPE = 'ap_profile_field_grp';
	const FIELD_POST_TYPE       = 'ap_profile_field';
	const FORM_POST_TYPE        = 'ap_profile_form';

	/**
	 * Register internal post types.
	 *
	 * @return void
	 */
	public static function register() {
		self::register_config_type(
			self::FIELD_GROUP_POST_TYPE,
			__( 'Profile Field Groups', 'assemblypress' ),
			__( 'Profile Field Group', 'assemblypress' ),
			'profile-field-groups'
		);

		self::register_config_type(
			self::FIELD_POST_TYPE,
			__( 'Profile Fields', 'assemblypress' ),
			__( 'Profile Field', 'assemblypress' ),
			'profile-fields'
		);

		self::register_config_type(
			self::FORM_POST_TYPE,
			__( 'Profile Forms', 'assemblypress' ),
			__( 'Profile Form', 'assemblypress' ),
			'profile-forms'
		);
	}

	/**
	 * Register a single private configuration post type.
	 *
	 * @param string $post_type Post type key.
	 * @param string $plural    Plural label.
	 * @param string $singular  Singular label.
	 * @param string $rest_base REST base.
	 * @return void
	 */
	private static function register_config_type( $post_type, $plural, $singular, $rest_base ) {
		register_post_type(
			$post_type,
			array(
				'labels'                => array(
					'name'          => $plural,
					'singular_name' => $singular,
				),
				'public'                => false,
				'publicly_queryable'    => false,
				'exclude_from_search'   => true,
				'show_ui'               => false,
				'show_in_menu'          => false,
				'show_in_rest'          => true,
				'rest_base'             => $rest_base,
				'supports'              => array( 'title', 'revisions' ),
				'capability_type'       => 'post',
				'map_meta_cap'          => true,
				'delete_with_user'      => false,
				'rest_controller_class' => 'WP_REST_Posts_Controller',
			)
		);
	}
}
