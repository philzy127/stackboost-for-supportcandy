<?php
/**
 * Admin Settings for the Directory module's Ticket Widget.
 *
 * @package StackBoost
 * @subpackage Modules\Directory\Admin
 */

namespace StackBoost\ForSupportCandy\Modules\Directory\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * TicketWidgetSettings class.
 */
class TicketWidgetSettings {

	/**
	 * The option group for the widget settings.
	 */
	const WIDGET_OPTION_GROUP = 'stackboost_directory_widget_settings';

	/**
	 * The option name for storing widget settings.
	 */
	const WIDGET_OPTION_NAME = 'stackboost_directory_widget_settings';

	/**
	 * Register settings.
	 */
	public static function register_settings() {
		register_setting(
			self::WIDGET_OPTION_GROUP,
			self::WIDGET_OPTION_NAME,
			array( __CLASS__, 'sanitize_widget_settings' )
		);
	}

	/**
	 * Sanitize widget settings.
	 *
	 * @param array $input The input data.
	 * @return array The sanitized data.
	 */
	public static function sanitize_widget_settings( $input ): array {
		$sanitized_input = [];

		$sanitized_input['enabled'] = isset( $input['enabled'] ) ? '1' : '0';

		if ( isset( $input['placement'] ) && in_array( $input['placement'], [ 'before', 'after' ], true ) ) {
			$sanitized_input['placement'] = $input['placement'];
		} else {
			$sanitized_input['placement'] = 'before';
		}

		if ( isset( $input['target_widget'] ) ) {
			$sanitized_input['target_widget'] = sanitize_key( $input['target_widget'] );
		} else {
			$sanitized_input['target_widget'] = '';
		}

		if ( isset( $input['display_fields'] ) && is_string( $input['display_fields'] ) ) {
			$fields = array_filter( explode( ',', $input['display_fields'] ) );
			$sanitized_input['display_fields'] = array_map( 'sanitize_key', $fields );
		} else {
			$sanitized_input['display_fields'] = [];
		}

		return $sanitized_input;
	}

	/**
	 * Get the available fields for the directory widget.
	 *
	 * @return array
	 */
	public static function get_directory_fields(): array {
		return [
			'chp_staff_job_title' => __( 'Title', 'stackboost-for-supportcandy' ),
			'office_phone'        => __( 'Office Phone', 'stackboost-for-supportcandy' ),
			'extension'           => __( 'Extension', 'stackboost-for-supportcandy' ),
			'mobile_phone'        => __( 'Mobile Phone', 'stackboost-for-supportcandy' ),
			'email_address'       => __( 'Email Address', 'stackboost-for-supportcandy' ),
			'location'            => __( 'Location', 'stackboost-for-supportcandy' ),
			'room_number'         => __( 'Room #', 'stackboost-for-supportcandy' ),
			'department_program'  => __( 'Department / Program', 'stackboost-for-supportcandy' ),
		];
	}

	/**
	 * Render the dual list field selector.
	 *
	 * @param array $widget_options The widget options.
	 */
	private static function render_dual_list_field_selector( array $widget_options ) {
		$all_fields       = self::get_directory_fields();
		$displayed_fields = $widget_options['display_fields'] ?? [];
		$available_fields = array_diff_key( $all_fields, array_flip( $displayed_fields ) );
		?>
		<div class="dual-list-selector">
			<div class="list-container">
				<h3><?php esc_html_e( 'Available Fields', 'stackboost-for-supportcandy' ); ?></h3>
				<ul id="available-fields" class="sortable-list">
					<?php foreach ( $available_fields as $key => $label ) : ?>
						<li data-key="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
			<div class="list-container">
				<h3><?php esc_html_e( 'Displayed Fields', 'stackboost-for-supportcandy' ); ?></h3>
				<ul id="displayed-fields" class="sortable-list">
					<?php
					if ( ! empty( $displayed_fields ) ) {
						foreach ( $displayed_fields as $key ) {
							if ( isset( $all_fields[ $key ] ) ) {
								echo '<li data-key="' . esc_attr( $key ) . '">' . esc_html( $all_fields[ $key ] ) . '</li>';
							}
						}
					}
					?>
				</ul>
			</div>
		</div>
		<input type="hidden" id="stackboost-display-fields" name="<?php echo esc_attr( self::WIDGET_OPTION_NAME ); ?>[display_fields]" value="<?php echo esc_attr( implode( ',', $displayed_fields ) ); ?>">
		<?php
	}

	/**
	 * Render the settings page.
	 */
	public static function render_page() {
		?>
		<form action="options.php" method="post">
			<?php
			settings_fields( self::WIDGET_OPTION_GROUP );
			$widget_options = get_option( self::WIDGET_OPTION_NAME, [] );
			$is_enabled     = $widget_options['enabled'] ?? '0';
			$placement      = $widget_options['placement'] ?? 'before';
			$target_widget  = $widget_options['target_widget'] ?? '';
			?>
			<table class="form-table">
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Enable Widget', 'stackboost-for-supportcandy' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( self::WIDGET_OPTION_NAME ); ?>[enabled]" value="1" <?php checked( $is_enabled, '1' ); ?>>
							<?php esc_html_e( 'Enable the Company Directory widget on the ticket screen.', 'stackboost-for-supportcandy' ); ?>
						</label>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="stackboost-widget-placement"><?php esc_html_e( 'Placement', 'stackboost-for-supportcandy' ); ?></label></th>
					<td>
						<select id="stackboost-widget-placement" name="<?php echo esc_attr( self::WIDGET_OPTION_NAME ); ?>[placement]">
							<option value="before" <?php selected( $placement, 'before' ); ?>><?php esc_html_e( 'Before', 'stackboost-for-supportcandy' ); ?></option>
							<option value="after" <?php selected( $placement, 'after' ); ?>><?php esc_html_e( 'After', 'stackboost-for-supportcandy' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'Choose to display the widget before or after the target widget.', 'stackboost-for-supportcandy' ); ?></p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="stackboost-target-widget"><?php esc_html_e( 'Target Widget', 'stackboost-for-supportcandy' ); ?></label></th>
					<td>
						<select id="stackboost-target-widget" name="<?php echo esc_attr( self::WIDGET_OPTION_NAME ); ?>[target_widget]">
							<?php
							$sc_widgets = get_option( 'wpsc-ticket-widget', [] );
							foreach ( $sc_widgets as $slug => $widget_config ) {
								if ( ! empty( $widget_config['is_enable'] ) ) {
									echo '<option value="' . esc_attr( $slug ) . '" ' . selected( $target_widget, $slug, false ) . '>' . esc_html( $widget_config['title'] ) . '</option>';
								}
							}
							?>
						</select>
						<p class="description"><?php esc_html_e( 'Select the SupportCandy widget to position against.', 'stackboost-for-supportcandy' ); ?></p>
					</td>
				</tr>
			</table>
			<hr>
			<h2><?php esc_html_e( 'Widget Display Fields', 'stackboost-for-supportcandy' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Select and order the directory fields to be displayed in the widget.', 'stackboost-for-supportcandy' ); ?></p>
			<?php self::render_dual_list_field_selector( $widget_options ); ?>
			<?php submit_button(); ?>
		</form>
		<?php
	}
}