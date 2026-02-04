<?php
/**
 * Logs admin page.
 *
 * @package CloudflareImagesSync
 */

namespace CFI\Admin;

use CFI\Repos\LogsRepo;

/**
 * Display and clear sync logs (ring buffer).
 */
class LogsPage {

	/**
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
	 * Handle actions and render the page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'cloudflare-images-sync' ) );
		}

		$message = '';

		if ( isset( $_POST['cfi_clear_logs'] ) ) {
			check_admin_referer( 'cfi_clear_logs' );
			$this->repo->clear();
			$message = __( 'Logs cleared.', 'cloudflare-images-sync' );
		}

		$items = $this->repo->all();

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Cloudflare Images — Logs', 'cloudflare-images-sync' ); ?></h1>

			<?php if ( $message ) : ?>
				<div class="notice notice-info is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
			<?php endif; ?>

			<form method="post" style="margin-bottom:12px;">
				<?php wp_nonce_field( 'cfi_clear_logs' ); ?>
				<span><?php echo esc_html( sprintf( __( '%d entries', 'cloudflare-images-sync' ), count( $items ) ) ); ?></span>
				<input type="submit" name="cfi_clear_logs" class="button" value="<?php esc_attr_e( 'Clear Logs', 'cloudflare-images-sync' ); ?>" onclick="return confirm('<?php esc_attr_e( 'Clear all logs?', 'cloudflare-images-sync' ); ?>');" />
			</form>

			<?php if ( empty( $items ) ) : ?>
				<p><?php esc_html_e( 'No log entries.', 'cloudflare-images-sync' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Time', 'cloudflare-images-sync' ); ?></th>
							<th><?php esc_html_e( 'Level', 'cloudflare-images-sync' ); ?></th>
							<th><?php esc_html_e( 'Message', 'cloudflare-images-sync' ); ?></th>
							<th><?php esc_html_e( 'Post', 'cloudflare-images-sync' ); ?></th>
							<th><?php esc_html_e( 'Mapping', 'cloudflare-images-sync' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( array_reverse( $items ) as $entry ) : ?>
							<tr>
								<td><?php echo esc_html( wp_date( 'Y-m-d H:i:s', $entry['t'] ?? 0 ) ); ?></td>
								<td><code><?php echo esc_html( $entry['lvl'] ?? '' ); ?></code></td>
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
