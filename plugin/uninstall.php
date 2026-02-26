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

// Remove all plugin options (current cfimg_ prefix).
delete_option( 'cfimg_settings' );
delete_option( 'cfimg_presets' );
delete_option( 'cfimg_mappings' );
delete_option( 'cfimg_logs' );
delete_option( 'cfimg_demo_image_id' );
delete_option( 'cfimg_demo_sig' );
delete_option( 'cfimg_demo_updated_at' );
delete_option( 'cfimg_api_token_encrypted' );
delete_option( 'cfimg_db_version' );

// TODO: Remove this legacy cfi_ cleanup block after acceptance into the WP.org repository.
// Remove legacy cfi_ options (pre-1.0.6, in case migration did not run).
delete_option( 'cfi_settings' );
delete_option( 'cfi_presets' );
delete_option( 'cfi_mappings' );
delete_option( 'cfi_logs' );
delete_option( 'cfi_demo_image_id' );
delete_option( 'cfi_demo_sig' );
delete_option( 'cfi_demo_updated_at' );
delete_option( 'cfi_api_token_encrypted' );
delete_option( 'cfi_db_version' );

// Remove plugin post meta from all posts (both current and legacy keys).
global $wpdb;
$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.SlowDBQuery.slow_db_query_meta_key
	$wpdb->prepare(
		'DELETE FROM %i WHERE meta_key IN (%s, %s, %s, %s, %s, %s, %s, %s)',
		$wpdb->postmeta,
		'cfimg_preview_image_id',
		'cfimg_preview_sig',
		'_cfimg_cf_image_id',
		'_cfimg_sig',
		'cfi_preview_image_id',
		'cfi_preview_sig',
		'_cfi_cf_image_id',
		'_cfi_sig'
	)
);
