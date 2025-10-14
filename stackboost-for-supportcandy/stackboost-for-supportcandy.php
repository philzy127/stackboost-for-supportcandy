<?php
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

// Include the bootstrap file.
require_once STACKBOOST_PLUGIN_PATH . 'bootstrap.php';

/**
 * Flush rewrite rules on plugin activation.
 *
 * This ensures that the new custom post type slugs are recognized by WordPress.
 */
function stackboost_activate() {
	error_log('[StackBoost DEBUG] Plugin activation hook fired.');
	// Manually trigger the plugin's initialization to ensure CPTs are registered.
	stackboost_run();
	error_log('[StackBoost DEBUG] stackboost_run() called to register CPTs.');
	// Now flush the rewrite rules.
	flush_rewrite_rules();
	error_log('[StackBoost DEBUG] flush_rewrite_rules() called.');
}
register_activation_hook( __FILE__, 'stackboost_activate' );