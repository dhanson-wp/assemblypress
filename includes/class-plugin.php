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
		add_action( 'init', array( Config_Entities::class, 'register' ), 5 );
		add_action( 'init', array( Profile_Fields::class, 'register_user_meta' ), 20 );
		add_action( 'init', array( Blocks::class, 'register' ), 30 );
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
		Config_Entities::register();
		Profile_Fields::seed_defaults();
		flush_rewrite_rules();
	}
}
