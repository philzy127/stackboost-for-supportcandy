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
	 * Can be 'free', 'plus', or 'operations_suite'.
	 * In a real application, this would be determined by checking a stored license key
	 * against an external API. For now, we can hardcode it for development.
	 *
	 * @var string
	 */
	private static string $tier = 'operations_suite'; // Default to the highest tier for development

	/**
	 * Get the current active license tier.
	 *
	 * @return string
	 */
	public static function get_tier(): string {
		// TODO: Replace this with actual license checking logic.
		// For example, get a stored option and validate it.
		// $saved_tier = get_option('stackboost_license_tier', 'free');
		// return self::is_valid_tier($saved_tier) ? $saved_tier : 'free';
		return self::$tier;
	}

    /**
     * Set the license tier for testing purposes.
     *
     * @param string $tier The tier to set ('free', 'plus', 'operations_suite').
     */
    public static function set_tier_for_testing(string $tier) {
        if (in_array($tier, ['free', 'plus', 'operations_suite'])) {
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
		return in_array( $tier, [ 'free', 'plus', 'operations_suite' ], true );
	}
}