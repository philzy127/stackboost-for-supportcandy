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

		?>
		<div class="wrap stackboost-dashboard <?php echo esc_attr( $theme_class ); ?>">
			<h1><?php esc_html_e( 'Ticket Metrics', 'stackboost-for-supportcandy' ); ?></h1>

			<div class="stackboost-dashboard-grid">
				<div class="stackboost-card">
					<h2><?php esc_html_e( 'Metrics Configuration', 'stackboost-for-supportcandy' ); ?></h2>
					<table class="form-table">
						<tr>
							<th><label for="stkb_date_preset"><?php esc_html_e( 'Date Range', 'stackboost-for-supportcandy' ); ?></label></th>
							<td>
								<select id="stkb_date_preset">
									<option value="this_week"><?php esc_html_e( 'This Week', 'stackboost-for-supportcandy' ); ?></option>
									<option value="last_week"><?php esc_html_e( 'Last Week', 'stackboost-for-supportcandy' ); ?></option>
									<option value="this_month"><?php esc_html_e( 'This Month', 'stackboost-for-supportcandy' ); ?></option>
									<option value="last_month"><?php esc_html_e( 'Last Month', 'stackboost-for-supportcandy' ); ?></option>
									<option value="custom"><?php esc_html_e( 'Custom', 'stackboost-for-supportcandy' ); ?></option>
								</select>
							</td>
						</tr>
						<tr id="stkb_custom_dates" style="display:none;">
							<th><label><?php esc_html_e( 'Custom Dates', 'stackboost-for-supportcandy' ); ?></label></th>
							<td>
								<input type="date" id="stkb_start_date" /> - <input type="date" id="stkb_end_date" />
							</td>
						</tr>
						<tr>
							<th><label for="stkb_breakdown"><?php esc_html_e( 'Breakdown By', 'stackboost-for-supportcandy' ); ?></label></th>
							<td>
								<select id="stkb_breakdown">
									<option value="none"><?php esc_html_e( 'None', 'stackboost-for-supportcandy' ); ?></option>
									<option value="agent"><?php esc_html_e( 'Agent', 'stackboost-for-supportcandy' ); ?></option>
									<option value="type"><?php esc_html_e( 'Ticket Type', 'stackboost-for-supportcandy' ); ?></option>
								</select>
							</td>
						</tr>
						<tr id="stkb_type_field_row" style="display:none;">
							<th><label for="stkb_type_field"><?php esc_html_e( 'Ticket Type Field', 'stackboost-for-supportcandy' ); ?></label></th>
							<td>
								<select id="stkb_type_field">
									<?php foreach ( $all_type_fields as $key => $label ) : ?>
										<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th></th>
							<td>
								<button type="button" class="button button-primary" id="stkb_generate_metrics"><?php esc_html_e( 'Generate Metrics', 'stackboost-for-supportcandy' ); ?></button>
							</td>
						</tr>
					</table>
				</div>
			</div>

			<div id="stkb_metrics_results" style="display:none; margin-top:20px;">
				<div class="stackboost-dashboard-grid">
					<div class="stackboost-card" style="text-align:center;">
						<h3><?php esc_html_e( 'Total Tickets Created', 'stackboost-for-supportcandy' ); ?></h3>
						<p style="font-size:2em; font-weight:bold;" id="stkb_metric_total">0</p>
					</div>
					<div class="stackboost-card" style="text-align:center;">
						<h3><?php esc_html_e( 'Total Tickets Closed', 'stackboost-for-supportcandy' ); ?></h3>
						<p style="font-size:2em; font-weight:bold;" id="stkb_metric_total_closed">0</p>
					</div>
					<div class="stackboost-card" style="text-align:center;">
						<h3><?php esc_html_e( 'Average Time to Close (Closed Tickets)', 'stackboost-for-supportcandy' ); ?></h3>
						<p style="font-size:2em; font-weight:bold;" id="stkb_metric_avg_open">0</p>
					</div>
					<div class="stackboost-card" style="text-align:center;">
						<h3><?php esc_html_e( 'Average Age (Open Tickets)', 'stackboost-for-supportcandy' ); ?></h3>
						<p style="font-size:2em; font-weight:bold;" id="stkb_metric_avg_age_open">0</p>
					</div>
					<div class="stackboost-card" style="text-align:center;">
						<h3><?php esc_html_e( 'Average Initial Response Time', 'stackboost-for-supportcandy' ); ?></h3>
						<p style="font-size:2em; font-weight:bold;" id="stkb_metric_avg_response">0</p>
					</div>
				</div>

				<div class="stackboost-card" id="stkb_breakdown_card" style="display:none; margin-top:20px;">
					<h3><?php esc_html_e( 'Breakdown', 'stackboost-for-supportcandy' ); ?></h3>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Name', 'stackboost-for-supportcandy' ); ?></th>
								<th><?php esc_html_e( 'Tickets Created', 'stackboost-for-supportcandy' ); ?></th>
							</tr>
						</thead>
						<tbody id="stkb_breakdown_body">
						</tbody>
					</table>
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

					$('#stkb_breakdown').on('change', function() {
						if ($(this).val() === 'type') {
							$('#stkb_type_field_row').show();
						} else {
							$('#stkb_type_field_row').hide();
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
						let breakdown = $('#stkb_breakdown').val();
						let type_field = $('#stkb_type_field').val();

						$.post(ajaxurl, {
							action: 'stackboost_get_ticket_metrics',
							nonce: stackboost_admin_ajax.nonce,
							start_date: start_date,
							end_date: end_date,
							breakdown: breakdown,
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

								if (data.breakdown_data && data.breakdown_data.length > 0) {
									let tbody = $('#stkb_breakdown_body');
									tbody.empty();
									data.breakdown_data.forEach(function(item) {
										let label = $('<div>').text(item.label).html();
										let value = $('<div>').text(item.value).html();
										tbody.append(`<tr><td>${label}</td><td>${value}</td></tr>`);
									});
									$('#stkb_breakdown_card').show();
								} else {
									$('#stkb_breakdown_card').hide();
								}

								$('#stkb_metrics_results').show();
							} else {
								alert(response.data);
							}
						});
					});
				});
			</script>
		</div>
		<?php
	}
}
