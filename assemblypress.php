<?php
/**
 * Plugin Name: AssemblyPress
 * Description: A block-native community platform for WordPress.
 * Version: 0.1.0
 * Requires at least: 6.9
 * Requires PHP: 7.4
 * Author: AssemblyPress contributors
 * License: GPL-2.0-or-later
 * Text Domain: assemblypress
 *
 * @package AssemblyPress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ASSEMBLYPRESS_VERSION', '0.1.0' );
define( 'ASSEMBLYPRESS_PLUGIN_FILE', __FILE__ );
define( 'ASSEMBLYPRESS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ASSEMBLYPRESS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once ASSEMBLYPRESS_PLUGIN_DIR . 'includes/class-plugin.php';
require_once ASSEMBLYPRESS_PLUGIN_DIR . 'includes/class-config-entities.php';
require_once ASSEMBLYPRESS_PLUGIN_DIR . 'includes/class-profile-fields.php';
require_once ASSEMBLYPRESS_PLUGIN_DIR . 'includes/class-rest-controller.php';
require_once ASSEMBLYPRESS_PLUGIN_DIR . 'includes/class-blocks.php';
require_once ASSEMBLYPRESS_PLUGIN_DIR . 'includes/class-admin.php';

register_activation_hook( __FILE__, array( 'AssemblyPress\\Plugin', 'activate' ) );

add_action(
	'plugins_loaded',
	static function () {
		AssemblyPress\Plugin::instance()->init();
	}
);
