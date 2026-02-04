<?php


namespace StackBoost\ForSupportCandy\Modules\OnboardingDashboard\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

class Settings {

	const OPTION_GROUP = 'stackboost_onboarding_general';
	const OPTION_NAME  = 'stackboost_onboarding_config';

	/**
	 * Initialize the Settings page.
	 */
	public static function init() {
		add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
		add_action( 'wp_ajax_stackboost_onboarding_get_field_options', [ __CLASS__, 'ajax_get_field_options' ] );
	}

	/**
	 * AJAX: Get options for a specific field.
	 */
	public static function ajax_get_field_options() {
		check_ajax_referer( 'stackboost_admin_nonce', 'nonce' );

		if ( ! current_user_can( STACKBOOST_CAP_MANAGE_SETTINGS ) ) {
			wp_send_json_error( __( 'Permission denied.', 'stackboost-for-supportcandy' ) );
		}

		$field_slug = isset( $_POST['field_slug'] ) ? sanitize_text_field( wp_unslash( $_POST['field_slug'] ) ) : '';
		if ( empty( $field_slug ) ) {
			wp_send_json_error( __( 'Invalid field slug.', 'stackboost-for-supportcandy' ) );
		}

		if ( ! class_exists( '\WPSC_Custom_Field' ) ) {
			wp_send_json_error( __( 'SupportCandy classes not loaded.', 'stackboost-for-supportcandy' ) );
		}

		$cf = \WPSC_Custom_Field::get_cf_by_slug( $field_slug );
		if ( ! $cf ) {
			wp_send_json_error( __( 'Field not found.', 'stackboost-for-supportcandy' ) );
		}

		// Check if field has options
		if ( ! $cf->type::$has_options ) {
			wp_send_json_error( __( 'This field type does not support options.', 'stackboost-for-supportcandy' ) );
		}

		$options = $cf->get_options();
		$response_data = [];
		foreach ( $options as $option ) {
			// option is likely an object or array with id/name
			$id = is_object( $option ) ? $option->id : ( $option['id'] ?? '' );
			$name = is_object( $option ) ? $option->name : ( $option['name'] ?? '' );
			if ( $id ) {
				$response_data[] = [
					'id' => $id,
					'name' => $name,
				];
			}
		}

		wp_send_json_success( $response_data );
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
			function() {
				echo '<p>' . esc_html__( 'Identify the critical fields required for the onboarding logic.', 'stackboost-for-supportcandy' ) . '</p>';
			},
			'stackboost-onboarding-general'
		);

		add_settings_section(
			'stkb_onboarding_phone_section',
			__( 'Phone Configuration', 'stackboost-for-supportcandy' ),
			null,
			'stackboost-onboarding-general'
		);

		add_settings_section(
			'stkb_onboarding_columns_section',
			__( 'Staff Table Columns', 'stackboost-for-supportcandy' ),
			function() {
				echo '<p>' . esc_html__( 'Select and order the columns to display in the staff table.', 'stackboost-for-supportcandy' ) . '</p>';
			},
			'stackboost-onboarding-general'
		);

		add_settings_section(
			'stkb_onboarding_rename_section',
			__( 'Column Renaming', 'stackboost-for-supportcandy' ),
			null,
			'stackboost-onboarding-general'
		);

		add_settings_section(
			'stkb_onboarding_certificate_section',
			__( 'Certificate Configuration', 'stackboost-for-supportcandy' ),
			null,
			'stackboost-onboarding-certificate'
		);
	}

	/**
	 * Sanitize callback.
	 */
	public static function sanitize_settings( $input ) {
		// Flush the cache whenever settings are saved.
		delete_transient( 'stackboost_onboarding_tickets_cache' );

		// Merge with existing options to prevent overwriting missing keys (e.g., from different tabs)
		$existing_options = get_option( self::OPTION_NAME, [] );
		$output = is_array( $existing_options ) ? $existing_options : [];

		if ( is_array( $input ) ) {
			foreach ( $input as $key => $value ) {
				if ( 'rename_rules' === $key ) {
					// Sanitize rules array
					$clean_rules = [];
					if ( is_array( $value ) ) {
						foreach ( $value as $rule ) {
							if ( isset( $rule['field'], $rule['name'] ) ) {
								$clean_rules[] = [
									'field' => sanitize_text_field( $rule['field'] ),
									'name'  => sanitize_text_field( $rule['name'] ),
								];
							}
						}
					}
					$output[ $key ] = $clean_rules;
				} elseif ( 'phone_multi_config' === $key ) {
					// Sanitize phone multi config
					$clean_phones = [];
					if ( is_array( $value ) ) {
						foreach ( $value as $phone ) {
							if ( isset( $phone['field'] ) ) {
								$clean_phones[] = [
									'field' => sanitize_text_field( $phone['field'] ),
									'type'  => sanitize_text_field( $phone['type'] ?? 'generic' ),
								];
							}
						}
					}
					$output[ $key ] = $clean_phones;
				} elseif ( 'table_columns' === $key ) {
					// Sanitize columns array
					if ( is_array( $value ) ) {
						$output[ $key ] = array_map( 'sanitize_text_field', $value );
					} else {
						$output[ $key ] = [];
					}
				} elseif ( in_array( $key, ['certificate_opening_text', 'certificate_footer_text'] ) ) {
					// Allow minor HTML or specific formatting if needed, but text_field for now is safer.
					// Opening text and footer text are single line or simple text usually.
					// Textarea for opening text though.
					if ( 'certificate_opening_text' === $key ) {
						$output[ $key ] = sanitize_textarea_field( $value );
					} else {
						$output[ $key ] = sanitize_text_field( $value );
					}
				} elseif ( is_array( $value ) ) {
					$output[ sanitize_key( $key ) ] = array_map( 'sanitize_text_field', $value );
				} else {
					$output[ sanitize_key( $key ) ] = sanitize_text_field( $value );
				}
			}
		}
		return $output;
	}

	/**
	 * Get configuration with defaults and migration logic.
	 */
	public static function get_config() {
		$options = get_option( self::OPTION_NAME, [] );

		// Defaults for new structure
		$defaults = [
			// General Logic Fields
			'request_type_field'    => '',
			'request_type_id'       => [],
			'inactive_statuses'     => [],
			'field_staff_name'      => '', // New Name Field
			'field_onboarding_date' => '',
			'field_cleared'         => '',

			// Phone Logic (New)
			'phone_config_mode'     => '', // 'single' or 'multiple'
			'phone_single_field'    => '',
			'phone_has_type'        => 'no',
			'phone_type_field'      => '',
			'phone_type_value_mobile' => '',
			'phone_multi_config'    => [], // Array of ['field' => slug, 'type' => string]

			// Display Columns
			'table_columns'         => [], // Array of slugs
			'rename_rules'          => [], // Array of rules

			// Certificate Customization
			'certificate_company_name' => get_bloginfo( 'name' ),
			'certificate_opening_text' => 'New Staffmember has completed Onboarding Training with [Trainer Name] and has been present for:',
			'certificate_footer_text'  => 'Completed: [Date] - [Trainer Name]',
		];

		// Ensure request_type_id is an array (backward compatibility)
		if ( isset( $options['request_type_id'] ) && ! is_array( $options['request_type_id'] ) ) {
			$options['request_type_id'] = [ $options['request_type_id'] ];
		}

		// Check for migration necessity
		if ( empty( $options['phone_config_mode'] ) && isset( $options['mobile_logic_mode'] ) ) {
			// Perform Just-In-Time Migration in memory
			$old_mode = $options['mobile_logic_mode'];
			$old_main_phone = $options['field_phone_number'] ?? '';

			if ( 'separate_field' === $old_mode ) {
				$old_mobile_field = $options['field_mobile_number'] ?? '';
				$options['phone_config_mode'] = 'multiple';
				$options['phone_multi_config'] = [];
				if ( $old_main_phone ) {
					$options['phone_multi_config'][] = [ 'field' => $old_main_phone, 'type' => 'generic' ];
				}
				if ( $old_mobile_field ) {
					$options['phone_multi_config'][] = [ 'field' => $old_mobile_field, 'type' => 'mobile' ];
				}
			} elseif ( 'indicator_field' === $old_mode ) {
				$options['phone_config_mode'] = 'single';
				$options['phone_single_field'] = $old_main_phone;
				$options['phone_has_type'] = 'yes';
				$options['phone_type_field'] = $options['field_is_mobile'] ?? '';
				$options['phone_type_value_mobile'] = $options['mobile_option_id'] ?? '';
			} else {
				// Default / None
				$options['phone_config_mode'] = 'single';
				$options['phone_single_field'] = $old_main_phone;
				$options['phone_has_type'] = 'no';
			}
		}

		return wp_parse_args( $options, $defaults );
	}

	/**
	 * Enqueue Assets for Settings Page.
	 */
	public static function enqueue_assets() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading 'tab' for UI logic only, no data processing.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : '';
		if ( 'settings' !== $tab && 'certificate' !== $tab ) {
			return;
		}

		wp_enqueue_script(
			'stackboost-onboarding-settings-js',
			STACKBOOST_PLUGIN_URL . 'src/Modules/OnboardingDashboard/assets/js/settings.js',
			[ 'jquery', 'stackboost-select2' ],
			STACKBOOST_VERSION,
			true
		);
		wp_enqueue_style(
			'stackboost-onboarding-settings-css',
			STACKBOOST_PLUGIN_URL . 'src/Modules/OnboardingDashboard/assets/css/settings.css',
			[],
			STACKBOOST_VERSION
		);
	}

	/**
	 * Render the Settings page.
	 */
	public static function render_page() {
		self::enqueue_assets();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce check handled by WP core during settings save redirect.
		if ( \StackBoost\ForSupportCandy\Core\Request::has_get('settings-updated') ) {
			add_settings_error( 'stackboost_messages', 'stackboost_message', __( 'Settings Saved', 'stackboost-for-supportcandy' ), 'updated' );
		}
		settings_errors( 'stackboost_messages' );

		$plugin_instance = \StackBoost\ForSupportCandy\WordPress\Plugin::get_instance();

		// Fetch fields using the centralized method
		$sc_fields = $plugin_instance->get_supportcandy_columns();
		// Fetch statuses using the centralized method
		$sc_statuses = $plugin_instance->get_supportcandy_statuses();

		$config = self::get_config();

		// Logic Fields Group
		$logic_fields = [
			'field_staff_name'      => __( 'Staff Name Field', 'stackboost-for-supportcandy' ),
			'field_onboarding_date' => __( 'Onboarding Date Field', 'stackboost-for-supportcandy' ),
			'field_cleared'         => __( 'Onboarding Cleared Field', 'stackboost-for-supportcandy' ),
		];

		// Phone Types
		$phone_types = [
			'mobile'  => __( 'Mobile', 'stackboost-for-supportcandy' ),
			'work'    => __( 'Office/Work', 'stackboost-for-supportcandy' ),
			'home'    => __( 'Home', 'stackboost-for-supportcandy' ),
			'fax'     => __( 'Fax', 'stackboost-for-supportcandy' ),
			'generic' => __( 'Generic', 'stackboost-for-supportcandy' ),
		];

		?>
		<div>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::OPTION_GROUP );
				?>

				<!-- Card 1: General Configuration -->
				<div class="stackboost-card stackboost-card-connected">
					<h3 style="margin-top: 0; padding-top: 10px;"><?php esc_html_e( 'General Configuration', 'stackboost-for-supportcandy' ); ?></h3>
					<p><?php esc_html_e( 'Identify the critical fields required for the onboarding logic.', 'stackboost-for-supportcandy' ); ?></p>
					<table class="form-table">
					<tr>
						<th scope="row"><label for="stkb_req_type"><?php esc_html_e( 'Request Type Field', 'stackboost-for-supportcandy' ); ?></label></th>
						<td>
							<select name="<?php echo esc_attr( self::OPTION_NAME ); ?>[request_type_field]" id="stkb_req_type" class="stackboost-ajax-field-selector" data-target="#stkb_req_id">
								<option value=""><?php esc_html_e( '-- Select Field --', 'stackboost-for-supportcandy' ); ?></option>
								<?php foreach ( $sc_fields as $key => $label ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $config['request_type_field'], $key ); ?>>
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'The field that identifies the type of request.', 'stackboost-for-supportcandy' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="stkb_req_id"><?php esc_html_e( 'Onboarding Options', 'stackboost-for-supportcandy' ); ?></label></th>
						<td>
							<select name="<?php echo esc_attr( self::OPTION_NAME ); ?>[request_type_id][]" id="stkb_req_id" class="stackboost-select2" data-selected="<?php echo esc_attr( json_encode( $config['request_type_id'] ) ); ?>" multiple disabled style="width: 100%;">
							</select>
							<p class="description"><?php esc_html_e( 'Select one or more options that represent "Onboarding" requests.', 'stackboost-for-supportcandy' ); ?></p>
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
						</td>
					</tr>
					<?php foreach ( $logic_fields as $key => $label ) : ?>
					<tr>
						<th scope="row"><label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
						<td>
							<select name="<?php echo esc_attr( self::OPTION_NAME ); ?>[<?php echo esc_attr( $key ); ?>]" id="<?php echo esc_attr( $key ); ?>">
								<option value=""><?php esc_html_e( '-- Select Field --', 'stackboost-for-supportcandy' ); ?></option>
								<?php foreach ( $sc_fields as $f_key => $f_label ) : ?>
									<option value="<?php echo esc_attr( $f_key ); ?>" <?php selected( $config[ $key ], $f_key ); ?>>
										<?php echo esc_html( $f_label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<?php endforeach; ?>
					</table>
				</div>

				<!-- Card 2: Phone Configuration -->
				<div class="stackboost-card">
					<h3 style="margin-top: 0; padding-top: 10px;"><?php esc_html_e( 'Phone Configuration', 'stackboost-for-supportcandy' ); ?></h3>
					<table class="form-table">
					<tr>
						<th scope="row"><label for="stkb_phone_mode"><?php esc_html_e( 'Do you have multiple phone fields?', 'stackboost-for-supportcandy' ); ?></label></th>
						<td>
							<select name="<?php echo esc_attr( self::OPTION_NAME ); ?>[phone_config_mode]" id="stkb_phone_mode">
								<option value="single" <?php selected( $config['phone_config_mode'], 'single' ); ?>><?php esc_html_e( 'No - Single Field', 'stackboost-for-supportcandy' ); ?></option>
								<option value="multiple" <?php selected( $config['phone_config_mode'], 'multiple' ); ?>><?php esc_html_e( 'Yes - Multiple Fields', 'stackboost-for-supportcandy' ); ?></option>
							</select>
						</td>
					</tr>
				</table>

				<!-- Scenario A: Single Field -->
				<div id="stkb_phone_single_container" class="stkb-phone-logic-container" style="display:none;">
					<table class="form-table">
						<tr>
							<th scope="row"><label for="stkb_phone_single_field"><?php esc_html_e( 'Phone Number Field', 'stackboost-for-supportcandy' ); ?></label></th>
							<td>
								<select name="<?php echo esc_attr( self::OPTION_NAME ); ?>[phone_single_field]" id="stkb_phone_single_field">
									<option value=""><?php esc_html_e( '-- Select Field --', 'stackboost-for-supportcandy' ); ?></option>
									<?php foreach ( $sc_fields as $key => $label ) : ?>
										<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $config['phone_single_field'], $key ); ?>>
											<?php echo esc_html( $label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="stkb_phone_has_type"><?php esc_html_e( 'Is there a Phone Type field?', 'stackboost-for-supportcandy' ); ?></label></th>
							<td>
								<select name="<?php echo esc_attr( self::OPTION_NAME ); ?>[phone_has_type]" id="stkb_phone_has_type">
									<option value="no" <?php selected( $config['phone_has_type'], 'no' ); ?>><?php esc_html_e( 'No', 'stackboost-for-supportcandy' ); ?></option>
									<option value="yes" <?php selected( $config['phone_has_type'], 'yes' ); ?>><?php esc_html_e( 'Yes', 'stackboost-for-supportcandy' ); ?></option>
								</select>
							</td>
						</tr>
						<tr class="stkb-phone-type-logic" style="display:none;">
							<th scope="row"><label for="stkb_phone_type_field"><?php esc_html_e( 'Phone Type Field', 'stackboost-for-supportcandy' ); ?></label></th>
							<td>
								<select name="<?php echo esc_attr( self::OPTION_NAME ); ?>[phone_type_field]" id="stkb_phone_type_field" class="stackboost-ajax-field-selector" data-target="#stkb_phone_type_val">
									<option value=""><?php esc_html_e( '-- Select Field --', 'stackboost-for-supportcandy' ); ?></option>
									<?php foreach ( $sc_fields as $key => $label ) : ?>
										<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $config['phone_type_field'], $key ); ?>>
											<?php echo esc_html( $label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr class="stkb-phone-type-logic" style="display:none;">
							<th scope="row"><label for="stkb_phone_type_val"><?php esc_html_e( 'Value for "Mobile"', 'stackboost-for-supportcandy' ); ?></label></th>
							<td>
								<select name="<?php echo esc_attr( self::OPTION_NAME ); ?>[phone_type_value_mobile]" id="stkb_phone_type_val" data-selected="<?php echo esc_attr( $config['phone_type_value_mobile'] ); ?>" disabled>
									<option value=""><?php esc_html_e( '-- Select Option --', 'stackboost-for-supportcandy' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'Select the value that indicates a mobile phone.', 'stackboost-for-supportcandy' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<!-- Scenario B: Multiple Fields -->
				<div id="stkb_phone_multi_container" class="stkb-phone-logic-container" style="display:none;">
					<table class="form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'Phone Fields', 'stackboost-for-supportcandy' ); ?></th>
							<td>
								<div id="stkb-phone-multi-list">
									<?php
									$multi_config = $config['phone_multi_config'];
									if ( is_array( $multi_config ) ) :
										foreach ( $multi_config as $index => $item ) :
											if ( empty( $item['field'] ) ) continue;
											$current_type = $item['type'] ?? 'generic';
											?>
											<div class="stkb-phone-multi-row" style="margin-bottom: 5px;">
												<select name="<?php echo esc_attr( self::OPTION_NAME ); ?>[phone_multi_config][<?php echo (int) $index; ?>][field]">
													<?php foreach ( $sc_fields as $key => $label ) : ?>
														<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $item['field'], $key ); ?>>
															<?php echo esc_html( $label ); ?>
														</option>
													<?php endforeach; ?>
												</select>

												<select name="<?php echo esc_attr( self::OPTION_NAME ); ?>[phone_multi_config][<?php echo (int) $index; ?>][type]" style="margin-left: 10px;">
													<?php foreach ( $phone_types as $val => $label ) : ?>
														<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $current_type, $val ); ?>>
															<?php echo esc_html( $label ); ?>
														</option>
													<?php endforeach; ?>
												</select>

												<button type="button" class="button stkb-remove-phone-row" title="Remove"><span class="dashicons dashicons-trash"></span></button>
											</div>
											<?php
										endforeach;
									endif;
									?>
								</div>
								<button type="button" id="stkb-add-phone-row" class="button"><?php esc_html_e( 'Add Phone Field', 'stackboost-for-supportcandy' ); ?></button>
								<p class="description"><?php esc_html_e( 'Add all fields that contain phone numbers and map them to their type.', 'stackboost-for-supportcandy' ); ?></p>
							</td>
						</tr>
					</table>
				</div>
				</div> <!-- End Phone Card -->

				<!-- Card 3: Columns & Renaming -->
				<div class="stackboost-card">
					<h3 style="margin-top: 0; padding-top: 10px;"><?php esc_html_e( 'Staff Table Columns', 'stackboost-for-supportcandy' ); ?></h3>
					<?php self::render_columns_selector( $sc_fields, $config['table_columns'] ); ?>

					<hr style="margin: 20px 0;">

					<h3 style="margin-top: 0;"><?php esc_html_e( 'Column Renaming', 'stackboost-for-supportcandy' ); ?></h3>
					<?php self::render_rename_rules( $sc_fields, $config['rename_rules'] ); ?>
				</div>

				<?php submit_button(); ?>
			</form>
		</div>

		<!-- Template for Multiple Phone Fields -->
		<script type="text/template" id="stkb-phone-multi-template">
			<div class="stkb-phone-multi-row" style="margin-bottom: 5px;">
				<select name="<?php echo esc_attr( self::OPTION_NAME ); ?>[phone_multi_config][__INDEX__][field]">
					<option value=""><?php esc_html_e( '-- Select Field --', 'stackboost-for-supportcandy' ); ?></option>
					<?php foreach ( $sc_fields as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>

				<select name="<?php echo esc_attr( self::OPTION_NAME ); ?>[phone_multi_config][__INDEX__][type]" style="margin-left: 10px;">
					<?php foreach ( $phone_types as $val => $label ) : ?>
						<option value="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>

				<button type="button" class="button stkb-remove-phone-row" title="Remove"><span class="dashicons dashicons-trash"></span></button>
			</div>
		</script>
		<?php
	}

	/**
	 * Render the Certificate Settings page.
	 */
	public static function render_certificate_page() {
		self::enqueue_assets();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce check handled by WP core during settings save redirect. Basic isset check for UI flag.
		if ( isset( $_GET['settings-updated'] ) ) {
			add_settings_error( 'stackboost_messages', 'stackboost_message', __( 'Settings Saved', 'stackboost-for-supportcandy' ), 'updated' );
		}
		settings_errors( 'stackboost_messages' );

		$config = self::get_config();

		?>
		<div class="stackboost-card stackboost-card-connected">
			<h2 style="margin-top: 0; padding-top: 10px;"><?php esc_html_e( 'Certificate Configuration', 'stackboost-for-supportcandy' ); ?></h2>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::OPTION_GROUP );
				?>

				<h3><?php esc_html_e( 'Certificate Details', 'stackboost-for-supportcandy' ); ?></h3>
				<p><?php esc_html_e( 'Customize the text that appears on the onboarding certificate.', 'stackboost-for-supportcandy' ); ?></p>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="stkb_cert_company"><?php esc_html_e( 'Company Name', 'stackboost-for-supportcandy' ); ?></label></th>
						<td>
							<input type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[certificate_company_name]" id="stkb_cert_company" class="regular-text" value="<?php echo esc_attr( $config['certificate_company_name'] ); ?>">
							<p class="description"><?php esc_html_e( 'The company name displayed in the header.', 'stackboost-for-supportcandy' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="stkb_cert_opening"><?php esc_html_e( 'Opening Statement', 'stackboost-for-supportcandy' ); ?></label></th>
						<td>
							<textarea name="<?php echo esc_attr( self::OPTION_NAME ); ?>[certificate_opening_text]" id="stkb_cert_opening" rows="3" class="large-text"><?php echo esc_textarea( $config['certificate_opening_text'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Supported placeholders: [Trainer Name], [Staff Name], [Date]', 'stackboost-for-supportcandy' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="stkb_cert_footer"><?php esc_html_e( 'Footer Text', 'stackboost-for-supportcandy' ); ?></label></th>
						<td>
							<input type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[certificate_footer_text]" id="stkb_cert_footer" class="regular-text" style="width: 100%;" value="<?php echo esc_attr( $config['certificate_footer_text'] ); ?>">
							<p class="description"><?php esc_html_e( 'Supported placeholders: [Trainer Name], [Staff Name], [Date]', 'stackboost-for-supportcandy' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render dual-list selector.
	 */
	private static function render_columns_selector( $all_columns, $selected_slugs ) {
		$selected_slugs = is_array( $selected_slugs ) ? $selected_slugs : [];

		$available_columns = array_diff_key( $all_columns, array_flip( $selected_slugs ) );
		$selected_columns_ordered = [];
		foreach ( $selected_slugs as $slug ) {
			if ( isset( $all_columns[ $slug ] ) ) {
				$selected_columns_ordered[ $slug ] = $all_columns[ $slug ];
			}
		}

		?>
		<div class="stackboost-odb-container">
			<div class="stackboost-odb-box">
				<h3><?php esc_html_e( 'Available Fields', 'stackboost-for-supportcandy' ); ?></h3>
				<select multiple id="stackboost_odb_available_fields" size="10">
					<?php foreach ( $available_columns as $slug => $name ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="stackboost-odb-buttons">
				<button type="button" class="button" id="stackboost_odb_add_all" title="<?php esc_attr_e( 'Add All', 'stackboost-for-supportcandy' ); ?>"><span class="dashicons dashicons-controls-forward"></span></button>
				<button type="button" class="button" id="stackboost_odb_add" title="<?php esc_attr_e( 'Add', 'stackboost-for-supportcandy' ); ?>"><span class="dashicons dashicons-arrow-right"></span></button>
				<button type="button" class="button" id="stackboost_odb_remove" title="<?php esc_attr_e( 'Remove', 'stackboost-for-supportcandy' ); ?>"><span class="dashicons dashicons-arrow-left"></span></button>
				<button type="button" class="button" id="stackboost_odb_remove_all" title="<?php esc_attr_e( 'Remove All', 'stackboost-for-supportcandy' ); ?>"><span class="dashicons dashicons-controls-back"></span></button>
			</div>
			<div class="stackboost-odb-box">
				<h3><?php esc_html_e( 'Selected Columns (In Order)', 'stackboost-for-supportcandy' ); ?></h3>
				<div class="stackboost-odb-selected-wrapper">
					<select multiple name="<?php echo esc_attr( self::OPTION_NAME ); ?>[table_columns][]" id="stackboost_odb_selected_fields" size="10">
						<?php foreach ( $selected_columns_ordered as $slug => $name ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $name ); ?></option>
						<?php endforeach; ?>
					</select>
					<div class="stackboost-odb-buttons">
						<button type="button" class="button" id="stackboost_odb_move_top" title="<?php esc_attr_e( 'Move to Top', 'stackboost-for-supportcandy' ); ?>"><span class="dashicons dashicons-controls-forward"></span></button>
						<button type="button" class="button" id="stackboost_odb_move_up" title="<?php esc_attr_e( 'Move Up', 'stackboost-for-supportcandy' ); ?>"><span class="dashicons dashicons-arrow-right"></span></button>
						<button type="button" class="button" id="stackboost_odb_move_down" title="<?php esc_attr_e( 'Move Down', 'stackboost-for-supportcandy' ); ?>"><span class="dashicons dashicons-arrow-left"></span></button>
						<button type="button" class="button" id="stackboost_odb_move_bottom" title="<?php esc_attr_e( 'Move to Bottom', 'stackboost-for-supportcandy' ); ?>"><span class="dashicons dashicons-controls-back"></span></button>
					</div>
				</div>
			</div>
		</div>
		<p class="description"><?php esc_html_e( 'Select the fields to display as columns in the staff table.', 'stackboost-for-supportcandy' ); ?></p>
		<?php
	}

	/**
	 * Render renaming rules builder.
	 */
	private static function render_rename_rules( $all_columns, $rename_rules ) {
		?>
		<div id="stackboost-odb-rules-container">
			<?php
			if ( ! empty( $rename_rules ) ) :
				foreach ( $rename_rules as $index => $rule ) :
					?>
					<div class="stackboost-odb-rule-row">
						<span><?php esc_html_e( 'Display', 'stackboost-for-supportcandy' ); ?></span>
						<select name="<?php echo esc_attr( self::OPTION_NAME ); ?>[rename_rules][<?php echo (int) $index; ?>][field]">
							<?php foreach ( $all_columns as $slug => $name ) : ?>
								<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $slug, $rule['field'] ); ?>><?php echo esc_html( $name ); ?></option>
							<?php endforeach; ?>
						</select>
						<span><?php esc_html_e( 'as', 'stackboost-for-supportcandy' ); ?></span>
						<input type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[rename_rules][<?php echo (int) $index; ?>][name]" class="regular-text" value="<?php echo esc_attr( $rule['name'] ); ?>" />
						<button type="button" class="button stackboost-odb-remove-rule" title="<?php esc_attr_e( 'Remove Rule', 'stackboost-for-supportcandy' ); ?>"><span class="dashicons dashicons-trash"></span></button>
					</div>
					<?php
				endforeach;
			endif;
			?>
		</div>
		<button type="button" id="stackboost-odb-add-rule" class="button"><?php esc_html_e( 'Add Rule', 'stackboost-for-supportcandy' ); ?></button>

		<script type="text/template" id="stackboost-odb-rule-template">
			<div class="stackboost-odb-rule-row">
				<span><?php esc_html_e( 'Display', 'stackboost-for-supportcandy' ); ?></span>
				<select name="<?php echo esc_attr( self::OPTION_NAME ); ?>[rename_rules][__INDEX__][field]">
					<?php foreach ( $all_columns as $slug => $name ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $name ); ?></option>
					<?php endforeach; ?>
				</select>
				<span><?php esc_html_e( 'as', 'stackboost-for-supportcandy' ); ?></span>
				<input type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[rename_rules][__INDEX__][name]" class="regular-text" value="" />
				<button type="button" class="button stackboost-odb-remove-rule" title="<?php esc_attr_e( 'Remove Rule', 'stackboost-for-supportcandy' ); ?>"><span class="dashicons dashicons-trash"></span></button>
			</div>
		</script>
		<?php
	}
}