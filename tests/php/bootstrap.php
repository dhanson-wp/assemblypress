<?php
/**
 * PHPUnit bootstrap placeholder.
 *
 * @package AssemblyPress
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "WordPress test suite not found. Set WP_TESTS_DIR.\n";
	exit( 1 );
}

$autoload = dirname( __DIR__, 2 ) . '/vendor/autoload.php';
if ( file_exists( $autoload ) ) {
	require_once $autoload;
}

require_once $_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () {
		require dirname( __DIR__, 2 ) . '/assemblypress.php';
	}
);

require $_tests_dir . '/includes/bootstrap.php';
