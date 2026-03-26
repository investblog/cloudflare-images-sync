<?php
/**
 * Tests: Validators utility class.
 *
 * @package CloudflareImagesSync
 */

class Test_Validators extends WP_UnitTestCase {

	/**
	 * Valid internal IDs should pass.
	 */
	public function test_valid_id_formats(): void {
		$this->assertTrue( CFIMG\Support\Validators::is_valid_id( 'preset_aabbccdd', 'preset' ) );
		$this->assertTrue( CFIMG\Support\Validators::is_valid_id( 'map_11223344', 'map' ) );
		$this->assertTrue( CFIMG\Support\Validators::is_valid_id( 'preset_00000000', 'preset' ) );
	}

	/**
	 * Invalid internal IDs should fail.
	 */
	public function test_invalid_id_formats(): void {
		$this->assertFalse( CFIMG\Support\Validators::is_valid_id( '', 'preset' ) );
		$this->assertFalse( CFIMG\Support\Validators::is_valid_id( 'preset_short', 'preset' ) );
		$this->assertFalse( CFIMG\Support\Validators::is_valid_id( 'map_AABBCCDD', 'map' ), 'Uppercase hex should fail.' );
		$this->assertFalse( CFIMG\Support\Validators::is_valid_id( 'wrong_aabbccdd', 'preset' ), 'Wrong prefix should fail.' );
		$this->assertFalse( CFIMG\Support\Validators::is_valid_id( 'preset_aabbccdde', 'preset' ), 'Too long should fail.' );
	}

	/**
	 * Valid meta keys should pass.
	 */
	public function test_valid_keys(): void {
		$this->assertTrue( CFIMG\Support\Validators::is_valid_key( '_cf_delivery_url' ) );
		$this->assertTrue( CFIMG\Support\Validators::is_valid_key( 'og_image' ) );
		$this->assertTrue( CFIMG\Support\Validators::is_valid_key( 'my-field' ) );
		$this->assertTrue( CFIMG\Support\Validators::is_valid_key( 'my.field' ) );
		$this->assertTrue( CFIMG\Support\Validators::is_valid_key( 'my:field' ) );
	}

	/**
	 * Invalid meta keys should fail.
	 */
	public function test_invalid_keys(): void {
		$this->assertFalse( CFIMG\Support\Validators::is_valid_key( '' ) );
		$this->assertFalse( CFIMG\Support\Validators::is_valid_key( '  ' ) );
		$this->assertFalse( CFIMG\Support\Validators::is_valid_key( 'has spaces' ) );
		$this->assertFalse( CFIMG\Support\Validators::is_valid_key( 'has<html>' ) );
		$this->assertFalse( CFIMG\Support\Validators::is_valid_key( str_repeat( 'a', 192 ) ), 'Over 191 chars should fail.' );
	}

	/**
	 * Reserved WordPress keys should be detected.
	 */
	public function test_reserved_keys(): void {
		$this->assertTrue( CFIMG\Support\Validators::is_reserved_key( '_wp_attached_file' ) );
		$this->assertTrue( CFIMG\Support\Validators::is_reserved_key( '_edit_lock' ) );
		$this->assertTrue( CFIMG\Support\Validators::is_reserved_key( '_oembed_abc' ) );
		$this->assertTrue( CFIMG\Support\Validators::is_reserved_key( '_thumbnail_id' ) );
		$this->assertFalse( CFIMG\Support\Validators::is_reserved_key( '_cf_delivery_url' ) );
		$this->assertFalse( CFIMG\Support\Validators::is_reserved_key( '_my_custom_field' ) );
	}

	/**
	 * Flexible variant detection should work.
	 */
	public function test_is_flexible_variant(): void {
		$this->assertTrue( CFIMG\Support\Validators::is_flexible_variant( 'w=1200,h=630,fit=cover' ) );
		$this->assertTrue( CFIMG\Support\Validators::is_flexible_variant( 'w=800' ) );
		$this->assertTrue( CFIMG\Support\Validators::is_flexible_variant( 'quality=85,f=auto' ) );
		$this->assertTrue( CFIMG\Support\Validators::is_flexible_variant( 'w=600,dpr=2' ) );
		$this->assertFalse( CFIMG\Support\Validators::is_flexible_variant( 'public' ) );
		$this->assertFalse( CFIMG\Support\Validators::is_flexible_variant( 'thumbnail' ) );
		$this->assertFalse( CFIMG\Support\Validators::is_flexible_variant( 'my-named-variant' ) );
	}

	/**
	 * Settings normalization should merge with defaults.
	 */
	public function test_normalize_settings_defaults(): void {
		$result = CFIMG\Support\Validators::normalize_settings( null );
		$this->assertIsArray( $result );
		$this->assertSame( '', $result['account_id'] );
		$this->assertSame( 'unknown', $result['flex_status'] );
		$this->assertSame( 200, $result['logs_max'] );
	}

	/**
	 * Settings normalization should clamp logs_max.
	 */
	public function test_normalize_settings_clamps_logs_max(): void {
		$result = CFIMG\Support\Validators::normalize_settings( array( 'logs_max' => 9999 ) );
		$this->assertSame( 1000, $result['logs_max'] );

		$result = CFIMG\Support\Validators::normalize_settings( array( 'logs_max' => 1 ) );
		$this->assertSame( 50, $result['logs_max'] );
	}

	/**
	 * Settings normalization should reject invalid flex_status.
	 */
	public function test_normalize_settings_invalid_flex_status(): void {
		$result = CFIMG\Support\Validators::normalize_settings( array( 'flex_status' => 'hacked' ) );
		$this->assertSame( 'unknown', $result['flex_status'] );
	}

	/**
	 * Logs normalization should respect the runtime max passed in by settings.
	 */
	public function test_normalize_logs_uses_runtime_max(): void {
		$result = CFIMG\Support\Validators::normalize_logs(
			array(
				'max'   => 50,
				'items' => array(),
			),
			750
		);
		$this->assertSame( 750, $result['max'] );

		$result = CFIMG\Support\Validators::normalize_logs( null, 10 );
		$this->assertSame( 50, $result['max'] );
	}

	/**
	 * Preset validation should require name and variant.
	 */
	public function test_validate_preset_requires_name(): void {
		$result = CFIMG\Support\Validators::validate_preset( array( 'name' => '', 'variant' => 'public' ) );
		$this->assertWPError( $result );
	}

	/**
	 * Preset validation should require variant.
	 */
	public function test_validate_preset_requires_variant(): void {
		$result = CFIMG\Support\Validators::validate_preset( array( 'name' => 'test', 'variant' => '' ) );
		$this->assertWPError( $result );
	}

	/**
	 * Valid preset should pass validation.
	 */
	public function test_validate_preset_valid(): void {
		$result = CFIMG\Support\Validators::validate_preset( array( 'name' => 'og_1200', 'variant' => 'w=1200,h=630,fit=cover' ) );
		$this->assertTrue( $result );
	}

	/**
	 * Preset variant with invalid characters should fail.
	 */
	public function test_validate_preset_variant_invalid_chars(): void {
		$result = CFIMG\Support\Validators::validate_preset( array( 'name' => 'test', 'variant' => 'w=1200; rm -rf /' ) );
		$this->assertWPError( $result );
	}

	/**
	 * Clamp utility should work correctly.
	 */
	public function test_clamp_int(): void {
		$this->assertSame( 50, CFIMG\Support\Validators::clamp_int( 10, 50, 1000 ) );
		$this->assertSame( 500, CFIMG\Support\Validators::clamp_int( 500, 50, 1000 ) );
		$this->assertSame( 1000, CFIMG\Support\Validators::clamp_int( 2000, 50, 1000 ) );
	}
}
