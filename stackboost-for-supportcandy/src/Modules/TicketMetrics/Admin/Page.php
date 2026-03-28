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

		$all_type_fields = array_merge( $default_fields, $custom_fields );
		asort( $all_type_fields );

		$options = get_option( 'stackboost_settings', [] );
		$saved_type_field = $options['ticket_metrics_type_field'] ?? 'category';
		$chart_type_agent = $options['ticket_metrics_chart_type_agent'] ?? 'multi_pie';
		$chart_type_type = $options['ticket_metrics_chart_type_type'] ?? 'doughnut';
		$show_other_agents = isset( $options['ticket_metrics_show_other_agents'] ) ? (bool) $options['ticket_metrics_show_other_agents'] : true;
		$frt_mode = $options['ticket_metrics_frt_mode'] ?? 'stackboost';
		$verbose_logging = isset( $options['ticket_metrics_verbose_logging'] ) ? (bool) $options['ticket_metrics_verbose_logging'] : false;

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

		?>
		<div class="wrap stackboost-dashboard <?php echo esc_attr( $theme_class ); ?>">
			<h1><?php esc_html_e( 'Ticket Metrics', 'stackboost-for-supportcandy' ); ?></h1>

			<h2 class="nav-tab-wrapper stackboost-nav-tabs">
				<a href="#dashboard" class="nav-tab nav-tab-active"><?php esc_html_e( 'Dashboard', 'stackboost-for-supportcandy' ); ?></a>
				<a href="#settings" class="nav-tab"><?php esc_html_e( 'Settings', 'stackboost-for-supportcandy' ); ?></a>
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
							<div class="stkb-metric-card">
								<h3><?php esc_html_e( 'Touched Tickets', 'stackboost-for-supportcandy' ); ?></h3>
								<p id="stkb_metric_touched_tickets">0</p>
							</div>
							<div class="stkb-metric-card">
								<h3><?php esc_html_e( 'Active Backlog', 'stackboost-for-supportcandy' ); ?></h3>
								<p id="stkb_metric_active_backlog">0</p>
							</div>
							<div class="stkb-metric-card" style="padding: 10px;">
								<table style="width: 100%; border-collapse: collapse;">
									<tr>
										<td style="width: 33.33%; text-align: center; border-right: 1px solid var(--sb-card-border, #ccd0d4); padding-top: 10px;">
											<h3><?php esc_html_e( 'Tickets Created', 'stackboost-for-supportcandy' ); ?></h3>
											<p id="stkb_metric_total">0</p>
										</td>
										<td style="width: 33.33%; text-align: center; border-right: 1px solid var(--sb-card-border, #ccd0d4); padding-top: 10px;">
											<h3><?php esc_html_e( 'Tickets Closed', 'stackboost-for-supportcandy' ); ?></h3>
											<p id="stkb_metric_total_closed">0</p>
										</td>
										<td style="width: 33.33%; text-align: center; padding-top: 10px;">
											<h3><?php esc_html_e( 'Resolution Rate', 'stackboost-for-supportcandy' ); ?></h3>
											<p id="stkb_metric_resolution_rate">0%</p>
										</td>
									</tr>
								</table>
							</div>
						</div>

						<!-- Column 2: Averages -->
						<div class="stkb-metric-col">
							<div class="stkb-metric-card">
								<h3><?php esc_html_e( 'Average Time to Close (Closed Tickets)', 'stackboost-for-supportcandy' ); ?></h3>
								<p id="stkb_metric_avg_open">0</p>
							</div>
							<div class="stkb-metric-card">
								<h3><?php esc_html_e( 'Average Age (Open Tickets)', 'stackboost-for-supportcandy' ); ?></h3>
								<p id="stkb_metric_avg_age_open">0</p>
							</div>
							<div class="stkb-metric-card">
								<h3><?php esc_html_e( 'Average Initial Response Time', 'stackboost-for-supportcandy' ); ?></h3>
								<p id="stkb_metric_avg_response">0</p>
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
									<th scope="row"><label for="stkb_chart_type_agent"><?php esc_html_e( 'Agent Chart Type', 'stackboost-for-supportcandy' ); ?></label></th>
									<td>
										<select name="stackboost_settings[ticket_metrics_chart_type_agent]" id="stkb_chart_type_agent">
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
											<option value="pie" <?php selected( $chart_type_type, 'pie' ); ?>><?php esc_html_e( 'Pie', 'stackboost-for-supportcandy' ); ?></option>
											<option value="doughnut" <?php selected( $chart_type_type, 'doughnut' ); ?>><?php esc_html_e( 'Doughnut', 'stackboost-for-supportcandy' ); ?></option>
											<option value="bar" <?php selected( $chart_type_type, 'bar' ); ?>><?php esc_html_e( 'Bar', 'stackboost-for-supportcandy' ); ?></option>
											<option value="line" <?php selected( $chart_type_type, 'line' ); ?>><?php esc_html_e( 'Line', 'stackboost-for-supportcandy' ); ?></option>
											<option value="radar" <?php selected( $chart_type_type, 'radar' ); ?>><?php esc_html_e( 'Radar', 'stackboost-for-supportcandy' ); ?></option>
											<option value="polarArea" <?php selected( $chart_type_type, 'polarArea' ); ?>><?php esc_html_e( 'Polar Area', 'stackboost-for-supportcandy' ); ?></option>
										</select>
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
							<p class="submit">
								<?php submit_button( __( 'Save Settings', 'stackboost-for-supportcandy' ), 'primary', 'submit', false ); ?>
							</p>
						</div>
					</div>
				</form>
			</div>

			<!-- Dynamic Modal Container -->
			<div id="stkb-metrics-modal" class="stackboost-modal" style="display:none; align-items:center; justify-content:center;">
				<div class="stackboost-modal-content" style="max-width: 800px; width:100%; max-height: 80vh; display:flex; flex-direction:column;">
					<span class="stackboost-modal-close-button" style="align-self: flex-end;">&times;</span>
					<div id="stkb-metrics-modal-body" class="stackboost-modal-body" style="overflow-y:auto; flex-grow:1; padding-right:15px;"></div>
				</div>
			</div>

		</div>
		<?php
	}
}
