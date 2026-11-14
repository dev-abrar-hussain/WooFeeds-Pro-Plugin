<?php
/**
 * Plugin Name: WooFeeds Pro
 * Description: Generates hosted product feeds for Google Merchant Center, Meta Commerce Manager, and ChatGPT Business Hub.
 * Version:     0.1.0
 * Author:      WooFeeds Pro
 * Text Domain: woofeeds-pro
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 */

defined( 'ABSPATH' ) || exit;

define( 'WOOFEEDS_PRO_VERSION', '0.1.0' );
define( 'WOOFEEDS_PRO_FILE', __FILE__ );
define( 'WOOFEEDS_PRO_DIR', plugin_dir_path( __FILE__ ) );
define( 'WOOFEEDS_PRO_URL', plugin_dir_url( __FILE__ ) );

require_once WOOFEEDS_PRO_DIR . 'includes/Autoloader.php';

WooFeedsPro\Autoloader::register();

register_activation_hook( __FILE__, array( 'WooFeedsPro\\Lifecycle\\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WooFeedsPro\\Lifecycle\\Deactivator', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		WooFeedsPro\Plugin::instance()->boot();
	}
);

