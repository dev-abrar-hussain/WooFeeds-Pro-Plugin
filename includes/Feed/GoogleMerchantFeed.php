<?php

namespace WooFeedsPro\Feed;

defined( 'ABSPATH' ) || exit;

final class GoogleMerchantFeed extends AbstractFeedGenerator {
	public function key() { return 'google'; }
	public function filename() { return 'google-merchant.xml'; }
	public function content_type() { return 'application/xml; charset=UTF-8'; }

	public function write( iterable $records, $path ) {
		$handle = fopen( $path, 'wb' );
		if ( false === $handle ) {
			throw new \RuntimeException( 'Unable to create the Google feed file.' );
		}

		fwrite( $handle, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n" );
		fwrite( $handle, '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0"><channel>' . "\n" );
		fwrite( $handle, '<title>' . $this->xml( get_bloginfo( 'name' ) ) . '</title>' . "\n" );
		fwrite( $handle, '<link>' . $this->xml( home_url( '/' ) ) . '</link>' . "\n" );
		fwrite( $handle, '<description>WooCommerce product feed</description>' . "\n" );
		$count = 0;

		foreach ( $records as $record ) {
			fwrite( $handle, $this->item( $record ) );
			++$count;
		}

		fwrite( $handle, '</channel></rss>' . "\n" );
		fclose( $handle );
		return $count;
	}

	/** @param array<string,mixed> $record */
	private function item( array $record ) {
		$availability = 'in_stock' === $record['availability'] ? 'in_stock' : ( 'backorder' === $record['availability'] ? 'backorder' : 'out_of_stock' );
		$fields       = array(
			'id'                      => $record['id'],
			'title'                   => $record['title'],
			'description'             => $record['description'],
			'link'                    => $record['link'],
			'image_link'              => $record['image_link'],
			'availability'            => $availability,
			'price'                   => $record['price'],
			'sale_price'              => $record['sale_price'],
			'condition'               => $record['condition'],
			'brand'                   => $record['brand'],
			'gtin'                    => $record['gtin'],
			'mpn'                     => $record['mpn'],
			'google_product_category' => $record['google_product_category'],
			'product_type'            => $record['product_category'],
			'item_group_id'           => $record['is_variation'] ? $record['group_id'] : '',
		);
		if ( ! $record['gtin'] && ! $record['mpn'] && ! $record['brand'] ) {
			$fields['identifier_exists'] = 'no';
		}

		$xml = "<item>\n";
		foreach ( $fields as $name => $value ) {
			if ( '' !== (string) $value ) {
				$xml .= '<g:' . $name . '>' . $this->xml( $value ) . '</g:' . $name . '>' . "\n";
			}
		}
		foreach ( $record['additional_image_links'] as $url ) {
			$xml .= '<g:additional_image_link>' . $this->xml( $url ) . '</g:additional_image_link>' . "\n";
		}
		return $xml . "</item>\n";
	}
}

