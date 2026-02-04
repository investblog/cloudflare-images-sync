<?php
/**
 * Bulk sync enqueuer — chunked via Action Scheduler.
 *
 * @package CloudflareImagesSync
 */

namespace CFI\Jobs;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CFI\Core\Guard;
use CFI\Core\SyncEngine;
use CFI\Repos\LogsRepo;
use CFI\Repos\MappingsRepo;
use CFI\Support\Validators;

/**
 * Process a chunk of posts for a mapping, then re-enqueue for the next chunk.
 */
class BulkEnqueuer {

	/**
	 * Register the Action Scheduler hook.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'cfi_bulk_sync', array( static::class, 'process' ), 10, 3 );
	}

	/**
	 * Process a chunk.
	 *
	 * @param string $mapping_id Mapping ID.
	 * @param int    $offset     Current offset.
	 * @param int    $chunk_size Posts per chunk.
	 * @return void
	 */
	public static function process( string $mapping_id, int $offset = 0, int $chunk_size = 20 ): void {
		$logs = new LogsRepo();

		if ( ! Validators::is_valid_id( $mapping_id, 'map' ) ) {
			$logs->push( 'error', 'Bulk sync: invalid mapping ID format.', array( 'mapping_id' => $mapping_id ) );
			return;
		}

		$repo    = new MappingsRepo();
		$mapping = $repo->find( $mapping_id );
		if ( $mapping === null ) {
			$logs->push( 'error', 'Bulk sync: mapping not found.', array( 'mapping_id' => $mapping_id ) );
			return;
		}

		$post_type = $mapping['post_type'] ?? '';
		$status    = $mapping['status'] ?? 'any';

		$post_ids = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => $status === 'publish' ? 'publish' : 'any',
				'posts_per_page' => $chunk_size,
				'offset'         => $offset,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);

		if ( empty( $post_ids ) ) {
			$logs->push( 'info', 'Bulk sync completed.', array( 'mapping_id' => $mapping_id ) );
			return;
		}

		$engine  = new SyncEngine();
		$success = 0;
		$errors  = 0;

		foreach ( $post_ids as $pid ) {
			Guard::reset();
			$result = $engine->sync( $pid, $mapping );

			if ( is_wp_error( $result ) ) {
				++$errors;
			} else {
				++$success;
			}
		}

		$logs->push(
			'info',
			sprintf( 'Bulk chunk done: offset=%d, ok=%d, err=%d.', $offset, $success, $errors ),
			array( 'mapping_id' => $mapping_id )
		);

		// Enqueue next chunk.
		if ( count( $post_ids ) >= $chunk_size ) {
			as_enqueue_async_action(
				'cfi_bulk_sync',
				array(
					'mapping_id' => $mapping_id,
					'offset'     => $offset + $chunk_size,
					'chunk_size' => $chunk_size,
				),
				'cfi'
			);
		} else {
			$logs->push( 'info', 'Bulk sync: all chunks processed.', array( 'mapping_id' => $mapping_id ) );
		}
	}
}
