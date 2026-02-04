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
use CFI\Support\Validators;

/**
 * Read/write access to plugin settings (cfi_settings option).
 */
class SettingsRepo {

	/**
	 * Get all settings, normalized with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function get(): array {
		$raw = get_option( OptionKeys::SETTINGS, array() );
		return Validators::normalize_settings( $raw );
	}

	/**
	 * Update settings by merging a partial patch.
	 *
	 * @param array<string, mixed> $patch Partial settings to merge.
	 * @return true|\WP_Error
	 */
	public function update( array $patch ) {
		$current = $this->get();

		// Only allow known keys.
		$allowed = array_keys( Defaults::settings() );
		$patch   = array_intersect_key( $patch, array_flip( $allowed ) );

		$merged = array_merge( $current, $patch );
		$merged = Validators::normalize_settings( $merged );

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
