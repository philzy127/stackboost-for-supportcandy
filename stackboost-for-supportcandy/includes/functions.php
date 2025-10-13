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