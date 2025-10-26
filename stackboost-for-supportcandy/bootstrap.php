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
try {
	stackboost_run();
	// Initialize upgrade routines.
	add_action( 'plugins_loaded', array( 'StackBoost\ForSupportCandy\Admin\Upgrade', 'init' ) );
} catch ( \Throwable $e ) {
	$error_message = sprintf(
		"[%s] FATAL ERROR on activation: %s in %s on line %d\n",
		gmdate( 'Y-m-d H:i:s' ),
		$e->getMessage(),
		$e->getFile(),
		$e->getLine()
	);
	file_put_contents( __DIR__ . '/stackboost-error.log', $error_message, FILE_APPEND );
}