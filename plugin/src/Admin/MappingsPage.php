<?php
/**
 * Mappings admin page.
 *
 * @package CloudflareImagesSync
 */

namespace CFI\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CFI\Repos\Defaults;
use CFI\Repos\MappingsRepo;
use CFI\Repos\PresetsRepo;
use CFI\Support\Validators;

/**
 * Mappings CRUD page with Bulk Sync trigger.
 */
class MappingsPage {

	use AdminNotice;

	/**
	 * Mappings repository instance.
	 *
	 * @var MappingsRepo
	 */
	private MappingsRepo $repo;

	/**
	 * Presets repository instance.
	 *
	 * @var PresetsRepo
	 */
	private PresetsRepo $presets;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->repo    = new MappingsRepo();
		$this->presets = new PresetsRepo();
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

		$redirect_url = admin_url( 'admin.php?page=cfi-mappings' );

		// Handle delete (GET with nonce).
		if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && ! empty( $_GET['mapping_id'] ) ) {
			$mapping_id = sanitize_text_field( wp_unslash( $_GET['mapping_id'] ) );
			if ( ! Validators::is_valid_id( $mapping_id, 'map' ) ) {
				$this->redirect_with_notice( $redirect_url, __( 'Invalid mapping ID.', 'cloudflare-images-sync' ), 'error' );
			}
			check_admin_referer( 'cfi_delete_mapping_' . $mapping_id );
			$result = $this->repo->delete( $mapping_id );

			if ( is_wp_error( $result ) ) {
				$this->redirect_with_notice( $redirect_url, $result->get_error_message(), 'error' );
			}

			$this->redirect_with_notice( $redirect_url, __( 'Mapping deleted.', 'cloudflare-images-sync' ) );
		}

		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
			return;
		}

		// Handle bulk sync.
		if ( isset( $_POST['cfi_bulk_sync'] ) && ! empty( $_POST['bulk_mapping_id'] ) ) {
			check_admin_referer( 'cfi_bulk_sync' );
			$mapping_id = sanitize_text_field( wp_unslash( $_POST['bulk_mapping_id'] ) );
			if ( ! Validators::is_valid_id( $mapping_id, 'map' ) ) {
				$this->redirect_with_notice( $redirect_url, __( 'Invalid mapping ID.', 'cloudflare-images-sync' ), 'error' );
			}
			$message    = $this->enqueue_bulk_sync( $mapping_id );
			$type       = strpos( $message, 'enqueued' ) !== false ? 'success' : 'error';
			$this->redirect_with_notice( $redirect_url, $message, $type );
		}

		// Handle create/update.
		if ( isset( $_POST['cfi_save_mapping'] ) ) {
			check_admin_referer( 'cfi_mapping_save' );
			$message = $this->handle_save();
			$type    = strpos( $message, 'error' ) !== false || strpos( $message, 'Error' ) !== false ? 'error' : 'success';
			$this->redirect_with_notice( $redirect_url, $message, $type );
		}
	}

	/**
	 * Render the mappings page (display only — actions handled in handle_actions).
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'cloudflare-images-sync' ) );
		}

		$mappings = $this->repo->all();
		$editing  = null;

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only GET params for UI state.
		if ( isset( $_GET['action'] ) && $_GET['action'] === 'edit' && ! empty( $_GET['mapping_id'] ) ) {
			$editing = $this->repo->find( sanitize_text_field( wp_unslash( $_GET['mapping_id'] ) ) );
		}

		$show_form = isset( $_GET['action'] ) && in_array( sanitize_text_field( wp_unslash( $_GET['action'] ) ), array( 'new', 'edit' ), true );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		?>
		<div class="wrap">
			<h1>
				<?php esc_html_e( 'CF Images — Mappings', 'cloudflare-images-sync' ); ?>
				<?php if ( ! $show_form ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=cfi-mappings&action=new' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New', 'cloudflare-images-sync' ); ?></a>
				<?php endif; ?>
			</h1>

			<?php $this->render_notice(); ?>

			<?php if ( $show_form ) : ?>
				<?php $this->render_form( $editing ); ?>
			<?php else : ?>
				<?php $this->render_table( $mappings ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Handle form save. Nonce already verified in handle_actions().
	 *
	 * @return string Status message.
	 */
	private function handle_save(): string {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verified in handle_actions().
		$edit_id = sanitize_text_field( wp_unslash( $_POST['mapping_id'] ?? '' ) );
		if ( $edit_id !== '' && ! Validators::is_valid_id( $edit_id, 'map' ) ) {
			return __( 'Invalid mapping ID.', 'cloudflare-images-sync' );
		}

		$data = array(
			'post_type' => sanitize_text_field( wp_unslash( $_POST['post_type'] ?? '' ) ),
			'status'    => sanitize_text_field( wp_unslash( $_POST['status'] ?? 'any' ) ),
			'triggers'  => array(
				'save_post'     => ! empty( $_POST['trigger_save_post'] ),
				'acf_save_post' => ! empty( $_POST['trigger_acf_save_post'] ),
			),
			'source'    => array(
				'type' => sanitize_text_field( wp_unslash( $_POST['source_type'] ?? '' ) ),
				'key'  => sanitize_text_field( wp_unslash( $_POST['source_key'] ?? '' ) ),
			),
			'target'    => array(
				'url_meta' => sanitize_text_field( wp_unslash( $_POST['target_url_meta'] ?? '' ) ),
				'id_meta'  => sanitize_text_field( wp_unslash( $_POST['target_id_meta'] ?? '' ) ),
				'sig_meta' => sanitize_text_field( wp_unslash( $_POST['target_sig_meta'] ?? '' ) ),
			),
			'behavior'  => array(
				'upload_if_missing'   => ! empty( $_POST['upload_if_missing'] ),
				'reupload_if_changed' => ! empty( $_POST['reupload_if_changed'] ),
				'clear_on_empty'      => ! empty( $_POST['clear_on_empty'] ),
				'store_cf_id_on_post' => ! empty( $_POST['store_cf_id_on_post'] ),
			),
			'preset_id' => sanitize_text_field( wp_unslash( $_POST['preset_id'] ?? '' ) ),
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( $data['preset_id'] !== '' && ! Validators::is_valid_id( $data['preset_id'], 'preset' ) ) {
			return __( 'Invalid preset ID.', 'cloudflare-images-sync' );
		}

		if ( $edit_id !== '' ) {
			$result = $this->repo->update( $edit_id, $data );
		} else {
			$result = $this->repo->create( $data );
		}

		if ( is_wp_error( $result ) ) {
			return $result->get_error_message();
		}

		return $edit_id ? __( 'Mapping updated.', 'cloudflare-images-sync' ) : __( 'Mapping created.', 'cloudflare-images-sync' );
	}

	/**
	 * Enqueue a bulk sync for a mapping.
	 *
	 * @param string $mapping_id Mapping ID.
	 * @return string Status message.
	 */
	private function enqueue_bulk_sync( string $mapping_id ): string {
		$mapping = $this->repo->find( $mapping_id );

		if ( $mapping === null ) {
			return __( 'Mapping not found.', 'cloudflare-images-sync' );
		}

		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			return __( 'Action Scheduler is not available. Install it or use WP-CLI for bulk sync.', 'cloudflare-images-sync' );
		}

		as_enqueue_async_action(
			'cfi_bulk_sync',
			array(
				'mapping_id' => $mapping_id,
				'offset'     => 0,
				'chunk_size' => 20,
			),
			'cfi'
		);

		return __( 'Bulk sync enqueued. Check Logs for progress.', 'cloudflare-images-sync' );
	}

	/**
	 * Render the mapping form.
	 *
	 * @param array<string, mixed>|null $mapping Existing mapping for editing, or null for new.
	 * @return void
	 */
	private function render_form( ?array $mapping ): void {
		$defaults     = Defaults::mapping();
		$m            = $mapping ?: $defaults;
		$source_types = Defaults::source_types();
		$presets      = $this->presets->all();
		$post_types   = get_post_types( array( 'public' => true ), 'objects' );

		?>
		<h2><?php echo $mapping ? esc_html__( 'Edit Mapping', 'cloudflare-images-sync' ) : esc_html__( 'New Mapping', 'cloudflare-images-sync' ); ?></h2>
		<form method="post">
			<?php wp_nonce_field( 'cfi_mapping_save' ); ?>
			<input type="hidden" name="mapping_id" value="<?php echo esc_attr( $m['id'] ?? '' ); ?>" />

			<table class="form-table">
				<tr>
					<th><label for="post_type"><?php esc_html_e( 'Post Type', 'cloudflare-images-sync' ); ?></label></th>
					<td>
						<select id="post_type" name="post_type" required>
							<option value=""><?php esc_html_e( '— Select —', 'cloudflare-images-sync' ); ?></option>
							<?php foreach ( $post_types as $pt ) : ?>
								<option value="<?php echo esc_attr( $pt->name ); ?>" <?php selected( $m['post_type'], $pt->name ); ?>><?php echo esc_html( $pt->labels->singular_name . ' (' . $pt->name . ')' ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="status"><?php esc_html_e( 'Post Status Filter', 'cloudflare-images-sync' ); ?></label></th>
					<td>
						<select id="status" name="status">
							<option value="any" <?php selected( $m['status'], 'any' ); ?>><?php esc_html_e( 'Any', 'cloudflare-images-sync' ); ?></option>
							<option value="publish" <?php selected( $m['status'], 'publish' ); ?>><?php esc_html_e( 'Published only', 'cloudflare-images-sync' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Triggers', 'cloudflare-images-sync' ); ?></th>
					<td>
						<label><input type="checkbox" name="trigger_save_post" value="1" <?php checked( $m['triggers']['save_post'] ?? true ); ?> /> save_post</label><br/>
						<label><input type="checkbox" name="trigger_acf_save_post" value="1" <?php checked( $m['triggers']['acf_save_post'] ?? true ); ?> /> acf/save_post</label>
					</td>
				</tr>
				<tr>
					<th><label for="source_type"><?php esc_html_e( 'Source Type', 'cloudflare-images-sync' ); ?></label></th>
					<td>
						<select id="source_type" name="source_type" required>
							<?php foreach ( $source_types as $st ) : ?>
								<option value="<?php echo esc_attr( $st ); ?>" <?php selected( $m['source']['type'] ?? '', $st ); ?>><?php echo esc_html( $st ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="source_key"><?php esc_html_e( 'Source Key', 'cloudflare-images-sync' ); ?></label></th>
					<td>
						<input type="text" id="source_key" name="source_key" value="<?php echo esc_attr( $m['source']['key'] ?? '' ); ?>" class="regular-text" />
						<p class="description"><?php esc_html_e( 'ACF field name or meta key. Not needed for featured_image / attachment_id.', 'cloudflare-images-sync' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="target_url_meta"><?php esc_html_e( 'Target: URL meta key', 'cloudflare-images-sync' ); ?></label></th>
					<td><input type="text" id="target_url_meta" name="target_url_meta" value="<?php echo esc_attr( $m['target']['url_meta'] ?? '' ); ?>" class="regular-text" required /></td>
				</tr>
				<tr>
					<th><label for="target_id_meta"><?php esc_html_e( 'Target: CF Image ID meta key', 'cloudflare-images-sync' ); ?></label></th>
					<td><input type="text" id="target_id_meta" name="target_id_meta" value="<?php echo esc_attr( $m['target']['id_meta'] ?? '' ); ?>" class="regular-text" /></td>
				</tr>
				<tr>
					<th><label for="target_sig_meta"><?php esc_html_e( 'Target: Signature meta key', 'cloudflare-images-sync' ); ?></label></th>
					<td><input type="text" id="target_sig_meta" name="target_sig_meta" value="<?php echo esc_attr( $m['target']['sig_meta'] ?? '' ); ?>" class="regular-text" /></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Behavior', 'cloudflare-images-sync' ); ?></th>
					<td>
						<label><input type="checkbox" name="upload_if_missing" value="1" <?php checked( $m['behavior']['upload_if_missing'] ?? true ); ?> /> <?php esc_html_e( 'Upload if no CF ID exists', 'cloudflare-images-sync' ); ?></label><br/>
						<label><input type="checkbox" name="reupload_if_changed" value="1" <?php checked( $m['behavior']['reupload_if_changed'] ?? true ); ?> /> <?php esc_html_e( 'Re-upload if file changed', 'cloudflare-images-sync' ); ?></label><br/>
						<label><input type="checkbox" name="clear_on_empty" value="1" <?php checked( $m['behavior']['clear_on_empty'] ?? true ); ?> /> <?php esc_html_e( 'Clear target meta if source is empty', 'cloudflare-images-sync' ); ?></label><br/>
						<label><input type="checkbox" name="store_cf_id_on_post" value="1" <?php checked( $m['behavior']['store_cf_id_on_post'] ?? true ); ?> /> <?php esc_html_e( 'Store CF image ID on post', 'cloudflare-images-sync' ); ?></label>
					</td>
				</tr>
				<tr>
					<th><label for="preset_id"><?php esc_html_e( 'Preset', 'cloudflare-images-sync' ); ?></label></th>
					<td>
						<select id="preset_id" name="preset_id">
							<option value=""><?php esc_html_e( '— Default (public) —', 'cloudflare-images-sync' ); ?></option>
							<?php foreach ( $presets as $preset ) : ?>
								<option value="<?php echo esc_attr( $preset['id'] ); ?>" <?php selected( $m['preset_id'] ?? '', $preset['id'] ); ?>><?php echo esc_html( $preset['name'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</table>

			<p class="submit">
				<input type="submit" name="cfi_save_mapping" class="button-primary" value="<?php esc_attr_e( 'Save Mapping', 'cloudflare-images-sync' ); ?>" />
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=cfi-mappings' ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'cloudflare-images-sync' ); ?></a>
			</p>
		</form>
		<?php
	}

	/**
	 * Render the mappings list table.
	 *
	 * @param array<string, array<string, mixed>> $mappings All mappings.
	 * @return void
	 */
	private function render_table( array $mappings ): void {
		if ( empty( $mappings ) ) {
			echo '<p>' . esc_html__( 'No mappings yet. Click "Add New" to create one.', 'cloudflare-images-sync' ) . '</p>';
			return;
		}

		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Post Type', 'cloudflare-images-sync' ); ?></th>
					<th><?php esc_html_e( 'Source', 'cloudflare-images-sync' ); ?></th>
					<th><?php esc_html_e( 'Target URL meta', 'cloudflare-images-sync' ); ?></th>
					<th><?php esc_html_e( 'Preset', 'cloudflare-images-sync' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'cloudflare-images-sync' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $mappings as $map ) : ?>
					<?php
					$preset_name = '(public)';
					if ( ! empty( $map['preset_id'] ) ) {
						$p           = $this->presets->find( $map['preset_id'] );
						$preset_name = $p ? $p['name'] : '(deleted)';
					}
					$source_label = ( $map['source']['type'] ?? '' );
					if ( ! empty( $map['source']['key'] ) ) {
						$source_label .= ': ' . $map['source']['key'];
					}
					?>
					<tr>
						<td><strong><?php echo esc_html( $map['post_type'] ); ?></strong><br/><code><?php echo esc_html( $map['id'] ); ?></code></td>
						<td><code><?php echo esc_html( $source_label ); ?></code></td>
						<td><code><?php echo esc_html( $map['target']['url_meta'] ?? '' ); ?></code></td>
						<td><?php echo esc_html( $preset_name ); ?></td>
						<td>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=cfi-mappings&action=edit&mapping_id=' . $map['id'] ) ); ?>"><?php esc_html_e( 'Edit', 'cloudflare-images-sync' ); ?></a> |
							<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=cfi-mappings&action=delete&mapping_id=' . $map['id'] ), 'cfi_delete_mapping_' . $map['id'] ) ); ?>" onclick="return confirm('<?php esc_attr_e( 'Delete this mapping?', 'cloudflare-images-sync' ); ?>');"><?php esc_html_e( 'Delete', 'cloudflare-images-sync' ); ?></a> |
							<form method="post" style="display:inline;">
								<?php wp_nonce_field( 'cfi_bulk_sync' ); ?>
								<input type="hidden" name="bulk_mapping_id" value="<?php echo esc_attr( $map['id'] ); ?>" />
								<button type="submit" name="cfi_bulk_sync" class="button-link" onclick="return confirm('<?php esc_attr_e( 'Enqueue bulk sync for this mapping?', 'cloudflare-images-sync' ); ?>');"><?php esc_html_e( 'Bulk Sync', 'cloudflare-images-sync' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}
