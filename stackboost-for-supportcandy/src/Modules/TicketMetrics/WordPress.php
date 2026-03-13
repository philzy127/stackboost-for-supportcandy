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
		$type_field = isset( $_POST['type_field'] ) ? sanitize_text_field( wp_unslash( $_POST['type_field'] ) ) : 'category';

		// Save preference
		$options = get_option('stackboost_settings', []);
		$settings_changed = false;
		if ( ! isset( $options['ticket_metrics_type_field'] ) || $options['ticket_metrics_type_field'] !== $type_field ) {
			$options['ticket_metrics_type_field'] = $type_field;
			$settings_changed = true;
		}
		if ( $settings_changed ) {
			update_option( 'stackboost_settings', $options );
		}

		if ( function_exists( 'stackboost_log' ) ) {
			stackboost_log( "Ticket Metrics Request - Start: {$start_date}, End: {$end_date}, Type Field: {$type_field}", 'ticket_metrics' );
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

		// Define the "Closed" logic dynamically based on schema capabilities.
		// User data proves `is_active` is NOT reliable for closure state on modern SC, but `date_closed` is.
		if ( $has_date_closed ) {
			$closed_condition = "date_closed IS NOT NULL AND date_closed != '0000-00-00 00:00:00'";
			$open_condition   = "(date_closed IS NULL OR date_closed = '0000-00-00 00:00:00')";
			$close_date_col   = "date_closed";
		} else {
			$closed_condition = "is_active = 0";
			$open_condition   = "is_active = 1";
			$close_date_col   = "date_updated";
		}

		// Total Tickets Closed
		// A ticket is considered closed in this range if its close date falls within the range.
		$total_closed = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(id) FROM {$tickets_table}
			 WHERE {$closed_condition}
			 AND {$close_date_col} >= %s AND {$close_date_col} <= %s",
			$start_dt, $end_dt
		) );
		$metrics['total_closed'] = $total_closed;

		// Average Time Ticket was Open (For Closed Tickets)
		$avg_open_query = $wpdb->prepare(
			"SELECT AVG(TIMESTAMPDIFF(SECOND, date_created, {$close_date_col}))
			 FROM {$tickets_table}
			 WHERE {$closed_condition}
			 AND {$close_date_col} >= %s AND {$close_date_col} <= %s",
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

		// "Active in Period" Condition
		// A ticket is considered active during the selected timeframe if:
		// 1. It was created before the timeframe ended.
		// AND 2. It is EITHER still open, OR it was closed AFTER the timeframe started.
		$active_in_period_sql = "date_created <= %s AND ( {$open_condition} OR {$close_date_col} >= %s )";

		// Average Age of Open Tickets
		// For tickets that are still open AND were active during the selected date range.
		$avg_age_query = $wpdb->prepare(
			"SELECT AVG(TIMESTAMPDIFF(SECOND, date_created, UTC_TIMESTAMP()))
			 FROM {$tickets_table}
			 WHERE {$open_condition}
			 AND {$active_in_period_sql}",
			$end_dt, $start_dt
		);

		$raw_avg_age_result = $wpdb->get_var($avg_age_query);
		$avg_age_seconds = (int) $raw_avg_age_result;

		if ( $avg_age_seconds > 0 ) {
			$metrics['avg_age_open'] = $this->format_seconds($avg_age_seconds);
		} else {
			$metrics['avg_age_open'] = 'N/A';
		}

		// Average Initial Response Time
		// For tickets OPEN/ACTIVE AT ANY POINT during the selected range.
		$avg_response_query = $wpdb->prepare(
			"SELECT AVG(response_time) FROM (
				SELECT t.id,
				TIMESTAMPDIFF(SECOND, t.date_created, MIN(th.date_created)) as response_time
				FROM {$tickets_table} t
				JOIN {$threads_table} th ON t.id = th.ticket
				WHERE {$active_in_period_sql}
				AND th.date_created > t.date_created
				GROUP BY t.id
			) as response_times",
			$end_dt, $start_dt
		);

		$avg_response_seconds = (int) $wpdb->get_var($avg_response_query);

		if ( function_exists( 'stackboost_log' ) ) {
			stackboost_log( "Avg Response Time Query: " . $avg_response_query, 'ticket_metrics' );
			stackboost_log( "Avg Response Time Raw Result: " . $avg_response_seconds, 'ticket_metrics' );
		}

		$metrics['avg_initial_response'] = $avg_response_seconds > 0 ? $this->format_seconds($avg_response_seconds) : '0s';

		// Agent Breakdown
		$metrics['agent_breakdown'] = [];
		// SupportCandy stores assigned_agent as a string, sometimes piped '4|12'.
		$agent_query = $wpdb->prepare(
			"SELECT assigned_agent, id
			 FROM {$tickets_table}
			 WHERE {$active_in_period_sql}
			 AND assigned_agent IS NOT NULL AND assigned_agent != ''",
			$end_dt, $start_dt
		);
		$agent_results = $wpdb->get_results($agent_query);
		$agent_map = $this->get_agent_map($wpdb, $customer_table);

		$agent_tallies = [];

		if ( is_array( $agent_results ) ) {
			foreach ( $agent_results as $row ) {
				$agent_ids = explode('|', $row->assigned_agent);
				foreach ( $agent_ids as $a_id ) {
					$clean_id = (int) $a_id;
					if ( $clean_id > 0 ) {
						if ( ! isset( $agent_tallies[ $clean_id ] ) ) {
							$agent_tallies[ $clean_id ] = 0;
						}
						$agent_tallies[ $clean_id ]++;
					}
				}
			}
		}

		arsort($agent_tallies);

		foreach ( $agent_tallies as $a_id => $count ) {
			$name = $agent_map[$a_id] ?? 'Agent ' . $a_id;
			$metrics['agent_breakdown'][] = [
				'label' => $name,
				'value' => $count
			];
		}

		// Type Breakdown
		$metrics['type_breakdown'] = [];
		if ( preg_match( '/^[a-zA-Z0-9_]+$/', $type_field ) ) {
			$type_query = $wpdb->prepare(
				"SELECT `{$type_field}` as type_id, COUNT(id) as count
				 FROM {$tickets_table}
				 WHERE {$active_in_period_sql}
				 GROUP BY `{$type_field}`",
				$end_dt, $start_dt
			);
			$type_results = $wpdb->get_results($type_query);
			$type_map = $this->get_type_map($wpdb, $type_field, $categories_table, $priorities_table, $status_table, $options_table);

			if ( is_array($type_results) ) {
				foreach ( $type_results as $row ) {
					$name = $type_map[$row->type_id] ?? $row->type_id;
					if(empty($name)) $name = 'Unassigned';
					$metrics['type_breakdown'][] = [
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
			if ( is_array($results) ) {
				foreach ( $results as $r ) {
					$map[$r->id] = $r->name;
				}
			}
		} else {
			// Custom field options are stored in the custom fields table, not a generic options table in newer versions.
			// However, rather than query raw tables for custom field option maps (which is incredibly fragile as the schema changes heavily between versions),
			// we will use SupportCandy's native classes if available.
			if ( class_exists( '\WPSC_Custom_Field' ) ) {
				$cfs = \WPSC_Custom_Field::find( [ 'items_per_page' => 0 ] )['results'];
				foreach ( $cfs as $cf ) {
					if ( $cf->slug === $type_field && method_exists( $cf, 'get_options' ) ) {
						$options = $cf->get_options();
						if ( is_array( $options ) ) {
							foreach ( $options as $opt ) {
								$id = is_object( $opt ) ? $opt->id : ( $opt['id'] ?? '' );
								$name = is_object( $opt ) ? $opt->name : ( $opt['name'] ?? '' );
								if ( $id ) {
									$map[$id] = $name;
								}
							}
						}
						break;
					}
				}
			} else {
				// Fallback if class doesn't exist: attempt the raw table query but ensure it doesn't fatal error if table is missing.
				$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$options_table}'") === $options_table;
				if ( $table_exists ) {
					$results = $wpdb->get_results("SELECT id, name FROM {$options_table}");
					if ( is_array($results) ) {
						foreach ( $results as $r ) {
							$map[$r->id] = $r->name;
						}
					}
				}
			}
		}
		return $map;
	}
}
