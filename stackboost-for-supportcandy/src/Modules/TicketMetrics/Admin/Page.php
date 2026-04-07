<?php

namespace StackBoost\ForSupportCandy\Modules\TicketMetrics\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

use StackBoost\ForSupportCandy\WordPress\Plugin;

/**
 * Admin page for the Ticket Metrics module.
 */
class Page {

	public static function render_page() {
		if ( ! current_user_can( STACKBOOST_CAP_MANAGE_TICKET_METRICS ) ) {
			return;
		}

		$theme_class = 'sb-theme-clean-tech';
		if ( class_exists( 'StackBoost\ForSupportCandy\Modules\Appearance\WordPress' ) ) {
			$theme_class = \StackBoost\ForSupportCandy\Modules\Appearance\WordPress::get_active_theme_class();
		}

		$plugin_instance = Plugin::get_instance();

		// Fetch ONLY fields that have options (multiple choice)
		// We'll use SupportCandy's classes to filter out non-choice fields if possible,
		// or at least fetch the known ones.
		// Since we need to know if a field is multiple choice, we must check WPSC_Custom_Field.
		$custom_fields = [];
		if ( class_exists( '\WPSC_Custom_Field' ) ) {
			$cf_results = \WPSC_Custom_Field::find( [ 'items_per_page' => 0 ] )['results'];
			foreach ( $cf_results as $cf ) {
				// Check if the type class exists before trying to access its static properties
				$type_class = $cf->type;
				$is_choice_field = false;

				// The type is typically a string representing the class name.
				if ( is_string( $type_class ) ) {
					if ( class_exists( $type_class ) ) {
						if ( isset( $type_class::$has_options ) && $type_class::$has_options ) {
							$is_choice_field = true;
						} elseif ( isset( $type_class::$slug ) && in_array( $type_class::$slug, [ 'df_category', 'df_priority', 'df_status', 'df_usergroups', 'df_dropdown', 'df_multi_choice', 'df_checkbox', 'df_radio' ] ) ) {
							$is_choice_field = true;
						}
					} elseif ( in_array( $type_class, [ 'WPSC_Dropdown', 'WPSC_Radio', 'WPSC_Checkbox', 'df_dropdown', 'df_category', 'df_priority', 'df_status', 'df_usergroups', 'df_multi_choice', 'df_checkbox', 'df_radio' ] ) ) {
						// Fallback if class isn't loaded but we know the type slug
						$is_choice_field = true;
					}
				}

				// Only add it if we are sure it's a choice field.
				if ( $is_choice_field ) {
					$custom_fields[ $cf->slug ] = $cf->name;
				}
			}
		}

		$default_fields = [
			'category' => __( 'Category', 'stackboost-for-supportcandy' ),
			'priority' => __( 'Priority', 'stackboost-for-supportcandy' ),
			'status'   => __( 'Status', 'stackboost-for-supportcandy' ),
		];

		// Remove duplicates that SC might return via WPSC_Custom_Field (df_category, etc)
		unset($custom_fields['df_category']);
		unset($custom_fields['df_priority']);
		unset($custom_fields['df_status']);

		// Fetch ALL text fields for the Trend Analysis "Breakdown Field"
		$all_text_fields = [
			'subject' => __( 'Subject', 'stackboost-for-supportcandy' ),
			'description' => __( 'Description (First Message)', 'stackboost-for-supportcandy' )
		];
		if ( class_exists( '\WPSC_Custom_Field' ) ) {
			if ( ! isset( $cf_results ) ) {
				$cf_results = \WPSC_Custom_Field::find( [ 'items_per_page' => 0 ] )['results'];
			}
			foreach ( $cf_results as $cf ) {
				$type_class = $cf->type;
				$is_text_field = false;
				if ( is_string( $type_class ) ) {
					if ( class_exists( $type_class ) ) {
						if ( isset( $type_class::$slug ) && in_array( $type_class::$slug, [ 'df_text', 'df_textarea' ] ) ) {
							$is_text_field = true;
						}
					} elseif ( in_array( $type_class, [ 'WPSC_Text', 'WPSC_Textarea', 'df_text', 'df_textarea' ] ) ) {
						$is_text_field = true;
					}
				}
				if ( $is_text_field ) {
					$all_text_fields[ $cf->slug ] = $cf->name;
				}
			}
		}

		$all_type_fields = array_merge( $default_fields, $custom_fields );
		asort( $all_type_fields );

		$options = get_option( 'stackboost_settings', [] );
		$other_issues_rules = $options['ticket_metrics_other_issues_rules'] ?? [];
		if ( ! is_array( $other_issues_rules ) ) {
			$other_issues_rules = [];
		}

		$saved_type_field = $options['ticket_metrics_type_field'] ?? 'category';
		$enable_agent_group_filter = isset( $options['ticket_metrics_enable_agent_group_filter'] ) ? (bool) $options['ticket_metrics_enable_agent_group_filter'] : false;
		$chart_type_agent = $options['ticket_metrics_chart_type_agent'] ?? 'multi_pie';
		$chart_type_type = $options['ticket_metrics_chart_type_type'] ?? 'doughnut';
		$chart_type_secondary = $options['ticket_metrics_chart_type_secondary'] ?? 'bar';
		$show_other_agents = isset( $options['ticket_metrics_show_other_agents'] ) ? (bool) $options['ticket_metrics_show_other_agents'] : true;
		$frt_mode = $options['ticket_metrics_frt_mode'] ?? 'stackboost';
		$verbose_logging = isset( $options['ticket_metrics_verbose_logging'] ) ? (bool) $options['ticket_metrics_verbose_logging'] : false;

		$api_key = $options['ticket_metrics_gemini_api_key'] ?? '';
		$api_key_locked = ! empty( $api_key );

		$sla_frt_hours = isset( $options['ticket_metrics_sla_frt_hours'] ) ? (float) $options['ticket_metrics_sla_frt_hours'] : 0;
		$sla_resolution_hours = isset( $options['ticket_metrics_sla_resolution_hours'] ) ? (float) $options['ticket_metrics_sla_resolution_hours'] : 0;
		$survey_max_score = isset( $options['ticket_metrics_survey_max_score'] ) ? (float) $options['ticket_metrics_survey_max_score'] : 0;

		$survey_categories = [];
		if ( isset( $options['ticket_metrics_survey_categories'] ) && is_array( $options['ticket_metrics_survey_categories'] ) ) {
			$survey_categories = $options['ticket_metrics_survey_categories'];
		}

		// Map legacy setting if needed, or default to an empty array (which means ALL are tracked by default).
		$tracked_agents = $options['ticket_metrics_tracked_agents'] ?? [];
		if ( ! is_array( $tracked_agents ) ) {
			$tracked_agents = [];
		}

		// For backward compatibility on first load if old legacy 'include' mode existed
		if ( empty($tracked_agents) && isset($options['ticket_metrics_agent_filter_mode']) && $options['ticket_metrics_agent_filter_mode'] === 'include' && !empty($options['ticket_metrics_excluded_agents']) ) {
			$tracked_agents = $options['ticket_metrics_excluded_agents'];
		}

		$is_track_none = (count($tracked_agents) === 1 && $tracked_agents[0] === -1);

		global $wpdb;
		$agents_table = $wpdb->prefix . 'psmsc_agents';
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $wpdb->get_var("SHOW TABLES LIKE '{$agents_table}'") !== $agents_table ) {
			$agents_table = $wpdb->prefix . 'wpsc_agents';
		}

		$all_agents = [];
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$agents_table}'") === $agents_table;
		if ( $table_exists ) {
			// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$agent_results = $wpdb->get_results("SELECT id, name FROM {$agents_table} ORDER BY name ASC");
			if ( is_array($agent_results) ) {
				foreach ( $agent_results as $a ) {
					$all_agents[$a->id] = $a->name;
				}
			}
		}

		// Fetch survey categories
		$all_survey_categories = [];
		$categories_table = $wpdb->prefix . 'stackboost_ats_question_categories';
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$cat_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$categories_table}'") === $categories_table;
		if ( $cat_table_exists ) {
			// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$cat_results = $wpdb->get_results("SELECT id, name FROM {$categories_table} ORDER BY name ASC");
			if ( is_array($cat_results) ) {
				foreach ( $cat_results as $c ) {
					$all_survey_categories[$c->id] = $c->name;
				}
			}
		}

		// Helper to pre-fetch friendly names for the other issues rules
		$trigger_condition_maps = [];
		if ( ! empty( $other_issues_rules ) ) {
			$metrics_instance = \StackBoost\ForSupportCandy\Modules\TicketMetrics\WordPress::get_instance();

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

			// Expose private get_type_map via reflection, or since it's just a helper we can fetch it.
			// Actually, it's easier to make get_type_map public or just query here.
			// Let's use a reflection method to access it safely without changing access modifier.
			$reflector = new \ReflectionClass($metrics_instance);
			$method = $reflector->getMethod('get_type_map');
			$method->setAccessible(true);

			foreach ( $other_issues_rules as $rule ) {
				if ( ! empty( $rule['trigger_field'] ) && ! isset( $trigger_condition_maps[$rule['trigger_field']] ) ) {
					$trigger_condition_maps[$rule['trigger_field']] = $method->invoke($metrics_instance, $wpdb, $rule['trigger_field'], $categories_table, $priorities_table, $status_table, $options_table);
				}
			}
		}

		?>
		<style>
			/* Custom grid styles for smaller metric cards as requested */
			.stkb-metrics-row { display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 20px; }
			.stkb-metric-col { flex: 1; min-width: 200px; display: flex; flex-direction: column; gap: 15px; }
			.stkb-metric-card { background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 15px; text-align: center; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
			.stkb-metric-card h3 { margin: 0 0 10px 0; font-size: 14px; color: #50575e; }
			.stkb-metric-card p { margin: 0; font-size: 24px; font-weight: 600; color: #1d2327; }
			.stkb-breakdown-wrapper { display: flex; gap: 20px; flex-wrap: wrap; margin-top: 20px; }
			.stkb-breakdown-col { flex: 1; min-width: 300px; background: #fff; border: 1px solid #c3c4c7; padding: 15px; border-radius: 4px; }
			.stkb-clickable-row { cursor: pointer; transition: background-color 0.2s; }
			.stkb-clickable-row:hover { background-color: #f0f0f1 !important; }

			.stkb-heatmap-table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 11px; }
			.stkb-heatmap-table th, .stkb-heatmap-table td { border: 1px solid #ccd0d4; padding: 4px; text-align: center; }
			.stkb-heatmap-table th { background: #f0f0f1; font-weight: 600; color: #50575e; }
			.stkb-heatmap-cell { cursor: default; transition: transform 0.1s; position: relative; }
			.stkb-heatmap-cell:hover { transform: scale(1.1); z-index: 2; box-shadow: 0 0 5px rgba(0,0,0,0.2); }

			/* Modal Scrolling Fixes */
			.stackboost-modal {
				overflow: hidden; /* Prevent background scroll */
			}
			#stkb-metrics-modal .stackboost-modal-content {
				max-height: 80vh; /* Leave some margin */
				display: flex;
				flex-direction: column;
			}
			#stkb-metrics-modal-body {
				overflow-y: auto;
				overflow-x: hidden;
				padding-right: 10px; /* Leave space for scrollbar */
				/* Flex shrink allows the body to shrink and scroll while header stays fixed */
				flex-shrink: 1;
				min-height: 0;
			}
			.stkb-chart-container {
				margin-top: 20px;
				position: relative;
				height: 300px;
				width: 100%;
				display: flex;
				justify-content: center;
			}
			/* Ticket Metrics Two Box Agent Filtering Styles */
			.stkb-agent-filter-container {
				display: flex;
				gap: 15px;
				align-items: flex-start;
				max-width: 600px;
				margin-top: 10px;
			}
			.stkb-agent-filter-box {
				flex: 1;
			}
			.stkb-agent-filter-box h4 {
				margin-top: 0;
				margin-bottom: 5px;
				font-size: 13px;
				color: #50575e;
			}
			.stkb-agent-filter-box select {
				width: 100%;
				height: 250px !important;
			}
			.stkb-agent-filter-buttons {
				display: flex;
				flex-direction: column;
				gap: 8px;
				justify-content: center;
				align-self: center;
				margin-top: 20px;
			}
			.stkb-agent-filter-buttons .button {
				display: inline-flex;
				justify-content: center;
				align-items: center;
				width: 40px;
				height: 40px;
				padding: 0;
				border: 1px solid var(--sb-accent, #2271b1);
				background: #fff;
				cursor: pointer;
				transition: all 0.15s ease-in-out;
				color: var(--sb-accent, #2271b1);
			}
			.stkb-agent-filter-buttons .button:hover {
				background: var(--sb-accent, #2271b1);
				border-color: var(--sb-accent, #2271b1);
				color: #fff;
			}
			.stkb-agent-filter-buttons .button:active {
				background: var(--sb-accent-dark, #122b40);
				border-color: var(--sb-accent-dark, #122b40);
				box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
			}
			.stkb-agent-filter-buttons .button .dashicons {
				font-size: 20px;
			}
			#stkb_agent_add .dashicons,
			#stkb_agent_remove .dashicons {
				transform: scale(1.3);
			}
		</style>
		<div class="wrap stackboost-dashboard <?php echo esc_attr( $theme_class ); ?>">
			<h1><?php esc_html_e( 'Ticket Metrics', 'stackboost-for-supportcandy' ); ?></h1>

			<h2 class="nav-tab-wrapper stackboost-nav-tabs">
				<a href="#dashboard" class="nav-tab nav-tab-active"><?php esc_html_e( 'Dashboard', 'stackboost-for-supportcandy' ); ?></a>
				<a href="#settings" class="nav-tab"><?php esc_html_e( 'Settings', 'stackboost-for-supportcandy' ); ?></a>
				<a href="#sla" class="nav-tab"><?php esc_html_e( 'SLA Config', 'stackboost-for-supportcandy' ); ?></a>
			</h2>

			<div id="tab-dashboard" class="stackboost-tab-content" style="display: block;">
				<div class="stackboost-dashboard-grid">
					<div class="stackboost-card" style="margin-bottom: 20px;">
						<h2><?php esc_html_e( 'Metrics Filter', 'stackboost-for-supportcandy' ); ?></h2>
						<div style="display:flex; gap: 20px; align-items: flex-end; flex-wrap: wrap;">
							<div>
								<label for="stkb_date_preset" style="display:block; margin-bottom:5px; font-weight:600;"><?php esc_html_e( 'Date Range', 'stackboost-for-supportcandy' ); ?></label>
								<select id="stkb_date_preset">
									<option value="this_week"><?php esc_html_e( 'This Week', 'stackboost-for-supportcandy' ); ?></option>
									<option value="last_week"><?php esc_html_e( 'Last Week', 'stackboost-for-supportcandy' ); ?></option>
									<option value="this_month"><?php esc_html_e( 'This Month', 'stackboost-for-supportcandy' ); ?></option>
									<option value="last_month"><?php esc_html_e( 'Last Month', 'stackboost-for-supportcandy' ); ?></option>
									<option value="last_30_days"><?php esc_html_e( 'Last 30 Days', 'stackboost-for-supportcandy' ); ?></option>
									<option value="last_60_days"><?php esc_html_e( 'Last 60 Days', 'stackboost-for-supportcandy' ); ?></option>
									<option value="last_90_days"><?php esc_html_e( 'Last 90 Days', 'stackboost-for-supportcandy' ); ?></option>
									<option value="this_year"><?php esc_html_e( 'This Year (to date)', 'stackboost-for-supportcandy' ); ?></option>
									<option value="last_year"><?php esc_html_e( 'Last Year', 'stackboost-for-supportcandy' ); ?></option>
									<option value="custom"><?php esc_html_e( 'Custom', 'stackboost-for-supportcandy' ); ?></option>
								</select>
							</div>
							<div id="stkb_custom_dates" style="display:none;">
								<label style="display:block; margin-bottom:5px; font-weight:600;"><?php esc_html_e( 'Custom Dates', 'stackboost-for-supportcandy' ); ?></label>
								<input type="date" id="stkb_start_date" /> - <input type="date" id="stkb_end_date" />
							</div>
							<div id="stkb_agent_group_filter_container" style="display: <?php echo $enable_agent_group_filter ? 'block' : 'none'; ?>;">
								<label for="stkb_agent_group_filter" style="display:block; margin-bottom:5px; font-weight:600;"><?php esc_html_e( 'Department (Agent Group)', 'stackboost-for-supportcandy' ); ?></label>
								<select id="stkb_agent_group_filter">
									<option value=""><?php esc_html_e( 'All', 'stackboost-for-supportcandy' ); ?></option>
									<?php
									global $wpdb;
									$table = $wpdb->prefix . 'psmsc_agentgroups';
									if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) !== $table ) {
										$table = $wpdb->prefix . 'wpsc_agentgroups'; // fallback
									}
									if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) === $table ) {
										// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
										$groups = $wpdb->get_results( "SELECT id, name FROM $table ORDER BY name ASC" );
										if ( $groups ) {
											foreach ( $groups as $group ) {
												echo '<option value="' . esc_attr( $group->id ) . '">' . esc_html( $group->name ) . '</option>';
											}
										}
									}
									?>
								</select>
							</div>
							<div>
								<button type="button" class="button button-primary" id="stkb_generate_metrics"><?php esc_html_e( 'Update Metrics', 'stackboost-for-supportcandy' ); ?></button>
							</div>
						</div>
					</div>
				</div>

				<div id="stkb_metrics_results" style="display:none;">
					<div class="stkb-metrics-row">
						<!-- Column 1: Counts -->
						<div class="stkb-metric-col">
							<div class="stkb-metric-card" style="padding: 15px;">
								<h3><?php esc_html_e( 'Touched Tickets', 'stackboost-for-supportcandy' ); ?></h3>
								<p id="stkb_metric_touched_tickets">0</p>
							</div>
							<div class="stkb-metric-card" style="padding: 15px;">
								<h3><?php esc_html_e( 'Active Backlog', 'stackboost-for-supportcandy' ); ?></h3>
								<p id="stkb_metric_active_backlog">0</p>
							</div>
							<div class="stkb-metric-card" style="padding: 15px; display: flex; justify-content: space-between;">
								<div style="flex: 1; text-align: center; border-right: 1px solid var(--sb-card-border, #ccd0d4);">
									<h3><?php esc_html_e( 'Tickets Created', 'stackboost-for-supportcandy' ); ?></h3>
									<p id="stkb_metric_total">0</p>
								</div>
								<div style="flex: 1; text-align: center; border-right: 1px solid var(--sb-card-border, #ccd0d4);">
									<h3><?php esc_html_e( 'Tickets Closed', 'stackboost-for-supportcandy' ); ?></h3>
									<p id="stkb_metric_total_closed">0</p>
								</div>
								<div style="flex: 1; text-align: center;">
									<h3><?php esc_html_e( 'Resolution Rate', 'stackboost-for-supportcandy' ); ?></h3>
									<p id="stkb_metric_resolution_rate">0%</p>
								</div>
							</div>
						</div>

						<!-- Column 2: Averages & SLAs -->
						<div class="stkb-metric-col">
							<div class="stkb-metric-card" style="padding: 15px; display: flex; justify-content: space-between;">
								<div style="flex: 1; text-align: center; border-right: 1px solid var(--sb-card-border, #ccd0d4);">
									<h3><?php esc_html_e( 'Avg Time to Close', 'stackboost-for-supportcandy' ); ?></h3>
									<p id="stkb_metric_avg_open">0</p>
								</div>
								<div style="flex: 1; text-align: center;">
									<h3><?php esc_html_e( 'Avg Age (Open)', 'stackboost-for-supportcandy' ); ?></h3>
									<p id="stkb_metric_avg_age_open">0</p>
								</div>
							</div>
							<div class="stkb-metric-card" style="padding: 15px; display: flex; justify-content: space-between;">
								<div style="flex: 1; text-align: center; border-right: 1px solid var(--sb-card-border, #ccd0d4);">
									<h3><?php esc_html_e( 'Avg Initial Response', 'stackboost-for-supportcandy' ); ?></h3>
									<p id="stkb_metric_avg_response">0</p>
								</div>
								<div style="flex: 1; text-align: center;">
									<h3><?php esc_html_e( 'Avg Touches/Ticket', 'stackboost-for-supportcandy' ); ?></h3>
									<p id="stkb_metric_avg_touches">0</p>
								</div>
							</div>
							<div class="stkb-metric-card" id="stkb_metric_sla_card" style="display:none; padding: 15px; justify-content: space-between;">
								<div style="flex: 1; text-align: center; border-right: 1px solid var(--sb-card-border, #ccd0d4);">
									<h3 style="color:#d63638;"><?php esc_html_e( 'FRT SLA Breach', 'stackboost-for-supportcandy' ); ?></h3>
									<p id="stkb_metric_sla_frt_breach" style="color:#d63638;">N/A</p>
								</div>
								<div style="flex: 1; text-align: center;">
									<h3 style="color:#d63638;"><?php esc_html_e( 'Resolution SLA Breach', 'stackboost-for-supportcandy' ); ?></h3>
									<p id="stkb_metric_sla_resolution_breach" style="color:#d63638;">N/A</p>
								</div>
							</div>
							<div class="stkb-metric-card" id="stkb_metric_survey_card" style="display:none; padding: 15px; justify-content: space-between;">
								<div style="flex: 1; text-align: center; border-right: 1px solid var(--sb-card-border, #ccd0d4);">
									<h3><?php esc_html_e( 'Survey Response Rate', 'stackboost-for-supportcandy' ); ?></h3>
									<p id="stkb_metric_survey_rate">N/A</p>
								</div>
								<div style="flex: 1; text-align: center;">
									<h3><?php esc_html_e( 'Avg CSAT Score', 'stackboost-for-supportcandy' ); ?></h3>
									<p id="stkb_metric_survey_csat">N/A</p>
								</div>
							</div>
						</div>
					</div>

					<!-- Breakdowns (Always generated) -->
					<div class="stkb-breakdown-wrapper">
						<div class="stkb-breakdown-col">
							<h3><?php esc_html_e( 'Agent Breakdown', 'stackboost-for-supportcandy' ); ?></h3>
							<table class="wp-list-table widefat fixed striped">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Agent', 'stackboost-for-supportcandy' ); ?></th>
										<th style="text-align:center; width:120px;"><?php esc_html_e( 'Assigned', 'stackboost-for-supportcandy' ); ?></th>
										<th style="text-align:center; width:120px;"><?php esc_html_e( 'Closed', 'stackboost-for-supportcandy' ); ?></th>
										<th style="text-align:center; width:120px;"><?php esc_html_e( 'Avg CSAT', 'stackboost-for-supportcandy' ); ?></th>
									</tr>
								</thead>
								<tbody id="stkb_agent_breakdown_body">
								</tbody>
							</table>
							<div class="stkb-chart-container">
								<canvas id="stkb_agent_chart"></canvas>
							</div>
						</div>
						<div class="stkb-breakdown-col">
							<h3><?php esc_html_e( 'Type Breakdown', 'stackboost-for-supportcandy' ); ?></h3>
							<table class="wp-list-table widefat fixed striped">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Type', 'stackboost-for-supportcandy' ); ?></th>
										<th style="text-align:center; width:120px;"><?php esc_html_e( 'Tickets', 'stackboost-for-supportcandy' ); ?></th>
									</tr>
								</thead>
								<tbody id="stkb_type_breakdown_body">
								</tbody>
							</table>
							<div class="stkb-chart-container">
								<canvas id="stkb_type_chart"></canvas>
							</div>
						</div>
					</div>

					<div class="stackboost-card" style="margin-top: 20px;">
						<h3><?php esc_html_e( 'Ticket Submission Heatmap (Time of Day)', 'stackboost-for-supportcandy' ); ?></h3>
						<p class="description" style="margin-top: 0; margin-bottom: 10px;"><?php esc_html_e( 'Displays the density of new tickets created during the selected date range. Darker cells indicate higher volume.', 'stackboost-for-supportcandy' ); ?></p>
						<div style="overflow-x: auto;">
							<table class="stkb-heatmap-table" id="stkb_heatmap_table">
								<thead>
									<tr>
										<th style="width: 80px;"><?php esc_html_e( 'Day / Hour', 'stackboost-for-supportcandy' ); ?></th>
										<!-- Hours 0-23 will be injected by JS -->
									</tr>
								</thead>
								<tbody>
									<!-- Days will be injected by JS -->
								</tbody>
							</table>
						</div>
					</div>
				</div>
			</div>

			<div id="tab-settings" class="stackboost-tab-content" style="display: none;">
				<form action="options.php" method="post">
					<?php
					settings_fields( 'stackboost_settings' );
					echo '<input type="hidden" name="stackboost_settings[page_slug]" value="stackboost-ticket-metrics">';
					?>
					<div class="stackboost-dashboard-grid">
						<div class="stackboost-card">
							<h2><?php esc_html_e( 'Configuration', 'stackboost-for-supportcandy' ); ?></h2>
							<table class="form-table">
								<tr>
									<th scope="row"><label for="stkb_type_field_setting"><?php esc_html_e( 'Ticket Type Field', 'stackboost-for-supportcandy' ); ?></label></th>
									<td>
										<select name="stackboost_settings[ticket_metrics_type_field]" id="stkb_type_field_setting">
											<?php foreach ( $all_type_fields as $key => $label ) : ?>
												<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $saved_type_field, $key ); ?>><?php echo esc_html( $label ); ?></option>
											<?php endforeach; ?>
										</select>
										<p class="description"><?php esc_html_e( 'Select the field used to generate the Type Breakdown.', 'stackboost-for-supportcandy' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="stkb_enable_agent_group_filter"><?php esc_html_e( 'Enable Department (Agent Group) Filter', 'stackboost-for-supportcandy' ); ?></label></th>
									<td>
										<label class="stackboost-switch">
											<input type="checkbox" id="stkb_enable_agent_group_filter" name="stackboost_settings[ticket_metrics_enable_agent_group_filter]" value="1" <?php checked( $enable_agent_group_filter, true ); ?>>
											<span class="stackboost-slider round"></span>
										</label>
										<p class="description"><?php esc_html_e( 'If enabled, a filter dropdown will appear on the dashboard allowing you to view metrics isolated to a specific SupportCandy Agent Group (Department).', 'stackboost-for-supportcandy' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="stkb_chart_type_agent"><?php esc_html_e( 'Agent Chart Type', 'stackboost-for-supportcandy' ); ?></label></th>
									<td>
										<select name="stackboost_settings[ticket_metrics_chart_type_agent]" id="stkb_chart_type_agent">
											<option value="none" <?php selected( $chart_type_agent, 'none' ); ?>><?php esc_html_e( 'None (Hide Chart)', 'stackboost-for-supportcandy' ); ?></option>
											<option value="pie" <?php selected( $chart_type_agent, 'pie' ); ?>><?php esc_html_e( 'Pie', 'stackboost-for-supportcandy' ); ?></option>
											<option value="doughnut" <?php selected( $chart_type_agent, 'doughnut' ); ?>><?php esc_html_e( 'Doughnut', 'stackboost-for-supportcandy' ); ?></option>
											<option value="multi_pie" <?php selected( $chart_type_agent, 'multi_pie' ); ?>><?php esc_html_e( 'Multi-series Pie', 'stackboost-for-supportcandy' ); ?></option>
											<option value="multi_doughnut" <?php selected( $chart_type_agent, 'multi_doughnut' ); ?>><?php esc_html_e( 'Multi-series Doughnut', 'stackboost-for-supportcandy' ); ?></option>
											<option value="bar" <?php selected( $chart_type_agent, 'bar' ); ?>><?php esc_html_e( 'Bar', 'stackboost-for-supportcandy' ); ?></option>
											<option value="line" <?php selected( $chart_type_agent, 'line' ); ?>><?php esc_html_e( 'Line', 'stackboost-for-supportcandy' ); ?></option>
											<option value="radar" <?php selected( $chart_type_agent, 'radar' ); ?>><?php esc_html_e( 'Radar', 'stackboost-for-supportcandy' ); ?></option>
											<option value="polarArea" <?php selected( $chart_type_agent, 'polarArea' ); ?>><?php esc_html_e( 'Polar Area', 'stackboost-for-supportcandy' ); ?></option>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="stkb_chart_type_type"><?php esc_html_e( 'Type Chart Type', 'stackboost-for-supportcandy' ); ?></label></th>
									<td>
										<select name="stackboost_settings[ticket_metrics_chart_type_type]" id="stkb_chart_type_type">
											<option value="none" <?php selected( $chart_type_type, 'none' ); ?>><?php esc_html_e( 'None (Hide Chart)', 'stackboost-for-supportcandy' ); ?></option>
											<option value="pie" <?php selected( $chart_type_type, 'pie' ); ?>><?php esc_html_e( 'Pie', 'stackboost-for-supportcandy' ); ?></option>
											<option value="doughnut" <?php selected( $chart_type_type, 'doughnut' ); ?>><?php esc_html_e( 'Doughnut', 'stackboost-for-supportcandy' ); ?></option>
											<option value="bar" <?php selected( $chart_type_type, 'bar' ); ?>><?php esc_html_e( 'Bar', 'stackboost-for-supportcandy' ); ?></option>
											<option value="line" <?php selected( $chart_type_type, 'line' ); ?>><?php esc_html_e( 'Line', 'stackboost-for-supportcandy' ); ?></option>
											<option value="radar" <?php selected( $chart_type_type, 'radar' ); ?>><?php esc_html_e( 'Radar', 'stackboost-for-supportcandy' ); ?></option>
											<option value="polarArea" <?php selected( $chart_type_type, 'polarArea' ); ?>><?php esc_html_e( 'Polar Area', 'stackboost-for-supportcandy' ); ?></option>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="stkb_chart_type_secondary"><?php esc_html_e( 'Secondary Breakdowns Chart Type', 'stackboost-for-supportcandy' ); ?></label></th>
									<td>
										<select name="stackboost_settings[ticket_metrics_chart_type_secondary]" id="stkb_chart_type_secondary">
											<option value="none" <?php selected( $chart_type_secondary, 'none' ); ?>><?php esc_html_e( 'None (Hide Chart)', 'stackboost-for-supportcandy' ); ?></option>
											<option value="pie" <?php selected( $chart_type_secondary, 'pie' ); ?>><?php esc_html_e( 'Pie', 'stackboost-for-supportcandy' ); ?></option>
											<option value="doughnut" <?php selected( $chart_type_secondary, 'doughnut' ); ?>><?php esc_html_e( 'Doughnut', 'stackboost-for-supportcandy' ); ?></option>
											<option value="bar" <?php selected( $chart_type_secondary, 'bar' ); ?>><?php esc_html_e( 'Bar', 'stackboost-for-supportcandy' ); ?></option>
											<option value="line" <?php selected( $chart_type_secondary, 'line' ); ?>><?php esc_html_e( 'Line', 'stackboost-for-supportcandy' ); ?></option>
											<option value="radar" <?php selected( $chart_type_secondary, 'radar' ); ?>><?php esc_html_e( 'Radar', 'stackboost-for-supportcandy' ); ?></option>
											<option value="polarArea" <?php selected( $chart_type_secondary, 'polarArea' ); ?>><?php esc_html_e( 'Polar Area', 'stackboost-for-supportcandy' ); ?></option>
										</select>
										<p class="description"><?php esc_html_e( 'Controls the chart style displayed for "Other Issues" subcategory breakdowns inside modals.', 'stackboost-for-supportcandy' ); ?></p>
									</td>
								</tr>
							</table>
						</div>

						<div class="stackboost-card">
							<h2><?php esc_html_e( 'Metrics Computation', 'stackboost-for-supportcandy' ); ?></h2>
							<table class="form-table">
								<tr>
									<th scope="row"><label for="stkb_frt_mode"><?php esc_html_e( 'First Response Calculation', 'stackboost-for-supportcandy' ); ?></label></th>
									<td>
										<select name="stackboost_settings[ticket_metrics_frt_mode]" id="stkb_frt_mode">
											<option value="stackboost" <?php selected( $frt_mode, 'stackboost' ); ?>><?php esc_html_e( 'StackBoost ("Everything Counts")', 'stackboost-for-supportcandy' ); ?></option>
											<option value="supportcandy" <?php selected( $frt_mode, 'supportcandy' ); ?>><?php esc_html_e( 'SupportCandy Native (FRT field)', 'stackboost-for-supportcandy' ); ?></option>
										</select>
										<p class="description"><?php esc_html_e( 'Choose whether to calculate Initial Response using StackBoost\'s strict thread timeline model or SupportCandy\'s native FRT database field (which respects clock gating/deferrals).', 'stackboost-for-supportcandy' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="stkb_verbose_logging"><?php esc_html_e( 'Verbose Logging', 'stackboost-for-supportcandy' ); ?></label></th>
									<td>
										<label>
											<input type="checkbox" name="stackboost_settings[ticket_metrics_verbose_logging]" id="stkb_verbose_logging" value="1" <?php checked( $verbose_logging ); ?> />
											<?php esc_html_e( 'Enable deep diagnostic logging for metrics generation arrays.', 'stackboost-for-supportcandy' ); ?>
										</label>
										<p class="description"><?php esc_html_e( 'Requires the general Ticket Metrics diagnostic log toggle to be enabled in the Diagnostics tab.', 'stackboost-for-supportcandy' ); ?></p>
									</td>
								</tr>
							</table>
						</div>

						<div class="stackboost-card">
							<h2><?php esc_html_e( 'Agent Filtering', 'stackboost-for-supportcandy' ); ?></h2>
							<p class="description"><?php esc_html_e( 'Select which agents to track individually. Unselected agents will be grouped together into a general "Other" category.', 'stackboost-for-supportcandy' ); ?></p>
							<table class="form-table">
								<tr>
									<th scope="row"><label for="stkb_show_other_agents"><?php esc_html_e( 'Display "Other Agents"', 'stackboost-for-supportcandy' ); ?></label></th>
									<td>
										<label>
											<input type="checkbox" name="stackboost_settings[ticket_metrics_show_other_agents]" id="stkb_show_other_agents" value="1" <?php checked( $show_other_agents ); ?> />
											<?php esc_html_e( 'Show the "Other Agents" group slice on the metrics charts.', 'stackboost-for-supportcandy' ); ?>
										</label>
										<p class="description"><?php esc_html_e( 'If disabled, untracked agents will be completely hidden from the metrics entirely instead of being grouped.', 'stackboost-for-supportcandy' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="stkb_tracked_agents"><?php esc_html_e( 'Tracked Agents', 'stackboost-for-supportcandy' ); ?></label></th>
									<td>
										<div class="stkb-agent-filter-container">
											<div class="stkb-agent-filter-box">
												<h4><?php esc_html_e( 'Other Agents (Grouped)', 'stackboost-for-supportcandy' ); ?></h4>
												<select multiple id="stkb_tracked_agents_available" size="10">
													<?php foreach ( $all_agents as $id => $name ) : ?>
														<?php if ( $is_track_none || (! empty( $tracked_agents ) && ! in_array( $id, $tracked_agents )) ) : ?>
															<option value="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $name ); ?></option>
														<?php endif; ?>
													<?php endforeach; ?>
												</select>
											</div>
											<div class="stkb-agent-filter-buttons">
												<button type="button" class="button" id="stkb_agent_add_all" title="<?php esc_attr_e( 'Track All', 'stackboost-for-supportcandy' ); ?>"><span class="dashicons dashicons-controls-forward"></span></button>
												<button type="button" class="button" id="stkb_agent_add" title="<?php esc_attr_e( 'Track Selected', 'stackboost-for-supportcandy' ); ?>"><span class="dashicons dashicons-arrow-right"></span></button>
												<button type="button" class="button" id="stkb_agent_remove" title="<?php esc_attr_e( 'Untrack Selected', 'stackboost-for-supportcandy' ); ?>"><span class="dashicons dashicons-arrow-left"></span></button>
												<button type="button" class="button" id="stkb_agent_remove_all" title="<?php esc_attr_e( 'Untrack All', 'stackboost-for-supportcandy' ); ?>"><span class="dashicons dashicons-controls-back"></span></button>
											</div>
											<div class="stkb-agent-filter-box">
												<h4><?php esc_html_e( 'Tracked Agents (Individual)', 'stackboost-for-supportcandy' ); ?></h4>
												<select multiple name="stackboost_settings[ticket_metrics_tracked_agents][]" id="stkb_tracked_agents" size="10">
													<?php if ( empty( $tracked_agents ) ) : ?>
														<?php foreach ( $all_agents as $id => $name ) : ?>
															<option value="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $name ); ?></option>
														<?php endforeach; ?>
													<?php elseif ( ! $is_track_none ): ?>
														<?php foreach ( $tracked_agents as $id ) : ?>
															<?php if ( isset( $all_agents[$id] ) ) : ?>
																<option value="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $all_agents[$id] ); ?></option>
															<?php endif; ?>
														<?php endforeach; ?>
													<?php endif; ?>
												</select>
											</div>
										</div>
									</td>
								</tr>
							</table>
						</div>

						<div class="stackboost-card">
							<div style="display:flex; justify-content:space-between; align-items:center;">
								<h2><?php esc_html_e( 'Subcategory Breakdown & Report Rules', 'stackboost-for-supportcandy' ); ?></h2>
								<button type="button" class="button button-primary" id="stkb_add_other_rule"><?php esc_html_e( '+ Add Rule', 'stackboost-for-supportcandy' ); ?></button>
							</div>
							<p class="description"><?php esc_html_e( 'Configure which fields should display a subcategory breakdown table in the dashboard modal. You can also optionally specify "Other" conditions to generate downloadable word cloud CSV reports.', 'stackboost-for-supportcandy' ); ?></p>

							<table class="wp-list-table widefat fixed striped" id="stkb_other_issues_rules_table" style="margin-top: 15px;">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Breakdown Field', 'stackboost-for-supportcandy' ); ?></th>
										<th><?php esc_html_e( '"Other" Options (Optional)', 'stackboost-for-supportcandy' ); ?></th>
										<th><?php esc_html_e( 'Trend Analysis Source Field (Optional)', 'stackboost-for-supportcandy' ); ?></th>
										<th style="width: 100px; text-align:center;"><?php esc_html_e( 'Actions', 'stackboost-for-supportcandy' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php if ( empty( $other_issues_rules ) ) : ?>
										<tr class="stkb-no-rules-row"><td colspan="4" style="text-align:center;"><?php esc_html_e( 'No rules configured. Click "Add Rule" to get started.', 'stackboost-for-supportcandy' ); ?></td></tr>
									<?php else : ?>
										<?php foreach ( $other_issues_rules as $index => $rule ) : ?>
											<tr class="stkb-rule-row" data-index="<?php echo esc_attr($index); ?>">
												<td>
													<span class="stkb-display-trigger-field"><?php echo esc_html( $all_type_fields[$rule['trigger_field']] ?? $rule['trigger_field'] ); ?></span>
													<input type="hidden" class="stkb-rule-trigger-field" value="<?php echo esc_attr( $rule['trigger_field'] ); ?>" />
												</td>
												<td>
													<span class="stkb-display-trigger-condition">
														<?php
															$conds = is_array($rule['trigger_condition']) ? $rule['trigger_condition'] : explode(',', $rule['trigger_condition']);
															$conds = array_map('trim', array_filter($conds));

															// Map to friendly names if possible
															$friendly_conds = [];
															$map = $trigger_condition_maps[$rule['trigger_field']] ?? [];
															foreach ($conds as $c) {
																$friendly_conds[] = $map[$c] ?? $c;
															}

															echo esc_html( implode(', ', $friendly_conds) );
														?>
													</span>
													<input type="hidden" class="stkb-rule-trigger-condition" value="<?php echo esc_attr( is_array($rule['trigger_condition']) ? json_encode($rule['trigger_condition']) : $rule['trigger_condition'] ); ?>" />
												</td>
												<td>
													<span class="stkb-display-text-field"><?php echo esc_html( $all_text_fields[$rule['text_field']] ?? $rule['text_field'] ); ?></span>
													<input type="hidden" class="stkb-rule-text-field" value="<?php echo esc_attr( $rule['text_field'] ); ?>" />
												</td>
												<td style="text-align:center;">
													<button type="button" class="stkb-edit-rule" style="color:#d68a00; background:none; border:none; box-shadow:none; cursor:pointer;" title="<?php esc_attr_e( 'Edit Rule', 'stackboost-for-supportcandy' ); ?>"><span class="dashicons dashicons-edit"></span></button>
													<button type="button" class="stkb-remove-rule" style="color:#d63638; background:none; border:none; box-shadow:none; cursor:pointer;" title="<?php esc_attr_e( 'Delete Rule', 'stackboost-for-supportcandy' ); ?>"><span class="dashicons dashicons-trash"></span></button>
												</td>
											</tr>
										<?php endforeach; ?>
									<?php endif; ?>
								</tbody>
							</table>

							<hr style="margin: 30px 0;">

							<h3 style="margin-bottom:10px;"><?php esc_html_e( 'Trend Analysis AI Settings', 'stackboost-for-supportcandy' ); ?></h3>
							<p class="description" style="margin-top:0; margin-bottom:15px;"><?php esc_html_e( 'Configure the AI connection used to generate Trend Analysis reports from ticket data.', 'stackboost-for-supportcandy' ); ?></p>

							<table class="form-table" role="presentation">
								<tbody>
									<tr>
										<th scope="row"><label for="stkb_gemini_api_key"><?php esc_html_e( 'Gemini API Key', 'stackboost-for-supportcandy' ); ?></label></th>
										<td>
											<?php if ( $api_key_locked ) : ?>
												<div style="display:flex; align-items:center; gap:10px;">
													<input type="password" name="ticket_metrics_gemini_api_key" id="stkb_gemini_api_key" value="********" class="regular-text" readonly="readonly" style="background:#f0f0f1; border-color:#8c8f94; cursor:not-allowed;">
													<button type="button" class="button" id="stkb_deactivate_api_key" style="color:#d63638; border-color:#d63638;"><?php esc_html_e( 'Deactivate / Remove Key', 'stackboost-for-supportcandy' ); ?></button>
												</div>
												<p class="description" id="stkb_api_key_desc"><?php esc_html_e( 'Your API key is currently locked and active.', 'stackboost-for-supportcandy' ); ?></p>
											<?php else : ?>
												<div style="display:flex; align-items:center; gap:10px;">
													<input type="password" name="ticket_metrics_gemini_api_key" id="stkb_gemini_api_key" value="" class="regular-text" autocomplete="new-password" placeholder="<?php esc_attr_e( 'Paste your API key here...', 'stackboost-for-supportcandy' ); ?>">
												</div>
												<p class="description" id="stkb_api_key_desc"><?php esc_html_e( 'Enter your Google Gemini API key to enable AI-powered trend analysis. Once saved, the key will be locked to prevent accidental changes.', 'stackboost-for-supportcandy' ); ?></p>
											<?php endif; ?>
										</td>
									</tr>
								</tbody>
							</table>

							<p class="submit">
								<?php submit_button( __( 'Save Settings', 'stackboost-for-supportcandy' ), 'primary', 'submit', false ); ?>
							</p>
						</div>
					</div>
				</form>
			</div>

			<div id="tab-sla" class="stackboost-tab-content" style="display: none;">
				<form action="options.php" method="post" id="stkb_sla_form">
					<?php
					settings_fields( 'stackboost_settings' );
					echo '<input type="hidden" name="stackboost_settings[page_slug]" value="stackboost-ticket-metrics">';
					?>
					<div class="stackboost-dashboard-grid">
						<div class="stackboost-card">
							<h2><?php esc_html_e( 'SLA Threshold Configuration', 'stackboost-for-supportcandy' ); ?></h2>
							<p class="description"><?php esc_html_e( 'Configure your Service Level Agreement (SLA) targets in hours. Enter 0 to disable tracking for that metric. These targets are used to calculate the SLA Breach Percentage in your dashboard metrics.', 'stackboost-for-supportcandy' ); ?></p>
							<table class="form-table">
								<tr>
									<th scope="row"><label for="stkb_sla_frt_hours"><?php esc_html_e( 'First Response Time (Hours)', 'stackboost-for-supportcandy' ); ?></label></th>
									<td>
										<input type="number" step="0.5" min="0" name="stackboost_settings[ticket_metrics_sla_frt_hours]" id="stkb_sla_frt_hours" value="<?php echo esc_attr( $sla_frt_hours ); ?>" class="small-text">
										<p class="description"><?php esc_html_e( 'The target number of hours an agent has to initially respond to a new ticket.', 'stackboost-for-supportcandy' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="stkb_sla_resolution_hours"><?php esc_html_e( 'Resolution Time (Hours)', 'stackboost-for-supportcandy' ); ?></label></th>
									<td>
										<input type="number" step="0.5" min="0" name="stackboost_settings[ticket_metrics_sla_resolution_hours]" id="stkb_sla_resolution_hours" value="<?php echo esc_attr( $sla_resolution_hours ); ?>" class="small-text">
										<p class="description"><?php esc_html_e( 'The target number of hours an agent has to fully resolve (close) a ticket.', 'stackboost-for-supportcandy' ); ?></p>
									</td>
								</tr>
							</table>

							<h2 style="margin-top: 30px;"><?php esc_html_e( 'Survey CSAT Integration', 'stackboost-for-supportcandy' ); ?></h2>
							<p class="description"><?php esc_html_e( 'Configure how the Ticket Metrics dashboard calculates and displays Customer Satisfaction (CSAT) scores from the After Ticket Survey module.', 'stackboost-for-supportcandy' ); ?></p>
							<table class="form-table">
								<tr>
									<th scope="row"><label for="stkb_survey_categories"><?php esc_html_e( 'Tracked Survey Categories', 'stackboost-for-supportcandy' ); ?></label></th>
									<td>
										<select multiple name="stackboost_settings[ticket_metrics_survey_categories][]" id="stkb_survey_categories" style="width: 300px; height: 100px;">
											<option value="0" <?php echo in_array('0', $survey_categories) ? 'selected' : ''; ?>><?php esc_html_e( '-- Uncategorized Questions --', 'stackboost-for-supportcandy' ); ?></option>
											<?php foreach ( $all_survey_categories as $id => $name ) : ?>
												<option value="<?php echo esc_attr( $id ); ?>" <?php echo in_array((string)$id, $survey_categories) ? 'selected' : ''; ?>><?php echo esc_html( $name ); ?></option>
											<?php endforeach; ?>
										</select>
										<p class="description"><?php esc_html_e( 'Select which question categories should be mathematically tied to Agent CSAT scores. If none are selected, all rating questions are calculated.', 'stackboost-for-supportcandy' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="stkb_survey_max_score"><?php esc_html_e( 'Maximum Survey Score', 'stackboost-for-supportcandy' ); ?></label></th>
									<td>
										<input type="number" step="0.1" min="0" name="stackboost_settings[ticket_metrics_survey_max_score]" id="stkb_survey_max_score" value="<?php echo esc_attr( $survey_max_score ); ?>" class="small-text">
										<p class="description"><?php esc_html_e( 'Specify the maximum possible numeric rating your survey allows (e.g., 5 or 10). If configured, the metrics dashboard will automatically normalize the raw CSAT average into a percentage. Leave as 0 to display the raw aggregate average without formatting.', 'stackboost-for-supportcandy' ); ?></p>
									</td>
								</tr>
							</table>

							<p class="submit">
								<?php submit_button( __( 'Save SLA Settings', 'stackboost-for-supportcandy' ), 'primary', 'submit', false ); ?>
							</p>
						</div>
					</div>
				</form>
			</div>

			<!-- Dynamic Modal Container for Rules -->
			<div id="stkb-rule-modal" class="stackboost-modal" style="display:none; align-items:center; justify-content:center; z-index:99999;">
				<div class="stackboost-modal-content" style="max-width: 500px; width:100%; display:flex; flex-direction:column; padding: 20px;">
					<h3 id="stkb-rule-modal-title" style="margin-top:0; border-bottom: 1px solid #ccc; padding-bottom:10px;"><?php esc_html_e( 'Add Rule', 'stackboost-for-supportcandy' ); ?></h3>
					<div class="stackboost-modal-body" style="padding: 15px 0;">
						<input type="hidden" id="stkb-rule-modal-index" value="" />
						<table class="form-table">
							<tr>
								<th scope="row"><label for="stkb-modal-trigger-field"><?php esc_html_e( 'Breakdown Field', 'stackboost-for-supportcandy' ); ?></label></th>
								<td>
									<select id="stkb-modal-trigger-field" style="width:100%;">
										<option value=""><?php esc_html_e( '-- Select Field --', 'stackboost-for-supportcandy' ); ?></option>
										<?php foreach ( $all_type_fields as $key => $label ) : ?>
											<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="stkb-modal-trigger-condition"><?php esc_html_e( '"Other" Options (Optional)', 'stackboost-for-supportcandy' ); ?></label>
									<span class="dashicons dashicons-update" id="stkb-modal-cond-spinner" style="display:none; animation: dashicons-spin 2s infinite linear; font-size: 16px; line-height: 24px;"></span>
								</th>
								<td>
									<select id="stkb-modal-trigger-condition" multiple="multiple" style="width:100%;" disabled>
										<!-- Options dynamically populated via AJAX based on trigger field -->
									</select>
									<p class="description" style="margin-top:2px; font-size:11px;"><?php esc_html_e( 'Optional. Select the options that represent "Other" to enable Trend Analysis CSV exports.', 'stackboost-for-supportcandy' ); ?></p>
								</td>
							</tr>
							<tr id="stkb-modal-text-field-row" style="display:none;">
								<th scope="row"><label for="stkb-modal-text-field"><?php esc_html_e( 'Trend Analysis Source Field (Optional)', 'stackboost-for-supportcandy' ); ?></label></th>
								<td>
									<select id="stkb-modal-text-field" style="width:100%;">
										<option value=""><?php esc_html_e( '-- Select Text Field --', 'stackboost-for-supportcandy' ); ?></option>
										<?php foreach ( $all_text_fields as $key => $label ) : ?>
											<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
										<?php endforeach; ?>
									</select>
									<p class="description" style="margin-top:2px; font-size:11px;"><?php esc_html_e( 'Optional. The field containing the text/excuse exported for the Trend Analysis. Required if "Other" options are selected.', 'stackboost-for-supportcandy' ); ?></p>
								</td>
							</tr>
						</table>
					</div>
					<div style="display:flex; justify-content:flex-end; gap:10px; border-top: 1px solid #ccc; padding-top:15px;">
						<button type="button" class="button" id="stkb-rule-modal-cancel"><?php esc_html_e( 'Cancel', 'stackboost-for-supportcandy' ); ?></button>
						<button type="button" class="button button-primary" id="stkb-rule-modal-save"><?php esc_html_e( 'Save Rule', 'stackboost-for-supportcandy' ); ?></button>
					</div>
				</div>
			</div>

			<!-- Dynamic Modal Container -->
			<div id="stkb-metrics-modal" class="stackboost-modal" style="display:none; align-items:center; justify-content:center;">
				<div class="stackboost-modal-content" style="max-width: 800px; width:100%; max-height: 80vh; display:flex; flex-direction:column;">
					<span class="stackboost-modal-close-button" style="align-self: flex-end;">&times;</span>
					<div id="stkb-metrics-modal-body" class="stackboost-modal-body" style="overflow-y:auto; flex-grow:1; padding-right:15px;"></div>
				</div>
			</div>

			<script>
				jQuery(document).ready(function($) {
					let agentChart = null;
					let typeChart = null;

					// Basic color palette that matches typical admin themes
					const chartColors = [
						'#2271b1', '#d63638', '#00a32a', '#dba617', '#72aee6',
						'#f8c43d', '#ed5e60', '#68de7c', '#b32d2e', '#135e96',
						'#ff8a65', '#ba68c8', '#4db6ac', '#aed581', '#f06292'
					];

					let secondaryCharts = [];

					// Agent Filter Two-Box Logic
					$('#stkb_agent_add').on('click', function() {
						$('#stkb_tracked_agents_available option:selected').appendTo('#stkb_tracked_agents');
					});

					$('#stkb_agent_remove').on('click', function() {
						$('#stkb_tracked_agents option:selected').appendTo('#stkb_tracked_agents_available');
					});

					$('#stkb_agent_add_all').on('click', function() {
						$('#stkb_tracked_agents_available option').appendTo('#stkb_tracked_agents');
					});

					$('#stkb_agent_remove_all').on('click', function() {
						$('#stkb_tracked_agents option').appendTo('#stkb_tracked_agents_available');
					});

					// Other Issues Rules Logic
					let rulesTable = $('#stkb_other_issues_rules_table tbody');
					let ruleModal = $('#stkb-rule-modal');

					// Helper function to safely init SelectWoo/Select2 depending on environment availability
					function initSelect(element, options = {}) {
						if ($.fn.selectWoo) {
							$(element).selectWoo(options);
						} else if ($.fn.select2) {
							$(element).select2(options);
						}
					}

					// Initialize static select on modal load
					initSelect('#stkb-modal-trigger-field');
					initSelect('#stkb-modal-text-field');
					initSelect('#stkb-modal-trigger-condition', { width: '100%', placeholder: '<?php esc_attr_e('Select options', 'stackboost-for-supportcandy'); ?>' });

					function openRuleModal(index, triggerField = '', triggerCondition = '[]', textField = 'subject') {
						$('#stkb-rule-modal-index').val(index);

						// Try to parse the condition if it's a JSON string
						let conds = [];
						try {
							conds = JSON.parse(triggerCondition);
						} catch (e) {
							conds = triggerCondition ? [triggerCondition] : [];
						}

						$('#stkb-modal-trigger-field').val(triggerField).trigger('change');

						if (conds.length > 0) {
							$('#stkb-modal-text-field-row').show();
							$('#stkb-modal-text-field').val(textField).trigger('change');
						} else {
							$('#stkb-modal-text-field-row').hide();
							$('#stkb-modal-text-field').val('').trigger('change');
						}

						// Store conditions temporarily so they can be set after AJAX loads the options
						$('#stkb-modal-trigger-condition').data('selected', conds);

						if (index === 'new') {
							$('#stkb-rule-modal-title').text('<?php esc_html_e( 'Add Rule', 'stackboost-for-supportcandy' ); ?>');
						} else {
							$('#stkb-rule-modal-title').text('<?php esc_html_e( 'Edit Rule', 'stackboost-for-supportcandy' ); ?>');
						}

						ruleModal.hide().css('display', 'flex').hide().fadeIn(200);
					}

					$('#stkb_add_other_rule').on('click', function() {
						openRuleModal('new');
					});

					$(document).on('click', '.stkb-edit-rule', function() {
						let row = $(this).closest('tr');
						let index = row.attr('data-index');
						let triggerField = row.find('.stkb-rule-trigger-field').val();
						let triggerCondition = row.find('.stkb-rule-trigger-condition').val();
						let textField = row.find('.stkb-rule-text-field').val();

						openRuleModal(index, triggerField, triggerCondition, textField);
					});

					$('#stkb-rule-modal-cancel').on('click', function() {
						ruleModal.fadeOut(200);
					});

					$('#stkb-modal-trigger-condition').on('change', function() {
						let vals = $(this).val() || [];
						if (vals.length > 0) {
							$('#stkb-modal-text-field-row').show();
						} else {
							$('#stkb-modal-text-field-row').hide();
							$('#stkb-modal-text-field').val('').trigger('change');
						}
					});

					$('#stkb-modal-trigger-field').on('change', function() {
						let field_slug = $(this).val();
						let conditionSelect = $('#stkb-modal-trigger-condition');
						let spinner = $('#stkb-modal-cond-spinner');

						conditionSelect.empty().prop('disabled', true).trigger('change');

						if (!field_slug) return;

						spinner.show();

						$.post(ajaxurl, {
							action: 'stackboost_get_field_options',
							nonce: stackboost_admin_ajax.nonce,
							field_slug: field_slug
						}, function(response) {
							spinner.hide();
							if (response.success && response.data) {
								$.each(response.data, function(id, text) {
									let newOption = new Option(text, id, false, false);
									conditionSelect.append(newOption);
								});

								// Re-apply selected values
								let selectedVals = conditionSelect.data('selected') || [];
								conditionSelect.val(selectedVals);

								conditionSelect.prop('disabled', false).trigger('change');
							}
						});
					});

					$('#stkb-rule-modal-save').on('click', function() {
						let index = $('#stkb-rule-modal-index').val();
						let tField = $('#stkb-modal-trigger-field').val();
						let tFieldText = $('#stkb-modal-trigger-field option:selected').text();
						let tCond = $('#stkb-modal-trigger-condition').val() || [];

						// Get the text representations of the selected options for display
						let tCondTexts = [];
						$('#stkb-modal-trigger-condition option:selected').each(function() {
							tCondTexts.push($(this).text());
						});

						let txtField = $('#stkb-modal-text-field').val();
						let txtFieldText = $('#stkb-modal-text-field option:selected').text();

						if (!tField) {
							alert('<?php echo esc_js( __( 'Please select a Breakdown Field.', 'stackboost-for-supportcandy' ) ); ?>');
							return;
						}

						if ((tCond.length > 0 && !txtField) || (txtField && tCond.length === 0)) {
							alert('<?php echo esc_js( __( 'If configuring an "Other" option for Trend Analysis exports, you must select both the "Other" condition and the Source Field.', 'stackboost-for-supportcandy' ) ); ?>');
							return;
						}

						if (index === 'new') {
							// Determine new index based on highest existing index or 0
							let maxIndex = -1;
							rulesTable.find('tr.stkb-rule-row').each(function() {
								let idx = parseInt($(this).attr('data-index'));
								if (idx > maxIndex) maxIndex = idx;
							});
							index = maxIndex + 1;

							let tr = $('<tr class="stkb-rule-row" data-index="'+index+'"></tr>');
							tr.append('<td><span class="stkb-display-trigger-field"></span><input type="hidden" class="stkb-rule-trigger-field" value="" /></td>');
							tr.append('<td><span class="stkb-display-trigger-condition"></span><input type="hidden" class="stkb-rule-trigger-condition" value="" /></td>');
							tr.append('<td><span class="stkb-display-text-field"></span><input type="hidden" class="stkb-rule-text-field" value="" /></td>');
							tr.append('<td style="text-align:center;">' +
								'<button type="button" class="stkb-edit-rule" style="color:#d68a00; background:none; border:none; box-shadow:none; cursor:pointer;" title="<?php esc_attr_e( 'Edit Rule', 'stackboost-for-supportcandy' ); ?>"><span class="dashicons dashicons-edit"></span></button> ' +
								'<button type="button" class="stkb-remove-rule" style="color:#d63638; background:none; border:none; box-shadow:none; cursor:pointer;" title="<?php esc_attr_e( 'Delete Rule', 'stackboost-for-supportcandy' ); ?>"><span class="dashicons dashicons-trash"></span></button>' +
							'</td>');

							rulesTable.find('.stkb-no-rules-row').remove();
							rulesTable.append(tr);
						}

						let row = rulesTable.find('tr[data-index="' + index + '"]');
						row.find('.stkb-display-trigger-field').text(tFieldText);
						row.find('.stkb-rule-trigger-field').val(tField);
						row.find('.stkb-display-trigger-condition').text(tCondTexts.join(', '));
						row.find('.stkb-rule-trigger-condition').val(JSON.stringify(tCond));
						row.find('.stkb-display-text-field').text(txtFieldText);
						row.find('.stkb-rule-text-field').val(txtField);

						ruleModal.fadeOut(200);
					});

					$(document).on('click', '.stkb-remove-rule', function() {
						$(this).closest('tr').remove();
						if (rulesTable.find('tr.stkb-rule-row').length === 0) {
							rulesTable.append('<tr class="stkb-no-rules-row"><td colspan="4" style="text-align:center;"><?php esc_html_e( 'No rules configured. Click "Add Rule" to get started.', 'stackboost-for-supportcandy' ); ?></td></tr>');
						}
					});

					// Tab handling logic
					$('.nav-tab').on('click', function(e) {
						e.preventDefault();
						$('.nav-tab').removeClass('nav-tab-active');
						$(this).addClass('nav-tab-active');
						var target = $(this).attr('href');
						$('.stackboost-tab-content').hide();
						$('#tab-' + target.substring(1)).show();
					});

					// API Key Lock/Unlock Handler
					$('#stkb_deactivate_api_key').on('click', function() {
						if (confirm('<?php esc_js( esc_html__( 'Are you sure you want to remove the API key?', 'stackboost-for-supportcandy' ) ); ?>')) {
							$('#stkb_gemini_api_key').val('').removeAttr('readonly').css({
								'background': '',
								'border-color': '',
								'cursor': 'text'
							}).attr('placeholder', '<?php esc_attr_e( 'Paste your API key here...', 'stackboost-for-supportcandy' ); ?>');

							$(this).hide();
							$('#stkb_api_key_desc').text('<?php esc_js( esc_html__( 'Enter your Google Gemini API key to enable AI-powered trend analysis. Once saved, the key will be locked to prevent accidental changes.', 'stackboost-for-supportcandy' ) ); ?>');

							// Auto save immediately so the backend drops the key
							$('#tab-settings form').submit();
						}
					});

					// Intercept form submission to post success message
					$('#tab-settings form, #stkb_sla_form').on('submit', function(e) {
						e.preventDefault(); // Prevent standard POST

						var form = $(this);
						var btn = form.find('input[type="submit"]');
						var originalText = btn.val();

						btn.prop('disabled', true).val('<?php esc_html_e( 'Saving...', 'stackboost-for-supportcandy' ); ?>');

						// Ensure all items in the selected box are actually selected before gathering values
						$('#stkb_tracked_agents option').prop('selected', true);

						// Extract rules from table
						var other_issues_rules = [];
						$('#stkb_other_issues_rules_table tbody tr').each(function() {
							let triggerField = $(this).find('.stkb-rule-trigger-field').val();
							let triggerCondition = $(this).find('.stkb-rule-trigger-condition').val();
							let textField = $(this).find('.stkb-rule-text-field').val();

							// Only save if there's at least a trigger field or condition
							if (triggerField || triggerCondition) {
								other_issues_rules.push({
									trigger_field: triggerField,
									trigger_condition: triggerCondition,
									text_field: textField
								});
							}
						});

						// Build payload locally to bypass any serialization issues
						var payload = {
							action: 'stackboost_save_ticket_metrics_settings',
							nonce: stackboost_admin_ajax.nonce,
							ticket_metrics_type_field: $('#stkb_type_field_setting').val(),
							ticket_metrics_enable_agent_group_filter: $('#stkb_enable_agent_group_filter').is(':checked') ? 1 : 0,
							ticket_metrics_chart_type_agent: $('#stkb_chart_type_agent').val(),
							ticket_metrics_chart_type_type: $('#stkb_chart_type_type').val(),
							ticket_metrics_chart_type_secondary: $('#stkb_chart_type_secondary').val(),
							ticket_metrics_show_other_agents: $('#stkb_show_other_agents').is(':checked') ? 1 : 0,
							ticket_metrics_frt_mode: $('#stkb_frt_mode').val(),
							ticket_metrics_verbose_logging: $('#stkb_verbose_logging').is(':checked') ? 1 : 0,
							ticket_metrics_tracked_agents: $('#stkb_tracked_agents').val() || [],
							ticket_metrics_other_issues_rules: other_issues_rules,
							ticket_metrics_gemini_api_key: $('#stkb_gemini_api_key').val(),
							ticket_metrics_sla_frt_hours: $('#stkb_sla_frt_hours').val(),
							ticket_metrics_sla_resolution_hours: $('#stkb_sla_resolution_hours').val(),
							ticket_metrics_survey_max_score: $('#stkb_survey_max_score').val(),
							ticket_metrics_survey_categories: $('#stkb_survey_categories').val() || []
						};

						// Use dedicated endpoint
						$.post(stackboost_admin_ajax.ajax_url, payload, function(response) {
							if (response.success) {
								if (typeof window.stackboost_show_toast !== 'undefined') {
									window.stackboost_show_toast(response.data, 'success');
								} else {
									alert(response.data);
								}
								// Update the UI filter visibility dynamically
								if ($('#stkb_enable_agent_group_filter').is(':checked')) {
									$('#stkb_agent_group_filter_container').show();
								} else {
									$('#stkb_agent_group_filter_container').hide();
									$('#stkb_agent_group_filter').val(''); // Reset selection if disabled
								}
								// Settings saved dynamically via AJAX, no page reload required.
							} else {
								if (typeof window.stackboost_show_toast !== 'undefined') {
									window.stackboost_show_toast(response.data || 'Error saving settings.', 'error');
								} else {
									alert('Error: ' + (response.data || 'Unknown error'));
								}
							}
						}).fail(function() {
							if (typeof window.stackboost_show_toast !== 'undefined') {
								window.stackboost_show_toast('An unexpected error occurred.', 'error');
							} else {
								alert('An unexpected error occurred.');
							}
						}).always(function() {
							btn.prop('disabled', false).val(originalText);
						});
					});

					$('#stkb_date_preset').on('change', function() {
						if ($(this).val() === 'custom') {
							$('#stkb_custom_dates').show();
						} else {
							$('#stkb_custom_dates').hide();
							setDatesFromPreset($(this).val());
						}
					});

					function setDatesFromPreset(preset) {
						let start = new Date();
						let end = new Date();
						let today = new Date();

						if (preset === 'this_week') {
							let day = today.getDay();
							let diff = today.getDate() - day + (day == 0 ? -6:1); // adjust when day is sunday
							start = new Date(today.setDate(diff));
							end = new Date(start);
							end.setDate(start.getDate() + 6);
						} else if (preset === 'last_week') {
							let day = today.getDay();
							let diff = today.getDate() - day + (day == 0 ? -6:1); // adjust when day is sunday
							start = new Date(today.setDate(diff - 7));
							end = new Date(start);
							end.setDate(start.getDate() + 6);
						} else if (preset === 'this_month') {
							start = new Date(today.getFullYear(), today.getMonth(), 1);
							end = new Date(today.getFullYear(), today.getMonth() + 1, 0);
						} else if (preset === 'last_month') {
							start = new Date(today.getFullYear(), today.getMonth() - 1, 1);
							end = new Date(today.getFullYear(), today.getMonth(), 0);
						} else if (preset === 'last_30_days') {
							start = new Date(today.setDate(today.getDate() - 30));
							end = new Date();
						} else if (preset === 'last_60_days') {
							start = new Date(today.setDate(today.getDate() - 60));
							end = new Date();
						} else if (preset === 'last_90_days') {
							start = new Date(today.setDate(today.getDate() - 90));
							end = new Date();
						} else if (preset === 'this_year') {
							start = new Date(today.getFullYear(), 0, 1);
							end = new Date();
						} else if (preset === 'last_year') {
							start = new Date(today.getFullYear() - 1, 0, 1);
							end = new Date(today.getFullYear() - 1, 11, 31);
						}

						// Adjust to local timezone format
						let start_date = start.getFullYear() + "-" + ("0" + (start.getMonth() + 1)).slice(-2) + "-" + ("0" + start.getDate()).slice(-2);
						let end_date = end.getFullYear() + "-" + ("0" + (end.getMonth() + 1)).slice(-2) + "-" + ("0" + end.getDate()).slice(-2);

						$('#stkb_start_date').val(start_date);
						$('#stkb_end_date').val(end_date);
					}


					setDatesFromPreset('this_week');

					$('#stkb_generate_metrics').on('click', function() {
						let btn = $(this);
						btn.prop('disabled', true).text('<?php esc_html_e( 'Updating...', 'stackboost-for-supportcandy' ); ?>');

						let start_date = $('#stkb_start_date').val();
						let end_date = $('#stkb_end_date').val();
						let type_field = $('#stkb_type_field_setting').val();
						let agent_group_val = $('#stkb_agent_group_filter').length ? $('#stkb_agent_group_filter').val() : '';

						$.post(ajaxurl, {
							action: 'stackboost_get_ticket_metrics',
							nonce: stackboost_admin_ajax.nonce,
							start_date: start_date,
							end_date: end_date,
							type_field: type_field,
							agent_group_val: agent_group_val
						}, function(response) {
							btn.prop('disabled', false).text('<?php esc_html_e( 'Update Metrics', 'stackboost-for-supportcandy' ); ?>');

							if (response.success) {
								let data = response.data;
								$('#stkb_metric_total').text(data.total_created);
								$('#stkb_metric_total_closed').text(data.total_closed);
								$('#stkb_metric_avg_open').text(data.avg_open_time);
								$('#stkb_metric_avg_age_open').text(data.avg_age_open);
								$('#stkb_metric_avg_response').text(data.avg_initial_response);
								$('#stkb_metric_resolution_rate').text(data.resolution_rate);
								$('#stkb_metric_active_backlog').text(data.active_backlog);
								$('#stkb_metric_touched_tickets').text(data.touched_tickets);
								$('#stkb_metric_avg_touches').text(data.avg_touches);
								$('#stkb_metric_sla_frt_breach').text(data.sla_frt_breach_rate);
								$('#stkb_metric_sla_resolution_breach').text(data.sla_resolution_breach_rate);

								if (data.is_sla_configured === true || data.is_sla_configured === 1 || data.is_sla_configured === '1') {
									$('#stkb_metric_sla_card').css('display', 'flex');
								} else {
									$('#stkb_metric_sla_card').css('display', 'none');
								}

								$('#stkb_metric_survey_rate').text(data.survey_response_rate);

								let csat_html = data.survey_avg_csat;
								if (data.survey_count !== undefined) {
									csat_html += '<br><span style="font-size:11px; color:#50575e;">' + data.survey_count + ' responses</span>';
								}
								$('#stkb_metric_survey_csat').html(csat_html);

								if (data.is_survey_configured === true || data.is_survey_configured === 1 || data.is_survey_configured === '1') {
									$('#stkb_metric_survey_card').css('display', 'flex');
								} else {
									$('#stkb_metric_survey_card').css('display', 'none');
								}

								// Render Agent Breakdown
								let agentTbody = $('#stkb_agent_breakdown_body');
								agentTbody.empty();

								let agentLabels = [];
								let agentAssignedData = [];
								let agentClosedData = [];

								if (data.agent_breakdown && data.agent_breakdown.length > 0) {
									data.agent_breakdown.forEach(function(item) {
										agentLabels.push($('<div>').text(item.label).html());
										agentAssignedData.push(item.assigned);
										agentClosedData.push(item.closed);

										let label = $('<div>').text(item.label).html();
										let assigned = $('<div>').text(item.assigned).html();
										let closed = $('<div>').text(item.closed).html();

										let $tr = $('<tr class="stkb-clickable-row"></tr>');
										let $tdLabel = $('<td></td>');
										let $tdAssigned = $('<td style="text-align:center;"></td>').text(assigned);
										let $tdClosed = $('<td style="text-align:center;"></td>').text(closed);
										let $tdCsat = $('<td style="text-align:center;"></td>').html(item.csat || 'N/A');

										$tdLabel.append(label);

										if ( item.tooltip ) {
											$tr.attr('data-tippy-content', item.tooltip);
										}

										if ( item.modal_html ) {
											$tr.attr('data-modal-html', item.modal_html);
										}

										$tr.append($tdLabel).append($tdAssigned).append($tdClosed).append($tdCsat);
										agentTbody.append($tr);
									});
								} else {
									agentTbody.append(`<tr><td colspan="3" style="text-align:center;"><?php esc_html_e( 'No agents found.', 'stackboost-for-supportcandy' ); ?></td></tr>`);
								}

								// Render Type Breakdown
								let typeTbody = $('#stkb_type_breakdown_body');
								typeTbody.empty();

								let typeLabels = [];
								let typeData = [];

								// Render Heatmap
								if (data.heatmap_data) {
									let days = ['<?php esc_html_e( 'Sunday', 'stackboost-for-supportcandy' ); ?>', '<?php esc_html_e( 'Monday', 'stackboost-for-supportcandy' ); ?>', '<?php esc_html_e( 'Tuesday', 'stackboost-for-supportcandy' ); ?>', '<?php esc_html_e( 'Wednesday', 'stackboost-for-supportcandy' ); ?>', '<?php esc_html_e( 'Thursday', 'stackboost-for-supportcandy' ); ?>', '<?php esc_html_e( 'Friday', 'stackboost-for-supportcandy' ); ?>', '<?php esc_html_e( 'Saturday', 'stackboost-for-supportcandy' ); ?>'];
									// MySQL DAYOFWEEK is 1=Sun, 2=Mon... We map index 1-7 to array index 0-6.
									let tableHead = $('#stkb_heatmap_table thead tr');
									let tableBody = $('#stkb_heatmap_table tbody');

									tableHead.empty();
									tableBody.empty();

									tableHead.append('<th style="width: 80px;"><?php esc_html_e( 'Day / Hour', 'stackboost-for-supportcandy' ); ?></th>');
									for (let h = 0; h < 24; h++) {
										let label = h === 0 ? '12a' : (h < 12 ? h + 'a' : (h === 12 ? '12p' : (h - 12) + 'p'));
										tableHead.append('<th>' + label + '</th>');
									}

									// Build matrix
									let matrix = {};
									let maxCount = 0;
									for (let d = 1; d <= 7; d++) {
										matrix[d] = {};
										for (let h = 0; h < 24; h++) {
											matrix[d][h] = 0;
										}
									}

									data.heatmap_data.forEach(function(point) {
										matrix[point.dow][point.hod] = point.count;
										if (point.count > maxCount) maxCount = point.count;
									});

									// Note: If no tickets exist, maxCount is 0, so avoid division by zero.
									let maxOpacity = 0.9;
									let minOpacity = 0.05; // Base color for 0

									for (let d = 1; d <= 7; d++) {
										// We want Monday (2) to be first, Sunday (1) to be last usually for standard business weeks, but we'll follow DB 1-7 (Sun-Sat) for simplicity, or we can adjust:
										// DB: 1=Sun, 2=Mon, 3=Tue, 4=Wed, 5=Thu, 6=Fri, 7=Sat
										let rowTr = $('<tr></tr>');
										rowTr.append('<th>' + days[d-1] + '</th>');

										for (let h = 0; h < 24; h++) {
											let val = matrix[d][h];
											let opacity = minOpacity;

											if (maxCount > 0 && val > 0) {
												// Scale opacity based on max value
												opacity = minOpacity + ((val / maxCount) * (maxOpacity - minOpacity));
												// Floor the opacity at something visible if it's > 0
												if (opacity < 0.2) opacity = 0.2;
											}

											let bgColor = 'rgba(34, 113, 177, ' + opacity + ')';
											let textColor = opacity > 0.6 ? '#ffffff' : '#1d2327';

											let cell = $('<td class="stkb-heatmap-cell" style="background-color: ' + bgColor + '; color: ' + textColor + ';">' + (val > 0 ? val : '') + '</td>');

											if (val > 0) {
												cell.attr('title', val + ' tickets on ' + days[d-1] + ' at ' + h + ':00');
											}

											rowTr.append(cell);
										}
										tableBody.append(rowTr);
									}

									// Re-order rows so Monday is first (DB 2) and Sunday is last (DB 1)
									let sunRow = tableBody.find('tr:first').detach();
									tableBody.append(sunRow);
								}

								if (data.type_breakdown && data.type_breakdown.length > 0) {
									data.type_breakdown.forEach(function(item) {
										typeLabels.push($('<div>').text(item.label).html());
										typeData.push(item.value);

										let label = $('<div>').text(item.label).html();
										let value = $('<div>').text(item.value).html();
										let $tr = $('<tr class="stkb-clickable-row"></tr>');
										let $tdLabel = $('<td></td>');
										let $tdValue = $('<td style="text-align:center;"></td>').text(value);

										$tdLabel.append(label);

										if ( item.tooltip ) {
											$tr.attr('data-tippy-content', item.tooltip);
										}

										if ( item.modal_html ) {
											$tr.attr('data-modal-html', item.modal_html);
										}

										$tr.append($tdLabel).append($tdValue);
										typeTbody.append($tr);
									});
								} else {
									typeTbody.append(`<tr><td colspan="2" style="text-align:center;"><?php esc_html_e( 'No tickets found for this type.', 'stackboost-for-supportcandy' ); ?></td></tr>`);
								}

								$('#stkb_metrics_results').show();

								// Render Charts
								if (typeof Chart !== 'undefined') {
									if (agentChart) { agentChart.destroy(); }
									if (typeChart) { typeChart.destroy(); }

									const agentCtx = document.getElementById('stkb_agent_chart').getContext('2d');
									const typeCtx = document.getElementById('stkb_type_chart').getContext('2d');

									// Fetch latest selected types from the DOM directly to reflect any newly saved settings
									let agentChartTypeRaw = $('#stkb_chart_type_agent').val() || '<?php echo esc_js( $chart_type_agent ); ?>';
									let typeChartType = $('#stkb_chart_type_type').val() || '<?php echo esc_js( $chart_type_type ); ?>';

									if (agentChartTypeRaw === 'none') {
										$('#stkb_agent_chart').parent('.stkb-chart-container').hide();
									} else {
										$('#stkb_agent_chart').parent('.stkb-chart-container').show();
										// Determine actual chart.js type and if it's multi-series
										let agentChartType = agentChartTypeRaw;
										let isMultiSeriesPie = false;
										if ( agentChartTypeRaw === 'multi_pie' ) {
											agentChartType = 'pie';
											isMultiSeriesPie = true;
										} else if ( agentChartTypeRaw === 'multi_doughnut' ) {
											agentChartType = 'doughnut';
											isMultiSeriesPie = true;
										}

										// Setup base datasets
										let datasets = [
											{
												label: '<?php esc_html_e( 'Assigned', 'stackboost-for-supportcandy' ); ?>',
												data: agentAssignedData,
												backgroundColor: chartColors,
												borderWidth: 1
											}
										];

										// Add closed dataset for multi-series types
										if ( isMultiSeriesPie || agentChartType === 'bar' || agentChartType === 'line' || agentChartType === 'radar' ) {
											datasets.push({
												label: '<?php esc_html_e( 'Closed', 'stackboost-for-supportcandy' ); ?>',
												data: agentClosedData,
												backgroundColor: chartColors.map(c => c + '99'), // Default: slightly transparent map
												borderWidth: 1
											});
										}

										// Setup agent chart config
										let agentConfig = {
											type: agentChartType,
											data: {
												labels: agentLabels,
												datasets: datasets
											},
											options: {
												responsive: true,
												maintainAspectRatio: false,
												plugins: { legend: { position: 'right' } }
											}
										};

										// If bar chart, adjust legend position and use solid distinct colors for the datasets instead of array
										if (agentChartType === 'bar' || agentChartType === 'line' || agentChartType === 'radar') {
											agentConfig.data.datasets[0].backgroundColor = (agentChartType === 'bar') ? '#2271b1' : '#2271b133';
											agentConfig.data.datasets[0].borderColor = '#2271b1';
											agentConfig.data.datasets[1].backgroundColor = (agentChartType === 'bar') ? '#00a32a' : '#00a32a33';
											agentConfig.data.datasets[1].borderColor = '#00a32a';
											agentConfig.options.plugins.legend.position = 'top';

											// Optional: add fill to line charts
											if (agentChartType === 'line' || agentChartType === 'radar') {
												agentConfig.data.datasets[0].fill = true;
												agentConfig.data.datasets[1].fill = true;
											}
										}

										agentChart = new Chart(agentCtx, agentConfig);
									}

									if (typeChartType === 'none') {
										$('#stkb_type_chart').parent('.stkb-chart-container').hide();
									} else {
										$('#stkb_type_chart').parent('.stkb-chart-container').show();
										let typeConfig = {
											type: typeChartType,
											data: {
												labels: typeLabels,
												datasets: [{
													label: '<?php esc_html_e( 'Tickets', 'stackboost-for-supportcandy' ); ?>',
													data: typeData,
													backgroundColor: chartColors,
													borderWidth: 1
												}]
											},
											options: {
												responsive: true,
												maintainAspectRatio: false,
												plugins: { legend: { position: 'right' } }
											}
										};

										if (typeChartType === 'bar' || typeChartType === 'line' || typeChartType === 'radar') {
											typeConfig.data.datasets[0].backgroundColor = (typeChartType === 'bar') ? '#2271b1' : '#2271b133';
											typeConfig.data.datasets[0].borderColor = '#2271b1';
											typeConfig.options.plugins.legend.position = 'top';

											if (typeChartType === 'line' || typeChartType === 'radar') {
												typeConfig.data.datasets[0].fill = true;
											}
										}

										typeChart = new Chart(typeCtx, typeConfig);
									}
								}

								// Initialize Tippy if available
								// Using placement: 'right' ensuring it opens cleanly on the edge of the row, not randomly.
								if (typeof tippy !== 'undefined') {
									tippy('[data-tippy-content]', {
										allowHTML: true,
										placement: 'right',
										theme: 'light-border',
										maxWidth: 350,
										zIndex: 999999
									});
								}
							} else {
								alert(response.data);
							}
						});
					});

					// Modal Interactions
					$(document).on('click', '.stkb-clickable-row', function(e) {
						if ( $(e.target).closest('a').length ) {
							return; // Do not open modal if an internal link (like CSAT) was clicked
						}

						let html = $(this).attr('data-modal-html');
						if ( html ) {
							$('#stkb-metrics-modal-body').html(html);
							$('#stkb-metrics-modal').hide().css('display', 'flex').hide().fadeIn(200); // Use flex to center the modal content

							// Re-initialize tippy for newly added modal elements
							if (typeof tippy !== 'undefined') {
								setTimeout(function() {
									tippy('#stkb-metrics-modal-body [data-tippy-content]', {
										allowHTML: true,
										placement: 'top',
										theme: 'light-border',
										maxWidth: 350,
										appendTo: document.body,
										zIndex: 999999
									});
								}, 50);
							}

							// Render Secondary Charts in Modal
							if (typeof Chart !== 'undefined') {
								// Destroy any existing secondary charts
								secondaryCharts.forEach(c => c.destroy());
								secondaryCharts = [];

								let secondaryChartType = $('#stkb_chart_type_secondary').val() || 'bar';

								if (secondaryChartType === 'none') {
									$('.stkb-secondary-chart').closest('.stkb-chart-container').hide();
								} else {
									$('.stkb-secondary-chart').each(function() {
										let canvas = $(this)[0];
										let ctx = canvas.getContext('2d');
										let rawConfig = $(this).attr('data-chart-config');
										if (!rawConfig) return;

										let configData = JSON.parse(rawConfig);

										let typeType = secondaryChartType;
										let isMultiPie = false;
										if ( typeType === 'multi_pie' ) { typeType = 'pie'; isMultiPie = true; }
										if ( typeType === 'multi_doughnut' ) { typeType = 'doughnut'; isMultiPie = true; }

										let config = {
											type: typeType,
											data: {
												labels: configData.labels,
												datasets: [{
													label: '<?php esc_html_e( 'Tickets', 'stackboost-for-supportcandy' ); ?>',
													data: configData.data,
													backgroundColor: chartColors,
													borderWidth: 1
												}]
											},
											options: {
												responsive: true,
												maintainAspectRatio: false,
												plugins: { legend: { position: 'right' } }
											}
										};

										if (typeType === 'bar' || typeType === 'line' || typeType === 'radar') {
											config.data.datasets[0].backgroundColor = (typeType === 'bar') ? '#2271b1' : '#2271b133';
											config.data.datasets[0].borderColor = '#2271b1';
											config.options.plugins.legend.position = 'top';

											if (typeType === 'line' || typeType === 'radar') {
												config.data.datasets[0].fill = true;
											}
										}

										let c = new Chart(ctx, config);
										secondaryCharts.push(c);
									});
								}
							}
						}
					});

					$('.stackboost-modal-close-button').on('click', function() {
						$(this).closest('.stackboost-modal').hide();
						// Clear charts to free memory when modal closes
						secondaryCharts.forEach(c => c.destroy());
						secondaryCharts = [];
					});

					$(document).on('click', '.stkb-export-other-issues', function(e) {
						e.preventDefault();
						let btn = $(this);
						let originalContent = btn.html();
						let triggerField = btn.attr('data-trigger');
						let parentField = $('#stkb_type_field_setting').val();
						let parentVal = btn.closest('.stackboost-modal').data('stkb-category-val');

						btn.prop('disabled', true).css('opacity', '0.5');

						let start_date = $('#stkb_start_date').val();
						let end_date = $('#stkb_end_date').val();

						// Redirect to a specific URL that triggers the file download
						let url = ajaxurl + '?action=stackboost_get_other_issues_csv&nonce=' + stackboost_admin_ajax.nonce + '&start_date=' + encodeURIComponent(start_date) + '&end_date=' + encodeURIComponent(end_date) + '&trigger_field=' + encodeURIComponent(triggerField);
						if ( parentField && parentVal ) {
							url += '&parent_field=' + encodeURIComponent(parentField) + '&parent_val=' + encodeURIComponent(parentVal);
						}

						// Create a temporary iframe to trigger the download without leaving the page
						let iframe = document.createElement('iframe');
						iframe.style.display = 'none';
						iframe.src = url;
						document.body.appendChild(iframe);

						// Re-enable button after a short delay
						setTimeout(function() {
							btn.prop('disabled', false).css('opacity', '1');
							setTimeout(function() { document.body.removeChild(iframe); }, 5000);
						}, 2000);
					});

					$(document).on('click', '.stkb-trend-analysis-ai', function(e) {
						e.preventDefault();
						let btn = $(this);
						let triggerField = btn.attr('data-trigger');
						let parentField = $('#stkb_type_field_setting').val();
						let parentVal = btn.closest('.stackboost-modal').data('stkb-category-val');

						let start_date = $('#stkb_start_date').val();
						let end_date = $('#stkb_end_date').val();

						btn.html('<span class="dashicons dashicons-update" style="animation: dashicons-spin 2s infinite linear; vertical-align:middle;"></span>').css('opacity', '0.5');

						$.post(ajaxurl, {
							action: 'stackboost_get_trend_analysis_ai',
							nonce: stackboost_admin_ajax.nonce,
							start_date: start_date,
							end_date: end_date,
							trigger_field: triggerField,
							parent_field: parentField,
							parent_val: parentVal
						}, function(response) {
							btn.html('<span class="dashicons dashicons-lightbulb" style="vertical-align:middle;"></span>').css('opacity', '1');

							if (response.success) {
								let htmlContent = '<div style="padding: 20px;"><h2><?php esc_html_e( 'AI Trend Analysis', 'stackboost-for-supportcandy' ); ?></h2><div style="background: #f0f0f1; padding: 15px; border-radius: 4px;">' + response.data + '</div></div>';

								// Hide the main metrics modal to show the AI one, but this can be handled by creating a new modal or replacing content.
								// We'll replace the content and show it
								$('#stkb-metrics-modal-body').html(htmlContent);
								$('#stkb-metrics-modal').hide().css('display', 'flex').hide().fadeIn(200);
							} else {
								alert(response.data);
							}
						}).fail(function() {
							btn.html('<span class="dashicons dashicons-lightbulb" style="vertical-align:middle;"></span>').css('opacity', '1');
							alert('<?php esc_html_e( 'An error occurred while generating the trend analysis.', 'stackboost-for-supportcandy' ); ?>');
						});
					});

					// Trigger initial load on "This week"
					setTimeout(function() {
						$('#stkb_date_preset').val('this_week').trigger('change');
						$('#stkb_generate_metrics').trigger('click');
					}, 100);
				});
			</script>
		</div>
		<?php
	}
}
