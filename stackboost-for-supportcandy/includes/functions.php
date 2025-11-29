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
            'unified_ticket_macro',
        ],
        'operations_suite' => [
            'qol_enhancements',
            'after_hours_notice',
            'conditional_views',
            'queue_macro',
            'after_ticket_survey',
            'unified_ticket_macro',
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

/**
 * Format a phone number for display and click-to-dial.
 *
 * @param string $phone The phone number.
 * @param string $extension The extension, if any.
 * @param string $copy_icon_svg The SVG for the copy icon.
 * @return string The formatted HTML for the phone number.
 */
function stackboost_format_phone_number( string $phone, string $extension, string $copy_icon_svg ): string {
    $phone_digits = preg_replace( '/\D/', '', $phone );
    $display_phone = $phone;

    if ( 10 === strlen( $phone_digits ) ) {
        $display_phone = sprintf( '(%s) %s-%s',
            substr( $phone_digits, 0, 3 ),
            substr( $phone_digits, 3, 3 ),
            substr( $phone_digits, 6 )
        );
    }

    $tel_link = 'tel:' . $phone_digits;
    if ( ! empty( $extension ) ) {
        $tel_link .= ';ext=' . $extension;
        $display_phone .= ', ext. ' . esc_html( $extension );
    }

    $copy_span = sprintf(
        '<span class="stackboost-copy-phone-icon" data-phone="%s" data-extension="%s" title="%s">%s</span>',
        esc_attr( $phone_digits ),
        esc_attr( $extension ),
        esc_attr__( 'Click to copy phone number', 'stackboost-for-supportcandy' ),
        $copy_icon_svg
    );

    return sprintf( '<a href="%s">%s</a>%s', esc_url( $tel_link ), esc_html( $display_phone ), $copy_span );
}
