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
use CFI\Repos\LogsRepo;
use CFI\Repos\OptionKeys;
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
			$this->redirect_with_notice( $redirect_url, __( 'Settings saved.', 'cfi-images-sync' ) );
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

			$settings = $this->repo->get();
			$logs     = new LogsRepo();
			$logs->push(
				'info',
				sprintf(
					/* translators: %s: masked Cloudflare account ID */
					__( 'Connection test passed for account %s.', 'cfi-images-sync' ),
					Mask::token( $settings['account_id'] )
				)
			);

			// Check Flexible Variants status (non-blocking).
			$fv_label  = '';
			$fv_status = $this->check_flex_status( $client );
			if ( $fv_status === 'enabled' ) {
				$fv_label = ' ' . __( 'Flexible Variants: enabled.', 'cfi-images-sync' );
			} elseif ( $fv_status === 'disabled' ) {
				$fv_label = ' ' . __( 'Flexible Variants: disabled.', 'cfi-images-sync' );
			} else {
				$fv_label = ' ' . __( 'Flexible Variants: unknown (check manually).', 'cfi-images-sync' );
			}

			$this->redirect_with_notice( $redirect_url, __( 'Connection successful!', 'cfi-images-sync' ) . $fv_label );
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

		// Reset flex status cache when credentials change.
		$current = $this->repo->get();
		if ( $patch['account_id'] !== $current['account_id'] || isset( $patch['api_token'] ) ) {
			$patch['flex_status'] = 'unknown';
		}

		$this->repo->update( $patch );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Check Flexible Variants status via API, with canary fallback.
	 *
	 * @param CloudflareImagesClient $client API client.
	 * @return string 'enabled', 'disabled', or 'unknown'.
	 */
	private function check_flex_status( CloudflareImagesClient $client ): string {
		$fv_status = $client->get_flexible_variants_status();

		// Fallback to canary if API config endpoint is unsupported.
		if ( is_wp_error( $fv_status ) && $fv_status->get_error_code() === 'cfi_flex_unsupported' ) {
			$settings  = $this->repo->get();
			$demo_id   = get_option( OptionKeys::DEMO_IMAGE_ID, '' );

			if ( $demo_id !== '' && $settings['account_hash'] !== '' ) {
				$fv_status = $client->canary_flexible_variants( $settings['account_hash'], $demo_id );
			}
		}

		$fv_value = is_wp_error( $fv_status ) ? 'unknown' : ( $fv_status ? 'enabled' : 'disabled' );

		$this->repo->update(
			array(
				'flex_status'     => $fv_value,
				'flex_checked_at' => time(),
			)
		);

		return $fv_value;
	}

	/**
	 * AJAX handler: test Flexible Variants status.
	 *
	 * @return void
	 */
	public function ajax_flex_test(): void {
		check_ajax_referer( 'cfi_admin' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'cfi-images-sync' ) ) );
		}

		$client = CloudflareImagesClient::from_settings();

		if ( is_wp_error( $client ) ) {
			wp_send_json_error( array( 'message' => $client->get_error_message() ) );
		}

		$status = $this->check_flex_status( $client );

		$messages = array(
			'enabled'  => __( 'Flexible Variants are enabled.', 'cfi-images-sync' ),
			'disabled' => __( 'Flexible Variants are disabled.', 'cfi-images-sync' ),
			'unknown'  => __( 'Could not determine status.', 'cfi-images-sync' ),
		);

		wp_send_json_success(
			array(
				'status'  => $status,
				'message' => $messages[ $status ] ?? $messages['unknown'],
			)
		);
	}

	/**
	 * AJAX handler: enable Flexible Variants on the Cloudflare account.
	 *
	 * @return void
	 */
	public function ajax_flex_enable(): void {
		check_ajax_referer( 'cfi_admin' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'cfi-images-sync' ) ) );
		}

		$client = CloudflareImagesClient::from_settings();

		if ( is_wp_error( $client ) ) {
			wp_send_json_error( array( 'message' => $client->get_error_message() ) );
		}

		$result = $client->enable_flexible_variants();

		if ( is_wp_error( $result ) ) {
			$data    = $result->get_error_data();
			$cf_code = $data['cf_code'] ?? 0;

			if ( in_array( $cf_code, array( 401, 403 ), true ) ) {
				wp_send_json_error( array( 'message' => __( 'API token lacks permission to edit Images config.', 'cfi-images-sync' ) ) );
			}

			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$status = $result ? 'enabled' : 'disabled';

		$this->repo->update(
			array(
				'flex_status'     => $status,
				'flex_checked_at' => time(),
			)
		);

		wp_send_json_success(
			array(
				'status'  => $status,
				'message' => __( 'Flexible Variants enabled!', 'cfi-images-sync' ),
			)
		);
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'cfi-images-sync' ) );
		}

		$settings     = $this->repo->get();
		$masked_token = Mask::token( $settings['api_token'] );

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'CF Images — Settings', 'cfi-images-sync' ); ?></h1>

			<?php $this->render_notice(); ?>

			<form method="post">
				<?php wp_nonce_field( 'cfi_settings_save' ); ?>

				<table class="form-table">
					<tr>
						<th><label for="account_id"><?php esc_html_e( 'Account ID', 'cfi-images-sync' ); ?></label></th>
						<td><input type="text" id="account_id" name="account_id" value="<?php echo esc_attr( $settings['account_id'] ); ?>" class="regular-text" />
						<p class="description">
							<?php
							$allowed_link = array(
								'a' => array(
									'href'   => array(),
									'target' => array(),
									'rel'    => array(),
								),
							);
							$images_link  = '<a href="https://dash.cloudflare.com/?to=/:account/images" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Images', 'cfi-images-sync' ) . '</a>';
							echo wp_kses(
								sprintf(
									/* translators: %s: link to Cloudflare Images dashboard */
									__( 'Found on the %s page (right sidebar).', 'cfi-images-sync' ),
									$images_link
								),
								$allowed_link
							);
							?>
						</p></td>
					</tr>
					<tr>
						<th><label for="account_hash"><?php esc_html_e( 'Account Hash', 'cfi-images-sync' ); ?></label></th>
						<td><input type="text" id="account_hash" name="account_hash" value="<?php echo esc_attr( $settings['account_hash'] ); ?>" class="regular-text" />
						<p class="description">
							<?php
							echo wp_kses(
								sprintf(
									/* translators: %s: link to Cloudflare Images dashboard */
									__( 'Found on the %s page (right sidebar). Used for delivery URLs.', 'cfi-images-sync' ),
									$images_link
								),
								$allowed_link
							);
							?>
						</p></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Flexible Variants', 'cfi-images-sync' ); ?></th>
						<td>
							<?php
							$flex_status = $settings['flex_status'];
							$flex_labels = array(
								'enabled'  => __( 'Enabled', 'cfi-images-sync' ),
								'disabled' => __( 'Disabled', 'cfi-images-sync' ),
								'unknown'  => __( 'Unknown', 'cfi-images-sync' ),
							);
							$flex_label  = $flex_labels[ $flex_status ] ?? $flex_labels['unknown'];
							?>
							<span id="cfi-flex-badge" class="cfi-flex-badge cfi-flex--<?php echo esc_attr( $flex_status ); ?>"><?php echo esc_html( $flex_label ); ?></span>
							<p class="description">
								<?php
								$flex_link = '<a href="https://developers.cloudflare.com/images/transform-images/flexible-variants/" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Flexible Variants docs', 'cfi-images-sync' ) . '</a>';
								if ( $flex_status === 'enabled' ) {
									echo wp_kses(
										sprintf(
											/* translators: %s: link to Cloudflare Flexible Variants docs */
											__( 'Parameter-based presets (w=, h=, fit=...) are available. See %s.', 'cfi-images-sync' ),
											$flex_link
										),
										array(
											'a' => array(
												'href'   => array(),
												'target' => array(),
												'rel'    => array(),
											),
										)
									);
								} elseif ( $flex_status === 'disabled' ) {
									echo wp_kses(
										sprintf(
											/* translators: %s: link to Cloudflare Flexible Variants docs */
											__( 'Parameter-based presets will not work until enabled. See %s.', 'cfi-images-sync' ),
											$flex_link
										),
										array(
											'a' => array(
												'href'   => array(),
												'target' => array(),
												'rel'    => array(),
											),
										)
									);
								} else {
									echo wp_kses(
										sprintf(
											/* translators: %s: link to Cloudflare Flexible Variants docs */
											__( 'Status not yet checked. Use "Test Connection" or click "Test" below. See %s.', 'cfi-images-sync' ),
											$flex_link
										),
										array(
											'a' => array(
												'href'   => array(),
												'target' => array(),
												'rel'    => array(),
											),
										)
									);
								}
								?>
							</p>
							<div class="cfi-flex-actions" id="cfi-flex-actions">
								<button type="button" class="button" id="cfi-flex-test"><?php esc_html_e( 'Test', 'cfi-images-sync' ); ?></button>
								<button type="button" class="button" id="cfi-flex-enable" <?php echo $flex_status === 'enabled' ? 'style="display:none;"' : ''; ?>><?php esc_html_e( 'Enable', 'cfi-images-sync' ); ?></button>
								<span id="cfi-flex-spinner" class="spinner"></span>
								<span id="cfi-flex-result"></span>
							</div>
						</td>
					</tr>
					<tr>
						<th><label for="api_token"><?php esc_html_e( 'API Token', 'cfi-images-sync' ); ?></label></th>
						<td><input type="password" id="api_token" name="api_token" value="" placeholder="<?php echo esc_attr( $masked_token ); ?>" class="regular-text" autocomplete="new-password" />
						<p class="description">
							<?php
							$tokens_link = '<a href="https://dash.cloudflare.com/profile/api-tokens" target="_blank" rel="noopener noreferrer">' . esc_html__( 'API Tokens', 'cfi-images-sync' ) . '</a>';
							echo wp_kses(
								sprintf(
									/* translators: %s: link to Cloudflare API Tokens page */
									__( 'Create at %s with "Cloudflare Images: Edit" permission. Not the signature token from the Images Keys tab.', 'cfi-images-sync' ),
									$tokens_link
								),
								$allowed_link
							);
							?>
						</p>
						<p class="description"><?php esc_html_e( 'Leave blank to keep the current token.', 'cfi-images-sync' ); ?></p></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Debug mode', 'cfi-images-sync' ); ?></th>
						<td><label><input type="checkbox" name="debug" value="1" <?php checked( $settings['debug'] ); ?> /> <?php esc_html_e( 'Enable debug logging', 'cfi-images-sync' ); ?></label></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Use queue', 'cfi-images-sync' ); ?></th>
						<td>
							<?php $has_as = function_exists( 'as_enqueue_async_action' ); ?>
							<label>
								<input type="checkbox" name="use_queue" value="1" <?php checked( $settings['use_queue'] ); ?> <?php disabled( ! $has_as ); ?> />
								<?php esc_html_e( 'Process syncs via Action Scheduler (recommended)', 'cfi-images-sync' ); ?>
							</label>
							<?php if ( ! $has_as ) : ?>
								<p class="description" style="color: #d63638;">
									<?php esc_html_e( 'Action Scheduler is not available. Install and activate a plugin that includes it (e.g. WooCommerce or Action Scheduler standalone) to enable background processing.', 'cfi-images-sync' ); ?>
								</p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th><label for="logs_max"><?php esc_html_e( 'Max log entries', 'cfi-images-sync' ); ?></label></th>
						<td><input type="number" id="logs_max" name="logs_max" value="<?php echo esc_attr( $settings['logs_max'] ); ?>" min="50" max="1000" class="small-text" /></td>
					</tr>
				</table>

				<p class="submit">
					<input type="submit" name="cfi_save_settings" class="button-primary" value="<?php esc_attr_e( 'Save Settings', 'cfi-images-sync' ); ?>" />
					<input type="submit" name="cfi_test_connection" class="button-secondary" value="<?php esc_attr_e( 'Test Connection', 'cfi-images-sync' ); ?>" />
				</p>
			</form>
		</div>
		<?php
	}
}
