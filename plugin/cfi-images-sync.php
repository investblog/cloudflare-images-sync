<?php
/**
 * Plugin Name:       Images Sync for Cloudflare
 * Plugin URI:        https://github.com/investblog/cloudflare-images-sync
 * Description:       Sync WordPress images to Cloudflare Images with flexible mappings, presets, and variant delivery.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            301st
 * Author URI:        https://301.st
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
define( 'CFI_VERSION', '1.0.0' );
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

	// Migrate plain-text token to encrypted storage.
	cfi_migrate_token();
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\\cfi_activate' );

/**
 * Migrate plain-text API token to encrypted storage.
 *
 * @return void
 */
function cfi_migrate_token(): void {
	$settings = get_option( Repos\OptionKeys::SETTINGS, array() );

	// Check if there's a plain-text token in settings.
	if ( ! empty( $settings['api_token'] ) && is_string( $settings['api_token'] ) ) {
		$token_storage = new Support\TokenStorage();

		// Only migrate if encrypted storage is empty.
		if ( ! $token_storage->has_token() ) {
			$token_storage->store( $settings['api_token'] );
		}

		// Remove plain-text token from settings.
		unset( $settings['api_token'] );
		update_option( Repos\OptionKeys::SETTINGS, $settings, false );
	}
}

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
	// Run migration for plugin updates (token encryption added in 1.0.0).
	$db_version = get_option( 'cfi_db_version', '0' );
	if ( version_compare( $db_version, '1.0.0', '<' ) ) {
		cfi_migrate_token();
		update_option( 'cfi_db_version', CFI_VERSION, false );
	}

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
 * Add Settings link to plugin action links.
 *
 * @param array $links Existing plugin action links.
 * @return array Modified plugin action links.
 */
function cfi_plugin_action_links( array $links ): array {
	$settings_link = sprintf(
		'<a href="%s">%s</a>',
		esc_url( admin_url( 'admin.php?page=cfi-settings' ) ),
		esc_html__( 'Settings', 'cfi-images-sync' )
	);
	array_unshift( $links, $settings_link );
	return $links;
}
add_filter( 'plugin_action_links_' . CFI_PLUGIN_BASENAME, __NAMESPACE__ . '\\cfi_plugin_action_links' );

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
