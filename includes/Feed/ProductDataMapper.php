<?php

namespace WooFeedsPro\Feed;

use WooFeedsPro\Support\Options;

defined( 'ABSPATH' ) || exit;

final class ProductDataMapper {
	/** @var Options */
	private $options;

	public function __construct( Options $options ) {
		$this->options = $options;
	}

	/**
	 * Convert a WooCommerce product into a platform-neutral record.
	 *
	 * @param \WC_Product $product Product or variation.
	 * @return array<string,mixed>
	 */
	public function map( $product ) {
		$settings   = $this->options->all();
		$parent     = $product->is_type( 'variation' ) ? wc_get_product( $product->get_parent_id() ) : null;
		$image_id   = $product->get_image_id();
		$image_id   = $image_id ? $image_id : ( $parent ? $parent->get_image_id() : 0 );
		$description = $product->get_description();
		$description = $description ? $description : $product->get_short_description();
		$description = $description ? $description : ( $parent ? $parent->get_description() : '' );
		$regular     = $product->get_regular_price();
		$sale        = $product->get_sale_price();
		$price       = $product->get_price();
		$currency    = get_woocommerce_currency();
		$sku         = $product->get_sku();

		if ( '' === $regular ) {
			$regular = $price;
		}

		return array(
			'id'                      => (string) ( $sku ? $sku : $product->get_id() ),
			'group_id'                => $parent ? (string) ( $parent->get_sku() ? $parent->get_sku() : $parent->get_id() ) : '',
			'is_variation'            => (bool) $parent,
			'variant_attributes'       => $this->variant_attributes( $product ),
			'title'                   => $this->plain_text( $product->get_name(), 150 ),
			'description'             => $this->plain_text( $description, 5000 ),
			'link'                    => $product->get_permalink(),
			'image_link'              => $image_id ? wp_get_attachment_image_url( $image_id, 'full' ) : '',
			'image_id'                => (int) $image_id,
			'additional_image_links'  => $this->gallery_images( $product, $parent ),
			'price'                   => $this->money( $regular, $currency ),
			'sale_price'              => '' !== $sale && (float) $sale < (float) $regular ? $this->money( $sale, $currency ) : '',
			'currency'                => $currency,
			'availability'            => $this->availability( $product ),
			'condition'               => 'new',
			'brand'                   => $this->resolve_source( $product, $settings['field_mappings']['brand'] ?? '', $parent ),
			'gtin'                    => preg_replace( '/\D+/', '', $this->resolve_source( $product, $settings['field_mappings']['gtin'] ?? '', $parent ) ),
			'mpn'                     => $this->resolve_source( $product, $settings['field_mappings']['mpn'] ?? '', $parent ),
			'product_category'        => $this->category_path( $product, $parent ),
			'google_product_category' => (string) ( $settings['google_product_category'] ?? '' ),
			'fb_product_category'     => (string) ( $settings['fb_product_category'] ?? '' ),
			'seller_name'             => (string) ( $settings['seller_name'] ?? get_bloginfo( 'name' ) ),
			'seller_url'              => home_url( '/' ),
			'store_country'           => (string) ( $settings['store_country'] ?? 'US' ),
			'target_countries'        => (array) ( $settings['target_countries'] ?? array( 'US' ) ),
			'weight'                  => (string) $product->get_weight(),
			'weight_unit'             => $this->weight_unit(),
			'dimensions'              => $this->dimensions( $product ),
		);
	}

	/**
	 * Resolve a configured field source, falling back from variation to parent.
	 *
	 * @param \WC_Product      $product Product.
	 * @param string           $source  Mapping in type:key form.
	 * @param \WC_Product|null $parent  Parent product.
	 * @return string
	 */
	private function resolve_source( $product, $source, $parent ) {
		if ( ! $source || false === strpos( $source, ':' ) ) {
			return '';
		}

		list( $type, $key ) = explode( ':', $source, 2 );
		$value              = '';

		if ( 'attribute' === $type ) {
			$value = $product->get_attribute( $key );
			$value = $value ? $value : ( $parent ? $parent->get_attribute( $key ) : '' );
		} elseif ( 'meta' === $type ) {
			$value = $product->get_meta( $key, true );
			$value = $value ? $value : ( $parent ? $parent->get_meta( $key, true ) : '' );
		} elseif ( 'property' === $type ) {
			$method = 'get_' . $key;
			if ( is_callable( array( $product, $method ) ) ) {
				$value = $product->{$method}();
			}
			if ( ! $value && $parent && is_callable( array( $parent, $method ) ) ) {
				$value = $parent->{$method}();
			}
		}

		return is_scalar( $value ) ? trim( wp_strip_all_tags( (string) $value ) ) : '';
	}

	/** @return array<string,string> */
	private function variant_attributes( $product ) {
		if ( ! $product->is_type( 'variation' ) ) {
			return array();
		}

		$values = array();
		foreach ( $product->get_attributes() as $key => $value ) {
			$name = wc_attribute_label( str_replace( 'attribute_', '', $key ) );
			$term = taxonomy_exists( $key ) ? get_term_by( 'slug', $value, $key ) : false;
			$values[ sanitize_key( $name ) ] = $term && ! is_wp_error( $term ) ? $term->name : (string) $value;
		}
		return array_filter( $values );
	}

	/** @return string[] */
	private function gallery_images( $product, $parent ) {
		$ids = $product->get_gallery_image_ids();
		if ( empty( $ids ) && $parent ) {
			$ids = $parent->get_gallery_image_ids();
		}
		$urls = array();
		foreach ( array_slice( $ids, 0, 10 ) as $id ) {
			$url = wp_get_attachment_image_url( $id, 'full' );
			if ( $url ) {
				$urls[] = $url;
			}
		}
		return $urls;
	}

	private function availability( $product ) {
		$status = $product->get_stock_status();
		if ( 'onbackorder' === $status ) {
			return 'backorder';
		}
		return 'instock' === $status ? 'in_stock' : 'out_of_stock';
	}

	private function money( $amount, $currency ) {
		if ( '' === $amount || null === $amount ) {
			return '';
		}
		return wc_format_decimal( $amount, wc_get_price_decimals() ) . ' ' . strtoupper( $currency );
	}

	private function plain_text( $value, $limit ) {
		$text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $value ) ) );
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $limit ) : substr( $text, 0, $limit );
	}

	private function category_path( $product, $parent ) {
		$product_id = $parent ? $parent->get_id() : $product->get_id();
		$terms      = get_the_terms( $product_id, 'product_cat' );
		if ( ! $terms || is_wp_error( $terms ) ) {
			return '';
		}

		$term = reset( $terms );
		$path = array();
		foreach ( array_reverse( get_ancestors( $term->term_id, 'product_cat', 'taxonomy' ) ) as $ancestor_id ) {
			$ancestor = get_term( $ancestor_id, 'product_cat' );
			if ( $ancestor && ! is_wp_error( $ancestor ) ) {
				$path[] = $ancestor->name;
			}
		}
		$path[] = $term->name;
		return implode( ' > ', array_unique( $path ) );
	}

	/** @return array<string,string> */
	private function dimensions( $product ) {
		$values = array_filter(
			array(
				'length' => (string) $product->get_length(),
				'width'  => (string) $product->get_width(),
				'height' => (string) $product->get_height(),
			),
			static function ( $value ) {
				return '' !== $value && (float) $value > 0;
			}
		);

		if ( count( $values ) < 2 ) {
			return array();
		}
		$unit = (string) get_option( 'woocommerce_dimension_unit', 'cm' );
		if ( 'yd' === $unit ) {
			foreach ( array( 'length', 'width', 'height' ) as $axis ) {
				if ( isset( $values[ $axis ] ) ) {
					$values[ $axis ] = wc_format_decimal( (float) $values[ $axis ] * 3, wc_get_price_decimals() );
				}
			}
			$unit = 'ft';
		}
		if ( ! in_array( $unit, array( 'mm', 'cm', 'm', 'in', 'ft' ), true ) ) {
			return array();
		}
		$values['unit'] = $unit;
		return $values;
	}

	/** Normalize WooCommerce's plural pounds token to the feed schema token. */
	private function weight_unit() {
		$unit = (string) get_option( 'woocommerce_weight_unit', 'kg' );
		return 'lbs' === $unit ? 'lb' : $unit;
	}
}

