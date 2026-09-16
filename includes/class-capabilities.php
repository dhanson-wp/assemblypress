<?php
/**
 * AssemblyPress capabilities.
 *
 * @package AssemblyPress
 */

namespace AssemblyPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Capability names and checks.
 */
final class Capabilities {
	/**
	 * Capability for managing profile schema.
	 */
	const MANAGE_PROFILE_SCHEMA = 'manage_assemblypress_profile_schema';

	/**
	 * Get the capability required to manage profile schema.
	 *
	 * @return string
	 */
	public static function manage_profile_schema() {
		return self::MANAGE_PROFILE_SCHEMA;
	}

	/**
	 * Check whether the current user can manage profile schema.
	 *
	 * @return bool
	 */
	public static function current_user_can_manage_profile_schema() {
		return current_user_can( self::manage_profile_schema() );
	}

	/**
	 * Grant AssemblyPress capabilities to default WordPress roles.
	 *
	 * @return void
	 */
	public static function grant_default_role_capabilities() {
		$role = get_role( 'administrator' );

		if ( $role && ! $role->has_cap( self::MANAGE_PROFILE_SCHEMA ) ) {
			$role->add_cap( self::MANAGE_PROFILE_SCHEMA );
		}
	}
}
