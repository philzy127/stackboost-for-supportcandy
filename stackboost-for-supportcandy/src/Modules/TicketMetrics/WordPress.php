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

		// Save preference securely using a standalone option to bypass the central settings sanitizer
		// which would otherwise reject programmatic updates missing a page_slug.
		if ( get_option( 'stackboost_ticket_metrics_type_field' ) !== $type_field ) {
			update_option( 'stackboost_ticket_metrics_type_field', $type_field );
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
		$threads_table = $wpdb->prefix . 'psmsc_threads';

		// Check if the old prefix is used or the new prefix is used
		if ( $wpdb->get_var("SHOW TABLES LIKE '{$tickets_table}'") !== $tickets_table ) {
			$tickets_table = $wpdb->prefix . 'wpsc_tickets';
			$threads_table = $wpdb->prefix . 'wpsc_threads';
			$status_table  = $wpdb->prefix . 'wpsc_statuses';
			$customer_table = $wpdb->prefix . 'wpsc_customers';
			$agents_table  = $wpdb->prefix . 'wpsc_agents';
			$categories_table = $wpdb->prefix . 'wpsc_categories';
			$priorities_table = $wpdb->prefix . 'wpsc_priorities';
			$options_table = $wpdb->prefix . 'wpsc_options';
		} else {
			$status_table  = $wpdb->prefix . 'psmsc_statuses';
			$customer_table = $wpdb->prefix . 'psmsc_customers';
			$agents_table  = $wpdb->prefix . 'psmsc_agents';
			$categories_table = $wpdb->prefix . 'psmsc_categories';
			$priorities_table = $wpdb->prefix . 'psmsc_priorities';
			$options_table = $wpdb->prefix . 'psmsc_options';
		}

		// Is `date_closed` explicitly available?
		$has_date_closed = $wpdb->get_var("SHOW COLUMNS FROM {$tickets_table} LIKE 'date_closed'") === 'date_closed';
		$close_date_column = $has_date_closed ? 'date_closed' : 'date_updated';

		// Define the "Closed" logic dynamically based on schema capabilities.
		if ( $has_date_closed ) {
			$closed_condition = "t.date_closed IS NOT NULL AND t.date_closed != '0000-00-00 00:00:00'";
			$open_condition   = "(t.date_closed IS NULL OR t.date_closed = '0000-00-00 00:00:00')";
			$close_date_col   = "t.date_closed";
		} else {
			$closed_condition = "t.is_active = 0";
			$open_condition   = "t.is_active = 1";
			$close_date_col   = "t.date_updated";
		}

		// Total Tickets Closed
		// A ticket is considered closed in this range if its close date falls within the range.
		$total_closed = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(t.id) FROM {$tickets_table} t
			 WHERE {$closed_condition}
			 AND {$close_date_col} >= %s AND {$close_date_col} <= %s",
			$start_dt, $end_dt
		) );
		$metrics['total_closed'] = $total_closed;

		// Average Time Ticket was Open (For Closed Tickets)
		$avg_open_query = $wpdb->prepare(
			"SELECT AVG(TIMESTAMPDIFF(SECOND, t.date_created, {$close_date_col}))
			 FROM {$tickets_table} t
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
		// Variables already contain 't.' prefixes.
		$active_in_period_sql = "t.date_created <= %s AND ( {$open_condition} OR {$close_date_col} >= %s )";

		// Average Age of Open Tickets
		// For tickets that are still open AND were active during the selected date range.
		$avg_age_query = $wpdb->prepare(
			"SELECT AVG(TIMESTAMPDIFF(SECOND, t.date_created, UTC_TIMESTAMP()))
			 FROM {$tickets_table} t
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

		$raw_avg_response = $wpdb->get_var($avg_response_query);
		$avg_response_seconds = (int) $raw_avg_response;

		if ( function_exists( 'stackboost_log' ) ) {
			stackboost_log( "Avg Response Time Query: " . $avg_response_query, 'ticket_metrics' );
			stackboost_log( "Avg Response Time Raw Result: " . var_export($raw_avg_response, true), 'ticket_metrics' );

			if ( ! empty( $wpdb->last_error ) ) {
				stackboost_log( "SQL Error: " . $wpdb->last_error, 'ticket_metrics' );
			}

			// Deep diagnostic: Check threads table structure
			if ( empty( $raw_avg_response ) ) {
				$threads_check = $wpdb->get_col("SHOW TABLES LIKE '%threads%'");
				stackboost_log( "Available Thread Tables: " . json_encode($threads_check), 'ticket_metrics' );

				// Run a test query against the assumed threads table to see if it works
				$test_join_query = "SELECT t.id, th.id as thread_id, th.date_created as thread_date
									FROM {$tickets_table} t
									JOIN {$threads_table} th ON t.id = th.ticket
									LIMIT 1";
				$test_res = $wpdb->get_results($test_join_query);
				if ( ! empty( $wpdb->last_error ) ) {
					stackboost_log( "Test Join Error: " . $wpdb->last_error, 'ticket_metrics' );
				} else {
					stackboost_log( "Test Join Success. Sample data: " . json_encode($test_res), 'ticket_metrics' );
				}
			}
		}

		$metrics['avg_initial_response'] = $avg_response_seconds > 0 ? $this->format_seconds($avg_response_seconds) : '0s';

		// Fetch maps first
		$agent_map = $this->get_agent_map($wpdb, $agents_table);
		$type_map = [];
		if ( preg_match( '/^[a-zA-Z0-9_]+$/', $type_field ) ) {
			$type_map = $this->get_type_map($wpdb, $type_field, $categories_table, $priorities_table, $status_table, $options_table);
		}

		// Perform a unified raw fetch to build rich hierarchies for Tooltips and Modals
		// We need: id, assigned_agent, type_field value, and whether it was closed in range.
		$metrics['agent_breakdown'] = [];
		$metrics['type_breakdown'] = [];

		if ( preg_match( '/^[a-zA-Z0-9_]+$/', $type_field ) ) {
			$raw_tickets_query = $wpdb->prepare(
				"SELECT t.id, t.assigned_agent, t.`{$type_field}` as type_val,
						IF({$closed_condition} AND {$close_date_col} >= %s AND {$close_date_col} <= %s, 1, 0) as is_closed_in_range
				 FROM {$tickets_table} t
				 WHERE {$active_in_period_sql}",
				$start_dt, $end_dt, $end_dt, $start_dt
			);

			$raw_tickets = $wpdb->get_results($raw_tickets_query);

			$agent_data = [];
			$type_data = [];

			if ( is_array($raw_tickets) ) {
				foreach ( $raw_tickets as $t ) {
					$type_val = $t->type_val;
					$is_closed = (bool) $t->is_closed_in_range;
					$agents = array_filter( array_map( 'intval', explode( '|', $t->assigned_agent ) ) );

					// Init Type Data
					if ( ! isset( $type_data[$type_val] ) ) {
						$type_data[$type_val] = ['count' => 0, 'agents' => []];
					}
					$type_data[$type_val]['count']++;

					foreach ( $agents as $a_id ) {
						if ( $a_id <= 0 ) continue;

						// Init Agent Data
						if ( ! isset( $agent_data[$a_id] ) ) {
							$agent_data[$a_id] = ['assigned' => 0, 'closed' => 0, 'types' => []];
						}

						// Agent Totals
						$agent_data[$a_id]['assigned']++;
						if ( $is_closed ) {
							$agent_data[$a_id]['closed']++;
						}

						// Agent Type Breakdown
						if ( ! isset( $agent_data[$a_id]['types'][$type_val] ) ) {
							$agent_data[$a_id]['types'][$type_val] = ['assigned' => 0, 'closed' => 0];
						}
						$agent_data[$a_id]['types'][$type_val]['assigned']++;
						if ( $is_closed ) {
							$agent_data[$a_id]['types'][$type_val]['closed']++;
						}

						// Type Agent Breakdown
						if ( ! isset( $type_data[$type_val]['agents'][$a_id] ) ) {
							$type_data[$type_val]['agents'][$a_id] = 0;
						}
						$type_data[$type_val]['agents'][$a_id]++;
					}
				}
			}

			// Format Agent Breakdown
			uasort($agent_data, function($a, $b) { return $b['assigned'] <=> $a['assigned']; });
			foreach ( $agent_data as $a_id => $data ) {
				$name = $agent_map[$a_id] ?? 'Agent ' . $a_id;

				// Build HTML for tooltip/modal
				$tooltip_rows = '';
				foreach ( $data['types'] as $t_val => $t_counts ) {
					$t_name = $type_map[$t_val] ?? ($t_val ?: 'Unassigned');
					$tooltip_rows .= sprintf(
						'<tr><td>%s</td><td style="text-align:center;">%s</td><td style="text-align:center;">%s</td></tr>',
						esc_html($t_name),
						(int)$t_counts['assigned'],
						(int)$t_counts['closed']
					);
				}

				$modal_html = sprintf(
					'<div style="text-align:left; font-size: 14px;">
						<h3 style="margin-top:0;">%s - Ticket Breakdown</h3>
						<table class="wp-list-table widefat striped" style="margin-top:10px;">
							<thead><tr><th>Type</th><th style="text-align:center;">Assigned</th><th style="text-align:center;">Closed</th></tr></thead>
							<tbody>%s</tbody>
						</table>
					</div>',
					esc_html($name),
					$tooltip_rows ?: '<tr><td colspan="3">No type data available</td></tr>'
				);

				$metrics['agent_breakdown'][] = [
					'label' => $name,
					'assigned' => $data['assigned'],
					'closed' => $data['closed'],
					'tooltip' => 'Click to view breakdown by Ticket Type',
					'modal_html' => $modal_html
				];
			}

			// Format Type Breakdown
			uasort($type_data, function($a, $b) { return $b['count'] <=> $a['count']; });
			foreach ( $type_data as $t_val => $data ) {
				$name = $type_map[$t_val] ?? ($t_val ?: 'Unassigned');

				// Deep metric calculation
				$type_where = $wpdb->prepare("AND t.`{$type_field}` = %s", $t_val);
				$type_metrics = $this->calculate_metric_set(
					$wpdb, $tickets_table, $threads_table, $start_dt, $end_dt,
					$closed_condition, $open_condition, $close_date_col,
					$active_in_period_sql, $type_where
				);

				// Build agent distribution HTML
				$agent_rows = '';
				arsort($data['agents']);
				foreach ( $data['agents'] as $a_id => $a_count ) {
					$a_name = $agent_map[$a_id] ?? 'Agent ' . $a_id;
					$agent_rows .= sprintf(
						'<tr><td>%s</td><td style="text-align:center;">%s</td></tr>',
						esc_html($a_name),
						(int)$a_count
					);
				}

				$tooltip_html = sprintf(
					'<div style="text-align:left; font-size: 13px; line-height: 1.5;">
						<strong>%s</strong><br><hr style="margin:5px 0; border: 0; border-top: 1px solid #ccc;">
						Created: <strong>%s</strong><br>
						Closed: <strong>%s</strong><br>
						Avg Time to Close: <strong>%s</strong><br>
						Avg Age (Open): <strong>%s</strong><br>
						Avg Initial Response: <strong>%s</strong><br><br>
						<em>Click row to view Agent distribution</em>
					</div>',
					esc_html($name),
					esc_html($type_metrics['total_created']),
					esc_html($type_metrics['total_closed']),
					esc_html($type_metrics['avg_open_time']),
					esc_html($type_metrics['avg_age_open']),
					esc_html($type_metrics['avg_initial_response'])
				);

				$modal_html = sprintf(
					'<div style="text-align:left; font-size: 14px;">
						<h3 style="margin-top:0;">%s - Performance & Distribution</h3>
						<div style="display:flex; gap: 20px; flex-wrap: wrap;">
							<div style="flex:1; min-width: 200px; background:#f0f0f1; padding: 10px; border-radius: 4px;">
								<p style="margin: 0 0 5px 0;">Created: <strong>%s</strong></p>
								<p style="margin: 0 0 5px 0;">Closed: <strong>%s</strong></p>
								<p style="margin: 0 0 5px 0;">Avg Time to Close: <strong>%s</strong></p>
								<p style="margin: 0 0 5px 0;">Avg Age (Open): <strong>%s</strong></p>
								<p style="margin: 0;">Avg Initial Response: <strong>%s</strong></p>
							</div>
							<div style="flex:2; min-width: 300px;">
								<table class="wp-list-table widefat striped">
									<thead><tr><th>Assigned Agent</th><th style="text-align:center;">Tickets</th></tr></thead>
									<tbody>%s</tbody>
								</table>
							</div>
						</div>
					</div>',
					esc_html($name),
					esc_html($type_metrics['total_created']),
					esc_html($type_metrics['total_closed']),
					esc_html($type_metrics['avg_open_time']),
					esc_html($type_metrics['avg_age_open']),
					esc_html($type_metrics['avg_initial_response']),
					$agent_rows ?: '<tr><td colspan="2">No agents assigned</td></tr>'
				);

				$metrics['type_breakdown'][] = [
					'label' => $name,
					'value' => $data['count'],
					'tooltip' => $tooltip_html,
					'modal_html' => $modal_html
				];
			}
		}

		if ( function_exists( 'stackboost_log' ) ) {
			stackboost_log( "Ticket Metrics Generated: " . json_encode($metrics), 'ticket_metrics' );
		}

		wp_send_json_success( $metrics );
	}

	private function calculate_metric_set( $wpdb, $tickets_table, $threads_table, $start_dt, $end_dt, $closed_condition, $open_condition, $close_date_col, $active_in_period_sql, $extra_where = '' ) {
		$metrics = [];

		// Since $extra_where may contain literal percentage signs (e.g. from user input like "100% Complete"),
		// appending it into $wpdb->prepare WILL cause prepare to fail if it thinks those are unreplaced placeholders.
		// Instead, we compile the prepared string *first*, and then append the strictly prepared $extra_where.

		// Total Tickets Created
		$query = $wpdb->prepare( "SELECT COUNT(t.id) FROM {$tickets_table} t WHERE t.date_created >= %s AND t.date_created <= %s", $start_dt, $end_dt ) . " " . $extra_where;
		$metrics['total_created'] = (int) $wpdb->get_var( $query );

		// Total Tickets Closed
		$query = $wpdb->prepare(
			"SELECT COUNT(t.id) FROM {$tickets_table} t
			 WHERE {$closed_condition}
			 AND {$close_date_col} >= %s AND {$close_date_col} <= %s",
			$start_dt, $end_dt
		) . " " . $extra_where;
		$metrics['total_closed'] = (int) $wpdb->get_var( $query );

		// Average Time Ticket was Open (For Closed Tickets)
		$query = $wpdb->prepare(
			"SELECT AVG(TIMESTAMPDIFF(SECOND, t.date_created, {$close_date_col}))
			 FROM {$tickets_table} t
			 WHERE {$closed_condition}
			 AND {$close_date_col} >= %s AND {$close_date_col} <= %s",
			$start_dt, $end_dt
		) . " " . $extra_where;
		$metrics['avg_open_time'] = (int) $wpdb->get_var($query) > 0 ? $this->format_seconds((int) $wpdb->get_var($query)) : 'N/A';

		// Average Age of Open Tickets
		$query = $wpdb->prepare(
			"SELECT AVG(TIMESTAMPDIFF(SECOND, t.date_created, UTC_TIMESTAMP()))
			 FROM {$tickets_table} t
			 WHERE {$open_condition}
			 AND {$active_in_period_sql}",
			$end_dt, $start_dt
		) . " " . $extra_where;
		$metrics['avg_age_open'] = (int) $wpdb->get_var($query) > 0 ? $this->format_seconds((int) $wpdb->get_var($query)) : 'N/A';

		// Average Initial Response Time
		$query = $wpdb->prepare(
			"SELECT AVG(response_time) FROM (
				SELECT t.id,
				TIMESTAMPDIFF(SECOND, t.date_created, MIN(th.date_created)) as response_time
				FROM {$tickets_table} t
				JOIN {$threads_table} th ON t.id = th.ticket
				WHERE {$active_in_period_sql}",
			$end_dt, $start_dt
		) . " {$extra_where} AND th.date_created > t.date_created GROUP BY t.id ) as response_times";
		$metrics['avg_initial_response'] = (int) $wpdb->get_var($query) > 0 ? $this->format_seconds((int) $wpdb->get_var($query)) : '0s';

		return $metrics;
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

	private function get_agent_map( $wpdb, $agents_table ) {
		$map = [];
		// SupportCandy uses a dedicated agents table, not the customers table for assignment mappings.
		$results = $wpdb->get_results("SELECT id, name FROM {$agents_table}");
		if ( is_array($results) ) {
			foreach ( $results as $r ) {
				$map[$r->id] = $r->name;
			}
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
