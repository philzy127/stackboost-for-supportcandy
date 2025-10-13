<?php
/**
 * Plugin Name: StackBoost - For SupportCandy
 * Description: A collection of enhancements for the SupportCandy plugin, rebranded and refactored.
 * Version: 3.0.0
 * Author: Jules
 * Author URI: https://example.com
 * Text Domain: stackboost-for-supportcandy
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define plugin constants.
define( 'STACKBOOST_VERSION', '3.0.0' );
define( 'STACKBOOST_PLUGIN_FILE', __FILE__ );
define( 'STACKBOOST_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'STACKBOOST_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Include the bootstrap file.
require_once STACKBOOST_PLUGIN_PATH . 'bootstrap.php';

/**
 * Upgrade routine.
 *
 * Runs on admin initialization to check for version changes and
 * trigger data migrations if necessary.
 */
function stackboost_upgrade_routine() {
	$current_db_version = get_option( 'stackboost_version', '0.0.0' );

	if ( version_compare( $current_db_version, STACKBOOST_VERSION, '<' ) ) {
		// A more robust system would check version ranges, but for this specific task, this is sufficient.
		if ( version_compare( $current_db_version, '3.0.0', '<' ) ) {
			// Check if the migration class exists before including.
			if ( file_exists( STACKBOOST_PLUGIN_PATH . 'src/Modules/Directory/Admin/Migration.php' ) ) {
				require_once STACKBOOST_PLUGIN_PATH . 'src/Modules/Directory/Admin/Migration.php';
				\StackBoost\ForSupportCandy\Modules\Directory\Admin\Migration::run();
			}
		}

		// Update the version in the database to the current plugin version.
		update_option( 'stackboost_version', STACKBOOST_VERSION );
	}
}
add_action( 'admin_init', 'stackboost_upgrade_routine' );