<?php

if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * Check for SupportCandy Pro
 *
 * @package StackBoost\ForSupportCandy\WordPress
 */

/**
 * Check if SupportCandy Pro is active.
 *
 * Checks for a variety of Pro-only constants and classes to determine if the user
 * has a premium version or premium add-ons active.
 *
 * @return bool
 */
function stackboost_is_supportcandy_pro_active() {
    // 1. Check for Pro Version Constant (if defined by some versions)
    if ( defined( 'WPSC_PRO_VERSION' ) ) {
        return true;
    }

    // 2. Check for common Pro Add-on Classes
    // If ANY of these exist, the user has premium features active.
    $pro_classes = [
        'WPSC_Pro_Custom_Fields',
        'WPSC_Email_Piping',
        'WPSC_Canned_Reply',
        'WPSC_SLA',
        'WPSC_Knowledgebase',
        'WPSC_Satisfaction_Survey',
        'WPSC_Time_Tracking',
        'WPSC_Private_Note',
        'WPSC_Print_Ticket',
        'WPSC_Export_Ticket',
        'WPSC_User_Registration',
        'WPSC_WooCommerce_Integration',
        'WPSC_Reports',
        'WPSC_Schedule_Tickets',
        'WPSC_Schedule_Ticket',
        'WPSC_WooCommerce',
    ];

    foreach ( $pro_classes as $class ) {
        if ( class_exists( $class ) ) {
            return true;
        }
    }

    // 3. Fallback: check for license key option presence (basic heuristic)
    // SupportCandy typically stores license keys in 'wpsc_license_key' or similar.
    // We only check if it's set and not empty.
    $license = get_option( 'wpsc_license_key' );
    if ( ! empty( $license ) ) {
        return true;
    }

    return false;
}
