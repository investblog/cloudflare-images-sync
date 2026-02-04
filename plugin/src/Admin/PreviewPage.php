<?php
/**
 * Preview / Variant Studio admin page.
 *
 * @package CloudflareImagesSync
 */

namespace CFI\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CFI\Api\CloudflareImagesClient;
use CFI\Core\Signature;
use CFI\Core\SourceResolver;
use CFI\Core\SyncEngine;
use CFI\Core\UrlBuilder;
use CFI\Repos\MappingsRepo;
use CFI\Repos\OptionKeys;
use CFI\Repos\PresetsRepo;
use CFI\Repos\SettingsRepo;
use CFI\Support\Validators;

/**
 * Preview page with two modes:
 *   Mode A: Attachment preview (upload-on-demand, grid of presets)
 *   Mode B: Post + Mapping preview (find source, show preview, sync now)
 */
class PreviewPage {

	use AdminNotice;

	/**
	 * Handle actions before headers are sent (PRG pattern).
	 *
	 * @return void
	 */
	public function handle_actions(): void {
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_SERVER['REQUEST_METHOD'] ) || $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
			return;
		}

		// Handle attachment upload-on-demand.
		if ( isset( $_POST['cfi_preview_upload'] ) ) {
			check_admin_referer( 'cfi_preview_upload' );
			$attachment_id = isset( $_GET['attachment_id'] ) ? absint( wp_unslash( $_GET['attachment_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			if ( $attachment_id > 0 ) {
				$message = $this->ensure_preview_uploaded( $attachment_id );
				$type    = strpos( $message, 'error' ) !== false || strpos( $message, 'not found' ) !== false ? 'error' : 'success';
				$this->redirect_with_notice(
					admin_url( 'admin.php?page=cfi-preview&mode=attachment&attachment_id=' . $attachment_id ),
					$message,
					$type
				);
			}
		}

		// Handle sync now.
		if ( isset( $_POST['cfi_sync_now'] ) ) {
			check_admin_referer( 'cfi_sync_now' );

			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- GET params for context.
			$post_id    = isset( $_GET['post_id'] ) ? absint( wp_unslash( $_GET['post_id'] ) ) : 0;
			$mapping_id = isset( $_GET['mapping_id'] ) ? sanitize_text_field( wp_unslash( $_GET['mapping_id'] ) ) : '';
			// phpcs:enable WordPress.Security.NonceVerification.Recommended

			if ( $mapping_id !== '' && ! Validators::is_valid_id( $mapping_id, 'map' ) ) {
				$this->redirect_with_notice(
					admin_url( 'admin.php?page=cfi-preview&mode=post' ),
					__( 'Invalid mapping ID.', 'cloudflare-images-sync' ),
					'error'
				);
			}

			if ( $post_id > 0 && $mapping_id !== '' ) {
				$mappings = new MappingsRepo();
				$mapping  = $mappings->find( $mapping_id );

				if ( $mapping ) {
					$engine = new SyncEngine();
					$result = $engine->sync( $post_id, $mapping );
					$msg    = is_wp_error( $result ) ? $result->get_error_message() : __( 'Synced successfully.', 'cloudflare-images-sync' );
					$type   = is_wp_error( $result ) ? 'error' : 'success';
				} else {
					$msg  = __( 'Mapping not found.', 'cloudflare-images-sync' );
					$type = 'error';
				}

				$this->redirect_with_notice(
					admin_url( 'admin.php?page=cfi-preview&mode=post&post_id=' . $post_id . '&mapping_id=' . $mapping_id ),
					$msg,
					$type
				);
			}
		}
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

		wp_enqueue_media();

		$mode = isset( $_GET['mode'] ) ? sanitize_text_field( wp_unslash( $_GET['mode'] ) ) : 'attachment'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'CF Images — Preview / Variant Studio', 'cloudflare-images-sync' ); ?></h1>

			<h2 class="nav-tab-wrapper">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=cfi-preview&mode=attachment' ) ); ?>" class="nav-tab <?php echo $mode === 'attachment' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Attachment Preview', 'cloudflare-images-sync' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=cfi-preview&mode=post' ) ); ?>" class="nav-tab <?php echo $mode === 'post' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Post + Mapping', 'cloudflare-images-sync' ); ?></a>
			</h2>

			<?php $this->render_notice(); ?>

			<?php
			if ( $mode === 'post' ) {
				$this->render_post_mode();
			} else {
				$this->render_attachment_mode();
			}
			?>
		</div>
		<?php
	}

	/**
	 * Mode A: Attachment preview.
	 *
	 * @return void
	 */
	private function render_attachment_mode(): void {
		$attachment_id = isset( $_GET['attachment_id'] ) ? absint( wp_unslash( $_GET['attachment_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		?>
		<form method="get">
			<input type="hidden" name="page" value="cfi-preview" />
			<input type="hidden" name="mode" value="attachment" />
			<p>
				<label for="attachment_id"><?php esc_html_e( 'Attachment ID:', 'cloudflare-images-sync' ); ?></label>
				<input type="number" id="attachment_id" name="attachment_id" value="<?php echo esc_attr( $attachment_id ); ?>" min="1" class="small-text" />
				<input type="submit" class="button" value="<?php esc_attr_e( 'Load', 'cloudflare-images-sync' ); ?>" />
			</p>
			<p class="description">
				<?php esc_html_e( 'Load a Media Library image to preview how it looks with each preset variant. The image will be uploaded to Cloudflare on demand.', 'cloudflare-images-sync' ); ?>
			</p>
		</form>

		<?php
		if ( $attachment_id <= 0 ) {
			return;
		}

		$cf_image_id = (string) get_post_meta( $attachment_id, OptionKeys::META_PREVIEW_IMAGE_ID, true );

		if ( $cf_image_id === '' ) {
			?>
			<p><?php esc_html_e( 'This attachment has not been uploaded to Cloudflare yet.', 'cloudflare-images-sync' ); ?></p>
			<form method="post">
				<?php wp_nonce_field( 'cfi_preview_upload' ); ?>
				<input type="hidden" name="page" value="cfi-preview" />
				<input type="submit" name="cfi_preview_upload" class="button-primary" value="<?php esc_attr_e( 'Upload for Preview', 'cloudflare-images-sync' ); ?>" />
			</form>
			<?php
			return;
		}

		$this->render_preset_grid( $cf_image_id );
	}

	/**
	 * Mode B: Post + Mapping preview.
	 *
	 * @return void
	 */
	private function render_post_mode(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only GET params for UI state.
		$post_id    = isset( $_GET['post_id'] ) ? absint( wp_unslash( $_GET['post_id'] ) ) : 0;
		$mapping_id = isset( $_GET['mapping_id'] ) ? sanitize_text_field( wp_unslash( $_GET['mapping_id'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$mappings_repo = new MappingsRepo();
		$all_mappings  = $mappings_repo->all();

		?>
		<form method="get">
			<input type="hidden" name="page" value="cfi-preview" />
			<input type="hidden" name="mode" value="post" />
			<p>
				<label for="post_id"><?php esc_html_e( 'Post ID:', 'cloudflare-images-sync' ); ?></label>
				<input type="number" id="post_id" name="post_id" value="<?php echo esc_attr( $post_id ); ?>" min="1" class="small-text" />

				<label for="mapping_id"><?php esc_html_e( 'Mapping:', 'cloudflare-images-sync' ); ?></label>
				<select id="mapping_id" name="mapping_id">
					<option value=""><?php esc_html_e( '— Select —', 'cloudflare-images-sync' ); ?></option>
					<?php foreach ( $all_mappings as $m ) : ?>
						<option value="<?php echo esc_attr( $m['id'] ); ?>" <?php selected( $mapping_id, $m['id'] ); ?>><?php echo esc_html( $m['post_type'] . ' → ' . ( $m['target']['url_meta'] ?? '' ) ); ?></option>
					<?php endforeach; ?>
				</select>

				<input type="submit" class="button" value="<?php esc_attr_e( 'Preview', 'cloudflare-images-sync' ); ?>" />
			</p>
			<p class="description">
				<?php esc_html_e( 'Check the current delivery URL for a post or use "Sync Now" to manually trigger image sync for the selected mapping.', 'cloudflare-images-sync' ); ?>
			</p>
		</form>

		<?php
		if ( $post_id <= 0 || $mapping_id === '' ) {
			return;
		}

		$mapping = $mappings_repo->find( $mapping_id );
		if ( ! $mapping ) {
			echo '<p>' . esc_html__( 'Mapping not found.', 'cloudflare-images-sync' ) . '</p>';
			return;
		}

		// Resolve source.
		$resolved = SourceResolver::resolve( $post_id, $mapping['source'] ?? array() );

		if ( $resolved->is_empty() ) {
			echo '<p>' . esc_html__( 'Source field is empty for this post.', 'cloudflare-images-sync' ) . '</p>';
			return;
		}

		// Show current stored URL.
		$url_meta   = $mapping['target']['url_meta'] ?? '';
		$stored_url = $url_meta ? (string) get_post_meta( $post_id, $url_meta, true ) : '';

		if ( $stored_url !== '' ) {
			echo '<h3>' . esc_html__( 'Current Delivery URL', 'cloudflare-images-sync' ) . '</h3>';
			echo '<p><code>' . esc_html( $stored_url ) . '</code></p>';
			echo '<p><img src="' . esc_url( $stored_url ) . '" class="cfi-preview-img" loading="lazy" /></p>';
		} else {
			echo '<p>' . esc_html__( 'No delivery URL stored yet.', 'cloudflare-images-sync' ) . '</p>';
		}

		?>
		<form method="post">
			<?php wp_nonce_field( 'cfi_sync_now' ); ?>
			<input type="submit" name="cfi_sync_now" class="button-primary" value="<?php esc_attr_e( 'Sync Now', 'cloudflare-images-sync' ); ?>" />
		</form>
		<?php
	}

	/**
	 * Upload an attachment to Cloudflare for preview (on-demand).
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string Status message.
	 */
	private function ensure_preview_uploaded( int $attachment_id ): string {
		$file_path = get_attached_file( $attachment_id );

		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return __( 'Attachment file not found.', 'cloudflare-images-sync' );
		}

		// Check if already uploaded and unchanged.
		$stored_sig = (string) get_post_meta( $attachment_id, OptionKeys::META_PREVIEW_SIG, true );
		$cf_id      = (string) get_post_meta( $attachment_id, OptionKeys::META_PREVIEW_IMAGE_ID, true );

		if ( $cf_id !== '' && ! Signature::has_changed( $file_path, $stored_sig ) ) {
			return __( 'Already uploaded (unchanged).', 'cloudflare-images-sync' );
		}

		$client = CloudflareImagesClient::from_settings();
		if ( is_wp_error( $client ) ) {
			return $client->get_error_message();
		}

		// Delete old if re-uploading.
		if ( $cf_id !== '' ) {
			$client->delete( $cf_id );
		}

		$result = $client->upload(
			$file_path,
			array(
				'wp_attachment_id' => $attachment_id,
				'purpose'          => 'preview',
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result->get_error_message();
		}

		$new_cf_id = $result['id'] ?? '';
		$new_sig   = Signature::compute( $file_path );

		if ( is_wp_error( $new_sig ) ) {
			$new_sig = '';
		}

		update_post_meta( $attachment_id, OptionKeys::META_PREVIEW_IMAGE_ID, $new_cf_id );
		update_post_meta( $attachment_id, OptionKeys::META_PREVIEW_SIG, $new_sig );

		return __( 'Uploaded successfully.', 'cloudflare-images-sync' );
	}

	/**
	 * Render a grid of preset variant previews.
	 *
	 * @param string $cf_image_id Cloudflare image ID.
	 * @return void
	 */
	private function render_preset_grid( string $cf_image_id ): void {
		$settings = ( new SettingsRepo() )->get();
		$builder  = new UrlBuilder( $settings['account_hash'] );
		$presets  = ( new PresetsRepo() )->all();

		if ( empty( $presets ) ) {
			echo '<p>' . esc_html__( 'No presets configured. Add presets first.', 'cloudflare-images-sync' ) . '</p>';
			return;
		}

		echo '<h3>' . esc_html__( 'Variant Previews', 'cloudflare-images-sync' ) . '</h3>';
		echo '<div class="cfi-preset-grid">';

		foreach ( $presets as $preset ) {
			$url = $builder->url_from_preset( $cf_image_id, $preset );

			if ( is_wp_error( $url ) ) {
				continue;
			}

			echo '<div class="cfi-preset-card">';
			echo '<h4>' . esc_html( $preset['name'] ) . '</h4>';
			echo '<img src="' . esc_url( $url ) . '" loading="lazy" />';
			echo '<p><code class="cfi-copy-url" title="Click to copy">' . esc_html( $url ) . '</code></p>';
			echo '</div>';
		}

		echo '</div>';
	}
}
