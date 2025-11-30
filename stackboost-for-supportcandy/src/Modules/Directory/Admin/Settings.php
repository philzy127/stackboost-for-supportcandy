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

		if ( isset( $input['revisions_to_keep'] ) && '' !== $input['revisions_to_keep'] ) {
			$sanitized_input['revisions_to_keep'] = intval( $input['revisions_to_keep'] );
		} else {
			$sanitized_input['revisions_to_keep'] = '';
		}

		return $sanitized_input;
	}

	/**
	 * Render the settings page.
	 */
	public static function render_settings_page() {
		$options = get_option( self::OPTION_NAME, array() );
		$edit_roles           = $options['edit_roles'] ?? array( 'administrator', 'editor' );
		$management_roles     = $options['management_roles'] ?? array( 'administrator' );
		$listing_display_mode = $options['listing_display_mode'] ?? 'page';
		$revisions_to_keep    = $options['revisions_to_keep'] ?? '';
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
							<select id="stackboost-listing-display-mode" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[listing_display_mode]">
								<option value="page" <?php selected( $listing_display_mode, 'page' ); ?>><?php esc_html_e( 'Page View', 'stackboost-for-supportcandy' ); ?></option>
								<option value="modal" <?php selected( $listing_display_mode, 'modal' ); ?>><?php esc_html_e( 'Modal View', 'stackboost-for-supportcandy' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Choose how to display individual staff listings on the front-end directory.', 'stackboost-for-supportcandy' ); ?></p>
						</td>
					</tr>
				</table>
				<hr>
				<h2><?php esc_html_e( 'General Settings', 'stackboost-for-supportcandy' ); ?></h2>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><label for="stackboost-revisions-to-keep"><?php esc_html_e( 'Revisions to Keep', 'stackboost-for-supportcandy' ); ?></label></th>
						<td>
							<input type="number" id="stackboost-revisions-to-keep" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[revisions_to_keep]" value="<?php echo esc_attr( $revisions_to_keep ); ?>" class="small-text">
							<p class="description"><?php esc_html_e( 'Set the number of revisions to keep for Staff, Locations, and Departments. Leave empty or set to -1 for unlimited. Set to 0 to disable revisions.', 'stackboost-for-supportcandy' ); ?></p>
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