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
				$this->redirect_with_notice( $redirect_url, __( 'Invalid mapping ID.', 'cfi-images-sync' ), 'error' );
			}
			check_admin_referer( 'cfi_delete_mapping_' . $mapping_id );
			$result = $this->repo->delete( $mapping_id );

			if ( is_wp_error( $result ) ) {
				$this->redirect_with_notice( $redirect_url, $result->get_error_message(), 'error' );
			}

			$this->redirect_with_notice( $redirect_url, __( 'Mapping deleted.', 'cfi-images-sync' ) );
		}

		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
			return;
		}

		// Handle bulk sync.
		if ( isset( $_POST['cfi_bulk_sync'] ) && ! empty( $_POST['bulk_mapping_id'] ) ) {
			check_admin_referer( 'cfi_bulk_sync' );
			$mapping_id = sanitize_text_field( wp_unslash( $_POST['bulk_mapping_id'] ) );
			if ( ! Validators::is_valid_id( $mapping_id, 'map' ) ) {
				$this->redirect_with_notice( $redirect_url, __( 'Invalid mapping ID.', 'cfi-images-sync' ), 'error' );
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
			wp_die( esc_html__( 'Unauthorized.', 'cfi-images-sync' ) );
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
				<?php esc_html_e( 'CF Images — Mappings', 'cfi-images-sync' ); ?>
				<?php if ( ! $show_form ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=cfi-mappings&action=new' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New', 'cfi-images-sync' ); ?></a>
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
			return __( 'Invalid mapping ID.', 'cfi-images-sync' );
		}

		$data = array(
			'post_type' => sanitize_text_field( wp_unslash( $_POST['cfi_post_type'] ?? '' ) ),
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
				'upload_if_missing'     => ! empty( $_POST['upload_if_missing'] ),
				'reupload_if_changed'   => ! empty( $_POST['reupload_if_changed'] ),
				'clear_on_empty'        => ! empty( $_POST['clear_on_empty'] ),
				'store_cf_id_on_post'   => ! empty( $_POST['store_cf_id_on_post'] ),
				'delete_cf_on_reupload' => ! empty( $_POST['delete_cf_on_reupload'] ),
			),
			'preset_id' => sanitize_text_field( wp_unslash( $_POST['preset_id'] ?? '' ) ),
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		// Reject WordPress internal meta keys as destination targets.
		foreach ( array( 'url_meta', 'id_meta', 'sig_meta' ) as $target_field ) {
			$key = $data['target'][ $target_field ];
			if ( $key !== '' && $this->is_reserved_meta_key( $key ) ) {
				return sprintf(
					/* translators: %s: meta key name */
					__( 'The meta key "%s" is reserved by WordPress and cannot be used as a destination.', 'cfi-images-sync' ),
					$key
				);
			}
		}

		if ( $data['preset_id'] !== '' ) {
			if ( ! Validators::is_valid_id( $data['preset_id'], 'preset' ) ) {
				return __( 'Invalid preset ID format.', 'cfi-images-sync' );
			}
			if ( $this->presets->find( $data['preset_id'] ) === null ) {
				return __( 'Selected preset does not exist.', 'cfi-images-sync' );
			}
		}

		if ( $edit_id !== '' ) {
			$result = $this->repo->update( $edit_id, $data );
		} else {
			$result = $this->repo->create( $data );
		}

		if ( is_wp_error( $result ) ) {
			return $result->get_error_message();
		}

		return $edit_id ? __( 'Mapping updated.', 'cfi-images-sync' ) : __( 'Mapping created.', 'cfi-images-sync' );
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
			return __( 'Mapping not found.', 'cfi-images-sync' );
		}

		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			return __( 'Action Scheduler is not available. Install it or use WP-CLI for bulk sync.', 'cfi-images-sync' );
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

		return __( 'Bulk sync enqueued. Check Logs for progress.', 'cfi-images-sync' );
	}

	/**
	 * Human-readable labels for source types.
	 *
	 * @return array<string, string>
	 */
	private function source_type_labels(): array {
		return array(
			'acf_field'                => __( 'ACF Image Field', 'cfi-images-sync' ),
			'featured_image'           => __( 'Featured Image (Thumbnail)', 'cfi-images-sync' ),
			'post_meta_attachment_id'  => __( 'Meta Field → Attachment ID', 'cfi-images-sync' ),
			'post_meta_url'            => __( 'Meta Field → Image URL', 'cfi-images-sync' ),
			'attachment_id'            => __( 'Attachment (post is the image)', 'cfi-images-sync' ),
		);
	}

	/**
	 * AJAX handler: return meta keys for a given post type.
	 *
	 * Returns items in unified suggestion format: [{name, label, type}].
	 * Caches results in a transient for 5 minutes.
	 *
	 * @return void
	 */
	public function ajax_meta_keys(): void {
		check_ajax_referer( 'cfi_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce checked via check_ajax_referer above.
		$post_type = sanitize_key( wp_unslash( $_GET['post_type'] ?? '' ) );

		if ( $post_type === '' || ! post_type_exists( $post_type ) ) {
			wp_send_json_success( array() );
		}

		$transient_key = 'cfi_meta_keys_' . $post_type;
		$cached        = get_transient( $transient_key );

		if ( is_array( $cached ) ) {
			wp_send_json_success( $cached );
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- admin AJAX lookup, cached via transient.
		$keys = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT pm.meta_key
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE p.post_type = %s
				ORDER BY pm.meta_key
				LIMIT 200",
				$post_type
			)
		);

		$items = array();
		foreach ( ( $keys ?: array() ) as $key ) {
			$items[] = array(
				'name'  => $key,
				'label' => $key,
				'type'  => 'meta',
			);
		}

		set_transient( $transient_key, $items, 300 );

		wp_send_json_success( $items );
	}

	/**
	 * AJAX handler: return ACF image fields for a given post type.
	 *
	 * Uses ACF's location rules to return only image fields assigned to the post type.
	 * Recursively traverses subfields (repeater, group, flexible_content).
	 *
	 * @return void
	 */
	public function ajax_acf_fields(): void {
		check_ajax_referer( 'cfi_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}

		if ( ! function_exists( 'acf_get_field_groups' ) ) {
			wp_send_json_success( array() );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce checked via check_ajax_referer above.
		$post_type = sanitize_key( wp_unslash( $_GET['post_type'] ?? '' ) );

		if ( $post_type === '' || ! post_type_exists( $post_type ) ) {
			wp_send_json_success( array() );
		}

		$groups = acf_get_field_groups( array( 'post_type' => $post_type ) );
		$result = array();

		foreach ( $groups as $group ) {
			$fields = acf_get_fields( $group['key'] );
			if ( ! is_array( $fields ) ) {
				continue;
			}
			$group_title = $group['title'] ?? '';
			$this->collect_image_fields( $fields, $group_title, $result );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Recursively collect image fields from ACF field array.
	 *
	 * Supports subfields in repeater/group and layouts in flexible_content.
	 *
	 * @param array<int, array<string, mixed>> $fields      ACF fields array.
	 * @param string                           $group_title Field group title for context.
	 * @param array<int, array<string, mixed>> $result      Result accumulator (passed by reference).
	 * @return void
	 */
	private function collect_image_fields( array $fields, string $group_title, array &$result ): void {
		foreach ( $fields as $field ) {
			$name = $field['name'] ?? '';
			if ( $name === '' ) {
				continue;
			}

			if ( ( $field['type'] ?? '' ) === 'image' ) {
				$result[] = array(
					'name'  => $name,
					'label' => ( $field['label'] ?? '' ) !== '' ? $field['label'] : $name,
					'type'  => 'acf_image',
					'group' => $group_title,
				);
			}

			// Recurse into repeater / group subfields.
			if ( ! empty( $field['sub_fields'] ) && is_array( $field['sub_fields'] ) ) {
				$this->collect_image_fields( $field['sub_fields'], $group_title, $result );
			}

			// Recurse into flexible_content layouts.
			if ( ( $field['type'] ?? '' ) === 'flexible_content' && ! empty( $field['layouts'] ) && is_array( $field['layouts'] ) ) {
				foreach ( $field['layouts'] as $layout ) {
					if ( ! empty( $layout['sub_fields'] ) && is_array( $layout['sub_fields'] ) ) {
						$this->collect_image_fields( $layout['sub_fields'], $group_title, $result );
					}
				}
			}
		}
	}

	/**
	 * AJAX handler: dry-run a mapping against a single post.
	 *
	 * Resolves the source, checks whether an upload would be needed,
	 * and previews the delivery URL — without performing actual sync.
	 *
	 * @return void
	 */
	public function ajax_test_mapping(): void {
		check_ajax_referer( 'cfi_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce checked via check_ajax_referer above.
		$post_id = absint( wp_unslash( $_POST['post_id'] ?? 0 ) );

		if ( $post_id <= 0 ) {
			wp_send_json_error( __( 'Please enter a valid post ID.', 'cfi-images-sync' ) );
		}

		$post = get_post( $post_id );

		if ( ! $post ) {
			wp_send_json_error( __( 'Post not found.', 'cfi-images-sync' ) );
		}

		$source = array(
			'type' => sanitize_text_field( wp_unslash( $_POST['source_type'] ?? '' ) ),
			'key'  => sanitize_text_field( wp_unslash( $_POST['source_key'] ?? '' ) ),
		);

		$target = array(
			'url_meta' => sanitize_text_field( wp_unslash( $_POST['target_url_meta'] ?? '' ) ),
			'id_meta'  => sanitize_text_field( wp_unslash( $_POST['target_id_meta'] ?? '' ) ),
			'sig_meta' => sanitize_text_field( wp_unslash( $_POST['target_sig_meta'] ?? '' ) ),
		);

		$preset_id = sanitize_text_field( wp_unslash( $_POST['preset_id'] ?? '' ) );

		$behavior = array(
			'upload_if_missing'   => ! empty( $_POST['upload_if_missing'] ),
			'reupload_if_changed' => ! empty( $_POST['reupload_if_changed'] ),
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		// Resolve source.
		$resolved = \CFI\Core\SourceResolver::resolve( $post_id, $source );

		$result = array(
			'post_title'    => $post->post_title,
			'post_type'     => $post->post_type,
			'source_found'  => ! $resolved->is_empty(),
			'attachment_id' => 0,
			'file_name'     => '',
			'would_upload'  => false,
			'upload_reason' => '',
			'preview_url'   => '',
			'current_url'   => '',
		);

		if ( $resolved->is_empty() ) {
			$result['upload_reason'] = __( 'Source image not found or empty.', 'cfi-images-sync' );
			wp_send_json_success( $result );
		}

		$att_id    = $resolved->get_attachment_id();
		$file_path = $resolved->get_file_path();

		$result['attachment_id'] = $att_id;
		$result['file_name']     = wp_basename( $file_path );

		// Check attachment-level CF cache.
		$att_cf_id = $att_id > 0 ? (string) get_post_meta( $att_id, \CFI\Repos\OptionKeys::META_CF_IMAGE_ID, true ) : '';
		$att_sig   = $att_id > 0 ? (string) get_post_meta( $att_id, \CFI\Repos\OptionKeys::META_SIG, true ) : '';

		// Check per-post stored data.
		$sig_meta    = $target['sig_meta'];
		$stored_sig  = $sig_meta !== '' ? (string) get_post_meta( $post_id, $sig_meta, true ) : '';
		$id_meta     = $target['id_meta'];
		$stored_cfid = $id_meta !== '' ? (string) get_post_meta( $post_id, $id_meta, true ) : '';

		// Determine upload necessity (mirrors SyncEngine logic).
		if ( $att_cf_id !== '' && ! \CFI\Core\Signature::has_changed( $file_path, $att_sig ) ) {
			$result['upload_reason'] = __( 'Reuse: image already on Cloudflare (attachment cache hit).', 'cfi-images-sync' );
		} elseif ( $stored_cfid === '' && $att_cf_id === '' && ! empty( $behavior['upload_if_missing'] ) ) {
			$result['would_upload']  = true;
			$result['upload_reason'] = __( 'New upload: image not yet on Cloudflare.', 'cfi-images-sync' );
		} elseif ( $stored_cfid !== '' && ! empty( $behavior['reupload_if_changed'] ) ) {
			if ( \CFI\Core\Signature::has_changed( $file_path, $stored_sig ) ) {
				$result['would_upload']  = true;
				$result['upload_reason'] = __( 'Re-upload: local file has changed since last sync.', 'cfi-images-sync' );
			} else {
				$result['upload_reason'] = __( 'Skip: file unchanged since last sync.', 'cfi-images-sync' );
			}
		} else {
			$result['upload_reason'] = __( 'Skip: upload not needed or not enabled by behavior settings.', 'cfi-images-sync' );
		}

		// Preview delivery URL.
		$cf_id = $stored_cfid !== '' ? $stored_cfid : $att_cf_id;

		if ( $cf_id !== '' ) {
			$settings = ( new \CFI\Repos\SettingsRepo() )->get();
			$builder  = new \CFI\Core\UrlBuilder( $settings['account_hash'] );
			$variant  = 'public';

			if ( $preset_id !== '' ) {
				$preset = $this->presets->find( $preset_id );
				if ( $preset && ! empty( $preset['variant'] ) ) {
					$variant = $preset['variant'];
				}
			}

			$url = $builder->url( $cf_id, $variant );
			if ( ! is_wp_error( $url ) ) {
				$result['preview_url'] = $url;
			}
		}

		// Current stored URL.
		if ( $target['url_meta'] !== '' ) {
			$result['current_url'] = (string) get_post_meta( $post_id, $target['url_meta'], true );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Check if a meta key belongs to WordPress core internals.
	 *
	 * @param string $key Meta key name.
	 * @return bool
	 */
	private function is_reserved_meta_key( string $key ): bool {
		$prefixes = array( '_wp_', '_edit_', '_oembed_' );
		foreach ( $prefixes as $prefix ) {
			if ( str_starts_with( $key, $prefix ) ) {
				return true;
			}
		}

		return in_array( $key, array( '_pingme', '_encloseme', '_thumbnail_id' ), true );
	}

	/**
	 * Render the mapping form.
	 *
	 * @param array<string, mixed>|null $mapping Existing mapping for editing, or null for new.
	 * @return void
	 */
	private function render_form( ?array $mapping ): void {
		$defaults      = Defaults::mapping();
		$m             = $mapping ?: $defaults;
		$source_types  = Defaults::source_types();
		$source_labels = $this->source_type_labels();
		$has_acf       = class_exists( 'ACF' );
		$presets       = $this->presets->all();
		$post_types    = get_post_types( array( 'public' => true ), 'objects' );

		// Localize mapping form JS data.
		wp_localize_script(
			'cfi-admin',
			'cfiMapping',
			array(
				'hasAcf'          => $has_acf,
				'sourceKeyConfig' => array(
					'acf_field'               => array(
						'label'       => __( 'ACF Field Name', 'cfi-images-sync' ),
						'placeholder' => 'hero_image',
						'required'    => true,
					),
					'post_meta_attachment_id'  => array(
						'label'       => __( 'Meta Key', 'cfi-images-sync' ),
						'placeholder' => '_thumbnail_id',
						'required'    => true,
					),
					'post_meta_url'            => array(
						'label'       => __( 'Meta Key', 'cfi-images-sync' ),
						'placeholder' => 'og_image_url',
						'required'    => true,
					),
					'featured_image'           => array( 'hidden' => true ),
					'attachment_id'            => array( 'hidden' => true ),
				),
				'i18n'            => array(
					'required'      => __( 'This field is required.', 'cfi-images-sync' ),
					'invalidKey'    => __( 'Only letters, numbers, underscores, hyphens, colons, and dots are allowed.', 'cfi-images-sync' ),
					'keyTooLong'    => __( 'Maximum 191 characters.', 'cfi-images-sync' ),
					'selectPostType' => __( 'Please select a post type.', 'cfi-images-sync' ),
				),
			)
		);

		?>
		<h2>
			<?php echo $mapping ? esc_html__( 'Edit Mapping', 'cfi-images-sync' ) : esc_html__( 'New Mapping', 'cfi-images-sync' ); ?>
		</h2>
		<form method="post" id="cfi-mapping-form" novalidate>
			<?php wp_nonce_field( 'cfi_mapping_save' ); ?>
			<input type="hidden" name="mapping_id" value="<?php echo esc_attr( $m['id'] ?? '' ); ?>" />

			<?php // ── Section 1: Source ──────────────────────────────────────── ?>
			<div class="cfi-form-section">
				<h3><?php esc_html_e( 'Image Source', 'cfi-images-sync' ); ?></h3>
				<p class="cfi-section-desc">
					<?php esc_html_e( 'Define which posts to process and where the original image comes from.', 'cfi-images-sync' ); ?>
				</p>
				<table class="form-table">
					<tr>
						<th>
							<label for="cfi_post_type">
								<?php esc_html_e( 'Post Type', 'cfi-images-sync' ); ?>
							</label>
						</th>
						<td>
							<select id="cfi_post_type" name="cfi_post_type" required>
								<option value="">
									<?php esc_html_e( '— Select —', 'cfi-images-sync' ); ?>
								</option>
								<?php foreach ( $post_types as $pt ) : ?>
									<option value="<?php echo esc_attr( $pt->name ); ?>" <?php selected( $m['post_type'], $pt->name ); ?>>
										<?php echo esc_html( $pt->labels->singular_name . ' (' . $pt->name . ')' ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description">
								<?php esc_html_e( 'Choose which content type this mapping applies to.', 'cfi-images-sync' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th>
							<label for="status">
								<?php esc_html_e( 'Post Status', 'cfi-images-sync' ); ?>
							</label>
						</th>
						<td>
							<select id="status" name="status">
								<option value="any" <?php selected( $m['status'], 'any' ); ?>>
									<?php esc_html_e( 'Any', 'cfi-images-sync' ); ?>
								</option>
								<option value="publish" <?php selected( $m['status'], 'publish' ); ?>>
									<?php esc_html_e( 'Published only', 'cfi-images-sync' ); ?>
								</option>
							</select>
							<p class="description">
								<?php esc_html_e( 'Sync only published posts, or include drafts and other statuses.', 'cfi-images-sync' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th>
							<label for="source_type">
								<?php esc_html_e( 'Source Type', 'cfi-images-sync' ); ?>
							</label>
						</th>
						<td>
							<select id="source_type" name="source_type" required>
								<?php foreach ( $source_types as $st ) : ?>
									<?php
									$label = $source_labels[ $st ] ?? $st;
									if ( 'acf_field' === $st && ! $has_acf ) :
										?>
										<option value="<?php echo esc_attr( $st ); ?>" disabled>
											<?php
											/* translators: %s: source type label */
											echo esc_html( sprintf( __( '%s (ACF not active)', 'cfi-images-sync' ), $label ) );
											?>
										</option>
									<?php else : ?>
										<option value="<?php echo esc_attr( $st ); ?>" <?php selected( $m['source']['type'] ?? '', $st ); ?>>
											<?php echo esc_html( $label ); ?>
										</option>
									<?php endif; ?>
								<?php endforeach; ?>
							</select>
							<p class="description">
								<?php esc_html_e( 'Where does the original image come from?', 'cfi-images-sync' ); ?>
							</p>
						</td>
					</tr>
					<tr id="cfi-source-key-row">
						<th>
							<label for="source_key" id="cfi-source-key-label">
								<?php esc_html_e( 'Source Key', 'cfi-images-sync' ); ?>
							</label>
						</th>
						<td>
							<input type="text" id="source_key" name="source_key" value="<?php echo esc_attr( $m['source']['key'] ?? '' ); ?>" class="regular-text" autocomplete="off" />
							<p class="description" id="cfi-source-key-desc">
								<?php esc_html_e( 'The field name or meta key that holds the image. Not needed for Featured Image or Attachment source types.', 'cfi-images-sync' ); ?>
							</p>
						</td>
					</tr>
				</table>
			</div>

			<?php
			// ── Section 2: Destination ────────────────────────────────────
			// phpcs:disable Generic.WhiteSpace.ScopeIndent.IncorrectExact -- inline SVG.
			$copy_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 19 22" width="16" height="16" fill="currentColor"><path d="M17 20H6V6h11m0-2H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2m-3-4H2a2 2 0 0 0-2 2v14h2V2h12z"/></svg>';
			// phpcs:enable Generic.WhiteSpace.ScopeIndent.IncorrectExact
			?>
			<div class="cfi-form-section">
				<h3><?php esc_html_e( 'Destination', 'cfi-images-sync' ); ?></h3>
				<p class="cfi-section-desc">
					<?php esc_html_e( 'Define where the Cloudflare delivery URL and metadata will be stored on each post. Meta keys are created automatically on first sync — no need to register them beforehand.', 'cfi-images-sync' ); ?>
				</p>
				<table class="form-table">
					<tr>
						<th>
							<label for="target_url_meta">
								<?php esc_html_e( 'Delivery URL Meta Key', 'cfi-images-sync' ); ?>
							</label>
						</th>
						<td>
							<div class="cfi-input-with-copy">
								<input type="text" id="target_url_meta" name="target_url_meta" value="<?php echo esc_attr( $m['target']['url_meta'] ?? '' ); ?>" class="regular-text" placeholder="_cf_delivery_url" required />
								<button type="button" class="cfi-copy-btn" data-copy-from="#target_url_meta" aria-label="<?php esc_attr_e( 'Copy key', 'cfi-images-sync' ); ?>" title="<?php esc_attr_e( 'Copy key', 'cfi-images-sync' ); ?>" disabled>
									<?php echo $copy_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
								</button>
							</div>
							<p class="description">
								<?php esc_html_e( 'The post meta key where the Cloudflare delivery URL will be stored. Use this key in your theme to display the optimized image.', 'cfi-images-sync' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th>
							<label for="target_id_meta">
								<?php esc_html_e( 'CF Image ID Meta Key', 'cfi-images-sync' ); ?>
							</label>
						</th>
						<td>
							<div class="cfi-input-with-copy">
								<input type="text" id="target_id_meta" name="target_id_meta" value="<?php echo esc_attr( $m['target']['id_meta'] ?? '' ); ?>" class="regular-text" placeholder="_cf_image_id" />
								<button type="button" class="cfi-copy-btn" data-copy-from="#target_id_meta" aria-label="<?php esc_attr_e( 'Copy key', 'cfi-images-sync' ); ?>" title="<?php esc_attr_e( 'Copy key', 'cfi-images-sync' ); ?>" disabled>
									<?php echo $copy_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
								</button>
							</div>
							<p class="description">
								<?php esc_html_e( 'Optional. Stores the Cloudflare image ID for management purposes.', 'cfi-images-sync' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th>
							<label for="target_sig_meta">
								<?php esc_html_e( 'Change Signature Meta Key', 'cfi-images-sync' ); ?>
							</label>
						</th>
						<td>
							<div class="cfi-input-with-copy">
								<input type="text" id="target_sig_meta" name="target_sig_meta" value="<?php echo esc_attr( $m['target']['sig_meta'] ?? '' ); ?>" class="regular-text" placeholder="_cf_change_sig" />
								<button type="button" class="cfi-copy-btn" data-copy-from="#target_sig_meta" aria-label="<?php esc_attr_e( 'Copy key', 'cfi-images-sync' ); ?>" title="<?php esc_attr_e( 'Copy key', 'cfi-images-sync' ); ?>" disabled>
									<?php echo $copy_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
								</button>
							</div>
							<p class="description">
								<?php esc_html_e( 'Optional. Stores a hash to detect image changes and avoid redundant uploads.', 'cfi-images-sync' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th></th>
						<td>
							<button type="button" id="cfi-copy-all-targets" class="button cfi-copy-btn-all" aria-label="<?php esc_attr_e( 'Copy all target keys as JSON', 'cfi-images-sync' ); ?>" title="<?php esc_attr_e( 'Copy all target keys as JSON', 'cfi-images-sync' ); ?>" disabled>
								<?php echo $copy_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
								<?php esc_html_e( 'Copy All Target Keys', 'cfi-images-sync' ); ?>
							</button>
						</td>
					</tr>
					<tr>
						<th>
							<label for="preset_id">
								<?php esc_html_e( 'Preset', 'cfi-images-sync' ); ?>
							</label>
						</th>
						<td>
							<select id="preset_id" name="preset_id">
								<option value="">
									<?php esc_html_e( '— Default (public) —', 'cfi-images-sync' ); ?>
								</option>
								<?php foreach ( $presets as $preset ) : ?>
									<option value="<?php echo esc_attr( $preset['id'] ); ?>" <?php selected( $m['preset_id'] ?? '', $preset['id'] ); ?>>
										<?php echo esc_html( $preset['name'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description">
								<?php
								$presets_link = '<a href="' . esc_url( admin_url( 'admin.php?page=cfi-presets' ) ) . '">' . esc_html__( 'CF Images → Presets', 'cfi-images-sync' ) . '</a>';
								echo wp_kses(
									sprintf(
										/* translators: %s: link to Presets admin page */
										__( 'Image variant preset for the delivery URL. Manage presets under %s.', 'cfi-images-sync' ),
										$presets_link
									),
									array( 'a' => array( 'href' => array() ) )
								);
								?>
							</p>
						</td>
					</tr>
				</table>
			</div>

			<?php // ── Section 3: Sync Settings ──────────────────────────────── ?>
			<div class="cfi-form-section">
				<h3><?php esc_html_e( 'Sync Settings', 'cfi-images-sync' ); ?></h3>
				<p class="cfi-section-desc">
					<?php esc_html_e( 'Configure when and how the sync runs.', 'cfi-images-sync' ); ?>
				</p>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Auto-Sync Triggers', 'cfi-images-sync' ); ?></th>
						<td>
							<fieldset>
								<label>
									<input type="checkbox" name="trigger_save_post" value="1" <?php checked( $m['triggers']['save_post'] ?? true ); ?> />
									<?php esc_html_e( 'When a post is saved', 'cfi-images-sync' ); ?>
								</label>
								<br/>
								<label>
									<input type="checkbox" name="trigger_acf_save_post" value="1" <?php checked( $m['triggers']['acf_save_post'] ?? true ); ?> <?php disabled( ! $has_acf ); ?> />
									<?php esc_html_e( 'When ACF fields are updated', 'cfi-images-sync' ); ?>
								</label>
								<?php if ( ! $has_acf ) : ?>
									<p class="description">
										<?php esc_html_e( 'ACF is not active on this site. Install Advanced Custom Fields to enable this trigger.', 'cfi-images-sync' ); ?>
									</p>
								<?php endif; ?>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Upload Behavior', 'cfi-images-sync' ); ?></th>
						<td>
							<fieldset>
								<label>
									<input type="checkbox" name="upload_if_missing" value="1" <?php checked( $m['behavior']['upload_if_missing'] ?? true ); ?> />
									<?php esc_html_e( 'Upload image if not yet on Cloudflare', 'cfi-images-sync' ); ?>
								</label>
								<br/>
								<label>
									<input type="checkbox" name="reupload_if_changed" value="1" <?php checked( $m['behavior']['reupload_if_changed'] ?? true ); ?> />
									<?php esc_html_e( 'Re-upload if the local file has changed', 'cfi-images-sync' ); ?>
								</label>
								<br/>
								<label>
									<input type="checkbox" name="clear_on_empty" value="1" <?php checked( $m['behavior']['clear_on_empty'] ?? true ); ?> />
									<?php esc_html_e( 'Clear delivery URL if the source image is removed', 'cfi-images-sync' ); ?>
								</label>
								<br/>
								<label>
									<input type="checkbox" name="store_cf_id_on_post" value="1" <?php checked( $m['behavior']['store_cf_id_on_post'] ?? true ); ?> />
									<?php esc_html_e( 'Store Cloudflare image ID on the post', 'cfi-images-sync' ); ?>
								</label>
								<br/>
								<label>
									<input type="checkbox" name="delete_cf_on_reupload" value="1" <?php checked( $m['behavior']['delete_cf_on_reupload'] ?? false ); ?> />
									<?php esc_html_e( 'Delete old Cloudflare image when re-uploading', 'cfi-images-sync' ); ?>
								</label>
								<p class="description">
									<?php esc_html_e( 'Disabled by default. Enable only if you are sure no other mapping or post references the same Cloudflare image.', 'cfi-images-sync' ); ?>
								</p>
							</fieldset>
						</td>
					</tr>
				</table>
			</div>

			<?php // ── Section 4: Test Mapping ─────────────────────────────────── ?>
			<div class="cfi-form-section">
				<h3><?php esc_html_e( 'Test Mapping', 'cfi-images-sync' ); ?></h3>
				<p class="cfi-section-desc">
					<?php esc_html_e( 'Dry-run this mapping against a single post to verify the source resolves correctly and preview the delivery URL. No upload or sync is performed.', 'cfi-images-sync' ); ?>
				</p>
				<table class="form-table">
					<tr>
						<th>
							<label for="cfi_test_post_id">
								<?php esc_html_e( 'Post ID', 'cfi-images-sync' ); ?>
							</label>
						</th>
						<td>
							<input type="number" id="cfi_test_post_id" class="small-text" min="1" placeholder="123" />
							<button type="button" id="cfi-test-btn" class="button">
								<?php esc_html_e( 'Test', 'cfi-images-sync' ); ?>
							</button>
							<span id="cfi-test-spinner" class="spinner"></span>
						</td>
					</tr>
				</table>
				<div id="cfi-test-results" class="cfi-test-results" style="display:none;"></div>
			</div>

			<p class="submit">
				<input type="submit" name="cfi_save_mapping" class="button-primary" value="<?php esc_attr_e( 'Save Mapping', 'cfi-images-sync' ); ?>" />
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=cfi-mappings' ) ); ?>" class="button">
					<?php esc_html_e( 'Cancel', 'cfi-images-sync' ); ?>
				</a>
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
			echo '<p>' . esc_html__( 'No mappings yet. Click "Add New" to create one.', 'cfi-images-sync' ) . '</p>';
			return;
		}

		$source_labels = $this->source_type_labels();

		foreach ( $mappings as $map ) :
			$preset_name = __( 'Default (public)', 'cfi-images-sync' );
			if ( ! empty( $map['preset_id'] ) ) {
				$p           = $this->presets->find( $map['preset_id'] );
				$preset_name = $p ? $p['name'] : __( '(deleted)', 'cfi-images-sync' );
			}
			$source_type_label = $source_labels[ $map['source']['type'] ?? '' ] ?? ( $map['source']['type'] ?? '' );
			$pt_obj            = get_post_type_object( $map['post_type'] );
			$pt_label          = $pt_obj ? $pt_obj->labels->singular_name : $map['post_type'];
			?>
			<div class="cfi-mapping-card">
				<div class="cfi-mapping-card__header">
					<strong><?php echo esc_html( $pt_label ); ?></strong>
					<span class="cfi-mapping-card__id"><?php echo esc_html( $map['id'] ); ?></span>
				</div>
				<div class="cfi-mapping-card__body">
					<div class="cfi-mapping-card__field">
						<span class="cfi-mapping-card__label"><?php esc_html_e( 'Source', 'cfi-images-sync' ); ?></span>
						<?php echo esc_html( $source_type_label ); ?>
						<?php if ( ! empty( $map['source']['key'] ) ) : ?>
							<code><?php echo esc_html( $map['source']['key'] ); ?></code>
						<?php endif; ?>
					</div>
					<span class="cfi-mapping-card__arrow">&rarr;</span>
					<div class="cfi-mapping-card__field">
						<span class="cfi-mapping-card__label"><?php esc_html_e( 'Target', 'cfi-images-sync' ); ?></span>
						<code><?php echo esc_html( $map['target']['url_meta'] ?? '' ); ?></code>
					</div>
					<div class="cfi-mapping-card__field">
						<span class="cfi-mapping-card__label"><?php esc_html_e( 'Preset', 'cfi-images-sync' ); ?></span>
						<?php if ( ! empty( $map['preset_id'] ) && $p ) : ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=cfi-presets&action=edit&preset_id=' . $map['preset_id'] ) ); ?>"><?php echo esc_html( $preset_name ); ?></a>
						<?php else : ?>
							<?php echo esc_html( $preset_name ); ?>
						<?php endif; ?>
					</div>
				</div>
				<div class="cfi-mapping-card__actions">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=cfi-mappings&action=edit&mapping_id=' . $map['id'] ) ); ?>" class="button button-small">
						<?php esc_html_e( 'Edit', 'cfi-images-sync' ); ?>
					</a>
					<form method="post" class="cfi-inline-form">
						<?php wp_nonce_field( 'cfi_bulk_sync' ); ?>
						<input type="hidden" name="bulk_mapping_id" value="<?php echo esc_attr( $map['id'] ); ?>" />
						<button type="submit" name="cfi_bulk_sync" class="button button-small" onclick="return confirm('<?php esc_attr_e( 'Enqueue bulk sync for this mapping?', 'cfi-images-sync' ); ?>');">
							<?php esc_html_e( 'Bulk Sync', 'cfi-images-sync' ); ?>
						</button>
					</form>
					<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=cfi-mappings&action=delete&mapping_id=' . $map['id'] ), 'cfi_delete_mapping_' . $map['id'] ) ); ?>" class="cfi-mapping-card__delete" onclick="return confirm('<?php esc_attr_e( 'Delete this mapping?', 'cfi-images-sync' ); ?>');">
						<?php esc_html_e( 'Delete', 'cfi-images-sync' ); ?>
					</a>
				</div>
			</div>
		<?php endforeach; ?>
		<?php
	}
}
