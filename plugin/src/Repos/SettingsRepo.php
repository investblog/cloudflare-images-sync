<?php
/**
 * Settings repository.
 *
 * @package CloudflareImagesSync
 */

namespace CFI\Repos;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CFI\Support\Mask;
use CFI\Support\TokenStorage;
use CFI\Support\Validators;

/**
 * Read/write access to plugin settings (cfi_settings option).
 *
 * API token is stored separately using encrypted TokenStorage.
 */
class SettingsRepo {

	/**
	 * Token storage instance.
	 *
	 * @var TokenStorage
	 */
	private TokenStorage $token_storage;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->token_storage = new TokenStorage();
	}

	/**
	 * Get all settings, normalized with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function get(): array {
		$raw      = get_option( OptionKeys::SETTINGS, array() );
		$settings = Validators::normalize_settings( $raw );

		// Merge in the decrypted token.
		$settings['api_token'] = $this->token_storage->retrieve();

		return $settings;
	}

	/**
	 * Update settings by merging a partial patch.
	 *
	 * @param array<string, mixed> $patch Partial settings to merge.
	 * @return true|\WP_Error
	 */
	public function update( array $patch ) {
		$current = $this->get();

		// Handle token separately (encrypted storage).
		if ( isset( $patch['api_token'] ) ) {
			$this->token_storage->store( $patch['api_token'] );
			unset( $patch['api_token'] );
		}

		// Only allow known keys (excluding api_token which is stored separately).
		$allowed = array_keys( Defaults::settings() );
		$allowed = array_diff( $allowed, array( 'api_token' ) );
		$patch   = array_intersect_key( $patch, array_flip( $allowed ) );

		// Remove api_token from current before merge (it's stored separately).
		unset( $current['api_token'] );

		$merged = array_merge( $current, $patch );
		$merged = Validators::normalize_settings( $merged );

		// Don't store api_token in main settings.
		unset( $merged['api_token'] );

		update_option( OptionKeys::SETTINGS, $merged, false );

		return true;
	}

	/**
	 * Reset settings to defaults.
	 *
	 * @return void
	 */
	public function reset(): void {
		delete_option( OptionKeys::SETTINGS );
		$this->token_storage->delete();
	}

	/**
	 * Get settings with token masked (safe for display).
	 *
	 * @return array<string, mixed>
	 */
	public function get_masked(): array {
		$settings              = $this->get();
		$settings['api_token'] = Mask::token( $settings['api_token'] );
		return $settings;
	}
}
