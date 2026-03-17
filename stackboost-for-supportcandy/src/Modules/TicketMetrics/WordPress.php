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
		add_action( 'wp_ajax_stackboost_save_ticket_metrics_settings', [ $this, 'ajax_save_ticket_metrics_settings' ] );
	}

	public function ajax_save_ticket_metrics_settings() {
		check_ajax_referer( 'stackboost_admin_nonce', 'nonce' );

		// START EXPLICIT LOGGING
		if ( function_exists( 'stackboost_log' ) ) {
			stackboost_log('=== STACKBOOST METRICS SETTINGS SAVE INIT ===', 'ticket_metrics');
			stackboost_log('RAW $_POST DATA RECEIVED:', 'ticket_metrics');
			stackboost_log(json_encode($_POST), 'ticket_metrics');
		}

		if ( ! current_user_can( STACKBOOST_CAP_MANAGE_TICKET_METRICS ) ) {
			if ( function_exists( 'stackboost_log' ) ) {
				stackboost_log('ABORT: Permission denied for current user.', 'ticket_metrics');
			}
			wp_send_json_error( __( 'Permission denied.', 'stackboost-for-supportcandy' ) );
		}

		$options = get_option( 'stackboost_settings', [] );
		if ( ! is_array( $options ) ) {
			$options = [];
		}

		// Read and sanitize raw POST data directly
		$options['ticket_metrics_type_field']        = isset( $_POST['ticket_metrics_type_field'] ) ? sanitize_text_field( wp_unslash( $_POST['ticket_metrics_type_field'] ) ) : 'category';

		$agent_chart = isset( $_POST['ticket_metrics_chart_type_agent'] ) ? sanitize_text_field( wp_unslash( $_POST['ticket_metrics_chart_type_agent'] ) ) : 'multi_pie';
		$options['ticket_metrics_chart_type_agent']  = in_array( $agent_chart, [ 'pie', 'doughnut', 'multi_pie', 'multi_doughnut', 'bar', 'line', 'radar', 'polarArea' ] ) ? $agent_chart : 'multi_pie';

		$type_chart = isset( $_POST['ticket_metrics_chart_type_type'] ) ? sanitize_text_field( wp_unslash( $_POST['ticket_metrics_chart_type_type'] ) ) : 'doughnut';
		$options['ticket_metrics_chart_type_type']   = in_array( $type_chart, [ 'pie', 'doughnut', 'bar', 'line', 'radar', 'polarArea' ] ) ? $type_chart : 'doughnut';

		// Handle the array of tracked agents
		$agents = [];
		if ( isset( $_POST['ticket_metrics_tracked_agents'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$raw_agents = wp_unslash( $_POST['ticket_metrics_tracked_agents'] );
			if ( is_array( $raw_agents ) ) {
				$agents = array_map( 'intval', $raw_agents );
			} else {
				// Fallback if somehow sent as string
				$agents = [ intval( sanitize_text_field( $raw_agents ) ) ];
			}
		}

		// If intentionally tracking no one, store a sentinel value so it's not confused with a fresh/unconfigured state
		if ( empty( $agents ) ) {
			$agents = [ -1 ];
		}

		$options['ticket_metrics_tracked_agents'] = $agents;
		$options['ticket_metrics_show_other_agents'] = isset( $_POST['ticket_metrics_show_other_agents'] ) ? (int) $_POST['ticket_metrics_show_other_agents'] : 0;
		$options['ticket_metrics_frt_mode'] = isset( $_POST['ticket_metrics_frt_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['ticket_metrics_frt_mode'] ) ) : 'stackboost';
		$options['ticket_metrics_verbose_logging'] = isset( $_POST['ticket_metrics_verbose_logging'] ) ? (int) $_POST['ticket_metrics_verbose_logging'] : 0;

		// Clean up legacy settings if present
		unset( $options['ticket_metrics_agent_filter_mode'] );
		unset( $options['ticket_metrics_excluded_agents'] );

		// Explicitly set the page_slug so the central Settings sanitizer accepts the update
		$options['page_slug'] = 'stackboost-ticket-metrics';

		if ( function_exists( 'stackboost_log' ) ) {
			stackboost_log('PROCESSED $options ARRAY TO BE SAVED:', 'ticket_metrics');
			stackboost_log(json_encode($options), 'ticket_metrics');
		}

		$update_result = update_option( 'stackboost_settings', $options );

		if ( function_exists( 'stackboost_log' ) ) {
			stackboost_log('RESULT OF update_option(): ' . ($update_result ? 'TRUE (Rows changed)' : 'FALSE (Identical to DB or error)'), 'ticket_metrics');
			stackboost_log('=== END STACKBOOST METRICS SETTINGS SAVE ===', 'ticket_metrics');
		}

		wp_send_json_success( __( 'Settings saved successfully.', 'stackboost-for-supportcandy' ) );
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

		// Save preference securely using a standalone option.
		// Inject page_slug to satisfy central settings sanitizer.
		$options = get_option( 'stackboost_settings', [] );
		if ( ! isset( $options['ticket_metrics_type_field'] ) || $options['ticket_metrics_type_field'] !== $type_field ) {
			$options['ticket_metrics_type_field'] = $type_field;
			$options['page_slug'] = 'stackboost-ticket-metrics';
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
		$start_dt = gmdate( 'Y-m-d 00:00:00', strtotime( $start_date ) );
		$end_dt   = gmdate( 'Y-m-d 23:59:59', strtotime( $end_date ) );

		$metrics = [];

		$tickets_table = $wpdb->prefix . 'psmsc_tickets';
		$threads_table = $wpdb->prefix . 'psmsc_threads';

		// Check if the old prefix is used or the new prefix is used
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
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
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
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
		$sql_total_closed = "SELECT COUNT(t.id) FROM " . $tickets_table . " t
			 WHERE " . $closed_condition . "
			 AND " . $close_date_col . " >= %s AND " . $close_date_col . " <= %s";
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total_closed = (int) $wpdb->get_var( $wpdb->prepare( $sql_total_closed, $start_dt, $end_dt ) );
		$metrics['total_closed'] = $total_closed;

		// Average Time Ticket was Open (For Closed Tickets)
		$sql_avg_open = "SELECT AVG(TIMESTAMPDIFF(SECOND, t.date_created, " . $close_date_col . "))
			 FROM " . $tickets_table . " t
			 WHERE " . $closed_condition . "
			 AND " . $close_date_col . " >= %s AND " . $close_date_col . " <= %s";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$avg_open_query = $wpdb->prepare( $sql_avg_open, $start_dt, $end_dt );

		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$raw_avg_open_result = $wpdb->get_var($avg_open_query);
		$avg_open_seconds = (int) $raw_avg_open_result;

		if ( function_exists( 'stackboost_log' ) ) {
			stackboost_log( "Avg Open Time Query: " . $avg_open_query, 'ticket_metrics' );
			stackboost_log( "Avg Open Time Raw Result: " . json_encode($raw_avg_open_result), 'ticket_metrics' );
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
		$active_in_period_sql = "t.date_created <= %s AND ( " . $open_condition . " OR " . $close_date_col . " >= %s )";

		// Average Age of Open Tickets
		// For tickets that are still open AND were active during the selected date range.
		$sql_avg_age = "SELECT AVG(TIMESTAMPDIFF(SECOND, t.date_created, UTC_TIMESTAMP()))
			 FROM " . $tickets_table . " t
			 WHERE " . $open_condition . "
			 AND " . $active_in_period_sql;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$avg_age_query = $wpdb->prepare( $sql_avg_age, $end_dt, $start_dt );

		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$raw_avg_age_result = $wpdb->get_var($avg_age_query);
		$avg_age_seconds = (int) $raw_avg_age_result;

		if ( $avg_age_seconds > 0 ) {
			$metrics['avg_age_open'] = $this->format_seconds($avg_age_seconds);
		} else {
			$metrics['avg_age_open'] = 'N/A';
		}

		// Average Initial Response Time
		// For tickets OPEN/ACTIVE AT ANY POINT during the selected range.
		$options = get_option( 'stackboost_settings', [] );
		$frt_mode = $options['ticket_metrics_frt_mode'] ?? 'stackboost';
		if ( $frt_mode === 'supportcandy' ) {
			// SupportCandy Native FRT field
			$sql_avg_response = "SELECT AVG(t.frd) FROM " . $tickets_table . " t
				 WHERE t.frd IS NOT NULL AND t.frd > 0 AND " . $active_in_period_sql;
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$avg_response_query = $wpdb->prepare( $sql_avg_response, $end_dt, $start_dt );
		} else {
			// StackBoost "Everything Counts" strict timeline
			$sql_avg_response = "SELECT AVG(response_time) FROM (
					SELECT t.id,
					TIMESTAMPDIFF(SECOND, t.date_created, MIN(th.date_created)) as response_time
					FROM " . $tickets_table . " t
					JOIN " . $threads_table . " th ON t.id = th.ticket
					WHERE " . $active_in_period_sql . "
					AND th.date_created > t.date_created
					GROUP BY t.id
				) as response_times";
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$avg_response_query = $wpdb->prepare( $sql_avg_response, $end_dt, $start_dt );
		}

		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$raw_avg_response = $wpdb->get_var($avg_response_query);
		$avg_response_seconds = (int) $raw_avg_response;
		$verbose_logging = isset( $options['ticket_metrics_verbose_logging'] ) ? (bool) $options['ticket_metrics_verbose_logging'] : false;

		if ( function_exists( 'stackboost_log' ) ) {
			stackboost_log( "Ticket Metrics Request - Start: {$start_dt}, End: {$end_dt}, Type Field: {$type_field}, FRT Mode: {$frt_mode}", 'ticket_metrics' );
			stackboost_log( "Avg Response Time Query: " . $avg_response_query, 'ticket_metrics' );
			stackboost_log( "Avg Response Time Raw Result: " . json_encode($raw_avg_response), 'ticket_metrics' );

			if ( ! empty( $wpdb->last_error ) ) {
				stackboost_log( "SQL Error: " . $wpdb->last_error, 'ticket_metrics' );
			}

			if ( $verbose_logging ) {
				// Deep diagnostic: Check threads table structure
				if ( empty( $raw_avg_response ) && $frt_mode === 'stackboost' ) {
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$threads_check = $wpdb->get_col("SHOW TABLES LIKE '%threads%'");
					stackboost_log( "Available Thread Tables: " . json_encode($threads_check), 'ticket_metrics' );

					// Run a test query against the assumed threads table to see if it works
					$test_join_query = "SELECT t.id, th.id as thread_id, th.date_created as thread_date
											FROM " . $tickets_table . " t
											JOIN " . $threads_table . " th ON t.id = th.ticket
										LIMIT 1";
						// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$test_res = $wpdb->get_results($test_join_query);
					if ( ! empty( $wpdb->last_error ) ) {
						stackboost_log( "Test Join Error: " . $wpdb->last_error, 'ticket_metrics' );
					} else {
						stackboost_log( "Test Join Success. Sample data: " . json_encode($test_res), 'ticket_metrics' );
					}
				}
			}
		}

		$metrics['avg_initial_response'] = $avg_response_seconds > 0 ? $this->format_seconds($avg_response_seconds) : '0m';

		// Fetch maps first
		$agent_map = $this->get_agent_map($wpdb, $agents_table);
		$type_map = [];
		if ( preg_match( '/^[a-zA-Z0-9_]+$/', $type_field ) ) {
			$type_map = $this->get_type_map($wpdb, $type_field, $categories_table, $priorities_table, $status_table, $options_table);
		}

		// Perform a unified raw fetch to build rich hierarchies for Tooltips and Modals
		// We need: id, assigned_agent, type_field value, and whether it was closed in range.
		$options = get_option( 'stackboost_settings', [] );
		$tracked_agents = $options['ticket_metrics_tracked_agents'] ?? [];
		if ( ! is_array( $tracked_agents ) ) {
			$tracked_agents = [];
		}

		// For backward compatibility on first load if old legacy 'include' mode existed
		if ( empty($tracked_agents) && isset($options['ticket_metrics_agent_filter_mode']) && $options['ticket_metrics_agent_filter_mode'] === 'include' && !empty($options['ticket_metrics_excluded_agents']) ) {
			$tracked_agents = $options['ticket_metrics_excluded_agents'];
		}

		$is_track_none = (count($tracked_agents) === 1 && $tracked_agents[0] === -1);

		$show_other_agents = isset( $options['ticket_metrics_show_other_agents'] ) ? (bool) $options['ticket_metrics_show_other_agents'] : true; // Default true if not set

		$metrics['agent_breakdown'] = [];
		$metrics['type_breakdown'] = [];

		// Overall Metrics
		$overall_metrics = $this->calculate_metric_set(
			$wpdb, $tickets_table, $threads_table, $start_dt, $end_dt,
			$closed_condition, $open_condition, $close_date_col,
			$active_in_period_sql, '' // Empty extra where for root totals
		);

		// Manually append these root properties because JS expects them at the top level
		$metrics['total_created']        = $overall_metrics['total_created'];
		$metrics['total_closed']         = $overall_metrics['total_closed'];
		$metrics['avg_open_time']        = $overall_metrics['avg_open_time'];
		$metrics['avg_age_open']         = $overall_metrics['avg_age_open'];
		$metrics['avg_initial_response'] = $overall_metrics['avg_initial_response'];
		$metrics['resolution_rate']      = $overall_metrics['resolution_rate'];
		$metrics['active_backlog']       = $overall_metrics['active_backlog'];
		$metrics['touched_tickets']      = $overall_metrics['touched_tickets'];

		if ( preg_match( '/^[a-zA-Z0-9_]+$/', $type_field ) ) {
			$sql_raw_tickets = "SELECT t.id, t.assigned_agent, t.`" . $type_field . "` as type_val,
						IF(" . $closed_condition . " AND " . $close_date_col . " >= %s AND " . $close_date_col . " <= %s, 1, 0) as is_closed_in_range
				 FROM " . $tickets_table . " t
				 WHERE " . $active_in_period_sql;
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$raw_tickets_query = $wpdb->prepare( $sql_raw_tickets, $start_dt, $end_dt, $end_dt, $start_dt );

			// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$raw_tickets = $wpdb->get_results($raw_tickets_query);

			$agent_data = [];
			$type_data = [];
			$agent_data_raw = [];

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

					foreach ( $agents as $a_id_raw ) {
						if ( $a_id_raw <= 0 ) continue;

						// Grouping Logic
						// If tracked_agents is empty, we track all. If not empty, we group unselected into 'other'
						if ( $is_track_none || (!empty($tracked_agents) && !in_array($a_id_raw, $tracked_agents)) ) {
							if ( ! $show_other_agents ) {
								continue;
							}
							$a_id = 'other';
						} else {
							$a_id = $a_id_raw;
						}

						// Init Agent Data
						if ( ! isset( $agent_data[$a_id] ) ) {
							$agent_data[$a_id] = ['assigned' => 0, 'closed' => 0, 'types' => [], 'agents_in_other' => []];
						}

						if ( $a_id === 'other' && !in_array($a_id_raw, $agent_data[$a_id]['agents_in_other']) ) {
							$agent_data[$a_id]['agents_in_other'][] = $a_id_raw;
						}

						// Record raw individual agent counts for use in tooltips later
						if ( ! isset( $agent_data_raw[$a_id_raw] ) ) {
							$agent_data_raw[$a_id_raw] = ['types' => []];
						}
						if ( ! isset( $agent_data_raw[$a_id_raw]['types'][$type_val] ) ) {
							$agent_data_raw[$a_id_raw]['types'][$type_val] = ['assigned' => 0, 'closed' => 0];
						}
						$agent_data_raw[$a_id_raw]['types'][$type_val]['assigned']++;
						if ( $is_closed ) {
							$agent_data_raw[$a_id_raw]['types'][$type_val]['closed']++;
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

			// Extract "other" to force it to the end if present
			if ( isset($agent_data['other']) ) {
				$other_data = $agent_data['other'];
				unset($agent_data['other']);
				$agent_data['other'] = $other_data;
			}

			foreach ( $agent_data as $a_id => $data ) {
				$name = ($a_id === 'other') ? __( 'Other Agents', 'stackboost-for-supportcandy' ) : ($agent_map[$a_id] ?? 'Agent ' . $a_id);

				// Deep metric calculation for the Agent's overall stats (Tooltip)
				if ($a_id === 'other') {
					if (empty($data['agents_in_other'])) {
						$agent_where = "AND 1=0"; // fallback if somehow empty
					} else {
						$find_in_set_parts = [];
						foreach ($data['agents_in_other'] as $other_id) {
							$find_in_set_parts[] = $wpdb->prepare("FIND_IN_SET(%d, REPLACE(t.assigned_agent, '|', ',')) > 0", $other_id);
						}
						$agent_where = "AND (" . implode(" OR ", $find_in_set_parts) . ")";
					}
				} else {
					$agent_where = $wpdb->prepare("AND FIND_IN_SET(%d, REPLACE(t.assigned_agent, '|', ',')) > 0", $a_id);
				}

				$agent_metrics = $this->calculate_metric_set(
					$wpdb, $tickets_table, $threads_table, $start_dt, $end_dt,
					$closed_condition, $open_condition, $close_date_col,
					$active_in_period_sql, $agent_where
				);

				$tooltip_html = sprintf(
					'<div style="text-align:left; font-size: 13px; line-height: 1.5;">
						<strong>%s</strong><br><hr style="margin:5px 0; border: 0; border-top: 1px solid #ccc;">
						Assigned: <strong>%s</strong><br>
						Closed: <strong>%s</strong><br>
						Avg Time to Close: <strong>%s</strong><br>
						Avg Age (Open): <strong>%s</strong><br>
						Avg Initial Response: <strong>%s</strong><br><br>
						<em>Click row to view %s</em>
					</div>',
					esc_html($name),
					(int)$data['assigned'],
					(int)$data['closed'],
					esc_html($agent_metrics['avg_open_time']),
					esc_html($agent_metrics['avg_age_open']),
					esc_html($agent_metrics['avg_initial_response']),
					($a_id === 'other') ? __( 'individual agent breakdown', 'stackboost-for-supportcandy' ) : __( 'Ticket Type distribution', 'stackboost-for-supportcandy' )
				);

				// Build HTML for Modal
				$modal_rows = '';

				if ($a_id === 'other') {
					// For 'Other', the modal shows the breakdown of the individual users in 'Other'
					foreach ( $data['agents_in_other'] as $other_id ) {
						$o_name = $agent_map[$other_id] ?? 'Agent ' . $other_id;
						$o_where = $wpdb->prepare("AND FIND_IN_SET(%d, REPLACE(t.assigned_agent, '|', ',')) > 0", $other_id);
						$o_metrics = $this->calculate_metric_set(
							$wpdb, $tickets_table, $threads_table, $start_dt, $end_dt,
							$closed_condition, $open_condition, $close_date_col,
							$active_in_period_sql, $o_where
						);

						$modal_rows .= sprintf(
							'<tr>
								<td><strong>%s</strong></td>
								<td style="text-align:center;">%s</td>
								<td style="text-align:center;">%s</td>
								<td style="text-align:center;">%s</td>
								<td style="text-align:center;">%s</td>
							</tr>',
							esc_html($o_name),
							esc_html($o_metrics['total_created']),
							esc_html($o_metrics['total_closed']),
							esc_html($o_metrics['avg_open_time']),
							esc_html($o_metrics['avg_initial_response'])
						);
					}

					$modal_html = sprintf(
						'<div class="stackboost-dashboard" style="text-align:left;">
							<h2>%s - Individual Breakdown</h2>
							<div class="stackboost-card" style="overflow-x: auto;">
								<table class="wp-list-table widefat striped">
									<thead>
										<tr>
											<th>Agent</th>
											<th style="text-align:center;">Assigned</th>
											<th style="text-align:center;">Closed</th>
											<th style="text-align:center;">Avg Close Time</th>
											<th style="text-align:center;">Avg Initial Response</th>
										</tr>
									</thead>
									<tbody>%s</tbody>
								</table>
							</div>
						</div>',
						esc_html($name),
						$modal_rows ?: '<tr><td colspan="5">No data available</td></tr>'
					);
				} else {
					// Standard deep stats per type
					foreach ( $data['types'] as $t_val => $t_counts ) {
						$t_name = $type_map[$t_val] ?? ($t_val ?: 'Unassigned');

						$sql_agent_type_where = "AND FIND_IN_SET(%d, REPLACE(t.assigned_agent, '|', ',')) > 0 AND t.`" . $type_field . "` = %s";
						// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
						$agent_type_where = $wpdb->prepare( $sql_agent_type_where, $a_id, $t_val );
						$agent_type_metrics = $this->calculate_metric_set(
							$wpdb, $tickets_table, $threads_table, $start_dt, $end_dt,
							$closed_condition, $open_condition, $close_date_col,
							$active_in_period_sql, $agent_type_where
						);

						$modal_rows .= sprintf(
							'<tr>
								<td><strong>%s</strong></td>
								<td style="text-align:center;">%s</td>
								<td style="text-align:center;">%s</td>
								<td style="text-align:center;">%s</td>
								<td style="text-align:center;">%s</td>
								<td style="text-align:center;">%s</td>
							</tr>',
							esc_html($t_name),
							(int)$t_counts['assigned'],
							(int)$t_counts['closed'],
							esc_html($agent_type_metrics['avg_open_time']),
							esc_html($agent_type_metrics['avg_age_open']),
							esc_html($agent_type_metrics['avg_initial_response'])
						);
					}

					$modal_html = sprintf(
						'<div class="stackboost-dashboard" style="text-align:left;">
							<h2>%s</h2>
							<div class="stackboost-card" style="overflow-x: auto;">
								<h3>Performance by Ticket Type</h3>
								<table class="wp-list-table widefat striped">
									<thead>
										<tr>
											<th>Type</th>
											<th style="text-align:center;">Assigned</th>
											<th style="text-align:center;">Closed</th>
											<th style="text-align:center;">Avg Close Time</th>
											<th style="text-align:center;">Avg Age (Open)</th>
											<th style="text-align:center;">Avg Initial Response</th>
										</tr>
									</thead>
									<tbody>%s</tbody>
								</table>
							</div>
						</div>',
						esc_html($name),
						$modal_rows ?: '<tr><td colspan="6">No type data available</td></tr>'
					);
				}

				$metrics['agent_breakdown'][] = [
					'label' => $name,
					'assigned' => $data['assigned'],
					'closed' => $data['closed'],
					'tooltip' => $tooltip_html,
					'modal_html' => $modal_html
				];
			}

			// Format Type Breakdown
			uasort($type_data, function($a, $b) { return $b['count'] <=> $a['count']; });
			foreach ( $type_data as $t_val => $data ) {
				$name = $type_map[$t_val] ?? ($t_val ?: 'Unassigned');

				// Deep metric calculation
				$sql_type_where = "AND t.`" . $type_field . "` = %s";
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$type_where = $wpdb->prepare( $sql_type_where, $t_val );
				$type_metrics = $this->calculate_metric_set(
					$wpdb, $tickets_table, $threads_table, $start_dt, $end_dt,
					$closed_condition, $open_condition, $close_date_col,
					$active_in_period_sql, $type_where
				);

				// Build agent distribution HTML
				$agent_rows = '';
				arsort($data['agents']);

				// Extract "other" to force it to the end if present
				if ( isset($data['agents']['other']) ) {
					$other_count = $data['agents']['other'];
					unset($data['agents']['other']);
					$data['agents']['other'] = $other_count;
				}

				foreach ( $data['agents'] as $a_id => $a_count ) {
					$a_name = ($a_id === 'other') ? __( 'Other Agents', 'stackboost-for-supportcandy' ) : ($agent_map[$a_id] ?? 'Agent ' . $a_id);

					// Calculate specific assigned/closed metrics for this agent+type combination
					$a_assigned = $agent_data[$a_id]['types'][$t_val]['assigned'] ?? 0;
					$a_closed   = $agent_data[$a_id]['types'][$t_val]['closed'] ?? 0;

					// Calculate specific averages for this agent+type combination
					if ($a_id === 'other') {
						if (empty($agent_data['other']['agents_in_other'])) {
							$agent_type_where = "AND 1=0"; // fallback if somehow empty
						} else {
							$find_in_set_parts = [];
							$tooltip_lines = [];
							foreach ($agent_data['other']['agents_in_other'] as $other_id) {
								$find_in_set_parts[] = $wpdb->prepare("FIND_IN_SET(%d, REPLACE(t.assigned_agent, '|', ',')) > 0", $other_id);

								// Compile tooltip lines for individual agents
								$ind_assigned = $agent_data_raw[$other_id]['types'][$t_val]['assigned'] ?? 0;
								$ind_closed   = $agent_data_raw[$other_id]['types'][$t_val]['closed'] ?? 0;

								// Only show them in the tooltip if they actually had tickets of this type
								if ( $ind_assigned > 0 || $ind_closed > 0 ) {
									$ind_name = $agent_map[$other_id] ?? 'Agent ' . $other_id;
									$tooltip_lines[] = sprintf( "<strong>%s:</strong> %d Assigned, %d Closed", esc_html($ind_name), $ind_assigned, $ind_closed );
								}
							}

							$agent_type_where = "AND (" . implode(" OR ", $find_in_set_parts) . ") AND t.`" . $type_field . "` = " . $wpdb->prepare("%s", $t_val);

							if ( !empty($tooltip_lines) ) {
								$tooltip_content = implode( "<br>", $tooltip_lines );
								$a_name = sprintf( '<span data-tippy-content="%s" style="cursor:help;">%s</span>', esc_attr($tooltip_content), esc_html($a_name) );
							} else {
								$a_name = esc_html($a_name); // Fallback
							}
						}
					} else {
						$sql_agent_type_where = "AND FIND_IN_SET(%d, REPLACE(t.assigned_agent, '|', ',')) > 0 AND t.`" . $type_field . "` = %s";
						// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
						$agent_type_where = $wpdb->prepare( $sql_agent_type_where, $a_id, $t_val );
						$a_name = esc_html($a_name); // Normal agents have no tooltip here
					}

					$agent_type_metrics = $this->calculate_metric_set(
						$wpdb, $tickets_table, $threads_table, $start_dt, $end_dt,
						$closed_condition, $open_condition, $close_date_col,
						$active_in_period_sql, $agent_type_where
					);

					$agent_rows .= sprintf(
						'<tr>
							<td>%s</td>
							<td style="text-align:center;">%s</td>
							<td style="text-align:center;">%s</td>
							<td style="text-align:center;">%s</td>
							<td style="text-align:center;">%s</td>
							<td style="text-align:center;">%s</td>
						</tr>',
						$a_name, // Output raw string since we optionally injected a span
						(int)$a_assigned,
						(int)$a_closed,
						esc_html($agent_type_metrics['avg_open_time']),
						esc_html($agent_type_metrics['avg_age_open']),
						esc_html($agent_type_metrics['avg_initial_response'])
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
					'<div class="stackboost-dashboard" style="text-align:left;">
						<h2>%s - Performance & Distribution</h2>
						<div style="display: flex; gap: 20px; margin-bottom: 20px;">
							<div class="stackboost-card" style="flex: 1;">
								<h3>Lifecycle</h3>
								<p>New (Created in range): <strong>%s</strong></p>
								<p>Carried Over & Closed: <strong>%s</strong></p>
								<p>Carried Over & Still Open: <strong>%s</strong></p>
							</div>
							<div class="stackboost-card" style="flex: 1;">
								<h3>Averages</h3>
								<p>Time to Close: <strong>%s</strong></p>
								<p>Age (Open): <strong>%s</strong></p>
								<p>Initial Response: <strong>%s</strong></p>
							</div>
						</div>
						<div style="display: block;">
							<div class="stackboost-card" style="overflow-x: auto;">
								<h3>Agent Distribution</h3>
								<table class="wp-list-table widefat striped">
									<thead>
										<tr>
											<th>Assigned Agent</th>
											<th style="text-align:center;">Assigned</th>
											<th style="text-align:center;">Closed</th>
											<th style="text-align:center;">Avg Close Time</th>
											<th style="text-align:center;">Avg Age (Open)</th>
											<th style="text-align:center;">Avg Initial Response</th>
										</tr>
									</thead>
									<tbody>%s</tbody>
								</table>
							</div>
						</div>
					</div>',
					esc_html($name),
					esc_html($type_metrics['total_created']),
					esc_html($type_metrics['carried_closed']),
					esc_html($type_metrics['carried_open']),
					esc_html($type_metrics['avg_open_time']),
					esc_html($type_metrics['avg_age_open']),
					esc_html($type_metrics['avg_initial_response']),
					$agent_rows ?: '<tr><td colspan="6">No agents assigned</td></tr>'
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
			if ( $verbose_logging ) {
				stackboost_log( "Ticket Metrics Generated: " . json_encode($metrics), 'ticket_metrics' );
			} else {
				stackboost_log( "Ticket Metrics Generated. (Verbose JSON dump skipped)", 'ticket_metrics' );
			}
		}

		wp_send_json_success( $metrics );
	}

	private function calculate_metric_set( $wpdb, $tickets_table, $threads_table, $start_dt, $end_dt, $closed_condition, $open_condition, $close_date_col, $active_in_period_sql, $extra_where = '' ) {
		$metrics = [];
		$options = get_option( 'stackboost_settings', [] );

		// Since $extra_where may contain literal percentage signs (e.g. from user input like "100% Complete"),
		// appending it into $wpdb->prepare WILL cause prepare to fail if it thinks those are unreplaced placeholders.
		// Instead, we compile the prepared string *first*, and then append the strictly prepared $extra_where.

		// Total Tickets Created
		$sql = "SELECT COUNT(t.id) FROM " . $tickets_table . " t WHERE t.date_created >= %s AND t.date_created <= %s";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$query = $wpdb->prepare( $sql, $start_dt, $end_dt ) . " " . $extra_where;
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$metrics['total_created'] = (int) $wpdb->get_var( $query );

		// Total Tickets Closed
		$sql = "SELECT COUNT(t.id) FROM " . $tickets_table . " t
			 WHERE " . $closed_condition . " AND " . $close_date_col . " >= %s AND " . $close_date_col . " <= %s";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$query = $wpdb->prepare( $sql, $start_dt, $end_dt ) . " " . $extra_where;
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$metrics['total_closed'] = (int) $wpdb->get_var( $query );

		// Queue Health Metrics
		if ($metrics['total_created'] > 0) {
			$metrics['resolution_rate'] = round(($metrics['total_closed'] / $metrics['total_created']) * 100) . '%';
		} else {
			$metrics['resolution_rate'] = $metrics['total_closed'] > 0 ? '>100%' : '0%';
		}

		// Touched Tickets (Any ticket active or updated during this period)
		$sql = "SELECT COUNT(DISTINCT t.id) FROM " . $tickets_table . " t WHERE " . $active_in_period_sql;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$query = $wpdb->prepare( $sql, $end_dt, $start_dt ) . " " . $extra_where;
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$metrics['touched_tickets'] = (int) $wpdb->get_var( $query );

		// Active Backlog (Tickets Open at the exact end of the period, regardless of creation date)
		// Active means: Created before the end date, AND (Not closed OR Closed after the end date)
		$sql = "SELECT COUNT(DISTINCT t.id) FROM " . $tickets_table . " t WHERE t.date_created <= %s AND (NOT (" . $closed_condition . ") OR " . $close_date_col . " > %s)";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$query = $wpdb->prepare( $sql, $end_dt, $end_dt ) . " " . $extra_where;
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$metrics['active_backlog'] = (int) $wpdb->get_var( $query );

		// Lifecycle Bucket 2: Carried Over & Closed (Created before range, closed during range)
		$sql = "SELECT COUNT(t.id) FROM " . $tickets_table . " t
			 WHERE " . $closed_condition . "
			 AND t.date_created < %s
			 AND " . $close_date_col . " >= %s AND " . $close_date_col . " <= %s";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$query = $wpdb->prepare( $sql, $start_dt, $start_dt, $end_dt ) . " " . $extra_where;
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$metrics['carried_closed'] = (int) $wpdb->get_var( $query );

		// Lifecycle Bucket 3: Carried Over & Still Open (Created before range, and either open or closed AFTER range)
		$sql = "SELECT COUNT(t.id) FROM " . $tickets_table . " t
			 WHERE t.date_created < %s
			 AND ( " . $open_condition . " OR " . $close_date_col . " > %s )";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$query = $wpdb->prepare( $sql, $start_dt, $end_dt ) . " " . $extra_where;
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$metrics['carried_open'] = (int) $wpdb->get_var( $query );

		// Average Time Ticket was Open (For Closed Tickets)
		$sql = "SELECT AVG(TIMESTAMPDIFF(SECOND, t.date_created, " . $close_date_col . "))
			 FROM " . $tickets_table . " t
			 WHERE " . $closed_condition . "
			 AND " . $close_date_col . " >= %s AND " . $close_date_col . " <= %s";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$query = $wpdb->prepare( $sql, $start_dt, $end_dt ) . " " . $extra_where;
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$metrics['avg_open_time'] = (int) $wpdb->get_var($query) > 0 ? $this->format_seconds((int) $wpdb->get_var($query)) : 'N/A';

		// Average Age of Open Tickets
		$sql = "SELECT AVG(TIMESTAMPDIFF(SECOND, t.date_created, UTC_TIMESTAMP()))
			 FROM " . $tickets_table . " t
			 WHERE " . $open_condition . "
			 AND " . $active_in_period_sql;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$query = $wpdb->prepare( $sql, $end_dt, $start_dt ) . " " . $extra_where;
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$metrics['avg_age_open'] = (int) $wpdb->get_var($query) > 0 ? $this->format_seconds((int) $wpdb->get_var($query)) : 'N/A';

		// Average Initial Response Time
		$frt_mode = $options['ticket_metrics_frt_mode'] ?? 'stackboost';

		if ( $frt_mode === 'supportcandy' ) {
			// SupportCandy Native FRT field
			$sql = "SELECT AVG(t.frd)
				 FROM " . $tickets_table . " t
				 WHERE t.frd IS NOT NULL AND t.frd > 0 AND " . $active_in_period_sql;
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$query = $wpdb->prepare( $sql, $end_dt, $start_dt ) . " " . $extra_where;
		} else {
			// StackBoost "Everything Counts" strict timeline
			$sql = "SELECT AVG(response_time) FROM (
					SELECT t.id,
					TIMESTAMPDIFF(SECOND, t.date_created, MIN(th.date_created)) as response_time
					FROM " . $tickets_table . " t
					JOIN " . $threads_table . " th ON t.id = th.ticket
					WHERE " . $active_in_period_sql;
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$query = $wpdb->prepare( $sql, $end_dt, $start_dt ) . " " . $extra_where . " AND th.date_created > t.date_created GROUP BY t.id ) as response_times";
		}

		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$metrics['avg_initial_response'] = (int) $wpdb->get_var($query) > 0 ? $this->format_seconds((int) $wpdb->get_var($query)) : '0m';

		return $metrics;
	}

	private function format_seconds( $seconds ) {
		if ( ! $seconds ) return '0m';

		$parts = [];
		$days = floor($seconds / 86400);
		$hours = floor(($seconds % 86400) / 3600);
		$minutes = floor(($seconds % 3600) / 60);

		if ( $days > 0 ) $parts[] = $days . 'd';
		if ( $hours > 0 ) $parts[] = $hours . 'h';
		if ( $minutes > 0 ) $parts[] = $minutes . 'm';

		// If the action took less than 60 seconds total, display a clean indicator rather than empty string.
		if ( empty($parts) && $seconds > 0 ) {
			return '< 1m';
		}

		return implode(' ', $parts);
	}

	private function get_agent_map( $wpdb, $agents_table ) {
		$map = [];
		// SupportCandy uses a dedicated agents table, not the customers table for assignment mappings.
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
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
			// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
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
				// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$options_table}'") === $options_table;
				if ( $table_exists ) {
					// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
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
