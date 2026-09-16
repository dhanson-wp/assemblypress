<?php
/**
 * Main plugin coordinator.
 *
 * @package AssemblyPress
 */

namespace AssemblyPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Coordinates AssemblyPress services.
 */
final class Plugin {
	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'init', array( Membership::class, 'register' ) );
		add_action( 'init', array( Joining::class, 'register' ) );
		add_filter( 'registration_errors', array( Joining::class, 'core_registration' ) );
		add_filter( 'register_url', array( Joining::class, 'registration_url' ) );
		add_action( 'init', array( Directory_Blocks::class, 'register' ), 31 );
		add_action( 'template_redirect', array( Joining::class, 'handle' ), 0 );
		add_action( 'rest_api_init', array( Owner_API::class, 'register' ) );
		add_filter( 'query_vars', array( Member_Profiles::class, 'query_vars' ) );
		add_action( 'init', array( Member_Profiles::class, 'register_routes' ) );
		add_action( 'init', array( Member_Profiles::class, 'register_templates' ), 35 );
		add_action( 'wp_loaded', array( Member_Profiles::class, 'maybe_upgrade_routes' ) );
		add_action( 'template_redirect', array( Member_Profiles::class, 'prepare_request' ), 1 );
		add_filter( 'redirect_canonical', array( Member_Profiles::class, 'canonical_redirect' ) );
		add_filter( 'author_template_hierarchy', array( Member_Profiles::class, 'template_hierarchy' ) );
		add_filter( 'template_include', array( Member_Profiles::class, 'classic_template' ) );
		add_filter( 'document_title_parts', array( Member_Profiles::class, 'document_title' ) );
		add_filter( 'wp_robots', array( Member_Profiles::class, 'robots' ) );
		add_filter( 'render_block_context', array( Member_Profiles::class, 'block_context' ) );
		add_filter( 'pre_get_avatar_data', array( Member_Profiles::class, 'avatar_data' ), 10, 2 );
		add_action( 'init', array( Capabilities::class, 'grant_default_role_capabilities' ), 1 );
		add_action( 'init', array( Config_Entities::class, 'register' ), 5 );
		add_action( 'init', array( Profile_Field_Groups::class, 'ensure_starter_groups' ), 15 );
		add_action( 'init', array( Profile_Fields::class, 'register_user_meta' ), 20 );
		add_action( 'init', array( Blocks::class, 'register' ), 30 );
		add_action( 'template_redirect', array( Blocks::class, 'maybe_handle_edit_profile_form_submission' ) );
		add_action( 'rest_api_init', array( Rest_Controller::class, 'register_routes' ) );
		add_action( 'admin_menu', array( Admin::class, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( Admin::class, 'enqueue_assets' ) );
	}

	/**
	 * Seed default member profile configuration.
	 *
	 * @return void
	 */
	public static function activate() {
		Capabilities::grant_default_role_capabilities();
		Config_Entities::register();
		Profile_Field_Groups::ensure_starter_groups();
		Profile_Fields::seed_defaults();
		Member_Profiles::register_routes();
		flush_rewrite_rules();
		update_option( 'assemblypress_profile_routes_version', '1' );
	}
}
