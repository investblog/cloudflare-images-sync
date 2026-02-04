<?php
/**
 * Uninstall handler for Cloudflare Images Sync.
 *
 * Fired when the plugin is deleted via the WordPress admin.
 *
 * @package CloudflareImagesSync
 */

// Exit if not called by WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove all plugin options.
delete_option( 'cfi_settings' );
delete_option( 'cfi_presets' );
delete_option( 'cfi_mappings' );
delete_option( 'cfi_logs' );
delete_option( 'cfi_demo_image_id' );
delete_option( 'cfi_demo_sig' );
delete_option( 'cfi_demo_updated_at' );
