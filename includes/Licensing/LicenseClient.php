<?php

namespace WooFeedsPro\Licensing;

use WooFeedsPro\Support\Options;

defined( 'ABSPATH' ) || exit;

final class LicenseClient {
	/** @var Options */
	private $options;

	public function __construct( Options $options ) {
		$this->options = $options;
	}

	/**
	 * Call a license API operation.
	 *
	 * @param string              $operation API action.
	 * @param array<string,mixed> $payload   Request data.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function request( $operation, array $payload ) {
		$base = untrailingslashit( (string) $this->options->get( 'license_api_url', '' ) );
		if ( ! $base ) {
			return new \WP_Error( 'woofeeds_license_unconfigured', __( 'The license server URL has not been configured.', 'woofeeds-pro' ) );
		}

		$scheme = wp_parse_url( $base, PHP_URL_SCHEME );
		$host   = wp_parse_url( $base, PHP_URL_HOST );
		if ( 'https' !== $scheme && ! in_array( $host, array( 'localhost', '127.0.0.1' ), true ) ) {
			return new \WP_Error( 'woofeeds_license_insecure', __( 'The license server must use HTTPS.', 'woofeeds-pro' ) );
		}

		$response = wp_safe_remote_post(
			$base . '/v1/licenses/' . $operation,
			array(
				'timeout'     => 15,
				'redirection' => 0,
				'headers'     => array(
					'Accept'       => 'application/json',
					'Content-Type' => 'application/json',
					'User-Agent'   => 'WooFeeds-Pro/' . WOOFEEDS_PRO_VERSION . '; ' . home_url( '/' ),
				),
				'body'        => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'woofeeds_license_offline', $response->get_error_message() );
		}

		$status = wp_remote_retrieve_response_code( $response );
		$data   = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) ) {
			return new \WP_Error( 'woofeeds_license_response', __( 'The license server returned an unreadable response.', 'woofeeds-pro' ) );
		}

		if ( $status < 200 || $status >= 300 || empty( $data['success'] ) ) {
			$code    = sanitize_key( $data['code'] ?? 'license_rejected' );
			$message = sanitize_text_field( $data['message'] ?? __( 'The license request was rejected.', 'woofeeds-pro' ) );
			return new \WP_Error( 'woofeeds_' . $code, $message, array( 'http_status' => $status, 'response' => $data ) );
		}

		return $data;
	}
}

