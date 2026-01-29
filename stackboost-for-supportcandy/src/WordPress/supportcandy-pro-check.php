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
