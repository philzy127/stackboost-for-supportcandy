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
		add_action( 'admin_init', array( $this, 'run_upgrade_routines' ) );
	}

	/**
	 * Run the upgrade routines.
	 */
	public function run_upgrade_routines() {
		$stored_version = get_option( self::VERSION_OPTION, '0.0.0' );

		if ( version_compare( $stored_version, STACKBOOST_VERSION, '<' ) ) {
			$this->upgrade_to_3_0_1();
			update_option( self::VERSION_OPTION, STACKBOOST_VERSION );
		}
	}

	/**
	 * Upgrade routine for version 3.0.1.
	 *
	 * This routine flushes the rewrite rules if the data migration has already been completed.
	 * This fixes the "Invalid post type" error for users who updated to 3.0.0 and ran the migration.
	 */
	private function upgrade_to_3_0_1() {
		if ( 'completed' === get_option( 'stackboost_directory_migration_status' ) ) {
			flush_rewrite_rules();
		}
	}
}