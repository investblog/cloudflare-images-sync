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
		);
	}

	/**
	 * Default presets seeded on activation.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function presets(): array {
		return array(
			'social_1200x630' => array(
				'name'    => 'social_1200x630',
				'variant' => 'w=1200,height=630,fit=cover,quality=85,f=auto',
			),
			'square_800'      => array(
				'name'    => 'square_800',
				'variant' => 'w=800,height=800,fit=cover,quality=85,f=auto',
			),
			'thumb_400x300'   => array(
				'name'    => 'thumb_400x300',
				'variant' => 'w=400,height=300,fit=cover,quality=80,f=auto',
			),
		);
	}

	/**
	 * Recommended presets v2 — full set available on-demand.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function recommended_presets(): array {
		return array(
			'og_1200x630'           => array(
				'name'    => 'og_1200x630',
				'variant' => 'w=1200,h=630,fit=cover,quality=85,f=auto',
			),
			'og_1200x630_smartcrop' => array(
				'name'    => 'og_1200x630_smartcrop',
				'variant' => 'w=1200,h=630,fit=cover,gravity=auto,quality=85,f=auto',
			),
			'square_800'            => array(
				'name'    => 'square_800',
				'variant' => 'w=800,h=800,fit=cover,quality=85,f=auto',
			),
			'square_800_smartcrop'  => array(
				'name'    => 'square_800_smartcrop',
				'variant' => 'w=800,h=800,fit=cover,gravity=auto,quality=85,f=auto',
			),
			'hero_1600x900'         => array(
				'name'    => 'hero_1600x900',
				'variant' => 'w=1600,h=900,fit=cover,quality=85,f=auto',
			),
			'thumb_400x300'         => array(
				'name'    => 'thumb_400x300',
				'variant' => 'w=400,h=300,fit=cover,quality=80,f=auto',
			),
			'thumb_400x300_sharp'   => array(
				'name'    => 'thumb_400x300_sharp',
				'variant' => 'w=400,h=300,fit=cover,quality=80,sharpen=2,f=auto',
			),
			'retina_600w_dpr2'      => array(
				'name'    => 'retina_600w_dpr2',
				'variant' => 'w=600,dpr=2,quality=85,f=auto',
			),
			'mobile_slow'           => array(
				'name'    => 'mobile_slow',
				'variant' => 'w=900,quality=85,slow-connection-quality=60,f=auto',
			),
			'placeholder_blur'      => array(
				'name'    => 'placeholder_blur',
				'variant' => 'w=80,blur=20,quality=50,f=auto',
			),
			'trim_transparent'      => array(
				'name'    => 'trim_transparent',
				'variant' => 'trim=auto,w=512,quality=90,f=auto',
			),
			'no_metadata'           => array(
				'name'    => 'no_metadata',
				'variant' => 'w=1200,quality=85,metadata=none,f=auto',
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
