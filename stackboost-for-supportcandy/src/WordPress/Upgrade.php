<?php
/**
 * StackBoost Upgrade Routine.
 *
 * This file handles the automatic upgrade routines that run on plugin update.
 *
 * @package StackBoost
 * @subpackage WordPress
 */

namespace StackBoost\ForSupportCandy\WordPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Upgrade Class
 *
 * Handles the automatic upgrade routines.
 */
class Upgrade {

	/**
	 * The option name to store the plugin version.
	 *
	 * @var string
	 */
	const VERSION_OPTION = 'stackboost_version';

	/**
	 * Constructor.
	 */
	public function __construct() {
		stackboost_debug_log( 'Upgrade constructor started.' );
		add_action( 'admin_init', array( $this, 'run_upgrade_routines' ) );
		stackboost_debug_log( 'Upgrade constructor finished.' );
	}

	/**
	 * Run the upgrade routines.
	 */
	public function run_upgrade_routines() {
		stackboost_debug_log( 'Running upgrade routines.' );
		$stored_version = get_option( self::VERSION_OPTION, '0.0.0' );
		stackboost_debug_log( 'Stored version: ' . $stored_version . ', Current version: ' . STACKBOOST_VERSION );

		if ( version_compare( $stored_version, STACKBOOST_VERSION, '<' ) ) {
			stackboost_debug_log( 'Upgrade needed.' );
			$this->upgrade_to_3_0_1();
			update_option( self::VERSION_OPTION, STACKBOOST_VERSION );
			stackboost_debug_log( 'Version updated to ' . STACKBOOST_VERSION );
		}
	}

	/**
	 * Upgrade routine for version 3.0.1.
	 *
	 * This routine flushes the rewrite rules if the data migration has already been completed.
	 * This fixes the "Invalid post type" error for users who updated to 3.0.0 and ran the migration.
	 */
	private function upgrade_to_3_0_1() {
		stackboost_debug_log( 'Running upgrade_to_3_0_1.' );
		$migration_status = get_option( 'stackboost_directory_migration_status' );
		stackboost_debug_log( 'Migration status for upgrade: ' . $migration_status );
		if ( 'completed' === $migration_status ) {
			stackboost_debug_log( 'Flushing rewrite rules.' );
			flush_rewrite_rules();
		}
	}
}