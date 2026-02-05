<?php
/**
 * Plugin Name:       Images Sync for Cloudflare
 * Description:       Sync WordPress images to Cloudflare Images with flexible mappings, presets, and variant delivery.
 * Version:           0.1.11-beta
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            investblog
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       cfi-images-sync
 * Domain Path:       /languages
 *
 * @package CloudflareImagesSync
 */

namespace CFI;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin constants.
 */
define( 'CFI_VERSION', '0.1.11-beta' );
define( 'CFI_PLUGIN_FILE', __FILE__ );
define( 'CFI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CFI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CFI_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoloader for PSR-4 compliant classes.
 *
 * Maps CFI\Repos\SettingsRepo → src/Repos/SettingsRepo.php
 *
 * @param string $class The fully-qualified class name.
 */
spl_autoload_register(
	function ( $class ) {
		$prefix   = 'CFI\\';
		$base_dir = CFI_PLUGIN_DIR . 'src/';

		$len = strlen( $prefix );
		if ( strncmp( $prefix, $class, $len ) !== 0 ) {
			return;
		}

		$relative_class = substr( $class, $len );
		$file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);

/**
 * Plugin activation hook.
 */
function cfi_activate() {
	$repos = new Repos\PresetsRepo();
	$repos->seed_defaults();
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\\cfi_activate' );

/**
 * Plugin deactivation hook.
 */
function cfi_deactivate() {
	// Nothing to clean up on deactivation for now.
}
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\cfi_deactivate' );

/**
 * Load plugin translations.
 */
function cfi_load_textdomain() {
	load_plugin_textdomain(
		'cfi-images-sync',
		false,
		dirname( plugin_basename( CFI_PLUGIN_FILE ) ) . '/languages'
	);
}
add_action( 'init', __NAMESPACE__ . '\\cfi_load_textdomain' );

/**
 * Initialize the plugin.
 */
function cfi_init() {
	// Register save_post / acf/save_post hooks based on mappings.
	$hooks = new Core\Hooks();
	$hooks->init();

	// Register Action Scheduler bulk sync handler.
	Jobs\BulkEnqueuer::register();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\cfi_init' );

/**
 * Action Scheduler callback for single-post sync.
 *
 * @param int    $post_id    Post ID.
 * @param string $mapping_id Mapping ID.
 */
function cfi_sync_single_callback( int $post_id, string $mapping_id ): void {
	$repo    = new Repos\MappingsRepo();
	$mapping = $repo->find( $mapping_id );

	if ( $mapping === null ) {
		return;
	}

	$engine = new Core\SyncEngine();
	$engine->sync( $post_id, $mapping );
}
add_action( 'cfi_sync_single', __NAMESPACE__ . '\\cfi_sync_single_callback', 10, 2 );

/**
 * Initialize admin functionality.
 */
function cfi_admin_init() {
	if ( ! is_admin() ) {
		return;
	}

	$admin_menu = new Admin\AdminMenu();
	$admin_menu->init();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\cfi_admin_init' );

/**
 * Initialize REST API.
 */
function cfi_rest_init() {
	// REST controllers will be registered here.
}
add_action( 'rest_api_init', __NAMESPACE__ . '\\cfi_rest_init' );

/**
 * Register WP-CLI commands.
 */
function cfi_cli_init() {
	if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
		return;
	}

	\WP_CLI::add_command( 'cfi', CLI\Commands::class );
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\cfi_cli_init' );
