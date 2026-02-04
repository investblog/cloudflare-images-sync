<?php
/**
 * Centralized option key constants.
 *
 * @package CloudflareImagesSync
 */

namespace CFI\Repos;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Option keys used by the plugin.
 */
final class OptionKeys {

	public const SETTINGS = 'cfi_settings';
	public const PRESETS  = 'cfi_presets';
	public const MAPPINGS = 'cfi_mappings';
	public const LOGS     = 'cfi_logs';

	/**
	 * Attachment meta keys (Preview Studio cache).
	 */
	public const META_PREVIEW_IMAGE_ID = 'cfi_preview_image_id';
	public const META_PREVIEW_SIG      = 'cfi_preview_sig';

	/**
	 * Attachment meta keys (sync engine cache).
	 */
	public const META_CF_IMAGE_ID = '_cfi_cf_image_id';
	public const META_SIG         = '_cfi_sig';

	/**
	 * Demo image option keys (Preview sample image cache).
	 */
	public const DEMO_IMAGE_ID = 'cfi_demo_image_id';
	public const DEMO_SIG      = 'cfi_demo_sig';
	public const DEMO_UPDATED  = 'cfi_demo_updated_at';
}
