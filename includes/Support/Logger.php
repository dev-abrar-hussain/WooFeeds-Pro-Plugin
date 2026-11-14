<?php

namespace WooFeedsPro\Support;

defined( 'ABSPATH' ) || exit;

final class Logger {
	/**
	 * Record an operational error in WooCommerce logs without exposing secrets.
	 *
	 * @param string              $message Error summary.
	 * @param array<string,mixed> $context Safe context values.
	 */
	public static function error( $message, array $context = array() ) {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->error( $message, array_merge( array( 'source' => 'woofeeds-pro' ), $context ) );
		}
	}
}

