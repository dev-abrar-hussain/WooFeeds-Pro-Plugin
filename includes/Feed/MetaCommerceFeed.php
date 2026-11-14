<?php

namespace WooFeedsPro\Feed;

defined( 'ABSPATH' ) || exit;

final class MetaCommerceFeed extends AbstractFeedGenerator {
	public function key() { return 'meta'; }
	public function filename() { return 'meta-commerce.xml'; }
	public function content_type() { return 'application/xml; charset=UTF-8'; }

	public function write( iterable $records, $path ) {
		$handle = fopen( $path, 'wb' );
		if ( false === $handle ) {
			throw new \RuntimeException( 'Unable to create the Meta feed file.' );
		}

		fwrite( $handle, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n" );
		fwrite( $handle, '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0"><channel>' . "\n" );
		fwrite( $handle, '<title>' . $this->xml( get_bloginfo( 'name' ) ) . '</title>' . "\n" );
		fwrite( $handle, '<link>' . $this->xml( home_url( '/' ) ) . '</link>' . "\n" );
		$count = 0;

		foreach ( $records as $record ) {
			$availability = array(
				'in_stock'     => 'in stock',
				'out_of_stock' => 'out of stock',
				'backorder'    => 'available for order',
			);
			$fields = array(
				'id'                      => $record['id'],
				'title'                   => $record['title'],
				'description'             => $record['description'],
				'availability'            => $availability[ $record['availability'] ] ?? 'out of stock',
				'condition'               => $record['condition'],
				'price'                   => $record['price'],
				'sale_price'              => $record['sale_price'],
				'link'                    => $record['link'],
				'image_link'              => $record['image_link'],
				'brand'                   => $record['brand'],
				'gtin'                    => $record['gtin'],
				'mpn'                     => $record['mpn'],
				'google_product_category' => $record['google_product_category'],
				'fb_product_category'     => $record['fb_product_category'],
				'item_group_id'           => $record['is_variation'] ? $record['group_id'] : '',
			);

			fwrite( $handle, "<item>\n" );
			foreach ( $fields as $name => $value ) {
				if ( '' !== (string) $value ) {
					fwrite( $handle, '<g:' . $name . '>' . $this->xml( $value ) . '</g:' . $name . '>' . "\n" );
				}
			}
			fwrite( $handle, "</item>\n" );
			++$count;
		}

		fwrite( $handle, '</channel></rss>' . "\n" );
		fclose( $handle );
		return $count;
	}
}

