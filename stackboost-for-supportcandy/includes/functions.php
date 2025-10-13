<?php
/**
 * General helper functions for the StackBoost plugin.
 *
 * @package StackBoost
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Check if a StackBoost feature is active.
 *
 * @param string $feature_slug The slug of the feature to check.
 * @return bool True if the feature is active, false otherwise.
 */
function stackboost_is_feature_active( string $feature_slug ): bool {
	$options = get_option( 'stackboost_settings' );
	return ! empty( $options[ 'enable_' . $feature_slug ] );
}

/**
 * Custom debug logging function.
 *
 * @param string $message The message to log.
 */
function stackboost_debug_log( $message ) {
	// Ensure the path constant is defined before using it.
	if ( ! defined( 'STACKBOOST_PLUGIN_PATH' ) ) {
		return;
	}
	$log_file = STACKBOOST_PLUGIN_PATH . 'stackboost-debug.log';
	$timestamp = date( 'Y-m-d H:i:s' );
	$formatted_message = sprintf( "[%s] %s\n", $timestamp, print_r( $message, true ) );
	file_put_contents( $log_file, $formatted_message, FILE_APPEND );
}