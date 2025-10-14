<?php
error_log('[StackBoost DEBUG] Main plugin file loading...');
/**
 * Plugin Name: StackBoost - For SupportCandy
 * Description: A collection of enhancements for the SupportCandy plugin, rebranded and refactored.
 * Version: 1.0.0
 * Author: Jules
 * Author URI: https://example.com
 * Text Domain: stackboost-for-supportcandy
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define plugin constants.
define( 'STACKBOOST_VERSION', '1.0.0' );
define( 'STACKBOOST_PLUGIN_FILE', __FILE__ );
define( 'STACKBOOST_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'STACKBOOST_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'STACKBOOST_REQUIRED_UPDATE_TIMESTAMP', '202510131839' );

/**
 * Handles one-time upgrade routines.
 *
 * This function runs on 'admin_init' and checks if the plugin has been updated
 * by comparing a timestamp in the code with one in the database. If an update
 * is needed, it runs the necessary functions.
 */
function stackboost_upgrade_routine() {
	if ( ! is_admin() ) {
		error_log('[StackBoost DEBUG] Upgrade routine: exiting, not admin area.');
		return;
	}

	$last_update = get_option( 'stackboost_last_update_completed_timestamp', 0 );
	error_log('[StackBoost DEBUG] Upgrade routine: Got last update timestamp: ' . $last_update);

	error_log('[StackBoost DEBUG] Checking for upgrades. Required timestamp: ' . STACKBOOST_REQUIRED_UPDATE_TIMESTAMP . ', Completed timestamp: ' . $last_update);

	if ( STACKBOOST_REQUIRED_UPDATE_TIMESTAMP > $last_update ) {
		error_log('[StackBoost DEBUG] Upgrade needed. Flushing rewrite rules...');
		flush_rewrite_rules();
		error_log('[StackBoost DEBUG] ...flush_rewrite_rules() complete.');
		$update_result = update_option( 'stackboost_last_update_completed_timestamp', time() );
		error_log('[StackBoost DEBUG] Upgrade complete. Update option result: ' . ($update_result ? 'true' : 'false') . '. New completed timestamp: ' . time());
	} else {
		error_log('[StackBoost DEBUG] Upgrade not needed.');
	}
}
add_action( 'admin_init', 'stackboost_upgrade_routine' );

/**
 * Display a debug notice in the admin area.
 */
function stackboost_debug_admin_notice() {
    $last_update = get_option( 'stackboost_last_update_completed_timestamp', 'Not set' );
    ?>
    <div class="notice notice-warning">
        <p><strong>StackBoost Debug Notice:</strong></p>
        <ul>
            <li><strong>Required Update Timestamp:</strong> <?php echo esc_html( STACKBOOST_REQUIRED_UPDATE_TIMESTAMP ); ?></li>
            <li><strong>Last Update Completed:</strong> <?php echo esc_html( $last_update ); ?></li>
        </ul>
    </div>
    <?php
}
add_action( 'admin_notices', 'stackboost_debug_admin_notice' );

// Include the bootstrap file to run the plugin.
require_once STACKBOOST_PLUGIN_PATH . 'bootstrap.php';
error_log('[StackBoost DEBUG] Main plugin file finished loading.');