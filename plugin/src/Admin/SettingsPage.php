<?php
/**
 * Settings admin page.
 *
 * @package CloudflareImagesSync
 */

namespace CFI\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CFI\Api\CloudflareImagesClient;
use CFI\Repos\SettingsRepo;
use CFI\Support\Mask;

/**
 * Settings page: account_id, account_hash, api_token (masked), debug, use_queue, test connection.
 */
class SettingsPage {

	use AdminNotice;

	/**
	 * Settings repository instance.
	 *
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
	 * Handle POST actions before headers are sent (PRG pattern).
	 *
	 * @return void
	 */
	public function handle_actions(): void {
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_SERVER['REQUEST_METHOD'] ) || $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
			return;
		}

		$redirect_url = admin_url( 'admin.php?page=cfi-settings' );

		// Handle save.
		if ( isset( $_POST['cfi_save_settings'] ) ) {
			check_admin_referer( 'cfi_settings_save' );
			$this->save_from_post();
			$this->redirect_with_notice( $redirect_url, __( 'Settings saved.', 'cloudflare-images-sync' ) );
		}

		// Handle test connection (save first, then test).
		if ( isset( $_POST['cfi_test_connection'] ) ) {
			check_admin_referer( 'cfi_settings_save' );
			$this->save_from_post();

			$client = CloudflareImagesClient::from_settings();

			if ( is_wp_error( $client ) ) {
				$this->redirect_with_notice( $redirect_url, $client->get_error_message(), 'error' );
			}

			$result = $client->test_connection();

			if ( is_wp_error( $result ) ) {
				$this->redirect_with_notice( $redirect_url, $result->get_error_message(), 'error' );
			}

			$this->redirect_with_notice( $redirect_url, __( 'Connection successful!', 'cloudflare-images-sync' ) );
		}
	}

	/**
	 * Save settings from the current POST data.
	 *
	 * @return void
	 */
	private function save_from_post(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce checked in handle_actions().
		$patch = array(
			'account_id'   => sanitize_text_field( wp_unslash( $_POST['account_id'] ?? '' ) ),
			'account_hash' => sanitize_text_field( wp_unslash( $_POST['account_hash'] ?? '' ) ),
			'debug'        => ! empty( $_POST['debug'] ),
			'use_queue'    => ! empty( $_POST['use_queue'] ),
			'logs_max'     => absint( wp_unslash( $_POST['logs_max'] ?? 200 ) ),
		);

		// Only update token if a new value was provided (not the masked placeholder).
		$token_input = sanitize_text_field( wp_unslash( $_POST['api_token'] ?? '' ) );
		if ( $token_input !== '' && strpos( $token_input, '****' ) === false ) {
			$patch['api_token'] = $token_input;
		}

		$this->repo->update( $patch );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'cloudflare-images-sync' ) );
		}

		$settings     = $this->repo->get();
		$masked_token = Mask::token( $settings['api_token'] );

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'CF Images — Settings', 'cloudflare-images-sync' ); ?></h1>

			<?php $this->render_notice(); ?>

			<form method="post">
				<?php wp_nonce_field( 'cfi_settings_save' ); ?>

				<table class="form-table">
					<tr>
						<th><label for="account_id"><?php esc_html_e( 'Account ID', 'cloudflare-images-sync' ); ?></label></th>
						<td><input type="text" id="account_id" name="account_id" value="<?php echo esc_attr( $settings['account_id'] ); ?>" class="regular-text" />
						<p class="description">
							<?php
							printf(
								/* translators: %s: link to Cloudflare Images dashboard */
								esc_html__( 'Found on the %s page (right sidebar).', 'cloudflare-images-sync' ),
								'<a href="https://dash.cloudflare.com/?to=/:account/images" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Images', 'cloudflare-images-sync' ) . '</a>'
							);
							?>
						</p></td>
					</tr>
					<tr>
						<th><label for="account_hash"><?php esc_html_e( 'Account Hash', 'cloudflare-images-sync' ); ?></label></th>
						<td><input type="text" id="account_hash" name="account_hash" value="<?php echo esc_attr( $settings['account_hash'] ); ?>" class="regular-text" />
						<p class="description">
							<?php
							printf(
								/* translators: %s: link to Cloudflare Images dashboard */
								esc_html__( 'Found on the %s page (right sidebar). Used for delivery URLs.', 'cloudflare-images-sync' ),
								'<a href="https://dash.cloudflare.com/?to=/:account/images" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Images', 'cloudflare-images-sync' ) . '</a>'
							);
							?>
						</p></td>
					</tr>
					<tr>
						<th><label for="api_token"><?php esc_html_e( 'API Token', 'cloudflare-images-sync' ); ?></label></th>
						<td><input type="password" id="api_token" name="api_token" value="" placeholder="<?php echo esc_attr( $masked_token ); ?>" class="regular-text" autocomplete="new-password" />
						<p class="description">
							<?php
							printf(
								/* translators: %s: link to Cloudflare API Tokens page */
								esc_html__( 'Create at %s with "Cloudflare Images: Edit" permission. Not the signature token from the Images Keys tab.', 'cloudflare-images-sync' ),
								'<a href="https://dash.cloudflare.com/profile/api-tokens" target="_blank" rel="noopener noreferrer">' . esc_html__( 'API Tokens', 'cloudflare-images-sync' ) . '</a>'
							);
							?>
						</p>
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
