<?php
/**
 * Presets repository.
 *
 * @package CloudflareImagesSync
 */

namespace CFI\Repos;

use CFI\Support\Ids;
use CFI\Support\Validators;

/**
 * CRUD access to image presets (cfi_presets option).
 */
class PresetsRepo {

	/**
	 * Get all presets.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function all(): array {
		$raw = get_option( OptionKeys::PRESETS, array() );

		if ( ! is_array( $raw ) ) {
			return array();
		}

		return $raw;
	}

	/**
	 * Get a single preset by ID.
	 *
	 * @param string $id Preset ID.
	 * @return array<string, mixed>|null
	 */
	public function find( string $id ): ?array {
		$all = $this->all();
		return $all[ $id ] ?? null;
	}

	/**
	 * Create a new preset.
	 *
	 * @param array<string, mixed> $data Preset data (name, variant).
	 * @return string|\WP_Error Preset ID on success.
	 */
	public function create( array $data ) {
		$valid = Validators::validate_preset( $data );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		// Check name uniqueness (case-insensitive).
		if ( $this->name_exists( $data['name'] ) ) {
			return new \WP_Error( 'cfi_duplicate_preset', 'A preset with this name already exists.' );
		}

		$id  = Ids::preset();
		$all = $this->all();

		$all[ $id ] = array(
			'id'         => $id,
			'name'       => sanitize_text_field( $data['name'] ),
			'variant'    => sanitize_text_field( $data['variant'] ),
			'updated_at' => time(),
		);

		$this->save( $all );

		return $id;
	}

	/**
	 * Update an existing preset.
	 *
	 * @param string               $id   Preset ID.
	 * @param array<string, mixed> $data Fields to update.
	 * @return true|\WP_Error
	 */
	public function update( string $id, array $data ) {
		$all = $this->all();

		if ( ! isset( $all[ $id ] ) ) {
			return new \WP_Error( 'cfi_preset_not_found', 'Preset not found.' );
		}

		// If name is changing, check uniqueness.
		if ( isset( $data['name'] ) && strtolower( $data['name'] ) !== strtolower( $all[ $id ]['name'] ) ) {
			if ( $this->name_exists( $data['name'] ) ) {
				return new \WP_Error( 'cfi_duplicate_preset', 'A preset with this name already exists.' );
			}
		}

		if ( isset( $data['name'] ) ) {
			$all[ $id ]['name'] = sanitize_text_field( $data['name'] );
		}
		if ( isset( $data['variant'] ) ) {
			$all[ $id ]['variant'] = sanitize_text_field( $data['variant'] );
		}

		$all[ $id ]['updated_at'] = time();

		$valid = Validators::validate_preset( $all[ $id ] );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$this->save( $all );

		return true;
	}

	/**
	 * Delete a preset.
	 *
	 * @param string $id Preset ID.
	 * @return true|\WP_Error
	 */
	public function delete( string $id ) {
		$all = $this->all();

		if ( ! isset( $all[ $id ] ) ) {
			return new \WP_Error( 'cfi_preset_not_found', 'Preset not found.' );
		}

		unset( $all[ $id ] );
		$this->save( $all );

		return true;
	}

	/**
	 * Seed default presets if none exist.
	 *
	 * @return void
	 */
	public function seed_defaults(): void {
		$existing = $this->all();

		if ( ! empty( $existing ) ) {
			return;
		}

		$all = array();
		foreach ( Defaults::presets() as $data ) {
			$id          = Ids::preset();
			$all[ $id ]  = array(
				'id'         => $id,
				'name'       => $data['name'],
				'variant'    => $data['variant'],
				'updated_at' => time(),
			);
		}

		$this->save( $all );
	}

	/**
	 * Check if a preset name already exists (case-insensitive).
	 *
	 * @param string $name Preset name to check.
	 * @return bool
	 */
	private function name_exists( string $name ): bool {
		$lower = strtolower( $name );

		foreach ( $this->all() as $preset ) {
			if ( strtolower( $preset['name'] ) === $lower ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Persist presets to the database.
	 *
	 * @param array<string, array<string, mixed>> $all All presets.
	 * @return void
	 */
	private function save( array $all ): void {
		update_option( OptionKeys::PRESETS, $all, false );
	}
}
