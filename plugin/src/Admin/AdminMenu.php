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
		add_action( 'wp_dashboard_setup', array( $this, 'register_dashboard_widget' ) );

		// AJAX handlers.
		$mappings_page = new MappingsPage();
		add_action( 'wp_ajax_cfi_meta_keys', array( $mappings_page, 'ajax_meta_keys' ) );
		add_action( 'wp_ajax_cfi_acf_fields', array( $mappings_page, 'ajax_acf_fields' ) );
		add_action( 'wp_ajax_cfi_test_mapping', array( $mappings_page, 'ajax_test_mapping' ) );

		$settings_page = new SettingsPage();
		add_action( 'wp_ajax_cfi_flex_test', array( $settings_page, 'ajax_flex_test' ) );
		add_action( 'wp_ajax_cfi_flex_enable', array( $settings_page, 'ajax_flex_enable' ) );

		$preview_page = new PreviewPage();
		add_action( 'wp_ajax_cfi_validate_attachment', array( $preview_page, 'ajax_validate_attachment' ) );
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
		// Only load on our pages and the main dashboard (for widget).
		$is_our_page   = strpos( $hook_suffix, 'cfi-' ) !== false || $hook_suffix === 'toplevel_page_cfi-settings';
		$is_dashboard  = $hook_suffix === 'index.php';

		if ( ! $is_our_page && ! $is_dashboard ) {
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

		$settings = ( new \CFI\Repos\SettingsRepo() )->get();

		wp_localize_script(
			'cfi-admin',
			'cfiAdmin',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'cfi_admin' ),
				'flexStatus' => $settings['flex_status'],
				'flexLabels' => array(
					'enabled'  => __( 'Enabled', 'cfi-images-sync' ),
					'disabled' => __( 'Disabled', 'cfi-images-sync' ),
					'unknown'  => __( 'Unknown', 'cfi-images-sync' ),
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
			'cfi_status_widget',
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
		$icon = '<svg style="width:18px;height:18px;vertical-align:text-bottom;margin-right:6px;" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">'
			. '<path d="M19.027 11.311c-.056 0-.106.042-.127.097l-.337 1.156c-.148.499-.092.956.154 1.295.226.311.605.491 1.063.512l1.842.11a.16.16 0 0 1 .134.07.2.2 0 0 1 .021.152.24.24 0 0 1-.204.153l-1.92.11c-1.041.049-2.16.873-2.553 1.884l-.141.353c-.028.069.021.138.098.138h6.598a.17.17 0 0 0 .17-.125 4.7 4.7 0 0 0 .175-1.26c0-2.561-2.124-4.652-4.734-4.652-.077 0-.162 0-.24.007" fill="#fbad41"/>'
			. '<path d="M16.509 16.767c.148-.499.091-.956-.155-1.295-.225-.311-.605-.492-1.062-.512l-8.659-.111a.16.16 0 0 1-.134-.07.2.2 0 0 1-.02-.152.24.24 0 0 1 .203-.152l8.737-.11c1.034-.05 2.159-.873 2.553-1.884l.5-1.28a.27.27 0 0 0 .013-.167c-.562-2.506-2.834-4.375-5.55-4.375-2.504 0-4.628 1.592-5.388 3.8a2.6 2.6 0 0 0-1.793-.49c-1.203.117-2.167 1.065-2.286 2.25a2.6 2.6 0 0 0 .063.878C1.57 13.153 0 14.731 0 16.677q.002.26.035.519a.17.17 0 0 0 .169.145h15.981a.22.22 0 0 0 .204-.152z" fill="#f6821f"/>'
			. '</svg>';

		return '<span>' . $icon . esc_html__( 'CF Images Sync', 'cfi-images-sync' ) . '</span>';
	}

	/**
	 * Render the dashboard widget content.
	 *
	 * @return void
	 */
	public function render_dashboard_widget(): void {
		$settings = ( new \CFI\Repos\SettingsRepo() )->get();
		$mappings = ( new \CFI\Repos\MappingsRepo() )->all();
		$presets  = ( new \CFI\Repos\PresetsRepo() )->all();

		$flex_status  = $settings['flex_status'];
		$flex_checked = (int) $settings['flex_checked_at'];
		$api_tested   = (int) $settings['api_tested_at'];
		$account_hash = $settings['account_hash'];
		$account_id   = $settings['account_id'];
		$has_token    = $settings['api_token'] !== '';

		$hash_valid = preg_match( '/^[A-Za-z0-9_-]{10,}$/', $account_hash );
		$id_valid   = preg_match( '/^[a-f0-9]{32}$/', $account_id );
		?>
		<div class="cfi-widget">
			<!-- Connection Status -->
			<table class="cfi-widget-table">
				<tr>
					<td><?php esc_html_e( 'API Access', 'cfi-images-sync' ); ?></td>
					<td>
						<?php if ( $api_tested > 0 ) : ?>
							<span class="cfi-status-indicator cfi-status--ok"><?php esc_html_e( 'OK', 'cfi-images-sync' ); ?></span>
						<?php elseif ( $has_token && $id_valid ) : ?>
							<span class="cfi-status-indicator cfi-status--pending"><?php esc_html_e( 'Not tested', 'cfi-images-sync' ); ?></span>
						<?php elseif ( ! $has_token ) : ?>
							<span class="cfi-status-indicator cfi-status--error"><?php esc_html_e( 'Missing token', 'cfi-images-sync' ); ?></span>
						<?php else : ?>
							<span class="cfi-status-indicator cfi-status--error"><?php esc_html_e( 'Invalid ID', 'cfi-images-sync' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Flexible Variants', 'cfi-images-sync' ); ?></td>
					<td>
						<?php if ( $flex_status === 'enabled' ) : ?>
							<span class="cfi-status-indicator cfi-status--ok"><?php esc_html_e( 'Enabled', 'cfi-images-sync' ); ?></span>
						<?php elseif ( $flex_status === 'disabled' ) : ?>
							<span class="cfi-status-indicator cfi-status--error"><?php esc_html_e( 'Disabled', 'cfi-images-sync' ); ?></span>
						<?php else : ?>
							<span class="cfi-status-indicator cfi-status--pending"><?php esc_html_e( 'Unknown', 'cfi-images-sync' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Account Hash', 'cfi-images-sync' ); ?></td>
					<td>
						<?php if ( $hash_valid ) : ?>
							<span class="cfi-status-indicator cfi-status--ok"><?php esc_html_e( 'Configured', 'cfi-images-sync' ); ?></span>
						<?php elseif ( $account_hash === '' ) : ?>
							<span class="cfi-status-indicator cfi-status--error"><?php esc_html_e( 'Missing', 'cfi-images-sync' ); ?></span>
						<?php else : ?>
							<span class="cfi-status-indicator cfi-status--pending"><?php esc_html_e( 'Check format', 'cfi-images-sync' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
			</table>

			<?php if ( $flex_checked > 0 ) : ?>
				<p class="cfi-widget-timestamp">
					<?php
					printf(
						/* translators: %s: human-readable time difference */
						esc_html__( 'Last checked: %s ago', 'cfi-images-sync' ),
						esc_html( human_time_diff( $flex_checked ) )
					);
					?>
				</p>
			<?php endif; ?>

			<!-- Stats -->
			<div class="cfi-widget-stats">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=cfi-presets' ) ); ?>">
					<span class="dashicons dashicons-images-alt2"></span>
					<?php echo esc_html( count( $presets ) ); ?> <?php esc_html_e( 'Presets', 'cfi-images-sync' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=cfi-mappings' ) ); ?>">
					<span class="dashicons dashicons-randomize"></span>
					<?php echo esc_html( count( $mappings ) ); ?> <?php esc_html_e( 'Mappings', 'cfi-images-sync' ); ?>
				</a>
			</div>

			<!-- Actions -->
			<div class="cfi-widget-actions">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=cfi-settings' ) ); ?>"><?php esc_html_e( 'Settings', 'cfi-images-sync' ); ?></a>
				<span class="cfi-widget-sep">·</span>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=cfi-preview' ) ); ?>"><?php esc_html_e( 'Preview', 'cfi-images-sync' ); ?></a>
				<span class="cfi-widget-sep">·</span>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=cfi-logs' ) ); ?>"><?php esc_html_e( 'Logs', 'cfi-images-sync' ); ?></a>
			</div>
		</div>
		<?php
	}
}
