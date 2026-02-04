<?php
/**
 * Source resolver — extracts attachment ID and file path from various source types.
 *
 * @package CloudflareImagesSync
 */

namespace CFI\Core;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Given a mapping source config and a post ID, resolve the attachment ID
 * and its local file path.
 */
class SourceResolver {

	/**
	 * Resolved attachment ID (0 if none).
	 *
	 * @var int
	 */
	private int $attachment_id = 0;

	/**
	 * Resolved file path (empty if none).
	 *
	 * @var string
	 */
	private string $file_path = '';

	/**
	 * Whether the source field is empty (no image set).
	 *
	 * @var bool
	 */
	private bool $is_empty = true;

	/**
	 * Resolve source for a given post and mapping source config.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $source  Mapping source config ('type', 'key').
	 * @return self
	 */
	public static function resolve( int $post_id, array $source ): self {
		$instance = new self();
		$type     = $source['type'] ?? '';
		$key      = $source['key'] ?? '';

		switch ( $type ) {
			case 'acf_field':
				$instance->resolve_acf_field( $post_id, $key );
				break;

			case 'featured_image':
				$instance->resolve_featured_image( $post_id );
				break;

			case 'post_meta_attachment_id':
				$instance->resolve_meta_attachment_id( $post_id, $key );
				break;

			case 'post_meta_url':
				$instance->resolve_meta_url( $post_id, $key );
				break;

			case 'attachment_id':
				// Post itself is the attachment.
				$instance->resolve_attachment( $post_id );
				break;
		}

		return $instance;
	}

	/**
	 * Get the resolved attachment ID.
	 *
	 * @return int 0 if not resolved.
	 */
	public function get_attachment_id(): int {
		return $this->attachment_id;
	}

	/**
	 * Get the resolved local file path.
	 *
	 * @return string Empty if not resolved.
	 */
	public function get_file_path(): string {
		return $this->file_path;
	}

	/**
	 * Whether the source field is empty (no image assigned).
	 *
	 * @return bool
	 */
	public function is_empty(): bool {
		return $this->is_empty;
	}

	/**
	 * Resolve ACF image field.
	 *
	 * ACF image fields can return ID, array, or URL depending on return format.
	 * We use get_field() to get the raw value, then normalize.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     ACF field name.
	 */
	private function resolve_acf_field( int $post_id, string $key ): void {
		if ( ! function_exists( 'get_field' ) ) {
			return;
		}

		$value = get_field( $key, $post_id, false );

		if ( empty( $value ) ) {
			return;
		}

		// ACF return format: ID (int or numeric string).
		if ( is_numeric( $value ) ) {
			$this->resolve_attachment( (int) $value );
			return;
		}

		// ACF return format: array with 'ID' key.
		if ( is_array( $value ) && ! empty( $value['ID'] ) ) {
			$this->resolve_attachment( (int) $value['ID'] );
			return;
		}

		// ACF return format: URL string.
		if ( is_string( $value ) ) {
			$this->resolve_url_to_attachment( $value );
		}
	}

	/**
	 * Resolve featured image (post thumbnail).
	 *
	 * @param int $post_id Post ID.
	 */
	private function resolve_featured_image( int $post_id ): void {
		$thumb_id = (int) get_post_thumbnail_id( $post_id );

		if ( $thumb_id > 0 ) {
			$this->resolve_attachment( $thumb_id );
		}
	}

	/**
	 * Resolve attachment ID stored in post meta.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 */
	private function resolve_meta_attachment_id( int $post_id, string $key ): void {
		$value = get_post_meta( $post_id, $key, true );

		if ( ! empty( $value ) && is_numeric( $value ) ) {
			$this->resolve_attachment( (int) $value );
		}
	}

	/**
	 * Resolve image URL stored in post meta.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 */
	private function resolve_meta_url( int $post_id, string $key ): void {
		$value = get_post_meta( $post_id, $key, true );

		if ( ! empty( $value ) && is_string( $value ) ) {
			$this->resolve_url_to_attachment( $value );
		}
	}

	/**
	 * Set attachment data from a known attachment ID.
	 *
	 * @param int $attachment_id Attachment post ID.
	 */
	private function resolve_attachment( int $attachment_id ): void {
		if ( $attachment_id <= 0 ) {
			return;
		}

		if ( get_post_type( $attachment_id ) !== 'attachment' ) {
			return;
		}

		$file = get_attached_file( $attachment_id );

		if ( ! $file || ! file_exists( $file ) ) {
			return;
		}

		$this->attachment_id = $attachment_id;
		$this->file_path     = $file;
		$this->is_empty      = false;
	}

	/**
	 * Try to resolve a URL to a local attachment.
	 *
	 * @param string $url Image URL.
	 */
	private function resolve_url_to_attachment( string $url ): void {
		$attachment_id = attachment_url_to_postid( $url );

		if ( $attachment_id > 0 ) {
			$this->resolve_attachment( $attachment_id );
		}
	}
}
