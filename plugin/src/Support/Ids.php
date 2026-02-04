<?php
/**
 * ID generation helpers.
 *
 * @package CloudflareImagesSync
 */

namespace CFI\Support;

/**
 * Generate prefixed unique IDs.
 */
final class Ids {

	/**
	 * Generate a preset ID.
	 *
	 * @return string
	 */
	public static function preset(): string {
		return 'preset_' . self::short_uuid();
	}

	/**
	 * Generate a mapping ID.
	 *
	 * @return string
	 */
	public static function mapping(): string {
		return 'map_' . self::short_uuid();
	}

	/**
	 * Generate a short UUID (8 hex chars).
	 *
	 * @return string
	 */
	private static function short_uuid(): string {
		return bin2hex( random_bytes( 4 ) );
	}
}
