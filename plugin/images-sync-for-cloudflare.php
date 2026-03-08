<?php
/**
 * Plugin Name:       Images Sync for Cloudflare
 * Plugin URI:        https://github.com/investblog/cloudflare-images-sync
 * Description:       Auto-sync WordPress images to Cloudflare Images — optimized CDN URLs stored in post meta.
 * Version:           1.0.8
 * Requires at least: 6.2
 * Requires PHP:      8.0
 * Author:            301.st
 * Author URI:        https://301.st
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       images-sync-for-cloudflare
 * Domain Path:       /languages
 *
 * @package CloudflareImagesSync
 */

namespace CFIMG;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin constants.
 */
define( 'CFIMG_VERSION', '1.0.8' );
define( 'CFIMG_PLUGIN_FILE', __FILE__ );
define( 'CFIMG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CFIMG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CFIMG_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoloader for PSR-4 compliant classes.
 *
 * Maps CFIMG\Repos\SettingsRepo → src/Repos/SettingsRepo.php
 *
 * @param string $class The fully-qualified class name.
 */
spl_autoload_register(
	function ( $class ) {
		$prefix   = 'CFIMG\\';
		$base_dir = CFIMG_PLUGIN_DIR . 'src/';

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
function cfimg_activate() {
	$repos = new Repos\PresetsRepo();
	$repos->seed_defaults();

	// Migrate plain-text token to encrypted storage.
	cfimg_migrate_token();
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\\cfimg_activate' );

/**
 * Migrate plain-text API token to encrypted storage.
 *
 * @return void
 */
function cfimg_migrate_token(): void {
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
function cfimg_deactivate() {
	// Nothing to clean up on deactivation for now.
}
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\cfimg_deactivate' );

/**
 * Load plugin translations.
 *
 * Since WordPress 4.6, translations for plugins hosted on .org are loaded
 * automatically. For GitHub / private distributions, WordPress still picks
 * up .mo files from wp-content/languages/plugins/ without this call.
 */

/**
 * Initialize the plugin.
 */
function cfimg_init() {
	// TODO: Remove this cfi_ → cfimg_ migration block after acceptance into the WP.org repository.
	// Migrate cfi_ prefixed options/meta to cfimg_ (added in 1.0.6).
	$db_version = get_option( 'cfimg_db_version', false );
	if ( $db_version === false ) {
		$old_version = get_option( 'cfi_db_version', false );
		if ( $old_version !== false ) {
			// Rename options.
			$old_options = array(
				'cfi_settings',
				'cfi_presets',
				'cfi_mappings',
				'cfi_logs',
				'cfi_demo_image_id',
				'cfi_demo_sig',
				'cfi_demo_updated_at',
				'cfi_api_token_encrypted',
				'cfi_db_version',
			);
			foreach ( $old_options as $old_key ) {
				$new_key = str_replace( 'cfi_', 'cfimg_', $old_key );
				$val     = get_option( $old_key );
				if ( $val !== false ) {
					update_option( $new_key, $val, false );
					delete_option( $old_key );
				}
			}
			// Rename post meta.
			global $wpdb;
			$meta_renames = array(
				'cfi_preview_image_id' => 'cfimg_preview_image_id',
				'cfi_preview_sig'      => 'cfimg_preview_sig',
				'_cfi_cf_image_id'     => '_cfimg_cf_image_id',
				'_cfi_sig'             => '_cfimg_sig',
			);
			foreach ( $meta_renames as $old => $new ) {
				$wpdb->update( $wpdb->postmeta, array( 'meta_key' => $new ), array( 'meta_key' => $old ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			}
			update_option( 'cfimg_db_version', CFIMG_VERSION, false );
		}
	}

	// Run migration for plugin updates (token encryption added in 1.0.0).
	$db_version = get_option( 'cfimg_db_version', '0' );
	if ( version_compare( $db_version, '1.0.0', '<' ) ) {
		cfimg_migrate_token();
		update_option( 'cfimg_db_version', CFIMG_VERSION, false );
	}

	// Register save_post / acf/save_post hooks based on mappings.
	$hooks = new Core\Hooks();
	$hooks->init();

	// Register Action Scheduler bulk sync handler.
	Jobs\BulkEnqueuer::register();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\cfimg_init' );

/**
 * Action Scheduler callback for single-post sync.
 *
 * @param int    $post_id    Post ID.
 * @param string $mapping_id Mapping ID.
 */
function cfimg_sync_single_callback( int $post_id, string $mapping_id ): void {
	$repo    = new Repos\MappingsRepo();
	$mapping = $repo->find( $mapping_id );

	if ( $mapping === null ) {
		return;
	}

	$engine = new Core\SyncEngine();
	$engine->sync( $post_id, $mapping );
}
add_action( 'cfimg_sync_single', __NAMESPACE__ . '\\cfimg_sync_single_callback', 10, 2 );

/**
 * Initialize admin functionality.
 */
function cfimg_admin_init() {
	if ( ! is_admin() ) {
		return;
	}

	$admin_menu = new Admin\AdminMenu();
	$admin_menu->init();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\cfimg_admin_init' );

/**
 * Add Settings link to plugin action links.
 *
 * @param array $links Existing plugin action links.
 * @return array Modified plugin action links.
 */
function cfimg_plugin_action_links( array $links ): array {
	$settings_link = sprintf(
		'<a href="%s">%s</a>',
		esc_url( admin_url( 'admin.php?page=cfimg-settings' ) ),
		esc_html__( 'Settings', 'images-sync-for-cloudflare' )
	);
	array_unshift( $links, $settings_link );
	return $links;
}
add_filter( 'plugin_action_links_' . CFIMG_PLUGIN_BASENAME, __NAMESPACE__ . '\\cfimg_plugin_action_links' );

/**
 * Initialize REST API.
 */
function cfimg_rest_init() {
	// REST controllers will be registered here.
}
add_action( 'rest_api_init', __NAMESPACE__ . '\\cfimg_rest_init' );

/**
 * Register WP-CLI commands.
 */
function cfimg_cli_init() {
	if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
		return;
	}

	\WP_CLI::add_command( 'cfimg', CLI\Commands::class );
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\cfimg_cli_init' );
