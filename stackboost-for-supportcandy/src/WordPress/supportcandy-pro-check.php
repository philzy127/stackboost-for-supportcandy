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
 * @return bool
 */
function stackboost_is_supportcandy_pro_active() {
    return class_exists( 'WPSC_Pro_Custom_Fields' );
}

/**
 * Check if SupportCandy Pro is active (Legacy wrapper).
 *
 * @return bool
 */
if ( ! function_exists( 'is_supportcandy_pro_active' ) ) {
    function is_supportcandy_pro_active() {
        return stackboost_is_supportcandy_pro_active();
    }
}