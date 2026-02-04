<?php
/**
 * WP-CLI commands for Cloudflare Images Sync.
 *
 * @package CloudflareImagesSync
 */

namespace CFI\CLI;

use CFI\Api\CloudflareImagesClient;
use CFI\Core\Guard;
use CFI\Core\SyncEngine;
use CFI\Repos\MappingsRepo;

/**
 * Manage Cloudflare Images sync operations.
 */
class Commands {

	/**
	 * Test connection to Cloudflare Images API.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cfi test
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 * @return void
	 */
	public function test( array $args, array $assoc_args ): void {
		$client = CloudflareImagesClient::from_settings();

		if ( is_wp_error( $client ) ) {
			\WP_CLI::error( $client->get_error_message() );
			return;
		}

		$result = $client->test_connection();

		if ( is_wp_error( $result ) ) {
			\WP_CLI::error( 'Connection failed: ' . $result->get_error_message() );
			return;
		}

		\WP_CLI::success( 'Connection to Cloudflare Images API is working.' );
	}

	/**
	 * Sync posts to Cloudflare Images by mapping.
	 *
	 * ## OPTIONS
	 *
	 * --mapping=<id>
	 * : Mapping ID to sync.
	 *
	 * [--post_id=<id>]
	 * : Sync a single post by ID.
	 *
	 * [--limit=<number>]
	 * : Max posts to process.
	 * ---
	 * default: 100
	 * ---
	 *
	 * [--offset=<number>]
	 * : Skip this many posts.
	 * ---
	 * default: 0
	 * ---
	 *
	 * [--dry-run]
	 * : Show what would be synced without actually syncing.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cfi sync --mapping=map_abc123
	 *     wp cfi sync --mapping=map_abc123 --post_id=456
	 *     wp cfi sync --mapping=map_abc123 --limit=50 --offset=100
	 *     wp cfi sync --mapping=map_abc123 --dry-run
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 * @return void
	 */
	public function sync( array $args, array $assoc_args ): void {
		$mapping_id = $assoc_args['mapping'] ?? '';
		$repo       = new MappingsRepo();
		$mapping    = $repo->find( $mapping_id );

		if ( $mapping === null ) {
			\WP_CLI::error( "Mapping '{$mapping_id}' not found." );
			return;
		}

		$engine  = new SyncEngine();
		$dry_run = isset( $assoc_args['dry-run'] );

		// Single post mode.
		if ( ! empty( $assoc_args['post_id'] ) ) {
			$post_id = (int) $assoc_args['post_id'];

			if ( $dry_run ) {
				\WP_CLI::log( "Would sync post #{$post_id} with mapping {$mapping_id}." );
				return;
			}

			Guard::reset();
			$result = $engine->sync( $post_id, $mapping );

			if ( is_wp_error( $result ) ) {
				\WP_CLI::error( "Post #{$post_id}: " . $result->get_error_message() );
				return;
			}

			\WP_CLI::success( "Post #{$post_id} synced." );
			return;
		}

		// Batch mode.
		$limit  = (int) ( $assoc_args['limit'] ?? 100 );
		$offset = (int) ( $assoc_args['offset'] ?? 0 );

		$post_type = $mapping['post_type'] ?? '';
		$status    = $mapping['status'] ?? 'any';

		$query_args = array(
			'post_type'      => $post_type,
			'post_status'    => $status === 'publish' ? 'publish' : 'any',
			'posts_per_page' => $limit,
			'offset'         => $offset,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'ASC',
		);

		$post_ids = get_posts( $query_args );

		if ( empty( $post_ids ) ) {
			\WP_CLI::warning( 'No posts found matching the criteria.' );
			return;
		}

		\WP_CLI::log( sprintf( 'Found %d posts to sync.', count( $post_ids ) ) );

		if ( $dry_run ) {
			foreach ( $post_ids as $pid ) {
				\WP_CLI::log( "Would sync post #{$pid}." );
			}
			return;
		}

		$success = 0;
		$errors  = 0;

		foreach ( $post_ids as $pid ) {
			Guard::reset();
			$result = $engine->sync( $pid, $mapping );

			if ( is_wp_error( $result ) ) {
				\WP_CLI::warning( "Post #{$pid}: " . $result->get_error_message() );
				++$errors;
			} else {
				\WP_CLI::log( "Post #{$pid}: OK" );
				++$success;
			}
		}

		\WP_CLI::success( "Done. {$success} synced, {$errors} errors." );
	}
}
