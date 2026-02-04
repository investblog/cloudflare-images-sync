<?php
/**
 * File signature calculator.
 *
 * @package CloudflareImagesSync
 */

namespace CFI\Core;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compute a file signature to detect changes.
 *
 * Uses md5 of file contents — fast and sufficient for change detection
 * (not security). Falls back to size+mtime if md5 fails.
 */
class Signature {

	/**
	 * Compute signature for a file path.
	 *
	 * @param string $file_path Absolute path to the file.
	 * @return string|\WP_Error Signature string or error.
	 */
	public static function compute( string $file_path ) {
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return new \WP_Error( 'cfi_sig_file_missing', 'File not found or not readable for signature.' );
		}

		$md5 = md5_file( $file_path ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_md5

		if ( $md5 !== false ) {
			return $md5;
		}

		// Fallback: size + mtime.
		$size  = filesize( $file_path );
		$mtime = filemtime( $file_path );

		if ( $size === false || $mtime === false ) {
			return new \WP_Error( 'cfi_sig_stat_failed', 'Could not stat file for signature.' );
		}

		return $size . ':' . $mtime;
	}

	/**
	 * Check if the signature has changed compared to a stored value.
	 *
	 * @param string $file_path  Absolute path to the file.
	 * @param string $stored_sig Previously stored signature (empty = always changed).
	 * @return bool True if changed (or no stored sig), false if identical.
	 */
	public static function has_changed( string $file_path, string $stored_sig ): bool {
		if ( $stored_sig === '' ) {
			return true;
		}

		$current = self::compute( $file_path );

		if ( is_wp_error( $current ) ) {
			return true;
		}

		return $current !== $stored_sig;
	}
}
