<?php
/**
 * Admin menu registration.
 *
 * @package CloudflareImagesSync
 */

namespace CFI\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the admin menu and sub-pages.
 */
class AdminMenu {

	/**
	 * Initialize admin hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( $this, 'maybe_show_config_warning' ) );

		// AJAX handlers.
		$mappings_page = new MappingsPage();
		add_action( 'wp_ajax_cfi_meta_keys', array( $mappings_page, 'ajax_meta_keys' ) );
		add_action( 'wp_ajax_cfi_acf_fields', array( $mappings_page, 'ajax_acf_fields' ) );
		add_action( 'wp_ajax_cfi_test_mapping', array( $mappings_page, 'ajax_test_mapping' ) );
	}

	/**
	 * Register the top-level menu and sub-pages.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		$capability    = 'manage_options';
		$icon          = 'dashicons-cloud';
		$settings_page = new SettingsPage();
		$presets_page  = new PresetsPage();
		$mappings_page = new MappingsPage();
		$preview_page  = new PreviewPage();
		$logs_page     = new LogsPage();

		$hook = add_menu_page(
			__( 'Images Sync for Cloudflare', 'cfi-images-sync' ),
			__( 'CF Images', 'cfi-images-sync' ),
			$capability,
			'cfi-settings',
			array( $settings_page, 'render' ),
			$icon,
			81
		);
		add_action( 'load-' . $hook, array( $settings_page, 'handle_actions' ) );

		// Rename the auto-generated first submenu item from "CF Images" to "Settings".
		add_submenu_page(
			'cfi-settings',
			__( 'Settings', 'cfi-images-sync' ),
			__( 'Settings', 'cfi-images-sync' ),
			$capability,
			'cfi-settings',
			array( $settings_page, 'render' )
		);

		$hook = add_submenu_page(
			'cfi-settings',
			__( 'Presets', 'cfi-images-sync' ),
			__( 'Presets', 'cfi-images-sync' ),
			$capability,
			'cfi-presets',
			array( $presets_page, 'render' )
		);
		add_action( 'load-' . $hook, array( $presets_page, 'handle_actions' ) );

		$hook = add_submenu_page(
			'cfi-settings',
			__( 'Mappings', 'cfi-images-sync' ),
			__( 'Mappings', 'cfi-images-sync' ),
			$capability,
			'cfi-mappings',
			array( $mappings_page, 'render' )
		);
		add_action( 'load-' . $hook, array( $mappings_page, 'handle_actions' ) );

		$hook = add_submenu_page(
			'cfi-settings',
			__( 'Preview', 'cfi-images-sync' ),
			__( 'Preview', 'cfi-images-sync' ),
			$capability,
			'cfi-preview',
			array( $preview_page, 'render' )
		);
		add_action( 'load-' . $hook, array( $preview_page, 'handle_actions' ) );

		$hook = add_submenu_page(
			'cfi-settings',
			__( 'Logs', 'cfi-images-sync' ),
			__( 'Logs', 'cfi-images-sync' ),
			$capability,
			'cfi-logs',
			array( $logs_page, 'render' )
		);
		add_action( 'load-' . $hook, array( $logs_page, 'handle_actions' ) );
	}

	/**
	 * Show a warning banner when Cloudflare credentials are not configured.
	 *
	 * @return void
	 */
	public function maybe_show_config_warning(): void {
		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, 'cfi-' ) === false ) {
			return;
		}

		// Skip on the settings page itself — the user is likely configuring right now.
		if ( $screen->id === 'toplevel_page_cfi-settings' ) {
			return;
		}

		$settings = ( new \CFI\Repos\SettingsRepo() )->get();
		$missing  = array();

		if ( $settings['account_id'] === '' ) {
			$missing[] = 'Account ID';
		}
		if ( $settings['account_hash'] === '' ) {
			$missing[] = 'Account Hash';
		}
		if ( $settings['api_token'] === '' ) {
			$missing[] = 'API Token';
		}

		if ( empty( $missing ) ) {
			return;
		}

		$settings_url = admin_url( 'admin.php?page=cfi-settings' );
		printf(
			'<div class="notice notice-warning"><p><strong>%s</strong> %s <a href="%s">%s</a></p></div>',
			esc_html__( 'Cloudflare Images not configured.', 'cfi-images-sync' ),
			esc_html(
				sprintf(
					/* translators: %s: comma-separated list of missing fields */
					__( 'Missing: %s.', 'cfi-images-sync' ),
					implode( ', ', $missing )
				)
			),
			esc_url( $settings_url ),
			esc_html__( 'Go to Settings →', 'cfi-images-sync' )
		);
	}

	/**
	 * Enqueue admin CSS and JS on plugin pages.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		// Only load on our pages.
		if ( strpos( $hook_suffix, 'cfi-' ) === false && $hook_suffix !== 'toplevel_page_cfi-settings' ) {
			return;
		}

		wp_enqueue_style(
			'cfi-admin',
			CFI_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			CFI_VERSION
		);

		wp_enqueue_script(
			'cfi-admin',
			CFI_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			CFI_VERSION,
			true
		);

		wp_localize_script(
			'cfi-admin',
			'cfiAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'cfi_admin' ),
			)
		);
	}
}
