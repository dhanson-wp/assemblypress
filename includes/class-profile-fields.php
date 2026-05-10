<?php
/**
 * Member profile field definitions and values.
 *
 * @package AssemblyPress
 */

namespace AssemblyPress;

use WP_Error;
use WP_User;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Profile field registry.
 */
final class Profile_Fields {
	const META_PREFIX = 'assemblypress_';

	/**
	 * Field properties that define a protected system field's storage contract.
	 *
	 * @var array
	 */
	const PROTECTED_FIELD_PROPERTIES = array( 'key', 'type', 'source', 'user_key', 'meta_key' );

	/**
	 * Supported field types.
	 *
	 * @return array
	 */
	public static function supported_types() {
		return array( 'text', 'textarea', 'url', 'email', 'select', 'checkbox', 'image', 'social_links' );
	}

	/**
	 * Default field definitions.
	 *
	 * @return array
	 */
	public static function default_fields() {
		return array(
			array(
				'key'        => 'display_name',
				'label'      => __( 'Display name', 'assemblypress' ),
				'type'       => 'text',
				'source'     => 'user',
				'user_key'   => 'display_name',
				'visibility' => 'public',
				'editable'   => true,
				'required'   => true,
				'order'      => 10,
			),
			array(
				'key'        => 'bio',
				'label'      => __( 'Bio', 'assemblypress' ),
				'type'       => 'textarea',
				'source'     => 'user',
				'user_key'   => 'description',
				'visibility' => 'public',
				'editable'   => true,
				'required'   => false,
				'order'      => 20,
			),
			array(
				'key'        => 'location',
				'label'      => __( 'Location', 'assemblypress' ),
				'type'       => 'text',
				'source'     => 'meta',
				'meta_key'   => self::META_PREFIX . 'location', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'visibility' => 'public',
				'editable'   => true,
				'required'   => false,
				'order'      => 30,
			),
			array(
				'key'        => 'website',
				'label'      => __( 'Website', 'assemblypress' ),
				'type'       => 'url',
				'source'     => 'user',
				'user_key'   => 'user_url',
				'visibility' => 'public',
				'editable'   => true,
				'required'   => false,
				'order'      => 40,
			),
			array(
				'key'        => 'avatar',
				'label'      => __( 'Profile image', 'assemblypress' ),
				'type'       => 'image',
				'source'     => 'meta',
				'meta_key'   => self::META_PREFIX . 'avatar_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'visibility' => 'public',
				'editable'   => true,
				'required'   => false,
				'order'      => 50,
			),
			array(
				'key'        => 'social_links',
				'label'      => __( 'Social links', 'assemblypress' ),
				'type'       => 'social_links',
				'source'     => 'meta',
				'meta_key'   => self::META_PREFIX . 'social_links', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'visibility' => 'public',
				'editable'   => true,
				'required'   => false,
				'order'      => 60,
			),
		);
	}

	/**
	 * Get protected default field keys.
	 *
	 * User-backed default fields map to WordPress user columns/meta and must keep their
	 * storage contract stable so member profile reads and writes remain safe.
	 *
	 * @return array
	 */
	private static function protected_field_keys() {
		$keys = array();

		foreach ( self::default_fields() as $field ) {
			if ( 'user' === ( $field['source'] ?? '' ) ) {
				$keys[] = $field['key'];
			}
		}

		return $keys;
	}

	/**
	 * Seed default field group, fields, and form.
	 *
	 * @return void
	 */
	public static function seed_defaults() {
		if ( get_posts(
			array(
				'post_type'      => Config_Entities::FIELD_POST_TYPE,
				'posts_per_page' => 1,
				'post_status'    => 'any',
			)
		) ) {
			return;
		}

		$group_id = wp_insert_post(
			array(
				'post_type'    => Config_Entities::FIELD_GROUP_POST_TYPE,
				'post_title'   => __( 'Profile', 'assemblypress' ),
				'post_name'    => 'profile',
				'post_status'  => 'publish',
				'post_content' => wp_json_encode(
					array(
						'key'   => 'profile',
						'order' => 10,
					)
				),
			)
		);

		$field_keys = array();
		foreach ( self::default_fields() as $field ) {
			$field['group_id'] = $group_id;
			$field_keys[]      = $field['key'];
			wp_insert_post(
				array(
					'post_type'    => Config_Entities::FIELD_POST_TYPE,
					'post_title'   => $field['label'],
					'post_name'    => sanitize_key( $field['key'] ),
					'post_status'  => 'publish',
					'post_content' => wp_json_encode( $field ),
					'menu_order'   => (int) $field['order'],
				)
			);
		}

		wp_insert_post(
			array(
				'post_type'    => Config_Entities::FORM_POST_TYPE,
				'post_title'   => __( 'Edit Profile', 'assemblypress' ),
				'post_name'    => 'edit-profile',
				'post_status'  => 'publish',
				'post_content' => wp_json_encode(
					array(
						'key'         => 'edit-profile',
						'type'        => 'profile_edit',
						'field_keys'  => $field_keys,
						'submit_text' => __( 'Save profile', 'assemblypress' ),
					)
				),
			)
		);
	}

	/**
	 * Get field definitions.
	 *
	 * @return array
	 */
	public static function get_fields() {
		$posts = get_posts(
			array(
				'post_type'      => Config_Entities::FIELD_POST_TYPE,
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'orderby'        => array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				),
			)
		);

		$fields = array();
		foreach ( $posts as $post ) {
			$field = json_decode( $post->post_content, true );
			if ( ! is_array( $field ) ) {
				continue;
			}
			$field['id'] = $post->ID;
			$fields[]    = self::normalize_field( $field );
		}

		if ( empty( $fields ) ) {
			$fields = array_map( array( self::class, 'normalize_field' ), self::default_fields() );
		}

		return $fields;
	}

	/**
	 * Get one field by key.
	 *
	 * @param string $key Field key.
	 * @return array|null
	 */
	public static function get_field( $key ) {
		foreach ( self::get_fields() as $field ) {
			if ( $key === $field['key'] ) {
				return $field;
			}
		}

		return null;
	}

	/**
	 * Normalize a field definition.
	 *
	 * @param array $field Raw field.
	 * @return array
	 */
	public static function normalize_field( $field ) {
		$key  = sanitize_key( $field['key'] ?? '' );
		$type = sanitize_key( $field['type'] ?? 'text' );

		if ( ! in_array( $type, self::supported_types(), true ) ) {
			$type = 'text';
		}

		$source = 'user' === ( $field['source'] ?? '' ) ? 'user' : 'meta';

		$normalized = array(
			'id'          => isset( $field['id'] ) ? (int) $field['id'] : 0,
			'key'         => $key,
			'label'       => sanitize_text_field( $field['label'] ?? $key ),
			'type'        => $type,
			'source'      => $source,
			'user_key'    => sanitize_key( $field['user_key'] ?? '' ),
			'meta_key'    => sanitize_key( $field['meta_key'] ?? self::META_PREFIX . $key ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'visibility'  => 'private' === ( $field['visibility'] ?? '' ) ? 'private' : 'public',
			'editable'    => ! empty( $field['editable'] ),
			'required'    => ! empty( $field['required'] ),
			'order'       => isset( $field['order'] ) ? (int) $field['order'] : 0,
			'options'     => self::normalize_options( $field['options'] ?? array() ),
			'description' => sanitize_text_field( $field['description'] ?? '' ),
			'group_id'    => isset( $field['group_id'] ) ? (int) $field['group_id'] : 0,
		);

		$normalized['is_system'] = self::is_system_field( $normalized );

		return $normalized;
	}

	/**
	 * Determine whether a field is a protected system field.
	 *
	 * @param array $field Field definition.
	 * @return bool
	 */
	private static function is_system_field( $field ) {
		return 'user' === ( $field['source'] ?? '' ) && in_array( $field['key'] ?? '', self::protected_field_keys(), true );
	}

	/**
	 * Normalize choice field options.
	 *
	 * @param array $options Raw options.
	 * @return array
	 */
	private static function normalize_options( $options ) {
		if ( ! is_array( $options ) ) {
			return array();
		}

		$normalized = array();
		$seen       = array();

		foreach ( $options as $option ) {
			if ( is_array( $option ) ) {
				$value = sanitize_key( $option['value'] ?? '' );
				$label = sanitize_text_field( $option['label'] ?? $value );
			} else {
				$value = sanitize_key( $option );
				$label = sanitize_text_field( $option );
			}

			if ( '' === $value || isset( $seen[ $value ] ) ) {
				continue;
			}

			$seen[ $value ] = true;
			$normalized[]   = array(
				'value' => $value,
				'label' => $label ? $label : $value,
			);
		}

		return $normalized;
	}

	/**
	 * Create a field definition.
	 *
	 * @param array $field Field data.
	 * @return array|WP_Error
	 */
	public static function create_field( $field ) {
		$field = self::normalize_field( $field );

		if ( empty( $field['key'] ) ) {
			return new WP_Error( 'assemblypress_missing_field_key', __( 'Field key is required.', 'assemblypress' ), array( 'status' => 400 ) );
		}

		if ( self::get_field( $field['key'] ) ) {
			return new WP_Error( 'assemblypress_duplicate_field_key', __( 'A field with this key already exists.', 'assemblypress' ), array( 'status' => 409 ) );
		}

		$post_id = wp_insert_post(
			array(
				'post_type'    => Config_Entities::FIELD_POST_TYPE,
				'post_title'   => $field['label'],
				'post_name'    => $field['key'],
				'post_status'  => 'publish',
				'post_content' => wp_json_encode( $field ),
				'menu_order'   => (int) $field['order'],
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$field['id'] = $post_id;
		return self::normalize_field( $field );
	}

	/**
	 * Update a field definition.
	 *
	 * @param int   $field_id Field post ID.
	 * @param array $field    Field data.
	 * @return array|WP_Error
	 */
	public static function update_field( $field_id, $field ) {
		$post = get_post( $field_id );

		if ( ! $post || Config_Entities::FIELD_POST_TYPE !== $post->post_type ) {
			return new WP_Error( 'assemblypress_field_not_found', __( 'Profile field not found.', 'assemblypress' ), array( 'status' => 404 ) );
		}

		$existing_field = self::field_from_post( $post );
		$field          = self::normalize_field( array_merge( array( 'id' => $field_id ), $field ) );

		if ( self::is_system_field( $existing_field ) ) {
			foreach ( self::PROTECTED_FIELD_PROPERTIES as $property ) {
				$field[ $property ] = $existing_field[ $property ];
			}
			$field['is_system'] = true;
		}

		$existing_id = self::field_id_for_key( $field['key'] );

		if ( empty( $field['key'] ) ) {
			return new WP_Error( 'assemblypress_missing_field_key', __( 'Field key is required.', 'assemblypress' ), array( 'status' => 400 ) );
		}

		if ( $existing_id && $existing_id !== $field_id ) {
			return new WP_Error( 'assemblypress_duplicate_field_key', __( 'A field with this key already exists.', 'assemblypress' ), array( 'status' => 409 ) );
		}

		$result = wp_update_post(
			array(
				'ID'           => $field_id,
				'post_title'   => $field['label'],
				'post_name'    => $field['key'],
				'post_content' => wp_json_encode( $field ),
				'menu_order'   => (int) $field['order'],
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $field;
	}

	/**
	 * Delete a field definition.
	 *
	 * @param int $field_id Field post ID.
	 * @return array|WP_Error
	 */
	public static function delete_field( $field_id ) {
		$post = get_post( $field_id );

		if ( ! $post || Config_Entities::FIELD_POST_TYPE !== $post->post_type ) {
			return new WP_Error( 'assemblypress_field_not_found', __( 'Profile field not found.', 'assemblypress' ), array( 'status' => 404 ) );
		}

		if ( self::is_system_field( self::field_from_post( $post ) ) ) {
			return new WP_Error( 'assemblypress_system_field_delete_forbidden', __( 'System profile fields cannot be deleted.', 'assemblypress' ), array( 'status' => 400 ) );
		}

		$deleted = wp_delete_post( $field_id, true );

		if ( ! $deleted ) {
			return new WP_Error( 'assemblypress_field_delete_failed', __( 'Profile field could not be deleted.', 'assemblypress' ), array( 'status' => 500 ) );
		}

		return array( 'deleted' => true );
	}

	/**
	 * Build a normalized field definition from a config post.
	 *
	 * @param \WP_Post $post Field config post.
	 * @return array
	 */
	private static function field_from_post( $post ) {
		$field = json_decode( $post->post_content, true );

		if ( ! is_array( $field ) ) {
			$field = array();
		}

		$field['id'] = $post->ID;

		return self::normalize_field( $field );
	}

	/**
	 * Find a field post ID by key.
	 *
	 * @param string $key Field key.
	 * @return int
	 */
	private static function field_id_for_key( $key ) {
		foreach ( self::get_fields() as $field ) {
			if ( $key === $field['key'] ) {
				return (int) $field['id'];
			}
		}

		return 0;
	}

	/**
	 * Register REST-exposed user meta for custom field values.
	 *
	 * @return void
	 */
	public static function register_user_meta() {
		foreach ( self::get_fields() as $field ) {
			if ( 'meta' !== $field['source'] ) {
				continue;
			}

			register_meta(
				'user',
				$field['meta_key'],
				array(
					'type'              => self::meta_type_for_field( $field ),
					'single'            => true,
					'default'           => self::default_value_for_field( $field ),
					'show_in_rest'      => array(
						'schema' => self::rest_schema_for_field( $field ),
					),
					'sanitize_callback' => array( self::class, 'sanitize_meta_value' ),
					'auth_callback'     => static function ( $allowed, $meta_key, $user_id ) {
						return current_user_can( 'edit_user', $user_id );
					},
				)
			);
		}
	}

	/**
	 * Sanitize meta values.
	 *
	 * @param mixed  $value    Submitted value.
	 * @param string $meta_key Meta key.
	 * @return mixed
	 */
	public static function sanitize_meta_value( $value, $meta_key ) {
		$field = null;
		foreach ( self::get_fields() as $candidate ) {
			if ( isset( $candidate['meta_key'] ) && $meta_key === $candidate['meta_key'] ) {
				$field = $candidate;
				break;
			}
		}

		return $field ? self::sanitize_value( $value, $field ) : sanitize_text_field( (string) $value );
	}

	/**
	 * Get a member profile response.
	 *
	 * @param int  $user_id      User ID.
	 * @param bool $include_priv Include private fields.
	 * @return array|WP_Error
	 */
	public static function get_member_profile( $user_id, $include_priv = false ) {
		$user = get_userdata( $user_id );

		if ( ! $user instanceof WP_User ) {
			return new WP_Error( 'assemblypress_member_not_found', __( 'Member not found.', 'assemblypress' ), array( 'status' => 404 ) );
		}

		$fields = array();
		foreach ( self::get_fields() as $field ) {
			if ( 'private' === $field['visibility'] && ! $include_priv ) {
				continue;
			}

			$fields[] = array_merge(
				$field,
				array(
					'value' => self::get_value( $user, $field ),
				)
			);
		}

		return array(
			'id'           => $user->ID,
			'display_name' => $user->display_name,
			'avatar_url'   => get_avatar_url( $user->ID ),
			'profile_url'  => get_author_posts_url( $user->ID ),
			'fields'       => $fields,
		);
	}

	/**
	 * Update a user's editable profile values.
	 *
	 * @param int   $user_id User ID.
	 * @param array $values  Submitted values keyed by field key.
	 * @return array|WP_Error
	 */
	public static function update_member_profile( $user_id, $values ) {
		$user = get_userdata( $user_id );

		if ( ! $user instanceof WP_User ) {
			return new WP_Error( 'assemblypress_member_not_found', __( 'Member not found.', 'assemblypress' ), array( 'status' => 404 ) );
		}

		foreach ( self::get_fields() as $field ) {
			if ( empty( $field['editable'] ) || ! array_key_exists( $field['key'], $values ) ) {
				continue;
			}

			$value = self::sanitize_value( $values[ $field['key'] ], $field );

			if ( ! empty( $field['required'] ) && self::is_empty_value( $value ) ) {
				return new WP_Error(
					'assemblypress_required_field',
					sprintf(
						/* translators: %s: Field label. */
						__( '%s is required.', 'assemblypress' ),
						$field['label']
					),
					array( 'status' => 400 )
				);
			}

			if ( 'user' === $field['source'] ) {
				self::update_user_core_value( $user_id, $field, $value );
			} else {
				update_user_meta( $user_id, $field['meta_key'], $value );
			}
		}

		return self::get_member_profile( $user_id, true );
	}

	/**
	 * Get a field value for a user.
	 *
	 * @param WP_User $user  User.
	 * @param array   $field Field definition.
	 * @return mixed
	 */
	public static function get_value( WP_User $user, $field ) {
		if ( 'user' === $field['source'] ) {
			switch ( $field['user_key'] ) {
				case 'display_name':
					return $user->display_name;
				case 'description':
					return get_user_meta( $user->ID, 'description', true );
				case 'user_url':
					return $user->user_url;
			}
		}

		return get_user_meta( $user->ID, $field['meta_key'], true );
	}

	/**
	 * Update a core user value.
	 *
	 * @param int   $user_id User ID.
	 * @param array $field   Field definition.
	 * @param mixed $value   Field value.
	 * @return void
	 */
	private static function update_user_core_value( $user_id, $field, $value ) {
		if ( 'display_name' === $field['user_key'] ) {
			wp_update_user(
				array(
					'ID'           => $user_id,
					'display_name' => $value,
				)
			);
			return;
		}

		if ( 'user_url' === $field['user_key'] ) {
			wp_update_user(
				array(
					'ID'       => $user_id,
					'user_url' => $value,
				)
			);
			return;
		}

		if ( 'description' === $field['user_key'] ) {
			update_user_meta( $user_id, 'description', $value );
		}
	}

	/**
	 * Sanitize a value by field type.
	 *
	 * @param mixed $value Field value.
	 * @param array $field Field definition.
	 * @return mixed
	 */
	public static function sanitize_value( $value, $field ) {
		switch ( $field['type'] ) {
			case 'textarea':
				return sanitize_textarea_field( (string) $value );
			case 'url':
				return esc_url_raw( (string) $value );
			case 'email':
				return sanitize_email( (string) $value );
			case 'checkbox':
				return (bool) $value;
			case 'image':
				return absint( $value );
			case 'social_links':
				if ( ! is_array( $value ) ) {
					return array();
				}
				return array_values(
					array_filter(
						array_map(
							static function ( $link ) {
								if ( ! is_array( $link ) ) {
									return null;
								}
								return array(
									'label' => sanitize_text_field( $link['label'] ?? '' ),
									'url'   => esc_url_raw( $link['url'] ?? '' ),
								);
							},
							$value
						)
					)
				);
			case 'select':
				$value   = sanitize_text_field( (string) $value );
				$options = wp_list_pluck( $field['options'], 'value' );
				return empty( $options ) || in_array( $value, $options, true ) ? $value : '';
			case 'text':
			default:
				return sanitize_text_field( (string) $value );
		}
	}

	/**
	 * Determine whether a value is empty for validation.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	private static function is_empty_value( $value ) {
		return '' === $value || null === $value || array() === $value;
	}

	/**
	 * Get REST/meta schema for a field.
	 *
	 * @param array $field Field definition.
	 * @return array
	 */
	public static function rest_schema_for_field( $field ) {
		if ( 'social_links' === $field['type'] ) {
			return array(
				'type'  => 'array',
				'items' => array(
					'type'       => 'object',
					'properties' => array(
						'label' => array( 'type' => 'string' ),
						'url'   => array(
							'type'   => 'string',
							'format' => 'uri',
						),
					),
				),
			);
		}

		return array( 'type' => self::meta_type_for_field( $field ) );
	}

	/**
	 * Get meta type for a field.
	 *
	 * @param array $field Field definition.
	 * @return string
	 */
	private static function meta_type_for_field( $field ) {
		switch ( $field['type'] ) {
			case 'checkbox':
				return 'boolean';
			case 'image':
				return 'integer';
			case 'social_links':
				return 'array';
			default:
				return 'string';
		}
	}

	/**
	 * Get default value for a field.
	 *
	 * @param array $field Field definition.
	 * @return mixed
	 */
	private static function default_value_for_field( $field ) {
		switch ( $field['type'] ) {
			case 'checkbox':
				return false;
			case 'image':
				return 0;
			case 'social_links':
				return array();
			default:
				return '';
		}
	}
}
