<?php

namespace WooFeedsPro\Lifecycle;

use WooFeedsPro\Scheduling\Scheduler;

defined( 'ABSPATH' ) || exit;

final class Deactivator {
	/**
	 * Remove scheduled tasks and rewrite rules; preserve customer data.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( Scheduler::FEED_HOOK );
		wp_clear_scheduled_hook( Scheduler::LICENSE_HOOK );
		flush_rewrite_rules();
	}
}

