<?php

namespace WooFeedsPro\Admin;

use WooFeedsPro\Feed\FeedManager;
use WooFeedsPro\Feed\Validator;
use WooFeedsPro\Licensing\LicenseManager;
use WooFeedsPro\Support\Options;

defined( 'ABSPATH' ) || exit;

final class AdminPage {
	const CAPABILITY        = 'manage_woocommerce';
	const PAGE              = 'woofeeds-pro';
	const VALIDATION_OPTION = 'woofeeds_pro_validation';

	/** @var Options */
	private $options;
	/** @var FeedManager */
	private $manager;
	/** @var Validator */
	private $validator;
	/** @var LicenseManager */
	private $license;

	public function __construct( Options $options, FeedManager $manager, Validator $validator, LicenseManager $license ) {
		$this->options   = $options;
		$this->manager   = $manager;
		$this->validator = $validator;
		$this->license   = $license;
	}

	public function register() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_woofeeds_save', array( $this, 'save' ) );
		add_action( 'admin_post_woofeeds_generate', array( $this, 'generate' ) );
		add_action( 'admin_post_woofeeds_validate', array( $this, 'validate' ) );
		add_action( 'admin_post_woofeeds_license', array( $this, 'license_action' ) );
	}

	public function menu() {
		add_submenu_page(
			'woocommerce',
			__( 'WooFeeds Pro', 'woofeeds-pro' ),
			__( 'WooFeeds Pro', 'woofeeds-pro' ),
			self::CAPABILITY,
			self::PAGE,
			array( $this, 'render' )
		);
	}

	public function assets( $hook ) {
		if ( 'woocommerce_page_' . self::PAGE !== $hook ) {
			return;
		}
		wp_enqueue_style( 'woofeeds-pro-admin', WOOFEEDS_PRO_URL . 'assets/admin.css', array(), WOOFEEDS_PRO_VERSION );
		wp_enqueue_script( 'woofeeds-pro-admin', WOOFEEDS_PRO_URL . 'assets/admin.js', array(), WOOFEEDS_PRO_VERSION, true );
		wp_localize_script(
			'woofeeds-pro-admin',
			'wooFeedsProAdmin',
			array(
				'copied'     => __( 'Feed URL copied.', 'woofeeds-pro' ),
				'copyFailed' => __( 'Copy failed. Select the URL and copy it manually.', 'woofeeds-pro' ),
			)
		);
	}

	public function render() {
		$this->guard();
		$tab  = sanitize_key( wp_unslash( $_GET['tab'] ?? 'dashboard' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tabs = array(
			'dashboard' => __( 'Dashboard', 'woofeeds-pro' ),
			'google'    => __( 'Google Merchant', 'woofeeds-pro' ),
			'meta'      => __( 'Meta Commerce', 'woofeeds-pro' ),
			'chatgpt'   => __( 'ChatGPT', 'woofeeds-pro' ),
			'mapping'   => __( 'Field Mapping', 'woofeeds-pro' ),
			'validation'=> __( 'Validation', 'woofeeds-pro' ),
			'license'   => __( 'License', 'woofeeds-pro' ),
		);
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'dashboard';
		}
		?>
		<div class="wrap woofeeds-wrap">
			<h1><?php esc_html_e( 'WooFeeds Pro', 'woofeeds-pro' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Keep your product catalogs fresh across Google, Meta, and ChatGPT.', 'woofeeds-pro' ); ?></p>
			<?php $this->notice(); ?>
			<nav class="nav-tab-wrapper woofeeds-tabs" aria-label="<?php esc_attr_e( 'WooFeeds Pro sections', 'woofeeds-pro' ); ?>">
				<?php foreach ( $tabs as $key => $label ) : ?>
					<a class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( $this->page_url( $key ) ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>
			<div class="woofeeds-panel">
				<?php
				switch ( $tab ) {
					case 'dashboard': $this->dashboard(); break;
					case 'mapping': $this->mapping(); break;
					case 'validation': $this->validation(); break;
					case 'license': $this->license(); break;
					default: $this->platform( $tab );
				}
				?>
			</div>
		</div>
		<?php
	}

	public function save() {
		$this->guard();
		check_admin_referer( 'woofeeds_save' );
		$settings = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$this->options->save( is_array( $settings ) ? $settings : array() );
		$this->manager->mark_stale();
		$this->set_notice( 'success', __( 'Settings saved. Regenerate the feeds to publish your changes.', 'woofeeds-pro' ) );
		$this->redirect( sanitize_key( wp_unslash( $_POST['return_tab'] ?? 'dashboard' ) ) );
	}

	public function generate() {
		$this->guard();
		check_admin_referer( 'woofeeds_generate' );
		$result = $this->manager->generate_all( true );
		if ( is_wp_error( $result ) ) {
			$this->set_notice( 'error', $result->get_error_message() );
		} else {
			$this->set_notice( 'success', __( 'All enabled feeds were regenerated successfully.', 'woofeeds-pro' ) );
		}
		$this->redirect( 'dashboard' );
	}

	public function validate() {
		$this->guard();
		check_admin_referer( 'woofeeds_validate' );
		try {
			update_option( self::VALIDATION_OPTION, $this->validator->run(), false );
			$this->set_notice( 'success', __( 'Catalog validation is complete.', 'woofeeds-pro' ) );
		} catch ( \Throwable $error ) {
			$this->set_notice( 'error', $error->getMessage() );
		}
		$this->redirect( 'validation' );
	}

	public function license_action() {
		$this->guard();
		check_admin_referer( 'woofeeds_license' );
		$action = sanitize_key( wp_unslash( $_POST['license_action'] ?? '' ) );
		if ( 'activate' === $action ) {
			$result = $this->license->activate( sanitize_text_field( wp_unslash( $_POST['license_key'] ?? '' ) ) );
		} elseif ( 'deactivate' === $action ) {
			$result = $this->license->deactivate();
		} else {
			$result = $this->license->validate();
		}
		if ( is_wp_error( $result ) ) {
			$this->set_notice( 'error', $result->get_error_message() );
		} else {
			$this->set_notice( 'success', __( 'License status updated.', 'woofeeds-pro' ) );
		}
		$this->redirect( 'license' );
	}

	private function dashboard() {
		$status  = $this->manager->status();
		$license = $this->license->state();
		?>
		<div class="woofeeds-grid woofeeds-grid-3">
			<?php $this->card( __( 'Feed status', 'woofeeds-pro' ), $status['last_error'] ? __( 'Needs attention', 'woofeeds-pro' ) : ( $status['stale'] ? __( 'Update pending', 'woofeeds-pro' ) : __( 'Up to date', 'woofeeds-pro' ) ), $status['last_error'] ?: __( 'Static feeds are served without querying products on every request.', 'woofeeds-pro' ) ); ?>
			<?php $this->card( __( 'Last generated', 'woofeeds-pro' ), $status['last_success'] ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $status['last_success'] ) : __( 'Not generated yet', 'woofeeds-pro' ), __( 'Scheduled refreshes run through WP-Cron.', 'woofeeds-pro' ) ); ?>
			<?php $this->card( __( 'License', 'woofeeds-pro' ), ucfirst( str_replace( '_', ' ', $license['status'] ) ), $license['domain'] ); ?>
		</div>
		<h2><?php esc_html_e( 'Published feeds', 'woofeeds-pro' ); ?></h2>
		<div class="woofeeds-feed-list">
			<?php foreach ( $this->manager->generators() as $generator ) : $feed = $status['feeds'][ $generator->key() ] ?? array(); ?>
				<div class="woofeeds-feed-row">
					<div><strong><?php echo esc_html( ucfirst( $generator->key() ) ); ?></strong><br><span><?php echo esc_html( $feed ? sprintf( __( '%d products', 'woofeeds-pro' ), $feed['product_count'] ) : __( 'Not generated', 'woofeeds-pro' ) ); ?></span></div>
					<input type="url" readonly value="<?php echo esc_attr( $this->manager->url( $generator ) ); ?>">
					<button type="button" class="button woofeeds-copy" data-copy="<?php echo esc_attr( $this->manager->url( $generator ) ); ?>"><?php esc_html_e( 'Copy URL', 'woofeeds-pro' ); ?></button>
				</div>
			<?php endforeach; ?>
		</div>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="woofeeds-actions">
			<input type="hidden" name="action" value="woofeeds_generate">
			<?php wp_nonce_field( 'woofeeds_generate' ); ?>
			<?php submit_button( __( 'Regenerate Now', 'woofeeds-pro' ), 'primary', 'submit', false ); ?>
		</form>
		<?php
	}

	private function platform( $platform ) {
		$settings  = $this->options->all();
		$generator = $this->manager->generator( $platform );
		$descriptions = array(
			'google'  => __( 'RSS 2.0 XML for Google Merchant Center.', 'woofeeds-pro' ),
			'meta'    => __( 'Google-style XML accepted by Meta Commerce Manager.', 'woofeeds-pro' ),
			'chatgpt' => __( 'Gzip-compressed JSONL using the stable OpenAI Agentic Commerce product-feed schema.', 'woofeeds-pro' ),
		);
		?>
		<h2><?php echo esc_html( ucfirst( $platform ) ); ?></h2>
		<p><?php echo esc_html( $descriptions[ $platform ] ); ?></p>
		<?php if ( $generator ) : ?>
			<div class="woofeeds-url-box"><code><?php echo esc_html( $this->manager->url( $generator ) ); ?></code><button type="button" class="button woofeeds-copy" data-copy="<?php echo esc_attr( $this->manager->url( $generator ) ); ?>"><?php esc_html_e( 'Copy Feed URL', 'woofeeds-pro' ); ?></button></div>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="woofeeds_save"><input type="hidden" name="return_tab" value="<?php echo esc_attr( $platform ); ?>">
			<?php wp_nonce_field( 'woofeeds_save' ); $this->settings_hidden( $settings, $platform ); ?>
			<table class="form-table" role="presentation"><tbody>
			<tr><th><?php esc_html_e( 'Enable feed', 'woofeeds-pro' ); ?></th><td><label><input type="checkbox" name="settings[enabled_feeds][<?php echo esc_attr( $platform ); ?>]" value="1" <?php checked( ! empty( $settings['enabled_feeds'][ $platform ] ) ); ?>> <?php esc_html_e( 'Generate this feed on schedule', 'woofeeds-pro' ); ?></label></td></tr>
			<?php if ( 'google' === $platform || 'meta' === $platform ) : ?>
			<tr><th><label for="google-category"><?php esc_html_e( 'Google product category', 'woofeeds-pro' ); ?></label></th><td><input id="google-category" class="regular-text" name="settings[google_product_category]" value="<?php echo esc_attr( $settings['google_product_category'] ); ?>"><p class="description"><?php esc_html_e( 'Use the official numeric category ID or full category path.', 'woofeeds-pro' ); ?></p></td></tr>
			<?php endif; ?>
			<?php if ( 'meta' === $platform ) : ?>
			<tr><th><label for="fb-category"><?php esc_html_e( 'Facebook product category', 'woofeeds-pro' ); ?></label></th><td><input id="fb-category" class="regular-text" name="settings[fb_product_category]" value="<?php echo esc_attr( $settings['fb_product_category'] ); ?>"></td></tr>
			<?php endif; ?>
			<?php if ( 'chatgpt' === $platform ) : ?>
			<tr><th><label for="seller-name"><?php esc_html_e( 'Seller name', 'woofeeds-pro' ); ?></label></th><td><input id="seller-name" required class="regular-text" name="settings[seller_name]" value="<?php echo esc_attr( $settings['seller_name'] ); ?>"></td></tr>
			<tr><th><label for="store-country"><?php esc_html_e( 'Store country', 'woofeeds-pro' ); ?></label></th><td><input id="store-country" required maxlength="2" class="small-text" name="settings[store_country]" value="<?php echo esc_attr( $settings['store_country'] ); ?>"> <span class="description"><?php esc_html_e( 'Two-letter code, for example US or PK.', 'woofeeds-pro' ); ?></span></td></tr>
			<tr><th><label for="target-countries"><?php esc_html_e( 'Target countries', 'woofeeds-pro' ); ?></label></th><td><input id="target-countries" class="regular-text" name="settings[target_countries]" value="<?php echo esc_attr( implode( ',', $settings['target_countries'] ) ); ?>"><p class="description"><?php esc_html_e( 'Comma-separated two-letter country codes.', 'woofeeds-pro' ); ?></p></td></tr>
			<?php endif; ?>
			</tbody></table>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	private function mapping() {
		$settings = $this->options->all();
		$sources  = $this->mapping_sources();
		?>
		<h2><?php esc_html_e( 'Field mapping', 'woofeeds-pro' ); ?></h2>
		<p><?php esc_html_e( 'Choose where WooFeeds Pro should read identifiers and brand information.', 'woofeeds-pro' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="woofeeds_save"><input type="hidden" name="return_tab" value="mapping">
			<?php wp_nonce_field( 'woofeeds_save' ); $this->settings_hidden( $settings, 'mapping' ); ?>
			<table class="form-table"><tbody>
			<?php foreach ( array( 'brand' => __( 'Brand', 'woofeeds-pro' ), 'gtin' => __( 'GTIN', 'woofeeds-pro' ), 'mpn' => __( 'MPN', 'woofeeds-pro' ) ) as $key => $label ) : ?>
			<tr><th><label for="mapping-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th><td><select id="mapping-<?php echo esc_attr( $key ); ?>" name="settings[field_mappings][<?php echo esc_attr( $key ); ?>]"><option value=""><?php esc_html_e( 'Not mapped', 'woofeeds-pro' ); ?></option><?php foreach ( $sources as $value => $source_label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['field_mappings'][ $key ], $value ); ?>><?php echo esc_html( $source_label ); ?></option><?php endforeach; ?></select></td></tr>
			<?php endforeach; ?>
			<tr><th><?php esc_html_e( 'Variable products', 'woofeeds-pro' ); ?></th><td><label><input type="checkbox" name="settings[include_variations]" value="1" <?php checked( $settings['include_variations'] ); ?>> <?php esc_html_e( 'Export each purchasable variation as its own feed item', 'woofeeds-pro' ); ?></label></td></tr>
			<tr><th><label for="batch-size"><?php esc_html_e( 'Batch size', 'woofeeds-pro' ); ?></label></th><td><input type="number" id="batch-size" min="10" max="500" name="settings[batch_size]" value="<?php echo esc_attr( $settings['batch_size'] ); ?>"><p class="description"><?php esc_html_e( 'Lower this on memory-limited hosting.', 'woofeeds-pro' ); ?></p></td></tr>
			<tr><th><label for="schedule"><?php esc_html_e( 'Refresh schedule', 'woofeeds-pro' ); ?></label></th><td><select id="schedule" name="settings[schedule]"><option value="hourly" <?php selected( $settings['schedule'], 'hourly' ); ?>><?php esc_html_e( 'Hourly', 'woofeeds-pro' ); ?></option><option value="twicedaily" <?php selected( $settings['schedule'], 'twicedaily' ); ?>><?php esc_html_e( 'Twice daily', 'woofeeds-pro' ); ?></option><option value="daily" <?php selected( $settings['schedule'], 'daily' ); ?>><?php esc_html_e( 'Daily', 'woofeeds-pro' ); ?></option></select></td></tr>
			</tbody></table>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	private function validation() {
		$result = get_option( self::VALIDATION_OPTION, array() );
		$labels = array(
			'missing_title'       => __( 'products are missing a title', 'woofeeds-pro' ),
			'missing_description' => __( 'products are missing a description', 'woofeeds-pro' ),
			'missing_brand'       => __( 'products are missing a brand', 'woofeeds-pro' ),
			'missing_gtin_mpn'    => __( 'products have neither GTIN nor MPN', 'woofeeds-pro' ),
			'invalid_gtin'        => __( 'products have an invalid GTIN checksum or length', 'woofeeds-pro' ),
			'missing_image'       => __( 'products are missing a main image', 'woofeeds-pro' ),
			'broken_image_file'   => __( 'products point to a missing local image file', 'woofeeds-pro' ),
			'invalid_image_url'   => __( 'products have an invalid image URL', 'woofeeds-pro' ),
			'missing_price'       => __( 'products are missing a positive price', 'woofeeds-pro' ),
			'missing_country'     => __( 'products are missing ChatGPT country targeting', 'woofeeds-pro' ),
		);
		?>
		<h2><?php esc_html_e( 'Catalog validation', 'woofeeds-pro' ); ?></h2>
		<p><?php esc_html_e( 'Check the product data before submitting a feed to a channel.', 'woofeeds-pro' ); ?></p>
		<?php if ( $result ) : ?>
			<p><strong><?php echo esc_html( sprintf( __( '%d products checked.', 'woofeeds-pro' ), $result['total'] ) ); ?></strong> <?php echo esc_html( sprintf( __( 'Last checked %s.', 'woofeeds-pro' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $result['checked_at'] ) ) ); ?></p>
			<ul class="woofeeds-validation-list"><?php foreach ( $labels as $key => $label ) : $count = (int) ( $result['issues'][ $key ] ?? 0 ); ?><li class="<?php echo $count ? 'has-issue' : 'is-ok'; ?>"><strong><?php echo esc_html( $count ); ?></strong> <?php echo esc_html( $label ); ?></li><?php endforeach; ?></ul>
		<?php else : ?><p class="woofeeds-empty"><?php esc_html_e( 'No validation has been run yet.', 'woofeeds-pro' ); ?></p><?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="woofeeds_validate"><?php wp_nonce_field( 'woofeeds_validate' ); submit_button( __( 'Run Validation', 'woofeeds-pro' ), 'primary', 'submit', false ); ?></form>
		<?php
	}

	private function license() {
		$settings = $this->options->all();
		$state    = $this->license->state();
		?>
		<h2><?php esc_html_e( 'License', 'woofeeds-pro' ); ?></h2>
		<div class="woofeeds-license-status"><span class="woofeeds-status woofeeds-status-<?php echo esc_attr( $state['status'] ); ?>"><?php echo esc_html( ucfirst( str_replace( '_', ' ', $state['status'] ) ) ); ?></span><p><?php echo esc_html( sprintf( __( 'Domain: %s', 'woofeeds-pro' ), $state['domain'] ) ); ?></p><?php if ( $state['message'] ) : ?><p><?php echo esc_html( $state['message'] ); ?></p><?php endif; ?></div>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="woofeeds-license-form"><input type="hidden" name="action" value="woofeeds_license"><?php wp_nonce_field( 'woofeeds_license' ); ?>
		<?php if ( $state['key'] ) : ?><p><label><?php esc_html_e( 'License key', 'woofeeds-pro' ); ?> <input readonly class="regular-text" value="<?php echo esc_attr( $this->license->masked_key() ); ?>"></label></p><button class="button button-primary" name="license_action" value="refresh"><?php esc_html_e( 'Check Now', 'woofeeds-pro' ); ?></button> <button class="button" name="license_action" value="deactivate"><?php esc_html_e( 'Deactivate', 'woofeeds-pro' ); ?></button>
		<?php else : ?><p><label><?php esc_html_e( 'License key', 'woofeeds-pro' ); ?> <input required class="regular-text" name="license_key" autocomplete="off"></label></p><button class="button button-primary" name="license_action" value="activate"><?php esc_html_e( 'Activate License', 'woofeeds-pro' ); ?></button><?php if ( $settings['purchase_url'] ) : ?> <a class="button" href="<?php echo esc_url( $settings['purchase_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Buy a License', 'woofeeds-pro' ); ?></a><?php endif; ?><?php endif; ?>
		</form>
		<hr><h3><?php esc_html_e( 'License server connection', 'woofeeds-pro' ); ?></h3>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="woofeeds_save"><input type="hidden" name="return_tab" value="license"><?php wp_nonce_field( 'woofeeds_save' ); $this->settings_hidden( $settings, 'license' ); ?><p><label><?php esc_html_e( 'API URL', 'woofeeds-pro' ); ?> <input type="url" required class="regular-text" name="settings[license_api_url]" value="<?php echo esc_attr( $settings['license_api_url'] ); ?>" placeholder="https://licenses.example.com"></label></p><p><label><?php esc_html_e( 'Buy Now URL', 'woofeeds-pro' ); ?> <input type="url" class="regular-text" name="settings[purchase_url]" value="<?php echo esc_attr( $settings['purchase_url'] ); ?>" placeholder="https://checkout.example.com/woofeeds-pro"></label></p><p><label><?php esc_html_e( 'Offline grace period (days)', 'woofeeds-pro' ); ?> <input type="number" min="1" max="30" name="settings[license_grace_days]" value="<?php echo esc_attr( $settings['license_grace_days'] ); ?>"></label></p><?php submit_button( __( 'Save Connection', 'woofeeds-pro' ) ); ?></form>
		<?php
	}

	private function settings_hidden( array $settings, $editable_platform = '' ) {
		foreach ( array( 'google', 'meta', 'chatgpt' ) as $platform ) {
			if ( $platform !== $editable_platform && ! empty( $settings['enabled_feeds'][ $platform ] ) ) {
				echo '<input type="hidden" name="settings[enabled_feeds][' . esc_attr( $platform ) . ']" value="1">';
			}
		}
		$values = array( 'schedule', 'batch_size', 'google_product_category', 'fb_product_category', 'seller_name', 'store_country', 'license_api_url', 'purchase_url', 'license_grace_days' );
		foreach ( $values as $key ) {
			if ( ( in_array( $key, array( 'schedule', 'batch_size' ), true ) && 'mapping' === $editable_platform ) || ( 'google_product_category' === $key && in_array( $editable_platform, array( 'google', 'meta' ), true ) ) || ( 'fb_product_category' === $key && 'meta' === $editable_platform ) || ( in_array( $key, array( 'seller_name', 'store_country' ), true ) && 'chatgpt' === $editable_platform ) || ( in_array( $key, array( 'license_api_url', 'purchase_url', 'license_grace_days' ), true ) && 'license' === $editable_platform ) ) {
				continue;
			}
			echo '<input type="hidden" name="settings[' . esc_attr( $key ) . ']" value="' . esc_attr( $settings[ $key ] ) . '">';
		}
		if ( 'chatgpt' !== $editable_platform ) { echo '<input type="hidden" name="settings[target_countries]" value="' . esc_attr( implode( ',', $settings['target_countries'] ) ) . '">'; }
		if ( 'mapping' !== $editable_platform && $settings['include_variations'] ) { echo '<input type="hidden" name="settings[include_variations]" value="1">'; }
		if ( $settings['remove_data_on_uninstall'] ) { echo '<input type="hidden" name="settings[remove_data_on_uninstall]" value="1">'; }
		if ( 'mapping' !== $editable_platform ) { foreach ( $settings['field_mappings'] as $key => $value ) { echo '<input type="hidden" name="settings[field_mappings][' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '">'; } }
	}

	private function mapping_sources() {
		$sources = array(
			'meta:_global_unique_id' => __( 'WooCommerce Global Unique ID', 'woofeeds-pro' ),
			'meta:_mpn'              => __( 'Custom field: _mpn', 'woofeeds-pro' ),
			'meta:_brand'            => __( 'Custom field: _brand', 'woofeeds-pro' ),
			'property:sku'           => __( 'Product SKU', 'woofeeds-pro' ),
		);
		foreach ( wc_get_attribute_taxonomies() as $attribute ) {
			$sources[ 'attribute:pa_' . $attribute->attribute_name ] = sprintf( __( 'Attribute: %s', 'woofeeds-pro' ), $attribute->attribute_label );
		}
		return $sources;
	}

	private function card( $title, $value, $description ) { echo '<div class="woofeeds-card"><h3>' . esc_html( $title ) . '</h3><strong>' . esc_html( $value ) . '</strong><p>' . esc_html( $description ) . '</p></div>'; }
	private function page_url( $tab ) { return add_query_arg( array( 'page' => self::PAGE, 'tab' => $tab ), admin_url( 'admin.php' ) ); }
	private function redirect( $tab ) { wp_safe_redirect( $this->page_url( $tab ) ); exit; }
	private function guard() { if ( ! current_user_can( self::CAPABILITY ) ) { wp_die( esc_html__( 'You do not have permission to manage product feeds.', 'woofeeds-pro' ) ); } }
	private function set_notice( $type, $message ) { set_transient( 'woofeeds_pro_notice_' . get_current_user_id(), array( 'type' => $type, 'message' => sanitize_text_field( $message ) ), MINUTE_IN_SECONDS ); }
	private function notice() { $key = 'woofeeds_pro_notice_' . get_current_user_id(); $notice = get_transient( $key ); if ( $notice ) { delete_transient( $key ); echo '<div class="notice notice-' . esc_attr( 'error' === $notice['type'] ? 'error' : 'success' ) . ' is-dismissible"><p>' . esc_html( $notice['message'] ) . '</p></div>'; } }
}

