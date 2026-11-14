<?php

namespace WooFeedsPro\Feed;

use WooFeedsPro\Support\Options;

defined( 'ABSPATH' ) || exit;

final class ProductRepository {
	/** @var Options */
	private $options;

	public function __construct( Options $options ) {
		$this->options = $options;
	}

	/**
	 * Iterate published purchasable products in bounded batches.
	 *
	 * @return \Generator<int,\WC_Product>
	 */
	public function iterate() {
		$page       = 1;
		$batch_size = (int) $this->options->get( 'batch_size', 100 );
		$variations = (bool) $this->options->get( 'include_variations', true );

		do {
			$query = wc_get_products(
				array(
					'status'   => 'publish',
					'type'     => array( 'simple', 'external', 'variable' ),
					'limit'    => $batch_size,
					'page'     => $page,
					'paginate' => true,
					'orderby'  => 'ID',
					'order'    => 'ASC',
				)
			);

			foreach ( $query->products as $product ) {
				if ( $product->is_type( 'variable' ) && $variations ) {
					foreach ( $product->get_visible_children() as $variation_id ) {
						$variation = wc_get_product( $variation_id );
						if ( $variation && $variation->is_purchasable() ) {
							yield $variation;
						}
					}
					continue;
				}

				if ( $product->is_purchasable() || $product->is_type( 'external' ) ) {
					yield $product;
				}
			}

			++$page;
		} while ( $page <= (int) $query->max_num_pages );
	}
}

