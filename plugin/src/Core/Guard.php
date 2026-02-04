<?php
/**
 * Dedupe guard for save hooks.
 *
 * @package CloudflareImagesSync
 */

namespace CFI\Core;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prevents double-sync when both save_post and acf/save_post fire
 * for the same post in a single request.
 */
class Guard {

	/**
	 * Post IDs already processed in this request.
	 *
	 * Key: "{post_id}:{mapping_id}", value: true.
	 *
	 * @var array<string, true>
	 */
	private static array $processed = array();

	/**
	 * Whether we're inside our own meta update (prevent recursion).
	 *
	 * @var bool
	 */
	private static bool $updating = false;

	/**
	 * Check if this post+mapping combo can proceed.
	 * Marks it as processed if allowed.
	 *
	 * @param int    $post_id    Post ID.
	 * @param string $mapping_id Mapping ID.
	 * @return bool True if allowed to proceed, false if already processed.
	 */
	public static function acquire( int $post_id, string $mapping_id ): bool {
		$key = $post_id . ':' . $mapping_id;

		if ( isset( self::$processed[ $key ] ) ) {
			return false;
		}

		self::$processed[ $key ] = true;
		return true;
	}

	/**
	 * Mark that we're inside our own update (to avoid recursion on save_post).
	 *
	 * @return void
	 */
	public static function lock(): void {
		self::$updating = true;
	}

	/**
	 * Release the recursion lock.
	 *
	 * @return void
	 */
	public static function unlock(): void {
		self::$updating = false;
	}

	/**
	 * Check if we're currently inside our own update.
	 *
	 * @return bool
	 */
	public static function is_locked(): bool {
		return self::$updating;
	}

	/**
	 * Reset all state (useful for testing / WP-CLI batch).
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$processed = array();
		self::$updating  = false;
	}
}
