<?php

use StackBoost\ForSupportCandy\WordPress\Plugin;

/**
 * Autoloader for the plugin.
 *
 * @param string $class The fully-qualified class name.
 */
spl_autoload_register( function ( $class ) {
    // Top-level namespace.
    $prefix = 'StackBoost\\ForSupportCandy\\';

    // Base directory for the namespace prefix.
    $base_dir = __DIR__ . '/src/';

    // Does the class use the namespace prefix?
    $len = strlen( $prefix );
    if ( strncmp( $prefix, $class, $len ) !== 0 ) {
        // No, move to the next registered autoloader.
        return;
    }

    // Get the relative class name.
    $relative_class = substr( $class, $len );

    // Replace the namespace prefix with the base directory, replace namespace
    // separators with directory separators in the relative class name, append
    // with .php
    $file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

    // If the file exists, require it.
    if ( file_exists( $file ) ) {
        require $file;
    }
} );

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since 3.0.0
 */
function stackboost_run() {
	return Plugin::get_instance();
}

// Include helper functions.
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/src/WordPress/supportcandy-pro-check.php';

// Get the plugin running.
stackboost_run();

if ( ! function_exists( 'stackboost_log' ) ) {
	/**
	 * Custom logger for StackBoost.
	 *
	 * @param mixed $message The message to log.
	 */
	function stackboost_log( $message ) {
		$log_dir  = __DIR__ . '/logs';
		$log_file = $log_dir . '/debug.log';

		// Ensure the directory exists.
		if ( ! is_dir( $log_dir ) ) {
			// This will create the directory recursively.
			wp_mkdir_p( $log_dir );
		}

		$timestamp = date_i18n( 'Y-m-d H:i:s T' );
		$log_entry = '[' . $timestamp . '] ';

		if ( is_array( $message ) || is_object( $message ) ) {
			$log_entry .= print_r( $message, true );
		} else {
			$log_entry .= $message;
		}

		// Add a newline character.
		$log_entry .= "\n";

		// Write to the log file.
		file_put_contents( $log_file, $log_entry, FILE_APPEND );
	}
}

// Initialize upgrade routines.
add_action( 'plugins_loaded', array( 'StackBoost\ForSupportCandy\Admin\Upgrade', 'init' ) );