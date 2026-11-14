<?php

namespace WooFeedsPro\Feed;

defined( 'ABSPATH' ) || exit;

final class FeedEndpoint {
	/** @var FeedManager */
	private $manager;

	public function __construct( FeedManager $manager ) {
		$this->manager = $manager;
	}

	public function register() {
		add_action( 'init', array( __CLASS__, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'template_redirect', array( $this, 'serve' ), 0 );
	}

	public static function add_rewrite_rules() {
		add_rewrite_rule( '^wc-feeds/(google-merchant\.xml|meta-commerce\.xml|chatgpt-products\.jsonl\.gz)$', 'index.php?woofeeds_file=$matches[1]', 'top' );
	}

	public function query_vars( $vars ) {
		$vars[] = 'woofeeds_file';
		return $vars;
	}

	public function serve() {
		$requested = get_query_var( 'woofeeds_file' );
		if ( ! $requested ) {
			return;
		}

		$generator = null;
		foreach ( $this->manager->generators() as $candidate ) {
			if ( $candidate->filename() === $requested ) {
				$generator = $candidate;
				break;
			}
		}

		if ( ! $generator ) {
			status_header( 404 );
			exit;
		}

		$path = $this->manager->path( $generator );
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			status_header( 404 );
			header( 'Content-Type: text/plain; charset=UTF-8' );
			echo esc_html__( 'This feed has not been generated yet.', 'woofeeds-pro' );
			exit;
		}

		$modified = filemtime( $path );
		$etag     = '"' . md5( $modified . ':' . filesize( $path ) ) . '"';
		if ( isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) && $etag === sanitize_text_field( wp_unslash( $_SERVER['HTTP_IF_NONE_MATCH'] ) ) ) {
			status_header( 304 );
			exit;
		}

		nocache_headers();
		header_remove( 'Pragma' );
		header( 'Content-Type: ' . $generator->content_type() );
		header( 'Content-Length: ' . filesize( $path ) );
		header( 'ETag: ' . $etag );
		header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', $modified ) . ' GMT' );
		header( 'Cache-Control: public, max-age=3600, stale-while-revalidate=86400' );
		header( 'X-Content-Type-Options: nosniff' );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_readfile
		exit;
	}
}

