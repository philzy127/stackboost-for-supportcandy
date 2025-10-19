<?php
/**
 * Plugin Name: StackBoost - For SupportCandy
 * Description: A collection of enhancements for the SupportCandy plugin, rebranded and refactored.
 * Version: 1.2.1
 * Author: StackBoost
 * Author URI: https://example.com
 * Text Domain: stackboost-for-supportcandy
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define plugin constants.
define( 'STACKBOOST_VERSION', '1.2.1' );
define( 'STACKBOOST_PLUGIN_FILE', __FILE__ );
define( 'STACKBOOST_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'STACKBOOST_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'STACKBOOST_REQUIRED_UPDATE_TIMESTAMP', '202510131911' );

/**
 * Handles one-time upgrade routines.
 *
 * This function runs on 'admin_init' and checks if the plugin has been updated
 * by comparing a timestamp in the code with one in the database. If an update
 * is needed, it runs the necessary functions.
 */
function stackboost_upgrade_routine() {
	if ( ! is_admin() ) {
		return;
	}

	$last_update = get_option( 'stackboost_last_update_completed_timestamp', 0 );

	if ( STACKBOOST_REQUIRED_UPDATE_TIMESTAMP > $last_update ) {
		flush_rewrite_rules();
		update_option( 'stackboost_last_update_completed_timestamp', time() );
	}
}
add_action( 'admin_init', 'stackboost_upgrade_routine' );

// --- BEGIN UNMISTAKABLE TICKET PAGE LOGGING ---
function stackboost_ticket_page_diagnostic_logs() {
    $screen = get_current_screen();
    if ( $screen && 'wpsc_ticket' === $screen->post_type ) {
        // PHP Log for Ticket Page
        error_log('****************************************************************');
        error_log('*** STACKBOOST PHP LOG: TICKET PAGE IS LOADING ***');
        error_log('****************************************************************');

        // JavaScript Console Log for Ticket Page
        echo "<script>console.log('**************************************************');</script>";
        echo "<script>console.log('*** STACKBOOST JS CONSOLE: TICKET PAGE SCRIPT IS RUNNING ***');</script>";
        echo "<script>console.log('**************************************************');</script>";
    }
}
add_action('admin_footer', 'stackboost_ticket_page_diagnostic_logs');
// --- END UNMISTAKABLE TICKET PAGE LOGGING ---

// Include the bootstrap file to run the plugin.
require_once STACKBOOST_PLUGIN_PATH . 'bootstrap.php';