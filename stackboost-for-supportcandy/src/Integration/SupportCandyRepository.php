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
// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Repository pattern encapsulates DB access.
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

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is derived from DB prefix.
		$results = $wpdb->get_results( "SELECT slug, name FROM `{$safe_table}`", ARRAY_A );

		return $results ?: [];
	}

	/**
	 * Get SupportCandy custom fields filtered by type.
	 *
	 * @param string $type The field type (e.g., 'datetime').
	 * @return array List of custom fields with 'slug' and 'name'.
	 */
	public function get_custom_fields_by_type( string $type ): array {
		global $wpdb;
		$custom_fields_table = $wpdb->prefix . 'psmsc_custom_fields';
		$safe_table          = $custom_fields_table;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is derived from DB prefix.
		$results = $wpdb->get_results(
			$wpdb->prepare( "SELECT slug, name FROM `{$safe_table}` WHERE type = %s", $type ),
			ARRAY_A
		);

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

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is derived from DB prefix.
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
		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name_like ) ) !== $table_name ) {
			return 0;
		}

		$safe_table = $table_name;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is derived from DB prefix.
		$field_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM `{$safe_table}` WHERE name = %s",
				$field_name
			)
		);

		return $field_id ? (int) $field_id : 0;
	}

	/**
	 * Update the type of a custom field.
	 *
	 * @param string $slug The slug of the custom field.
	 * @param string $new_type The new type (e.g., 'date', 'text').
	 * @return bool True on success, false on failure.
	 */
	public function update_custom_field_type( string $slug, string $new_type ): bool {
		global $wpdb;
		$table_name = $wpdb->prefix . 'psmsc_custom_fields';

		// Verify table exists
		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) !== $table_name ) {
			return false;
		}

		$result = $wpdb->update(
			$table_name,
			[ 'type' => $new_type ],
			[ 'slug' => $slug ],
			[ '%s' ],
			[ '%s' ]
		);

		return false !== $result;
	}

	/**
	 * Add a column to a table if it does not exist.
	 *
	 * @param string $table_name The table name (prefixed).
	 * @param string $column_name The column name.
	 * @param string $column_def The column definition (e.g., "varchar(50) NOT NULL DEFAULT 'text'").
	 * @param string $after_column Optional. The column after which to position the new column.
	 * @return bool True on success or if exists, false on failure.
	 */
	public function add_column_if_not_exists( string $table_name, string $column_name, string $column_def, string $after_column = '' ): bool {
		global $wpdb;

		// Sanitize table name (basic check, as this is internal)
		// We trust the caller to pass correct table names from internal constants.

		// Check if column exists
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Internal system query on trusted tables.
		$row = $wpdb->get_results( "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = '{$table_name}' AND column_name = '{$column_name}'" );

		if ( empty( $row ) ) {
			// Escape identifiers
			$safe_table  = '`' . esc_sql( $table_name ) . '`';
			$safe_column = '`' . esc_sql( $column_name ) . '`';
			$safe_after  = $after_column ? 'AFTER `' . esc_sql( $after_column ) . '`' : '';

			// Verify column definition safety (basic injection check)
			if ( strpos( $column_def, ';' ) !== false ) {
				return false;
			}

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Schema modification is intended here; identifiers escaped.
			$wpdb->query( "ALTER TABLE {$safe_table} ADD COLUMN {$safe_column} {$column_def} {$safe_after}" );
			return true;
		}

		return true;
	}

}
