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

		$log_file = fopen( \STACKBOOST_PLUGIN_PATH . 'migration_debug.log', 'w' );
		fwrite( $log_file, "Migration started at " . date( 'Y-m-d H:i:s' ) . "\n" );

		// Rename post types.
		$queries = array(
			"UPDATE {$wpdb->posts} SET post_type = 'stackboost_staff_directory' WHERE post_type = 'chp_staff_directory'",
			"UPDATE {$wpdb->posts} SET post_type = 'stackboost_location' WHERE post_type = 'chp_location'",
			"UPDATE {$wpdb->posts} SET post_type = 'stackboost_department' WHERE post_type = 'chp_department'",
		);

		foreach ( $queries as $query ) {
			$result = $wpdb->query( $query );
			fwrite( $log_file, "Query: {$query} | Rows affected: {$result}\n" );
		}

		// Rename meta keys.
		$meta_keys_to_rename = array(
			'_chp_staff_job_title' => '_stackboost_staff_job_title',
			'_chp_location_is_complete' => '_stackboost_location_is_complete',
			'_needs_completion' => '_stackboost_needs_completion',
			'chp_staff_job_title' => 'stackboost_staff_job_title',
			'chp_needs_completion' => 'stackboost_needs_completion',
		);

		foreach ( $meta_keys_to_rename as $old_key => $new_key ) {
			$query = $wpdb->prepare(
				"UPDATE {$wpdb->postmeta} SET meta_key = %s WHERE meta_key = %s",
				$new_key,
				$old_key
			);
			$result = $wpdb->query( $query );
			fwrite( $log_file, "Query: {$query} | Rows affected: {$result}\n" );
		}

		fwrite( $log_file, "Migration finished at " . date( 'Y-m-d H:i:s' ) . "\n" );
		fclose( $log_file );
	}
}