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
		add_action( 'wp_ajax_stackboost_get_other_issues_csv', [ $this, 'ajax_get_other_issues_csv' ] );
		add_action( 'wp_ajax_stackboost_get_field_options', [ $this, 'ajax_get_field_options' ] );
		add_action( 'wp_ajax_stackboost_get_trend_analysis_ai', [ $this, 'ajax_get_trend_analysis_ai' ] );
	}

	public function ajax_get_field_options() {
		check_ajax_referer( 'stackboost_admin_nonce', 'nonce' );

		if ( ! current_user_can( STACKBOOST_CAP_MANAGE_TICKET_METRICS ) ) {
			wp_send_json_error( __( 'Permission denied.', 'stackboost-for-supportcandy' ) );
		}

		$field_slug = isset( $_POST['field_slug'] ) ? sanitize_text_field( wp_unslash( $_POST['field_slug'] ) ) : '';

		if ( empty( $field_slug ) ) {
			wp_send_json_error( __( 'No field selected.', 'stackboost-for-supportcandy' ) );
		}

		global $wpdb;
		$categories_table = $wpdb->prefix . 'psmsc_categories';
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $wpdb->get_var("SHOW TABLES LIKE '{$categories_table}'") !== $categories_table ) {
			$categories_table = $wpdb->prefix . 'wpsc_categories';
			$priorities_table = $wpdb->prefix . 'wpsc_priorities';
			$status_table     = $wpdb->prefix . 'wpsc_statuses';
			$options_table    = $wpdb->prefix . 'wpsc_options';
		} else {
			$priorities_table = $wpdb->prefix . 'psmsc_priorities';
			$status_table     = $wpdb->prefix . 'psmsc_statuses';
			$options_table    = $wpdb->prefix . 'psmsc_options';
		}

		$map = $this->get_type_map($wpdb, $field_slug, $categories_table, $priorities_table, $status_table, $options_table);

		wp_send_json_success( $map );
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
		$options['ticket_metrics_enable_agent_group_filter'] = isset( $_POST['ticket_metrics_enable_agent_group_filter'] ) ? filter_var( wp_unslash( $_POST['ticket_metrics_enable_agent_group_filter'] ), FILTER_VALIDATE_BOOLEAN ) : false;

		$agent_chart = isset( $_POST['ticket_metrics_chart_type_agent'] ) ? sanitize_text_field( wp_unslash( $_POST['ticket_metrics_chart_type_agent'] ) ) : 'multi_pie';
		$options['ticket_metrics_chart_type_agent']  = in_array( $agent_chart, [ 'none', 'pie', 'doughnut', 'multi_pie', 'multi_doughnut', 'bar', 'line', 'radar', 'polarArea' ] ) ? $agent_chart : 'multi_pie';

		$type_chart = isset( $_POST['ticket_metrics_chart_type_type'] ) ? sanitize_text_field( wp_unslash( $_POST['ticket_metrics_chart_type_type'] ) ) : 'doughnut';
		$options['ticket_metrics_chart_type_type']   = in_array( $type_chart, [ 'none', 'pie', 'doughnut', 'bar', 'line', 'radar', 'polarArea' ] ) ? $type_chart : 'doughnut';

		$secondary_chart = isset( $_POST['ticket_metrics_chart_type_secondary'] ) ? sanitize_text_field( wp_unslash( $_POST['ticket_metrics_chart_type_secondary'] ) ) : 'bar';
		$options['ticket_metrics_chart_type_secondary']   = in_array( $secondary_chart, [ 'none', 'pie', 'doughnut', 'bar', 'line', 'radar', 'polarArea' ] ) ? $secondary_chart : 'bar';

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

		$options['ticket_metrics_sla_frt_hours'] = isset( $_POST['ticket_metrics_sla_frt_hours'] ) ? max( 0, (float) $_POST['ticket_metrics_sla_frt_hours'] ) : 0;
		$options['ticket_metrics_sla_resolution_hours'] = isset( $_POST['ticket_metrics_sla_resolution_hours'] ) ? max( 0, (float) $_POST['ticket_metrics_sla_resolution_hours'] ) : 0;
		$options['ticket_metrics_survey_max_score'] = isset( $_POST['ticket_metrics_survey_max_score'] ) ? max( 0, (float) $_POST['ticket_metrics_survey_max_score'] ) : 0;

		if ( isset( $_POST['ticket_metrics_survey_categories'] ) && is_array( $_POST['ticket_metrics_survey_categories'] ) ) {
			$options['ticket_metrics_survey_categories'] = array_map( 'sanitize_text_field', wp_unslash( $_POST['ticket_metrics_survey_categories'] ) );
		} else {
			$options['ticket_metrics_survey_categories'] = [];
		}

		$other_issues_rules = [];
		if ( isset( $_POST['ticket_metrics_other_issues_rules'] ) && is_array( $_POST['ticket_metrics_other_issues_rules'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$raw_rules = wp_unslash( $_POST['ticket_metrics_other_issues_rules'] );
			foreach ( $raw_rules as $rule ) {
				// Handle both string representations and arrays for trigger conditions from the JS payload
				$trigger_condition = [];
				if ( isset( $rule['trigger_condition'] ) ) {
					if ( is_array( $rule['trigger_condition'] ) ) {
						$trigger_condition = array_map('sanitize_text_field', $rule['trigger_condition']);
					} elseif ( is_string( $rule['trigger_condition'] ) ) {
						$decoded = json_decode($rule['trigger_condition'], true);
						if ( is_array($decoded) ) {
							$trigger_condition = array_map('sanitize_text_field', $decoded);
						} else {
							$trigger_condition = [ sanitize_text_field( $rule['trigger_condition'] ) ];
						}
					}
				}
				$other_issues_rules[] = [
					'trigger_field' => isset( $rule['trigger_field'] ) ? sanitize_text_field( $rule['trigger_field'] ) : '',
					'trigger_condition' => $trigger_condition,
					'text_field' => isset( $rule['text_field'] ) ? sanitize_text_field( $rule['text_field'] ) : 'subject',
				];
			}
		}
		$options['ticket_metrics_other_issues_rules'] = $other_issues_rules;

		$incoming_key = isset( $_POST['ticket_metrics_gemini_api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['ticket_metrics_gemini_api_key'] ) ) : '';

		if ( $incoming_key !== '********' ) {
			// If it's not the masked placeholder, it means they either wiped it empty or provided a new key.
			$options['ticket_metrics_gemini_api_key'] = $incoming_key;
		}
		// If it IS '********', we do nothing and let the existing key persist in the $options array.

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

	public function ajax_get_other_issues_csv() {
		check_ajax_referer( 'stackboost_admin_nonce', 'nonce' );

		if ( ! current_user_can( STACKBOOST_CAP_MANAGE_TICKET_METRICS ) ) {
			wp_die( esc_html__( 'Permission denied.', 'stackboost-for-supportcandy' ) );
		}

		$start_date = isset( $_GET['start_date'] ) ? sanitize_text_field( wp_unslash( $_GET['start_date'] ) ) : '';
		$end_date   = isset( $_GET['end_date'] ) ? sanitize_text_field( wp_unslash( $_GET['end_date'] ) ) : '';
		$trigger_field_filter = isset( $_GET['trigger_field'] ) ? sanitize_text_field( wp_unslash( $_GET['trigger_field'] ) ) : '';
		$parent_field = isset( $_GET['parent_field'] ) ? sanitize_text_field( wp_unslash( $_GET['parent_field'] ) ) : '';
		$parent_val   = isset( $_GET['parent_val'] ) ? sanitize_text_field( wp_unslash( $_GET['parent_val'] ) ) : '';

		if ( empty( $start_date ) || empty( $end_date ) ) {
			wp_die( esc_html__( 'Start and End dates are required.', 'stackboost-for-supportcandy' ) );
		}

		$options = get_option( 'stackboost_settings', [] );
		$rules = $options['ticket_metrics_other_issues_rules'] ?? [];

		if ( empty( $rules ) ) {
			wp_die( esc_html__( 'No "Other Issues" rules configured in settings.', 'stackboost-for-supportcandy' ) );
		}

		global $wpdb;
		$start_dt = gmdate( 'Y-m-d 00:00:00', strtotime( $start_date ) );
		$end_dt   = gmdate( 'Y-m-d 23:59:59', strtotime( $end_date ) );

		$tickets_table = $wpdb->prefix . 'psmsc_tickets';
		$threads_table = $wpdb->prefix . 'psmsc_threads';
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $wpdb->get_var("SHOW TABLES LIKE '{$tickets_table}'") !== $tickets_table ) {
			$tickets_table = $wpdb->prefix . 'wpsc_tickets';
			$threads_table = $wpdb->prefix . 'wpsc_threads';
		}

		$csv_data = [];

		foreach ( $rules as $rule ) {
			if ( empty( $rule['trigger_field'] ) ) {
				continue;
			}

			if ( ! empty( $trigger_field_filter ) && $rule['trigger_field'] !== $trigger_field_filter ) {
				continue; // Skip rules that do not match the explicitly requested trigger field
			}

			$trigger_field = $rule['trigger_field'];
			$trigger_cond  = $rule['trigger_condition'];
			$text_field    = $rule['text_field'];

			// We need to fetch tickets in the timeframe that match the condition.
			// Similar to `active_in_period_sql` in regular metrics
			// Is `date_closed` explicitly available?
			// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$has_date_closed = $wpdb->get_var("SHOW COLUMNS FROM {$tickets_table} LIKE 'date_closed'") === 'date_closed';
			$close_date_col = $has_date_closed ? 't.date_closed' : 't.date_updated';
			$open_condition = $has_date_closed ? "(t.date_closed IS NULL OR t.date_closed = '0000-00-00 00:00:00')" : "t.is_active = 1";

			$active_in_period_sql = "t.date_created <= %s AND ( " . $open_condition . " OR " . $close_date_col . " >= %s )";

			// Build query for this rule
			if ( preg_match( '/^[a-zA-Z0-9_]+$/', $trigger_field ) ) {
				$sql = "SELECT t.id, t.`" . $trigger_field . "` as trigger_val ";

				// Add select for text field if it's a native column
				if ( in_array( $text_field, [ 'subject', 'description' ] ) ) {
					// We will grab description from threads separately to avoid huge joins if many tickets.
					// Subject is on tickets table usually. Let's verify.
					// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$has_subject = $wpdb->get_var("SHOW COLUMNS FROM {$tickets_table} LIKE 'subject'") === 'subject';
					if ( $has_subject && $text_field === 'subject' ) {
						$sql .= ", t.subject as extracted_text ";
					} else {
						$sql .= ", '' as extracted_text "; // Will populate manually
					}
				} elseif ( preg_match( '/^[a-zA-Z0-9_]+$/', $text_field ) ) {
					// Custom text field, usually stored in a column on the tickets table in modern SC.
					$sql .= ", t.`" . $text_field . "` as extracted_text ";
				} else {
					$sql .= ", '' as extracted_text ";
				}

				$sql .= " FROM " . $tickets_table . " t WHERE " . $active_in_period_sql;

				$query_args = [ $end_dt, $start_dt ];

				if ( ! empty( $parent_field ) && ! empty( $parent_val ) && preg_match( '/^[a-zA-Z0-9_]+$/', $parent_field ) ) {
					$sql .= " AND t.`" . $parent_field . "` = %s";
					$query_args[] = $parent_val;
				}

				if ( ! empty( $trigger_cond ) ) {
					if ( is_array( $trigger_cond ) ) {
						$placeholders = implode( ',', array_fill( 0, count( $trigger_cond ), '%s' ) );
						$sql .= " AND t.`" . $trigger_field . "` IN ($placeholders)";
						$query_args = array_merge( $query_args, $trigger_cond );
					} else {
						$sql .= " AND t.`" . $trigger_field . "` = %s";
						$query_args[] = $trigger_cond;
					}
				} else {
					// If no condition specified, assume it's misconfigured.
					continue;
				}

				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$query = $wpdb->prepare( $sql, $query_args );

				// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$results = $wpdb->get_results( $query );

				if ( is_array( $results ) ) {
					foreach ( $results as $res ) {
						$extracted_text = $res->extracted_text;

						// If text field is description, fetch the first thread body.
						if ( $text_field === 'description' ) {
							$thread_sql = "SELECT body FROM {$threads_table} WHERE ticket = %d ORDER BY date_created ASC LIMIT 1";
							// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
							$thread_query = $wpdb->prepare( $thread_sql, $res->id );
							// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
							$first_thread = $wpdb->get_var( $thread_query );
							if ( $first_thread ) {
								$extracted_text = wp_strip_all_tags( $first_thread );
							}
						} elseif ( $text_field === 'subject' && empty( $extracted_text ) ) {
							// fallback if it wasn't returned in the main query for some reason
							$subj_sql = "SELECT subject FROM {$tickets_table} WHERE id = %d";
							// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
							$subj_query = $wpdb->prepare( $subj_sql, $res->id );
							// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
							$subj = $wpdb->get_var( $subj_query );
							$extracted_text = $subj ? $subj : '';
						}

						// Clean up the text for CSV
						$extracted_text = trim( $extracted_text );
						if ( ! empty( $extracted_text ) ) {
							$csv_data[] = [ $extracted_text ];
						}
					}
				}
			}
		}

		if ( empty( $csv_data ) ) {
			wp_die( esc_html__( 'No data found matching the rules in this timeframe.', 'stackboost-for-supportcandy' ) );
		}

		// Generate CSV output
		$filename = 'other-issues-report-' . date( 'Y-m-d' ) . '.csv';

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );

		$output = fopen( 'php://output', 'w' );
		if ( $output === false ) {
			wp_die( esc_html__( 'Failed to generate CSV.', 'stackboost-for-supportcandy' ) );
		}

		// Output UTF-8 BOM for Excel compatibility
		fprintf( $output, chr(0xEF).chr(0xBB).chr(0xBF) );

		foreach ( $csv_data as $row ) {
			fputcsv( $output, $row );
		}
		fclose( $output );
		exit;
	}

	public function ajax_get_trend_analysis_ai() {
		check_ajax_referer( 'stackboost_admin_nonce', 'nonce' );

		if ( ! current_user_can( STACKBOOST_CAP_MANAGE_TICKET_METRICS ) ) {
			wp_send_json_error( esc_html__( 'Permission denied.', 'stackboost-for-supportcandy' ) );
		}

		$options = get_option( 'stackboost_settings', [] );
		$api_key = $options['ticket_metrics_gemini_api_key'] ?? '';

		if ( empty( $api_key ) ) {
			wp_send_json_error( esc_html__( 'Gemini API Key is not configured. Please add it in the settings tab.', 'stackboost-for-supportcandy' ) );
		}

		$start_date = isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : '';
		$end_date   = isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : '';
		$trigger_field_filter = isset( $_POST['trigger_field'] ) ? sanitize_text_field( wp_unslash( $_POST['trigger_field'] ) ) : '';
		$parent_field = isset( $_POST['parent_field'] ) ? sanitize_text_field( wp_unslash( $_POST['parent_field'] ) ) : '';
		$parent_val   = isset( $_POST['parent_val'] ) ? sanitize_text_field( wp_unslash( $_POST['parent_val'] ) ) : '';

		if ( empty( $start_date ) || empty( $end_date ) ) {
			wp_send_json_error( esc_html__( 'Start and End dates are required.', 'stackboost-for-supportcandy' ) );
		}

		$rules = $options['ticket_metrics_other_issues_rules'] ?? [];

		if ( empty( $rules ) ) {
			wp_send_json_error( esc_html__( 'No "Other Issues" rules configured in settings.', 'stackboost-for-supportcandy' ) );
		}

		global $wpdb;
		$start_dt = gmdate( 'Y-m-d 00:00:00', strtotime( $start_date ) );
		$end_dt   = gmdate( 'Y-m-d 23:59:59', strtotime( $end_date ) );

		$tickets_table = $wpdb->prefix . 'psmsc_tickets';
		$threads_table = $wpdb->prefix . 'psmsc_threads';
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $wpdb->get_var("SHOW TABLES LIKE '{$tickets_table}'") !== $tickets_table ) {
			$tickets_table = $wpdb->prefix . 'wpsc_tickets';
			$threads_table = $wpdb->prefix . 'wpsc_threads';
		}

		$texts_to_analyze = [];

		foreach ( $rules as $rule ) {
			if ( empty( $rule['trigger_field'] ) ) {
				continue;
			}

			if ( ! empty( $trigger_field_filter ) && $rule['trigger_field'] !== $trigger_field_filter ) {
				continue; // Skip rules that do not match the explicitly requested trigger field
			}

			$trigger_field = $rule['trigger_field'];
			$trigger_cond  = $rule['trigger_condition'];
			$text_field    = $rule['text_field'];

			// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$has_date_closed = $wpdb->get_var("SHOW COLUMNS FROM {$tickets_table} LIKE 'date_closed'") === 'date_closed';
			$close_date_col = $has_date_closed ? 't.date_closed' : 't.date_updated';
			$open_condition = $has_date_closed ? "(t.date_closed IS NULL OR t.date_closed = '0000-00-00 00:00:00')" : "t.is_active = 1";

			$active_in_period_sql = "t.date_created <= %s AND ( " . $open_condition . " OR " . $close_date_col . " >= %s )";

			if ( preg_match( '/^[a-zA-Z0-9_]+$/', $trigger_field ) ) {
				$sql = "SELECT t.id, t.`" . $trigger_field . "` as trigger_val ";

				if ( in_array( $text_field, [ 'subject', 'description' ] ) ) {
					// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$has_subject = $wpdb->get_var("SHOW COLUMNS FROM {$tickets_table} LIKE 'subject'") === 'subject';
					if ( $has_subject && $text_field === 'subject' ) {
						$sql .= ", t.subject as extracted_text ";
					} else {
						$sql .= ", '' as extracted_text ";
					}
				} elseif ( preg_match( '/^[a-zA-Z0-9_]+$/', $text_field ) ) {
					$sql .= ", t.`" . $text_field . "` as extracted_text ";
				} else {
					$sql .= ", '' as extracted_text ";
				}

				$sql .= " FROM " . $tickets_table . " t WHERE " . $active_in_period_sql;

				$query_args = [ $end_dt, $start_dt ];

				if ( ! empty( $parent_field ) && ! empty( $parent_val ) && preg_match( '/^[a-zA-Z0-9_]+$/', $parent_field ) ) {
					$sql .= " AND t.`" . $parent_field . "` = %s";
					$query_args[] = $parent_val;
				}

				if ( ! empty( $trigger_cond ) ) {
					if ( is_array( $trigger_cond ) ) {
						$placeholders = implode( ',', array_fill( 0, count( $trigger_cond ), '%s' ) );
						$sql .= " AND t.`" . $trigger_field . "` IN ($placeholders)";
						$query_args = array_merge( $query_args, $trigger_cond );
					} else {
						$sql .= " AND t.`" . $trigger_field . "` = %s";
						$query_args[] = $trigger_cond;
					}
				} else {
					continue;
				}

				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$query = $wpdb->prepare( $sql, $query_args );

				// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$results = $wpdb->get_results( $query );

				if ( is_array( $results ) ) {
					foreach ( $results as $res ) {
						$extracted_text = $res->extracted_text;

						if ( $text_field === 'description' ) {
							$thread_sql = "SELECT body FROM {$threads_table} WHERE ticket = %d ORDER BY date_created ASC LIMIT 1";
							// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
							$thread_query = $wpdb->prepare( $thread_sql, $res->id );
							// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
							$first_thread = $wpdb->get_var( $thread_query );
							if ( $first_thread ) {
								$extracted_text = wp_strip_all_tags( $first_thread );
							}
						} elseif ( $text_field === 'subject' && empty( $extracted_text ) ) {
							$subj_sql = "SELECT subject FROM {$tickets_table} WHERE id = %d";
							// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
							$subj_query = $wpdb->prepare( $subj_sql, $res->id );
							// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
							$subj = $wpdb->get_var( $subj_query );
							$extracted_text = $subj ? $subj : '';
						}

						$extracted_text = trim( $extracted_text );
						if ( ! empty( $extracted_text ) ) {
							$texts_to_analyze[] = $extracted_text;
						}
					}
				}
			}
		}

		if ( empty( $texts_to_analyze ) ) {
			wp_send_json_error( esc_html__( 'No text data found to analyze for this timeframe/rule.', 'stackboost-for-supportcandy' ) );
		}

		// Fetch existing options for this field to provide context to Gemini
		$categories_table = $wpdb->prefix . 'psmsc_categories';
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $wpdb->get_var("SHOW TABLES LIKE '{$categories_table}'") !== $categories_table ) {
			$categories_table = $wpdb->prefix . 'wpsc_categories';
			$priorities_table = $wpdb->prefix . 'wpsc_priorities';
			$status_table     = $wpdb->prefix . 'wpsc_statuses';
			$options_table    = $wpdb->prefix . 'wpsc_options';
		} else {
			$priorities_table = $wpdb->prefix . 'psmsc_priorities';
			$status_table     = $wpdb->prefix . 'psmsc_statuses';
			$options_table    = $wpdb->prefix . 'psmsc_options';
		}

		// Use the first rule's trigger field as the context (assuming the button clicked was for a specific trigger field, though we use a filter to narrow it down above)
		$context_field = ! empty( $trigger_field_filter ) ? $trigger_field_filter : ( $rules[0]['trigger_field'] ?? 'category' );
		$existing_options_map = $this->get_type_map( $wpdb, $context_field, $categories_table, $priorities_table, $status_table, $options_table );

		$existing_options_list = "None";
		if ( ! empty( $existing_options_map ) ) {
			$existing_options_list = implode( ', ', array_values( $existing_options_map ) );
		}

		// Prepare the prompt for Gemini
		$prompt_intro = "You are a helpful customer support trend analyst. I will provide you with a list of ticket issues or subjects submitted by customers over a specific period. These tickets were categorized as 'Other' or similar catch-all options because they didn't fit existing categories.\n\n";
		$prompt_intro .= "Please read through these items and provide a succinct summary of the main trends, common questions, or recurring complaints.\n\n";
		$prompt_intro .= "Additionally, you MUST include a specific section proposing 2-3 NEW ticket categories or subcategories that we should add to our system to help reduce the volume of these 'Other' tickets in the future. \n\n";
		$prompt_intro .= "For context, here are the categories/options that ALREADY exist in the system for this field: [" . $existing_options_list . "]. Do not suggest these exact existing options.\n\n";
		$prompt_intro .= "Do not mention that you are an AI. Provide the analysis in clean HTML format using only standard tags (<h3>, <ul>, <li>, <strong>, <p>, <br>). Ensure all text and elements are left-aligned using inline CSS where necessary (e.g., <div style=\"text-align: left;\">). Include extra line breaks (<br><br>) between major sections for readability so it can be directly embedded into an admin dashboard modal. Avoid Markdown formatting in your final output, just raw HTML. Do not wrap the response in ```html ``` blocks.\n\nHere are the ticket excerpts:\n\n";

		// Limit the texts to prevent exceeding token limits if there are thousands
		$texts_to_analyze = array_slice( $texts_to_analyze, 0, 1000 );
		$prompt = $prompt_intro . implode( "\n- ", $texts_to_analyze );

		$api_url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-lite-latest:generateContent?key=" . $api_key;

		$body = [
			'contents' => [
				[
					'parts' => [
						[ 'text' => $prompt ]
					]
				]
			]
		];

		$response = wp_remote_post( $api_url, [
			'headers'     => [
				'Content-Type' => 'application/json',
			],
			'body'        => json_encode( $body ),
			'method'      => 'POST',
			'data_format' => 'body',
			'timeout'     => 45, // AI requests can take a moment
		] );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( esc_html__( 'Failed to connect to Gemini API: ', 'stackboost-for-supportcandy' ) . $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $status_code !== 200 ) {
			$error_msg = isset( $data['error']['message'] ) ? $data['error']['message'] : 'Unknown API error';
			wp_send_json_error( esc_html__( 'Gemini API Error: ', 'stackboost-for-supportcandy' ) . $error_msg );
		}

		if ( ! isset( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
			wp_send_json_error( esc_html__( 'Invalid response format from Gemini API.', 'stackboost-for-supportcandy' ) );
		}

		$ai_response_html = $data['candidates'][0]['content']['parts'][0]['text'];

		// Clean up the response if Gemini still wrapped it in markdown code blocks despite instructions
		$ai_response_html = preg_replace('/^```html\s*$/m', '', $ai_response_html);
		$ai_response_html = preg_replace('/^```\s*$/m', '', $ai_response_html);
		$ai_response_html = trim($ai_response_html);

		// Basic safety sanitization (kses allows basic HTML)
		$allowed_html = array(
			'h2' => array(),
			'h3' => array(),
			'h4' => array(),
			'p' => array(),
			'ul' => array(),
			'ol' => array(),
			'li' => array(),
			'strong' => array(),
			'em' => array(),
			'br' => array(),
			'span' => array(
				'style' => array(),
				'class' => array(),
			),
			'div' => array(
				'style' => array(),
				'class' => array(),
			),
		);

		$sanitized_html = wp_kses( $ai_response_html, $allowed_html );

		wp_send_json_success( $sanitized_html );
	}

	public function ajax_get_metrics() {
		check_ajax_referer( 'stackboost_admin_nonce', 'nonce' );

		if ( ! current_user_can( STACKBOOST_CAP_MANAGE_TICKET_METRICS ) ) {
			wp_send_json_error( __( 'Permission denied.', 'stackboost-for-supportcandy' ) );
		}

		$start_date = isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : '';
		$end_date   = isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : '';
		$type_field = isset( $_POST['type_field'] ) ? sanitize_text_field( wp_unslash( $_POST['type_field'] ) ) : 'category';
		$agent_group_val = isset( $_POST['agent_group_val'] ) ? intval( wp_unslash( $_POST['agent_group_val'] ) ) : 0;

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

		$overall_extra_where = '';

		if ( $agent_group_val > 0 && isset( $options['ticket_metrics_enable_agent_group_filter'] ) && filter_var( $options['ticket_metrics_enable_agent_group_filter'], FILTER_VALIDATE_BOOLEAN ) ) {
			$agentgroups_table = $wpdb->prefix . 'psmsc_agentgroups';
			if ( $wpdb->get_var( "SHOW TABLES LIKE '$agentgroups_table'" ) !== $agentgroups_table ) {
				$agentgroups_table = $wpdb->prefix . 'wpsc_agentgroups'; // fallback
			}
			if ( $wpdb->get_var( "SHOW TABLES LIKE '$agentgroups_table'" ) === $agentgroups_table ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$group_record = $wpdb->get_row( $wpdb->prepare( "SELECT agents, supervisors FROM {$agentgroups_table} WHERE id = %d", $agent_group_val ) );
				if ( $group_record ) {
					$member_ids = array_filter( explode( '|', $group_record->agents ) );
					$supervisor_ids = array_filter( explode( '|', $group_record->supervisors ) );
					$all_group_agents = array_unique( array_merge( $member_ids, $supervisor_ids ) );

					if ( ! empty( $all_group_agents ) ) {
						$find_in_set_parts = [];
						foreach ( $all_group_agents as $a_id ) {
							// Native SupportCandy string format can be unreliable with LIKE queries depending on how missing/multiple pipes are stored.
							// We fall back to standard FIND_IN_SET after replacing the pipes, which handles strings correctly.
							$a = (int) $a_id;
							$find_in_set_parts[] = "FIND_IN_SET(" . $a . ", REPLACE(t.assigned_agent, '|', ',')) > 0";
						}
						$overall_extra_where = " AND (" . implode( " OR ", $find_in_set_parts ) . ")";
					} else {
						// Group has no members, return empty dataset
						$overall_extra_where = " AND 1=0";
					}
				}
			}
		}

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

		// Perform a unified raw fetch to build rich hierarchies for Tooltips and Modals
		// We need: id, assigned_agent, type_field value, and whether it was closed in range.
		$options = get_option( 'stackboost_settings', [] );

		// Fetch maps first
		$agent_map = $this->get_agent_map($wpdb, $agents_table);
		$type_map = [];
		if ( preg_match( '/^[a-zA-Z0-9_]+$/', $type_field ) ) {
			$type_map = $this->get_type_map($wpdb, $type_field, $categories_table, $priorities_table, $status_table, $options_table);
		}

		$other_rules = $options['ticket_metrics_other_issues_rules'] ?? [];
		$trigger_condition_maps = [];
		foreach ( $other_rules as $rule ) {
			if ( ! empty( $rule['trigger_field'] ) && ! isset( $trigger_condition_maps[$rule['trigger_field']] ) ) {
				$trigger_condition_maps[$rule['trigger_field']] = $this->get_type_map($wpdb, $rule['trigger_field'], $categories_table, $priorities_table, $status_table, $options_table);
			}
		}

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
		$metrics['heatmap_data'] = [];

		// Overall Metrics
		// Set a specific flag so calculate_metric_set knows this $overall_extra_where is just an agent group wrapper,
		// not an explicit agent/type breakdown filter that usually forces N/A on CSAT when a ticket question is missing.
		$is_agent_group_wrapper = ( $overall_extra_where !== '' );

		if ( function_exists( 'stackboost_log' ) ) {
			stackboost_log( "--- TICKET METRICS AGENT GROUP FILTER ---", 'ticket_metrics' );
			stackboost_log( "Agent Group Val: {$agent_group_val}", 'ticket_metrics' );
			stackboost_log( "Overall Extra Where: {$overall_extra_where}", 'ticket_metrics' );
		}

		// Pass $is_agent_group_wrapper to the root call so it falls back gracefully if unlinked
		$overall_metrics = $this->calculate_metric_set(
			$wpdb, $tickets_table, $threads_table, $start_dt, $end_dt,
			$closed_condition, $open_condition, $close_date_col,
			$active_in_period_sql, $overall_extra_where, $is_agent_group_wrapper
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
		$metrics['avg_touches']          = $overall_metrics['avg_touches'];
		$metrics['sla_frt_breach_rate']  = $overall_metrics['sla_frt_breach_rate'];
		$metrics['sla_resolution_breach_rate'] = $overall_metrics['sla_resolution_breach_rate'];
		$metrics['survey_response_rate'] = $overall_metrics['survey_response_rate'];
		$metrics['survey_avg_csat']      = $overall_metrics['survey_avg_csat'];
		$metrics['is_sla_configured']    = $overall_metrics['is_sla_configured'];
		$metrics['is_survey_configured'] = $overall_metrics['is_survey_configured'];

		// Heatmap Data (Ticket Creation Volume by Day of Week and Hour of Day)
		// We use DAYOFWEEK where 1=Sunday, 2=Monday, etc. and HOUR 0-23
		// date_created is stored in UTC. Apply WP gmt_offset so the heatmap visually aligns with the local timezone.
		$gmt_offset = (float) get_option( 'gmt_offset' );

		$sql_heatmap = "SELECT DAYOFWEEK(DATE_ADD(t.date_created, INTERVAL %f HOUR)) as dow,
							   HOUR(DATE_ADD(t.date_created, INTERVAL %f HOUR)) as hod,
							   COUNT(t.id) as count
						FROM " . $tickets_table . " t
						WHERE t.date_created >= %s AND t.date_created <= %s" . $overall_extra_where . "
						GROUP BY dow, hod";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$query_heatmap = $wpdb->prepare( $sql_heatmap, $gmt_offset, $gmt_offset, $start_dt, $end_dt );
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$heatmap_results = $wpdb->get_results( $query_heatmap );

		if ( is_array( $heatmap_results ) ) {
			foreach ( $heatmap_results as $row ) {
				$metrics['heatmap_data'][] = [
					'dow'   => (int) $row->dow,
					'hod'   => (int) $row->hod,
					'count' => (int) $row->count,
				];
			}
		}

		if ( preg_match( '/^[a-zA-Z0-9_]+$/', $type_field ) ) {
			// Check for ALL rules to see if we need to fetch multiple trigger fields for breakdown tracking
			$other_rules = $options['ticket_metrics_other_issues_rules'] ?? [];
			$trigger_fields_to_fetch = [];

			// Ensure we fetch all fields that have a rule defined so we can show breakdowns regardless of the main grouped $type_field
			foreach ( $other_rules as $rule ) {
				if ( ! empty( $rule['trigger_field'] ) ) {
					$tf = $rule['trigger_field'];
					if ( preg_match( '/^[a-zA-Z0-9_]+$/', $tf ) ) {
						$trigger_fields_to_fetch[$tf] = $rule;
					}
				}
			}

			$sql_raw_tickets = "SELECT t.id, t.assigned_agent, t.`" . $type_field . "` as type_val,
						IF(" . $closed_condition . " AND " . $close_date_col . " >= %s AND " . $close_date_col . " <= %s, 1, 0) as is_closed_in_range";

			// Append all valid trigger fields so we can track if the ticket matches any configured rules
			foreach ( $trigger_fields_to_fetch as $tf => $rule ) {
				if ( $tf !== $type_field ) { // avoid double querying the main group field
					$sql_raw_tickets .= ", t.`" . $tf . "` as trigger_" . $tf;
				}

				// If a rule exists for this trigger field and the subcategory is a native/custom text column on the ticket, fetch it inline
				$subcat_field = $rule['text_field'];
				if ( $subcat_field && $subcat_field !== 'description' ) {
					if ( preg_match( '/^[a-zA-Z0-9_]+$/', $subcat_field ) ) {
						// Alias it distinctively so we don't collide if multiple rules use the same subcat field
						$sql_raw_tickets .= ", t.`" . $subcat_field . "` as subcat_" . $tf;
					}
				}
			}

			$sql_raw_tickets .= " FROM " . $tickets_table . " t WHERE " . $active_in_period_sql . $overall_extra_where;

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
						$type_data[$type_val] = ['count' => 0, 'agents' => [], 'breakdown_tables' => []];
					}

					// Process Trigger Fields for Subcategory Breakdown Tables
					foreach ( $trigger_fields_to_fetch as $tf => $rule ) {
						// Retrieve the ticket's value for this trigger field
						$tf_val = ($tf === $type_field) ? $type_val : $t->{"trigger_" . $tf};

						// Skip if this ticket does not have a value for this trigger field
						if ( empty( $tf_val ) ) {
							continue;
						}

						// Determine if this value is the "Other" condition
						$trigger_cond = $rule['trigger_condition'];
						$is_other_match = false;
						if ( is_array( $trigger_cond ) && in_array( $tf_val, $trigger_cond ) ) {
							$is_other_match = true;
						} elseif ( ! is_array( $trigger_cond ) && (string)$tf_val === (string)$trigger_cond ) {
							$is_other_match = true;
						}

						$subcat_field = isset( $rule['text_field'] ) ? $rule['text_field'] : '';

						// Initialize the table array for this trigger field if not exists
						if ( ! isset( $type_data[$type_val]['breakdown_tables'][$tf] ) ) {
							$type_data[$type_val]['breakdown_tables'][$tf] = [
								'responses' => [],
								'other_matches' => [] // Store the exact "Other" values encountered
							];
						}

						if ( ! isset( $type_data[$type_val]['breakdown_tables'][$tf]['responses'][$tf_val] ) ) {
							$type_data[$type_val]['breakdown_tables'][$tf]['responses'][$tf_val] = ['count' => 0, 'has_subcat' => false];
						}

						$type_data[$type_val]['breakdown_tables'][$tf]['responses'][$tf_val]['count']++;

						// We only record that a subcat field is available if the rule defines one
						if ( ! empty( $subcat_field ) && $is_other_match ) {
							$type_data[$type_val]['breakdown_tables'][$tf]['responses'][$tf_val]['has_subcat'] = true;

							if ( ! in_array( $tf_val, $type_data[$type_val]['breakdown_tables'][$tf]['other_matches'] ) ) {
								$type_data[$type_val]['breakdown_tables'][$tf]['other_matches'][] = $tf_val;
							}
						}
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

				// Explicitly drop $overall_extra_where for the individual agent breakdown calculations if it's an agent group wrapper.
				// This is because we are already calculating stats FOR THIS SPECIFIC AGENT (`$agent_where`).
				// If we append the group wrapper, we might inadvertently demand the ticket also belong to someone ELSE in the group,
				// or we enforce an unnecessary duplicate WHERE clause. But more importantly, the survey query will
				// fail to correctly scope the unlinked surveys if there are two complex conflicting agent strings.
				$agent_metrics = $this->calculate_metric_set(
					$wpdb, $tickets_table, $threads_table, $start_dt, $end_dt,
					$closed_condition, $open_condition, $close_date_col,
					$active_in_period_sql, $agent_where
				);

				$csat_text = 'N/A';
				if ( $agent_metrics['survey_avg_csat'] !== 'N/A' ) {
					if ( $a_id !== 'other' ) {
						$filter_url = admin_url( 'admin.php?page=stackboost-ats&tab=results&agent_id=' . $a_id . '&start_date=' . urlencode( date('Y-m-d', strtotime($start_dt)) ) . '&end_date=' . urlencode( date('Y-m-d', strtotime($end_dt)) ) );
						$csat_text = '<a href="' . esc_url( $filter_url ) . '" target="_blank">' . esc_html( $agent_metrics['survey_avg_csat'] ) . '</a><br>' . (int)$agent_metrics['survey_count'] . ' surveys';
					} else {
						$csat_text = esc_html($agent_metrics['survey_avg_csat']) . '<br>' . (int)$agent_metrics['survey_count'] . ' surveys';
					}
				}

				$tooltip_html = sprintf(
					'<div style="text-align:left; font-size: 13px; line-height: 1.5;">
						<strong>%s</strong><br><hr style="margin:5px 0; border: 0; border-top: 1px solid #ccc;">
						Assigned: <strong>%s</strong><br>
						Closed: <strong>%s</strong><br>
						Avg Time to Close: <strong>%s</strong><br>
						Avg Age (Open): <strong>%s</strong><br>
						Avg Initial Response: <strong>%s</strong><br>
						CSAT: <strong>%s</strong><br><br>
						<em>Click row to view %s</em>
					</div>',
					esc_html($name),
					(int)$data['assigned'],
					(int)$data['closed'],
					esc_html($agent_metrics['avg_open_time']),
					esc_html($agent_metrics['avg_age_open']),
					esc_html($agent_metrics['avg_initial_response']),
					$csat_text,
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
								<td style="text-align:center;">%s</td>
							</tr>',
							esc_html($t_name),
							(int)$t_counts['assigned'],
							(int)$agent_type_metrics['touched_tickets'],
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
											<th style="text-align:center;">Touches Per Ticket</th>
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
					'csat' => $csat_text,
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
					$active_in_period_sql, $type_where . $overall_extra_where
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
							<td style="text-align:center;">%s</td>
						</tr>',
						$a_name, // Output raw string since we optionally injected a span
						(int)$a_assigned,
						(int)$agent_type_metrics['touched_tickets'],
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

				$subcat_html = '';
				if ( ! empty( $data['breakdown_tables'] ) ) {
					// We need to fetch the names of the custom fields themselves for the table title
					$all_type_fields = [
						'category' => __( 'Category', 'stackboost-for-supportcandy' ),
						'priority' => __( 'Priority', 'stackboost-for-supportcandy' ),
						'status'   => __( 'Status', 'stackboost-for-supportcandy' ),
					];
					if ( class_exists( '\WPSC_Custom_Field' ) ) {
						$cfs = \WPSC_Custom_Field::find( [ 'items_per_page' => 0 ] )['results'];
						foreach ( $cfs as $cf ) {
							$all_type_fields[ $cf->slug ] = $cf->name;
						}
					}

					foreach ( $data['breakdown_tables'] as $tf => $tf_data ) {
						// Only render a breakdown table if there are actually multiple responses, or if it's a direct rule match we need to show
						if ( empty( $tf_data['responses'] ) ) {
							continue;
						}

						// Sort responses by count descending
						uasort($tf_data['responses'], function($a, $b) { return $b['count'] <=> $a['count']; });

						$tf_name = $all_type_fields[$tf] ?? $tf; // Fallback in case type_map doesn't have it, though UI should have it

						$subcat_rows = '';
						$chart_labels = [];
						$chart_data = [];

						foreach ( $tf_data['responses'] as $resp_val => $resp_data ) {
							$is_this_other = in_array( $resp_val, $tf_data['other_matches'] );

							// Map response value to friendly name if possible
							$raw_resp_name = isset($trigger_condition_maps[$tf][$resp_val]) ? $trigger_condition_maps[$tf][$resp_val] : $resp_val;
							$resp_name = $raw_resp_name;

							$chart_labels[] = $raw_resp_name;
							$chart_data[] = $resp_data['count'];

							// If this is an 'other' condition, make the name a link to the CSV
							if ( $is_this_other ) {
								$resp_name = sprintf(
									'<a href="#" class="stkb-export-other-issues" style="text-decoration:none; font-weight:bold; color: var(--sb-accent, #2271b1);" data-trigger="%s" data-trigger-val="%s" title="%s">%s <span class="dashicons dashicons-download" style="vertical-align:middle;"></span></a> <a href="#" class="stkb-trend-analysis-ai" style="text-decoration:none; font-weight:bold; color: #ffb900;" data-trigger="%s" data-trigger-val="%s" title="%s"><span class="dashicons dashicons-lightbulb" style="vertical-align:middle;"></span></a>',
									esc_attr( $tf ),
									esc_attr( $resp_val ),
									esc_attr__( 'Export Issues (CSV)', 'stackboost-for-supportcandy' ),
									esc_html( $resp_name ),
									esc_attr( $tf ),
									esc_attr( $resp_val ),
									esc_attr__( 'Generate Trend Analysis (AI)', 'stackboost-for-supportcandy' )
								);
							} else {
								$resp_name = esc_html($resp_name);
							}

							$subcat_rows .= sprintf(
								'<tr>
									<td>%s</td>
									<td style="text-align:center;"><strong>%d</strong></td>
								</tr>',
								$resp_name,
								$resp_data['count']
							);
						}

						$chart_json = json_encode([
							'labels' => $chart_labels,
							'data'   => $chart_data,
							'title'  => $tf_name
						]);

						$subcat_html .= sprintf(
							'<div class="stackboost-card" style="margin-bottom: 20px;">
								<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
									<h3>%s</h3>
								</div>
								<div style="display: flex; gap: 20px; flex-wrap: wrap;">
									<div style="flex: 1; min-width: 300px; overflow-x: auto;">
										<table class="wp-list-table widefat striped">
											<thead>
												<tr>
													<th>%s</th>
													<th style="text-align:center; width:100px;">%s</th>
												</tr>
											</thead>
											<tbody>%s</tbody>
										</table>
									</div>
									<div style="flex: 1; min-width: 300px;">
										<div class="stkb-chart-container" style="height: 300px;">
											<canvas class="stkb-secondary-chart" data-chart-config="%s"></canvas>
										</div>
									</div>
								</div>
							</div>',
							sprintf( esc_html__( 'Issue Breakdown: %s', 'stackboost-for-supportcandy' ), esc_html($tf_name) ),
							esc_html__( 'Response / Subcategory', 'stackboost-for-supportcandy' ),
							esc_html__( 'Count', 'stackboost-for-supportcandy' ),
							$subcat_rows,
							esc_attr($chart_json)
						);
					}
				}

				$modal_title = esc_html($name) . ' - ' . esc_html__( 'Performance & Distribution', 'stackboost-for-supportcandy' );

				$modal_html = sprintf(
					'<div class="stackboost-dashboard" style="text-align:left;" data-stkb-category-val="%s">
						<h2>%s</h2>
						<div style="display: flex; gap: 20px; margin-bottom: 20px;">
							<div class="stackboost-card" style="flex: 1;">
								<h3>Lifecycle</h3>
								<p>New (Created in range): <strong>%s</strong></p>
								<p>Carried Over & Closed: <strong>%s</strong></p>
								<p>Carried Over & Still Open: <strong>%s</strong></p>
							</div>
							<div class="stackboost-card" style="flex: 1;">
								<h3>Averages</h3>
								<p>Touches Per Ticket: <strong>%s</strong></p>
								<p>Time to Close: <strong>%s</strong></p>
								<p>Age (Open): <strong>%s</strong></p>
								<p>Initial Response: <strong>%s</strong></p>
							</div>
						</div>
						%s
						<div style="display: block;">
							<div class="stackboost-card" style="overflow-x: auto;">
								<h3>Agent Distribution</h3>
								<table class="wp-list-table widefat striped">
									<thead>
										<tr>
											<th>Assigned Agent</th>
											<th style="text-align:center;">Assigned</th>
											<th style="text-align:center;">Touches Per Ticket</th>
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
					esc_attr($t_val),
					$modal_title, // Allowed raw HTML from sprintf above
					esc_html($type_metrics['total_created']),
					esc_html($type_metrics['carried_closed']),
					esc_html($type_metrics['carried_open']),
					esc_html($type_metrics['avg_touches']),
					esc_html($type_metrics['avg_open_time']),
					esc_html($type_metrics['avg_age_open']),
					esc_html($type_metrics['avg_initial_response']),
					$subcat_html,
					$agent_rows ?: '<tr><td colspan="7">No agents assigned</td></tr>'
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

	private function calculate_metric_set( $wpdb, $tickets_table, $threads_table, $start_dt, $end_dt, $closed_condition, $open_condition, $close_date_col, $active_in_period_sql, $extra_where = '', $is_agent_group_wrapper = false ) {
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

		// Average Touches per Ticket
		// Total messages in threads for the touched tickets divided by the number of touched tickets
		if ($metrics['touched_tickets'] > 0) {
			$sql = "SELECT COUNT(th.id) FROM " . $threads_table . " th
					JOIN " . $tickets_table . " t ON th.ticket = t.id
					WHERE " . $active_in_period_sql;
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$query = $wpdb->prepare( $sql, $end_dt, $start_dt ) . " " . $extra_where;
			// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$total_threads = (int) $wpdb->get_var( $query );

			// Format as float to 1 decimal place
			$metrics['avg_touches'] = number_format($total_threads / $metrics['touched_tickets'], 1);
		} else {
			$metrics['avg_touches'] = '0.0';
		}

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

		// SLA Calculations
		$sla_frt_hours = isset( $options['ticket_metrics_sla_frt_hours'] ) ? (float) $options['ticket_metrics_sla_frt_hours'] : 0;
		$sla_resolution_hours = isset( $options['ticket_metrics_sla_resolution_hours'] ) ? (float) $options['ticket_metrics_sla_resolution_hours'] : 0;

		$metrics['sla_frt_breach_rate'] = 'N/A';
		$metrics['sla_resolution_breach_rate'] = 'N/A';

		$metrics['survey_response_rate'] = 'N/A';
		$metrics['survey_avg_csat'] = 'N/A';
		$metrics['survey_count'] = 0;

		$metrics['is_sla_configured'] = ($sla_frt_hours > 0 || $sla_resolution_hours > 0);
		$metrics['is_survey_configured'] = false;

		// Resolution SLA (Closed Tickets in Period)
		if ( $sla_resolution_hours > 0 && $metrics['total_closed'] > 0 ) {
			$resolution_seconds_limit = $sla_resolution_hours * 3600;

			$sql = "SELECT COUNT(t.id) FROM " . $tickets_table . " t
				 WHERE " . $closed_condition . "
				 AND TIMESTAMPDIFF(SECOND, t.date_created, " . $close_date_col . ") > %d
				 AND " . $close_date_col . " >= %s AND " . $close_date_col . " <= %s";
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$query = $wpdb->prepare( $sql, $resolution_seconds_limit, $start_dt, $end_dt ) . " " . $extra_where;
			// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$breached_resolution = (int) $wpdb->get_var( $query );

			$metrics['sla_resolution_breach_rate'] = round(($breached_resolution / $metrics['total_closed']) * 100, 1) . '%';
		}

		// FRT SLA (Touched Tickets in Period)
		// We calculate this based on the tickets that HAD a first response during this period.
		if ( $sla_frt_hours > 0 ) {
			$frt_seconds_limit = $sla_frt_hours * 3600;

			if ( $frt_mode === 'supportcandy' ) {
				// Base it on tickets touched in period where FRD > limit
				$sql_total = "SELECT COUNT(t.id) FROM " . $tickets_table . " t WHERE t.frd IS NOT NULL AND t.frd > 0 AND " . $active_in_period_sql;
				$sql_breach = "SELECT COUNT(t.id) FROM " . $tickets_table . " t WHERE t.frd IS NOT NULL AND t.frd > %d AND " . $active_in_period_sql;

				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$query_total = $wpdb->prepare( $sql_total, $end_dt, $start_dt ) . " " . $extra_where;
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$query_breach = $wpdb->prepare( $sql_breach, $frt_seconds_limit, $end_dt, $start_dt ) . " " . $extra_where;
			} else {
				$sql_total = "SELECT COUNT(t.id) FROM (
						SELECT t.id, TIMESTAMPDIFF(SECOND, t.date_created, MIN(th.date_created)) as response_time
						FROM " . $tickets_table . " t JOIN " . $threads_table . " th ON t.id = th.ticket
						WHERE " . $active_in_period_sql . " " . $extra_where . " AND th.date_created > t.date_created GROUP BY t.id ) as response_times";

				$sql_breach = "SELECT COUNT(t.id) FROM (
						SELECT t.id, TIMESTAMPDIFF(SECOND, t.date_created, MIN(th.date_created)) as response_time
						FROM " . $tickets_table . " t JOIN " . $threads_table . " th ON t.id = th.ticket
						WHERE " . $active_in_period_sql . " " . $extra_where . " AND th.date_created > t.date_created GROUP BY t.id
					) as response_times WHERE response_time > %d";

				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$query_total = $wpdb->prepare( $sql_total, $end_dt, $start_dt );
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$query_breach = $wpdb->prepare( $sql_breach, $end_dt, $start_dt, $frt_seconds_limit );
			}

			// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$total_responded = (int) $wpdb->get_var( $query_total );
			// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$breached_frt = (int) $wpdb->get_var( $query_breach );

			if ( $total_responded > 0 ) {
				$metrics['sla_frt_breach_rate'] = round(($breached_frt / $total_responded) * 100, 1) . '%';
			}
		}

		// Survey Tracking
		$submissions_table = $wpdb->prefix . 'stackboost_ats_survey_submissions';
		$answers_table     = $wpdb->prefix . 'stackboost_ats_survey_answers';
		$questions_table   = $wpdb->prefix . 'stackboost_ats_questions';
		$verbose_logging = isset( $options['ticket_metrics_verbose_logging'] ) ? (bool) $options['ticket_metrics_verbose_logging'] : false;

		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $wpdb->get_var("SHOW TABLES LIKE '{$submissions_table}'") === $submissions_table ) {
			$metrics['is_survey_configured'] = true;
			$ats_options = get_option( 'stackboost_settings', [] );
			$ticket_question_id = isset( $ats_options['ats_ticket_question_id'] ) ? (int) $ats_options['ats_ticket_question_id'] : 0;

			if ( $ticket_question_id === 0 ) {
				// Auto-detect the ticket ID question if the user hasn't explicitly configured it in ATS settings.
				// ATS uses the `question_type = 'ticket_number'` to designate which input receives the `ticket_id`.
				// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$ticket_question_id = (int) $wpdb->get_var("SELECT id FROM {$questions_table} WHERE question_type = 'ticket_number' LIMIT 1");
			}

			if ( $verbose_logging && function_exists( 'stackboost_log' ) ) {
				stackboost_log( "ATS Ticket Question ID Mapping: {$ticket_question_id}", 'ticket_metrics' );
			}

			if ( $ticket_question_id > 0 ) {
				// Find total completed surveys linked to tickets closed during this exact period via the dynamic ticket_id answer
				$sql_surveys = "SELECT COUNT(DISTINCT s.id) FROM " . $submissions_table . " s
								JOIN " . $answers_table . " a_ticket ON s.id = a_ticket.submission_id
								JOIN " . $tickets_table . " t ON a_ticket.answer_value = t.id
								WHERE a_ticket.question_id = %d AND " . $closed_condition . " AND " . $close_date_col . " >= %s AND " . $close_date_col . " <= %s";
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$query_surveys = $wpdb->prepare( $sql_surveys, $ticket_question_id, $start_dt, $end_dt ) . " " . ltrim($extra_where);
				// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$total_surveys = (int) $wpdb->get_var( $query_surveys );

				if ( function_exists( 'stackboost_log' ) ) {
					stackboost_log( "ATS Total Surveys Linked Query: {$query_surveys}", 'ticket_metrics' );
					stackboost_log( "ATS Total Surveys Linked Result: {$total_surveys}", 'ticket_metrics' );
				}

				$metrics['survey_count'] = $total_surveys;

				if ( $metrics['total_closed'] > 0 ) {
					$metrics['survey_response_rate'] = round(($total_surveys / $metrics['total_closed']) * 100, 1) . '%';
				}

				// CSAT Average (Calculate average of numeric responses, assuming 1-5 or 1-10 scales like stars/numbers)
				// Target ONLY questions where question_type = 'rating' via a join to the questions table
				if ( $total_surveys > 0 ) {
					$survey_categories = $options['ticket_metrics_survey_categories'] ?? [];
					$category_filter = "";
					if ( ! empty( $survey_categories ) ) {
						$placeholders = implode( ',', array_fill( 0, count( $survey_categories ), '%s' ) );
						$category_filter = " AND q.category_id IN ($placeholders)";
					}

					$sql_csat = "SELECT AVG(CAST(TRIM(a.answer_value) AS DECIMAL(10,2))) FROM " . $answers_table . " a
								JOIN " . $submissions_table . " s ON a.submission_id = s.id
								JOIN " . $answers_table . " a_ticket ON s.id = a_ticket.submission_id
								JOIN " . $tickets_table . " t ON a_ticket.answer_value = t.id
								JOIN " . $questions_table . " q ON a.question_id = q.id
								WHERE a_ticket.question_id = %d AND q.question_type = 'rating' " . $category_filter . " AND " . $closed_condition . " AND " . $close_date_col . " >= %s AND " . $close_date_col . " <= %s
								AND TRIM(a.answer_value) REGEXP '^[0-9]+'";

					$args = [ $ticket_question_id ];
					if ( ! empty( $survey_categories ) ) {
						$args = array_merge( $args, $survey_categories );
					}
					$args[] = $start_dt;
					$args[] = $end_dt;

					// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					$query_csat = $wpdb->prepare( $sql_csat, $args ) . " " . ltrim($extra_where);
					// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$avg_csat = $wpdb->get_var( $query_csat );

					if ( function_exists( 'stackboost_log' ) ) {
						stackboost_log( "ATS CSAT Linked Average Query: {$query_csat}", 'ticket_metrics' );
						stackboost_log( "ATS CSAT Linked Average Result: " . print_r($avg_csat, true), 'ticket_metrics' );
					}

					if ( $avg_csat !== null ) {
						$metrics['survey_avg_csat'] = round($avg_csat, 2);
					}
				} else {
					$metrics['survey_response_rate'] = '0%';
				}
			} else {
				// If the user hasn't configured which survey question maps to the Ticket ID, we can't link them accurately.
				// We CANNOT apply $extra_where here because we have no ticket to join against to check the assigned agent or category.
				// Therefore, if $extra_where is NOT empty (meaning this is a query for a specific agent or ticket type), we MUST return N/A
				// to prevent the global average from overwriting the specific agent's distinct metric.
				if ( ! empty( $extra_where ) && ! $is_agent_group_wrapper ) {
					$metrics['survey_response_rate'] = 'N/A';
					$metrics['survey_avg_csat'] = 'N/A';
				} else {
					// Fallback: Just calculate global survey stats for surveys submitted in the timeframe.
					// If $is_agent_group_wrapper is true, the user is expecting stats for that group.
					// Since we can't accurately link without a ticket ID map, we can't reliably scope the surveys.
					// To avoid showing completely inaccurate (global) stats disguised as group stats, we must return N/A if a group is selected!
					if ( $is_agent_group_wrapper ) {
						$metrics['survey_response_rate'] = 'N/A';
						$metrics['survey_avg_csat'] = 'N/A';
					} else {
						$sql_surveys = "SELECT COUNT(id) FROM " . $submissions_table . " WHERE submission_date >= %s AND submission_date <= %s";
						// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
						$query_surveys = $wpdb->prepare( $sql_surveys, $start_dt, $end_dt );
						// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
						$total_surveys = (int) $wpdb->get_var( $query_surveys );

						if ( function_exists( 'stackboost_log' ) ) {
							stackboost_log( "ATS Total Surveys Unlinked Query: {$query_surveys}", 'ticket_metrics' );
							stackboost_log( "ATS Total Surveys Unlinked Result: {$total_surveys}", 'ticket_metrics' );
						}

						$metrics['survey_count'] = $total_surveys;

						if ( $metrics['total_closed'] > 0 ) {
							$metrics['survey_response_rate'] = round(($total_surveys / $metrics['total_closed']) * 100, 1) . '%';
						}

						if ( $total_surveys > 0 ) {
							$survey_categories = $options['ticket_metrics_survey_categories'] ?? [];
							$category_filter = "";
							if ( ! empty( $survey_categories ) ) {
								$placeholders = implode( ',', array_fill( 0, count( $survey_categories ), '%s' ) );
								$category_filter = " AND q.category_id IN ($placeholders)";
							}

							$sql_csat = "SELECT AVG(CAST(TRIM(a.answer_value) AS DECIMAL(10,2))) FROM " . $answers_table . " a
										JOIN " . $submissions_table . " s ON a.submission_id = s.id
										JOIN " . $questions_table . " q ON a.question_id = q.id
										WHERE q.question_type = 'rating' " . $category_filter . " AND s.submission_date >= %s AND s.submission_date <= %s
										AND TRIM(a.answer_value) REGEXP '^[0-9]+'";

							$args = [];
							if ( ! empty( $survey_categories ) ) {
								$args = array_merge( $args, $survey_categories );
							}
							$args[] = $start_dt;
							$args[] = $end_dt;

							// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
							$query_csat = $wpdb->prepare( $sql_csat, $args );
							// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
							$avg_csat = $wpdb->get_var( $query_csat );

							if ( function_exists( 'stackboost_log' ) ) {
								stackboost_log( "ATS CSAT Unlinked Average Query: {$query_csat}", 'ticket_metrics' );
								stackboost_log( "ATS CSAT Unlinked Average Result: " . print_r($avg_csat, true), 'ticket_metrics' );
							}

							if ( $avg_csat !== null ) {
								$metrics['survey_avg_csat'] = round($avg_csat, 2);
							}
						} else {
							$metrics['survey_response_rate'] = '0%';
						}
					}
				}
			}
		}

		// Normalize CSAT against Max Score setting if configured to give it meaning (e.g., 4.2 / 5)
		$survey_max_score = isset( $options['ticket_metrics_survey_max_score'] ) ? (float) $options['ticket_metrics_survey_max_score'] : 0;
		if ( $survey_max_score > 0 && $metrics['survey_avg_csat'] !== 'N/A' ) {
			$raw_csat = (float) $metrics['survey_avg_csat'];
			// Format as requested: "4.2 (84%)" without the / 5.
			$percentage = round(($raw_csat / $survey_max_score) * 100);
			$metrics['survey_avg_csat'] = "{$raw_csat} ({$percentage}%)";
		}

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
