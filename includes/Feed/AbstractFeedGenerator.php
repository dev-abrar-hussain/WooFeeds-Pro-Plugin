<?php

namespace WooFeedsPro\Feed;

defined( 'ABSPATH' ) || exit;

abstract class AbstractFeedGenerator {
	/**
	 * Platform identifier.
	 *
	 * @return string
	 */
	abstract public function key();

	/** @return string */
	abstract public function filename();

	/** @return string */
	abstract public function content_type();

	/**
	 * Write a complete feed snapshot to a temporary path.
	 *
	 * @param iterable<array<string,mixed>> $records Records.
	 * @param string                        $path    Temporary path.
	 * @return int Written record count.
	 */
	abstract public function write( iterable $records, $path );

	/**
	 * Escape an XML value.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	protected function xml( $value ) {
		return htmlspecialchars( (string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8' );
	}
}

