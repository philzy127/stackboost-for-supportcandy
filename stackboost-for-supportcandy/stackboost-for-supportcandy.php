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
 * Display an admin notice if the migration needs to be run.
 */
function stackboost_migration_notice() {
	if ( ! get_option( 'stackboost_migration_complete' ) ) {
		$migration_url = add_query_arg( array( 'stackboost_migrate' => 'true' ) );
		echo '<div class="notice notice-warning is-dismissible">';
		echo '<p>' . esc_html__( 'The StackBoost plugin needs to update your database to be compatible with the latest version. Please back up your database and then', 'stackboost-for-supportcandy' ) . ' <a href="' . esc_url( $migration_url ) . '">' . esc_html__( 'click here to run the migration', 'stackboost-for-supportcandy' ) . '</a>.</p>';
		echo '</div>';
	}
}
add_action( 'admin_notices', 'stackboost_migration_notice' );

/**
 * Handle the migration trigger.
 */
function stackboost_handle_migration() {
	if ( isset( $_GET['stackboost_migrate'] ) && 'true' === $_GET['stackboost_migrate'] ) {
		if ( file_exists( STACKBOOST_PLUGIN_PATH . 'src/Modules/Directory/Admin/Migration.php' ) ) {
			require_once STACKBOOST_PLUGIN_PATH . 'src/Modules/Directory/Admin/Migration.php';
			\StackBoost\ForSupportCandy\Modules\Directory\Admin\Migration::run();
			update_option( 'stackboost_migration_complete', true );
			wp_safe_redirect( remove_query_arg( 'stackboost_migrate' ) );
			exit;
		}
	}
}
add_action( 'admin_init', 'stackboost_handle_migration' );