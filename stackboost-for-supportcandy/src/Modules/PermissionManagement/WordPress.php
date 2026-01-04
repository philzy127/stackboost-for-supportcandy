<?php

namespace StackBoost\ForSupportCandy\Modules\PermissionManagement;

use StackBoost\ForSupportCandy\Core\Module;

/**
 * WordPress Adapter for Permission Management.
 * Handles hooks, settings registration, and script enqueueing.
 *
 * @package StackBoost\ForSupportCandy\Modules\PermissionManagement
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
		return 'permission_management';
	}

	/**
	 * Initialize hooks.
	 */
	public function init_hooks() {
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_scripts' ] );

		// AJAX Hooks for Admin UI
		add_action( 'wp_ajax_stackboost_pm_get_field_options', [ $this, 'ajax_get_field_options' ] );
		add_action( 'wp_ajax_stackboost_pm_get_roles', [ $this, 'ajax_get_roles' ] );
		add_action( 'wp_ajax_stackboost_pm_save_rules', [ $this, 'ajax_save_rules' ] );

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
		wp_enqueue_style( 'stackboost-pm-admin-css' );
		wp_enqueue_script( 'stackboost-pm-admin-js' );

		?>
		<div class="wrap stackboost-dashboard">
			<h1><?php esc_html_e( 'Permission Management', 'stackboost-for-supportcandy' ); ?></h1>
			<p><?php esc_html_e( 'Configure granular visibility rules for field options based on user roles.', 'stackboost-for-supportcandy' ); ?></p>

			<div id="stackboost-pm-app">
				<!-- JS App Container -->
				<div class="stackboost-card">
					<div class="pm-header">
						<h2><?php esc_html_e( 'Manage Rules', 'stackboost-for-supportcandy' ); ?></h2>
						<div class="pm-limit-counter">
							<!-- Populated by JS -->
						</div>
					</div>

					<div class="pm-controls">
						<button id="pm-add-rule-btn" class="button button-primary"><?php esc_html_e( 'Add New Rule', 'stackboost-for-supportcandy' ); ?></button>
					</div>

					<div id="pm-rules-container">
						<!-- Rules will be rendered here -->
					</div>
				</div>
			</div>

			<div class="pm-save-actions">
				<button id="pm-save-all-btn" class="button button-primary button-large"><?php esc_html_e( 'Save Changes', 'stackboost-for-supportcandy' ); ?></button>
				<span class="spinner"></span>
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
		if ( 'stackboost-for-supportcandy_page_stackboost-permission-management' !== $hook_suffix && 'stackboost_page_stackboost-permission-management' !== $hook_suffix ) {
			return;
		}

		wp_register_style(
			'stackboost-pm-admin-css',
			STACKBOOST_PLUGIN_URL . 'src/Modules/PermissionManagement/assets/css/admin-matrix.css',
			[],
			STACKBOOST_VERSION
		);

		wp_register_script(
			'stackboost-pm-admin-js',
			STACKBOOST_PLUGIN_URL . 'src/Modules/PermissionManagement/assets/js/admin-matrix.js',
			[ 'jquery', 'stackboost-admin-common' ], // Depends on common for nonce/ajax_url
			STACKBOOST_VERSION,
			true
		);

		// Localize Data
		$plugin_instance = \StackBoost\ForSupportCandy\WordPress\Plugin::get_instance();
		$core = Core::get_instance();

		wp_localize_script( 'stackboost-pm-admin-js', 'stackboostPM', [
			'fields' => $plugin_instance->get_supportcandy_columns(), // Pass field list [slug => name]
			'rules'  => $core->get_rules(),
			'tier'   => stackboost_get_license_tier(),
			'i18n'   => [
				'confirm_delete' => __( 'Are you sure you want to delete this rule?', 'stackboost-for-supportcandy' ),
				'limit_reached'  => __( 'Limit Reached: Upgrade to Pro for unlimited rules.', 'stackboost-for-supportcandy' ),
			]
		] );
	}

	/**
	 * Enqueue Frontend Enforcement Scripts.
	 */
	public function enqueue_frontend_scripts() {
		wp_enqueue_script(
			'stackboost-pm-frontend',
			STACKBOOST_PLUGIN_URL . 'src/Modules/PermissionManagement/assets/js/frontend-enforcement.js',
			[ 'jquery' ],
			STACKBOOST_VERSION,
			true
		);

		$core = Core::get_instance();

		// Determine Current User Roles
		$current_wp_roles = [];
		$current_sc_roles = [];

		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			$current_wp_roles = (array) $user->roles;
		} else {
			// Guest role handling if needed, usually empty or 'guest'
		}

		// SupportCandy Role (Agent)
		if ( class_exists( '\WPSC_Current_User' ) && isset( \WPSC_Current_User::$current_user ) ) {
			$sc_user = \WPSC_Current_User::$current_user;
			if ( $sc_user->is_agent ) {
				$current_sc_roles[] = $sc_user->agent->role;
			}
		}

		wp_localize_script( 'stackboost-pm-frontend', 'stackboostPMRules', [
			'rules' => $core->get_rules(),
			'user'  => [
				'wp_roles' => $current_wp_roles,
				'sc_roles' => $current_sc_roles,
				'is_admin' => current_user_can( 'manage_options' ), // Admin Override
			]
		] );
	}

	/**
	 * AJAX: Get Field Options.
	 */
	public function ajax_get_field_options() {
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

		// Check if field has options
		if ( ! $cf->type::$has_options ) {
			wp_send_json_error( [ 'message' => 'This field type does not support options.' ] );
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
	 * AJAX: Get Roles.
	 */
	public function ajax_get_roles() {
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
		check_ajax_referer( 'stackboost_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Forbidden' ] );
		}

		$rules_json = stripslashes( $_POST['rules'] ?? '[]' );
		$rules      = json_decode( $rules_json, true );

		if ( ! is_array( $rules ) ) {
			wp_send_json_error( [ 'message' => 'Invalid data format' ] );
		}

		// Sanitize Rules Structure
		// [ field_slug => [ context => 'wp', option_rules => [ option_id => [ role1, role2 ] ] ] ]
		// We should accept the array as is but ensure keys/values are safe.
		// For simplicity, we trust the structure but sanitize strings.

		// TODO: Implement deep sanitization if needed.

		$result = Core::get_instance()->save_rules( $rules );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
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
		if ( current_user_can( 'manage_options' ) ) {
			return $data; // Admins are exempt
		}

		$core = Core::get_instance();
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
			$option_rules = $rule['option_rules']; // [ option_id => [ excluded_roles ] ]

			$submitted_value = $data[ $field_slug ];
			// $submitted_value is typically the Option ID (int) or Array of IDs (multi-select)

			// Normalize to array for checking
			$submitted_ids = is_array( $submitted_value ) ? $submitted_value : [ $submitted_value ];

			foreach ( $submitted_ids as $id ) {
				if ( isset( $option_rules[ $id ] ) ) {
					$excluded_roles = $option_rules[ $id ];

					// Check against context
					$roles_to_check = ( 'wp' === $context ) ? $current_wp_roles : $current_sc_roles;

					// "Any Hidden = Hidden" Logic
					$is_restricted = false;
					foreach ( $roles_to_check as $user_role ) {
						if ( in_array( $user_role, $excluded_roles, true ) ) {
							$is_restricted = true;
							break;
						}
					}

					if ( $is_restricted ) {
						// Found a restricted value!
						// Action: Remove it (sanitize).
						// For single select, set to empty/default.
						// For multi-select, remove this ID from array.

						if ( is_array( $data[ $field_slug ] ) ) {
							$data[ $field_slug ] = array_diff( $data[ $field_slug ], [ $id ] );
						} else {
							// Single value
							// We should check if there's a default, or just null it.
							// Setting to '' usually triggers default handling or empty.
							$data[ $field_slug ] = '';
						}

						// Log enforcement action
						stackboost_log( "Permission Enforcement: Blocked value '$id' for field '$field_slug' for user.", 'security' );
					}
				}
			}
		}

		return $data;
	}
}
