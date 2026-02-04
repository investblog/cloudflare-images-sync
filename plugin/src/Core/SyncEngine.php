<?php
/**
 * Sync engine — orchestrates single-post sync by mapping.
 *
 * @package CloudflareImagesSync
 */

namespace CFI\Core;

use CFI\Api\CloudflareImagesClient;
use CFI\Repos\LogsRepo;
use CFI\Repos\PresetsRepo;
use CFI\Repos\SettingsRepo;

/**
 * Execute a sync operation for one post + one mapping.
 *
 * "Post-first" model: results (URL, CF image ID, signature) are stored
 * as post meta on the target post.
 */
class SyncEngine {

	/**
	 * Sync a single post according to a mapping rule.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $mapping Mapping record from MappingsRepo.
	 * @return true|\WP_Error True on success (including no-op), WP_Error on failure.
	 */
	public function sync( int $post_id, array $mapping ) {
		$logs = new LogsRepo();
		$ctx  = array(
			'post_id'    => $post_id,
			'mapping_id' => $mapping['id'] ?? '',
		);

		// 1. Resolve source.
		$source   = $mapping['source'] ?? array();
		$resolved = SourceResolver::resolve( $post_id, $source );

		// 2. Handle empty source.
		if ( $resolved->is_empty() ) {
			return $this->handle_empty_source( $post_id, $mapping, $logs, $ctx );
		}

		// 3. Check signature — skip upload if unchanged.
		$behavior  = $mapping['behavior'] ?? array();
		$target    = $mapping['target'] ?? array();
		$sig_meta  = $target['sig_meta'] ?? '';
		$file_path = $resolved->get_file_path();

		$stored_sig  = $sig_meta !== '' ? (string) get_post_meta( $post_id, $sig_meta, true ) : '';
		$stored_cfid = ( $target['id_meta'] ?? '' ) !== '' ? (string) get_post_meta( $post_id, $target['id_meta'], true ) : '';

		$needs_upload = false;

		if ( $stored_cfid === '' && ! empty( $behavior['upload_if_missing'] ) ) {
			$needs_upload = true;
		} elseif ( $stored_cfid !== '' && ! empty( $behavior['reupload_if_changed'] ) ) {
			if ( Signature::has_changed( $file_path, $stored_sig ) ) {
				$needs_upload = true;
			}
		}

		if ( ! $needs_upload ) {
			// URL might still need regenerating if preset changed but image didn't.
			$this->maybe_update_url( $post_id, $stored_cfid, $mapping );
			return true;
		}

		// 4. Upload.
		$client = CloudflareImagesClient::from_settings();
		if ( is_wp_error( $client ) ) {
			$logs->push( 'error', 'Cloudflare client not configured.', $ctx );
			return $client;
		}

		$metadata = array(
			'wp_post_id'       => $post_id,
			'wp_attachment_id' => $resolved->get_attachment_id(),
			'mapping_id'       => $mapping['id'] ?? '',
		);

		// If re-uploading, delete old image first.
		if ( $stored_cfid !== '' ) {
			$client->delete( $stored_cfid );
		}

		$upload = $client->upload( $file_path, $metadata );

		if ( is_wp_error( $upload ) ) {
			$logs->push( 'error', 'Upload failed: ' . $upload->get_error_message(), $ctx );
			return $upload;
		}

		$cf_image_id = $upload['id'] ?? '';

		if ( $cf_image_id === '' ) {
			$logs->push( 'error', 'Upload succeeded but no image ID returned.', $ctx );
			return new \WP_Error( 'cfi_no_image_id', 'Cloudflare returned no image ID.' );
		}

		// 5. Compute and store signature.
		$new_sig = Signature::compute( $file_path );
		if ( is_wp_error( $new_sig ) ) {
			$new_sig = '';
		}

		// 6. Build delivery URL.
		$settings = ( new SettingsRepo() )->get();
		$builder  = new UrlBuilder( $settings['account_hash'] );
		$variant  = $this->resolve_variant( $mapping );
		$url      = $builder->url( $cf_image_id, $variant );

		if ( is_wp_error( $url ) ) {
			$logs->push( 'warning', 'Could not build delivery URL: ' . $url->get_error_message(), $ctx );
			$url = '';
		}

		// 7. Store results on post.
		$this->store_meta( $post_id, $target, $cf_image_id, $url, $new_sig );

		$logs->push( 'info', 'Synced successfully.', $ctx );

		return true;
	}

	/**
	 * Handle the case where source field is empty.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $mapping Mapping record.
	 * @param LogsRepo             $logs    Logs repo.
	 * @param array<string, mixed> $ctx     Log context.
	 * @return true
	 */
	private function handle_empty_source( int $post_id, array $mapping, LogsRepo $logs, array $ctx ): bool {
		$behavior = $mapping['behavior'] ?? array();
		$target   = $mapping['target'] ?? array();

		if ( ! empty( $behavior['clear_on_empty'] ) ) {
			$this->clear_meta( $post_id, $target );
			$logs->push( 'info', 'Source empty, cleared target meta.', $ctx );
		}

		return true;
	}

	/**
	 * Update the delivery URL if CF image ID exists but URL might be stale.
	 *
	 * @param int                  $post_id  Post ID.
	 * @param string               $cf_id    Cloudflare image ID.
	 * @param array<string, mixed> $mapping  Mapping record.
	 */
	private function maybe_update_url( int $post_id, string $cf_id, array $mapping ): void {
		if ( $cf_id === '' ) {
			return;
		}

		$target   = $mapping['target'] ?? array();
		$url_meta = $target['url_meta'] ?? '';

		if ( $url_meta === '' ) {
			return;
		}

		$settings = ( new SettingsRepo() )->get();
		$builder  = new UrlBuilder( $settings['account_hash'] );
		$variant  = $this->resolve_variant( $mapping );
		$url      = $builder->url( $cf_id, $variant );

		if ( is_wp_error( $url ) ) {
			return;
		}

		update_post_meta( $post_id, $url_meta, $url );
	}

	/**
	 * Resolve the variant string from mapping preset.
	 *
	 * @param array<string, mixed> $mapping Mapping record.
	 * @return string Variant string, defaults to 'public'.
	 */
	private function resolve_variant( array $mapping ): string {
		$preset_id = $mapping['preset_id'] ?? '';

		if ( $preset_id === '' ) {
			return 'public';
		}

		$presets_repo = new PresetsRepo();
		$preset       = $presets_repo->find( $preset_id );

		if ( $preset === null || empty( $preset['variant'] ) ) {
			return 'public';
		}

		return $preset['variant'];
	}

	/**
	 * Store sync results in post meta.
	 *
	 * @param int                  $post_id    Post ID.
	 * @param array<string, mixed> $target     Target config from mapping.
	 * @param string               $cf_id      Cloudflare image ID.
	 * @param string               $url        Delivery URL.
	 * @param string               $signature  File signature.
	 */
	private function store_meta( int $post_id, array $target, string $cf_id, string $url, string $signature ): void {
		if ( ! empty( $target['url_meta'] ) ) {
			update_post_meta( $post_id, $target['url_meta'], $url );
		}

		if ( ! empty( $target['id_meta'] ) ) {
			update_post_meta( $post_id, $target['id_meta'], $cf_id );
		}

		if ( ! empty( $target['sig_meta'] ) ) {
			update_post_meta( $post_id, $target['sig_meta'], $signature );
		}
	}

	/**
	 * Clear all target meta keys on a post.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $target  Target config from mapping.
	 */
	private function clear_meta( int $post_id, array $target ): void {
		if ( ! empty( $target['url_meta'] ) ) {
			delete_post_meta( $post_id, $target['url_meta'] );
		}

		if ( ! empty( $target['id_meta'] ) ) {
			delete_post_meta( $post_id, $target['id_meta'] );
		}

		if ( ! empty( $target['sig_meta'] ) ) {
			delete_post_meta( $post_id, $target['sig_meta'] );
		}
	}
}
