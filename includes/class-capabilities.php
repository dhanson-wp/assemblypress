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
	 * Temporary capability for managing profile schema.
	 *
	 * This currently maps to a core user-management capability until
	 * AssemblyPress introduces product-specific capabilities.
	 */
	const MANAGE_PROFILE_SCHEMA = 'list_users';

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
}
