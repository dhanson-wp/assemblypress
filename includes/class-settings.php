<?php
/**
 * Owner settings and shared access policy.
 *
 * @package AssemblyPress
 */

namespace AssemblyPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Owner settings. */
final class Settings {
	/** Get settings with backwards-compatible defaults.
	 *
	 * @return array
	 */
	public static function get() {
		return wp_parse_args(
			get_option( 'assemblypress_settings', array() ),
			array(
				'joining'            => 'closed',
				'directory_audience' => 'public',
				'profile_audience'   => 'public',
				'eligible_roles'     => array( 'subscriber' ),
				'directory_page'     => 0,
				'join_page'          => 0,
				'filter_fields'      => array(),
			)
		);
	}

	/** Validate and save a complete settings document.
	 *
	 * @param array $values Proposed settings.
	 * @return array|\WP_Error
	 */
	public static function save( $values ) {
		$current = self::get();
		foreach ( $values as $key => $value ) {
			if ( ! array_key_exists( $key, $current ) || null === $value || ( is_array( $current[ $key ] ) ? ! is_array( $value ) || array_values( $value ) !== $value || count( array_filter( $value, 'is_string' ) ) !== count( $value ) : ! is_scalar( $value ) ) ) {
				return new \WP_Error( 'invalid_setting', __( 'Settings contain an invalid value.', 'assemblypress' ), array( 'status' => 400 ) );
			}
		}

		foreach ( array(
			'joining'            => array( 'closed', 'open', 'invitation', 'approval' ),
			'directory_audience' => array( 'public', 'members' ),
			'profile_audience'   => array( 'public', 'members' ),
		) as $key => $allowed ) {
			if ( isset( $values[ $key ] ) && ! in_array( $values[ $key ], $allowed, true ) ) {
				return new \WP_Error( 'invalid_setting', __( 'Choose a valid settings option.', 'assemblypress' ), array( 'status' => 400 ) );
			}
		}
		foreach ( array( 'directory_page', 'join_page' ) as $key ) {
			if ( ! empty( $values[ $key ] ) && ( 'page' !== get_post_type( absint( $values[ $key ] ) ) || 'publish' !== get_post_status( absint( $values[ $key ] ) ) ) ) {
				return new \WP_Error( 'invalid_page', __( 'Choose a published page.', 'assemblypress' ), array( 'status' => 400 ) );
			}
			if ( isset( $values[ $key ] ) ) {
				$values[ $key ] = absint( $values[ $key ] );
			}
		}
		if ( isset( $values['eligible_roles'] ) && ( ! is_array( $values['eligible_roles'] ) || array_diff( $values['eligible_roles'], array_keys( wp_roles()->roles ) ) ) ) {
			return new \WP_Error( 'invalid_roles', __( 'Choose existing WordPress roles.', 'assemblypress' ), array( 'status' => 400 ) );
		}
		if ( isset( $values['filter_fields'] ) ) {
			$allowed = array_column( self::filter_fields(), 'key' );
			if ( ! is_array( $values['filter_fields'] ) || array_diff( $values['filter_fields'], $allowed ) ) {
				return new \WP_Error( 'invalid_filters', __( 'Filters must use public select fields.', 'assemblypress' ), array( 'status' => 400 ) );
			}
		}
		$result = array_merge( $current, array_intersect_key( $values, $current ) );
		if ( 'members' === $result['profile_audience'] ) {
			$result['directory_audience'] = 'members';
		}
		update_option( 'assemblypress_settings', $result );
		return $result;
	}

	/** Public structured fields suitable for filters.
	 *
	 * @return array
	 */
	public static function filter_fields() {
		return array_values(
			array_filter(
				Profile_Fields::get_fields(),
				static function ( $field ) {
					return 'public' === $field['visibility'] && 'select' === $field['type'];
				}
			)
		);
	}

	/** Check audience access.
	 *
	 * @param string $surface Surface name.
	 * @return bool
	 */
	public static function can_browse( $surface = 'directory' ) {
		return 'public' === self::get()[ $surface . '_audience' ] || ( is_user_logged_in() && is_user_member_of_blog( get_current_user_id() ) );
	}

	/** Whether a member is listed.
	 *
	 * @param int $id User ID.
	 * @return bool
	 */
	public static function eligible( $id ) {
		$user = get_userdata( $id );
		return $user && is_user_member_of_blog( $id ) && (bool) array_intersect( $user->roles, self::get()['eligible_roles'] );
	}

	/** Directory destination.
	 *
	 * @return string
	 */
	public static function directory_url() {
		$id = self::get()['directory_page'];
		return $id && 'publish' === get_post_status( $id ) ? get_permalink( $id ) : home_url( '/' );
	}
}
