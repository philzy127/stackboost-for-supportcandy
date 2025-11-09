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
	 *
	 * @deprecated No longer used. Settings are now registered centrally.
	 */
	public static function register_settings() {
		// This function is deprecated. The settings are now registered
		// in the main \StackBoost\ForSupportCandy\WordPress\Admin\Settings class
		// to avoid conflicts and centralize settings management.
	}

	/**
	 * Sanitize widget settings.
	 *
	 * @param array $input The input data.
	 * @return array The sanitized data.
	 */
	public static function sanitize_widget_settings( $input ): array {
		$sanitized_input = [];

		$sanitized_input['stackboost_enabled'] = isset( $input['stackboost_enabled'] ) ? '1' : '0';

		if ( isset( $input['stackboost_placement'] ) && in_array( $input['stackboost_placement'], [ 'before', 'after' ], true ) ) {
			$sanitized_input['stackboost_placement'] = $input['stackboost_placement'];
		} else {
			$sanitized_input['stackboost_placement'] = 'before';
		}

		if ( isset( $input['stackboost_target_widget'] ) ) {
			$sanitized_input['stackboost_target_widget'] = sanitize_key( $input['stackboost_target_widget'] );
		} else {
			$sanitized_input['stackboost_target_widget'] = '';
		}

		if ( isset( $input['stackboost_display_fields'] ) && is_string( $input['stackboost_display_fields'] ) ) {
			$fields = array_filter( explode( ',', $input['stackboost_display_fields'] ) );
			$sanitized_input['stackboost_display_fields'] = array_map( 'sanitize_key', $fields );
		} else {
			$sanitized_input['stackboost_display_fields'] = [];
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
			'name'                       => __( 'Name', 'stackboost-for-supportcandy' ),
			'stackboost_job_title'       => __( 'Title', 'stackboost-for-supportcandy' ),
			'stackboost_phone_number'    => __( 'Phone', 'stackboost-for-supportcandy' ),
			'stackboost_email_address'   => __( 'Email Address', 'stackboost-for-supportcandy' ),
			'stackboost_location_id'     => __( 'Location', 'stackboost-for-supportcandy' ),
			'stackboost_room_number'     => __( 'Room #', 'stackboost-for-supportcandy' ),
			'stackboost_department_ids'  => __( 'Department / Program', 'stackboost-for-supportcandy' ),
		];
	}

	/**
	 * Get the CSS selector for a given widget slug.
	 *
	 * @param string $slug The widget slug.
	 * @return string The CSS selector.
	 */
	public static function get_widget_selector_by_slug( string $slug ): string {
		$map = [
			'add-rec'             => '.wpsc-itw-add-rec',
			'agentonly-fields'    => '.wpsc-itw-agentonly-fields',
			'assignee'            => '.wpsc-itw-assignee',
			'bio-info'            => '.wpsc-itw-bio-info',
			'change-status'       => '.wpsc-itw-ticket-status',
			'raised-by'           => '.wpsc-itw-raised-by',
			'ticket-fields'       => '.wpsc-itw-ticket-fields',
			'ticket-info'         => '.wpsc-itw-ticket-info',
			'ticket-tags'         => '.wpsc-itw-ticket-tags',
		];

		return $map[ $slug ] ?? '';
	}

	/**
	 * Render the dual list field selector.
	 *
	 * @param array $widget_options The widget options.
	 */
	private static function render_dual_list_field_selector( array $widget_options ) {
		$all_fields       = self::get_directory_fields();
		$displayed_fields = $widget_options['stackboost_display_fields'] ?? [];
		$available_fields = array_diff_key( $all_fields, array_flip( $displayed_fields ) );
		?>
		<div class="stackboost-dual-list-selector">
			<div class="list-container">
				<h3><?php esc_html_e( 'Available Fields', 'stackboost-for-supportcandy' ); ?></h3>
				<ul id="stackboost-available-fields" class="sortable-list">
					<?php foreach ( $available_fields as $key => $label ) : ?>
						<li data-key="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
			<div class="list-container">
				<h3><?php esc_html_e( 'Displayed Fields', 'stackboost-for-supportcandy' ); ?></h3>
				<ul id="stackboost-displayed-fields" class="sortable-list">
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
		<input type="hidden" id="stackboost-display-fields" name="<?php echo esc_attr( self::WIDGET_OPTION_NAME ); ?>[stackboost_display_fields]" value="<?php echo esc_attr( implode( ',', $displayed_fields ) ); ?>">
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
			$is_enabled     = $widget_options['stackboost_enabled'] ?? '0';
			$placement      = $widget_options['stackboost_placement'] ?? 'before';
			$target_widget  = $widget_options['stackboost_target_widget'] ?? '';
			?>
			<table class="form-table">
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Enable Widget', 'stackboost-for-supportcandy' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( self::WIDGET_OPTION_NAME ); ?>[stackboost_enabled]" value="1" <?php checked( $is_enabled, '1' ); ?>>
							<?php esc_html_e( 'Enable the Company Directory widget on the ticket screen.', 'stackboost-for-supportcandy' ); ?>
						</label>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="stackboost-widget-placement"><?php esc_html_e( 'Placement', 'stackboost-for-supportcandy' ); ?></label></th>
					<td>
						<select id="stackboost-widget-placement" name="<?php echo esc_attr( self::WIDGET_OPTION_NAME ); ?>[stackboost_placement]">
							<option value="before" <?php selected( $placement, 'before' ); ?>><?php esc_html_e( 'Before', 'stackboost-for-supportcandy' ); ?></option>
							<option value="after" <?php selected( $placement, 'after' ); ?>><?php esc_html_e( 'After', 'stackboost-for-supportcandy' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'Choose to display the widget before or after the target widget.', 'stackboost-for-supportcandy' ); ?></p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><label for="stackboost-target-widget"><?php esc_html_e( 'Target Widget', 'stackboost-for-supportcandy' ); ?></label></th>
					<td>
						<select id="stackboost-target-widget" name="<?php echo esc_attr( self::WIDGET_OPTION_NAME ); ?>[stackboost_target_widget]">
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