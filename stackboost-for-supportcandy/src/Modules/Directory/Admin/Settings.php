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
	 */
	public static function register_settings() {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			array( __CLASS__, 'sanitize_settings' )
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

		return $sanitized_input;
	}

	/**
	 * Render the settings page.
	 */
	public static function render_settings_page() {
		$options = get_option( self::OPTION_NAME, array() );
		$edit_roles       = $options['edit_roles'] ?? array( 'administrator', 'editor' );
		$management_roles = $options['management_roles'] ?? array( 'administrator' );
		?>
		<div class="wrap">
			<form action="options.php" method="post">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( self::OPTION_GROUP );
				?>
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