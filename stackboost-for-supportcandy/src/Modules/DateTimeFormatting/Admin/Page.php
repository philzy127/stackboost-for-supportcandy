<?php

namespace StackBoost\ForSupportCandy\Modules\DateTimeFormatting\Admin;

use StackBoost\ForSupportCandy\WordPress\Plugin;

/**
 * Handles the Date & Time Formatting settings page.
 *
 * @package StackBoost\ForSupportCandy\Modules\DateTimeFormatting\Admin
 */
class Page {

	/**
	 * Render the Date & Time Formatting settings page content.
	 */
	public static function render_page() {
		$theme_class = 'sb-theme-clean-tech';
		if ( class_exists( '\StackBoost\ForSupportCandy\Modules\Appearance\WordPress' ) ) {
			$theme_class = \StackBoost\ForSupportCandy\Modules\Appearance\WordPress::get_active_theme_class();
		}
		?>
		<!-- StackBoost Wrapper Start -->
		<!-- Theme: <?php echo esc_html( $theme_class ); ?> -->
		<div class="wrap stackboost-dashboard <?php echo esc_attr( $theme_class ); ?>">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php settings_errors(); ?>

			<form action="options.php" method="post">
				<?php
				settings_fields( 'stackboost_settings' );
				echo '<input type="hidden" name="stackboost_settings[page_slug]" value="stackboost-date-time">';
				?>

				<div class="stackboost-dashboard-grid">
					<!-- Single Card: Date & Time Formatting -->
					<div class="stackboost-card">
						<h2><?php esc_html_e( 'Date & Time Formatting', 'stackboost-for-supportcandy' ); ?></h2>
						<p><?php esc_html_e( 'Create rules to customize the date and time format for specific columns in the ticket list.', 'stackboost-for-supportcandy' ); ?></p>

						<table class="form-table" role="presentation">
							<tbody>
								<tr>
									<th scope="row"><?php esc_html_e( 'Enable Feature', 'stackboost-for-supportcandy' ); ?></th>
									<td>
										<?php self::render_enable_checkbox(); ?>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php esc_html_e( 'Formatting Rules', 'stackboost-for-supportcandy' ); ?></th>
									<td>
										<?php self::render_rules_builder(); ?>
									</td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>

				<?php submit_button( __( 'Save Settings', 'stackboost-for-supportcandy' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the Rules Builder section.
	 * (Deprecated in favor of inline rendering within render_page, keeping empty for safety if called elsewhere)
	 */
	public static function render_date_time_formatting_rules_section() {
		// No-op
	}

	/**
	 * Render the enable checkbox.
	 */
	private static function render_enable_checkbox() {
		$options = get_option( 'stackboost_settings', [] );
		$is_enabled = ! empty( $options['enable_date_time_formatting'] );
		?>
		<label>
			<input type="checkbox" name="stackboost_settings[enable_date_time_formatting]" value="1" <?php checked( $is_enabled ); ?>>
			<?php esc_html_e( 'Enable custom date and time formatting for the ticket list.', 'stackboost-for-supportcandy' ); ?>
		</label>
		<?php
	}

	/**
	 * Render the rule builder UI.
	 */
	private static function render_rules_builder() {
		$options = get_option( 'stackboost_settings', [] );
		$rules   = isset( $options['date_format_rules'] ) && is_array( $options['date_format_rules'] ) ? $options['date_format_rules'] : [];
		$columns = self::get_date_columns();
		?>
		<input type="hidden" name="stackboost_settings[date_format_rules]" value="">
		<div id="stackboost-date-rules-container">
			<?php
			if ( ! empty( $rules ) ) {
				foreach ( $rules as $index => $rule ) {
					self::render_rule_template( $index, $rule, $columns );
				}
			} else {
				echo '<p id="stackboost-no-date-rules-message">' . esc_html__( 'No rules defined yet. Click "Add New Rule" to start.', 'stackboost-for-supportcandy' ) . '</p>';
			}
			?>
		</div>
		<button type="button" class="button stackboost-add-rule-button" id="stackboost-add-date-rule"><?php esc_html_e( 'Add New Rule', 'stackboost-for-supportcandy' ); ?></button>

		<div class="stackboost-date-rule-template-wrapper" style="display: none;">
			<script type="text/template" id="stackboost-date-rule-template">
				<?php self::render_rule_template( '__INDEX__', [], $columns ); ?>
			</script>
		</div>
		<?php
	}

	/**
	 * Renders the HTML for a single date format rule row.
	 *
	 * @param string|int $index   The index of the rule.
	 * @param array      $rule    The rule data.
	 * @param array      $columns The available columns.
	 */
	private static function render_rule_template( $index, $rule, $columns ) {
		$column           = $rule['column'] ?? '';
		$format_type      = $rule['format_type'] ?? 'default';
		$custom_format    = $rule['custom_format'] ?? '';
		$use_long_date    = ! empty( $rule['use_long_date'] );
		$show_day_of_week = ! empty( $rule['show_day_of_week'] );
		?>
		<div class="stackboost-date-rule-wrapper">
			<div>
				<div class="stackboost-date-rule-row stackboost-date-rule-row-top">
					<span class="stackboost-rule-label"><?php esc_html_e( 'Display', 'stackboost-for-supportcandy' ); ?></span>
					<select name="stackboost_settings[date_format_rules][<?php echo esc_attr( $index ); ?>][column]">
						<option value=""><?php esc_html_e( '-- Select a Column --', 'stackboost-for-supportcandy' ); ?></option>
						<?php foreach ( $columns as $slug => $name ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $column, $slug ); ?>><?php echo esc_html( $name ); ?></option>
						<?php endforeach; ?>
					</select>
					<span class="stackboost-rule-label"><?php esc_html_e( 'as', 'stackboost-for-supportcandy' ); ?></span>
					<select class="stackboost-date-format-type" name="stackboost_settings[date_format_rules][<?php echo esc_attr( $index ); ?>][format_type]">
						<option value="default" <?php selected( $format_type, 'default' ); ?>><?php esc_html_e( 'Hours/Days Ago', 'stackboost-for-supportcandy' ); ?></option>
						<option value="date_only" <?php selected( $format_type, 'date_only' ); ?>><?php esc_html_e( 'Date Only', 'stackboost-for-supportcandy' ); ?></option>
						<option value="time_only" <?php selected( $format_type, 'time_only' ); ?>><?php esc_html_e( 'Time Only', 'stackboost-for-supportcandy' ); ?></option>
						<option value="date_and_time" <?php selected( $format_type, 'date_and_time' ); ?>><?php esc_html_e( 'Date and Time', 'stackboost-for-supportcandy' ); ?></option>
						<option value="custom" <?php selected( $format_type, 'custom' ); ?>><?php esc_html_e( 'Custom', 'stackboost-for-supportcandy' ); ?></option>
					</select>
					<span class="stackboost-custom-format-wrapper" style="<?php echo 'custom' === $format_type ? '' : 'display: none;'; ?>">
						<span class="stackboost-rule-label"><?php esc_html_e( 'Custom Format:', 'stackboost-for-supportcandy' ); ?></span>
						<input
							type="text"
							class="stackboost-date-custom-format"
							name="stackboost_settings[date_format_rules][<?php echo esc_attr( $index ); ?>][custom_format]"
							value="<?php echo esc_attr( $custom_format ); ?>"
							placeholder="<?php esc_attr_e( 'e.g., Y-m-d H:i', 'stackboost-for-supportcandy' ); ?>"
						/>
					</span>
				</div>

				<div class="stackboost-date-rule-row stackboost-date-rule-row-bottom" style="<?php echo in_array( $format_type, [ 'date_only', 'date_and_time' ], true ) ? '' : 'display: none;'; ?>">
					<div class="stackboost-date-options">
						<label>
							<input type="hidden" name="stackboost_settings[date_format_rules][<?php echo esc_attr( $index ); ?>][use_long_date]" value="0">
							<input type="checkbox" name="stackboost_settings[date_format_rules][<?php echo esc_attr( $index ); ?>][use_long_date]" value="1" <?php checked( $use_long_date ); ?>>
							<?php esc_html_e( 'Use Long Date Format', 'stackboost-for-supportcandy' ); ?>
						</label>
						<span class="stackboost-date-day-toggle stackboost-checkbox-indent">
							<label>
								<input type="hidden" name="stackboost_settings[date_format_rules][<?php echo esc_attr( $index ); ?>][show_day_of_week]" value="0">
								<input type="checkbox" name="stackboost_settings[date_format_rules][<?php echo esc_attr( $index ); ?>][show_day_of_week]" value="1" <?php checked( $show_day_of_week ); ?>>
								<?php esc_html_e( 'Show Day of the Week', 'stackboost-for-supportcandy' ); ?>
							</label>
						</span>
					</div>
				</div>
			</div>
			<button type="button" class="button stackboost-remove-date-rule">&times;</button>
		</div>
		<?php
	}

	/**
	 * Get all date-based columns for the settings page.
	 */
	private static function get_date_columns() {
		$plugin = Plugin::get_instance();
		$columns = [];

		// Standard SupportCandy date fields.
		$standard_fields = [
			'date_created' => __( 'Date Created', 'stackboost-for-supportcandy' ),
			'last_reply_on'   => __( 'Last Reply', 'stackboost-for-supportcandy' ),
			'date_closed'  => __( 'Date Closed', 'stackboost-for-supportcandy' ),
			'date_updated' => __( 'Date Updated', 'stackboost-for-supportcandy' ),
		];

		// Get custom fields using the plugin's centralized method, but filtering for datetime.
		// Note: The Plugin class method returns all columns. We need to check types if possible.
		// Since get_supportcandy_columns doesn't return type, we might need a direct query here
		// similar to the reference implementation to ensure we only get datetime fields.
		// However, adhering to the project's "reuse" preference, we can check if we can get types.
		// The Plugin class doesn't seem to expose types.
		// So I will implement the specific query here as per the reference implementation
		// to ensure correctness (filtering only datetime fields).

		global $wpdb;
		$custom_fields_table = $wpdb->prefix . 'psmsc_custom_fields';
		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $custom_fields_table ) ) ) {
			$custom_fields = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT slug, name FROM `{$custom_fields_table}` WHERE type = %s",
					'datetime'
				),
				ARRAY_A
			);
			if ( $custom_fields ) {
				foreach ( $custom_fields as $field ) {
					$columns[ $field['slug'] ] = $field['name'];
				}
			}
		}

		// Merge and sort.
		$all_columns = array_merge( $standard_fields, $columns );
		asort( $all_columns );

		return $all_columns;
	}
}
