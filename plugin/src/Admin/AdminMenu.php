<?php
/**
 * Admin menu registration.
 *
 * @package CloudflareImagesSync
 */

namespace CFIMG\Admin;

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
		add_action( 'wp_dashboard_setup', array( $this, 'register_dashboard_widget' ) );

		// AJAX handlers.
		$mappings_page = new MappingsPage();
		add_action( 'wp_ajax_cfimg_meta_keys', array( $mappings_page, 'ajax_meta_keys' ) );
		add_action( 'wp_ajax_cfimg_acf_fields', array( $mappings_page, 'ajax_acf_fields' ) );
		add_action( 'wp_ajax_cfimg_test_mapping', array( $mappings_page, 'ajax_test_mapping' ) );

		$settings_page = new SettingsPage();
		add_action( 'wp_ajax_cfimg_flex_test', array( $settings_page, 'ajax_flex_test' ) );
		add_action( 'wp_ajax_cfimg_flex_enable', array( $settings_page, 'ajax_flex_enable' ) );

		$preview_page = new PreviewPage();
		add_action( 'wp_ajax_cfimg_validate_attachment', array( $preview_page, 'ajax_validate_attachment' ) );
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
			__( 'Images Sync for Cloudflare', 'images-sync-for-cloudflare' ),
			__( 'CF Images', 'images-sync-for-cloudflare' ),
			$capability,
			'cfimg-settings',
			array( $settings_page, 'render' ),
			$icon,
			81
		);
		add_action( 'load-' . $hook, array( $settings_page, 'handle_actions' ) );

		// Rename the auto-generated first submenu item from "CF Images" to "Settings".
		add_submenu_page(
			'cfimg-settings',
			__( 'Settings', 'images-sync-for-cloudflare' ),
			__( 'Settings', 'images-sync-for-cloudflare' ),
			$capability,
			'cfimg-settings',
			array( $settings_page, 'render' )
		);

		$hook = add_submenu_page(
			'cfimg-settings',
			__( 'Presets', 'images-sync-for-cloudflare' ),
			__( 'Presets', 'images-sync-for-cloudflare' ),
			$capability,
			'cfimg-presets',
			array( $presets_page, 'render' )
		);
		add_action( 'load-' . $hook, array( $presets_page, 'handle_actions' ) );

		$hook = add_submenu_page(
			'cfimg-settings',
			__( 'Mappings', 'images-sync-for-cloudflare' ),
			__( 'Mappings', 'images-sync-for-cloudflare' ),
			$capability,
			'cfimg-mappings',
			array( $mappings_page, 'render' )
		);
		add_action( 'load-' . $hook, array( $mappings_page, 'handle_actions' ) );

		$hook = add_submenu_page(
			'cfimg-settings',
			__( 'Preview', 'images-sync-for-cloudflare' ),
			__( 'Preview', 'images-sync-for-cloudflare' ),
			$capability,
			'cfimg-preview',
			array( $preview_page, 'render' )
		);
		add_action( 'load-' . $hook, array( $preview_page, 'handle_actions' ) );

		$hook = add_submenu_page(
			'cfimg-settings',
			__( 'Logs', 'images-sync-for-cloudflare' ),
			__( 'Logs', 'images-sync-for-cloudflare' ),
			$capability,
			'cfimg-logs',
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
		if ( ! $screen || strpos( $screen->id, 'cfimg-' ) === false ) {
			return;
		}

		// Skip on the settings page itself — the user is likely configuring right now.
		if ( $screen->id === 'toplevel_page_cfimg-settings' ) {
			return;
		}

		$settings = ( new \CFIMG\Repos\SettingsRepo() )->get();
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

		$settings_url = admin_url( 'admin.php?page=cfimg-settings' );
		printf(
			'<div class="notice notice-warning"><p><strong>%s</strong> %s <a href="%s">%s</a></p></div>',
			esc_html__( 'Cloudflare Images not configured.', 'images-sync-for-cloudflare' ),
			esc_html(
				sprintf(
					/* translators: %s: comma-separated list of missing fields */
					__( 'Missing: %s.', 'images-sync-for-cloudflare' ),
					implode( ', ', $missing )
				)
			),
			esc_url( $settings_url ),
			esc_html__( 'Go to Settings →', 'images-sync-for-cloudflare' )
		);
	}

	/**
	 * Enqueue admin CSS and JS on plugin pages.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		// Only load on our pages and the main dashboard (for widget).
		$is_our_page   = strpos( $hook_suffix, 'cfi-' ) !== false || $hook_suffix === 'toplevel_page_cfimg-settings';
		$is_dashboard  = $hook_suffix === 'index.php';

		if ( ! $is_our_page && ! $is_dashboard ) {
			return;
		}

		wp_enqueue_style(
			'cfimg-admin',
			CFIMG_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			CFIMG_VERSION
		);

		wp_enqueue_script(
			'cfimg-admin',
			CFIMG_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			CFIMG_VERSION,
			true
		);

		$settings = ( new \CFIMG\Repos\SettingsRepo() )->get();

		wp_localize_script(
			'cfimg-admin',
			'cfimgAdmin',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'cfimg_admin' ),
				'flexStatus' => $settings['flex_status'],
				'flexLabels' => array(
					'enabled'  => __( 'Enabled', 'images-sync-for-cloudflare' ),
					'disabled' => __( 'Disabled', 'images-sync-for-cloudflare' ),
					'unknown'  => __( 'Unknown', 'images-sync-for-cloudflare' ),
				),
			)
		);
	}

	/**
	 * Register the dashboard widget.
	 *
	 * @return void
	 */
	public function register_dashboard_widget(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'cfimg_status_widget',
			$this->get_widget_title(),
			array( $this, 'render_dashboard_widget' )
		);
	}

	/**
	 * Get widget title with Cloudflare icon.
	 *
	 * @return string
	 */
	private function get_widget_title(): string {
		$icon = '<svg class="cfimg-cloudflare-icon-svg" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">'
			. '<path d="M19.027 11.311c-.056 0-.106.042-.127.097l-.337 1.156c-.148.499-.092.956.154 1.295.226.311.605.491 1.063.512l1.842.11a.16.16 0 0 1 .134.07.2.2 0 0 1 .021.152.24.24 0 0 1-.204.153l-1.92.11c-1.041.049-2.16.873-2.553 1.884l-.141.353c-.028.069.021.138.098.138h6.598a.17.17 0 0 0 .17-.125 4.7 4.7 0 0 0 .175-1.26c0-2.561-2.124-4.652-4.734-4.652-.077 0-.162 0-.24.007" fill="#fbad41"/>'
			. '<path d="M16.509 16.767c.148-.499.091-.956-.155-1.295-.225-.311-.605-.492-1.062-.512l-8.659-.111a.16.16 0 0 1-.134-.07.2.2 0 0 1-.02-.152.24.24 0 0 1 .203-.152l8.737-.11c1.034-.05 2.159-.873 2.553-1.884l.5-1.28a.27.27 0 0 0 .013-.167c-.562-2.506-2.834-4.375-5.55-4.375-2.504 0-4.628 1.592-5.388 3.8a2.6 2.6 0 0 0-1.793-.49c-1.203.117-2.167 1.065-2.286 2.25a2.6 2.6 0 0 0 .063.878C1.57 13.153 0 14.731 0 16.677q.002.26.035.519a.17.17 0 0 0 .169.145h15.981a.22.22 0 0 0 .204-.152z" fill="#f6821f"/>'
			. '</svg>';

		return '<span>' . $icon . esc_html__( 'CF Images Sync', 'images-sync-for-cloudflare' ) . '</span>';
	}

	/**
	 * Render the dashboard widget content.
	 *
	 * @return void
	 */
	public function render_dashboard_widget(): void {
		$settings = ( new \CFIMG\Repos\SettingsRepo() )->get();
		$mappings = ( new \CFIMG\Repos\MappingsRepo() )->all();
		$presets  = ( new \CFIMG\Repos\PresetsRepo() )->all();

		$flex_status  = $settings['flex_status'];
		$flex_checked = (int) $settings['flex_checked_at'];
		$api_tested   = (int) $settings['api_tested_at'];
		$account_hash = $settings['account_hash'];
		$account_id   = $settings['account_id'];
		$has_token    = $settings['api_token'] !== '';

		$hash_valid = preg_match( '/^[A-Za-z0-9_-]{10,}$/', $account_hash );
		$id_valid   = preg_match( '/^[a-f0-9]{32}$/', $account_id );
		?>
		<div class="cfimg-widget">
			<!-- Connection Status -->
			<table class="cfimg-widget-table">
				<tr>
					<td><?php esc_html_e( 'API Access', 'images-sync-for-cloudflare' ); ?></td>
					<td>
						<?php if ( $api_tested > 0 ) : ?>
							<span class="cfimg-status-indicator cfimg-status--ok"><?php esc_html_e( 'OK', 'images-sync-for-cloudflare' ); ?></span>
						<?php elseif ( $has_token && $id_valid ) : ?>
							<span class="cfimg-status-indicator cfimg-status--pending"><?php esc_html_e( 'Not tested', 'images-sync-for-cloudflare' ); ?></span>
						<?php elseif ( ! $has_token ) : ?>
							<span class="cfimg-status-indicator cfimg-status--error"><?php esc_html_e( 'Missing token', 'images-sync-for-cloudflare' ); ?></span>
						<?php else : ?>
							<span class="cfimg-status-indicator cfimg-status--error"><?php esc_html_e( 'Invalid ID', 'images-sync-for-cloudflare' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Flexible Variants', 'images-sync-for-cloudflare' ); ?></td>
					<td>
						<?php if ( $flex_status === 'enabled' ) : ?>
							<span class="cfimg-status-indicator cfimg-status--ok"><?php esc_html_e( 'Enabled', 'images-sync-for-cloudflare' ); ?></span>
						<?php elseif ( $flex_status === 'disabled' ) : ?>
							<span class="cfimg-status-indicator cfimg-status--error"><?php esc_html_e( 'Disabled', 'images-sync-for-cloudflare' ); ?></span>
						<?php else : ?>
							<span class="cfimg-status-indicator cfimg-status--pending"><?php esc_html_e( 'Unknown', 'images-sync-for-cloudflare' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Account Hash', 'images-sync-for-cloudflare' ); ?></td>
					<td>
						<?php if ( $hash_valid ) : ?>
							<span class="cfimg-status-indicator cfimg-status--ok"><?php esc_html_e( 'Configured', 'images-sync-for-cloudflare' ); ?></span>
						<?php elseif ( $account_hash === '' ) : ?>
							<span class="cfimg-status-indicator cfimg-status--error"><?php esc_html_e( 'Missing', 'images-sync-for-cloudflare' ); ?></span>
						<?php else : ?>
							<span class="cfimg-status-indicator cfimg-status--pending"><?php esc_html_e( 'Check format', 'images-sync-for-cloudflare' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
			</table>

			<?php if ( $flex_checked > 0 ) : ?>
				<p class="cfimg-widget-timestamp">
					<?php
					printf(
						/* translators: %s: human-readable time difference */
						esc_html__( 'Last checked: %s ago', 'images-sync-for-cloudflare' ),
						esc_html( human_time_diff( $flex_checked ) )
					);
					?>
				</p>
			<?php endif; ?>

			<!-- Stats -->
			<div class="cfimg-widget-stats">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=cfimg-presets' ) ); ?>">
					<span class="dashicons dashicons-images-alt2"></span>
					<?php echo esc_html( count( $presets ) ); ?> <?php esc_html_e( 'Presets', 'images-sync-for-cloudflare' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=cfimg-mappings' ) ); ?>">
					<span class="dashicons dashicons-randomize"></span>
					<?php echo esc_html( count( $mappings ) ); ?> <?php esc_html_e( 'Mappings', 'images-sync-for-cloudflare' ); ?>
				</a>
			</div>

			<!-- Actions -->
			<div class="cfimg-widget-actions">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=cfimg-settings' ) ); ?>"><?php esc_html_e( 'Settings', 'images-sync-for-cloudflare' ); ?></a>
				<span class="cfimg-widget-sep">·</span>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=cfimg-preview' ) ); ?>"><?php esc_html_e( 'Preview', 'images-sync-for-cloudflare' ); ?></a>
				<span class="cfimg-widget-sep">·</span>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=cfimg-logs' ) ); ?>"><?php esc_html_e( 'Logs', 'images-sync-for-cloudflare' ); ?></a>
			</div>
		</div>
		<?php
	}
}
