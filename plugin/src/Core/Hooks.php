<?php
/**
 * WordPress hook registrations for auto-sync.
 *
 * @package CloudflareImagesSync
 */

namespace CFI\Core;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CFI\Repos\LogsRepo;
use CFI\Repos\MappingsRepo;
use CFI\Repos\SettingsRepo;

/**
 * Register save_post_{cpt} and acf/save_post hooks
 * based on configured mappings.
 */
class Hooks {

	/**
	 * Mappings repository instance.
	 *
	 * @var MappingsRepo
	 */
	private MappingsRepo $mappings;

	/**
	 * Sync engine instance.
	 *
	 * @var SyncEngine
	 */
	private SyncEngine $engine;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->mappings = new MappingsRepo();
		$this->engine   = new SyncEngine();
	}

	/**
	 * Register all hooks based on current mappings.
	 *
	 * @return void
	 */
	public function init(): void {
		$all_mappings = $this->mappings->all();

		if ( empty( $all_mappings ) ) {
			$this->debug_log( 'Hooks::init — no mappings found, skipping hook registration.' );
			return;
		}

		// Collect unique post types that have mappings.
		$post_types = array();
		foreach ( $all_mappings as $mapping ) {
			$pt = $mapping['post_type'] ?? '';
			if ( $pt !== '' ) {
				$post_types[ $pt ] = true;
			}
		}

		// Register save_post_{cpt} for each post type.
		// Priority 100 ensures we run after most other plugins.
		foreach ( array_keys( $post_types ) as $pt ) {
			add_action( 'save_post_' . $pt, array( $this, 'on_save_post' ), 100, 2 );
		}

		$this->debug_log( 'Hooks::init — registered save_post hooks for: ' . implode( ', ', array_keys( $post_types ) ) );

		// Register acf/save_post (fires for all post types).
		// Priority 100 ensures we run after ACF has fully saved all fields
		// and after any other plugins that might modify ACF data.
		if ( function_exists( 'acf_get_field' ) || has_action( 'acf/init' ) ) {
			add_action( 'acf/save_post', array( $this, 'on_acf_save_post' ), 100 );
		}
	}

	/**
	 * Handle save_post_{cpt} hook.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public function on_save_post( int $post_id, \WP_Post $post ): void {
		$this->debug_log( "Hooks::on_save_post fired for post #{$post_id} ({$post->post_type})" );

		if ( ! $this->should_process( $post_id, $post ) ) {
			$this->debug_log( "Hooks::on_save_post — should_process returned false for post #{$post_id}" );
			return;
		}

		$this->debug_log( "Hooks::on_save_post — running mappings for post #{$post_id}" );
		$this->run_mappings( $post_id, $post->post_type, 'save_post' );
	}

	/**
	 * Handle acf/save_post hook.
	 *
	 * @param int $post_id Post ID (or other object ID — ACF can pass user/term IDs).
	 * @return void
	 */
	public function on_acf_save_post( $post_id ): void {
		// ACF can pass string IDs like "user_1" or "term_5".
		if ( ! is_numeric( $post_id ) ) {
			return;
		}

		$post_id = (int) $post_id;
		$post    = get_post( $post_id );

		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$this->debug_log( "Hooks::on_acf_save_post fired for post #{$post_id} ({$post->post_type})" );

		if ( ! $this->should_process( $post_id, $post ) ) {
			$this->debug_log( "Hooks::on_acf_save_post — should_process returned false for post #{$post_id}" );
			return;
		}

		$this->debug_log( "Hooks::on_acf_save_post — running mappings for post #{$post_id}" );
		$this->run_mappings( $post_id, $post->post_type, 'acf_save_post' );
	}

	/**
	 * Run all matching mappings for a post.
	 *
	 * @param int    $post_id   Post ID.
	 * @param string $post_type Post type.
	 * @param string $trigger   Trigger name ('save_post' or 'acf_save_post').
	 * @return void
	 */
	private function run_mappings( int $post_id, string $post_type, string $trigger ): void {
		$mappings = $this->mappings->for_post_type( $post_type );

		$this->debug_log( 'run_mappings — found ' . count( $mappings ) . " mapping(s) for post_type '{$post_type}'" );

		if ( empty( $mappings ) ) {
			return;
		}

		foreach ( $mappings as $mapping ) {
			$mapping_id = $mapping['id'] ?? '';

			// Check if this trigger is enabled for the mapping.
			$triggers = $mapping['triggers'] ?? array();
			if ( empty( $triggers[ $trigger ] ) ) {
				$this->debug_log( "run_mappings — mapping '{$mapping_id}' skipped: trigger '{$trigger}' not enabled" );
				continue;
			}

			// Check status filter.
			$status = $mapping['status'] ?? 'any';
			if ( $status === 'publish' ) {
				$post = get_post( $post_id );
				if ( $post && $post->post_status !== 'publish' ) {
					$this->debug_log( "run_mappings — mapping '{$mapping_id}' skipped: post status is '{$post->post_status}', not 'publish'" );
					continue;
				}
			}

			// Dedupe guard.
			if ( ! Guard::acquire( $post_id, $mapping_id ) ) {
				$this->debug_log( "run_mappings — mapping '{$mapping_id}' skipped: Guard blocked (already processed)" );
				continue;
			}

			// Use queue or sync directly.
			$settings = ( new SettingsRepo() )->get();

			if ( ! empty( $settings['use_queue'] ) && $this->action_scheduler_available() ) {
				$this->debug_log( "run_mappings — mapping '{$mapping_id}' queued via Action Scheduler" );
				as_enqueue_async_action(
					'cfi_sync_single',
					array(
						'post_id'    => $post_id,
						'mapping_id' => $mapping_id,
					),
					'cfi'
				);
			} else {
				$this->debug_log( "run_mappings — mapping '{$mapping_id}' syncing directly" );
				Guard::lock();
				$this->engine->sync( $post_id, $mapping );
				Guard::unlock();
			}
		}
	}

	/**
	 * Common checks before processing a save.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return bool
	 */
	private function should_process( int $post_id, \WP_Post $post ): bool {
		// Skip if inside our own meta update.
		if ( Guard::is_locked() ) {
			return false;
		}

		// Skip autosaves.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return false;
		}

		// Skip revisions.
		if ( wp_is_post_revision( $post_id ) ) {
			return false;
		}

		// Skip auto-drafts.
		if ( $post->post_status === 'auto-draft' ) {
			return false;
		}

		return true;
	}

	/**
	 * Check if Action Scheduler is available.
	 *
	 * @return bool
	 */
	private function action_scheduler_available(): bool {
		return function_exists( 'as_enqueue_async_action' );
	}

	/**
	 * Log debug message if debug mode is enabled.
	 *
	 * @param string $message Log message.
	 * @return void
	 */
	private function debug_log( string $message ): void {
		$settings = ( new SettingsRepo() )->get();
		if ( empty( $settings['debug'] ) ) {
			return;
		}

		$logs = new LogsRepo();
		$logs->push( 'debug', $message );
	}
}
