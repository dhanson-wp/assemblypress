<?php
/**
 * AssemblyPress REST API.
 *
 * @package AssemblyPress
 */

namespace AssemblyPress;

use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST route registration.
 */
final class Rest_Controller {
	const NAMESPACE = 'assemblypress/v1';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/profile-fields',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'get_profile_fields' ),
					'permission_callback' => array( self::class, 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( self::class, 'create_profile_field' ),
					'permission_callback' => array( self::class, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/profile-fields/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( self::class, 'update_profile_field' ),
					'permission_callback' => array( self::class, 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( self::class, 'delete_profile_field' ),
					'permission_callback' => array( self::class, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/profile-field-groups',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'get_profile_field_groups' ),
				'permission_callback' => array( self::class, 'can_manage' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/profile-forms',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'get_profile_forms' ),
					'permission_callback' => array( self::class, 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( self::class, 'create_profile_form' ),
					'permission_callback' => array( self::class, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/members',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'get_members' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/members/(?P<id>[\d]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'get_member' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/me/profile',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'get_my_profile' ),
					'permission_callback' => 'is_user_logged_in',
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( self::class, 'update_my_profile' ),
					'permission_callback' => 'is_user_logged_in',
				),
			)
		);
	}

	/**
	 * Check management capability.
	 *
	 * @return bool
	 */
	public static function can_manage() {
		return current_user_can( 'list_users' );
	}

	/**
	 * Return profile fields.
	 *
	 * @return array
	 */
	public static function get_profile_fields() {
		return Profile_Fields::get_fields();
	}

	/**
	 * Create a profile field.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	public static function create_profile_field( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		return Profile_Fields::create_field( $params ? $params : array() );
	}

	/**
	 * Update a profile field.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	public static function update_profile_field( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		return Profile_Fields::update_field( (int) $request['id'], $params ? $params : array() );
	}

	/**
	 * Delete a profile field.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	public static function delete_profile_field( WP_REST_Request $request ) {
		return Profile_Fields::delete_field( (int) $request['id'] );
	}

	/**
	 * Return profile field groups.
	 *
	 * @return array
	 */
	public static function get_profile_field_groups() {
		return self::get_config_posts( Config_Entities::FIELD_GROUP_POST_TYPE );
	}

	/**
	 * Return profile forms.
	 *
	 * @return array
	 */
	public static function get_profile_forms() {
		return self::get_config_posts( Config_Entities::FORM_POST_TYPE );
	}

	/**
	 * Create a profile form.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	public static function create_profile_form( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		$params = $params ? $params : array();
		$title  = sanitize_text_field( $params['title'] ?? __( 'Profile Form', 'assemblypress' ) );
		$key    = sanitize_key( $params['key'] ?? $title );
		$config = array(
			'key'         => $key,
			'type'        => sanitize_key( $params['type'] ?? 'profile_edit' ),
			'field_keys'  => array_map( 'sanitize_key', $params['field_keys'] ?? array() ),
			'submit_text' => sanitize_text_field( $params['submit_text'] ?? __( 'Save profile', 'assemblypress' ) ),
		);

		$post_id = wp_insert_post(
			array(
				'post_type'    => Config_Entities::FORM_POST_TYPE,
				'post_title'   => $title,
				'post_name'    => $key,
				'post_status'  => 'publish',
				'post_content' => wp_json_encode( $config ),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		return array(
			'id'     => $post_id,
			'title'  => $title,
			'key'    => $key,
			'config' => $config,
		);
	}

	/**
	 * Return public member directory records.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	public static function get_members( WP_REST_Request $request ) {
		$requested_per_page = (int) $request->get_param( 'per_page' );
		$requested_page     = (int) $request->get_param( 'page' );
		$requested_search   = $request->get_param( 'search' );
		$per_page           = min( 100, max( 1, $requested_per_page ? $requested_per_page : 20 ) );
		$page               = max( 1, $requested_page ? $requested_page : 1 );
		$search             = sanitize_text_field( $requested_search ? $requested_search : '' );

		$query = new \WP_User_Query(
			array(
				'number'  => $per_page,
				'paged'   => $page,
				'search'  => $search ? '*' . $search . '*' : '',
				'orderby' => 'display_name',
				'order'   => 'ASC',
				'fields'  => 'all',
			)
		);

		return array(
			'members'    => array_map(
				static function ( $user ) {
					return Profile_Fields::get_member_profile( $user->ID, current_user_can( 'edit_user', $user->ID ) );
				},
				$query->get_results()
			),
			'total'      => (int) $query->get_total(),
			'totalPages' => (int) ceil( $query->get_total() / $per_page ),
		);
	}

	/**
	 * Return a public member profile.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	public static function get_member( WP_REST_Request $request ) {
		$user_id = (int) $request['id'];
		return Profile_Fields::get_member_profile( $user_id, current_user_can( 'edit_user', $user_id ) );
	}

	/**
	 * Return current user's profile.
	 *
	 * @return array|\WP_Error
	 */
	public static function get_my_profile() {
		return Profile_Fields::get_member_profile( get_current_user_id(), true );
	}

	/**
	 * Update current user's profile.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	public static function update_my_profile( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		$params = $params ? $params : array();
		return Profile_Fields::update_member_profile( get_current_user_id(), $params['fields'] ?? $params );
	}

	/**
	 * Format internal config posts.
	 *
	 * @param string $post_type Post type.
	 * @return array
	 */
	private static function get_config_posts( $post_type ) {
		$posts = get_posts(
			array(
				'post_type'      => $post_type,
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'orderby'        => 'menu_order title',
				'order'          => 'ASC',
			)
		);

		return array_map(
			static function ( $post ) {
				return array(
					'id'     => $post->ID,
					'title'  => $post->post_title,
					'key'    => $post->post_name,
					'config' => json_decode( $post->post_content, true ) ? json_decode( $post->post_content, true ) : array(),
				);
			},
			$posts
		);
	}
}
