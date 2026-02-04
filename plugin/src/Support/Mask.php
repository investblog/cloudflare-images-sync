<?php
/**
 * Token masking helper.
 *
 * @package CloudflareImagesSync
 */

namespace CFI\Support;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mask sensitive values for display.
 */
final class Mask {

	/**
	 * Mask an API token, showing only the last 4 characters.
	 *
	 * @param string $token The token to mask.
	 * @return string Masked token or empty string.
	 */
	public static function token( string $token ): string {
		if ( strlen( $token ) <= 4 ) {
			return $token === '' ? '' : '****';
		}

		return str_repeat( '*', strlen( $token ) - 4 ) . substr( $token, -4 );
	}
}
