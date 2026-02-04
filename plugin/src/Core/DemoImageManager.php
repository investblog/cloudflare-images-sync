<?php
/**
 * Demo image upload and cache manager.
 *
 * @package CloudflareImagesSync
 */

namespace CFI\Core;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CFI\Api\CloudflareImagesClient;
use CFI\Repos\OptionKeys;

/**
 * Manages the bundled demo.jpg upload to Cloudflare Images.
 *
 * Stores result in WP options (not post meta, not attachment).
 */
class DemoImageManager {

	/**
	 * Relative path from plugin directory to the demo image.
	 */
	private const DEMO_PATH = 'assets/img/demo.jpg';

	/**
	 * Get the absolute path to the demo image.
	 *
	 * @return string
	 */
	public function get_file_path(): string {
		return CFI_PLUGIN_DIR . self::DEMO_PATH;
	}

	/**
	 * Get the cached Cloudflare image ID, or empty string if not uploaded.
	 *
	 * @return string
	 */
	public function get_cf_image_id(): string {
		return (string) get_option( OptionKeys::DEMO_IMAGE_ID, '' );
	}

	/**
	 * Check if the demo image needs (re-)uploading.
	 *
	 * @return bool True if upload is needed.
	 */
	public function needs_upload(): bool {
		$file_path = $this->get_file_path();

		if ( ! file_exists( $file_path ) ) {
			return false;
		}

		$cf_id      = $this->get_cf_image_id();
		$stored_sig = (string) get_option( OptionKeys::DEMO_SIG, '' );

		if ( $cf_id === '' ) {
			return true;
		}

		return Signature::has_changed( $file_path, $stored_sig );
	}

	/**
	 * Upload the demo image to Cloudflare (or return cached ID).
	 *
	 * @return string|\WP_Error Cloudflare image ID on success.
	 */
	public function ensure_uploaded() {
		$file_path = $this->get_file_path();

		if ( ! file_exists( $file_path ) ) {
			return new \WP_Error( 'cfi_demo_not_found', 'Demo image file not found.' );
		}

		// Return cached if unchanged.
		if ( ! $this->needs_upload() ) {
			return $this->get_cf_image_id();
		}

		$client = CloudflareImagesClient::from_settings();
		if ( is_wp_error( $client ) ) {
			return $client;
		}

		// Delete old image if re-uploading.
		$old_cf_id = $this->get_cf_image_id();
		if ( $old_cf_id !== '' ) {
			$client->delete( $old_cf_id );
		}

		$result = $client->upload(
			$file_path,
			array(
				'purpose' => 'demo_preview',
				'source'  => 'plugin_bundled',
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$new_cf_id = $result['id'] ?? '';
		$new_sig   = Signature::compute( $file_path );

		if ( is_wp_error( $new_sig ) ) {
			$new_sig = '';
		}

		update_option( OptionKeys::DEMO_IMAGE_ID, $new_cf_id, false );
		update_option( OptionKeys::DEMO_SIG, $new_sig, false );
		update_option( OptionKeys::DEMO_UPDATED, time(), false );

		return $new_cf_id;
	}
}
