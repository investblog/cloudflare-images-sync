<?php
/**
 * Logs admin page.
 *
 * @package CloudflareImagesSync
 */

namespace CFIMG\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CFIMG\Repos\LogsRepo;

/**
 * Display and clear sync logs (ring buffer).
 */
class LogsPage {

	use AdminNotice;

	/**
	 * Logs repository instance.
	 *
	 * @var LogsRepo
	 */
	private LogsRepo $repo;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->repo = new LogsRepo();
	}

	/**
	 * Handle actions before headers are sent (PRG pattern).
	 *
	 * @return void
	 */
	public function handle_actions(): void {
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_SERVER['REQUEST_METHOD'] ) || $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
			return;
		}

		if ( isset( $_POST['cfimg_clear_logs'] ) ) {
			check_admin_referer( 'cfimg_clear_logs' );
			$this->repo->clear();
			$this->redirect_with_notice(
				admin_url( 'admin.php?page=cfimg-logs' ),
				__( 'Logs cleared.', 'images-sync-for-cloudflare' )
			);
		}
	}

	/**
	 * Render the logs page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'images-sync-for-cloudflare' ) );
		}

		$items = $this->repo->all();

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'CF Images — Logs', 'images-sync-for-cloudflare' ); ?></h1>

			<?php $this->render_notice(); ?>

			<?php if ( empty( $items ) ) : ?>
				<p><?php esc_html_e( 'No log entries yet. Events will appear here as the plugin runs.', 'images-sync-for-cloudflare' ); ?></p>
			<?php else : ?>
				<div class="cfimg-tablenav">
					<form method="post">
						<?php wp_nonce_field( 'cfimg_clear_logs' ); ?>
						<input type="submit" name="cfimg_clear_logs" class="button" value="<?php esc_attr_e( 'Clear Logs', 'images-sync-for-cloudflare' ); ?>" onclick="return confirm('<?php esc_attr_e( 'Clear all logs?', 'images-sync-for-cloudflare' ); ?>');" />
					</form>
					<?php /* translators: %d: number of log entries */ ?>
					<span class="cfimg-count-badge"><?php echo esc_html( sprintf( __( '%d entries', 'images-sync-for-cloudflare' ), count( $items ) ) ); ?></span>
				</div>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Time', 'images-sync-for-cloudflare' ); ?></th>
							<th><?php esc_html_e( 'Level', 'images-sync-for-cloudflare' ); ?></th>
							<th><?php esc_html_e( 'Message', 'images-sync-for-cloudflare' ); ?></th>
							<th><?php esc_html_e( 'Post', 'images-sync-for-cloudflare' ); ?></th>
							<th><?php esc_html_e( 'Mapping', 'images-sync-for-cloudflare' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( array_reverse( $items ) as $entry ) : ?>
							<tr>
								<td><?php echo esc_html( wp_date( 'Y-m-d H:i:s', $entry['t'] ?? 0 ) ); ?></td>
								<td><span class="cfimg-log-level cfimg-log-level--<?php echo esc_attr( $entry['lvl'] ?? 'info' ); ?>"><?php echo esc_html( $entry['lvl'] ?? '' ); ?></span></td>
								<td><?php echo esc_html( $entry['msg'] ?? '' ); ?></td>
								<td><?php echo isset( $entry['post_id'] ) ? esc_html( $entry['post_id'] ) : '—'; ?></td>
								<td><?php echo isset( $entry['mapping_id'] ) ? '<code>' . esc_html( $entry['mapping_id'] ) . '</code>' : '—'; ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}
}
