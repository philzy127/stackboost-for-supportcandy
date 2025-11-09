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
	 * The option group for the settings page.
	 */
	const OPTION_GROUP = 'stackboost_directory_settings';

	/**
	 * The option name for storing settings.
	 */
	const OPTION_NAME = 'stackboost_directory_settings';

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
	 * Sanitize settings.
	 *
	 * @param array $input The input data.
	 * @return array The sanitized data.
	 */
	public static function sanitize_settings( $input ): array {
		$sanitized_input = array();

		if ( isset( $input['stackboost_edit_roles'] ) && is_array( $input['stackboost_edit_roles'] ) ) {
			$sanitized_input['stackboost_edit_roles'] = array_map( 'sanitize_text_field', $input['stackboost_edit_roles'] );
		} else {
			$sanitized_input['stackboost_edit_roles'] = array();
		}

		if ( isset( $input['stackboost_management_roles'] ) && is_array( $input['stackboost_management_roles'] ) ) {
			$sanitized_input['stackboost_management_roles'] = array_map( 'sanitize_text_field', $input['stackboost_management_roles'] );
		} else {
			$sanitized_input['stackboost_management_roles'] = array();
		}

		if ( isset( $input['stackboost_listing_display_mode'] ) && in_array( $input['stackboost_listing_display_mode'], array( 'page', 'modal' ), true ) ) {
			$sanitized_input['stackboost_listing_display_mode'] = $input['stackboost_listing_display_mode'];
		} else {
			$sanitized_input['stackboost_listing_display_mode'] = 'page';
		}

		$sanitized_input['stackboost_default_photo_id'] = isset( $input['stackboost_default_photo_id'] ) ? absint( $input['stackboost_default_photo_id'] ) : 0;
		$sanitized_input['stackboost_enable_single_pages'] = isset( $input['stackboost_enable_single_pages'] ) ? '1' : '0';
		$sanitized_input['stackboost_show_all_locations_filter'] = isset( $input['stackboost_show_all_locations_filter'] ) ? '1' : '0';
		$sanitized_input['stackboost_show_all_departments_filter'] = isset( $input['stackboost_show_all_departments_filter'] ) ? '1' : '0';

		return $sanitized_input;
	}

	/**
	 * Render the settings page.
	 */
	public static function render_settings_page() {
		$options = get_option( self::OPTION_NAME, array() );
		$edit_roles           = $options['stackboost_edit_roles'] ?? array( 'administrator', 'editor' );
		$management_roles     = $options['stackboost_management_roles'] ?? array( 'administrator' );
		$listing_display_mode = $options['stackboost_listing_display_mode'] ?? 'page';
		$default_photo_id = $options['stackboost_default_photo_id'] ?? 0;
		$enable_single_pages = $options['stackboost_enable_single_pages'] ?? '1';
		$show_all_locations_filter = $options['stackboost_show_all_locations_filter'] ?? '1';
		$show_all_departments_filter = $options['stackboost_show_all_departments_filter'] ?? '1';
		?>
		<div class="wrap">
			<form action="options.php" method="post">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( self::OPTION_GROUP );
				?>
				<h2><?php esc_html_e( 'Display Settings', 'stackboost-for-supportcandy' ); ?></h2>
				<table class="form-table">
					<tr valign="top">
						<th scope="row">
							<label for="stackboost-listing-display-mode"><?php esc_html_e( 'Listing Display Mode', 'stackboost-for-supportcandy' ); ?></label>
						</th>
						<td>
							<select id="stackboost-listing-display-mode" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[stackboost_listing_display_mode]">
								<option value="page" <?php selected( $listing_display_mode, 'page' ); ?>><?php esc_html_e( 'Page View', 'stackboost-for-supportcandy' ); ?></option>
								<option value="modal" <?php selected( $listing_display_mode, 'modal' ); ?>><?php esc_html_e( 'Modal View', 'stackboost-for-supportcandy' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Choose how to display individual staff listings on the front-end directory.', 'stackboost-for-supportcandy' ); ?></p>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<label for="stackboost-default-photo"><?php esc_html_e( 'Default Staff Photo', 'stackboost-for-supportcandy' ); ?></label>
						</th>
						<td>
							<input type="hidden" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[stackboost_default_photo_id]" id="stackboost-default-photo-id" value="<?php echo esc_attr( $default_photo_id ); ?>">
							<button type="button" class="button" id="stackboost-upload-default-photo"><?php esc_html_e( 'Upload Image', 'stackboost-for-supportcandy' ); ?></button>
							<div id="stackboost-default-photo-preview">
								<?php if ( $default_photo_id ) : ?>
									<?php echo wp_get_attachment_image( $default_photo_id, 'thumbnail' ); ?>
								<?php endif; ?>
							</div>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Enable Single Pages', 'stackboost-for-supportcandy' ); ?></th>
						<td>
							<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[stackboost_enable_single_pages]" value="1" <?php checked( $enable_single_pages, '1' ); ?>>
							<p class="description"><?php esc_html_e( 'Enable single pages for staff members.', 'stackboost-for-supportcandy' ); ?></p>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Show "All Locations" Filter', 'stackboost-for-supportcandy' ); ?></th>
						<td>
							<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[stackboost_show_all_locations_filter]" value="1" <?php checked( $show_all_locations_filter, '1' ); ?>>
							<p class="description"><?php esc_html_e( 'Show the "All Locations" filter on the directory shortcode.', 'stackboost-for-supportcandy' ); ?></p>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Show "All Departments" Filter', 'stackboost-for-supportcandy' ); ?></th>
						<td>
							<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[stackboost_show_all_departments_filter]" value="1" <?php checked( $show_all_departments_filter, '1' ); ?>>
							<p class="description"><?php esc_html_e( 'Show the "All Departments" filter on the directory shortcode.', 'stackboost-for-supportcandy' ); ?></p>
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
							<?php self::render_role_checkboxes( 'stackboost_edit_roles', $edit_roles ); ?>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php esc_html_e( 'Management Tab Access', 'stackboost-for-supportcandy' ); ?></th>
						<td>
							<p><?php esc_html_e( 'Select the roles that can access the Management tab (Import, Clear, Fresh Start).', 'stackboost-for-supportcandy' ); ?></p>
							<?php self::render_role_checkboxes( 'stackboost_management_roles', $management_roles ); ?>
						</td>
					</tr>
				</table>
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