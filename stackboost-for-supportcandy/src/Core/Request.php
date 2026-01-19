<?php

namespace StackBoost\ForSupportCandy\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Request Helper Class.
 *
 * Centralizes access to superglobals ($_GET, $_POST, $_REQUEST) to handle
 * sanitization, unslashing, and PHPCS suppression in a single location.
 *
 * @package StackBoost\ForSupportCandy\Core
 */
class Request {

	/**
	 * Get a sanitized parameter from $_GET.
	 *
	 * @param string $key     The key to retrieve.
	 * @param mixed  $default Default value if key is missing.
	 * @param string $type    Sanitization type ('text', 'key', 'int', 'email', 'array').
	 * @return mixed
	 */
	public static function get_get( string $key, $default = '', string $type = 'text' ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET[ $key ] ) ) {
			return $default;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$value = wp_unslash( $_GET[ $key ] );

		return self::sanitize( $value, $type );
	}

	/**
	 * Get a sanitized parameter from $_POST.
	 *
	 * @param string $key     The key to retrieve.
	 * @param mixed  $default Default value if key is missing.
	 * @param string $type    Sanitization type ('text', 'key', 'int', 'email', 'array', 'textarea', 'raw').
	 * @return mixed
	 */
	public static function get_post( string $key, $default = '', string $type = 'text' ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST[ $key ] ) ) {
			return $default;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$value = wp_unslash( $_POST[ $key ] );

		return self::sanitize( $value, $type );
	}

	/**
	 * Get a sanitized parameter from $_REQUEST.
	 * Useful for List Tables that might use either GET or POST.
	 *
	 * @param string $key     The key to retrieve.
	 * @param mixed  $default Default value.
	 * @param string $type    Sanitization type.
	 * @return mixed
	 */
	public static function get_request( string $key, $default = '', string $type = 'text' ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_REQUEST[ $key ] ) ) {
			return $default;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$value = wp_unslash( $_REQUEST[ $key ] );

		return self::sanitize( $value, $type );
	}

	/**
	 * Check if a specific GET parameter exists.
	 *
	 * @param string $key The key to check.
	 * @return bool
	 */
	public static function has_get( string $key ): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( $_GET[ $key ] );
	}

	/**
	 * Check if a specific POST parameter exists.
	 *
	 * @param string $key The key to check.
	 * @return bool
	 */
	public static function has_post( string $key ): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		return isset( $_POST[ $key ] );
	}

	/**
	 * Internal sanitization helper.
	 *
	 * @param mixed  $value The value to sanitize.
	 * @param string $type  The type of sanitization.
	 * @return mixed
	 */
	private static function sanitize( $value, string $type ) {
		switch ( $type ) {
			case 'int':
				return is_array( $value ) ? array_map( 'intval', $value ) : intval( $value );
			case 'key':
				return is_array( $value ) ? array_map( 'sanitize_key', $value ) : sanitize_key( $value );
			case 'email':
				return is_array( $value ) ? array_map( 'sanitize_email', $value ) : sanitize_email( $value );
			case 'textarea':
				return is_array( $value ) ? array_map( 'sanitize_textarea_field', $value ) : sanitize_textarea_field( $value );
			case 'array':
				return is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : [ sanitize_text_field( $value ) ];
			case 'raw':
				return $value; // Use with caution, usually for JSON strings that will be decoded.
			case 'text':
			default:
				return is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : sanitize_text_field( $value );
		}
	}
}
