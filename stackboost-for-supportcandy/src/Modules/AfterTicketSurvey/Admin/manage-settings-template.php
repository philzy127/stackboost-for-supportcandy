<?php

if ( ! defined( 'ABSPATH' ) ) exit;

$options = get_option( 'stackboost_settings', [] );

$landing_action = isset( $options['ats_landing_action'] ) ? $options['ats_landing_action'] : 'custom_message';
$internal_page  = isset( $options['ats_landing_internal_page'] ) ? $options['ats_landing_internal_page'] : '';
$external_url   = isset( $options['ats_landing_external_url'] ) ? $options['ats_landing_external_url'] : '';
$custom_message = isset( $options['ats_landing_custom_message'] ) ? $options['ats_landing_custom_message'] : 'Thank you for completing our survey! Your feedback is invaluable and helps us improve our services.';

?>
<h2><?php esc_html_e( 'Survey Settings', 'stackboost-for-supportcandy' ); ?></h2>

<form method="post" action="options.php">
	<?php
	settings_fields( 'stackboost_settings' );
	echo '<input type="hidden" name="stackboost_settings[page_slug]" value="stackboost-ats-settings">';
	?>

	<table class="form-table">
		<tr>
			<th scope="row"><?php esc_html_e( 'Action After Submit', 'stackboost-for-supportcandy' ); ?></th>
			<td>
				<fieldset>
					<label style="display:block; margin-bottom: 5px;">
						<input type="radio" name="stackboost_settings[ats_landing_action]" value="custom_message" <?php checked( $landing_action, 'custom_message' ); ?>>
						<?php esc_html_e( 'Show Custom Message', 'stackboost-for-supportcandy' ); ?>
					</label>
					<label style="display:block; margin-bottom: 5px;">
						<input type="radio" name="stackboost_settings[ats_landing_action]" value="internal_page" <?php checked( $landing_action, 'internal_page' ); ?>>
						<?php esc_html_e( 'Redirect to Internal Page', 'stackboost-for-supportcandy' ); ?>
					</label>
					<label style="display:block;">
						<input type="radio" name="stackboost_settings[ats_landing_action]" value="external_url" <?php checked( $landing_action, 'external_url' ); ?>>
						<?php esc_html_e( 'Redirect to External Site', 'stackboost-for-supportcandy' ); ?>
					</label>
				</fieldset>
				<p class="description"><?php esc_html_e( 'Choose what happens immediately after a user submits the survey.', 'stackboost-for-supportcandy' ); ?></p>
			</td>
		</tr>

		<tr id="ats_landing_custom_message_row" style="<?php echo ( 'custom_message' === $landing_action ) ? '' : 'display:none;'; ?>">
			<th scope="row"><label for="ats_landing_custom_message"><?php esc_html_e( 'Custom Message', 'stackboost-for-supportcandy' ); ?></label></th>
			<td>
				<?php
				wp_editor(
					$custom_message,
					'ats_landing_custom_message',
					[
						'textarea_name' => 'stackboost_settings[ats_landing_custom_message]',
						'media_buttons' => true,
						'textarea_rows' => 10,
					]
				);
				?>
				<p class="description"><?php esc_html_e( 'This message will be shown on the same page in place of the survey form.', 'stackboost-for-supportcandy' ); ?></p>
			</td>
		</tr>

		<tr id="ats_landing_internal_page_row" style="<?php echo ( 'internal_page' === $landing_action ) ? '' : 'display:none;'; ?>">
			<th scope="row"><label for="ats_landing_internal_page"><?php esc_html_e( 'Select Internal Page', 'stackboost-for-supportcandy' ); ?></label></th>
			<td>
				<?php
				wp_dropdown_pages( [
					'name'              => 'stackboost_settings[ats_landing_internal_page]',
					'id'                => 'ats_landing_internal_page',
					'show_option_none'  => __( '&mdash; Select &mdash;', 'stackboost-for-supportcandy' ),
					'option_none_value' => '0',
					'selected'          => $internal_page,
				] );
				?>
			</td>
		</tr>

		<tr id="ats_landing_external_url_row" style="<?php echo ( 'external_url' === $landing_action ) ? '' : 'display:none;'; ?>">
			<th scope="row"><label for="ats_landing_external_url"><?php esc_html_e( 'External URL', 'stackboost-for-supportcandy' ); ?></label></th>
			<td>
				<input type="url" name="stackboost_settings[ats_landing_external_url]" id="ats_landing_external_url" value="<?php echo esc_url( $external_url ); ?>" class="regular-text">
				<p class="description"><?php esc_html_e( 'Enter the full URL including https://', 'stackboost-for-supportcandy' ); ?></p>
			</td>
		</tr>
	</table>

	<?php submit_button( __( 'Save Settings', 'stackboost-for-supportcandy' ) ); ?>
</form>

<script>
jQuery(document).ready(function($) {
	$('input[name="stackboost_settings[ats_landing_action]"]').on('change', function() {
		var action = $(this).val();
		if ( 'custom_message' === action ) {
			$('#ats_landing_custom_message_row').show();
			$('#ats_landing_internal_page_row').hide();
			$('#ats_landing_external_url_row').hide();
		} else if ( 'internal_page' === action ) {
			$('#ats_landing_custom_message_row').hide();
			$('#ats_landing_internal_page_row').show();
			$('#ats_landing_external_url_row').hide();
		} else if ( 'external_url' === action ) {
			$('#ats_landing_custom_message_row').hide();
			$('#ats_landing_internal_page_row').hide();
			$('#ats_landing_external_url_row').show();
		}
	});
});
</script>
