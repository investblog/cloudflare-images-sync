<?php
/**
 * Admin menu registration.
 *
 * @package CloudflareImagesSync
 */

namespace CFI\Admin;

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

		add_menu_page(
			__( 'Cloudflare Images', 'cloudflare-images-sync' ),
			__( 'CF Images', 'cloudflare-images-sync' ),
			$capability,
			'cfi-settings',
			array( $settings_page, 'render' ),
			$icon,
			81
		);

		// Rename the auto-generated first submenu item from "CF Images" to "Settings".
		add_submenu_page(
			'cfi-settings',
			__( 'Settings', 'cloudflare-images-sync' ),
			__( 'Settings', 'cloudflare-images-sync' ),
			$capability,
			'cfi-settings',
			array( $settings_page, 'render' )
		);

		add_submenu_page(
			'cfi-settings',
			__( 'Presets', 'cloudflare-images-sync' ),
			__( 'Presets', 'cloudflare-images-sync' ),
			$capability,
			'cfi-presets',
			array( new PresetsPage(), 'render' )
		);

		add_submenu_page(
			'cfi-settings',
			__( 'Mappings', 'cloudflare-images-sync' ),
			__( 'Mappings', 'cloudflare-images-sync' ),
			$capability,
			'cfi-mappings',
			array( new MappingsPage(), 'render' )
		);

		add_submenu_page(
			'cfi-settings',
			__( 'Preview', 'cloudflare-images-sync' ),
			__( 'Preview', 'cloudflare-images-sync' ),
			$capability,
			'cfi-preview',
			array( new PreviewPage(), 'render' )
		);

		add_submenu_page(
			'cfi-settings',
			__( 'Logs', 'cloudflare-images-sync' ),
			__( 'Logs', 'cloudflare-images-sync' ),
			$capability,
			'cfi-logs',
			array( new LogsPage(), 'render' )
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
