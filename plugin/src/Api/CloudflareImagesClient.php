<?php
/**
 * Cloudflare Images API client.
 *
 * @package CloudflareImagesSync
 */

namespace CFI\Api;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * HTTP client for Cloudflare Images API v1.
 *
 * Uses wp_remote_* functions for all HTTP communication.
 * Never exposes api_token in return values or logs.
 */
class CloudflareImagesClient {

	private const API_BASE = 'https://api.cloudflare.com/client/v4/accounts';

	/**
	 * Cloudflare account ID.
	 *
	 * @var string
	 */
	private string $account_id;

	/**
	 * Cloudflare API bearer token.
	 *
	 * @var string
	 */
	private string $api_token;

	/**
	 * Constructor.
	 *
	 * @param string $account_id Cloudflare account ID.
	 * @param string $api_token  Cloudflare API token.
	 */
	public function __construct( string $account_id, string $api_token ) {
		$this->account_id = $account_id;
		$this->api_token  = $api_token;
	}

	/**
	 * Create from current plugin settings.
	 *
	 * @return self|\WP_Error Client instance or error if not configured.
	 */
	public static function from_settings() {
		$repo     = new \CFI\Repos\SettingsRepo();
		$settings = $repo->get();

		if ( $settings['account_id'] === '' || $settings['api_token'] === '' ) {
			return new \WP_Error(
				'cfi_not_configured',
				'Cloudflare account_id and api_token must be configured in settings.'
			);
		}

		return new self( $settings['account_id'], $settings['api_token'] );
	}

	/**
	 * Upload an image file to Cloudflare Images.
	 *
	 * @param string               $file_path Absolute path to the image file.
	 * @param array<string, mixed> $metadata  Optional metadata key-value pairs.
	 * @return array<string, mixed>|\WP_Error Response with 'id', 'filename', 'variants' on success.
	 */
	public function upload( string $file_path, array $metadata = array() ) {
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return new \WP_Error( 'cfi_file_not_found', 'Image file not found or not readable.' );
		}

		$boundary = wp_generate_password( 24, false );
		$body     = $this->build_multipart_body( $file_path, $metadata, $boundary );

		if ( is_wp_error( $body ) ) {
			return $body;
		}

		$response = wp_remote_post(
			$this->endpoint( '/images/v1' ),
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_token,
					'Content-Type'  => 'multipart/form-data; boundary=' . $boundary,
				),
				'body'    => $body,
				'timeout' => 60,
			)
		);

		return $this->parse_response( $response );
	}

	/**
	 * Upload an image from a URL to Cloudflare Images.
	 *
	 * @param string               $url      Public URL of the image.
	 * @param array<string, mixed> $metadata Optional metadata key-value pairs.
	 * @return array<string, mixed>|\WP_Error Response with 'id', 'filename', 'variants' on success.
	 */
	public function upload_from_url( string $url, array $metadata = array() ) {
		$boundary = wp_generate_password( 24, false );
		$body     = $this->build_multipart_body_url( $url, $metadata, $boundary );

		$response = wp_remote_post(
			$this->endpoint( '/images/v1' ),
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_token,
					'Content-Type'  => 'multipart/form-data; boundary=' . $boundary,
				),
				'body'    => $body,
				'timeout' => 60,
			)
		);

		return $this->parse_response( $response );
	}

	/**
	 * Delete an image from Cloudflare Images.
	 *
	 * @param string $image_id Cloudflare image ID.
	 * @return true|\WP_Error
	 */
	public function delete( string $image_id ) {
		if ( $image_id === '' ) {
			return new \WP_Error( 'cfi_missing_image_id', 'Image ID is required.' );
		}

		$response = wp_remote_request(
			$this->endpoint( '/images/v1/' . rawurlencode( $image_id ) ),
			array(
				'method'  => 'DELETE',
				'headers' => $this->auth_headers(),
				'timeout' => 30,
			)
		);

		$parsed = $this->parse_response( $response );

		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		return true;
	}

	/**
	 * Get image details from Cloudflare Images.
	 *
	 * @param string $image_id Cloudflare image ID.
	 * @return array<string, mixed>|\WP_Error Image details on success.
	 */
	public function get( string $image_id ) {
		if ( $image_id === '' ) {
			return new \WP_Error( 'cfi_missing_image_id', 'Image ID is required.' );
		}

		$response = wp_remote_get(
			$this->endpoint( '/images/v1/' . rawurlencode( $image_id ) ),
			array(
				'headers' => $this->auth_headers(),
				'timeout' => 30,
			)
		);

		return $this->parse_response( $response );
	}

	/**
	 * List images (used for canary connection test).
	 *
	 * @param int $per_page Number of images to return (1 for canary).
	 * @return array<string, mixed>|\WP_Error
	 */
	public function list_images( int $per_page = 1 ) {
		$response = wp_remote_get(
			$this->endpoint( '/images/v2' ) . '?per_page=' . $per_page,
			array(
				'headers' => $this->auth_headers(),
				'timeout' => 15,
			)
		);

		return $this->parse_response( $response );
	}

	/**
	 * Test the connection by listing 1 image.
	 *
	 * @return true|\WP_Error True on success, WP_Error with details on failure.
	 */
	public function test_connection() {
		$result = $this->list_images( 1 );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	/**
	 * Build the full API endpoint URL.
	 *
	 * @param string $path API path after /accounts/{id}.
	 * @return string
	 */
	private function endpoint( string $path ): string {
		return self::API_BASE . '/' . rawurlencode( $this->account_id ) . $path;
	}

	/**
	 * Get authorization headers.
	 *
	 * @return array<string, string>
	 */
	private function auth_headers(): array {
		return array(
			'Authorization' => 'Bearer ' . $this->api_token,
		);
	}

	/**
	 * Build multipart/form-data body for file upload.
	 *
	 * @param string               $file_path Absolute file path.
	 * @param array<string, mixed> $metadata  Metadata pairs.
	 * @param string               $boundary  Multipart boundary.
	 * @return string|\WP_Error
	 */
	private function build_multipart_body( string $file_path, array $metadata, string $boundary ) {
		$file_contents = file_get_contents( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		if ( $file_contents === false ) {
			return new \WP_Error( 'cfi_file_read_error', 'Could not read image file.' );
		}

		$filename = wp_basename( $file_path );
		$mime     = wp_check_filetype( $filename )['type'] ?: 'application/octet-stream';

		$body  = '';
		$body .= '--' . $boundary . "\r\n";
		$body .= 'Content-Disposition: form-data; name="file"; filename="' . $filename . '"' . "\r\n";
		$body .= 'Content-Type: ' . $mime . "\r\n\r\n";
		$body .= $file_contents . "\r\n";

		if ( ! empty( $metadata ) ) {
			$body .= '--' . $boundary . "\r\n";
			$body .= 'Content-Disposition: form-data; name="metadata"' . "\r\n";
			$body .= 'Content-Type: application/json' . "\r\n\r\n";
			$body .= wp_json_encode( $metadata ) . "\r\n";
		}

		$body .= '--' . $boundary . '--' . "\r\n";

		return $body;
	}

	/**
	 * Build multipart/form-data body for URL upload.
	 *
	 * @param string               $url      Image URL.
	 * @param array<string, mixed> $metadata Metadata pairs.
	 * @param string               $boundary Multipart boundary.
	 * @return string
	 */
	private function build_multipart_body_url( string $url, array $metadata, string $boundary ): string {
		$body  = '';
		$body .= '--' . $boundary . "\r\n";
		$body .= 'Content-Disposition: form-data; name="url"' . "\r\n\r\n";
		$body .= $url . "\r\n";

		if ( ! empty( $metadata ) ) {
			$body .= '--' . $boundary . "\r\n";
			$body .= 'Content-Disposition: form-data; name="metadata"' . "\r\n";
			$body .= 'Content-Type: application/json' . "\r\n\r\n";
			$body .= wp_json_encode( $metadata ) . "\r\n";
		}

		$body .= '--' . $boundary . '--' . "\r\n";

		return $body;
	}

	/**
	 * Parse Cloudflare API response.
	 *
	 * @param array|\WP_Error $response wp_remote_* response.
	 * @return array<string, mixed>|\WP_Error Parsed result on success.
	 */
	private function parse_response( $response ) {
		if ( is_wp_error( $response ) ) {
			return new \WP_Error(
				'cfi_http_error',
				'HTTP request failed: ' . $response->get_error_message()
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) ) {
			return new \WP_Error(
				'cfi_invalid_response',
				'Could not parse Cloudflare API response.',
				array( 'http_code' => $code )
			);
		}

		if ( empty( $data['success'] ) ) {
			$errors = $data['errors'] ?? array();
			$msg    = 'Cloudflare API error.';

			if ( ! empty( $errors[0]['message'] ) ) {
				$msg = $errors[0]['message'];
			}

			$error_code = $errors[0]['code'] ?? $code;

			return new \WP_Error(
				'cfi_api_error',
				$msg,
				array(
					'http_code' => $code,
					'cf_code'   => $error_code,
				)
			);
		}

		return $data['result'] ?? array();
	}
}
