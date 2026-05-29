<?php
/**
 * Member profile field groups.
 *
 * @package AssemblyPress
 */

namespace AssemblyPress;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Profile field group registry.
 */
final class Profile_Field_Groups {
	/**
	 * Starter group definitions.
	 *
	 * @return array
	 */
	public static function starter_groups() {
		return array(
			array(
				'key'         => 'base',
				'label'       => __( 'Base', 'assemblypress' ),
				'description' => __( 'Primary profile fields used as the foundation of a member profile.', 'assemblypress' ),
				'order'       => 10,
			),
			array(
				'key'         => 'contact',
				'label'       => __( 'Contact', 'assemblypress' ),
				'description' => __( 'Ways people can reach or learn more about a member.', 'assemblypress' ),
				'order'       => 20,
			),
			array(
				'key'         => 'about',
				'label'       => __( 'About', 'assemblypress' ),
				'description' => __( 'Context and biographical details.', 'assemblypress' ),
				'order'       => 30,
			),
			array(
				'key'         => 'social',
				'label'       => __( 'Social', 'assemblypress' ),
				'description' => __( 'Social profile links and public networks.', 'assemblypress' ),
				'order'       => 40,
			),
		);
	}

	/**
	 * Known field-to-group starter assignments.
	 *
	 * @return array
	 */
	public static function starter_field_map() {
		return array(
			'display_name'      => 'base',
			'avatar'            => 'base',
			'website'           => 'contact',
			'preferred_contact' => 'contact',
			'prefer_contact'    => 'contact',
			'bio'               => 'about',
			'location'          => 'about',
			'social_links'      => 'social',
		);
	}

	/**
	 * Ensure starter groups exist and known fields are assigned.
	 *
	 * @return void
	 */
	public static function ensure_starter_groups() {
		self::migrate_legacy_identity_group();
		self::delete_empty_legacy_profile_group();

		foreach ( self::starter_groups() as $group ) {
			if ( ! self::group_id_for_key( $group['key'] ) ) {
				self::create_group( $group );
			}
		}

		if ( class_exists( Profile_Fields::class ) ) {
			Profile_Fields::assign_starter_groups_to_known_fields();
		}
	}

	/**
	 * Get all field groups.
	 *
	 * @return array
	 */
	public static function get_groups() {
		$posts = get_posts(
			array(
				'post_type'      => Config_Entities::FIELD_GROUP_POST_TYPE,
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'orderby'        => array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				),
			)
		);

		return array_map( array( self::class, 'group_from_post' ), $posts );
	}

	/**
	 * Get one group by key.
	 *
	 * @param string $key Group key.
	 * @return array|null
	 */
	public static function get_group( $key ) {
		foreach ( self::get_groups() as $group ) {
			if ( $key === $group['key'] ) {
				return $group;
			}
		}

		return null;
	}

	/**
	 * Get one group by ID.
	 *
	 * @param int $group_id Group post ID.
	 * @return array|null
	 */
	public static function get_group_by_id( $group_id ) {
		$post = get_post( $group_id );

		if ( ! $post || Config_Entities::FIELD_GROUP_POST_TYPE !== $post->post_type ) {
			return null;
		}

		return self::group_from_post( $post );
	}

	/**
	 * Get the fallback group ID.
	 *
	 * @return int
	 */
	public static function get_default_group_id() {
		$groups = self::get_groups();

		return $groups ? (int) $groups[0]['id'] : 0;
	}

	/**
	 * Normalize a group definition.
	 *
	 * @param array $group Raw group.
	 * @return array
	 */
	public static function normalize_group( $group ) {
		$key   = sanitize_key( $group['key'] ?? '' );
		$label = sanitize_text_field( $group['label'] ?? ( $group['title'] ?? $key ) );

		return array(
			'id'          => isset( $group['id'] ) ? (int) $group['id'] : 0,
			'key'         => $key,
			'label'       => $label ? $label : $key,
			'description' => sanitize_text_field( $group['description'] ?? '' ),
			'order'       => isset( $group['order'] ) ? (int) $group['order'] : 0,
		);
	}

	/**
	 * Create a profile field group.
	 *
	 * @param array $group Group data.
	 * @return array|WP_Error
	 */
	public static function create_group( $group ) {
		$group = self::normalize_group( $group );

		if ( empty( $group['key'] ) ) {
			return new WP_Error( 'assemblypress_missing_group_key', __( 'Group key is required.', 'assemblypress' ), array( 'status' => 400 ) );
		}

		if ( self::group_id_for_key( $group['key'] ) ) {
			return new WP_Error( 'assemblypress_duplicate_group_key', __( 'A field group with this key already exists.', 'assemblypress' ), array( 'status' => 409 ) );
		}

		$post_id = wp_insert_post(
			array(
				'post_type'    => Config_Entities::FIELD_GROUP_POST_TYPE,
				'post_title'   => $group['label'],
				'post_name'    => $group['key'],
				'post_status'  => 'publish',
				'post_content' => wp_json_encode( $group ),
				'menu_order'   => (int) $group['order'],
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$group['id'] = $post_id;
		return self::normalize_group( $group );
	}

	/**
	 * Update a profile field group.
	 *
	 * @param int   $group_id Group post ID.
	 * @param array $group    Group data.
	 * @return array|WP_Error
	 */
	public static function update_group( $group_id, $group ) {
		$post = get_post( $group_id );

		if ( ! $post || Config_Entities::FIELD_GROUP_POST_TYPE !== $post->post_type ) {
			return new WP_Error( 'assemblypress_group_not_found', __( 'Profile field group not found.', 'assemblypress' ), array( 'status' => 404 ) );
		}

		$group       = self::normalize_group( array_merge( array( 'id' => $group_id ), $group ) );
		$existing_id = self::group_id_for_key( $group['key'] );

		if ( empty( $group['key'] ) ) {
			return new WP_Error( 'assemblypress_missing_group_key', __( 'Group key is required.', 'assemblypress' ), array( 'status' => 400 ) );
		}

		if ( $existing_id && $existing_id !== $group_id ) {
			return new WP_Error( 'assemblypress_duplicate_group_key', __( 'A field group with this key already exists.', 'assemblypress' ), array( 'status' => 409 ) );
		}

		$result = wp_update_post(
			array(
				'ID'           => $group_id,
				'post_title'   => $group['label'],
				'post_name'    => $group['key'],
				'post_content' => wp_json_encode( $group ),
				'menu_order'   => (int) $group['order'],
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $group;
	}

	/**
	 * Delete a profile field group.
	 *
	 * @param int $group_id Group post ID.
	 * @return array|WP_Error
	 */
	public static function delete_group( $group_id ) {
		$post = get_post( $group_id );

		if ( ! $post || Config_Entities::FIELD_GROUP_POST_TYPE !== $post->post_type ) {
			return new WP_Error( 'assemblypress_group_not_found', __( 'Profile field group not found.', 'assemblypress' ), array( 'status' => 404 ) );
		}

		foreach ( Profile_Fields::get_fields() as $field ) {
			if ( (int) $field['group_id'] === $group_id ) {
				return new WP_Error( 'assemblypress_group_not_empty', __( 'Profile field groups with assigned fields cannot be deleted.', 'assemblypress' ), array( 'status' => 400 ) );
			}
		}

		$deleted = wp_delete_post( $group_id, true );

		if ( ! $deleted ) {
			return new WP_Error( 'assemblypress_group_delete_failed', __( 'Profile field group could not be deleted.', 'assemblypress' ), array( 'status' => 500 ) );
		}

		return array( 'deleted' => true );
	}

	/**
	 * Build a normalized group from a config post.
	 *
	 * @param \WP_Post $post Group config post.
	 * @return array
	 */
	private static function group_from_post( $post ) {
		$group = json_decode( $post->post_content, true );

		if ( ! is_array( $group ) ) {
			$group = array();
		}

		$group['id']    = $post->ID;
		$group['key']   = $group['key'] ?? $post->post_name;
		$group['label'] = $group['label'] ?? $post->post_title;
		$group['order'] = isset( $group['order'] ) ? $group['order'] : $post->menu_order;

		return self::normalize_group( $group );
	}

	/**
	 * Rename the first profile-group iteration to the BuddyPress-familiar Base group.
	 *
	 * @return void
	 */
	private static function migrate_legacy_identity_group() {
		if ( self::group_id_for_key( 'base' ) ) {
			return;
		}

		$identity_id = self::group_id_for_key( 'identity' );

		if ( ! $identity_id ) {
			return;
		}

		self::update_group(
			$identity_id,
			array(
				'key'         => 'base',
				'label'       => __( 'Base', 'assemblypress' ),
				'description' => __( 'Primary profile fields used as the foundation of a member profile.', 'assemblypress' ),
				'order'       => 10,
			)
		);
	}

	/**
	 * Remove the placeholder group used by the earliest profile scaffold.
	 *
	 * @return void
	 */
	private static function delete_empty_legacy_profile_group() {
		$profile_id = self::group_id_for_key( 'profile' );

		if ( ! $profile_id ) {
			return;
		}

		foreach ( Profile_Fields::get_fields() as $field ) {
			if ( (int) $field['group_id'] === $profile_id ) {
				return;
			}
		}

		wp_delete_post( $profile_id, true );
	}

	/**
	 * Find a group post ID by key.
	 *
	 * @param string $key Group key.
	 * @return int
	 */
	private static function group_id_for_key( $key ) {
		$posts = get_posts(
			array(
				'name'           => sanitize_key( $key ),
				'post_type'      => Config_Entities::FIELD_GROUP_POST_TYPE,
				'posts_per_page' => 1,
				'post_status'    => 'publish',
				'fields'         => 'ids',
			)
		);

		return $posts ? (int) $posts[0] : 0;
	}
}
