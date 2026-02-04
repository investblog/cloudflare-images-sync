<?php
/**
 * Settings admin page.
 *
 * @package CloudflareImagesSync
 */

namespace CFI\Admin;

use CFI\Api\CloudflareImagesClient;
use CFI\Repos\SettingsRepo;
use CFI\Support\Mask;

/**
 * Settings page: account_id, account_hash, api_token (masked), debug, use_queue, test connection.
 */
class SettingsPage {

	/**
	 * @var SettingsRepo
	 */
	private SettingsRepo $repo;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->repo = new SettingsRepo();
	}

	/**
	 * Handle form submission and render the page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'cloudflare-images-sync' ) );
		}

		$message = '';

		// Handle save.
		if ( isset( $_POST['cfi_save_settings'] ) ) {
			check_admin_referer( 'cfi_settings_save' );

			$patch = array(
				'account_id'   => sanitize_text_field( wp_unslash( $_POST['account_id'] ?? '' ) ),
				'account_hash' => sanitize_text_field( wp_unslash( $_POST['account_hash'] ?? '' ) ),
				'debug'        => ! empty( $_POST['debug'] ),
				'use_queue'    => ! empty( $_POST['use_queue'] ),
				'logs_max'     => (int) ( $_POST['logs_max'] ?? 200 ),
			);

			// Only update token if a new value was provided (not the masked placeholder).
			$token_input = wp_unslash( $_POST['api_token'] ?? '' );
			if ( $token_input !== '' && strpos( $token_input, '****' ) === false ) {
				$patch['api_token'] = sanitize_text_field( $token_input );
			}

			$this->repo->update( $patch );
			$message = __( 'Settings saved.', 'cloudflare-images-sync' );
		}

		// Handle test connection.
		if ( isset( $_POST['cfi_test_connection'] ) ) {
			check_admin_referer( 'cfi_settings_save' );

			$client = CloudflareImagesClient::from_settings();

			if ( is_wp_error( $client ) ) {
				$message = '❌ ' . $client->get_error_message();
			} else {
				$result = $client->test_connection();
				if ( is_wp_error( $result ) ) {
					$message = '❌ ' . $result->get_error_message();
				} else {
					$message = '✅ ' . __( 'Connection successful!', 'cloudflare-images-sync' );
				}
			}
		}

		$settings     = $this->repo->get();
		$masked_token = Mask::token( $settings['api_token'] );

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Cloudflare Images — Settings', 'cloudflare-images-sync' ); ?></h1>

			<?php if ( $message ) : ?>
				<div class="notice notice-info is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
			<?php endif; ?>

			<form method="post">
				<?php wp_nonce_field( 'cfi_settings_save' ); ?>

				<table class="form-table">
					<tr>
						<th><label for="account_id"><?php esc_html_e( 'Account ID', 'cloudflare-images-sync' ); ?></label></th>
						<td><input type="text" id="account_id" name="account_id" value="<?php echo esc_attr( $settings['account_id'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th><label for="account_hash"><?php esc_html_e( 'Account Hash', 'cloudflare-images-sync' ); ?></label></th>
						<td><input type="text" id="account_hash" name="account_hash" value="<?php echo esc_attr( $settings['account_hash'] ); ?>" class="regular-text" />
						<p class="description"><?php esc_html_e( 'Used for delivery URLs (imagedelivery.net).', 'cloudflare-images-sync' ); ?></p></td>
					</tr>
					<tr>
						<th><label for="api_token"><?php esc_html_e( 'API Token', 'cloudflare-images-sync' ); ?></label></th>
						<td><input type="password" id="api_token" name="api_token" value="" placeholder="<?php echo esc_attr( $masked_token ); ?>" class="regular-text" autocomplete="new-password" />
						<p class="description"><?php esc_html_e( 'Leave blank to keep the current token.', 'cloudflare-images-sync' ); ?></p></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Debug mode', 'cloudflare-images-sync' ); ?></th>
						<td><label><input type="checkbox" name="debug" value="1" <?php checked( $settings['debug'] ); ?> /> <?php esc_html_e( 'Enable debug logging', 'cloudflare-images-sync' ); ?></label></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Use queue', 'cloudflare-images-sync' ); ?></th>
						<td><label><input type="checkbox" name="use_queue" value="1" <?php checked( $settings['use_queue'] ); ?> /> <?php esc_html_e( 'Process syncs via Action Scheduler (recommended)', 'cloudflare-images-sync' ); ?></label></td>
					</tr>
					<tr>
						<th><label for="logs_max"><?php esc_html_e( 'Max log entries', 'cloudflare-images-sync' ); ?></label></th>
						<td><input type="number" id="logs_max" name="logs_max" value="<?php echo esc_attr( $settings['logs_max'] ); ?>" min="50" max="1000" class="small-text" /></td>
					</tr>
				</table>

				<p class="submit">
					<input type="submit" name="cfi_save_settings" class="button-primary" value="<?php esc_attr_e( 'Save Settings', 'cloudflare-images-sync' ); ?>" />
					<input type="submit" name="cfi_test_connection" class="button-secondary" value="<?php esc_attr_e( 'Test Connection', 'cloudflare-images-sync' ); ?>" />
				</p>
			</form>
		</div>
		<?php
	}
}
