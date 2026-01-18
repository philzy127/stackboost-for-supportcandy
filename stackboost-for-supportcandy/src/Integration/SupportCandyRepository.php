<?php

namespace StackBoost\ForSupportCandy\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Repository for interacting with SupportCandy's custom database tables.
 *
 * Encapsulates direct database queries to external (SupportCandy) tables
 * to centralize logic and suppressions.
 *
 * @package StackBoost\ForSupportCandy\Integration
 */
class SupportCandyRepository {

	/**
	 * Get all SupportCandy custom fields.
	 *
	 * @return array List of custom fields with 'slug' and 'name'.
	 */
	public function get_custom_fields(): array {
		global $wpdb;
		$custom_fields_table = $wpdb->prefix . 'psmsc_custom_fields';
		$safe_table          = $custom_fields_table;

		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results( "SELECT slug, name FROM `{$safe_table}`", ARRAY_A );

		return $results ?: [];
	}

	/**
	 * Get all SupportCandy statuses.
	 *
	 * @return array List of statuses with 'id' and 'name'.
	 */
	public function get_statuses(): array {
		global $wpdb;
		$status_table = $wpdb->prefix . 'psmsc_statuses';
		$safe_table   = $status_table;

		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results( "SELECT id, name FROM `{$safe_table}` ORDER BY name ASC" );

		return $results ?: [];
	}

	/**
	 * Get a custom field ID by its name.
	 *
	 * @param string $field_name The name of the custom field.
	 * @return int The ID or 0 if not found.
	 */
	public function get_custom_field_id_by_name( string $field_name ): int {
		global $wpdb;
		if ( empty( $field_name ) ) {
			return 0;
		}

		$table_name      = $wpdb->prefix . 'psmsc_custom_fields';
		$table_name_like = $wpdb->esc_like( $table_name );

		// Check if table exists first (optional, but preserved from legacy logic if robust)
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name_like ) ) !== $table_name ) {
			return 0;
		}

		$safe_table = $table_name;
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$field_id = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT id FROM `{$safe_table}` WHERE name = %s",
				$field_name
			)
		);

		return $field_id ? (int) $field_id : 0;
	}
}
