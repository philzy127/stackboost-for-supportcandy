<?php

use StackBoost\ForSupportCandy\Core\License;

/**
 * Checks if a specific feature is active based on the current license tier.
 *
 * This function acts as a central gatekeeper for all tiered features.
 *
 * @param string $feature_slug The unique identifier for the feature.
 * @return bool True if the feature is available, false otherwise.
 */
function stackboost_is_feature_active( string $feature_slug ): bool {
    $current_tier = License::get_tier();

    $features_by_tier = [
        'free' => [
            'qol_enhancements',
            'after_hours_notice',
        ],
        'plus' => [
            'qol_enhancements',
            'after_hours_notice',
            'conditional_views',
            'queue_macro',
            'after_ticket_survey',
        ],
        'operations_suite' => [
            'qol_enhancements',
            'after_hours_notice',
            'conditional_views',
            'queue_macro',
            'after_ticket_survey',
            'onboarding_dashboard', // Coming soon
            'staff_directory',      // Coming soon
        ],
    ];

    // Check if the tier exists and if the feature is in the list for that tier.
    if ( isset( $features_by_tier[ $current_tier ] ) ) {
        return in_array( $feature_slug, $features_by_tier[ $current_tier ], true );
    }

    return false;
}