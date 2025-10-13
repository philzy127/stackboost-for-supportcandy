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
		$this->run_one_time_flush();
	}

	/**
	 * One-time flush of rewrite rules.
	 *
	 * This routine flushes the rewrite rules if the data migration has already been completed.
	 * This fixes the "Invalid post type" error for users who updated to 3.0.0 and ran the migration.
	 */
	private function run_one_time_flush() {
		$flush_flag = 'stackboost_rules_flushed_for_3_0_2';
		if ( ! get_option( $flush_flag ) ) {
			if ( 'completed' === get_option( 'stackboost_directory_migration_status' ) ) {
				flush_rewrite_rules();
			}
			add_option( $flush_flag, true );
		}
	}
}