<?php

namespace WooFeedsPro\Feed;

use WooFeedsPro\Licensing\LicenseManager;
use WooFeedsPro\Support\Logger;
use WooFeedsPro\Support\Options;

defined( 'ABSPATH' ) || exit;

final class FeedManager {
	const STATUS_OPTION = 'woofeeds_pro_feed_status';
	const LOCK_KEY      = 'woofeeds_pro_generation_lock';

	/** @var Options */
	private $options;
	/** @var ProductRepository */
	private $repository;
	/** @var ProductDataMapper */
	private $mapper;
	/** @var LicenseManager */
	private $license;

	public function __construct( Options $options, ProductRepository $repository, ProductDataMapper $mapper, LicenseManager $license ) {
		$this->options    = $options;
		$this->repository = $repository;
		$this->mapper     = $mapper;
		$this->license    = $license;
	}

	/**
	 * Generate all enabled feeds.
	 *
	 * @param bool $force Ignore the stale marker.
	 * @return array<string,array<string,mixed>>|\WP_Error
	 */
	public function generate_all( $force = false ) {
		if ( ! $this->license->allows_generation() ) {
			return new \WP_Error( 'woofeeds_license_required', __( 'Activate a valid WooFeeds Pro license before generating feeds.', 'woofeeds-pro' ) );
		}

		$status = $this->status();
		if ( ! $force && empty( $status['stale'] ) ) {
			return $status['feeds'];
		}

		if ( ! $this->options->ensure_feed_directory() ) {
			return new \WP_Error( 'woofeeds_directory', __( 'The feed directory could not be created or written.', 'woofeeds-pro' ) );
		}

		if ( ! $this->acquire_lock() ) {
			return new \WP_Error( 'woofeeds_generation_busy', __( 'Feed generation is already running. Please try again shortly.', 'woofeeds-pro' ) );
		}

		$enabled = (array) $this->options->get( 'enabled_feeds', array() );
		$results = array();

		try {
			foreach ( $this->generators() as $generator ) {
				if ( empty( $enabled[ $generator->key() ] ) ) {
					continue;
				}
				$results[ $generator->key() ] = $this->generate( $generator );
			}

			update_option(
				self::STATUS_OPTION,
				array(
					'stale'        => false,
					'last_attempt' => time(),
					'last_success' => time(),
					'last_error'   => '',
					'feeds'        => $results,
				),
				false
			);
		} catch ( \Throwable $error ) {
			Logger::error( 'Feed generation failed.', array( 'error' => $error->getMessage() ) );
			update_option(
				self::STATUS_OPTION,
				array(
					'stale'        => true,
					'last_attempt' => time(),
					'last_success' => $status['last_success'],
					'last_error'   => $error->getMessage(),
					'feeds'        => $status['feeds'],
				),
				false
			);
			return new \WP_Error( 'woofeeds_generation_failed', $error->getMessage() );
		} finally {
			delete_option( self::LOCK_KEY );
		}

		return $results;
	}

	/**
	 * Mark generated snapshots stale after a catalog change.
	 */
	public function mark_stale() {
		$status          = $this->status();
		$status['stale'] = true;
		update_option( self::STATUS_OPTION, $status, false );
	}

	/**
	 * Mark stale only when the deleted post was a product.
	 *
	 * @param int $post_id Post ID.
	 */
	public function maybe_mark_product_stale( $post_id ) {
		if ( in_array( get_post_type( $post_id ), array( 'product', 'product_variation' ), true ) ) {
			$this->mark_stale();
		}
	}

	/** @return array<string,mixed> */
	public function status() {
		$value = get_option( self::STATUS_OPTION, array() );
		return wp_parse_args(
			is_array( $value ) ? $value : array(),
			array(
				'stale'        => true,
				'last_attempt' => 0,
				'last_success' => 0,
				'last_error'   => '',
				'feeds'        => array(),
			)
		);
	}

	/** @return AbstractFeedGenerator|null */
	public function generator( $key ) {
		foreach ( $this->generators() as $generator ) {
			if ( $key === $generator->key() ) {
				return $generator;
			}
		}
		return null;
	}

	/** @return string */
	public function path( AbstractFeedGenerator $generator ) {
		return trailingslashit( $this->options->feed_directory() ) . $generator->filename();
	}

	/** @return string */
	public function url( AbstractFeedGenerator $generator ) {
		return home_url( '/wc-feeds/' . $generator->filename() );
	}

	/** @return AbstractFeedGenerator[] */
	public function generators() {
		return array( new GoogleMerchantFeed(), new MetaCommerceFeed(), new ChatGptFeed() );
	}

	/** @return \Generator<int,array<string,mixed>> */
	private function records() {
		foreach ( $this->repository->iterate() as $product ) {
			yield $this->mapper->map( $product );
		}
	}

	/** @return array<string,mixed> */
	private function generate( AbstractFeedGenerator $generator ) {
		$target = $this->path( $generator );
		$temp   = $target . '.tmp-' . wp_generate_uuid4();

		try {
			$count = $generator->write( $this->records(), $temp );
			if ( ! is_file( $temp ) || 0 === filesize( $temp ) ) {
				throw new \RuntimeException( 'The generated feed file is empty.' );
			}

			if ( ! @rename( $temp, $target ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				$backup = $target . '.bak-' . wp_generate_uuid4();
				if ( is_file( $target ) && ! @rename( $target, $backup ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
					throw new \RuntimeException( 'The previous feed could not be prepared for replacement.' );
				}
				if ( ! @rename( $temp, $target ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
					if ( is_file( $backup ) ) {
						@rename( $backup, $target ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
					}
					throw new \RuntimeException( 'The completed feed could not be published.' );
				}
				if ( is_file( $backup ) ) {
					wp_delete_file( $backup );
				}
			}
			chmod( $target, 0644 );
		} catch ( \Throwable $error ) {
			if ( is_file( $temp ) ) {
				wp_delete_file( $temp );
			}
			throw $error;
		}

		return array(
			'filename'     => $generator->filename(),
			'url'          => $this->url( $generator ),
			'product_count'=> $count,
			'bytes'        => filesize( $target ),
			'generated_at' => time(),
		);
	}

	/**
	 * Acquire an atomic database-backed generation lock.
	 *
	 * @return bool
	 */
	private function acquire_lock() {
		if ( add_option( self::LOCK_KEY, time(), '', 'no' ) ) {
			return true;
		}

		$started = (int) get_option( self::LOCK_KEY, 0 );
		if ( ! $started || $started < time() - ( 30 * MINUTE_IN_SECONDS ) ) {
			delete_option( self::LOCK_KEY );
			return add_option( self::LOCK_KEY, time(), '', 'no' );
		}

		return false;
	}
}

