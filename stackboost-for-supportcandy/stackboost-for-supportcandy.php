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