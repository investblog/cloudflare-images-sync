<?php
/**
 * Cloudflare Images delivery URL builder.
 *
 * @package CloudflareImagesSync
 */

namespace CFI\Core;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Build delivery URLs for Cloudflare Images.
 *
 * Format: https://imagedelivery.net/{account_hash}/{image_id}/{variant}
 */
class UrlBuilder {

	private const DELIVERY_HOST = 'https://imagedelivery.net';

	/**
	 * Account hash from settings.
	 *
	 * @var string
	 */
	private string $account_hash;

	/**
	 * Constructor.
	 *
	 * @param string $account_hash Cloudflare account hash.
	 */
	public function __construct( string $account_hash ) {
		$this->account_hash = $account_hash;
	}

	/**
	 * Build a delivery URL for a specific image and variant.
	 *
	 * @param string $image_id Cloudflare image ID.
	 * @param string $variant  Variant string (e.g. "w=1200,height=630,fit=cover").
	 * @return string|\WP_Error Delivery URL or error if params are missing.
	 */
	public function url( string $image_id, string $variant = 'public' ) {
		if ( $this->account_hash === '' ) {
			return new \WP_Error( 'cfi_missing_account_hash', 'Account hash is not configured.' );
		}

		if ( $image_id === '' ) {
			return new \WP_Error( 'cfi_missing_image_id', 'Image ID is required.' );
		}

		return sprintf(
			'%s/%s/%s/%s',
			self::DELIVERY_HOST,
			rawurlencode( $this->account_hash ),
			rawurlencode( $image_id ),
			$variant,
		);
	}

	/**
	 * Build a delivery URL using a preset record.
	 *
	 * @param string               $image_id Cloudflare image ID.
	 * @param array<string, mixed> $preset   Preset record with 'variant' key.
	 * @return string|\WP_Error
	 */
	public function url_from_preset( string $image_id, array $preset ) {
		$variant = $preset['variant'] ?? 'public';
		return $this->url( $image_id, $variant );
	}
}
