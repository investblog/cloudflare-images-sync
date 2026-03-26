<?php
/**
 * Tests: SyncEngine core sync flows.
 *
 * Covers empty source, no-op (unchanged), and basic data flow.
 *
 * @package CloudflareImagesSync
 */

class Test_Sync_Engine extends WP_UnitTestCase {

	/**
	 * Default mapping used in tests.
	 *
	 * @var array
	 */
	private array $mapping;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();

		( new CFIMG\Repos\SettingsRepo() )->reset();

		$this->mapping = array(
			'id'        => 'map_aaaabbbb',
			'post_type' => 'post',
			'status'    => 'any',
			'triggers'  => array(
				'save_post'     => true,
				'acf_save_post' => true,
			),
			'source'    => array(
				'type' => 'featured_image',
				'key'  => '',
			),
			'target'    => array(
				'url_meta' => '_cf_delivery_url',
				'id_meta'  => '_cf_image_id',
				'sig_meta' => '_cf_change_sig',
			),
			'behavior'  => array(
				'upload_if_missing'     => true,
				'reupload_if_changed'   => true,
				'clear_on_empty'        => true,
				'store_cf_id_on_post'   => true,
				'delete_cf_on_reupload' => false,
			),
			'preset_id' => '',
		);
	}

	/**
	 * Clean up settings after each test.
	 */
	public function tear_down(): void {
		( new CFIMG\Repos\SettingsRepo() )->reset();
		parent::tear_down();
	}

	/**
	 * Sync with no source image should clear target meta when clear_on_empty is true.
	 */
	public function test_sync_empty_source_clears_meta(): void {
		$post_id = self::factory()->post->create();

		// Pre-populate target meta.
		update_post_meta( $post_id, '_cf_delivery_url', 'https://old.example.com/image' );
		update_post_meta( $post_id, '_cf_image_id', 'old-cf-id' );
		update_post_meta( $post_id, '_cf_change_sig', 'old-sig' );

		$engine = new CFIMG\Core\SyncEngine();
		$result = $engine->sync( $post_id, $this->mapping );

		$this->assertTrue( $result );
		$this->assertEmpty( get_post_meta( $post_id, '_cf_delivery_url', true ), 'URL meta should be cleared.' );
		$this->assertEmpty( get_post_meta( $post_id, '_cf_image_id', true ), 'ID meta should be cleared.' );
		$this->assertEmpty( get_post_meta( $post_id, '_cf_change_sig', true ), 'Sig meta should be cleared.' );
	}

	/**
	 * Sync with no source and clear_on_empty disabled should leave meta intact.
	 */
	public function test_sync_empty_source_preserves_meta_when_clear_disabled(): void {
		$post_id = self::factory()->post->create();

		update_post_meta( $post_id, '_cf_delivery_url', 'https://example.com/keep-me' );

		$mapping = $this->mapping;
		$mapping['behavior']['clear_on_empty'] = false;

		$engine = new CFIMG\Core\SyncEngine();
		$result = $engine->sync( $post_id, $mapping );

		$this->assertTrue( $result );
		$this->assertSame(
			'https://example.com/keep-me',
			get_post_meta( $post_id, '_cf_delivery_url', true ),
			'URL meta should be preserved when clear_on_empty is false.'
		);
	}

	/**
	 * Sync with invalid mapping (no url_meta) should return WP_Error.
	 */
	public function test_sync_invalid_mapping_no_url_meta(): void {
		$post_id = self::factory()->post->create();

		$mapping = $this->mapping;
		$mapping['target']['url_meta'] = '';

		$engine = new CFIMG\Core\SyncEngine();
		$result = $engine->sync( $post_id, $mapping );

		$this->assertWPError( $result );
		$this->assertSame( 'cfimg_invalid_mapping', $result->get_error_code() );
	}

	/**
	 * Sync with invalid mapping (bad post_type) should return WP_Error.
	 */
	public function test_sync_invalid_mapping_bad_post_type(): void {
		$post_id = self::factory()->post->create();

		$mapping = $this->mapping;
		$mapping['post_type'] = 'nonexistent_cpt';

		$engine = new CFIMG\Core\SyncEngine();
		$result = $engine->sync( $post_id, $mapping );

		$this->assertWPError( $result );
		$this->assertSame( 'cfimg_invalid_mapping', $result->get_error_code() );
	}

	/**
	 * Sync should reject posts whose type does not match the mapping.
	 */
	public function test_sync_rejects_post_type_mismatch(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_type' => 'page',
			)
		);

		$engine = new CFIMG\Core\SyncEngine();
		$result = $engine->sync( $post_id, $this->mapping );

		$this->assertWPError( $result );
		$this->assertSame( 'cfimg_post_type_mismatch', $result->get_error_code() );
	}

	/**
	 * Sync should honor the store_cf_id_on_post flag when reusing attachment cache.
	 */
	public function test_sync_can_skip_post_cf_id_storage(): void {
		$file = DIR_TESTDATA . '/images/canola.jpg';

		if ( ! file_exists( $file ) ) {
			$this->markTestSkipped( 'Test image canola.jpg not found in test data.' );
		}

		$att_id  = self::factory()->attachment->create_upload_object( $file );
		$post_id = self::factory()->post->create();
		set_post_thumbnail( $post_id, $att_id );

		$file_path = get_attached_file( $att_id );
		$sig       = CFIMG\Core\Signature::compute( $file_path );

		if ( is_wp_error( $sig ) ) {
			$this->fail( 'Could not compute test signature.' );
		}

		update_post_meta( $att_id, CFIMG\Repos\OptionKeys::META_CF_IMAGE_ID, 'cf-test-id' );
		update_post_meta( $att_id, CFIMG\Repos\OptionKeys::META_SIG, $sig );
		update_post_meta( $post_id, '_cf_image_id', 'legacy-cf-id' );

		( new CFIMG\Repos\SettingsRepo() )->update(
			array(
				'account_hash' => 'account-hash-test',
			)
		);

		$mapping = $this->mapping;
		$mapping['behavior']['store_cf_id_on_post'] = false;

		$engine = new CFIMG\Core\SyncEngine();
		$result = $engine->sync( $post_id, $mapping );

		$this->assertTrue( $result );
		$this->assertSame(
			'',
			get_post_meta( $post_id, '_cf_image_id', true ),
			'CF ID meta should be removed when storage is disabled.'
		);
		$this->assertSame(
			$sig,
			get_post_meta( $post_id, '_cf_change_sig', true ),
			'Signature should still be stored.'
		);
		$this->assertStringContainsString(
			'imagedelivery.net/account-hash-test/cf-test-id/',
			(string) get_post_meta( $post_id, '_cf_delivery_url', true )
		);
	}

	/**
	 * SourceResolver should return empty for a post with no featured image.
	 */
	public function test_source_resolver_no_featured_image(): void {
		$post_id = self::factory()->post->create();

		$source   = array( 'type' => 'featured_image', 'key' => '' );
		$resolved = CFIMG\Core\SourceResolver::resolve( $post_id, $source );

		$this->assertTrue( $resolved->is_empty() );
		$this->assertSame( 0, $resolved->get_attachment_id() );
		$this->assertSame( '', $resolved->get_file_path() );
	}

	/**
	 * SourceResolver should resolve a valid attachment.
	 */
	public function test_source_resolver_resolves_attachment(): void {
		$file    = DIR_TESTDATA . '/images/canola.jpg';

		if ( ! file_exists( $file ) ) {
			$this->markTestSkipped( 'Test image canola.jpg not found in test data.' );
		}

		$att_id  = self::factory()->attachment->create_upload_object( $file );
		$post_id = self::factory()->post->create();
		set_post_thumbnail( $post_id, $att_id );

		$source   = array( 'type' => 'featured_image', 'key' => '' );
		$resolved = CFIMG\Core\SourceResolver::resolve( $post_id, $source );

		$this->assertFalse( $resolved->is_empty() );
		$this->assertSame( $att_id, $resolved->get_attachment_id() );
		$this->assertNotEmpty( $resolved->get_file_path() );
	}

	/**
	 * SourceResolver should resolve attachment from meta key.
	 */
	public function test_source_resolver_meta_attachment_id(): void {
		$file = DIR_TESTDATA . '/images/canola.jpg';

		if ( ! file_exists( $file ) ) {
			$this->markTestSkipped( 'Test image canola.jpg not found in test data.' );
		}

		$att_id  = self::factory()->attachment->create_upload_object( $file );
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, '_custom_image_id', $att_id );

		$source   = array( 'type' => 'post_meta_attachment_id', 'key' => '_custom_image_id' );
		$resolved = CFIMG\Core\SourceResolver::resolve( $post_id, $source );

		$this->assertFalse( $resolved->is_empty() );
		$this->assertSame( $att_id, $resolved->get_attachment_id() );
	}

	/**
	 * cfimg_resolve_source filter should override default resolution.
	 */
	public function test_resolve_source_filter_overrides(): void {
		$file = DIR_TESTDATA . '/images/canola.jpg';

		if ( ! file_exists( $file ) ) {
			$this->markTestSkipped( 'Test image canola.jpg not found in test data.' );
		}

		$att_id  = self::factory()->attachment->create_upload_object( $file );
		$post_id = self::factory()->post->create();

		// No featured image set, but the filter should return our attachment.
		$callback = function ( $default, $pid, $src ) use ( $att_id ) {
			return $att_id;
		};
		add_filter( 'cfimg_resolve_source', $callback, 10, 3 );

		$source   = array( 'type' => 'featured_image', 'key' => '' );
		$resolved = CFIMG\Core\SourceResolver::resolve( $post_id, $source );

		$this->assertFalse( $resolved->is_empty() );
		$this->assertSame( $att_id, $resolved->get_attachment_id() );

		remove_filter( 'cfimg_resolve_source', $callback, 10 );
	}
}
