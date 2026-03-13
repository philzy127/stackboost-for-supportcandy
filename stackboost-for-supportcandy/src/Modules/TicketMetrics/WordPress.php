<?php

namespace StackBoost\ForSupportCandy\Modules\TicketMetrics;

if ( ! defined( 'ABSPATH' ) ) exit;

use StackBoost\ForSupportCandy\Core\Module;

/**
 * Main class for the Ticket Metrics module.
 */
class WordPress extends Module {

	private static ?WordPress $instance = null;

	public static function get_instance(): WordPress {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function get_slug(): string {
		return 'ticket_metrics';
	}

	public function init_hooks() {
		if ( ! stackboost_is_feature_active( $this->get_slug() ) ) {
			return;
		}

		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'wp_ajax_stackboost_get_ticket_metrics', [ $this, 'ajax_get_metrics' ] );
	}

	public function register_settings() {
		// Basic setting if we ever want to toggle it or add preferences
	}

	public function ajax_get_metrics() {
		check_ajax_referer( 'stackboost_admin_nonce', 'nonce' );

		if ( ! current_user_can( STACKBOOST_CAP_MANAGE_TICKET_METRICS ) ) {
			wp_send_json_error( __( 'Permission denied.', 'stackboost-for-supportcandy' ) );
		}

		$start_date = isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : '';
		$end_date   = isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : '';
		$breakdown  = isset( $_POST['breakdown'] ) ? sanitize_text_field( wp_unslash( $_POST['breakdown'] ) ) : 'none';
		$type_field = isset( $_POST['type_field'] ) ? sanitize_text_field( wp_unslash( $_POST['type_field'] ) ) : 'category';

		if ( function_exists( 'stackboost_log' ) ) {
			stackboost_log( "Ticket Metrics Request - Start: {$start_date}, End: {$end_date}, Breakdown: {$breakdown}, Type Field: {$type_field}", 'ticket_metrics' );
		}

		if ( empty( $start_date ) || empty( $end_date ) ) {
			wp_send_json_error( __( 'Start and End dates are required.', 'stackboost-for-supportcandy' ) );
		}

		global $wpdb;

		// Convert dates to Y-m-d H:i:s range
		$start_dt = date( 'Y-m-d 00:00:00', strtotime( $start_date ) );
		$end_dt   = date( 'Y-m-d 23:59:59', strtotime( $end_date ) );

		$metrics = [];

		$tickets_table = $wpdb->prefix . 'psmsc_tickets';
		$threads_table = $wpdb->prefix . 'psmsc_ticket_threads';

		// Check if the old prefix is used or the new prefix is used
		if ( $wpdb->get_var("SHOW TABLES LIKE '{$tickets_table}'") !== $tickets_table ) {
			$tickets_table = $wpdb->prefix . 'wpsc_tickets';
			$threads_table = $wpdb->prefix . 'wpsc_ticket_threads';
			$status_table  = $wpdb->prefix . 'wpsc_statuses';
			$customer_table = $wpdb->prefix . 'wpsc_customers';
			$categories_table = $wpdb->prefix . 'wpsc_categories';
			$priorities_table = $wpdb->prefix . 'wpsc_priorities';
			$options_table = $wpdb->prefix . 'wpsc_options';
		} else {
			$status_table  = $wpdb->prefix . 'psmsc_statuses';
			$customer_table = $wpdb->prefix . 'psmsc_customers';
			$categories_table = $wpdb->prefix . 'psmsc_categories';
			$priorities_table = $wpdb->prefix . 'psmsc_priorities';
			$options_table = $wpdb->prefix . 'psmsc_options';
		}

		// Is `date_closed` explicitly available?
		$has_date_closed = $wpdb->get_var("SHOW COLUMNS FROM {$tickets_table} LIKE 'date_closed'") === 'date_closed';
		$close_date_column = $has_date_closed ? 'date_closed' : 'date_updated';

		// Total Tickets Created
		$total_created = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(id) FROM {$tickets_table} WHERE date_created >= %s AND date_created <= %s",
			$start_dt, $end_dt
		) );
		$metrics['total_created'] = $total_created;

		// Total Tickets Closed
		$total_closed = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(id) FROM {$tickets_table} WHERE is_active = 0 AND {$close_date_column} >= %s AND {$close_date_column} <= %s",
			$start_dt, $end_dt
		) );
		$metrics['total_closed'] = $total_closed;

		// Average Time Ticket was Open
		// SupportCandy relies on `is_active = 0` directly on the tickets table to mark a ticket as closed.
		$avg_open_query = $wpdb->prepare(
			"SELECT AVG(TIMESTAMPDIFF(SECOND, date_created, {$close_date_column}))
			 FROM {$tickets_table}
			 WHERE is_active = 0
			 AND {$close_date_column} >= %s AND {$close_date_column} <= %s",
			$start_dt, $end_dt
		);

		$raw_avg_open_result = $wpdb->get_var($avg_open_query);
		$avg_open_seconds = (int) $raw_avg_open_result;

		if ( function_exists( 'stackboost_log' ) ) {
			stackboost_log( "Avg Open Time Query: " . $avg_open_query, 'ticket_metrics' );
			stackboost_log( "Avg Open Time Raw Result: " . var_export($raw_avg_open_result, true), 'ticket_metrics' );
			if ( ! empty( $wpdb->last_error ) ) {
				stackboost_log( "SQL Error: " . $wpdb->last_error, 'ticket_metrics' );
			}
		}

		if ( $avg_open_seconds > 0 ) {
			$metrics['avg_open_time'] = $this->format_seconds($avg_open_seconds);
		} else {
			$metrics['avg_open_time'] = 'N/A';
		}

		// Average Initial Response Time
		// For tickets created in the range
		// First reply from a user who is not the creator, and is not internal
		$avg_response_query = $wpdb->prepare(
			"SELECT AVG(response_time) FROM (
				SELECT t.id,
				TIMESTAMPDIFF(SECOND, t.date_created, MIN(th.date_created)) as response_time
				FROM {$tickets_table} t
				JOIN {$threads_table} th ON t.id = th.ticket
				WHERE t.date_created >= %s AND t.date_created <= %s
				AND th.customer != t.customer
				AND th.is_internal = 0
				GROUP BY t.id
			) as response_times",
			$start_dt, $end_dt
		);

		$avg_response_seconds = (int) $wpdb->get_var($avg_response_query);
		$metrics['avg_initial_response'] = $this->format_seconds($avg_response_seconds);

		// Breakdown
		$metrics['breakdown_data'] = [];
		if ( $breakdown === 'agent' ) {
			// Breakdown by assigned agent
			$agent_query = $wpdb->prepare(
				"SELECT agent, COUNT(id) as count
				 FROM {$tickets_table}
				 WHERE date_created >= %s AND date_created <= %s
				 AND agent > 0
				 GROUP BY agent",
				$start_dt, $end_dt
			);
			$agent_results = $wpdb->get_results($agent_query);
			$agent_map = $this->get_agent_map($wpdb, $customer_table);

			foreach ( $agent_results as $row ) {
				$name = $agent_map[$row->agent] ?? 'Agent ' . $row->agent;
				$metrics['breakdown_data'][] = [
					'label' => $name,
					'value' => $row->count
				];
			}
		} elseif ( $breakdown === 'type' ) {
			// Validate field name to prevent SQL injection
			if ( preg_match( '/^[a-zA-Z0-9_]+$/', $type_field ) ) {
				$type_query = $wpdb->prepare(
					"SELECT `{$type_field}` as type_id, COUNT(id) as count
					 FROM {$tickets_table}
					 WHERE date_created >= %s AND date_created <= %s
					 GROUP BY `{$type_field}`",
					$start_dt, $end_dt
				);
				$type_results = $wpdb->get_results($type_query);
				$type_map = $this->get_type_map($wpdb, $type_field, $categories_table, $priorities_table, $status_table, $options_table);

				foreach ( $type_results as $row ) {
					$name = $type_map[$row->type_id] ?? $row->type_id;
					if(empty($name)) $name = 'Unassigned';
					$metrics['breakdown_data'][] = [
						'label' => $name,
						'value' => $row->count
					];
				}
			}
		}

		if ( function_exists( 'stackboost_log' ) ) {
			stackboost_log( "Ticket Metrics Generated: " . json_encode($metrics), 'ticket_metrics' );
		}

		wp_send_json_success( $metrics );
	}

	private function format_seconds( $seconds ) {
		if ( ! $seconds ) return '0s';

		$parts = [];
		$days = floor($seconds / 86400);
		$hours = floor(($seconds % 86400) / 3600);
		$minutes = floor(($seconds % 3600) / 60);
		$secs = $seconds % 60;

		if ( $days > 0 ) $parts[] = $days . 'd';
		if ( $hours > 0 ) $parts[] = $hours . 'h';
		if ( $minutes > 0 ) $parts[] = $minutes . 'm';
		if ( empty($parts) || $secs > 0 ) $parts[] = $secs . 's';

		return implode(' ', $parts);
	}

	private function get_agent_map( $wpdb, $customer_table ) {
		$map = [];
		$results = $wpdb->get_results("SELECT id, name FROM {$customer_table} WHERE is_agent = 1");
		foreach ( $results as $r ) {
			$map[$r->id] = $r->name;
		}
		return $map;
	}

	private function get_type_map( $wpdb, $type_field, $categories_table, $priorities_table, $status_table, $options_table ) {
		$map = [];
		$table_name = '';
		if ( $type_field === 'category' ) $table_name = $categories_table;
		if ( $type_field === 'priority' ) $table_name = $priorities_table;
		if ( $type_field === 'status' ) $table_name = $status_table;

		if ( $table_name ) {
			$results = $wpdb->get_results("SELECT id, name FROM {$table_name}");
			foreach ( $results as $r ) {
				$map[$r->id] = $r->name;
			}
		} else {
			// Custom field options
			$results = $wpdb->get_results("SELECT id, name FROM {$options_table}");
			foreach ( $results as $r ) {
				$map[$r->id] = $r->name;
			}
		}
		return $map;
	}
}
