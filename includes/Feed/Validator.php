<?php

namespace WooFeedsPro\Feed;

defined( 'ABSPATH' ) || exit;

final class Validator {
	/** @var ProductRepository */
	private $repository;
	/** @var ProductDataMapper */
	private $mapper;

	public function __construct( ProductRepository $repository, ProductDataMapper $mapper ) {
		$this->repository = $repository;
		$this->mapper     = $mapper;
	}

	/**
	 * Validate mapped catalog records and return beginner-friendly counters.
	 *
	 * @return array<string,mixed>
	 */
	public function run() {
		$issues = array(
			'missing_title'       => 0,
			'missing_description' => 0,
			'missing_brand'       => 0,
			'missing_gtin_mpn'    => 0,
			'invalid_gtin'        => 0,
			'missing_image'       => 0,
			'broken_image_file'   => 0,
			'invalid_image_url'   => 0,
			'missing_price'       => 0,
			'missing_country'     => 0,
		);
		$examples = array();
		$total    = 0;

		foreach ( $this->repository->iterate() as $product ) {
			$record = $this->mapper->map( $product );
			++$total;
			$checks = array(
				'missing_title'       => empty( $record['title'] ),
				'missing_description' => empty( $record['description'] ),
				'missing_brand'       => empty( $record['brand'] ),
				'missing_gtin_mpn'    => empty( $record['gtin'] ) && empty( $record['mpn'] ),
				'invalid_gtin'        => ! empty( $record['gtin'] ) && ! $this->valid_gtin( $record['gtin'] ),
				'missing_image'       => empty( $record['image_link'] ),
				'broken_image_file'   => ! empty( $record['image_id'] ) && ! $this->attachment_exists( $record['image_id'] ),
				'invalid_image_url'   => ! empty( $record['image_link'] ) && ! wp_http_validate_url( $record['image_link'] ),
				'missing_price'       => empty( $record['price'] ) || 0 >= (float) $record['price'],
				'missing_country'     => empty( $record['store_country'] ) || empty( $record['target_countries'] ),
			);

			foreach ( $checks as $key => $failed ) {
				if ( $failed ) {
					++$issues[ $key ];
					if ( count( $examples ) < 20 ) {
						$examples[] = array( 'product_id' => $product->get_id(), 'title' => $record['title'], 'issue' => $key );
					}
				}
			}
		}

		return array(
			'checked_at' => time(),
			'total'      => $total,
			'issues'     => $issues,
			'examples'   => $examples,
			'passed'     => 0 === array_sum( $issues ),
		);
	}

	private function attachment_exists( $attachment_id ) {
		$file = get_attached_file( $attachment_id );
		return $file && is_file( $file );
	}

	private function valid_gtin( $value ) {
		$value = (string) $value;
		if ( ! preg_match( '/^\d{8}$|^\d{12}$|^\d{13}$|^\d{14}$/', $value ) ) {
			return false;
		}
		$digits = array_map( 'intval', str_split( $value ) );
		$check  = array_pop( $digits );
		$sum    = 0;
		$weight = 3;
		for ( $i = count( $digits ) - 1; $i >= 0; --$i ) {
			$sum += $digits[ $i ] * $weight;
			$weight = 3 === $weight ? 1 : 3;
		}
		return ( 10 - ( $sum % 10 ) ) % 10 === $check;
	}
}

