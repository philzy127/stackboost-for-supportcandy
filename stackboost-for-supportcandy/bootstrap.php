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

/**
 * Central logging function for the plugin.
 *
 * @param mixed  $message The message or data to log.
 * @param string $context A slug to identify the logging context (e.g., 'module-utm', 'core').
 */
function stackboost_log( $message, $context = 'general' ) {
    $options = get_option( 'stackboost_settings', [] );

    // 1. Master Kill Switch
    if ( empty( $options['diagnostic_log_enabled'] ) ) {
        return;
    }

    // 2. Define Context to Setting Mapping
    // Maps the log context string to the specific settings key that controls it.
    // If a context maps to 'enable_log_general', it is controlled by the General Settings toggle.
    $context_map = [
        // General / Core
        'general'              => 'enable_log_general',
        'core'                 => 'enable_log_general',
        'error'                => 'enable_log_general',

        // Modules
        'module-utm'           => 'enable_log_utm',
        'ats'                  => 'enable_log_ats',
        'onboarding'           => 'enable_log_onboarding',
        'date_time_formatting' => 'enable_log_date_time',
        'ticket_view'          => 'enable_log_ticket_view',
        'after_hours'          => 'enable_log_after_hours',
        'conditional_views'    => 'enable_log_conditional_views',
        'queue_macro'          => 'enable_log_queue_macro',
        'appearance'           => 'enable_log_appearance',
        'chat_bubbles'         => 'enable_log_chat_bubbles',

        // Directory (handles multiple contexts)
        'directory-import'     => 'enable_log_directory',
        'directory-error'      => 'enable_log_directory',

        // Future mappings can be added here as features adopt logging.
    ];

    // 3. Determine the controlling setting for this context
    $setting_key = $context_map[ $context ] ?? 'enable_log_general'; // Default to General if unknown context

    // 4. Check the specific module setting
    // If the key exists in options, check its boolean value.
    // We assume that if the key is missing from options (e.g. fresh install), it defaults to false (off).
    if ( empty( $options[ $setting_key ] ) ) {
        return;
    }

    $upload_dir = wp_upload_dir();
    $log_dir    = $upload_dir['basedir'] . '/stackboost-logs';
    $log_file   = $log_dir . '/debug.log';

    // Create the directory if it doesn't exist.
    if ( ! is_dir( $log_dir ) ) {
        wp_mkdir_p( $log_dir );
    }

    // Check for log rotation.
    if ( file_exists( $log_file ) && filesize( $log_file ) > 5 * MB_IN_BYTES ) {
        // Simple rotation: just clear the file.
        file_put_contents( $log_file, '' );
    }

    // Format the message.
    $timestamp = date( 'Y-m-d H:i:s' );
    $entry     = sprintf( "[%s] [%s] ", $timestamp, $context );

    if ( is_array( $message ) || is_object( $message ) ) {
        $entry .= print_r( $message, true );
    } else {
        $entry .= $message;
    }

    $entry .= PHP_EOL;

    // Append to the log file.
    file_put_contents( $log_file, $entry, FILE_APPEND );
}

// Get the plugin running.
add_action( 'plugins_loaded', 'stackboost_run' );

// Initialize upgrade routines.
add_action( 'plugins_loaded', array( 'StackBoost\ForSupportCandy\Admin\Upgrade', 'init' ) );
