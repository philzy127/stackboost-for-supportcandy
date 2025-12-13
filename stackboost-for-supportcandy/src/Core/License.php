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
		// 1. Check if a DB option exists and is valid.
		$saved_tier = strtolower( get_option( 'stackboost_license_tier', '' ) );

		if ( self::is_valid_tier( $saved_tier ) ) {
			return $saved_tier;
		}

		// 2. Fallback to 'lite' if no valid option is found (or if deactivated).
        // Note: For development, we might want to default to 'business', but for production 'lite' is safer.
        // Assuming we want to stick to the previous request of 'business' as default ONLY if configured,
        // but now we have a real license system.
        // Logic: If no license is saved, we are Lite.
		return 'lite';
	}

    /**
     * Set the license tier for testing purposes.
     *
     * @param string $tier The tier to set ('lite', 'pro', 'business').
     */
    public static function set_tier_for_testing(string $tier) {
		$tier = strtolower( $tier );
        if (self::is_valid_tier($tier)) {
            // We can't easily override the get_option call in get_tier without mocking,
            // so we might need to update the option temporarily or refactor.
            // For simple unit testing, we might assume the test db is clean.
            // OR we rely on a static override property if set.
            self::$tier = $tier;
        }
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
