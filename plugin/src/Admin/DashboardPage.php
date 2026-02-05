<?php
/**
 * Dashboard admin page.
 *
 * @package CloudflareImagesSync
 */

namespace CFI\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CFI\Repos\LogsRepo;
use CFI\Repos\MappingsRepo;
use CFI\Repos\PresetsRepo;
use CFI\Repos\SettingsRepo;

/**
 * Dashboard page: connection status overview and quick links.
 */
class DashboardPage {

	/**
	 * Settings repository instance.
	 *
	 * @var SettingsRepo
	 */
	private SettingsRepo $settings_repo;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->settings_repo = new SettingsRepo();
	}

	/**
	 * Render the dashboard page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'cfi-images-sync' ) );
		}

		$settings = $this->settings_repo->get();
		$mappings = ( new MappingsRepo() )->all();
		$presets  = ( new PresetsRepo() )->all();
		$logs     = ( new LogsRepo() )->recent( 5 );

		?>
		<div class="wrap cfi-dashboard">
			<h1 class="cfi-dashboard-title">
				<?php $this->render_cloudflare_icon(); ?>
				<?php esc_html_e( 'Images Sync for Cloudflare', 'cfi-images-sync' ); ?>
			</h1>

			<div class="cfi-dashboard-grid">
				<!-- Connection Status -->
				<div class="cfi-dashboard-card cfi-dashboard-card--status">
					<?php $this->render_status_box( $settings ); ?>
				</div>

				<!-- Quick Stats -->
				<div class="cfi-dashboard-card">
					<h3><?php esc_html_e( 'Quick Stats', 'cfi-images-sync' ); ?></h3>
					<ul class="cfi-stats-list">
						<li>
							<span class="cfi-stat-value"><?php echo esc_html( count( $presets ) ); ?></span>
							<span class="cfi-stat-label"><?php esc_html_e( 'Presets', 'cfi-images-sync' ); ?></span>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=cfi-presets' ) ); ?>" class="cfi-stat-link"><?php esc_html_e( 'Manage', 'cfi-images-sync' ); ?></a>
						</li>
						<li>
							<span class="cfi-stat-value"><?php echo esc_html( count( $mappings ) ); ?></span>
							<span class="cfi-stat-label"><?php esc_html_e( 'Mappings', 'cfi-images-sync' ); ?></span>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=cfi-mappings' ) ); ?>" class="cfi-stat-link"><?php esc_html_e( 'Manage', 'cfi-images-sync' ); ?></a>
						</li>
					</ul>
				</div>

				<!-- Quick Actions -->
				<div class="cfi-dashboard-card">
					<h3><?php esc_html_e( 'Quick Actions', 'cfi-images-sync' ); ?></h3>
					<div class="cfi-quick-actions">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=cfi-preview' ) ); ?>" class="button">
							<?php esc_html_e( 'Preview Studio', 'cfi-images-sync' ); ?>
						</a>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=cfi-settings' ) ); ?>" class="button">
							<?php esc_html_e( 'Settings', 'cfi-images-sync' ); ?>
						</a>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=cfi-logs' ) ); ?>" class="button">
							<?php esc_html_e( 'View Logs', 'cfi-images-sync' ); ?>
						</a>
					</div>
				</div>

				<!-- Recent Activity -->
				<div class="cfi-dashboard-card cfi-dashboard-card--wide">
					<h3><?php esc_html_e( 'Recent Activity', 'cfi-images-sync' ); ?></h3>
					<?php if ( empty( $logs ) ) : ?>
						<p class="cfi-empty-state"><?php esc_html_e( 'No recent activity.', 'cfi-images-sync' ); ?></p>
					<?php else : ?>
						<table class="cfi-logs-mini">
							<tbody>
								<?php foreach ( $logs as $log ) : ?>
									<tr>
										<td class="cfi-log-level cfi-log-level--<?php echo esc_attr( $log['level'] ); ?>">
											<?php echo esc_html( $log['level'] ); ?>
										</td>
										<td class="cfi-log-message"><?php echo esc_html( $log['message'] ); ?></td>
										<td class="cfi-log-time"><?php echo esc_html( human_time_diff( strtotime( $log['time'] ) ) ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
						<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=cfi-logs' ) ); ?>"><?php esc_html_e( 'View all logs →', 'cfi-images-sync' ); ?></a></p>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the Cloudflare SVG icon.
	 *
	 * @return void
	 */
	private function render_cloudflare_icon(): void {
		?>
		<svg class="cfi-cloudflare-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" width="32" height="32">
			<path d="M19.027 11.311c-.056 0-.106.042-.127.097l-.337 1.156c-.148.499-.092.956.154 1.295.226.311.605.491 1.063.512l1.842.11a.16.16 0 0 1 .134.07.2.2 0 0 1 .021.152.24.24 0 0 1-.204.153l-1.92.11c-1.041.049-2.16.873-2.553 1.884l-.141.353c-.028.069.021.138.098.138h6.598a.17.17 0 0 0 .17-.125 4.7 4.7 0 0 0 .175-1.26c0-2.561-2.124-4.652-4.734-4.652-.077 0-.162 0-.24.007" fill="#fbad41"/>
			<path d="M16.509 16.767c.148-.499.091-.956-.155-1.295-.225-.311-.605-.492-1.062-.512l-8.659-.111a.16.16 0 0 1-.134-.07.2.2 0 0 1-.02-.152.24.24 0 0 1 .203-.152l8.737-.11c1.034-.05 2.159-.873 2.553-1.884l.5-1.28a.27.27 0 0 0 .013-.167c-.562-2.506-2.834-4.375-5.55-4.375-2.504 0-4.628 1.592-5.388 3.8a2.6 2.6 0 0 0-1.793-.49c-1.203.117-2.167 1.065-2.286 2.25a2.6 2.6 0 0 0 .063.878C1.57 13.153 0 14.731 0 16.677q.002.26.035.519a.17.17 0 0 0 .169.145h15.981a.22.22 0 0 0 .204-.152z" fill="#f6821f"/>
		</svg>
		<?php
	}

	/**
	 * Render the Connection Status box.
	 *
	 * @param array $settings Current settings.
	 * @return void
	 */
	private function render_status_box( array $settings ): void {
		$flex_status  = $settings['flex_status'];
		$flex_checked = (int) $settings['flex_checked_at'];
		$api_tested   = (int) $settings['api_tested_at'];
		$account_hash = $settings['account_hash'];
		$account_id   = $settings['account_id'];
		$has_token    = $settings['api_token'] !== '';

		$hash_valid = preg_match( '/^[A-Za-z0-9_-]{10,}$/', $account_hash );
		$id_valid   = preg_match( '/^[a-f0-9]{32}$/', $account_id );

		$all_ok = $api_tested > 0 && $flex_status === 'enabled' && $hash_valid;
		?>
		<h3>
			<?php esc_html_e( 'Connection Status', 'cfi-images-sync' ); ?>
			<?php if ( $all_ok ) : ?>
				<span class="cfi-status-badge cfi-status-badge--ok"><?php esc_html_e( 'All OK', 'cfi-images-sync' ); ?></span>
			<?php endif; ?>
		</h3>

		<dl class="cfi-status-list">
			<dt><?php esc_html_e( 'API Access', 'cfi-images-sync' ); ?></dt>
			<dd>
				<?php if ( $api_tested > 0 ) : ?>
					<span class="cfi-status-indicator cfi-status--ok"><?php esc_html_e( 'OK', 'cfi-images-sync' ); ?></span>
				<?php elseif ( $has_token && $id_valid ) : ?>
					<span class="cfi-status-indicator cfi-status--pending"><?php esc_html_e( 'Not tested', 'cfi-images-sync' ); ?></span>
				<?php elseif ( ! $has_token ) : ?>
					<span class="cfi-status-indicator cfi-status--error"><?php esc_html_e( 'Missing token', 'cfi-images-sync' ); ?></span>
				<?php else : ?>
					<span class="cfi-status-indicator cfi-status--error"><?php esc_html_e( 'Invalid Account ID', 'cfi-images-sync' ); ?></span>
				<?php endif; ?>
			</dd>

			<dt><?php esc_html_e( 'Flexible Variants', 'cfi-images-sync' ); ?></dt>
			<dd>
				<?php if ( $flex_status === 'enabled' ) : ?>
					<span class="cfi-status-indicator cfi-status--ok"><?php esc_html_e( 'Enabled', 'cfi-images-sync' ); ?></span>
				<?php elseif ( $flex_status === 'disabled' ) : ?>
					<span class="cfi-status-indicator cfi-status--error"><?php esc_html_e( 'Disabled', 'cfi-images-sync' ); ?></span>
				<?php else : ?>
					<span class="cfi-status-indicator cfi-status--pending"><?php esc_html_e( 'Unknown', 'cfi-images-sync' ); ?></span>
				<?php endif; ?>
			</dd>

			<dt><?php esc_html_e( 'Account Hash', 'cfi-images-sync' ); ?></dt>
			<dd>
				<?php if ( $hash_valid ) : ?>
					<span class="cfi-status-indicator cfi-status--ok"><?php esc_html_e( 'Configured', 'cfi-images-sync' ); ?></span>
				<?php elseif ( $account_hash === '' ) : ?>
					<span class="cfi-status-indicator cfi-status--error"><?php esc_html_e( 'Missing', 'cfi-images-sync' ); ?></span>
				<?php else : ?>
					<span class="cfi-status-indicator cfi-status--pending"><?php esc_html_e( 'Check format', 'cfi-images-sync' ); ?></span>
				<?php endif; ?>
			</dd>
		</dl>

		<?php if ( $flex_checked > 0 ) : ?>
			<p class="cfi-status-timestamp">
				<?php
				printf(
					/* translators: %s: human-readable time difference */
					esc_html__( 'Last checked: %s ago', 'cfi-images-sync' ),
					esc_html( human_time_diff( $flex_checked ) )
				);
				?>
			</p>
		<?php endif; ?>

		<p class="cfi-status-actions">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=cfi-settings' ) ); ?>" class="button button-primary">
				<?php esc_html_e( 'Configure Settings', 'cfi-images-sync' ); ?>
			</a>
		</p>
		<?php
	}
}
