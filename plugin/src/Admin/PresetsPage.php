<?php
/**
 * Presets admin page.
 *
 * @package CloudflareImagesSync
 */

namespace CFI\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CFI\Repos\Defaults;
use CFI\Repos\PresetsRepo;
use CFI\Repos\SettingsRepo;
use CFI\Support\Validators;

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

		$redirect_url = admin_url( 'admin.php?page=cfi-presets' );

		// Handle delete (GET with nonce).
		if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && ! empty( $_GET['preset_id'] ) ) {
			$preset_id = sanitize_text_field( wp_unslash( $_GET['preset_id'] ) );
			if ( ! Validators::is_valid_id( $preset_id, 'preset' ) ) {
				$this->redirect_with_notice( $redirect_url, __( 'Invalid preset ID.', 'cfi-images-sync' ), 'error' );
			}
			check_admin_referer( 'cfi_delete_preset_' . $preset_id );
			$result = $this->repo->delete( $preset_id );

			if ( is_wp_error( $result ) ) {
				$this->redirect_with_notice( $redirect_url, $result->get_error_message(), 'error' );
			}

			$this->redirect_with_notice( $redirect_url, __( 'Preset deleted.', 'cfi-images-sync' ) );
		}

		// Handle install recommended presets.
		if ( isset( $_POST['cfi_install_recommended'] ) ) {
			check_admin_referer( 'cfi_install_recommended' );
			$result = $this->repo->install_recommended();
			$count  = count( $result['installed'] );
			$skip   = count( $result['skipped'] );

			if ( $count > 0 ) {
				$message = sprintf(
					/* translators: %d: number of presets installed */
					_n( '%d preset installed.', '%d presets installed.', $count, 'cfi-images-sync' ),
					$count
				);
				if ( $skip > 0 ) {
					$message .= ' ' . sprintf(
						/* translators: %d: number of presets skipped */
						_n( '%d already existed (skipped).', '%d already existed (skipped).', $skip, 'cfi-images-sync' ),
						$skip
					);
				}
			} else {
				$message = __( 'All recommended presets already exist.', 'cfi-images-sync' );
			}

			$this->redirect_with_notice( $redirect_url, $message );
		}

		// Handle create/update (POST).
		if ( isset( $_POST['cfi_save_preset'] ) ) {
			check_admin_referer( 'cfi_preset_save' );

			$data = array(
				'name'    => sanitize_text_field( wp_unslash( $_POST['preset_name'] ?? '' ) ),
				'variant' => sanitize_text_field( wp_unslash( $_POST['preset_variant'] ?? '' ) ),
			);

			$edit_id = sanitize_text_field( wp_unslash( $_POST['preset_id'] ?? '' ) );
			if ( $edit_id !== '' && ! Validators::is_valid_id( $edit_id, 'preset' ) ) {
				$this->redirect_with_notice( $redirect_url, __( 'Invalid preset ID.', 'cfi-images-sync' ), 'error' );
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
				? __( 'Preset updated.', 'cfi-images-sync' )
				: __( 'Preset created.', 'cfi-images-sync' );

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
			wp_die( esc_html__( 'Unauthorized.', 'cfi-images-sync' ) );
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
			<h1><?php esc_html_e( 'CF Images — Presets', 'cfi-images-sync' ); ?></h1>

			<?php $this->render_notice(); ?>

			<?php $this->render_flex_callout( $flex_status ); ?>

			<h2><?php echo $editing ? esc_html__( 'Edit Preset', 'cfi-images-sync' ) : esc_html__( 'Add Preset', 'cfi-images-sync' ); ?></h2>
			<form method="post">
				<?php wp_nonce_field( 'cfi_preset_save' ); ?>
				<input type="hidden" name="preset_id" value="<?php echo esc_attr( $editing['id'] ?? '' ); ?>" />
				<table class="form-table">
					<tr>
						<th><label for="preset_name"><?php esc_html_e( 'Name', 'cfi-images-sync' ); ?></label></th>
						<td><input type="text" id="preset_name" name="preset_name" value="<?php echo esc_attr( $editing['name'] ?? '' ); ?>" class="regular-text" required /></td>
					</tr>
					<tr>
						<th><label for="preset_variant"><?php esc_html_e( 'Variant', 'cfi-images-sync' ); ?></label></th>
						<td><input type="text" id="preset_variant" name="preset_variant" value="<?php echo esc_attr( $editing['variant'] ?? '' ); ?>" class="regular-text" required />
						<p class="description"><?php esc_html_e( 'e.g. w=1200,height=630,fit=cover,quality=85,f=auto', 'cfi-images-sync' ); ?></p></td>
					</tr>
				</table>
				<p class="submit">
					<input type="submit" name="cfi_save_preset" class="button-primary" value="<?php esc_attr_e( 'Save Preset', 'cfi-images-sync' ); ?>" />
					<?php if ( $editing ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=cfi-presets' ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'cfi-images-sync' ); ?></a>
					<?php endif; ?>
				</p>
			</form>

			<div style="display:flex;align-items:center;gap:12px;margin:20px 0 12px;">
				<h2 style="margin:0;"><?php esc_html_e( 'Existing Presets', 'cfi-images-sync' ); ?></h2>
				<form method="post" style="display:inline;" id="cfi-install-recommended-form">
					<?php wp_nonce_field( 'cfi_install_recommended' ); ?>
					<?php if ( $flex_status === 'enabled' ) : ?>
						<button type="submit" name="cfi_install_recommended" class="button">
							<?php esc_html_e( 'Install Recommended Presets', 'cfi-images-sync' ); ?>
						</button>
					<?php else : ?>
						<button type="button" class="button" id="cfi-install-recommended-btn" data-flex-status="<?php echo esc_attr( $flex_status ); ?>">
							<?php esc_html_e( 'Install Recommended Presets', 'cfi-images-sync' ); ?>
						</button>
					<?php endif; ?>
				</form>
			</div>
			<?php if ( empty( $presets ) ) : ?>
				<p><?php esc_html_e( 'No presets yet. Click "Install Recommended Presets" to add a curated set.', 'cfi-images-sync' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Name', 'cfi-images-sync' ); ?></th>
							<th><?php esc_html_e( 'Variant', 'cfi-images-sync' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'cfi-images-sync' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $presets as $preset ) : ?>
							<tr>
								<td>
									<strong><?php echo esc_html( $preset['name'] ); ?></strong>
									<?php if ( Defaults::is_recommended_name( $preset['name'] ) ) : ?>
										<span class="cfi-badge cfi-badge--recommended"><?php esc_html_e( 'Recommended', 'cfi-images-sync' ); ?></span>
									<?php endif; ?>
									<?php if ( Validators::is_flexible_variant( $preset['variant'] ) ) : ?>
										<?php if ( $flex_status === 'enabled' ) : ?>
											<span class="cfi-badge cfi-badge--flexible"><?php esc_html_e( 'Flexible', 'cfi-images-sync' ); ?></span>
										<?php elseif ( $flex_status === 'disabled' ) : ?>
											<span class="cfi-badge cfi-badge--flex-warn"><?php esc_html_e( 'Needs Flexible Variants', 'cfi-images-sync' ); ?></span>
										<?php else : ?>
											<span class="cfi-badge cfi-badge--flex-unknown"><?php esc_html_e( 'Flexible (status unknown)', 'cfi-images-sync' ); ?></span>
										<?php endif; ?>
									<?php else : ?>
										<span class="cfi-badge cfi-badge--universal"><?php esc_html_e( 'Universal', 'cfi-images-sync' ); ?></span>
									<?php endif; ?>
									<br/><code><?php echo esc_html( $preset['id'] ); ?></code>
								</td>
								<td><code><?php echo esc_html( $preset['variant'] ); ?></code></td>
								<td>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=cfi-presets&action=edit&preset_id=' . $preset['id'] ) ); ?>"><?php esc_html_e( 'Edit', 'cfi-images-sync' ); ?></a>
									|
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=cfi-preview&mode=attachment&preset_id=' . $preset['id'] ) ); ?>"><?php esc_html_e( 'Preview', 'cfi-images-sync' ); ?></a>
									|
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=cfi-presets&action=delete&preset_id=' . $preset['id'] ), 'cfi_delete_preset_' . $preset['id'] ) ); ?>" onclick="return confirm('<?php esc_attr_e( 'Delete this preset?', 'cfi-images-sync' ); ?>');"><?php esc_html_e( 'Delete', 'cfi-images-sync' ); ?></a>
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
		$settings_url = admin_url( 'admin.php?page=cfi-settings' );

		if ( $flex_status === 'enabled' ) {
			?>
			<div class="cfi-fv-callout cfi-fv-callout--enabled">
				<p class="cfi-fv-callout__title"><?php esc_html_e( 'Flexible Variants: Enabled', 'cfi-images-sync' ); ?></p>
				<p class="cfi-fv-callout__text"><?php esc_html_e( 'All parameter-based presets will work correctly.', 'cfi-images-sync' ); ?></p>
			</div>
			<?php
		} elseif ( $flex_status === 'disabled' ) {
			?>
			<div class="cfi-fv-callout cfi-fv-callout--disabled">
				<p class="cfi-fv-callout__title"><?php esc_html_e( 'Flexible Variants: Disabled', 'cfi-images-sync' ); ?></p>
				<p class="cfi-fv-callout__text"><?php esc_html_e( 'Parameter-based presets (w=, h=, fit=, etc.) require Flexible Variants to be enabled on your Cloudflare account.', 'cfi-images-sync' ); ?></p>
				<div class="cfi-fv-callout__actions">
					<button type="button" class="button" id="cfi-flex-test"><?php esc_html_e( 'Test Status', 'cfi-images-sync' ); ?></button>
					<button type="button" class="button button-primary" id="cfi-flex-enable"><?php esc_html_e( 'Enable Flexible Variants', 'cfi-images-sync' ); ?></button>
					<a href="<?php echo esc_url( $settings_url ); ?>" class="button"><?php esc_html_e( 'Go to Settings', 'cfi-images-sync' ); ?></a>
					<span class="spinner" id="cfi-flex-spinner"></span>
					<span id="cfi-flex-result"></span>
				</div>
			</div>
			<?php
		} else {
			?>
			<div class="cfi-fv-callout cfi-fv-callout--unknown">
				<p class="cfi-fv-callout__title"><?php esc_html_e( 'Flexible Variants: Status Unknown', 'cfi-images-sync' ); ?></p>
				<p class="cfi-fv-callout__text"><?php esc_html_e( 'Test the connection to check if Flexible Variants are enabled on your Cloudflare account.', 'cfi-images-sync' ); ?></p>
				<div class="cfi-fv-callout__actions">
					<button type="button" class="button button-primary" id="cfi-flex-test"><?php esc_html_e( 'Test Status', 'cfi-images-sync' ); ?></button>
					<a href="<?php echo esc_url( $settings_url ); ?>" class="button"><?php esc_html_e( 'Go to Settings', 'cfi-images-sync' ); ?></a>
					<span class="spinner" id="cfi-flex-spinner"></span>
					<span id="cfi-flex-result"></span>
				</div>
			</div>
			<?php
		}
	}
}
