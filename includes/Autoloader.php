<?php

namespace WooFeedsPro;

defined( 'ABSPATH' ) || exit;

final class Autoloader {
	/**
	 * Register the PSR-4-like loader used by the plugin.
	 */
	public static function register() {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Load classes from the includes directory.
	 *
	 * @param string $class Fully qualified class name.
	 */
	private static function autoload( $class ) {
		$prefix = __NAMESPACE__ . '\\';

		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$path     = WOOFEEDS_PRO_DIR . 'includes/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
}

