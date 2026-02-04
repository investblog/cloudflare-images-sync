<?php
/**
 * Logs repository (ring buffer).
 *
 * @package CloudflareImagesSync
 */

namespace CFI\Repos;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CFI\Support\Validators;

/**
 * Append-only ring buffer for sync log entries.
 */
class LogsRepo {

	/**
	 * Get all log entries.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function all(): array {
		$data = $this->load();
		return $data['items'];
	}

	/**
	 * Push a new log entry.
	 *
	 * @param string               $level   Log level (info, warning, error, debug).
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Optional context (post_id, mapping_id, extra).
	 * @return void
	 */
	public function push( string $level, string $message, array $context = array() ): void {
		$allowed_levels = Defaults::log_levels();

		if ( ! in_array( $level, $allowed_levels, true ) ) {
			$level = 'info';
		}

		// Whitelist context keys.
		$allowed_ctx = Defaults::log_context_keys();
		$context     = array_intersect_key( $context, array_flip( $allowed_ctx ) );

		$entry = array_merge(
			array(
				't'   => time(),
				'lvl' => $level,
				'msg' => sanitize_text_field( $message ),
			),
			$context,
		);

		$data            = $this->load();
		$data['items'][] = $entry;

		// Ring buffer: trim from the front.
		$max = $data['max'];
		if ( count( $data['items'] ) > $max ) {
			$data['items'] = array_slice( $data['items'], -$max );
		}

		$this->save( $data );
	}

	/**
	 * Clear all log entries.
	 *
	 * @return void
	 */
	public function clear(): void {
		$data          = $this->load();
		$data['items'] = array();
		$this->save( $data );
	}

	/**
	 * Get the current entry count.
	 *
	 * @return int
	 */
	public function count(): int {
		return count( $this->all() );
	}

	/**
	 * Load and normalize the logs option.
	 *
	 * @return array<string, mixed>
	 */
	private function load(): array {
		$raw = get_option( OptionKeys::LOGS, array() );

		// Get max from settings.
		$settings_repo = new SettingsRepo();
		$settings      = $settings_repo->get();

		return Validators::normalize_logs( $raw, $settings['logs_max'] );
	}

	/**
	 * Persist logs to the database.
	 *
	 * @param array<string, mixed> $data Logs data.
	 * @return void
	 */
	private function save( array $data ): void {
		update_option( OptionKeys::LOGS, $data, false );
	}
}
