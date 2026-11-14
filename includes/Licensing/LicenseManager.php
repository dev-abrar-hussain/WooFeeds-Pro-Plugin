<?php

namespace WooFeedsPro\Licensing;

use WooFeedsPro\Support\Options;

defined( 'ABSPATH' ) || exit;

final class LicenseManager {
	const OPTION = 'woofeeds_pro_license';

	/** @var Options */
	private $options;
	/** @var LicenseClient */
	private $client;

	public function __construct( Options $options ) {
		$this->options = $options;
		$this->client  = new LicenseClient( $options );
	}

	public function register() {
		add_filter( 'plugin_action_links_' . plugin_basename( WOOFEEDS_PRO_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Activate and bind a key to this normalized domain.
	 *
	 * @param string $key License key.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function activate( $key ) {
		$key = strtoupper( trim( sanitize_text_field( $key ) ) );
		if ( ! preg_match( '/^[A-Z0-9-]{16,80}$/', $key ) ) {
			return new \WP_Error( 'woofeeds_license_format', __( 'Enter a valid license key.', 'woofeeds-pro' ) );
		}

		$result = $this->client->request( 'activate', $this->payload( $key ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$state = array(
			'key'          => $key,
			'status'       => 'active',
			'domain'       => $this->domain(),
			'activated_at' => time(),
			'last_success' => time(),
			'last_attempt' => time(),
			'expires_at'   => isset( $result['license']['expires_at'] ) ? sanitize_text_field( $result['license']['expires_at'] ) : '',
			'message'      => sanitize_text_field( $result['message'] ?? '' ),
		);
		update_option( self::OPTION, $state, false );
		return $state;
	}

	/** @return array<string,mixed>|\WP_Error */
	public function validate() {
		$state = $this->state();
		if ( empty( $state['key'] ) ) {
			return new \WP_Error( 'woofeeds_license_missing', __( 'No license key is active.', 'woofeeds-pro' ) );
		}

		$result = $this->client->request( 'validate', $this->payload( $state['key'] ) );
		$state['last_attempt'] = time();

		if ( is_wp_error( $result ) ) {
			if ( $this->is_server_failure( $result ) ) {
				$state['status']  = $this->within_grace( $state ) ? 'grace' : 'offline_expired';
				$state['message'] = $result->get_error_message();
			} else {
				$state['status']  = 'invalid';
				$state['message'] = $result->get_error_message();
			}
			update_option( self::OPTION, $state, false );
			return $result;
		}

		$state['status']       = 'active';
		$state['last_success'] = time();
		$state['message']      = sanitize_text_field( $result['message'] ?? '' );
		$state['expires_at']   = isset( $result['license']['expires_at'] ) ? sanitize_text_field( $result['license']['expires_at'] ) : '';
		update_option( self::OPTION, $state, false );
		return $state;
	}

	/** @return bool|\WP_Error */
	public function deactivate() {
		$state = $this->state();
		if ( empty( $state['key'] ) ) {
			return true;
		}
		$result = $this->client->request( 'deactivate', $this->payload( $state['key'] ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		delete_option( self::OPTION );
		return true;
	}

	public function scheduled_validation() {
		if ( $this->state()['key'] ) {
			$this->validate();
		}
	}

	/** @return bool */
	public function allows_generation() {
		if ( defined( 'WOOFEEDS_PRO_DEV_MODE' ) && WOOFEEDS_PRO_DEV_MODE ) {
			return true;
		}

		$state = $this->state();
		if ( 'active' === $state['status'] ) {
			if ( ! empty( $state['last_success'] ) && (int) $state['last_success'] >= time() - ( 48 * HOUR_IN_SECONDS ) ) {
				return true;
			}
			$this->validate();
			$state = $this->state();
		}

		if ( 'grace' === $state['status'] && (int) $state['last_attempt'] < time() - DAY_IN_SECONDS ) {
			$this->validate();
			$state = $this->state();
		}
		if ( 'active' === $state['status'] ) {
			return true;
		}
		return 'grace' === $state['status'] && $this->within_grace( $state );
	}

	/** @return array<string,mixed> */
	public function state() {
		$value = get_option( self::OPTION, array() );
		return wp_parse_args(
			is_array( $value ) ? $value : array(),
			array(
				'key'          => '',
				'status'       => 'inactive',
				'domain'       => $this->domain(),
				'activated_at' => 0,
				'last_success' => 0,
				'last_attempt' => 0,
				'expires_at'   => '',
				'message'      => '',
			)
		);
	}

	/** @return string */
	public function masked_key() {
		$key = $this->state()['key'];
		return $key ? str_repeat( '*', max( 0, strlen( $key ) - 4 ) ) . substr( $key, -4 ) : '';
	}

	/** @return string */
	public function domain() {
		$host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		$host = preg_replace( '/^www\./', '', $host );
		if ( function_exists( 'idn_to_ascii' ) ) {
			$ascii = idn_to_ascii( $host, IDNA_DEFAULT );
			$host  = $ascii ? $ascii : $host;
		}
		return $host;
	}

	public function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=woofeeds-pro&tab=license' ) ) . '">' . esc_html__( 'License', 'woofeeds-pro' ) . '</a>' );
		return $links;
	}

	/** @return array<string,string> */
	private function payload( $key ) {
		return array(
			'license_key'   => (string) $key,
			'domain'        => $this->domain(),
			'site_url'      => home_url( '/' ),
			'plugin_version'=> WOOFEEDS_PRO_VERSION,
		);
	}

	private function within_grace( array $state ) {
		$seconds = absint( $this->options->get( 'license_grace_days', 7 ) ) * DAY_IN_SECONDS;
		return ! empty( $state['last_success'] ) && time() <= (int) $state['last_success'] + $seconds;
	}

	private function is_server_failure( \WP_Error $error ) {
		return in_array(
			$error->get_error_code(),
			array( 'woofeeds_license_offline', 'woofeeds_license_response' ),
			true
		);
	}
}

