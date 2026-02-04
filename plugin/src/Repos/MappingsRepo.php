<?php
/**
 * Mappings repository.
 *
 * @package CloudflareImagesSync
 */

namespace CFI\Repos;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CFI\Support\Ids;
use CFI\Support\Validators;

/**
 * CRUD access to sync mappings (cfi_mappings option).
 */
class MappingsRepo {

	/**
	 * Get all mappings.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function all(): array {
		$raw = get_option( OptionKeys::MAPPINGS, array() );

		if ( ! is_array( $raw ) ) {
			return array();
		}

		return $raw;
	}

	/**
	 * Get a single mapping by ID.
	 *
	 * @param string $id Mapping ID.
	 * @return array<string, mixed>|null
	 */
	public function find( string $id ): ?array {
		$all = $this->all();
		return $all[ $id ] ?? null;
	}

	/**
	 * Get all mappings for a given post type.
	 *
	 * @param string $post_type Post type slug.
	 * @return array<string, array<string, mixed>>
	 */
	public function for_post_type( string $post_type ): array {
		return array_filter(
			$this->all(),
			fn( $m ) => $m['post_type'] === $post_type,
		);
	}

	/**
	 * Create a new mapping.
	 *
	 * @param array<string, mixed> $data Mapping data.
	 * @return string|\WP_Error Mapping ID on success.
	 */
	public function create( array $data ) {
		$data  = Validators::normalize_mapping( $data );
		$valid = Validators::validate_mapping( $data );

		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$id  = Ids::mapping();
		$all = $this->all();

		$data['id']         = $id;
		$data['updated_at'] = time();
		$all[ $id ]         = $data;

		$this->save( $all );

		return $id;
	}

	/**
	 * Update an existing mapping.
	 *
	 * @param string               $id   Mapping ID.
	 * @param array<string, mixed> $data Fields to update.
	 * @return true|\WP_Error
	 */
	public function update( string $id, array $data ) {
		$all = $this->all();

		if ( ! isset( $all[ $id ] ) ) {
			return new \WP_Error( 'cfi_mapping_not_found', 'Mapping not found.' );
		}

		// Merge with existing, then normalize.
		$merged = array_merge( $all[ $id ], $data );
		$merged = Validators::normalize_mapping( $merged );

		$valid = Validators::validate_mapping( $merged );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$merged['id']         = $id;
		$merged['updated_at'] = time();
		$all[ $id ]           = $merged;

		$this->save( $all );

		return true;
	}

	/**
	 * Delete a mapping.
	 *
	 * @param string $id Mapping ID.
	 * @return true|\WP_Error
	 */
	public function delete( string $id ) {
		$all = $this->all();

		if ( ! isset( $all[ $id ] ) ) {
			return new \WP_Error( 'cfi_mapping_not_found', 'Mapping not found.' );
		}

		unset( $all[ $id ] );
		$this->save( $all );

		return true;
	}

	/**
	 * Persist mappings to the database.
	 *
	 * @param array<string, array<string, mixed>> $all All mappings.
	 * @return void
	 */
	private function save( array $all ): void {
		update_option( OptionKeys::MAPPINGS, $all, false );
	}
}
