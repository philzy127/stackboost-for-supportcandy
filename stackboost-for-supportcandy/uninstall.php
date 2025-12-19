<?php
/**
 * Fired when the plugin is deleted.
 *
 * @package StackBoost
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Logging Helper
if ( ! function_exists( 'stackboost_uninstall_log' ) ) {
	function stackboost_uninstall_log( $message ) {
		$log_file = WP_CONTENT_DIR . '/uploads/stackboost_uninstall.log';
		$entry    = gmdate( 'Y-m-d H:i:s' ) . ' [UNINSTALL] ' . $message . PHP_EOL;
		@file_put_contents( $log_file, $entry, FILE_APPEND );
	}
}

stackboost_uninstall_log( 'Uninstall process started.' );

// 1. Check for Authorization
$settings = get_option( 'stackboost_settings', [] );
$authorized_timestamp = isset( $settings['uninstall_authorized_timestamp'] ) ? (int) $settings['uninstall_authorized_timestamp'] : 0;

stackboost_uninstall_log( 'Authorized Timestamp: ' . $authorized_timestamp );

// If not authorized, or timestamp is 0, exit (Safe Mode - Default WordPress Uninstall behavior).
if ( empty( $authorized_timestamp ) ) {
	stackboost_uninstall_log( 'Authorization check FAILED: Timestamp is empty. Exiting (Safe Mode).' );
    return;
}

// 2. Check Time Limit (5 Minutes)
// 5 minutes = 300 seconds.
if ( ( time() - $authorized_timestamp ) > 300 ) {
	stackboost_uninstall_log( 'Authorization check FAILED: Time limit exceeded. Exiting.' );
    return; // Authorization expired.
}

stackboost_uninstall_log( 'Authorization check PASSED. Loading Service...' );

// 3. Authorization Valid: Proceed with Data Wipe.

// We need to require the service file manually because the plugin is not loaded during uninstall.
require_once plugin_dir_path( __FILE__ ) . 'src/Services/UninstallService.php';

// Run the cleanup.
\StackBoost\ForSupportCandy\Services\UninstallService::run_clean_uninstall();

stackboost_uninstall_log( 'UninstallService::run_clean_uninstall() execution triggered.' );
