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

		$saved_type_field = get_option( 'stackboost_ticket_metrics_type_field', 'category' );

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
		</style>
		<div class="wrap stackboost-dashboard <?php echo esc_attr( $theme_class ); ?>">
			<h1><?php esc_html_e( 'Ticket Metrics', 'stackboost-for-supportcandy' ); ?></h1>

			<div class="stackboost-dashboard-grid">
				<div class="stackboost-card" style="margin-bottom: 20px;">
					<h2><?php esc_html_e( 'Metrics Configuration', 'stackboost-for-supportcandy' ); ?></h2>
					<div style="display:flex; gap: 20px; align-items: flex-end; flex-wrap: wrap;">
						<div>
							<label for="stkb_date_preset" style="display:block; margin-bottom:5px; font-weight:600;"><?php esc_html_e( 'Date Range', 'stackboost-for-supportcandy' ); ?></label>
							<select id="stkb_date_preset">
								<option value="this_week"><?php esc_html_e( 'This Week', 'stackboost-for-supportcandy' ); ?></option>
								<option value="last_week"><?php esc_html_e( 'Last Week', 'stackboost-for-supportcandy' ); ?></option>
								<option value="this_month"><?php esc_html_e( 'This Month', 'stackboost-for-supportcandy' ); ?></option>
								<option value="last_month"><?php esc_html_e( 'Last Month', 'stackboost-for-supportcandy' ); ?></option>
								<option value="custom"><?php esc_html_e( 'Custom', 'stackboost-for-supportcandy' ); ?></option>
							</select>
						</div>
						<div id="stkb_custom_dates" style="display:none;">
							<label style="display:block; margin-bottom:5px; font-weight:600;"><?php esc_html_e( 'Custom Dates', 'stackboost-for-supportcandy' ); ?></label>
							<input type="date" id="stkb_start_date" /> - <input type="date" id="stkb_end_date" />
						</div>
						<div>
							<label for="stkb_type_field" style="display:block; margin-bottom:5px; font-weight:600;"><?php esc_html_e( 'Ticket Type Field (For Breakdown)', 'stackboost-for-supportcandy' ); ?></label>
							<select id="stkb_type_field">
								<?php foreach ( $all_type_fields as $key => $label ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $saved_type_field, $key ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
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
					</div>
				</div>
			</div>

			<!-- Dynamic Modal Container -->
			<div id="stkb-metrics-modal" class="stackboost-modal" style="display:none;">
				<div class="stackboost-modal-content" style="max-width: 600px;">
					<span class="stackboost-modal-close-button">&times;</span>
					<div id="stkb-metrics-modal-body" class="stackboost-modal-body"></div>
				</div>
			</div>

			<script>
				jQuery(document).ready(function($) {
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
						let type_field = $('#stkb_type_field').val();

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
								if (data.agent_breakdown && data.agent_breakdown.length > 0) {
									data.agent_breakdown.forEach(function(item) {
										let label = $('<div>').text(item.label).html();
										let assigned = $('<div>').text(item.assigned).html();
										let closed = $('<div>').text(item.closed).html();

										let $tr = $('<tr class="stkb-clickable-row"></tr>');
										let $tdLabel = $('<td></td>');
										let $tdAssigned = $('<td style="text-align:center;"></td>').text(assigned);
										let $tdClosed = $('<td style="text-align:center;"></td>').text(closed);

										$tdLabel.append(label);

										if ( item.modal_html ) {
											$tr.attr('data-modal-html', item.modal_html);
											$tdLabel.attr('title', 'Click to view details');
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
								if (data.type_breakdown && data.type_breakdown.length > 0) {
									data.type_breakdown.forEach(function(item) {
										let label = $('<div>').text(item.label).html();
										let value = $('<div>').text(item.value).html();
										let $tr = $('<tr class="stkb-clickable-row"></tr>');
										let $tdLabel = $('<td></td>');
										let $tdValue = $('<td style="text-align:center;"></td>').text(value);

										$tdLabel.append(label);

										if ( item.modal_html ) {
											$tr.attr('data-modal-html', item.modal_html);
											$tdLabel.attr('title', 'Click to view details');
										}

										$tr.append($tdLabel).append($tdValue);
										typeTbody.append($tr);
									});
								} else {
									typeTbody.append(`<tr><td colspan="2" style="text-align:center;"><?php esc_html_e( 'No tickets found for this type.', 'stackboost-for-supportcandy' ); ?></td></tr>`);
								}

								$('#stkb_metrics_results').show();

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
							$('#stkb-metrics-modal').show();
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
