<?php
/**
 * Settings admin page.
 *
 * @package CloudflareImagesSync
 */

namespace CFIMG\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CFIMG\Api\CloudflareImagesClient;
use CFIMG\Repos\LogsRepo;
use CFIMG\Repos\OptionKeys;
use CFIMG\Repos\SettingsRepo;
use CFIMG\Support\Mask;

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

		$redirect_url = admin_url( 'admin.php?page=cfimg-settings' );

		// Handle save.
		if ( isset( $_POST['cfimg_save_settings'] ) ) {
			check_admin_referer( 'cfimg_settings_save' );
			$this->save_from_post();
			$this->redirect_with_notice( $redirect_url, __( 'Settings saved.', 'images-sync-for-cloudflare' ) );
		}

		// Handle test connection (save first, then test).
		if ( isset( $_POST['cfimg_test_connection'] ) ) {
			check_admin_referer( 'cfimg_settings_save' );
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
					__( 'Connection test passed for account %s.', 'images-sync-for-cloudflare' ),
					Mask::token( $settings['account_id'] )
				)
			);

			// Mark API as tested successfully.
			$this->repo->update( array( 'api_tested_at' => time() ) );

			// Check Flexible Variants status (non-blocking).
			$fv_label  = '';
			$fv_status = $this->check_flex_status( $client );
			if ( $fv_status === 'enabled' ) {
				$fv_label = ' ' . __( 'Flexible Variants: enabled.', 'images-sync-for-cloudflare' );
			} elseif ( $fv_status === 'disabled' ) {
				$fv_label = ' ' . __( 'Flexible Variants: disabled.', 'images-sync-for-cloudflare' );
			} else {
				$fv_label = ' ' . __( 'Flexible Variants: unknown (check manually).', 'images-sync-for-cloudflare' );
			}

			$this->redirect_with_notice( $redirect_url, __( 'Connection successful!', 'images-sync-for-cloudflare' ) . $fv_label );
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

		// Reset status cache when credentials change.
		$current = $this->repo->get();
		if ( $patch['account_id'] !== $current['account_id'] || isset( $patch['api_token'] ) ) {
			$patch['flex_status']     = 'unknown';
			$patch['flex_checked_at'] = 0;
			$patch['api_tested_at']   = 0;
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
		if ( is_wp_error( $fv_status ) && $fv_status->get_error_code() === 'cfimg_flex_unsupported' ) {
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
		check_ajax_referer( 'cfimg_admin' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'images-sync-for-cloudflare' ) ) );
		}

		$client = CloudflareImagesClient::from_settings();

		if ( is_wp_error( $client ) ) {
			wp_send_json_error( array( 'message' => $client->get_error_message() ) );
		}

		$status = $this->check_flex_status( $client );

		$messages = array(
			'enabled'  => __( 'Flexible Variants are enabled.', 'images-sync-for-cloudflare' ),
			'disabled' => __( 'Flexible Variants are disabled.', 'images-sync-for-cloudflare' ),
			'unknown'  => __( 'Could not determine status.', 'images-sync-for-cloudflare' ),
		);

		$settings = $this->repo->get();

		wp_send_json_success(
			array(
				'status'     => $status,
				'message'    => $messages[ $status ] ?? $messages['unknown'],
				'checked_at' => $settings['flex_checked_at'],
			)
		);
	}

	/**
	 * AJAX handler: enable Flexible Variants on the Cloudflare account.
	 *
	 * @return void
	 */
	public function ajax_flex_enable(): void {
		check_ajax_referer( 'cfimg_admin' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'images-sync-for-cloudflare' ) ) );
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
				wp_send_json_error( array( 'message' => __( 'API token lacks permission to edit Images config.', 'images-sync-for-cloudflare' ) ) );
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

		$settings = $this->repo->get();

		wp_send_json_success(
			array(
				'status'     => $status,
				'message'    => __( 'Flexible Variants enabled!', 'images-sync-for-cloudflare' ),
				'checked_at' => $settings['flex_checked_at'],
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
			wp_die( esc_html__( 'Unauthorized.', 'images-sync-for-cloudflare' ) );
		}

		$settings     = $this->repo->get();
		$masked_token = Mask::token( $settings['api_token'] );

		$allowed_link = array(
			'a'    => array(
				'href'   => array(),
				'target' => array(),
				'rel'    => array(),
			),
			'code' => array(),
		);

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'CF Images — Settings', 'images-sync-for-cloudflare' ); ?></h1>

			<?php $this->render_notice(); ?>

			<form method="post">
				<?php wp_nonce_field( 'cfimg_settings_save' ); ?>

				<!-- Section A: API Access -->
				<div class="cfimg-settings-section">
					<h2><?php esc_html_e( 'API Access', 'images-sync-for-cloudflare' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Credentials for uploading images and managing config.', 'images-sync-for-cloudflare' ); ?></p>

					<table class="form-table">
						<tr>
							<th><label for="account_id"><?php esc_html_e( 'Account ID', 'images-sync-for-cloudflare' ); ?></label></th>
							<td>
								<input type="text" id="account_id" name="account_id" value="<?php echo esc_attr( $settings['account_id'] ); ?>" class="regular-text" />
								<p class="description">
									<?php esc_html_e( 'Cloudflare Account ID (32-character hex string).', 'images-sync-for-cloudflare' ); ?>
								</p>
								<details class="cfimg-help-details">
									<summary><?php esc_html_e( 'Where do I find this?', 'images-sync-for-cloudflare' ); ?></summary>
									<p><?php esc_html_e( 'Cloudflare Dashboard → Images (right sidebar), or Workers & Pages → Overview (right sidebar under "Account ID").', 'images-sync-for-cloudflare' ); ?></p>
								</details>
							</td>
						</tr>
						<tr>
							<th><label for="api_token"><?php esc_html_e( 'API Token', 'images-sync-for-cloudflare' ); ?></label></th>
							<td>
								<input type="password" id="api_token" name="api_token" value="" placeholder="<?php echo esc_attr( $masked_token ); ?>" class="regular-text" autocomplete="new-password" />
								<p class="description">
									<?php
									$tokens_link = '<a href="https://dash.cloudflare.com/profile/api-tokens" target="_blank" rel="noopener noreferrer">' . esc_html__( 'API Tokens', 'images-sync-for-cloudflare' ) . '</a>';
									echo wp_kses(
										sprintf(
											/* translators: %s: link to Cloudflare API Tokens page */
											__( 'Create at %s with "Cloudflare Images: Edit" permission.', 'images-sync-for-cloudflare' ),
											$tokens_link
										),
										$allowed_link
									);
									?>
								</p>
								<p class="description cfimg-description-error">
									<?php esc_html_e( 'Do NOT use the signature token from Images → Keys tab.', 'images-sync-for-cloudflare' ); ?>
								</p>
								<p class="description">
									<?php esc_html_e( 'Leave blank to keep the current token.', 'images-sync-for-cloudflare' ); ?>
								</p>
								<details class="cfimg-help-details">
									<summary><?php esc_html_e( 'What permissions are needed?', 'images-sync-for-cloudflare' ); ?></summary>
									<ul>
										<li><?php esc_html_e( 'Account → Cloudflare Images → Read', 'images-sync-for-cloudflare' ); ?></li>
										<li><?php esc_html_e( 'Account → Cloudflare Images → Edit', 'images-sync-for-cloudflare' ); ?></li>
									</ul>
								</details>
							</td>
						</tr>
					</table>

					<p class="submit cfimg-submit-compact">
						<input type="submit" name="cfimg_test_connection" class="button-secondary" value="<?php esc_attr_e( 'Test Connection', 'images-sync-for-cloudflare' ); ?>" />
					</p>
				</div>

				<!-- Section B: Delivery -->
				<div class="cfimg-settings-section">
					<h2><?php esc_html_e( 'Delivery', 'images-sync-for-cloudflare' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Settings for image delivery URLs (imagedelivery.net).', 'images-sync-for-cloudflare' ); ?></p>

					<table class="form-table">
						<tr>
							<th><label for="account_hash"><?php esc_html_e( 'Account Hash', 'images-sync-for-cloudflare' ); ?></label></th>
							<td>
								<input type="text" id="account_hash" name="account_hash" value="<?php echo esc_attr( $settings['account_hash'] ); ?>" class="regular-text" />
								<p class="description">
									<?php esc_html_e( 'Used to build delivery URLs. Copy from any Cloudflare Images URL:', 'images-sync-for-cloudflare' ); ?><br>
									<code>https://imagedelivery.net/<strong>&lt;hash&gt;</strong>/&lt;image_id&gt;/...</code>
								</p>
								<details class="cfimg-help-details">
									<summary><?php esc_html_e( 'Where do I find this?', 'images-sync-for-cloudflare' ); ?></summary>
									<p>
										<?php
										$images_url  = $settings['account_id'] !== ''
											? 'https://dash.cloudflare.com/' . $settings['account_id'] . '/images'
											: 'https://dash.cloudflare.com/?to=/:account/images';
										$images_link = '<a href="' . esc_url( $images_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Images page', 'images-sync-for-cloudflare' ) . '</a>';
										echo wp_kses(
											sprintf(
												/* translators: %s: link to Cloudflare Images dashboard */
												__( 'Cloudflare Dashboard → Images → any image → copy the hash from the delivery URL, or check the right sidebar on the %s.', 'images-sync-for-cloudflare' ),
												$images_link
											),
											$allowed_link
										);
										?>
									</p>
								</details>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Flexible Variants', 'images-sync-for-cloudflare' ); ?></th>
							<td>
								<?php $this->render_flex_status_row( $settings ); ?>
							</td>
						</tr>
					</table>
				</div>

				<!-- Section C: Advanced (collapsed) -->
				<details class="cfimg-settings-section cfimg-settings-advanced">
					<summary><h2 class="cfimg-heading-inline"><?php esc_html_e( 'Advanced', 'images-sync-for-cloudflare' ); ?></h2></summary>

					<table class="form-table">
						<tr>
							<th><?php esc_html_e( 'Debug mode', 'images-sync-for-cloudflare' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="debug" value="1" <?php checked( $settings['debug'] ); ?> />
									<?php esc_html_e( 'Enable debug logging', 'images-sync-for-cloudflare' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Logs detailed info about sync operations. Check Logs page.', 'images-sync-for-cloudflare' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Use queue', 'images-sync-for-cloudflare' ); ?></th>
							<td>
								<?php $has_as = function_exists( 'as_enqueue_async_action' ); ?>
								<label>
									<input type="checkbox" name="use_queue" value="1" <?php checked( $settings['use_queue'] ); ?> <?php disabled( ! $has_as ); ?> />
									<?php esc_html_e( 'Process syncs via Action Scheduler (recommended)', 'images-sync-for-cloudflare' ); ?>
								</label>
								<?php if ( ! $has_as ) : ?>
									<p class="description cfimg-description-error">
										<?php esc_html_e( 'Action Scheduler is not available. Install WooCommerce or Action Scheduler standalone plugin.', 'images-sync-for-cloudflare' ); ?>
									</p>
								<?php else : ?>
									<p class="description"><?php esc_html_e( 'Offloads image uploads to background jobs, avoiding timeouts.', 'images-sync-for-cloudflare' ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th><label for="logs_max"><?php esc_html_e( 'Max log entries', 'images-sync-for-cloudflare' ); ?></label></th>
							<td>
								<input type="number" id="logs_max" name="logs_max" value="<?php echo esc_attr( $settings['logs_max'] ); ?>" min="50" max="1000" class="small-text" />
								<p class="description"><?php esc_html_e( 'Ring buffer size for the Logs page (50-1000).', 'images-sync-for-cloudflare' ); ?></p>
							</td>
						</tr>
					</table>
				</details>

				<p class="submit">
					<input type="submit" name="cfimg_save_settings" class="button-primary" value="<?php esc_attr_e( 'Save Settings', 'images-sync-for-cloudflare' ); ?>" />
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Render Flexible Variants status row with badge and actions.
	 *
	 * @param array $settings Current settings.
	 * @return void
	 */
	private function render_flex_status_row( array $settings ): void {
		$flex_status = $settings['flex_status'];
		$flex_labels = array(
			'enabled'  => __( 'Enabled', 'images-sync-for-cloudflare' ),
			'disabled' => __( 'Disabled', 'images-sync-for-cloudflare' ),
			'unknown'  => __( 'Unknown', 'images-sync-for-cloudflare' ),
		);
		$flex_label  = $flex_labels[ $flex_status ] ?? $flex_labels['unknown'];

		$flex_docs_url = 'https://developers.cloudflare.com/images/transform-images/transform-via-url/';
		?>
		<span id="cfimg-flex-badge" class="cfimg-flex-badge cfimg-flex--<?php echo esc_attr( $flex_status ); ?>"><?php echo esc_html( $flex_label ); ?></span>
		<p class="description">
			<?php
			esc_html_e( 'Required for parameter presets like w=, h=, fit=, quality=. This is an account-wide Cloudflare setting.', 'images-sync-for-cloudflare' );
			echo ' ';
			printf(
				'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
				esc_url( $flex_docs_url ),
				esc_html__( 'Learn more', 'images-sync-for-cloudflare' )
			);
			?>
		</p>
		<div class="cfimg-flex-actions" id="cfimg-flex-actions">
			<button type="button" class="button" id="cfimg-flex-test"><?php esc_html_e( 'Test', 'images-sync-for-cloudflare' ); ?></button>
			<button type="button" class="button<?php echo $flex_status === 'enabled' ? ' cfimg-hidden' : ''; ?>" id="cfimg-flex-enable"><?php esc_html_e( 'Enable', 'images-sync-for-cloudflare' ); ?></button>
			<span id="cfimg-flex-spinner" class="spinner"></span>
			<span id="cfimg-flex-result"></span>
		</div>
		<?php
	}
}
