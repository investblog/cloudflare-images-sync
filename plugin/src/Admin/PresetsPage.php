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

use CFI\Repos\PresetsRepo;
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
				$this->redirect_with_notice( $redirect_url, __( 'Invalid preset ID.', 'cloudflare-images-sync' ), 'error' );
			}
			check_admin_referer( 'cfi_delete_preset_' . $preset_id );
			$result = $this->repo->delete( $preset_id );

			if ( is_wp_error( $result ) ) {
				$this->redirect_with_notice( $redirect_url, $result->get_error_message(), 'error' );
			}

			$this->redirect_with_notice( $redirect_url, __( 'Preset deleted.', 'cloudflare-images-sync' ) );
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
				$this->redirect_with_notice( $redirect_url, __( 'Invalid preset ID.', 'cloudflare-images-sync' ), 'error' );
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
				? __( 'Preset updated.', 'cloudflare-images-sync' )
				: __( 'Preset created.', 'cloudflare-images-sync' );

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
			wp_die( esc_html__( 'Unauthorized.', 'cloudflare-images-sync' ) );
		}

		$presets  = $this->repo->all();
		$editing  = null;

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only GET params for UI state.
		if ( isset( $_GET['action'] ) && $_GET['action'] === 'edit' && ! empty( $_GET['preset_id'] ) ) {
			$editing = $this->repo->find( sanitize_text_field( wp_unslash( $_GET['preset_id'] ) ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'CF Images — Presets', 'cloudflare-images-sync' ); ?></h1>

			<?php $this->render_notice(); ?>

			<h2><?php echo $editing ? esc_html__( 'Edit Preset', 'cloudflare-images-sync' ) : esc_html__( 'Add Preset', 'cloudflare-images-sync' ); ?></h2>
			<form method="post">
				<?php wp_nonce_field( 'cfi_preset_save' ); ?>
				<input type="hidden" name="preset_id" value="<?php echo esc_attr( $editing['id'] ?? '' ); ?>" />
				<table class="form-table">
					<tr>
						<th><label for="preset_name"><?php esc_html_e( 'Name', 'cloudflare-images-sync' ); ?></label></th>
						<td><input type="text" id="preset_name" name="preset_name" value="<?php echo esc_attr( $editing['name'] ?? '' ); ?>" class="regular-text" required /></td>
					</tr>
					<tr>
						<th><label for="preset_variant"><?php esc_html_e( 'Variant', 'cloudflare-images-sync' ); ?></label></th>
						<td><input type="text" id="preset_variant" name="preset_variant" value="<?php echo esc_attr( $editing['variant'] ?? '' ); ?>" class="regular-text" required />
						<p class="description"><?php esc_html_e( 'e.g. w=1200,height=630,fit=cover,quality=85,f=auto', 'cloudflare-images-sync' ); ?></p></td>
					</tr>
				</table>
				<p class="submit">
					<input type="submit" name="cfi_save_preset" class="button-primary" value="<?php esc_attr_e( 'Save Preset', 'cloudflare-images-sync' ); ?>" />
					<?php if ( $editing ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=cfi-presets' ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'cloudflare-images-sync' ); ?></a>
					<?php endif; ?>
				</p>
			</form>

			<h2><?php esc_html_e( 'Existing Presets', 'cloudflare-images-sync' ); ?></h2>
			<?php if ( empty( $presets ) ) : ?>
				<p><?php esc_html_e( 'No presets yet.', 'cloudflare-images-sync' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Name', 'cloudflare-images-sync' ); ?></th>
							<th><?php esc_html_e( 'Variant', 'cloudflare-images-sync' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'cloudflare-images-sync' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $presets as $preset ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $preset['name'] ); ?></strong><br/><code><?php echo esc_html( $preset['id'] ); ?></code></td>
								<td><code><?php echo esc_html( $preset['variant'] ); ?></code></td>
								<td>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=cfi-presets&action=edit&preset_id=' . $preset['id'] ) ); ?>"><?php esc_html_e( 'Edit', 'cloudflare-images-sync' ); ?></a>
									|
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=cfi-presets&action=delete&preset_id=' . $preset['id'] ), 'cfi_delete_preset_' . $preset['id'] ) ); ?>" onclick="return confirm('<?php esc_attr_e( 'Delete this preset?', 'cloudflare-images-sync' ); ?>');"><?php esc_html_e( 'Delete', 'cloudflare-images-sync' ); ?></a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}
}
