<?php
/**
 * Presets admin page.
 *
 * @package CloudflareImagesSync
 */

namespace CFIMG\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CFIMG\Repos\Defaults;
use CFIMG\Repos\PresetsRepo;
use CFIMG\Repos\SettingsRepo;
use CFIMG\Support\Validators;

/**
 * Presets CRUD page.
 */
class PresetsPage {

	use AdminNotice;

	/**
	 * Presets repository instance.
	 *
	 * @var PresetsRepo
	 */
	private PresetsRepo $repo;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->repo = new PresetsRepo();
	}

	/**
	 * Handle actions before headers are sent (PRG pattern).
	 *
	 * @return void
	 */
	public function handle_actions(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$redirect_url = admin_url( 'admin.php?page=cfimg-presets' );

		// Handle delete (GET with nonce).
		if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && ! empty( $_GET['preset_id'] ) ) {
			$preset_id = sanitize_text_field( wp_unslash( $_GET['preset_id'] ) );
			if ( ! Validators::is_valid_id( $preset_id, 'preset' ) ) {
				$this->redirect_with_notice( $redirect_url, __( 'Invalid preset ID.', 'images-sync-for-cloudflare' ), 'error' );
			}
			check_admin_referer( 'cfimg_delete_preset_' . $preset_id );
			$result = $this->repo->delete( $preset_id );

			if ( is_wp_error( $result ) ) {
				$this->redirect_with_notice( $redirect_url, $result->get_error_message(), 'error' );
			}

			$this->redirect_with_notice( $redirect_url, __( 'Preset deleted.', 'images-sync-for-cloudflare' ) );
		}

		// Handle install recommended presets.
		if ( isset( $_POST['cfimg_install_recommended'] ) ) {
			check_admin_referer( 'cfimg_install_recommended' );
			$result = $this->repo->install_recommended();
			$count  = count( $result['installed'] );
			$skip   = count( $result['skipped'] );

			if ( $count > 0 ) {
				$message = sprintf(
					/* translators: %d: number of presets installed */
					_n( '%d preset installed.', '%d presets installed.', $count, 'images-sync-for-cloudflare' ),
					$count
				);
				if ( $skip > 0 ) {
					$message .= ' ' . sprintf(
						/* translators: %d: number of presets skipped */
						_n( '%d already existed (skipped).', '%d already existed (skipped).', $skip, 'images-sync-for-cloudflare' ),
						$skip
					);
				}
			} else {
				$message = __( 'All recommended presets already exist.', 'images-sync-for-cloudflare' );
			}

			$this->redirect_with_notice( $redirect_url, $message );
		}

		// Handle create/update (POST).
		if ( isset( $_POST['cfimg_save_preset'] ) ) {
			check_admin_referer( 'cfimg_preset_save' );

			$data = array(
				'name'    => sanitize_text_field( wp_unslash( $_POST['preset_name'] ?? '' ) ),
				'variant' => sanitize_text_field( wp_unslash( $_POST['preset_variant'] ?? '' ) ),
			);

			$edit_id = sanitize_text_field( wp_unslash( $_POST['preset_id'] ?? '' ) );
			if ( $edit_id !== '' && ! Validators::is_valid_id( $edit_id, 'preset' ) ) {
				$this->redirect_with_notice( $redirect_url, __( 'Invalid preset ID.', 'images-sync-for-cloudflare' ), 'error' );
			}

			if ( $edit_id !== '' ) {
				$result = $this->repo->update( $edit_id, $data );
			} else {
				$result = $this->repo->create( $data );
			}

			if ( is_wp_error( $result ) ) {
				$this->redirect_with_notice( $redirect_url, $result->get_error_message(), 'error' );
			}

			$message = $edit_id
				? __( 'Preset updated.', 'images-sync-for-cloudflare' )
				: __( 'Preset created.', 'images-sync-for-cloudflare' );

			$this->redirect_with_notice( $redirect_url, $message );
		}
	}

	/**
	 * Render the presets page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'images-sync-for-cloudflare' ) );
		}

		$presets     = $this->repo->all();
		$editing     = null;
		$flex_status = ( new SettingsRepo() )->get()['flex_status'];

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only GET params for UI state.
		if ( isset( $_GET['action'] ) && $_GET['action'] === 'edit' && ! empty( $_GET['preset_id'] ) ) {
			$editing = $this->repo->find( sanitize_text_field( wp_unslash( $_GET['preset_id'] ) ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'CF Images — Presets', 'images-sync-for-cloudflare' ); ?></h1>

			<?php $this->render_notice(); ?>

			<?php $this->render_flex_callout( $flex_status ); ?>

			<h2><?php echo $editing ? esc_html__( 'Edit Preset', 'images-sync-for-cloudflare' ) : esc_html__( 'Add Preset', 'images-sync-for-cloudflare' ); ?></h2>
			<form method="post">
				<?php wp_nonce_field( 'cfimg_preset_save' ); ?>
				<input type="hidden" name="preset_id" value="<?php echo esc_attr( $editing['id'] ?? '' ); ?>" />
				<table class="form-table">
					<tr>
						<th><label for="preset_name"><?php esc_html_e( 'Name', 'images-sync-for-cloudflare' ); ?></label></th>
						<td><input type="text" id="preset_name" name="preset_name" value="<?php echo esc_attr( $editing['name'] ?? '' ); ?>" class="regular-text" required /></td>
					</tr>
					<tr>
						<th><label for="preset_variant"><?php esc_html_e( 'Variant', 'images-sync-for-cloudflare' ); ?></label></th>
						<td><input type="text" id="preset_variant" name="preset_variant" value="<?php echo esc_attr( $editing['variant'] ?? '' ); ?>" class="regular-text" required />
						<p class="description"><?php esc_html_e( 'e.g. w=1200,height=630,fit=cover,quality=85,f=auto', 'images-sync-for-cloudflare' ); ?></p></td>
					</tr>
				</table>
				<p class="submit">
					<input type="submit" name="cfimg_save_preset" class="button-primary" value="<?php esc_attr_e( 'Save Preset', 'images-sync-for-cloudflare' ); ?>" />
					<?php if ( $editing ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=cfimg-presets' ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'images-sync-for-cloudflare' ); ?></a>
					<?php endif; ?>
				</p>
			</form>

			<div class="cfimg-presets-header">
				<h2><?php esc_html_e( 'Existing Presets', 'images-sync-for-cloudflare' ); ?></h2>
				<form method="post" class="cfimg-inline-form" id="cfimg-install-recommended-form">
					<?php wp_nonce_field( 'cfimg_install_recommended' ); ?>
					<?php if ( $flex_status === 'enabled' ) : ?>
						<button type="submit" name="cfimg_install_recommended" class="button">
							<?php esc_html_e( 'Install Recommended Presets', 'images-sync-for-cloudflare' ); ?>
						</button>
					<?php else : ?>
						<button type="button" class="button" id="cfimg-install-recommended-btn" data-flex-status="<?php echo esc_attr( $flex_status ); ?>">
							<?php esc_html_e( 'Install Recommended Presets', 'images-sync-for-cloudflare' ); ?>
						</button>
					<?php endif; ?>
				</form>
			</div>
			<?php if ( empty( $presets ) ) : ?>
				<p><?php esc_html_e( 'No presets yet. Click "Install Recommended Presets" to add a curated set.', 'images-sync-for-cloudflare' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Name', 'images-sync-for-cloudflare' ); ?></th>
							<th><?php esc_html_e( 'Variant', 'images-sync-for-cloudflare' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'images-sync-for-cloudflare' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $presets as $preset ) : ?>
							<tr>
								<td>
									<strong><?php echo esc_html( $preset['name'] ); ?></strong>
									<?php if ( Defaults::is_recommended_name( $preset['name'] ) ) : ?>
										<span class="cfimg-badge cfimg-badge--recommended"><?php esc_html_e( 'Recommended', 'images-sync-for-cloudflare' ); ?></span>
									<?php endif; ?>
									<?php if ( Validators::is_flexible_variant( $preset['variant'] ) ) : ?>
										<?php if ( $flex_status === 'enabled' ) : ?>
											<span class="cfimg-badge cfimg-badge--flexible"><?php esc_html_e( 'Flexible', 'images-sync-for-cloudflare' ); ?></span>
										<?php elseif ( $flex_status === 'disabled' ) : ?>
											<span class="cfimg-badge cfimg-badge--flex-warn"><?php esc_html_e( 'Needs Flexible Variants', 'images-sync-for-cloudflare' ); ?></span>
										<?php else : ?>
											<span class="cfimg-badge cfimg-badge--flex-unknown"><?php esc_html_e( 'Flexible (status unknown)', 'images-sync-for-cloudflare' ); ?></span>
										<?php endif; ?>
									<?php else : ?>
										<span class="cfimg-badge cfimg-badge--universal"><?php esc_html_e( 'Universal', 'images-sync-for-cloudflare' ); ?></span>
									<?php endif; ?>
									<br/><code><?php echo esc_html( $preset['id'] ); ?></code>
								</td>
								<td><code><?php echo esc_html( $preset['variant'] ); ?></code></td>
								<td>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=cfimg-presets&action=edit&preset_id=' . $preset['id'] ) ); ?>"><?php esc_html_e( 'Edit', 'images-sync-for-cloudflare' ); ?></a>
									|
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=cfimg-preview&mode=attachment&preset_id=' . $preset['id'] ) ); ?>"><?php esc_html_e( 'Preview', 'images-sync-for-cloudflare' ); ?></a>
									|
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=cfimg-presets&action=delete&preset_id=' . $preset['id'] ), 'cfimg_delete_preset_' . $preset['id'] ) ); ?>" onclick="return confirm('<?php esc_attr_e( 'Delete this preset?', 'images-sync-for-cloudflare' ); ?>');"><?php esc_html_e( 'Delete', 'images-sync-for-cloudflare' ); ?></a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render the Flexible Variants status callout banner.
	 *
	 * @param string $flex_status Current FV status: 'enabled', 'disabled', or 'unknown'.
	 * @return void
	 */
	private function render_flex_callout( string $flex_status ): void {
		$settings_url = admin_url( 'admin.php?page=cfimg-settings' );

		if ( $flex_status === 'enabled' ) {
			?>
			<div class="cfimg-fv-callout cfimg-fv-callout--enabled">
				<p class="cfimg-fv-callout__title"><?php esc_html_e( 'Flexible Variants: Enabled', 'images-sync-for-cloudflare' ); ?></p>
				<p class="cfimg-fv-callout__text"><?php esc_html_e( 'All parameter-based presets will work correctly.', 'images-sync-for-cloudflare' ); ?></p>
			</div>
			<?php
		} elseif ( $flex_status === 'disabled' ) {
			?>
			<div class="cfimg-fv-callout cfimg-fv-callout--disabled">
				<p class="cfimg-fv-callout__title"><?php esc_html_e( 'Flexible Variants: Disabled', 'images-sync-for-cloudflare' ); ?></p>
				<p class="cfimg-fv-callout__text"><?php esc_html_e( 'Parameter-based presets (w=, h=, fit=, etc.) require Flexible Variants to be enabled on your Cloudflare account.', 'images-sync-for-cloudflare' ); ?></p>
				<div class="cfimg-fv-callout__actions">
					<button type="button" class="button" id="cfimg-flex-test"><?php esc_html_e( 'Test Status', 'images-sync-for-cloudflare' ); ?></button>
					<button type="button" class="button button-primary" id="cfimg-flex-enable"><?php esc_html_e( 'Enable Flexible Variants', 'images-sync-for-cloudflare' ); ?></button>
					<a href="<?php echo esc_url( $settings_url ); ?>" class="button"><?php esc_html_e( 'Go to Settings', 'images-sync-for-cloudflare' ); ?></a>
					<span class="spinner" id="cfimg-flex-spinner"></span>
					<span id="cfimg-flex-result"></span>
				</div>
			</div>
			<?php
		} else {
			?>
			<div class="cfimg-fv-callout cfimg-fv-callout--unknown">
				<p class="cfimg-fv-callout__title"><?php esc_html_e( 'Flexible Variants: Status Unknown', 'images-sync-for-cloudflare' ); ?></p>
				<p class="cfimg-fv-callout__text"><?php esc_html_e( 'Test the connection to check if Flexible Variants are enabled on your Cloudflare account.', 'images-sync-for-cloudflare' ); ?></p>
				<div class="cfimg-fv-callout__actions">
					<button type="button" class="button button-primary" id="cfimg-flex-test"><?php esc_html_e( 'Test Status', 'images-sync-for-cloudflare' ); ?></button>
					<a href="<?php echo esc_url( $settings_url ); ?>" class="button"><?php esc_html_e( 'Go to Settings', 'images-sync-for-cloudflare' ); ?></a>
					<span class="spinner" id="cfimg-flex-spinner"></span>
					<span id="cfimg-flex-result"></span>
				</div>
			</div>
			<?php
		}
	}
}
