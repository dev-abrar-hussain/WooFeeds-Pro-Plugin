<?php

namespace WooFeedsPro\Support;

defined( 'ABSPATH' ) || exit;

final class Options {
	const OPTION = 'woofeeds_pro_settings';

	/**
	 * Complete defaults for a new installation.
	 *
	 * @return array<string,mixed>
	 */
	public function defaults() {
		return array(
			'enabled_feeds'            => array(
				'google'  => true,
				'meta'    => true,
				'chatgpt' => true,
			),
			'schedule'                 => 'daily',
			'batch_size'               => 100,
			'include_variations'       => true,
			'google_product_category'  => '',
			'fb_product_category'      => '',
			'seller_name'              => get_bloginfo( 'name' ),
			'store_country'            => $this->woocommerce_country(),
			'target_countries'         => array( $this->woocommerce_country() ),
			'field_mappings'           => array(
				'brand' => 'attribute:pa_brand',
				'gtin'  => 'meta:_global_unique_id',
				'mpn'   => 'meta:_mpn',
			),
			'license_api_url'          => '',
			'purchase_url'             => '',
			'license_grace_days'       => 7,
			'remove_data_on_uninstall' => false,
		);
	}

	/**
	 * Install default values without overwriting existing choices.
	 */
	public function install_defaults() {
		$existing = get_option( self::OPTION, array() );
		update_option( self::OPTION, array_replace_recursive( $this->defaults(), is_array( $existing ) ? $existing : array() ), false );
	}

	/**
	 * Read all settings merged with defaults.
	 *
	 * @return array<string,mixed>
	 */
	public function all() {
		$value = get_option( self::OPTION, array() );
		return array_replace_recursive( $this->defaults(), is_array( $value ) ? $value : array() );
	}

	/**
	 * Read a setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback value.
	 * @return mixed
	 */
	public function get( $key, $default = null ) {
		$settings = $this->all();
		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
	}

	/**
	 * Save sanitized settings.
	 *
	 * @param array<string,mixed> $input Submitted settings.
	 */
	public function save( array $input ) {
		$current = $this->all();
		$clean   = $current;

		$clean['enabled_feeds'] = array(
			'google'  => ! empty( $input['enabled_feeds']['google'] ),
			'meta'    => ! empty( $input['enabled_feeds']['meta'] ),
			'chatgpt' => ! empty( $input['enabled_feeds']['chatgpt'] ),
		);

		$allowed_schedules = array( 'hourly', 'twicedaily', 'daily' );
		$clean['schedule'] = in_array( $input['schedule'] ?? '', $allowed_schedules, true ) ? $input['schedule'] : 'daily';
		$clean['batch_size'] = min( 500, max( 10, absint( $input['batch_size'] ?? 100 ) ) );
		$clean['include_variations'] = ! empty( $input['include_variations'] );
		$clean['google_product_category'] = sanitize_text_field( $input['google_product_category'] ?? '' );
		$clean['fb_product_category'] = sanitize_text_field( $input['fb_product_category'] ?? '' );
		$clean['seller_name'] = sanitize_text_field( $input['seller_name'] ?? get_bloginfo( 'name' ) );
		$clean['store_country'] = strtoupper( substr( sanitize_text_field( $input['store_country'] ?? '' ), 0, 2 ) );
		$clean['target_countries'] = $this->sanitize_countries( $input['target_countries'] ?? array() );
		$clean['license_api_url'] = esc_url_raw( untrailingslashit( $input['license_api_url'] ?? '' ) );
		$clean['purchase_url'] = esc_url_raw( $input['purchase_url'] ?? '' );
		$clean['license_grace_days'] = min( 30, max( 1, absint( $input['license_grace_days'] ?? 7 ) ) );
		$clean['remove_data_on_uninstall'] = ! empty( $input['remove_data_on_uninstall'] );

		foreach ( array( 'brand', 'gtin', 'mpn' ) as $field ) {
			$source = sanitize_text_field( $input['field_mappings'][ $field ] ?? '' );
			$clean['field_mappings'][ $field ] = preg_match( '/^(attribute|meta|property):[A-Za-z0-9_\-]+$/', $source ) ? $source : '';
		}

		update_option( self::OPTION, $clean, false );
	}

	/**
	 * Absolute feed directory path.
	 *
	 * @return string
	 */
	public function feed_directory() {
		$uploads = wp_get_upload_dir();
		return trailingslashit( $uploads['basedir'] ) . 'wc-feeds';
	}

	/**
	 * Ensure the private generation directory exists.
	 *
	 * @return bool
	 */
	public function ensure_feed_directory() {
		$directory = $this->feed_directory();
		return is_dir( $directory ) || wp_mkdir_p( $directory );
	}

	/**
	 * Resolve the WooCommerce base country when possible.
	 *
	 * @return string
	 */
	private function woocommerce_country() {
		$location = get_option( 'woocommerce_default_country', 'US' );
		return strtoupper( substr( (string) $location, 0, 2 ) );
	}

	/**
	 * Normalize a country list from either an array or comma-separated input.
	 *
	 * @param mixed $value Raw country list.
	 * @return string[]
	 */
	private function sanitize_countries( $value ) {
		$items = is_array( $value ) ? $value : explode( ',', (string) $value );
		$clean = array();

		foreach ( $items as $item ) {
			$country = strtoupper( trim( sanitize_text_field( $item ) ) );
			if ( preg_match( '/^[A-Z]{2}$/', $country ) ) {
				$clean[] = $country;
			}
		}

		return array_values( array_unique( $clean ) );
	}
}

