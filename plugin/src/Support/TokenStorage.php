<?php
/**
 * Secure Token Storage.
 *
 * @package CloudflareImagesSync
 */

namespace CFI\Support;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Encrypted storage for API tokens using libsodium or OpenSSL fallback.
 */
class TokenStorage {

	/**
	 * Option key for encrypted token.
	 *
	 * @var string
	 */
	private const OPTION_KEY = 'cfi_api_token_encrypted';

	/**
	 * Check if a token is stored.
	 *
	 * @return bool
	 */
	public function has_token(): bool {
		return ! empty( get_option( self::OPTION_KEY ) );
	}

	/**
	 * Store a token (encrypted).
	 *
	 * @param string $token Plain text API token.
	 * @return bool
	 */
	public function store( string $token ): bool {
		if ( empty( $token ) ) {
			return $this->delete();
		}

		$encrypted = $this->encrypt( $token );

		if ( $encrypted === false ) {
			return false;
		}

		return update_option( self::OPTION_KEY, $encrypted, false );
	}

	/**
	 * Retrieve the token (decrypted).
	 *
	 * @return string Empty string if not set or decryption fails.
	 */
	public function retrieve(): string {
		$encrypted = get_option( self::OPTION_KEY );

		if ( empty( $encrypted ) ) {
			return '';
		}

		$decrypted = $this->decrypt( $encrypted );

		return $decrypted ?? '';
	}

	/**
	 * Delete the stored token.
	 *
	 * @return bool
	 */
	public function delete(): bool {
		return delete_option( self::OPTION_KEY );
	}

	/**
	 * Encrypt a value using libsodium or OpenSSL fallback.
	 *
	 * @param string $plaintext Value to encrypt.
	 * @return string|false Base64 encoded encrypted value.
	 */
	private function encrypt( string $plaintext ): string|false {
		$key = $this->get_encryption_key();

		// Prefer libsodium (PHP 7.2+, built-in).
		if ( function_exists( 'sodium_crypto_secretbox' ) ) {
			$nonce      = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$ciphertext = sodium_crypto_secretbox( $plaintext, $nonce, $key );

			// Zero out plaintext from memory.
			sodium_memzero( $plaintext );

			return base64_encode( $nonce . $ciphertext ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		}

		// Fallback: OpenSSL with HMAC.
		if ( function_exists( 'openssl_encrypt' ) ) {
			$iv         = random_bytes( 16 );
			$ciphertext = openssl_encrypt( $plaintext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );

			if ( $ciphertext === false ) {
				return false;
			}

			$hmac = hash_hmac( 'sha256', $iv . $ciphertext, $key, true );

			return base64_encode( $iv . $ciphertext . $hmac ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		}

		return false;
	}

	/**
	 * Decrypt a value.
	 *
	 * @param string $encrypted Base64 encoded encrypted value.
	 * @return string|null
	 */
	private function decrypt( string $encrypted ): ?string {
		$key  = $this->get_encryption_key();
		$data = base64_decode( $encrypted, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if ( $data === false ) {
			return null;
		}

		// Try libsodium first.
		if ( function_exists( 'sodium_crypto_secretbox_open' ) ) {
			if ( strlen( $data ) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES ) {
				return null;
			}

			$nonce      = substr( $data, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$ciphertext = substr( $data, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$plaintext  = sodium_crypto_secretbox_open( $ciphertext, $nonce, $key );

			if ( $plaintext === false ) {
				return null;
			}

			return $plaintext;
		}

		// Fallback: OpenSSL.
		if ( function_exists( 'openssl_decrypt' ) ) {
			// Extract IV (16) + ciphertext + HMAC (32).
			if ( strlen( $data ) < 48 ) { // 16 + 32 minimum.
				return null;
			}

			$iv         = substr( $data, 0, 16 );
			$hmac       = substr( $data, -32 );
			$ciphertext = substr( $data, 16, -32 );

			// Verify HMAC.
			$expected_hmac = hash_hmac( 'sha256', $iv . $ciphertext, $key, true );
			if ( ! hash_equals( $expected_hmac, $hmac ) ) {
				return null;
			}

			$plaintext = openssl_decrypt( $ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );

			if ( $plaintext === false ) {
				return null;
			}

			return $plaintext;
		}

		return null;
	}

	/**
	 * Get the encryption key derived from WP salts.
	 *
	 * @return string 32-byte key.
	 */
	private function get_encryption_key(): string {
		$material = wp_salt( 'auth' ) . wp_salt( 'secure_auth' );

		if ( function_exists( 'sodium_crypto_generichash' ) ) {
			return sodium_crypto_generichash( $material, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
		}

		// Fallback: SHA-256.
		return hash( 'sha256', $material, true );
	}
}
