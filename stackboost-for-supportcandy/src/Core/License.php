<?php

namespace StackBoost\ForSupportCandy\Core;

/**
 * Manages the plugin's license and feature tiers.
 *
 * @package StackBoost\ForSupportCandy\Core
 */
class License {

	/**
	 * The currently active license tier.
	 *
	 * Can be 'lite', 'pro', or 'business'.
	 *
	 * @var string
	 */
	private static string $tier = 'lite'; // Default to lite for safety

	/**
	 * Get the current active license tier.
	 *
	 * @return string
	 */
	public static function get_tier(): string {
        $class = 'StackBoost\ForSupportCandy\Services\LicenseManager';
        if ( ! class_exists( $class ) ) {
            return 'lite';
        }

        // Validate license status periodically (12h cache)
        $license_key = get_option( 'stackboost_license_key', '' );

        if ( ! empty( $license_key ) ) {
            $manager = new $class();
            $status = $manager->check_license_status( $license_key );

            if ( $status && isset( $status['variant_id'] ) ) {
                return $manager->get_tier_from_variant( $status['variant_id'] );
            }
        }

		// Fallback to 'lite' if no valid option is found or validation fails.
		return 'lite';
	}

    /**
     * Set the license tier for testing purposes.
     *
     * @param string $tier The tier to set ('lite', 'pro', 'business').
     */
    public static function set_tier_for_testing(string $tier) {
		// Mock implementation for testing if needed
    }

	/**
	 * Validates if a given string is a valid tier name.
	 *
	 * @param string $tier The tier to validate.
	 * @return bool
	 */
	public static function is_valid_tier( string $tier ): bool {
		return in_array( $tier, [ 'lite', 'pro', 'business' ], true );
	}
}
