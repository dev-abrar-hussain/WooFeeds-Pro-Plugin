<?php

namespace WooFeedsPro\Feed;

defined( 'ABSPATH' ) || exit;

/**
 * OpenAI Agentic Commerce stable JSONL feed.
 *
 * Schema reference: https://developers.openai.com/commerce/specs/file-upload/products
 */
final class ChatGptFeed extends AbstractFeedGenerator {
	public function key() { return 'chatgpt'; }
	public function filename() { return 'chatgpt-products.jsonl.gz'; }
	public function content_type() { return 'application/gzip'; }

	public function write( iterable $records, $path ) {
		if ( ! function_exists( 'gzopen' ) ) {
			throw new \RuntimeException( 'The PHP zlib extension is required for the ChatGPT feed.' );
		}

		$handle = gzopen( $path, 'wb9' );
		if ( false === $handle ) {
			throw new \RuntimeException( 'Unable to create the ChatGPT feed file.' );
		}
		$count = 0;

		foreach ( $records as $record ) {
			$item = array(
				'item_id'           => (string) $record['id'],
				'title'             => (string) $record['title'],
				'description'       => (string) $record['description'],
				'url'               => (string) $record['link'],
				'brand'             => (string) $record['brand'],
				'seller_name'       => (string) $record['seller_name'],
				'image_url'         => (string) $record['image_link'],
				'availability'      => (string) $record['availability'],
				'price'             => (string) $record['price'],
				'target_countries'  => array_values( $record['target_countries'] ),
				'store_country'     => (string) $record['store_country'],
				'is_eligible_search'=> true,
				'condition'         => (string) $record['condition'],
				'product_category'  => (string) $record['product_category'],
				'seller_url'        => (string) $record['seller_url'],
			);

			if ( $record['sale_price'] ) {
				$item['sale_price'] = (string) $record['sale_price'];
			}
			if ( $record['gtin'] ) {
				$item['gtin'] = (string) $record['gtin'];
			}
			if ( $record['mpn'] ) {
				$item['mpn'] = (string) $record['mpn'];
			}
			if ( $record['additional_image_links'] ) {
				$item['additional_image_urls'] = array_values( $record['additional_image_links'] );
			}
			if ( $record['is_variation'] ) {
				$item['group_id'] = (string) $record['group_id'];
				$item['listing_has_variations'] = true;
				$item['variant_dict'] = (object) $record['variant_attributes'];
			}
			if ( count( $record['dimensions'] ) >= 3 ) {
				$item['dimensions'] = (object) $record['dimensions'];
			}
			if ( $record['weight'] ) {
				$item['weight'] = (string) $record['weight'];
				$item['item_weight_unit'] = (string) $record['weight_unit'];
			}

			$item = array_filter(
				$item,
				static function ( $value ) {
					return null !== $value && '' !== $value && array() !== $value;
				}
			);

			$encoded = wp_json_encode( $item, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			if ( false === $encoded ) {
				gzclose( $handle );
				throw new \RuntimeException( 'A product could not be encoded as JSON.' );
			}
			gzwrite( $handle, $encoded . "\n" );
			++$count;
		}

		gzclose( $handle );
		return $count;
	}
}

