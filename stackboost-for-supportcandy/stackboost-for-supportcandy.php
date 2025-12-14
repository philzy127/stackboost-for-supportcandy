<?php
/**
 * Plugin Name: StackBoost - For SupportCandy
 * Description: A collection of enhancements for the SupportCandy plugin, rebranded and refactored.
 * Version: 1.3.7
 * Author: StackBoost
 * Author URI: https://stackboost.net
 * Text Domain: stackboost-for-supportcandy
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define plugin constants.
define( 'STACKBOOST_VERSION', '1.3.7' );
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

// Include the bootstrap file to run the plugin.
require_once STACKBOOST_PLUGIN_PATH . 'bootstrap.php';