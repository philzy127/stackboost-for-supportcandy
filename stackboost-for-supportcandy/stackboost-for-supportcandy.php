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
	// The CPTs are registered in the bootstrap file, so we need to ensure they are registered before flushing.
	// Normally, you would call the registration function directly here, but since it's hooked to 'init',
	// we'll just flush the rules and rely on the next page load to have the CPTs registered.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'stackboost_activate' );