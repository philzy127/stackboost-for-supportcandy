<?php
/**
 * Fired when the plugin is deleted.
 *
 * @package StackBoost
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// 1. Check for Authorization
$settings = get_option( 'stackboost_settings', [] );
$authorized_timestamp = isset( $settings['uninstall_authorized_timestamp'] ) ? (int) $settings['uninstall_authorized_timestamp'] : 0;

// If not authorized, or timestamp is 0, exit (Safe Mode - Default WordPress Uninstall behavior).
if ( empty( $authorized_timestamp ) ) {
    return;
}

// 2. Check Time Limit (5 Minutes)
// 5 minutes = 300 seconds.
if ( ( time() - $authorized_timestamp ) > 300 ) {
    return; // Authorization expired.
}

// 3. Authorization Valid: Proceed with Data Wipe.

// We need to require the service file manually because the plugin is not loaded during uninstall.
require_once plugin_dir_path( __FILE__ ) . 'src/Services/UninstallService.php';

// Run the cleanup.
\StackBoost\ForSupportCandy\Services\UninstallService::run_clean_uninstall();
