<?php
/**
 * Centralized option key constants.
 *
 * @package CloudflareImagesSync
 */

namespace CFIMG\Repos;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Option keys used by the plugin.
 */
final class OptionKeys {

	public const SETTINGS = 'cfimg_settings';
	public const PRESETS  = 'cfimg_presets';
	public const MAPPINGS = 'cfimg_mappings';
	public const LOGS     = 'cfimg_logs';

	/**
	 * Attachment meta keys (Preview Studio cache).
	 */
	public const META_PREVIEW_IMAGE_ID = 'cfimg_preview_image_id';
	public const META_PREVIEW_SIG      = 'cfimg_preview_sig';

	/**
	 * Attachment meta keys (sync engine cache).
	 */
	public const META_CF_IMAGE_ID = '_cfimg_cf_image_id';
	public const META_SIG         = '_cfimg_sig';

	/**
	 * Demo image option keys (Preview sample image cache).
	 */
	public const DEMO_IMAGE_ID = 'cfimg_demo_image_id';
	public const DEMO_SIG      = 'cfimg_demo_sig';
	public const DEMO_UPDATED  = 'cfimg_demo_updated_at';
}
