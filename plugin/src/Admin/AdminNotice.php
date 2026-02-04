<?php
/**
 * Admin flash notice helper trait.
 *
 * @package CloudflareImagesSync
 */

namespace CFI\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides flash-notice helpers for admin pages (PRG pattern).
 */
trait AdminNotice {

	/**
	 * Store a flash notice in a transient and redirect.
	 *
	 * @param string $url     Redirect URL.
	 * @param string $message Notice message.
	 * @param string $type    Notice type: 'success' or 'error'.
	 * @return void
	 */
	protected function redirect_with_notice( string $url, string $message, string $type = 'success' ): void {
		set_transient(
			'cfi_admin_notice_' . get_current_user_id(),
			array(
				'message' => $message,
				'type'    => $type,
			),
			30
		);

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Render the flash notice if one exists.
	 *
	 * @return void
	 */
	protected function render_notice(): void {
		$key    = 'cfi_admin_notice_' . get_current_user_id();
		$notice = get_transient( $key );

		if ( ! $notice ) {
			return;
		}

		delete_transient( $key );

		$class = $notice['type'] === 'error' ? 'notice-error' : 'notice-success';

		printf(
			'<div class="notice %s is-dismissible"><p>%s</p></div>',
			esc_attr( $class ),
			esc_html( $notice['message'] )
		);
	}
}
