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
			function() {
				echo '<p>' . esc_html__( 'Identify the critical fields required for the onboarding logic.', 'stackboost-for-supportcandy' ) . '</p>';
			},
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
				} elseif ( 'table_columns' === $key ) {
					// Sanitize columns array
					if ( is_array( $value ) ) {
						$output[ $key ] = array_map( 'sanitize_text_field', $value );
					} else {
						$output[ $key ] = [];
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
	 * Get configuration with defaults.
	 */
	public static function get_config() {
		$options = get_option( self::OPTION_NAME, [] );

		$defaults = [
			// General Logic Fields
			'request_type_field'    => '',
			'request_type_id'       => '',
			'inactive_statuses'     => [],
			'field_onboarding_date' => '',
			'field_cleared'         => '',

			// Display Columns
			'table_columns'         => [], // Array of slugs
			'rename_rules'          => [], // Array of rules

			// Mobile Logic
			'field_is_mobile'       => '',
			'mobile_option_id'      => '',
		];

		return wp_parse_args( $options, $defaults );
	}

	/**
	 * Enqueue Assets for Settings Page.
	 */
	public static function enqueue_assets() {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : '';
		if ( 'settings' !== $tab ) {
			return;
		}

		wp_enqueue_script(
			'stackboost-onboarding-settings-js',
			STACKBOOST_PLUGIN_URL . 'src/Modules/OnboardingDashboard/assets/js/settings.js',
			[ 'jquery' ],
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

		if ( isset( $_GET['settings-updated'] ) ) {
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
			'field_onboarding_date' => __( 'Onboarding Date Field', 'stackboost-for-supportcandy' ),
			'field_cleared'         => __( 'Onboarding Cleared Field', 'stackboost-for-supportcandy' ),
		];

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
							<p class="description"><?php esc_html_e( 'The field that identifies the type of request.', 'stackboost-for-supportcandy' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="stkb_req_id"><?php esc_html_e( 'Onboarding Option ID', 'stackboost-for-supportcandy' ); ?></label></th>
						<td>
							<input type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[request_type_id]" id="stkb_req_id" value="<?php echo esc_attr( $config['request_type_id'] ); ?>" class="regular-text">
							<p class="description"><?php esc_html_e( 'The ID of the option that represents an "Onboarding" request.', 'stackboost-for-supportcandy' ); ?></p>
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

				<!-- Staff Table Columns (Dual List) -->
				<h3><?php esc_html_e( 'Staff Table Columns', 'stackboost-for-supportcandy' ); ?></h3>
				<?php self::render_columns_selector( $sc_fields, $config['table_columns'] ); ?>

				<!-- Renaming Rules -->
				<h3><?php esc_html_e( 'Column Renaming', 'stackboost-for-supportcandy' ); ?></h3>
				<?php self::render_rename_rules( $sc_fields, $config['rename_rules'] ); ?>

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
							<p class="description"><?php esc_html_e( 'The Option ID that represents "Yes" or "Mobile".', 'stackboost-for-supportcandy' ); ?></p>
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
