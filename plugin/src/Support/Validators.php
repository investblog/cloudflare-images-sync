<?php
/**
 * Validation helpers.
 *
 * @package CloudflareImagesSync
 */

namespace CFI\Support;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CFI\Repos\Defaults;

/**
 * Schema validators for plugin data.
 */
final class Validators {

	/**
	 * Normalize and validate settings, merging with defaults.
	 *
	 * @param mixed $raw Raw settings value (possibly corrupted).
	 * @return array<string, mixed> Normalized settings.
	 */
	public static function normalize_settings( $raw ): array {
		$defaults = Defaults::settings();

		if ( ! is_array( $raw ) ) {
			return $defaults;
		}

		$result = array_merge( $defaults, array_intersect_key( $raw, $defaults ) );

		// Type coercion.
		$result['account_id']   = (string) $result['account_id'];
		$result['account_hash'] = (string) $result['account_hash'];
		$result['api_token']    = (string) $result['api_token'];
		$result['debug']        = (bool) $result['debug'];
		$result['use_queue']    = (bool) $result['use_queue'];
		$result['logs_max']     = self::clamp_int( (int) $result['logs_max'], 50, 1000 );

		return $result;
	}

	/**
	 * Validate a preset record.
	 *
	 * @param array<string, mixed> $data Preset data.
	 * @return true|\WP_Error
	 */
	public static function validate_preset( array $data ) {
		if ( empty( $data['name'] ) || ! is_string( $data['name'] ) ) {
			return new \WP_Error( 'cfi_invalid_preset', 'Preset name is required.' );
		}

		if ( strlen( $data['name'] ) > 100 ) {
			return new \WP_Error( 'cfi_invalid_preset', 'Preset name must be 100 characters or less.' );
		}

		if ( empty( $data['variant'] ) || ! is_string( $data['variant'] ) ) {
			return new \WP_Error( 'cfi_invalid_preset', 'Preset variant string is required.' );
		}

		if ( strlen( $data['variant'] ) > 255 ) {
			return new \WP_Error( 'cfi_invalid_preset', 'Preset variant must be 255 characters or less.' );
		}

		if ( ! preg_match( '/^[A-Za-z0-9=,_\\-.]+$/', $data['variant'] ) ) {
			return new \WP_Error( 'cfi_invalid_preset', 'Preset variant contains invalid characters.' );
		}

		return true;
	}

	/**
	 * Validate a mapping record.
	 *
	 * @param array<string, mixed> $data Mapping data.
	 * @return true|\WP_Error
	 */
	public static function validate_mapping( array $data ) {
		if ( empty( $data['post_type'] ) || ! is_string( $data['post_type'] ) ) {
			return new \WP_Error( 'cfi_invalid_mapping', 'Post type is required.' );
		}

		if ( ! post_type_exists( $data['post_type'] ) ) {
			return new \WP_Error( 'cfi_invalid_mapping', 'Post type does not exist.' );
		}

		// Validate source.
		if ( empty( $data['source']['type'] ) || ! in_array( $data['source']['type'], Defaults::source_types(), true ) ) {
			return new \WP_Error( 'cfi_invalid_mapping', 'Invalid source type.' );
		}

		if ( $data['source']['type'] !== 'featured_image' && $data['source']['type'] !== 'attachment_id' ) {
			if ( empty( $data['source']['key'] ) ) {
				return new \WP_Error( 'cfi_invalid_mapping', 'Source key is required for this source type.' );
			}
		}

		if ( in_array( $data['source']['type'], array( 'post_meta_attachment_id', 'post_meta_url', 'acf_field' ), true ) ) {
			if ( ! self::is_valid_key( (string) $data['source']['key'] ) ) {
				return new \WP_Error( 'cfi_invalid_mapping', 'Source key contains invalid characters.' );
			}
		}

		// Validate target — at least url_meta is required.
		if ( empty( $data['target']['url_meta'] ) ) {
			return new \WP_Error( 'cfi_invalid_mapping', 'Target url_meta is required.' );
		}

		if ( ! self::is_valid_key( (string) $data['target']['url_meta'] ) ) {
			return new \WP_Error( 'cfi_invalid_mapping', 'Target url_meta contains invalid characters.' );
		}

		if ( ! empty( $data['target']['id_meta'] ) && ! self::is_valid_key( (string) $data['target']['id_meta'] ) ) {
			return new \WP_Error( 'cfi_invalid_mapping', 'Target id_meta contains invalid characters.' );
		}

		if ( ! empty( $data['target']['sig_meta'] ) && ! self::is_valid_key( (string) $data['target']['sig_meta'] ) ) {
			return new \WP_Error( 'cfi_invalid_mapping', 'Target sig_meta contains invalid characters.' );
		}

		return true;
	}

	/**
	 * Normalize a mapping record, merging with defaults.
	 *
	 * @param mixed $raw Raw mapping data.
	 * @return array<string, mixed>
	 */
	public static function normalize_mapping( $raw ): array {
		$defaults = Defaults::mapping();

		if ( ! is_array( $raw ) ) {
			return $defaults;
		}

		$result              = array_merge( $defaults, array_intersect_key( $raw, $defaults ) );
		$result['post_type'] = (string) $result['post_type'];
		$result['status']    = in_array( $result['status'], array( 'any', 'publish' ), true ) ? $result['status'] : 'any';

		// Normalize nested arrays.
		$result['triggers'] = array_merge( $defaults['triggers'], is_array( $result['triggers'] ) ? $result['triggers'] : array() );
		$result['source']   = array_merge( $defaults['source'], is_array( $result['source'] ) ? $result['source'] : array() );
		$result['target']   = array_merge( $defaults['target'], is_array( $result['target'] ) ? $result['target'] : array() );
		$result['behavior'] = array_merge( $defaults['behavior'], is_array( $result['behavior'] ) ? $result['behavior'] : array() );

		// Bool coercion for triggers.
		$result['triggers']['save_post']     = (bool) $result['triggers']['save_post'];
		$result['triggers']['acf_save_post'] = (bool) $result['triggers']['acf_save_post'];

		// Bool coercion for behavior.
		foreach ( $result['behavior'] as $key => $val ) {
			$result['behavior'][ $key ] = (bool) $val;
		}

		return $result;
	}

	/**
	 * Sanitize a preset name.
	 *
	 * @param string $name Preset name.
	 * @return string
	 */
	public static function sanitize_preset_name( string $name ): string {
		return sanitize_text_field( $name );
	}

	/**
	 * Sanitize a variant string.
	 *
	 * @param string $variant Variant string.
	 * @return string
	 */
	public static function sanitize_variant( string $variant ): string {
		return sanitize_text_field( $variant );
	}

	/**
	 * Validate a user-supplied key (meta key, ACF field name).
	 *
	 * @param string $key Key value.
	 * @return bool
	 */
	public static function is_valid_key( string $key ): bool {
		$key = trim( $key );

		if ( $key === '' ) {
			return false;
		}

		if ( strlen( $key ) > 191 ) {
			return false;
		}

		return (bool) preg_match( '/^[A-Za-z0-9_\\-:.]+$/', $key );
	}

	/**
	 * Validate an internal ID string.
	 *
	 * @param string $id     ID value.
	 * @param string $prefix Expected prefix (e.g. "preset", "map").
	 * @return bool
	 */
	public static function is_valid_id( string $id, string $prefix ): bool {
		$pattern = '/^' . preg_quote( $prefix, '/' ) . '_[a-f0-9]{8}$/';
		return (bool) preg_match( $pattern, $id );
	}

	/**
	 * Normalize logs structure.
	 *
	 * @param mixed $raw Raw logs value.
	 * @param int   $max Maximum items.
	 * @return array<string, mixed>
	 */
	public static function normalize_logs( $raw, int $max = 200 ): array {
		$defaults = Defaults::logs();

		if ( ! is_array( $raw ) ) {
			return $defaults;
		}

		$result        = array_merge( $defaults, $raw );
		$result['max'] = self::clamp_int( (int) $result['max'], 50, 1000 );

		if ( ! is_array( $result['items'] ) ) {
			$result['items'] = array();
		}

		return $result;
	}

	/**
	 * Clamp an integer to a range.
	 *
	 * @param int $value Value.
	 * @param int $min   Min.
	 * @param int $max   Max.
	 * @return int
	 */
	public static function clamp_int( int $value, int $min, int $max ): int {
		return max( $min, min( $max, $value ) );
	}
}
