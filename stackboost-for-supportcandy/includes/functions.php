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
    // Clean digits for logic, but keep raw for processing
    $phone_digits_only = preg_replace( '/\D/', '', $phone );

    // Default display is raw
    $display_phone = $phone;

    // Apply strict formatting if 10 digits
    if ( 10 === strlen( $phone_digits_only ) ) {
        $display_phone = sprintf( '(%s) %s-%s',
            substr( $phone_digits_only, 0, 3 ),
            substr( $phone_digits_only, 3, 3 ),
            substr( $phone_digits_only, 6 )
        );
    }

    // Build TEL URI (RFC 3966)
    // Keep + for international, remove others for the base number
    $clean_number = preg_replace( '/[^0-9+]/', '', $phone );
    $tel_link = 'tel:' . $clean_number;

    // Add extension if present
    if ( ! empty( $extension ) ) {
        $clean_extension = preg_replace( '/[^0-9]/', '', $extension );
        $tel_link .= ';ext=' . $clean_extension;

        // Append extension to display if not already part of formatting logic
        // Note: The original function appended it, so we keep that behavior for consistency
        $display_phone .= ' <span style="color: #777; font-size: 0.9em;">' . esc_html__( 'ext.', 'stackboost-for-supportcandy' ) . ' ' . esc_html( $extension ) . '</span>';
    }

    // Build Copy Span
    // Use the raw phone for the data attribute so the JS can handle it cleanly

    // Construct copy text to match display: (xxx) xxx-xxxx, ext. yyy
    $copy_text_formatted = $display_phone;
    // Strip HTML tags from display_phone (which might contain the extension span) to get clean text for clipboard
    $copy_text_formatted = strip_tags( $copy_text_formatted );

    $copy_span = sprintf(
        ' <span class="stackboost-copy-phone-icon" data-phone="%s" data-extension="%s" data-copy-text="%s" title="%s">%s</span>',
        esc_attr( $phone ),
        esc_attr( $extension ),
        esc_attr( $copy_text_formatted ),
        esc_attr__( 'Click to copy phone number', 'stackboost-for-supportcandy' ),
        $copy_icon_svg
    );

    // Return combined HTML
    // Note: display_phone might contain HTML (the extension span), so we don't esc_html the whole thing in the return
    // The parts were escaped individually above.
    return sprintf( '<a href="%s">%s</a>%s', esc_url( $tel_link ), $display_phone, $copy_span );
}
