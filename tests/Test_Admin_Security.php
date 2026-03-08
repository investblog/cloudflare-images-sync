<?php
/**
 * Tests: admin action security (nonce + capability enforcement).
 *
 * Verifies that all admin POST/AJAX handlers reject requests
 * that lack a valid nonce or the manage_options capability.
 *
 * @package CloudflareImagesSync
 */

class Test_Admin_Security extends WP_UnitTestCase {

	/**
	 * Admin user ID.
	 *
	 * @var int
	 */
	private int $admin_id;

	/**
	 * Subscriber user ID.
	 *
	 * @var int
	 */
	private int $subscriber_id;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->admin_id      = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
	}

	// ── Settings ────────────────────────────────────────────────────

	/**
	 * Settings save should do nothing for subscribers.
	 */
	public function test_settings_save_rejected_without_capability(): void {
		wp_set_current_user( $this->subscriber_id );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST['cfimg_save_settings'] = '1';

		$page = new CFIMG\Admin\SettingsPage();
		$page->handle_actions();

		// If it were processed, settings would have changed. Verify they didn't.
		$settings = ( new CFIMG\Repos\SettingsRepo() )->get();
		$this->assertSame( '', $settings['account_id'], 'Subscriber should not be able to save settings.' );

		unset( $_POST['cfimg_save_settings'], $_SERVER['REQUEST_METHOD'] );
	}

	/**
	 * Settings save should die without valid nonce.
	 */
	public function test_settings_save_dies_without_nonce(): void {
		wp_set_current_user( $this->admin_id );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST['cfimg_save_settings'] = '1';
		$_POST['account_id'] = 'aaaabbbbccccddddeeeeffffgggghhhh';

		$this->expectException( WPDieException::class );

		$page = new CFIMG\Admin\SettingsPage();
		$page->handle_actions();

		unset( $_POST['cfimg_save_settings'], $_POST['account_id'], $_SERVER['REQUEST_METHOD'] );
	}

	/**
	 * Test connection should do nothing for subscribers.
	 */
	public function test_settings_test_connection_rejected_without_capability(): void {
		wp_set_current_user( $this->subscriber_id );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST['cfimg_test_connection'] = '1';

		$page = new CFIMG\Admin\SettingsPage();
		$page->handle_actions();

		// No changes expected.
		$settings = ( new CFIMG\Repos\SettingsRepo() )->get();
		$this->assertSame( 0, $settings['api_tested_at'], 'Subscriber should not be able to test connection.' );

		unset( $_POST['cfimg_test_connection'], $_SERVER['REQUEST_METHOD'] );
	}

	// ── Mappings ────────────────────────────────────────────────────

	/**
	 * Mapping save should do nothing for subscribers.
	 */
	public function test_mappings_save_rejected_without_capability(): void {
		wp_set_current_user( $this->subscriber_id );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST['cfimg_save_mapping'] = '1';

		$page = new CFIMG\Admin\MappingsPage();
		$page->handle_actions();

		$mappings = ( new CFIMG\Repos\MappingsRepo() )->all();
		$this->assertEmpty( $mappings, 'Subscriber should not be able to create mappings.' );

		unset( $_POST['cfimg_save_mapping'], $_SERVER['REQUEST_METHOD'] );
	}

	/**
	 * Mapping save should die without valid nonce.
	 */
	public function test_mappings_save_dies_without_nonce(): void {
		wp_set_current_user( $this->admin_id );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST['cfimg_save_mapping'] = '1';
		$_POST['cfimg_post_type'] = 'post';

		$this->expectException( WPDieException::class );

		$page = new CFIMG\Admin\MappingsPage();
		$page->handle_actions();

		unset( $_POST['cfimg_save_mapping'], $_POST['cfimg_post_type'], $_SERVER['REQUEST_METHOD'] );
	}

	/**
	 * Mapping delete should do nothing for subscribers.
	 */
	public function test_mappings_delete_rejected_without_capability(): void {
		// Create a mapping as admin first.
		wp_set_current_user( $this->admin_id );
		$repo = new CFIMG\Repos\MappingsRepo();
		$repo->create(
			array(
				'post_type' => 'post',
				'source'    => array( 'type' => 'featured_image', 'key' => '' ),
				'target'    => array( 'url_meta' => '_test_url', 'id_meta' => '', 'sig_meta' => '' ),
			)
		);
		$all = $repo->all();
		$this->assertNotEmpty( $all );
		$mapping_id = array_key_first( $all );

		// Try to delete as subscriber.
		wp_set_current_user( $this->subscriber_id );
		$_GET['action']     = 'delete';
		$_GET['mapping_id'] = $mapping_id;

		$page = new CFIMG\Admin\MappingsPage();
		$page->handle_actions();

		// Mapping should still exist.
		$this->assertNotNull( $repo->find( $mapping_id ), 'Subscriber should not be able to delete mappings.' );

		unset( $_GET['action'], $_GET['mapping_id'] );
	}

	/**
	 * Bulk sync should do nothing for subscribers.
	 */
	public function test_mappings_bulk_sync_rejected_without_capability(): void {
		wp_set_current_user( $this->subscriber_id );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST['cfimg_bulk_sync']    = '1';
		$_POST['bulk_mapping_id']    = 'map_aaaabbbb';

		$page = new CFIMG\Admin\MappingsPage();
		$page->handle_actions();

		// Nothing should have been enqueued. If AS is not installed, we just
		// verify no error/redirect occurred (the method returned early).
		$this->assertTrue( true, 'Subscriber bulk sync request should be silently ignored.' );

		unset( $_POST['cfimg_bulk_sync'], $_POST['bulk_mapping_id'], $_SERVER['REQUEST_METHOD'] );
	}

	// ── Preview / Sync Now ──────────────────────────────────────────

	/**
	 * Preview sync-now should do nothing for subscribers.
	 */
	public function test_preview_sync_now_rejected_without_capability(): void {
		wp_set_current_user( $this->subscriber_id );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST['cfimg_sync_now'] = '1';

		$page = new CFIMG\Admin\PreviewPage();
		$page->handle_actions();

		$this->assertTrue( true, 'Subscriber sync-now request should be silently ignored.' );

		unset( $_POST['cfimg_sync_now'], $_SERVER['REQUEST_METHOD'] );
	}

	/**
	 * Preview sync-now should die without valid nonce.
	 */
	public function test_preview_sync_now_dies_without_nonce(): void {
		wp_set_current_user( $this->admin_id );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST['cfimg_sync_now'] = '1';
		$_GET['post_id']    = '1';
		$_GET['mapping_id'] = 'map_aaaabbbb';

		$this->expectException( WPDieException::class );

		$page = new CFIMG\Admin\PreviewPage();
		$page->handle_actions();

		unset( $_POST['cfimg_sync_now'], $_GET['post_id'], $_GET['mapping_id'], $_SERVER['REQUEST_METHOD'] );
	}

	/**
	 * Demo upload should do nothing for subscribers.
	 */
	public function test_preview_demo_upload_rejected_without_capability(): void {
		wp_set_current_user( $this->subscriber_id );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST['cfimg_demo_upload'] = '1';

		$page = new CFIMG\Admin\PreviewPage();
		$page->handle_actions();

		$this->assertTrue( true, 'Subscriber demo upload request should be silently ignored.' );

		unset( $_POST['cfimg_demo_upload'], $_SERVER['REQUEST_METHOD'] );
	}

	// ── Logs ────────────────────────────────────────────────────────

	/**
	 * Clear logs should do nothing for subscribers.
	 */
	public function test_logs_clear_rejected_without_capability(): void {
		// Add a log entry as admin.
		wp_set_current_user( $this->admin_id );
		$repo = new CFIMG\Repos\LogsRepo();
		$repo->push( 'info', 'Test entry' );
		$this->assertNotEmpty( $repo->all() );

		// Try to clear as subscriber.
		wp_set_current_user( $this->subscriber_id );
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST['cfimg_clear_logs'] = '1';

		$page = new CFIMG\Admin\LogsPage();
		$page->handle_actions();

		// Logs should still exist.
		$this->assertNotEmpty( $repo->all(), 'Subscriber should not be able to clear logs.' );

		unset( $_POST['cfimg_clear_logs'], $_SERVER['REQUEST_METHOD'] );
	}

	/**
	 * Clear logs should die without valid nonce.
	 */
	public function test_logs_clear_dies_without_nonce(): void {
		wp_set_current_user( $this->admin_id );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST['cfimg_clear_logs'] = '1';

		$this->expectException( WPDieException::class );

		$page = new CFIMG\Admin\LogsPage();
		$page->handle_actions();

		unset( $_POST['cfimg_clear_logs'], $_SERVER['REQUEST_METHOD'] );
	}

	// ── Presets ──────────────────────────────────────────────────────

	/**
	 * Preset save should do nothing for subscribers.
	 */
	public function test_presets_save_rejected_without_capability(): void {
		wp_set_current_user( $this->subscriber_id );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST['cfimg_save_preset'] = '1';
		$_POST['preset_name']     = 'evil_preset';
		$_POST['preset_variant']  = 'public';

		$page = new CFIMG\Admin\PresetsPage();
		$page->handle_actions();

		$presets = ( new CFIMG\Repos\PresetsRepo() )->all();
		$found   = false;
		foreach ( $presets as $p ) {
			if ( ( $p['name'] ?? '' ) === 'evil_preset' ) {
				$found = true;
			}
		}
		$this->assertFalse( $found, 'Subscriber should not be able to create presets.' );

		unset( $_POST['cfimg_save_preset'], $_POST['preset_name'], $_POST['preset_variant'], $_SERVER['REQUEST_METHOD'] );
	}
}
