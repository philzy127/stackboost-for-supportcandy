<?php
/**
 * StackBoost Company Directory Migration.
 *
 * This file handles the data migration from the old `chp_` prefixes to the new `stackboost_` prefixes.
 *
 * @package StackBoost
 * @subpackage Modules\Directory\Admin
 */

namespace StackBoost\ForSupportCandy\Modules\Directory\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Migration Class
 *
 * Handles the data migration.
 */
class Migration {

	/**
	 * Run the migration.
	 */
	public static function run() {
		global $wpdb;

		// Rename post types.
		$wpdb->query( "UPDATE {$wpdb->posts} SET post_type = 'stackboost_staff_directory' WHERE post_type = 'chp_staff_directory'" );
		$wpdb->query( "UPDATE {$wpdb->posts} SET post_type = 'stackboost_location' WHERE post_type = 'chp_location'" );
		$wpdb->query( "UPDATE {$wpdb->posts} SET post_type = 'stackboost_department' WHERE post_type = 'chp_department'" );

		// Rename meta keys.
		$wpdb->query( "UPDATE {$wpdb->postmeta} SET meta_key = '_stackboost_staff_job_title' WHERE meta_key = '_chp_staff_job_title'" );
		$wpdb->query( "UPDATE {$wpdb->postmeta} SET meta_key = '_stackboost_location_is_complete' WHERE meta_key = '_chp_location_is_complete'" );
		$wpdb->query( "UPDATE {$wpdb->postmeta} SET meta_key = '_stackboost_needs_completion' WHERE meta_key = '_needs_completion'" );
	}
}