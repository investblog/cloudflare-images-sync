<?php
/**
 * PHPUnit bootstrap for Cloudflare Images Sync tests.
 *
 * Loads the WordPress test library and the plugin.
 *
 * @package CloudflareImagesSync
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find {$_tests_dir}/includes/functions.php\n";
	echo "Set WP_TESTS_DIR or run tests inside wp-env.\n";
	exit( 1 );
}

require_once $_tests_dir . '/includes/functions.php';

/**
 * Load the plugin before tests run.
 */
function _cfimg_manually_load_plugin() {
	require dirname( __DIR__ ) . '/plugin/images-sync-for-cloudflare.php';
}
tests_add_filter( 'muplugins_loaded', '_cfimg_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';
