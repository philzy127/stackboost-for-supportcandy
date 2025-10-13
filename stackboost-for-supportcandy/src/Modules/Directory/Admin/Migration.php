<?php
/**
 * StackBoost Company Directory Migration.
 *
 * This file handles the data migration from the old prefixes to the new `stackboost_` prefixes.
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
		$meta_keys_to_rename = array(
			'_chp_staff_job_title' => '_stackboost_staff_job_title',
			'_chp_location_is_complete' => '_stackboost_location_is_complete',
			'_needs_completion' => '_stackboost_needs_completion',
			'chp_staff_job_title' => 'stackboost_staff_job_title',
			'chp_needs_completion' => 'stackboost_needs_completion',
		);

		foreach ( $meta_keys_to_rename as $old_key => $new_key ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->postmeta} SET meta_key = %s WHERE meta_key = %s",
					$new_key,
					$old_key
				)
			);
		}
	}
}