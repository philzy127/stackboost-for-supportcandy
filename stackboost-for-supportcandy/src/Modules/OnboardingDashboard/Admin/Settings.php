<?php

namespace StackBoost\ForSupportCandy\Modules\OnboardingDashboard\Admin;

class Settings {

	const OPTION_GROUP = 'stackboost_onboarding_general';
	const OPTION_NAME  = 'stackboost_onboarding_config';

	/**
	 * Initialize the Settings page.
	 */
	public static function init() {
		add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
	}

	/**
	 * Register settings.
	 */
	public static function register_settings() {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			[
				'type'              => 'array',
				'sanitize_callback' => [ __CLASS__, 'sanitize_settings' ],
			]
		);

		// Add sections
		add_settings_section(
			'stkb_onboarding_general_section',
			__( 'General Configuration', 'stackboost-for-supportcandy' ),
			null,
			'stackboost-onboarding-general'
		);

		add_settings_section(
			'stkb_onboarding_mapping_section',
			__( 'Field Mapping', 'stackboost-for-supportcandy' ),
			function() {
				echo '<p>' . esc_html__( 'Map the columns in the Onboarding Staff table to your SupportCandy custom fields.', 'stackboost-for-supportcandy' ) . '</p>';
			},
			'stackboost-onboarding-general'
		);

		add_settings_section(
			'stkb_onboarding_mobile_section',
			__( 'Mobile Configuration', 'stackboost-for-supportcandy' ),
			null,
			'stackboost-onboarding-general'
		);
	}

	/**
	 * Sanitize callback.
	 */
	public static function sanitize_settings( $input ) {
		// Flush the cache whenever settings are saved.
		delete_transient( 'stackboost_onboarding_tickets_cache' );

		$output = [];
		if ( is_array( $input ) ) {
			foreach ( $input as $key => $value ) {
				if ( is_array( $value ) ) {
					$output[ sanitize_key( $key ) ] = array_map( 'sanitize_text_field', $value );
				} else {
					$output[ sanitize_key( $key ) ] = sanitize_text_field( $value );
				}
			}
		}
		return $output;
	}

	/**
	 * Get configuration with defaults.
	 */
	public static function get_config() {
		$options = get_option( self::OPTION_NAME, [] );

		$defaults = [
			// General
			'request_type_field' => '',
			'request_type_id'    => '', // e.g., 69
			'inactive_statuses'  => [], // e.g., [4, 14, 15, 17]

			// Mapping
			'field_full_name'        => '',
			'field_position'         => '',
			'field_supervisor'       => '',
			'field_email'            => '',
			'field_phone'            => '',
			'field_shipping_address' => '',
			'field_tracking_number'  => '',
			'field_onboarding_date'  => '',
			'field_cleared'          => '',

			// Mobile
			'field_is_mobile'   => '',
			'mobile_option_id'  => '', // e.g., 227
		];

		return wp_parse_args( $options, $defaults );
	}

	/**
	 * Render the Settings page.
	 */
	public static function render_page() {
		if ( isset( $_GET['settings-updated'] ) ) {
			add_settings_error( 'stackboost_messages', 'stackboost_message', __( 'Settings Saved', 'stackboost-for-supportcandy' ), 'updated' );
		}
		settings_errors( 'stackboost_messages' );

		$sc_fields = [];
		if ( class_exists( '\WPSC_Custom_Field' ) && property_exists( '\WPSC_Custom_Field', 'custom_fields' ) ) {
			foreach ( \WPSC_Custom_Field::$custom_fields as $field ) {
				if ( is_object( $field ) && isset( $field->id ) ) {
					$key = 'cust_' . $field->id;
					$sc_fields[ $key ] = $field->name . ' (ID: ' . $field->id . ')';
				}
			}
		}

		$sc_statuses = [];
		if ( class_exists( '\WPSC_Status' ) ) {
			$statuses = \WPSC_Status::find( [ 'items_per_page' => 0 ] );
			if ( isset( $statuses['results'] ) ) {
				foreach ( $statuses['results'] as $status ) {
					$sc_statuses[ $status->id ] = $status->name;
				}
			}
		}

		$config = self::get_config();

		?>
		<div class="wrap">
			<h2><?php esc_html_e( 'Onboarding Settings', 'stackboost-for-supportcandy' ); ?></h2>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( 'stackboost-onboarding-general' );
				?>

				<!-- General Configuration -->
				<h3><?php esc_html_e( 'General Configuration', 'stackboost-for-supportcandy' ); ?></h3>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="stkb_req_type"><?php esc_html_e( 'Request Type Field', 'stackboost-for-supportcandy' ); ?></label></th>
						<td>
							<select name="<?php echo esc_attr( self::OPTION_NAME ); ?>[request_type_field]" id="stkb_req_type">
								<option value=""><?php esc_html_e( '-- Select Field --', 'stackboost-for-supportcandy' ); ?></option>
								<?php foreach ( $sc_fields as $key => $label ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $config['request_type_field'], $key ); ?>>
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'The SupportCandy custom field that identifies the type of request (e.g. "Request Type").', 'stackboost-for-supportcandy' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="stkb_req_id"><?php esc_html_e( 'Onboarding Option ID', 'stackboost-for-supportcandy' ); ?></label></th>
						<td>
							<input type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[request_type_id]" id="stkb_req_id" value="<?php echo esc_attr( $config['request_type_id'] ); ?>" class="regular-text">
							<p class="description"><?php esc_html_e( 'The ID of the dropdown option that represents an "Onboarding" request. (e.g. 69)', 'stackboost-for-supportcandy' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="stkb_inactive_status"><?php esc_html_e( 'Inactive Statuses', 'stackboost-for-supportcandy' ); ?></label></th>
						<td>
							<select name="<?php echo esc_attr( self::OPTION_NAME ); ?>[inactive_statuses][]" id="stkb_inactive_status" multiple style="height: 150px;">
								<?php foreach ( $sc_statuses as $id => $label ) : ?>
									<option value="<?php echo esc_attr( $id ); ?>" <?php echo in_array( $id, (array) $config['inactive_statuses'] ) ? 'selected' : ''; ?>>
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Select statuses that should be considered "Inactive" (excluded from lists). Hold Ctrl/Cmd to select multiple.', 'stackboost-for-supportcandy' ); ?></p>
						</td>
					</tr>
				</table>

				<!-- Field Mapping -->
				<h3><?php esc_html_e( 'Field Mapping', 'stackboost-for-supportcandy' ); ?></h3>
				<table class="form-table">
					<?php
					$mapping_fields = [
						'field_full_name'        => __( 'Full Name', 'stackboost-for-supportcandy' ),
						'field_position'         => __( 'Position', 'stackboost-for-supportcandy' ),
						'field_supervisor'       => __( 'Supervisor Name', 'stackboost-for-supportcandy' ),
						'field_email'            => __( 'Personal Email', 'stackboost-for-supportcandy' ),
						'field_phone'            => __( 'Personal Phone', 'stackboost-for-supportcandy' ),
						'field_shipping_address' => __( 'Shipping Address', 'stackboost-for-supportcandy' ),
						'field_tracking_number'  => __( 'Tracking Number', 'stackboost-for-supportcandy' ),
						'field_onboarding_date'  => __( 'Onboarding Date', 'stackboost-for-supportcandy' ),
						'field_cleared'          => __( 'Onboarding Cleared?', 'stackboost-for-supportcandy' ),
					];

					foreach ( $mapping_fields as $opt_key => $label ) :
					?>
					<tr>
						<th scope="row"><label for="<?php echo esc_attr( $opt_key ); ?>"><?php echo esc_html( $label ); ?></label></th>
						<td>
							<select name="<?php echo esc_attr( self::OPTION_NAME ); ?>[<?php echo esc_attr( $opt_key ); ?>]" id="<?php echo esc_attr( $opt_key ); ?>">
								<option value=""><?php esc_html_e( '-- Select Field --', 'stackboost-for-supportcandy' ); ?></option>
								<?php foreach ( $sc_fields as $key => $field_label ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $config[ $opt_key ], $key ); ?>>
										<?php echo esc_html( $field_label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<?php endforeach; ?>
				</table>

				<!-- Mobile Configuration -->
				<h3><?php esc_html_e( 'Mobile Configuration', 'stackboost-for-supportcandy' ); ?></h3>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="stkb_mobile_field"><?php esc_html_e( 'Is Mobile Field', 'stackboost-for-supportcandy' ); ?></label></th>
						<td>
							<select name="<?php echo esc_attr( self::OPTION_NAME ); ?>[field_is_mobile]" id="stkb_mobile_field">
								<option value=""><?php esc_html_e( '-- Select Field --', 'stackboost-for-supportcandy' ); ?></option>
								<?php foreach ( $sc_fields as $key => $label ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $config['field_is_mobile'], $key ); ?>>
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'The field (Radio/Checkbox) used to indicate if a mobile device is required.', 'stackboost-for-supportcandy' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="stkb_mobile_opt"><?php esc_html_e( 'Mobile Option ID', 'stackboost-for-supportcandy' ); ?></label></th>
						<td>
							<input type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[mobile_option_id]" id="stkb_mobile_opt" value="<?php echo esc_attr( $config['mobile_option_id'] ); ?>" class="regular-text">
							<p class="description"><?php esc_html_e( 'The Option ID that represents "Yes" or "Mobile" in the above field. (e.g. 227)', 'stackboost-for-supportcandy' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
