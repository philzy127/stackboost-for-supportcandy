<?php

namespace StackBoost\ForSupportCandy\Modules\QolEnhancements;

/**
 * Core business logic for Quality of Life (QOL) Enhancements.
 *
 * This class contains simple, reusable data processing functions that are
 * decoupled from the WordPress environment.
 *
 * @package StackBoost\ForSupportCandy\Modules\QolEnhancements
 */
class Core {

	/**
	 * Parses a newline-separated string of ticket types into a clean array.
	 *
	 * @param string $types_string The raw string from a textarea.
	 * @return array An array of trimmed ticket type names.
	 */
	public function parse_types_to_hide( string $types_string ): array {
		if ( empty( $types_string ) ) {
			return [];
		}

		// Normalize line endings and split into an array.
		$lines = preg_split( '/\r\n|\r|\n/', $types_string );

		// Trim whitespace from each line and remove any empty lines.
		return array_filter( array_map( 'trim', $lines ) );
	}

	/**
	 * Strips duplicate line breaks, HR tags, and excessive added whitespace.
	 *
	 * @param mixed $html Raw string or content.
	 * @return mixed Cleaned HTML content or original input if not string/empty.
	 */
	public function strip_excessive_breaks( $html ) {
		if ( ! is_string( $html ) || empty( $html ) ) {
			return $html;
		}

		// Handles variations of <br> and <hr> tags with whitespace, newlines, and &nbsp; entities
		$pattern = '/(?:(?:\s|&nbsp;)*<(?:br|hr)\s*\/?>\s*){2,}/i';
		return preg_replace( $pattern, '<br>', $html );
	}
}