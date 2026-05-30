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
					'args'                => self::profile_field_args( true ),
				),
				'schema' => array( self::class, 'get_profile_field_schema' ),
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
					'args'                => self::profile_field_args(),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( self::class, 'delete_profile_field' ),
					'permission_callback' => array( self::class, 'can_manage' ),
				),
				'schema' => array( self::class, 'get_profile_field_schema' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/profile-field-groups',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'get_profile_field_groups' ),
					'permission_callback' => array( self::class, 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( self::class, 'create_profile_field_group' ),
					'permission_callback' => array( self::class, 'can_manage' ),
					'args'                => self::profile_field_group_args( true ),
				),
				'schema' => array( self::class, 'get_profile_field_group_schema' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/profile-field-groups/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( self::class, 'update_profile_field_group' ),
					'permission_callback' => array( self::class, 'can_manage' ),
					'args'                => self::profile_field_group_args(),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( self::class, 'delete_profile_field_group' ),
					'permission_callback' => array( self::class, 'can_manage' ),
				),
				'schema' => array( self::class, 'get_profile_field_group_schema' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/members',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'get_members' ),
					'permission_callback' => '__return_true',
					'args'                => self::members_collection_args(),
				),
				'schema' => array( self::class, 'get_members_collection_schema' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/members/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'get_member' ),
					'permission_callback' => '__return_true',
				),
				'schema' => array( self::class, 'get_member_profile_schema' ),
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
					'args'                => self::me_profile_args(),
				),
				'schema' => array( self::class, 'get_member_profile_schema' ),
			)
		);
	}

	/**
	 * Check management capability.
	 *
	 * @return bool
	 */
	public static function can_manage() {
		return Capabilities::current_user_can_manage_profile_schema();
	}

	/**
	 * Profile field schema.
	 *
	 * @return array
	 */
	public static function get_profile_field_schema() {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'assemblypress_profile_field',
			'type'       => 'object',
			'properties' => array(
				'id'          => array(
					'type' => 'integer',
				),
				'key'         => array(
					'type' => 'string',
				),
				'label'       => array(
					'type' => 'string',
				),
				'type'        => array(
					'type' => 'string',
					'enum' => Profile_Fields::supported_types(),
				),
				'source'      => array(
					'type' => 'string',
					'enum' => array( 'user', 'meta' ),
				),
				'user_key'    => array(
					'type' => 'string',
				),
				'meta_key'    => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'type' => 'string',
				),
				'visibility'  => array(
					'type' => 'string',
					'enum' => array( 'public', 'private' ),
				),
				'editable'    => array(
					'type' => 'boolean',
				),
				'required'    => array(
					'type' => 'boolean',
				),
				'description' => array(
					'type' => 'string',
				),
				'order'       => array(
					'type' => 'integer',
				),
				'group_id'    => array(
					'type' => 'integer',
				),
				'options'     => array(
					'type'  => 'array',
					'items' => self::get_profile_field_option_schema(),
				),
				'is_system'   => array(
					'type' => 'boolean',
				),
				'value'       => array(
					'type' => array( 'string', 'boolean', 'array', 'integer', 'null' ),
				),
			),
		);
	}

	/**
	 * Profile field option schema.
	 *
	 * @return array
	 */
	private static function get_profile_field_option_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'value' => array(
					'type' => 'string',
				),
				'label' => array(
					'type' => 'string',
				),
			),
		);
	}

	/**
	 * Profile field group schema.
	 *
	 * @return array
	 */
	public static function get_profile_field_group_schema() {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'assemblypress_profile_field_group',
			'type'       => 'object',
			'properties' => array(
				'id'          => array(
					'type' => 'integer',
				),
				'key'         => array(
					'type' => 'string',
				),
				'label'       => array(
					'type' => 'string',
				),
				'description' => array(
					'type' => 'string',
				),
				'order'       => array(
					'type' => 'integer',
				),
			),
		);
	}

	/**
	 * Grouped member field group schema.
	 *
	 * @return array
	 */
	public static function get_grouped_field_group_schema() {
		$schema                         = self::get_profile_field_group_schema();
		$schema['title']                = 'assemblypress_grouped_profile_fields';
		$schema['properties']['fields'] = array(
			'type'  => 'array',
			'items' => self::get_profile_field_schema(),
		);

		return $schema;
	}

	/**
	 * Member profile response schema.
	 *
	 * @return array
	 */
	public static function get_member_profile_schema() {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'assemblypress_member_profile',
			'type'       => 'object',
			'properties' => array(
				'id'           => array(
					'type' => 'integer',
				),
				'display_name' => array(
					'type' => 'string',
				),
				'avatar_url'   => array(
					'type' => 'string',
				),
				'profile_url'  => array(
					'type' => 'string',
				),
				'fields'       => array(
					'type'  => 'array',
					'items' => self::get_profile_field_schema(),
				),
				'groups'       => array(
					'type'  => 'array',
					'items' => self::get_grouped_field_group_schema(),
				),
			),
		);
	}

	/**
	 * Member collection wrapper schema.
	 *
	 * @return array
	 */
	public static function get_members_collection_schema() {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'assemblypress_members_collection',
			'type'       => 'object',
			'properties' => array(
				'members'    => array(
					'type'  => 'array',
					'items' => self::get_member_profile_schema(),
				),
				'total'      => array(
					'type' => 'integer',
				),
				'totalPages' => array(
					'type' => 'integer',
				),
			),
		);
	}

	/**
	 * Members collection query args.
	 *
	 * @return array
	 */
	private static function members_collection_args() {
		return array(
			'per_page' => array(
				'type'              => 'integer',
				'default'           => 20,
				'minimum'           => 1,
				'maximum'           => 100,
				'validate_callback' => array( self::class, 'validate_schema_arg' ),
				'sanitize_callback' => 'absint',
			),
			'page'     => array(
				'type'              => 'integer',
				'default'           => 1,
				'minimum'           => 1,
				'validate_callback' => array( self::class, 'validate_schema_arg' ),
				'sanitize_callback' => 'absint',
			),
			'search'   => array(
				'type'              => 'string',
				'default'           => '',
				'validate_callback' => array( self::class, 'validate_schema_arg' ),
				'sanitize_callback' => array( self::class, 'sanitize_text_arg' ),
			),
		);
	}

	/**
	 * Current-member profile mutation args.
	 *
	 * @return array
	 */
	private static function me_profile_args() {
		return array(
			'fields' => array(
				'type'              => 'object',
				'validate_callback' => array( self::class, 'validate_profile_values_arg' ),
			),
		);
	}

	/**
	 * Profile field request args.
	 *
	 * @param bool $create Whether the args are for create requests.
	 * @return array
	 */
	private static function profile_field_args( $create = false ) {
		return array(
			'key'         => array(
				'type'              => 'string',
				'required'          => $create,
				'validate_callback' => array( self::class, 'validate_key_arg' ),
				'sanitize_callback' => array( self::class, 'sanitize_key_arg' ),
			),
			'label'       => array(
				'type'              => 'string',
				'required'          => $create,
				'validate_callback' => array( self::class, 'validate_required_text_arg' ),
				'sanitize_callback' => array( self::class, 'sanitize_text_arg' ),
			),
			'type'        => array(
				'type'              => 'string',
				'enum'              => Profile_Fields::supported_types(),
				'required'          => $create,
				'validate_callback' => array( self::class, 'validate_schema_arg' ),
				'sanitize_callback' => array( self::class, 'sanitize_key_arg' ),
			),
			'visibility'  => array(
				'type'              => 'string',
				'enum'              => array( 'public', 'private' ),
				'required'          => $create,
				'validate_callback' => array( self::class, 'validate_schema_arg' ),
				'sanitize_callback' => array( self::class, 'sanitize_key_arg' ),
			),
			'editable'    => array(
				'type'              => 'boolean',
				'validate_callback' => array( self::class, 'validate_schema_arg' ),
			),
			'required'    => array(
				'type'              => 'boolean',
				'validate_callback' => array( self::class, 'validate_schema_arg' ),
			),
			'description' => array(
				'type'              => 'string',
				'validate_callback' => array( self::class, 'validate_schema_arg' ),
				'sanitize_callback' => array( self::class, 'sanitize_text_arg' ),
			),
			'order'       => array(
				'type'              => 'integer',
				'minimum'           => 0,
				'validate_callback' => array( self::class, 'validate_schema_arg' ),
				'sanitize_callback' => 'absint',
			),
			'group_id'    => array(
				'type'              => 'integer',
				'minimum'           => 1,
				'validate_callback' => array( self::class, 'validate_profile_field_group_id_arg' ),
				'sanitize_callback' => 'absint',
			),
			'options'     => array(
				'type'              => 'array',
				'items'             => array(
					'type'                 => 'object',
					'required'             => array( 'value', 'label' ),
					'properties'           => array(
						'value' => array(
							'type' => 'string',
						),
						'label' => array(
							'type' => 'string',
						),
					),
					'additionalProperties' => false,
				),
				'validate_callback' => array( self::class, 'validate_profile_field_options_arg' ),
				'sanitize_callback' => array( self::class, 'sanitize_profile_field_options_arg' ),
			),
		);
	}

	/**
	 * Profile field group request args.
	 *
	 * @param bool $create Whether the args are for create requests.
	 * @return array
	 */
	private static function profile_field_group_args( $create = false ) {
		return array(
			'key'         => array(
				'type'              => 'string',
				'required'          => $create,
				'validate_callback' => array( self::class, 'validate_key_arg' ),
				'sanitize_callback' => array( self::class, 'sanitize_key_arg' ),
			),
			'label'       => array(
				'type'              => 'string',
				'required'          => $create,
				'validate_callback' => array( self::class, 'validate_required_text_arg' ),
				'sanitize_callback' => array( self::class, 'sanitize_text_arg' ),
			),
			'description' => array(
				'type'              => 'string',
				'validate_callback' => array( self::class, 'validate_schema_arg' ),
				'sanitize_callback' => array( self::class, 'sanitize_text_arg' ),
			),
			'order'       => array(
				'type'              => 'integer',
				'minimum'           => 0,
				'validate_callback' => array( self::class, 'validate_schema_arg' ),
				'sanitize_callback' => 'absint',
			),
		);
	}

	/**
	 * Validate an arg against its REST schema.
	 *
	 * @param mixed           $value   Submitted value.
	 * @param WP_REST_Request $request Request.
	 * @param string          $param   Parameter name.
	 * @return true|\WP_Error
	 */
	public static function validate_schema_arg( $value, WP_REST_Request $request, $param ) {
		$attributes = $request->get_attributes();
		$args       = $attributes['args'] ?? array();
		$schema     = $args[ $param ] ?? array();

		return rest_validate_value_from_schema( $value, $schema, $param );
	}

	/**
	 * Validate a REST key arg.
	 *
	 * @param mixed $value Submitted value.
	 * @return bool
	 */
	public static function validate_key_arg( $value ) {
		return is_string( $value ) && '' !== $value && sanitize_key( $value ) === $value;
	}

	/**
	 * Sanitize a REST key arg.
	 *
	 * @param mixed $value Submitted value.
	 * @return string
	 */
	public static function sanitize_key_arg( $value ) {
		return sanitize_key( $value );
	}

	/**
	 * Validate a required text arg.
	 *
	 * @param mixed $value Submitted value.
	 * @return bool
	 */
	public static function validate_required_text_arg( $value ) {
		return is_string( $value ) && '' !== trim( $value );
	}

	/**
	 * Validate current-member profile values.
	 *
	 * @param mixed $value Submitted value.
	 * @return bool
	 */
	public static function validate_profile_values_arg( $value ) {
		if ( ! is_array( $value ) ) {
			return false;
		}

		foreach ( array_keys( $value ) as $key ) {
			if ( ! is_string( $key ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Sanitize a REST text arg.
	 *
	 * @param mixed $value Submitted value.
	 * @return string
	 */
	public static function sanitize_text_arg( $value ) {
		return sanitize_text_field( (string) $value );
	}

	/**
	 * Validate a profile field group ID.
	 *
	 * @param mixed $value Submitted value.
	 * @return bool
	 */
	public static function validate_profile_field_group_id_arg( $value ) {
		return is_numeric( $value ) && Profile_Field_Groups::get_group_by_id( (int) $value );
	}

	/**
	 * Validate structured profile field options.
	 *
	 * @param mixed $value Submitted value.
	 * @return bool
	 */
	public static function validate_profile_field_options_arg( $value ) {
		if ( ! is_array( $value ) ) {
			return false;
		}

		$seen = array();
		foreach ( $value as $option ) {
			if ( ! is_array( $option ) ) {
				return false;
			}

			if ( ! isset( $option['value'], $option['label'] ) || ! is_string( $option['value'] ) || ! is_string( $option['label'] ) ) {
				return false;
			}

			$option_value = $option['value'];
			if ( '' === $option_value || sanitize_key( $option_value ) !== $option_value || '' === trim( $option['label'] ) ) {
				return false;
			}

			if ( isset( $seen[ $option_value ] ) ) {
				return false;
			}

			$seen[ $option_value ] = true;
		}

		return true;
	}

	/**
	 * Sanitize structured profile field options.
	 *
	 * @param mixed $value Submitted value.
	 * @return array
	 */
	public static function sanitize_profile_field_options_arg( $value ) {
		$options = array();

		foreach ( (array) $value as $option ) {
			$options[] = array(
				'value' => sanitize_key( $option['value'] ?? '' ),
				'label' => sanitize_text_field( $option['label'] ?? '' ),
			);
		}

		return $options;
	}

	/**
	 * Return validated request params for a profile field mutation.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	private static function get_profile_field_params( WP_REST_Request $request ) {
		return self::get_request_params(
			$request,
			array( 'key', 'label', 'type', 'visibility', 'editable', 'required', 'description', 'order', 'group_id', 'options' )
		);
	}

	/**
	 * Return validated request params for a profile field group mutation.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	private static function get_profile_field_group_params( WP_REST_Request $request ) {
		return self::get_request_params( $request, array( 'key', 'label', 'description', 'order' ) );
	}

	/**
	 * Return selected request params.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param array           $keys    Allowed keys.
	 * @return array
	 */
	private static function get_request_params( WP_REST_Request $request, $keys ) {
		$params = array();

		foreach ( $keys as $key ) {
			if ( $request->has_param( $key ) ) {
				$params[ $key ] = $request->get_param( $key );
			}
		}

		return $params;
	}

	/**
	 * Apply REST _fields filtering to plain callback array responses.
	 *
	 * @param mixed           $data    Response data.
	 * @param WP_REST_Request $request Request.
	 * @param string          $prefix  Current field path prefix.
	 * @return mixed
	 */
	private static function filter_response_fields( $data, WP_REST_Request $request, $prefix = '' ) {
		$fields = $request->get_param( '_fields' );

		if ( empty( $fields ) || ! is_array( $data ) ) {
			return $data;
		}

		$fields = wp_parse_list( $fields );

		if ( empty( $fields ) ) {
			return $data;
		}

		return self::filter_data_by_fields( $data, $fields, $prefix );
	}

	/**
	 * Filter data by requested REST field paths.
	 *
	 * @param array  $data   Response data.
	 * @param array  $fields Requested field paths.
	 * @param string $prefix Current field path prefix.
	 * @return array
	 */
	private static function filter_data_by_fields( $data, $fields, $prefix = '' ) {
		if ( wp_is_numeric_array( $data ) ) {
			return array_map(
				static function ( $item ) use ( $fields, $prefix ) {
					return is_array( $item ) ? self::filter_data_by_fields( $item, $fields, $prefix ) : $item;
				},
				$data
			);
		}

		$filtered = array();

		foreach ( $data as $key => $value ) {
			$path = $prefix ? $prefix . '.' . $key : (string) $key;

			if ( ! rest_is_field_included( $path, $fields ) ) {
				continue;
			}

			$filtered[ $key ] = is_array( $value ) && ! in_array( $path, $fields, true )
				? self::filter_data_by_fields( $value, $fields, $path )
				: $value;
		}

		return $filtered;
	}

	/**
	 * Prepare a member profile REST response.
	 *
	 * @param int             $user_id         User ID.
	 * @param WP_REST_Request $request         Request.
	 * @param bool            $include_private Whether private profile fields should be included.
	 * @param string          $field_prefix    Field path prefix for nested responses.
	 * @return array|\WP_Error
	 */
	private static function prepare_member_for_response( $user_id, WP_REST_Request $request, $include_private = false, $field_prefix = '' ) {
		$profile = Profile_Fields::get_member_profile( $user_id, $include_private );

		if ( is_wp_error( $profile ) ) {
			return $profile;
		}

		return self::filter_response_fields( $profile, $request, $field_prefix );
	}

	/**
	 * Prepare a members collection REST response.
	 *
	 * @param \WP_User_Query  $query    User query.
	 * @param WP_REST_Request $request  Request.
	 * @param int             $per_page Members per page.
	 * @return array
	 */
	private static function prepare_members_collection_for_response( \WP_User_Query $query, WP_REST_Request $request, $per_page ) {
		$members = array_map(
			static function ( $user ) use ( $request ) {
				return self::prepare_member_for_response(
					$user->ID,
					$request,
					current_user_can( 'edit_user', $user->ID ),
					'members'
				);
			},
			$query->get_results()
		);

		return self::filter_response_fields(
			array(
				'members'    => $members,
				'total'      => (int) $query->get_total(),
				'totalPages' => (int) ceil( $query->get_total() / $per_page ),
			),
			$request
		);
	}

	/**
	 * Prepare a profile field REST response.
	 *
	 * @param array|\WP_Error $field   Profile field data.
	 * @param WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	private static function prepare_profile_field_for_response( $field, WP_REST_Request $request ) {
		if ( is_wp_error( $field ) ) {
			return $field;
		}

		return self::filter_response_fields( $field, $request );
	}

	/**
	 * Prepare a profile fields collection REST response.
	 *
	 * @param array           $fields  Profile field rows.
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	private static function prepare_profile_fields_collection_for_response( $fields, WP_REST_Request $request ) {
		return array_map(
			static function ( $field ) use ( $request ) {
				return self::prepare_profile_field_for_response( $field, $request );
			},
			$fields
		);
	}

	/**
	 * Prepare a profile field group REST response.
	 *
	 * @param array|\WP_Error $group   Profile field group data.
	 * @param WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	private static function prepare_profile_field_group_for_response( $group, WP_REST_Request $request ) {
		if ( is_wp_error( $group ) ) {
			return $group;
		}

		return self::filter_response_fields( $group, $request );
	}

	/**
	 * Prepare a profile field groups collection REST response.
	 *
	 * @param array           $groups  Profile field group rows.
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	private static function prepare_profile_field_groups_collection_for_response( $groups, WP_REST_Request $request ) {
		return array_map(
			static function ( $group ) use ( $request ) {
				return self::prepare_profile_field_group_for_response( $group, $request );
			},
			$groups
		);
	}

	/**
	 * Return profile fields.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	public static function get_profile_fields( WP_REST_Request $request ) {
		return self::prepare_profile_fields_collection_for_response( Profile_Fields::get_fields(), $request );
	}

	/**
	 * Create a profile field.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	public static function create_profile_field( WP_REST_Request $request ) {
		return self::prepare_profile_field_for_response(
			Profile_Fields::create_field( self::get_profile_field_params( $request ) ),
			$request
		);
	}

	/**
	 * Update a profile field.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	public static function update_profile_field( WP_REST_Request $request ) {
		return self::prepare_profile_field_for_response(
			Profile_Fields::update_field( (int) $request['id'], self::get_profile_field_params( $request ) ),
			$request
		);
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
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	public static function get_profile_field_groups( WP_REST_Request $request ) {
		return self::prepare_profile_field_groups_collection_for_response( Profile_Field_Groups::get_groups(), $request );
	}

	/**
	 * Create a profile field group.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	public static function create_profile_field_group( WP_REST_Request $request ) {
		return self::prepare_profile_field_group_for_response(
			Profile_Field_Groups::create_group( self::get_profile_field_group_params( $request ) ),
			$request
		);
	}

	/**
	 * Update a profile field group.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	public static function update_profile_field_group( WP_REST_Request $request ) {
		return self::prepare_profile_field_group_for_response(
			Profile_Field_Groups::update_group( (int) $request['id'], self::get_profile_field_group_params( $request ) ),
			$request
		);
	}

	/**
	 * Delete a profile field group.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	public static function delete_profile_field_group( WP_REST_Request $request ) {
		return Profile_Field_Groups::delete_group( (int) $request['id'] );
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

		return self::prepare_members_collection_for_response( $query, $request, $per_page );
	}

	/**
	 * Return a public member profile.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	public static function get_member( WP_REST_Request $request ) {
		$user_id = (int) $request['id'];
		return self::prepare_member_for_response( $user_id, $request, current_user_can( 'edit_user', $user_id ) );
	}

	/**
	 * Return current user's profile.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	public static function get_my_profile( WP_REST_Request $request ) {
		return self::prepare_member_for_response( get_current_user_id(), $request, true );
	}

	/**
	 * Update current user's profile.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	public static function update_my_profile( WP_REST_Request $request ) {
		$params = $request->get_json_params();

		if ( null === $params ) {
			$params = array();
		}

		if ( ! is_array( $params ) ) {
			return new \WP_Error( 'assemblypress_invalid_profile_payload', __( 'Profile payload must be a JSON object.', 'assemblypress' ), array( 'status' => 400 ) );
		}

		$values = array_key_exists( 'fields', $params ) ? $params['fields'] : $params;
		$result = Profile_Fields::update_member_profile( get_current_user_id(), $values );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return self::prepare_member_for_response( get_current_user_id(), $request, true );
	}
}
