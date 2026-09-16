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
		foreach ( array(
			'assemblypress'            => array( __( 'Members', 'assemblypress' ), 'list_users' ),
			'assemblypress-fields'     => array( __( 'Profile Fields', 'assemblypress' ), Capabilities::manage_profile_schema() ),
			'assemblypress-membership' => array( __( 'Invitations and requests', 'assemblypress' ), 'manage_options' ),
			'assemblypress-settings'   => array( __( 'Settings', 'assemblypress' ), 'manage_options' ),
			'assemblypress-design'     => array( __( 'Design', 'assemblypress' ), 'edit_theme_options' ),
		) as $slug => $page ) {
			add_submenu_page( self::PAGE_SLUG, $page[0], $page[0], $page[1], $slug, array( self::class, 'render_screen' ) );
		}
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook_suffix Hook suffix.
	 * @return void
	 */
	public static function enqueue_assets( $hook_suffix ) {
		if ( false === strpos( $hook_suffix, 'assemblypress' ) ) {
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
		if ( current_user_can( 'upload_files' ) ) {
			wp_enqueue_media(); }

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
				'restUrl'            => esc_url_raw( rest_url( Rest_Controller::NAMESPACE ) ),
				'adminUrl'           => esc_url_raw( admin_url() ),
				'screen'             => get_current_screen()->id,
				'directoryUrl'       => Settings::directory_url(),
				'directoryEdit'      => Settings::get()['directory_page'] ? get_edit_post_link( Settings::get()['directory_page'], 'raw' ) : admin_url( 'post-new.php?post_type=page' ),
				'profileTemplateUrl' => add_query_arg(
					array(
						'postType' => 'wp_template',
						'canvas'   => 'edit',
						'postId'   => get_stylesheet() . '//assemblypress-member-profile',
					),
					admin_url( 'site-editor.php' )
				),
				'editTemplateUrl'    => add_query_arg(
					array(
						'postType' => 'wp_template',
						'canvas'   => 'edit',
						'postId'   => get_stylesheet() . '//assemblypress-edit-profile',
					),
					admin_url( 'site-editor.php' )
				),
				'canInvite'          => current_user_can( 'create_users' ),
				'nonce'              => wp_create_nonce( 'wp_rest' ),
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
