<?php

namespace WooFeedsPro\Scheduling;

use WooFeedsPro\Feed\FeedManager;
use WooFeedsPro\Licensing\LicenseManager;
use WooFeedsPro\Support\Options;

defined( 'ABSPATH' ) || exit;

final class Scheduler {
	const FEED_HOOK    = 'woofeeds_pro_generate_feeds';
	const LICENSE_HOOK = 'woofeeds_pro_validate_license';

	/** @var Options */
	private $options;
	/** @var FeedManager */
	private $manager;
	/** @var LicenseManager */
	private $license;

	public function __construct( Options $options, FeedManager $manager, LicenseManager $license ) {
		$this->options = $options;
		$this->manager = $manager;
		$this->license = $license;
	}

	public function register() {
		add_action( self::FEED_HOOK, array( $this, 'generate' ) );
		add_action( self::LICENSE_HOOK, array( $this->license, 'scheduled_validation' ) );
		add_action( 'update_option_' . Options::OPTION, array( $this, 'reschedule' ), 10, 2 );
	}

	public function generate() {
		$this->manager->generate_all( true );
	}

	public function reschedule( $old_value, $new_value ) {
		$old_schedule = is_array( $old_value ) ? ( $old_value['schedule'] ?? 'daily' ) : 'daily';
		$new_schedule = is_array( $new_value ) ? ( $new_value['schedule'] ?? 'daily' ) : 'daily';
		if ( $old_schedule === $new_schedule ) {
			return;
		}
		wp_clear_scheduled_hook( self::FEED_HOOK );
		wp_schedule_event( time() + MINUTE_IN_SECONDS, $new_schedule, self::FEED_HOOK );
	}
}

