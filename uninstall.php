<?php
/**
 * WooFeeds Pro uninstall handler.
 *
 * Customer settings and generated feeds are deliberately preserved unless the
 * store owner enabled the explicit "Remove data on uninstall" setting.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$settings = get_option( 'woofeeds_pro_settings', array() );

if ( empty( $settings['remove_data_on_uninstall'] ) ) {
	return;
}

delete_option( 'woofeeds_pro_settings' );
delete_option( 'woofeeds_pro_feed_status' );
delete_option( 'woofeeds_pro_license' );
delete_option( 'woofeeds_pro_validation' );
delete_option( 'woofeeds_pro_generation_lock' );
delete_transient( 'woofeeds_pro_license_check' );

$uploads = wp_get_upload_dir();
$dir     = trailingslashit( $uploads['basedir'] ) . 'wc-feeds';

if ( is_dir( $dir ) && 0 === strpos( wp_normalize_path( $dir ), wp_normalize_path( $uploads['basedir'] ) ) ) {
	$files = glob( trailingslashit( $dir ) . '*' );
	if ( is_array( $files ) ) {
		foreach ( $files as $file ) {
			if ( is_file( $file ) ) {
				wp_delete_file( $file );
			}
		}
	}
	@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
}

