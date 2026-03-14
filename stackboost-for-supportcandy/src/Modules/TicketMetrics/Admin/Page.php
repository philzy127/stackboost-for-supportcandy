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
		$agent_filter_mode = $options['ticket_metrics_agent_filter_mode'] ?? 'exclude';
		$excluded_agents = $options['ticket_metrics_excluded_agents'] ?? [];
		if ( ! is_array( $excluded_agents ) ) {
			$excluded_agents = [];
		}

		global $wpdb;
		$agents_table = $wpdb->prefix . 'psmsc_agents';
		if ( $wpdb->get_var("SHOW TABLES LIKE '{$agents_table}'") !== $agents_table ) {
			$agents_table = $wpdb->prefix . 'wpsc_agents';
		}

		$all_agents = [];
		$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$agents_table}'") === $agents_table;
		if ( $table_exists ) {
			$agent_results = $wpdb->get_results("SELECT id, name FROM {$agents_table} ORDER BY name ASC");
			if ( is_array($agent_results) ) {
				foreach ( $agent_results as $a ) {
					$all_agents[$a->id] = $a->name;
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
		</style>
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
								<button type="button" class="button button-primary" id="stkb_generate_metrics"><?php esc_html_e( 'Generate Metrics', 'stackboost-for-supportcandy' ); ?></button>
							</div>
						</div>
					</div>
				</div>

				<div id="stkb_metrics_results" style="display:none;">
					<div class="stkb-metrics-row">
						<!-- Column 1: Counts -->
						<div class="stkb-metric-col">
							<div class="stkb-metric-card">
								<h3><?php esc_html_e( 'Tickets Created', 'stackboost-for-supportcandy' ); ?></h3>
								<p id="stkb_metric_total">0</p>
							</div>
							<div class="stkb-metric-card">
								<h3><?php esc_html_e( 'Tickets Closed', 'stackboost-for-supportcandy' ); ?></h3>
								<p id="stkb_metric_total_closed">0</p>
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
										<select id="stkb_type_field_setting">
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
										<select id="stkb_chart_type_agent">
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
										<select id="stkb_chart_type_type">
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
							<h2><?php esc_html_e( 'Agent Filtering', 'stackboost-for-supportcandy' ); ?></h2>
							<table class="form-table">
								<tr>
									<th scope="row"><label for="stkb_agent_filter_mode"><?php esc_html_e( 'Filter Mode', 'stackboost-for-supportcandy' ); ?></label></th>
									<td>
										<select id="stkb_agent_filter_mode">
											<option value="exclude" <?php selected( $agent_filter_mode, 'exclude' ); ?>><?php esc_html_e( 'Exclude Selected', 'stackboost-for-supportcandy' ); ?></option>
											<option value="include" <?php selected( $agent_filter_mode, 'include' ); ?>><?php esc_html_e( 'Include ONLY Selected', 'stackboost-for-supportcandy' ); ?></option>
										</select>
										<p class="description"><?php esc_html_e( 'Choose whether to exclude specific agents (e.g., catch-all accounts) or strict-include only the ones you want to see.', 'stackboost-for-supportcandy' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="stkb_excluded_agents"><?php esc_html_e( 'Select Agents', 'stackboost-for-supportcandy' ); ?></label></th>
									<td>
										<select id="stkb_excluded_agents" multiple="multiple" style="min-width: 300px;" class="stackboost-selectwoo">
											<?php foreach ( $all_agents as $id => $name ) : ?>
												<option value="<?php echo esc_attr( $id ); ?>" <?php echo in_array( $id, $excluded_agents ) ? 'selected="selected"' : ''; ?>><?php echo esc_html( $name ); ?></option>
											<?php endforeach; ?>
										</select>
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
				<div class="stackboost-modal-content" style="max-width: 600px; width:100%; max-height: 80vh; display:flex; flex-direction:column;">
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
						'#f8c43d', '#ed5e60', '#68de7c', '#b32d2e', '#135e96'
					];

					// Tab handling logic
					$('.nav-tab').on('click', function(e) {
						e.preventDefault();
						$('.nav-tab').removeClass('nav-tab-active');
						$(this).addClass('nav-tab-active');
						var target = $(this).attr('href');
						$('.stackboost-tab-content').hide();
						$('#tab-' + target.substring(1)).show();
					});

					// Intercept form submission to post success message
					$('#tab-settings form').on('submit', function(e) {
						e.preventDefault(); // Prevent standard POST

						var form = $(this);
						var btn = form.find('input[type="submit"]');
						var originalText = btn.val();

						btn.prop('disabled', true).val('<?php esc_html_e( 'Saving...', 'stackboost-for-supportcandy' ); ?>');

						// Build payload locally to bypass any serialization issues
						var payload = {
							action: 'stackboost_save_ticket_metrics_settings',
							nonce: stackboost_admin_ajax.nonce,
							ticket_metrics_type_field: $('#stkb_type_field_setting').val(),
							ticket_metrics_chart_type_agent: $('#stkb_chart_type_agent').val(),
							ticket_metrics_chart_type_type: $('#stkb_chart_type_type').val(),
							ticket_metrics_agent_filter_mode: $('#stkb_agent_filter_mode').val(),
							ticket_metrics_excluded_agents: $('#stkb_excluded_agents').val() || []
						};

						// Use dedicated endpoint
						$.post(stackboost_admin_ajax.ajax_url, payload, function(response) {
							if (response.success) {
								if (typeof window.stackboost_show_toast !== 'undefined') {
									window.stackboost_show_toast(response.data, 'success');
								} else {
									alert(response.data);
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
						btn.prop('disabled', true).text('<?php esc_html_e( 'Generating...', 'stackboost-for-supportcandy' ); ?>');

						let start_date = $('#stkb_start_date').val();
						let end_date = $('#stkb_end_date').val();
						let type_field = $('#stkb_type_field_setting').val();

						$.post(ajaxurl, {
							action: 'stackboost_get_ticket_metrics',
							nonce: stackboost_admin_ajax.nonce,
							start_date: start_date,
							end_date: end_date,
							type_field: type_field
						}, function(response) {
							btn.prop('disabled', false).text('<?php esc_html_e( 'Generate Metrics', 'stackboost-for-supportcandy' ); ?>');

							if (response.success) {
								let data = response.data;
								$('#stkb_metric_total').text(data.total_created);
								$('#stkb_metric_total_closed').text(data.total_closed);
								$('#stkb_metric_avg_open').text(data.avg_open_time);
								$('#stkb_metric_avg_age_open').text(data.avg_age_open);
								$('#stkb_metric_avg_response').text(data.avg_initial_response);

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

										$tdLabel.append(label);

										if ( item.tooltip ) {
											$tr.attr('data-tippy-content', item.tooltip);
										}

										if ( item.modal_html ) {
											$tr.attr('data-modal-html', item.modal_html);
										}

										$tr.append($tdLabel).append($tdAssigned).append($tdClosed);
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

									typeChart = new Chart(typeCtx, {
										type: typeChartType,
										data: {
											labels: typeLabels,
											datasets: [{
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
									});
								}

								// Initialize Tippy if available
								// Using placement: 'right' ensuring it opens cleanly on the edge of the row, not randomly.
								if (typeof tippy !== 'undefined') {
									tippy('[data-tippy-content]', {
										allowHTML: true,
										placement: 'right',
										theme: 'light-border',
										maxWidth: 350
									});
								}
							} else {
								alert(response.data);
							}
						});
					});

					// Modal Interactions
					$(document).on('click', '.stkb-clickable-row', function() {
						let html = $(this).attr('data-modal-html');
						if ( html ) {
							$('#stkb-metrics-modal-body').html(html);
							$('#stkb-metrics-modal').css('display', 'flex'); // Use flex to center the modal content
						}
					});

					$('.stackboost-modal-close-button').on('click', function() {
						$(this).closest('.stackboost-modal').hide();
					});
				});
			</script>
		</div>
		<?php
	}
}
