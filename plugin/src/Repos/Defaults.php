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
			'logs_max'     => 200,
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
