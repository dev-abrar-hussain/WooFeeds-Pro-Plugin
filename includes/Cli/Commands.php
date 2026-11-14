<?php

namespace WooFeedsPro\Cli;

use WooFeedsPro\Feed\FeedManager;
use WooFeedsPro\Feed\Validator;
use WooFeedsPro\Licensing\LicenseManager;

defined( 'ABSPATH' ) || exit;

/**
 * WP-CLI commands for real-cron and operational workflows.
 */
final class Commands {
	/** @var FeedManager */
	private $manager;
	/** @var Validator */
	private $validator;
	/** @var LicenseManager */
	private $license;

	public function __construct( FeedManager $manager, Validator $validator, LicenseManager $license ) {
		$this->manager   = $manager;
		$this->validator = $validator;
		$this->license   = $license;
	}

	/**
	 * Generate every enabled feed immediately.
	 *
	 * ## EXAMPLES
	 *
	 *     wp woofeeds generate
	 */
	public function generate() {
		$result = $this->manager->generate_all( true );
		if ( is_wp_error( $result ) ) {
			\WP_CLI::error( $result->get_error_message() );
		}
		foreach ( $result as $key => $feed ) {
			\WP_CLI::log( sprintf( '%s: %d products -> %s', $key, $feed['product_count'], $feed['filename'] ) );
		}
		\WP_CLI::success( 'Enabled feeds generated.' );
	}

	/**
	 * Validate mapped product data and print issue counters.
	 */
	public function validate() {
		$result = $this->validator->run();
		\WP_CLI::log( sprintf( 'Products checked: %d', $result['total'] ) );
		foreach ( $result['issues'] as $issue => $count ) {
			\WP_CLI::log( sprintf( '%s: %d', $issue, $count ) );
		}
		$result['passed'] ? \WP_CLI::success( 'Catalog validation passed.' ) : \WP_CLI::warning( 'Catalog validation found issues.' );
	}

	/**
	 * Revalidate the current license against the license server.
	 */
	public function license_status() {
		$result = $this->license->validate();
		if ( is_wp_error( $result ) ) {
			\WP_CLI::error( $result->get_error_message() );
		}
		\WP_CLI::success( sprintf( 'License status: %s', $result['status'] ) );
	}
}

