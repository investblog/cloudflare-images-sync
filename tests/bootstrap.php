<?php
/**
 * PHPUnit bootstrap for Cloudflare Images Sync tests.
 *
 * Loads the WordPress test library and the plugin.
 * Works in both wp-env (plugin at current dir) and CI (plugin at ../plugin).
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

// Load PHPUnit Polyfills if available (required by WP test suite).
$_polyfills_candidates = array(
	dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php',
	'/tmp/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php',
);
foreach ( $_polyfills_candidates as $_pf ) {
	if ( file_exists( $_pf ) ) {
		require_once $_pf;
		break;
	}
}

require_once $_tests_dir . '/includes/functions.php';

/**
 * Load the plugin before tests run.
 */
function _cfimg_manually_load_plugin() {
	// wp-env: plugin is the current working directory.
	$candidates = array(
		dirname( __DIR__ ) . '/images-sync-for-cloudflare.php',
		dirname( __DIR__ ) . '/plugin/images-sync-for-cloudflare.php',
	);

	foreach ( $candidates as $path ) {
		if ( file_exists( $path ) ) {
			require $path;
			return;
		}
	}

	echo "Could not find plugin bootstrap file.\n";
	exit( 1 );
}
tests_add_filter( 'muplugins_loaded', '_cfimg_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';
