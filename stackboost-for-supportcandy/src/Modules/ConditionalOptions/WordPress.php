<?php

namespace StackBoost\ForSupportCandy\Modules\ConditionalOptions;

use StackBoost\ForSupportCandy\Core\Module;

/**
 * WordPress Adapter for Conditional Options.
 * Handles hooks, settings registration, and script enqueueing.
 *
 * @package StackBoost\ForSupportCandy\Modules\ConditionalOptions
 */
class WordPress extends Module {

	/** @var WordPress|null */
	private static ?WordPress $instance = null;

	/**
	 * Get the single instance of the class.
	 */
	public static function get_instance(): WordPress {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
	}

	/**
	 * Get the slug for this module.
	 * @return string
	 */
	public function get_slug(): string {
		return 'conditional_options';
	}

	/**
	 * Initialize hooks.
	 */
	public function init_hooks() {
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_scripts' ] );

		// AJAX Hooks for Admin UI
		add_action( 'wp_ajax_stackboost_co_get_field_options', [ $this, 'ajax_get_field_options' ] );
		add_action( 'wp_ajax_stackboost_co_get_roles', [ $this, 'ajax_get_roles' ] );
		add_action( 'wp_ajax_stackboost_co_save_rules', [ $this, 'ajax_save_rules' ] );

		// Security Enforcement Hook (Backend)
		// wpsc_create_ticket_data filters the data right before insertion.
		add_filter( 'wpsc_create_ticket_data', [ $this, 'enforce_permissions_on_submission' ], 20, 3 );
	}

	/**
	 * Render the administration page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Enqueue module-specific assets
		wp_enqueue_style( 'stackboost-co-admin-css' );
		wp_enqueue_script( 'stackboost-co-admin-js' );

		$core = Core::get_instance();
		$is_enabled = $core->is_enabled();
		?>
		<div class="wrap stackboost-dashboard">
			<h1><?php esc_html_e( 'Conditional Options', 'stackboost-for-supportcandy' ); ?></h1>
			<p><?php esc_html_e( 'Configure granular visibility rules for field options based on user roles.', 'stackboost-for-supportcandy' ); ?></p>

			<div id="stackboost-co-app">
				<!-- Global Enable/Disable Toggle -->
				<div class="stackboost-card" style="margin-bottom: 20px;">
					<div class="pm-header">
						<h2 style="margin-bottom: 15px;"><?php esc_html_e( 'Feature Status', 'stackboost-for-supportcandy' ); ?></h2>
					</div>
					<div style="display: flex; align-items: center; gap: 15px;">
						<label class="switch">
							<input type="checkbox" id="stackboost_co_enabled" <?php checked( $is_enabled, true ); ?>>
							<span class="slider round"></span>
						</label>
						<div>
							<strong><?php esc_html_e( 'Enable Conditional Options', 'stackboost-for-supportcandy' ); ?></strong>
							<p class="description" style="margin: 5px 0 0;"><?php esc_html_e( 'Toggle this feature on or off globally. Rules will not be enforced when disabled.', 'stackboost-for-supportcandy' ); ?></p>
						</div>
					</div>
				</div>

				<!-- Main Table View -->
				<div id="stackboost-co-rules-card" class="stackboost-card <?php echo $is_enabled ? '' : 'stackboost-disabled-ui'; ?>">
					<div class="pm-header">
						<h2><?php esc_html_e( 'Manage Rules', 'stackboost-for-supportcandy' ); ?></h2>
						<div class="pm-limit-counter">
							<!-- Populated by JS -->
						</div>
					</div>

					<div class="pm-controls">
						<button id="pm-add-rule-btn" class="button button-primary"><?php esc_html_e( 'Add New Rule', 'stackboost-for-supportcandy' ); ?></button>
					</div>

					<div class="pm-rules-wrapper" style="margin-top: 15px;">
						<table class="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Target Field', 'stackboost-for-supportcandy' ); ?></th>
									<th><?php esc_html_e( 'Context', 'stackboost-for-supportcandy' ); ?></th>
									<th style="width: 100px; text-align: right;"><?php esc_html_e( 'Actions', 'stackboost-for-supportcandy' ); ?></th>
								</tr>
							</thead>
							<tbody id="pm-rules-table-body">
								<!-- Populated by JS -->
							</tbody>
						</table>
						<p id="pm-no-rules-msg" style="display:none; text-align:center; padding: 20px; font-style: italic;">
							<?php esc_html_e( 'No rules configured.', 'stackboost-for-supportcandy' ); ?>
						</p>
					</div>
				</div>
			</div>

			<!-- Hidden Modal for Add/Edit -->
			<div id="stackboost-co-modal-overlay" class="stackboost-modal-overlay" style="display:none;">
				<div class="stackboost-modal-box" style="max-width: 800px; width: 90%;">
					<div class="stackboost-modal-header">
						<h3 class="stackboost-modal-title"><?php esc_html_e( 'Edit Rule', 'stackboost-for-supportcandy' ); ?></h3>
						<button type="button" class="stackboost-modal-close">&times;</button>
					</div>

					<div class="stackboost-modal-body">
						<div class="pm-settings-row">
							<!-- Field Selector -->
							<div class="pm-field-selector">
								<label for="pm-modal-field-select"><?php esc_html_e( 'Target Field:', 'stackboost-for-supportcandy' ); ?></label>
								<select id="pm-modal-field-select" class="pm-field-select" style="width: 100%;">
									<option value="">-- <?php esc_html_e( 'Select Field', 'stackboost-for-supportcandy' ); ?> --</option>
									<?php
									$plugin_instance = \StackBoost\ForSupportCandy\WordPress\Plugin::get_instance();
									$fields = $plugin_instance->get_supportcandy_columns();
									foreach ( $fields as $slug => $name ) {
										echo '<option value="' . esc_attr( $slug ) . '">' . esc_html( $name ) . '</option>';
									}
									?>
								</select>
							</div>

							<!-- Context Selector -->
							<div class="pm-context-selector">
								<label><?php esc_html_e( 'Role Context:', 'stackboost-for-supportcandy' ); ?></label>
								<div class="pm-radio-group">
									<label><input type="radio" name="modal_context" value="sc" checked> <?php esc_html_e( 'SupportCandy Roles', 'stackboost-for-supportcandy' ); ?></label>
									<label><input type="radio" name="modal_context" value="wp"> <?php esc_html_e( 'WP Roles', 'stackboost-for-supportcandy' ); ?></label>
								</div>
							</div>
						</div>

						<p class="description" style="margin-bottom: 15px;">
							<?php esc_html_e( 'Select the roles that should be BLOCKED from seeing each option.', 'stackboost-for-supportcandy' ); ?>
						</p>

						<!-- Matrix Container -->
						<div class="pm-matrix-container" id="pm-modal-matrix">
							<div class="pm-loading-placeholder"><?php esc_html_e( 'Select a field to configure options.', 'stackboost-for-supportcandy' ); ?></div>
						</div>
					</div>

					<div class="stackboost-modal-footer">
						<button type="button" class="button button-secondary stackboost-modal-close"><?php esc_html_e( 'Cancel', 'stackboost-for-supportcandy' ); ?></button>
						<button type="button" id="pm-modal-save-btn" class="button button-primary"><?php esc_html_e( 'Save Rule', 'stackboost-for-supportcandy' ); ?></button>
					</div>
				</div>
			</div>

		</div>
		<?php
	}

	/**
	 * Register settings (Menu is handled by central Settings class via page slug).
	 */
	public function register_settings() {
		// No traditional settings fields here as we use a custom AJAX UI.
	}

	/**
	 * Enqueue Admin Scripts.
	 */
	public function enqueue_admin_scripts( $hook_suffix ) {
		if ( 'stackboost-for-supportcandy_page_stackboost-conditional-options' !== $hook_suffix && 'stackboost_page_stackboost-conditional-options' !== $hook_suffix ) {
			return;
		}

		wp_register_style(
			'stackboost-co-admin-css',
			STACKBOOST_PLUGIN_URL . 'src/Modules/ConditionalOptions/assets/css/admin-matrix.css',
			[],
			STACKBOOST_VERSION
		);

		wp_register_script(
			'stackboost-co-admin-js',
			STACKBOOST_PLUGIN_URL . 'src/Modules/ConditionalOptions/assets/js/admin-matrix.js',
			[ 'jquery', 'stackboost-admin-common' ], // Depends on common for nonce/ajax_url
			STACKBOOST_VERSION . '.3',
			true
		);

		// Localize Data
		$plugin_instance = \StackBoost\ForSupportCandy\WordPress\Plugin::get_instance();
		$core = Core::get_instance();

		wp_localize_script( 'stackboost-co-admin-js', 'stackboostCO', [
			'fields'  => $plugin_instance->get_supportcandy_columns(), // Pass field list [slug => name]
			'rules'   => $core->get_rules(),
			'enabled' => $core->is_enabled(),
			'tier'    => stackboost_get_license_tier(),
			'i18n'    => [
				'confirm_delete' => __( 'Are you sure you want to delete this rule?', 'stackboost-for-supportcandy' ),
				'limit_reached'  => __( 'Limit Reached: Upgrade to Pro for unlimited rules.', 'stackboost-for-supportcandy' ),
				'toggle_all'     => __( 'Select All / None', 'stackboost-for-supportcandy' ),
			]
		] );
	}

	/**
	 * Enqueue Frontend Enforcement Scripts.
	 */
	public function enqueue_frontend_scripts() {
		$core = Core::get_instance();

		if ( ! $core->is_enabled() ) {
			return;
		}

		wp_enqueue_script(
			'stackboost-co-frontend',
			STACKBOOST_PLUGIN_URL . 'src/Modules/ConditionalOptions/assets/js/frontend-enforcement.js',
			[ 'jquery' ],
			STACKBOOST_VERSION,
			true
		);

		// Determine Current User Roles
		$current_wp_roles = [];
		$current_sc_roles = [];

		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			$current_wp_roles = (array) $user->roles;
		}

		// SupportCandy Role (Agent)
		if ( class_exists( '\WPSC_Current_User' ) && isset( \WPSC_Current_User::$current_user ) ) {
			$sc_user = \WPSC_Current_User::$current_user;
			if ( $sc_user->is_agent ) {
				$current_sc_roles[] = $sc_user->agent->role;
			}
		}

		wp_localize_script( 'stackboost-co-frontend', 'stackboostCORules', [
			'rules' => $core->get_rules(),
			'user'  => [
				'wp_roles' => $current_wp_roles,
				'sc_roles' => $current_sc_roles,
				// 'is_admin' removed as we now enforce for admins too if rule exists
			]
		] );
	}

	/**
	 * AJAX: Get Field Options.
	 */
	public function ajax_get_field_options() {
		if ( function_exists( 'stackboost_log' ) ) {
			stackboost_log( 'AJAX Get Field Options Called. POST: ' . print_r( $_POST, true ), 'conditional_options' );
		}

		check_ajax_referer( 'stackboost_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Forbidden' ] );
		}

		$field_slug = isset( $_POST['field_slug'] ) ? sanitize_text_field( $_POST['field_slug'] ) : '';
		if ( empty( $field_slug ) ) {
			wp_send_json_error( [ 'message' => 'Missing field slug' ] );
		}

		if ( ! class_exists( '\WPSC_Custom_Field' ) ) {
			wp_send_json_error( [ 'message' => 'SupportCandy classes not loaded.' ] );
		}

		$cf = \WPSC_Custom_Field::get_cf_by_slug( $field_slug );
		if ( ! $cf ) {
			wp_send_json_error( [ 'message' => 'Field not found.' ] );
		}

		$response_data = [];
		$raw_options = [];

		// Specific Handling for Standard Fields that don't use generic options
		if ( 'df_category' === $cf->type::$slug ) {
			$raw_options = \WPSC_Category::find( [ 'items_per_page' => 0 ] )['results'];
		} elseif ( 'df_priority' === $cf->type::$slug ) {
			$raw_options = \WPSC_Priority::find( [ 'items_per_page' => 0 ] )['results'];
		} elseif ( 'df_status' === $cf->type::$slug ) {
			$raw_options = \WPSC_Status::find( [ 'items_per_page' => 0 ] )['results'];
		} else {
			// Generic Handling
			if ( ! $cf->type::$has_options ) {
				wp_send_json_error( [ 'message' => 'This field type does not support options.' ] );
			}
			$raw_options = $cf->get_options();
		}

		foreach ( $raw_options as $option ) {
			// Handle object vs array (models are objects)
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
	 * AJAX: Get Roles.
	 */
	public function ajax_get_roles() {
		if ( function_exists( 'stackboost_log' ) ) {
			stackboost_log( 'AJAX Get Roles Called. POST: ' . print_r( $_POST, true ), 'conditional_options' );
		}

		check_ajax_referer( 'stackboost_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Forbidden' ] );
		}

		$context = sanitize_text_field( $_POST['context'] ?? 'wp' );
		$roles   = Core::get_instance()->get_roles( $context );

		wp_send_json_success( $roles );
	}

	/**
	 * AJAX: Save Rules.
	 */
	public function ajax_save_rules() {
		if ( function_exists( 'stackboost_log' ) ) {
			stackboost_log( 'AJAX Save Rules Called. POST: ' . print_r( $_POST, true ), 'conditional_options' );
		}

		check_ajax_referer( 'stackboost_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Forbidden' ] );
		}

		$rules_json = stripslashes( $_POST['rules'] ?? '[]' );
		$rules      = json_decode( $rules_json, true );
		$is_enabled = isset( $_POST['enabled'] ) && 'true' === $_POST['enabled'];

		if ( function_exists( 'stackboost_log' ) ) {
			stackboost_log( 'Decoded Rules: ' . print_r( $rules, true ) . ' Enabled: ' . ( $is_enabled ? 'Yes' : 'No' ), 'conditional_options' );
		}

		if ( ! is_array( $rules ) ) {
			wp_send_json_error( [ 'message' => 'Invalid data format' ] );
		}

		$result = Core::get_instance()->save_config( $rules, $is_enabled );

		if ( is_wp_error( $result ) ) {
			if ( function_exists( 'stackboost_log' ) ) {
				stackboost_log( 'Save Error: ' . $result->get_error_message(), 'conditional_options' );
			}
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		if ( function_exists( 'stackboost_log' ) ) {
			stackboost_log( 'Save Success', 'conditional_options' );
		}

		wp_send_json_success( [ 'message' => __( 'Rules saved successfully.', 'stackboost-for-supportcandy' ) ] );
	}

	/**
	 * Backend Enforcement Logic.
	 * Hooked to `wpsc_create_ticket_data`.
	 *
	 * @param array $data The data to be inserted.
	 * @param array $custom_fields Array of custom field objects.
	 * @param bool  $is_my_profile
	 * @return array The filtered data.
	 */
	public function enforce_permissions_on_submission( $data, $custom_fields, $is_my_profile ) {
		// Removed Admin bypass to allow testing/enforcement for admins too

		$core = Core::get_instance();
		if ( ! $core->is_enabled() ) {
			return $data;
		}

		$rules = $core->get_rules();

		if ( empty( $rules ) ) {
			return $data;
		}

		// Get Current User Roles
		$current_wp_roles = [];
		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			$current_wp_roles = (array) $user->roles;
		}

		$current_sc_roles = [];
		if ( class_exists( '\WPSC_Current_User' ) && isset( \WPSC_Current_User::$current_user ) ) {
			$sc_user = \WPSC_Current_User::$current_user;
			if ( $sc_user->is_agent ) {
				$current_sc_roles[] = $sc_user->agent->role;
			}
		}

		foreach ( $rules as $field_slug => $rule ) {
			if ( ! isset( $data[ $field_slug ] ) ) {
				continue;
			}

			$context = $rule['context'];
			$option_rules = $rule['option_rules']; // [ option_id => [ target_roles ] ]

			$submitted_value = $data[ $field_slug ];
			// Normalize to array
			$submitted_ids = is_array( $submitted_value ) ? $submitted_value : [ $submitted_value ];
			$modified_ids = $submitted_ids;
			$has_change = false;

			foreach ( $submitted_ids as $key => $id ) {
				// If no rules for this specific option, it's visible by default.
				if ( ! isset( $option_rules[ $id ] ) ) {
					continue;
				}

				$target_roles = $option_rules[ $id ];
				$roles_to_check = ( 'wp' === $context ) ? $current_wp_roles : $current_sc_roles;

				// Check if user has any of the target roles
				$user_has_target_role = false;
				foreach ( $roles_to_check as $user_role ) {
					if ( in_array( $user_role, $target_roles, true ) ) {
						$user_has_target_role = true;
						break;
					}
				}

				// Deny Mode Only: Hide if user HAS the target role
				if ( $user_has_target_role ) {
					unset( $modified_ids[ $key ] );
					$has_change = true;

					if ( function_exists( 'stackboost_log' ) ) {
						stackboost_log( "Permission Enforcement: Blocked value '$id' for field '$field_slug' (User Role Match).", 'security' );
					}
				}
			}

			if ( $has_change ) {
				if ( is_array( $data[ $field_slug ] ) ) {
					$data[ $field_slug ] = array_values( $modified_ids ); // Re-index
				} else {
					// Single value was blocked
					$data[ $field_slug ] = '';
				}
			}
		}

		return $data;
	}
}
