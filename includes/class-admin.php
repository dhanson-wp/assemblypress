<?php
/**
 * Admin screens.
 *
 * @package AssemblyPress
 */

namespace AssemblyPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AssemblyPress admin.
 */
final class Admin {
	const PAGE_SLUG = 'assemblypress';

	/**
	 * Register admin menu.
	 *
	 * @return void
	 */
	public static function register_menu() {
		add_menu_page(
			__( 'AssemblyPress', 'assemblypress' ),
			__( 'AssemblyPress', 'assemblypress' ),
			'list_users',
			self::PAGE_SLUG,
			array( self::class, 'render_screen' ),
			'dashicons-groups',
			58
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook_suffix Hook suffix.
	 * @return void
	 */
	public static function enqueue_assets( $hook_suffix ) {
		if ( 'toplevel_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		$asset_file = ASSEMBLYPRESS_PLUGIN_DIR . 'assets/build/admin/index.asset.php';
		$asset      = file_exists( $asset_file )
			? require $asset_file
			: array(
				'dependencies' => array( 'wp-api-fetch', 'wp-components', 'wp-element', 'wp-i18n' ),
				'version'      => ASSEMBLYPRESS_VERSION,
			);

		wp_enqueue_style( 'wp-components' );

		$style_path = ASSEMBLYPRESS_PLUGIN_DIR . 'assets/build/admin/style-index.css';
		if ( file_exists( $style_path ) ) {
			wp_enqueue_style(
				'assemblypress-admin',
				ASSEMBLYPRESS_PLUGIN_URL . 'assets/build/admin/style-index.css',
				array( 'wp-components' ),
				(string) filemtime( $style_path )
			);
		}

		wp_enqueue_script(
			'assemblypress-admin',
			ASSEMBLYPRESS_PLUGIN_URL . 'assets/build/admin/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_localize_script(
			'assemblypress-admin',
			'assemblyPressAdmin',
			array(
				'restUrl'  => esc_url_raw( rest_url( Rest_Controller::NAMESPACE ) ),
				'adminUrl' => esc_url_raw( admin_url() ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
			)
		);
	}

	/**
	 * Render admin root.
	 *
	 * @return void
	 */
	public static function render_screen() {
		echo '<div id="assemblypress-admin-root">';
		echo '<div class="assemblypress-admin"><span class="spinner is-active"></span> ';
		echo esc_html__( 'Loading AssemblyPress...', 'assemblypress' );
		echo '</div></div>';
	}
}
