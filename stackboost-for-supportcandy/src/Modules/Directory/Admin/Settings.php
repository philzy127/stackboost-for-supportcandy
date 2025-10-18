<?php
/**
 * Admin Settings for the Directory module.
 *
 * @package StackBoost
 * @subpackage Modules\Directory\Admin
 */

namespace StackBoost\ForSupportCandy\Modules\Directory\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Settings class.
 */
class Settings {

	/**
	 * The option group for the general settings page.
	 */
	const OPTION_GROUP = 'stackboost_directory_settings';

	/**
	 * The option name for storing general settings.
	 */
	const OPTION_NAME = 'stackboost_directory_settings';

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
			self::OPTION_GROUP,
			self::OPTION_NAME,
			array( __CLASS__, 'sanitize_settings' )
		);

		register_setting(
			self::WIDGET_OPTION_GROUP,
			self::WIDGET_OPTION_NAME,
			array( __CLASS__, 'sanitize_widget_settings' )
		);
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $input The input data.
	 * @return array The sanitized data.
	 */
	public static function sanitize_settings( $input ): array {
		$sanitized_input = array();

		if ( isset( $input['edit_roles'] ) && is_array( $input['edit_roles'] ) ) {
			$sanitized_input['edit_roles'] = array_map( 'sanitize_text_field', $input['edit_roles'] );
		} else {
			$sanitized_input['edit_roles'] = array();
		}

		if ( isset( $input['management_roles'] ) && is_array( $input['management_roles'] ) ) {
			$sanitized_input['management_roles'] = array_map( 'sanitize_text_field', $input['management_roles'] );
		} else {
			$sanitized_input['management_roles'] = array();
		}

		if ( isset( $input['listing_display_mode'] ) && in_array( $input['listing_display_mode'], array( 'page', 'modal' ), true ) ) {
			$sanitized_input['listing_display_mode'] = $input['listing_display_mode'];
		} else {
			$sanitized_input['listing_display_mode'] = 'page';
		}

		return $sanitized_input;
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
			$fields = explode( ',', $input['display_fields'] );
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
			'office_phone'               => __( 'Office Phone', 'stackboost-for-supportcandy' ),
			'extension'                  => __( 'Extension', 'stackboost-for-supportcandy' ),
			'mobile_phone'               => __( 'Mobile Phone', 'stackboost-for-supportcandy' ),
			'email_address'              => __( 'Email Address', 'stackboost-for-supportcandy' ),
			'location'                   => __( 'Location', 'stackboost-for-supportcandy' ),
			'room_number'                => __( 'Room #', 'stackboost-for-supportcandy' ),
			'department_program'         => __( 'Department / Program', 'stackboost-for-supportcandy' ),
		];
	}

	/**
	 * Render the dual list field selector.
	 *
	 * @param array $widget_options The widget options.
	 */
	private static function render_dual_list_field_selector( array $widget_options ) {
		$all_fields      = self::get_directory_fields();
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
					foreach ( $displayed_fields as $key ) {
						if ( isset( $all_fields[ $key ] ) ) {
							echo '<li data-key="' . esc_attr( $key ) . '">' . esc_html( $all_fields[ $key ] ) . '</li>';
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
	public static function render_settings_page() {
		$options = get_option( self::OPTION_NAME, array() );
		$edit_roles           = $options['edit_roles'] ?? array( 'administrator', 'editor' );
		$management_roles     = $options['management_roles'] ?? array( 'administrator' );
		$listing_display_mode = $options['listing_display_mode'] ?? 'page';
		$active_sub_tab       = isset( $_GET['sub-tab'] ) ? sanitize_key( $_GET['sub-tab'] ) : 'general';
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Company Directory Settings', 'stackboost-for-supportcandy' ); ?></h1>
			<h2 class="nav-tab-wrapper">
				<a href="?page=stackboost-directory&tab=settings&sub-tab=general" data-sub-tab="general" class="nav-tab <?php echo 'general' === $active_sub_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'General', 'stackboost-for-supportcandy' ); ?></a>
				<a href="?page=stackboost-directory&tab=settings&sub-tab=ticket_widget" data-sub-tab="ticket_widget" class="nav-tab <?php echo 'ticket_widget' === $active_sub_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Ticket Widget', 'stackboost-for-supportcandy' ); ?></a>
			</h2>
			<form action="options.php" method="post">
				<?php
				if ( 'ticket_widget' === $active_sub_tab ) {
					settings_fields( self::WIDGET_OPTION_GROUP );
				} else {
					settings_fields( self::OPTION_GROUP );
				}
				?>
				<div id="tab-general" class="tab-content" style="<?php echo 'general' === $active_sub_tab ? '' : 'display:none;'; ?>">
					<h2><?php esc_html_e( 'Display Settings', 'stackboost-for-supportcandy' ); ?></h2>
					<table class="form-table">
						<tr valign="top">
							<th scope="row">
								<label for="stackboost-listing-display-mode"><?php esc_html_e( 'Listing Display Mode', 'stackboost-for-supportcandy' ); ?></label>
							</th>
							<td>
								<select id="stackboost-listing-display-mode" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[listing_display_mode]">
									<option value="page" <?php selected( $listing_display_mode, 'page' ); ?>><?php esc_html_e( 'Page View', 'stackboost-for-supportcandy' ); ?></option>
									<option value="modal" <?php selected( $listing_display_mode, 'modal' ); ?>><?php esc_html_e( 'Modal View', 'stackboost-for-supportcandy' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'Choose how to display individual staff listings on the front-end directory.', 'stackboost-for-supportcandy' ); ?></p>
							</td>
						</tr>
					</table>
					<hr>
					<h2><?php esc_html_e( 'Permission Settings', 'stackboost-for-supportcandy' ); ?></h2>
					<table class="form-table">
						<tr valign="top">
							<th scope="row"><?php esc_html_e( 'Editing Permissions', 'stackboost-for-supportcandy' ); ?></th>
							<td>
								<p><?php esc_html_e( 'Select the roles that can create and edit directory listings.', 'stackboost-for-supportcandy' ); ?></p>
								<?php self::render_role_checkboxes( 'edit_roles', $edit_roles ); ?>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php esc_html_e( 'Management Tab Access', 'stackboost-for-supportcandy' ); ?></th>
							<td>
								<p><?php esc_html_e( 'Select the roles that can access the Management tab (Import, Clear, Fresh Start).', 'stackboost-for-supportcandy' ); ?></p>
								<?php self::render_role_checkboxes( 'management_roles', $management_roles ); ?>
							</td>
						</tr>
					</table>
				</div>

				<div id="tab-ticket_widget" class="tab-content" style="<?php echo 'ticket_widget' === $active_sub_tab ? '' : 'display:none;'; ?>">
					<?php
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
				</div>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render role checkboxes.
	 *
	 * @param string $setting_name The name of the setting.
	 * @param array  $selected_roles The currently selected roles.
	 */
	private static function render_role_checkboxes( string $setting_name, array $selected_roles ) {
		$roles = get_editable_roles();
		foreach ( $roles as $role_name => $role_info ) {
			?>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[<?php echo esc_attr( $setting_name ); ?>][]" value="<?php echo esc_attr( $role_name ); ?>" <?php checked( in_array( $role_name, $selected_roles, true ) ); ?>>
				<?php echo esc_html( $role_info['name'] ); ?>
			</label><br>
			<?php
		}
	}
}