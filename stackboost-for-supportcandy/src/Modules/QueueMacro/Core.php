<?php

namespace StackBoost\ForSupportCandy\Modules\QueueMacro;

/**
 * Core business logic for the Queue Macro feature.
 *
 * This class is responsible for calculating queue counts based on various
 * ticket properties. It is designed to work with a generic database adapter
 * to remain decoupled from WordPress's $wpdb global.
 *
 * @package StackBoost\ForSupportCandy\Modules\QueueMacro
 */
class Core {

	/**
	 * Calculate the queue count for a specific ticket type.
	 *
	 * @param object $db         The database adapter (e.g., $wpdb).
	 * @param string $type_field The database column that defines the queue (e.g., 'category').
	 * @param mixed  $type_value The specific value of the queue type (e.g., 'billing_support').
	 * @param array  $statuses   An array of status IDs to include in the count.
	 *
	 * @return int The number of tickets in the specified queue.
	 */
	public function calculate_queue_count( object $db, string $type_field, $type_value, array $statuses ): int {
		if ( empty( $type_field ) || empty( $statuses ) || is_null( $type_value ) || $type_value === '' ) {
			return 0;
		}

		$table        = $db->prefix . 'psmsc_tickets';
		$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%d' ) );

		// Basic validation for the column name to prevent obvious SQL injection.
		// A more robust solution might involve a whitelist of allowed columns.
		if ( ! preg_match( '/^[a-zA-Z0-9_]+$/', $type_field ) ) {
			return 0;
		}

		// The column name is correctly escaped using backticks.
		$sql = $db->prepare(
			"SELECT COUNT(*) FROM `{$table}` WHERE `{$type_field}` = %s AND `status` IN ($placeholders)",
			array_merge( [ $type_value ], $statuses )
		);

		return (int) $db->get_var( $sql );
	}

	/**
	 * Get counts for all queues based on a type field.
	 *
	 * @param object $db         The database adapter.
	 * @param string $type_field The column that defines the queue.
	 * @param array  $statuses   An array of status IDs to count.
	 *
	 * @return array An associative array of ['Queue Name' => count].
	 */
	public function get_all_queue_counts( object $db, string $type_field, array $statuses ): array {
		if ( empty( $statuses ) || ! preg_match( '/^[a-zA-Z0-9_]+$/', $type_field ) ) {
			return [];
		}

		$id_to_name_map = $this->get_id_to_name_map( $db );
		$table          = $db->prefix . 'psmsc_tickets';

		// Get all unique values for the given type field.
		$type_values_query = "SELECT DISTINCT `{$type_field}` FROM `{$table}`";
		$type_values       = $db->get_col( $type_values_query );

		$results      = [];
		$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%d' ) );

		foreach ( $type_values as $type_value ) {
			$sql = $db->prepare(
				"SELECT COUNT(*) FROM `{$table}` WHERE `{$type_field}` = %s AND `status` IN ($placeholders)",
				array_merge( [ $type_value ], $statuses )
			);
			$count = $db->get_var( $sql );

			// Use the mapped name if available, otherwise use the raw value.
			$name = $id_to_name_map[ $type_value ] ?? $type_value;
			if ( $count > 0 ) {
				$results[ $name ] = $count;
			}
		}
		arsort( $results );
		return $results;
	}

	/**
	 * Creates a comprehensive map of IDs to human-readable names from various SC tables.
	 *
	 * @param object $db The database adapter.
	 *
	 * @return array A map where key is an ID and value is the name.
	 */
	private function get_id_to_name_map( object $db ): array {
		$map = [];
		$tables = [
			'psmsc_options'    => 'wpya_psmsc_options', // Custom field options have a different prefix convention in the old code.
			'psmsc_statuses'   => $db->prefix . 'psmsc_statuses',
			'psmsc_priorities' => $db->prefix . 'psmsc_priorities',
			'psmsc_categories' => $db->prefix . 'psmsc_categories',
		];

		foreach ( $tables as $name => $table_name ) {
            // Check if table exists before querying.
            if ($db->get_var($db->prepare("SHOW TABLES LIKE %s", $table_name)) !== $table_name) {
                continue;
            }

			$results = $db->get_results( "SELECT id, name FROM `{$table_name}`" );
			if ( $results ) {
				foreach ( $results as $result ) {
					$map[ $result->id ] = $result->name;
				}
			}
		}
		return $map;
	}
}