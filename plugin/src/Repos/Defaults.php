<?php
/**
 * Default values for all plugin options.
 *
 * @package CloudflareImagesSync
 */

namespace CFI\Repos;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Default schemas and values.
 */
final class Defaults {

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function settings(): array {
		return array(
			'account_id'   => '',
			'account_hash' => '',
			'api_token'    => '',
			'debug'        => false,
			'use_queue'    => true,
			'logs_max'        => 200,
			'flex_status'     => 'unknown',
			'flex_checked_at' => 0,
			'api_tested_at'   => 0,
		);
	}

	/**
	 * Default presets seeded on activation.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function presets(): array {
		// Same as recommended — just the core set.
		return self::recommended_presets();
	}

	/**
	 * Recommended presets — core set for 80% of use cases.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function recommended_presets(): array {
		return array(
			'public'           => array(
				'name'    => 'public',
				'variant' => 'public',
			),
			'og_1200x630'      => array(
				'name'    => 'og_1200x630',
				'variant' => 'w=1200,h=630,fit=cover,quality=85,f=auto',
			),
			'square_800'       => array(
				'name'    => 'square_800',
				'variant' => 'w=800,h=800,fit=cover,quality=85,f=auto',
			),
			'thumb_400x300'    => array(
				'name'    => 'thumb_400x300',
				'variant' => 'w=400,h=300,fit=cover,quality=80,f=auto',
			),
			'hero_1600x900'    => array(
				'name'    => 'hero_1600x900',
				'variant' => 'w=1600,h=900,fit=cover,quality=85,f=auto',
			),
			'mobile_600w_2x'   => array(
				'name'    => 'mobile_600w_2x',
				'variant' => 'w=600,dpr=2,quality=85,f=auto',
			),
			'square_smartcrop' => array(
				'name'    => 'square_smartcrop',
				'variant' => 'w=800,h=800,fit=cover,gravity=auto,quality=85,f=auto',
			),
		);
	}

	/**
	 * Check if a preset name is in the recommended list.
	 *
	 * @param string $name Preset name.
	 * @return bool
	 */
	public static function is_recommended_name( string $name ): bool {
		$lower = strtolower( $name );
		foreach ( self::recommended_presets() as $preset ) {
			if ( strtolower( $preset['name'] ) === $lower ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Default mapping structure (used for normalization).
	 *
	 * @return array<string, mixed>
	 */
	public static function mapping(): array {
		return array(
			'post_type' => '',
			'status'    => 'any',
			'triggers'  => array(
				'save_post'     => true,
				'acf_save_post' => true,
			),
			'source'    => array(
				'type' => 'acf_field',
				'key'  => '',
			),
			'target'    => array(
				'url_meta' => '',
				'id_meta'  => '',
				'sig_meta' => '',
			),
			'behavior'  => array(
				'upload_if_missing'    => true,
				'reupload_if_changed'  => true,
				'clear_on_empty'       => true,
				'store_cf_id_on_post'  => true,
				'delete_cf_on_reupload' => false,
			),
			'preset_id' => '',
		);
	}

	/**
	 * Allowed source types.
	 *
	 * @return string[]
	 */
	public static function source_types(): array {
		return array(
			'acf_field',
			'featured_image',
			'post_meta_attachment_id',
			'post_meta_url',
			'attachment_id',
		);
	}

	/**
	 * Default logs structure.
	 *
	 * @return array<string, mixed>
	 */
	public static function logs(): array {
		return array(
			'max'   => 200,
			'items' => array(),
		);
	}

	/**
	 * Allowed log levels.
	 *
	 * @return string[]
	 */
	public static function log_levels(): array {
		return array( 'info', 'warning', 'error', 'debug' );
	}

	/**
	 * Allowed log context keys.
	 *
	 * @return string[]
	 */
	public static function log_context_keys(): array {
		return array( 'post_id', 'mapping_id', 'extra' );
	}
}
