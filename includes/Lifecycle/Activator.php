<?php

namespace WooFeedsPro\Lifecycle;

use WooFeedsPro\Feed\FeedEndpoint;
use WooFeedsPro\Scheduling\Scheduler;
use WooFeedsPro\Support\Options;

defined( 'ABSPATH' ) || exit;

final class Activator {
	/**
	 * Prepare defaults, feed directory, rewrite rules, and scheduled tasks.
	 */
	public static function activate() {
		$options = new Options();
		$options->install_defaults();
		$options->ensure_feed_directory();

		FeedEndpoint::add_rewrite_rules();
		flush_rewrite_rules();

		if ( ! wp_next_scheduled( Scheduler::FEED_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, $options->get( 'schedule', 'daily' ), Scheduler::FEED_HOOK );
		}

		if ( ! wp_next_scheduled( Scheduler::LICENSE_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', Scheduler::LICENSE_HOOK );
		}
	}
}

