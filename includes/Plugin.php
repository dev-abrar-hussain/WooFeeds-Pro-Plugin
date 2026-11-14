<?php

namespace WooFeedsPro;

use WooFeedsPro\Admin\AdminPage;
use WooFeedsPro\Cli\Commands;
use WooFeedsPro\Feed\FeedEndpoint;
use WooFeedsPro\Feed\FeedManager;
use WooFeedsPro\Feed\ProductDataMapper;
use WooFeedsPro\Feed\ProductRepository;
use WooFeedsPro\Feed\Validator;
use WooFeedsPro\Licensing\LicenseManager;
use WooFeedsPro\Scheduling\Scheduler;
use WooFeedsPro\Support\Options;

defined( 'ABSPATH' ) || exit;

final class Plugin {
	/** @var Plugin|null */
	private static $instance;

	/** @var bool */
	private $booted = false;

	/**
	 * Return the singleton plugin instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register all runtime services.
	 */
	public function boot() {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		load_plugin_textdomain( 'woofeeds-pro', false, dirname( plugin_basename( WOOFEEDS_PRO_FILE ) ) . '/languages' );

		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_notice' ) );
			return;
		}

		$options    = new Options();
		$license    = new LicenseManager( $options );
		$repository = new ProductRepository( $options );
		$mapper     = new ProductDataMapper( $options );
		$manager    = new FeedManager( $options, $repository, $mapper, $license );
		$validator  = new Validator( $repository, $mapper );

		( new Scheduler( $options, $manager, $license ) )->register();
		( new FeedEndpoint( $manager ) )->register();
		( new AdminPage( $options, $manager, $validator, $license ) )->register();
		$license->register();

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'woofeeds', new Commands( $manager, $validator, $license ) );
		}

		add_action( 'woocommerce_update_product', array( $manager, 'mark_stale' ) );
		add_action( 'woocommerce_new_product', array( $manager, 'mark_stale' ) );
		add_action( 'before_delete_post', array( $manager, 'maybe_mark_product_stale' ) );
	}

	/**
	 * Show a dependency notice without crashing the rest of WordPress.
	 */
	public function woocommerce_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'WooFeeds Pro requires WooCommerce to be installed and active.', 'woofeeds-pro' );
		echo '</p></div>';
	}
}

