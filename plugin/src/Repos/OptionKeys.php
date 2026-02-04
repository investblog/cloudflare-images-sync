<?php
/**
 * Centralized option key constants.
 *
 * @package CloudflareImagesSync
 */

namespace CFI\Repos;

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
}
