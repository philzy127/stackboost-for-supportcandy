<?php

namespace StackBoost\ForSupportCandy\Modules\TicketGuard;

if ( ! defined( 'ABSPATH' ) ) exit;

use StackBoost\ForSupportCandy\Core\Module;
use StackBoost\ForSupportCandy\Core\Request;

/**
 * WordPress Adapter for Ticket Guard.
 * Handles hooks, scripts, AJAX endpoints, and admin settings page.
 *
 * @package StackBoost\ForSupportCandy\Modules\TicketGuard
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
	 *
	 * @return string
	 */
	public function get_slug(): string {
		return 'ticket_guard';
	}

	/**
	 * Initialize hooks.
	 */
	public function init_hooks() {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_scripts' ] );

		// AJAX Hooks for Admin Rule Builder
		add_action( 'wp_ajax_stackboost_tg_save_rules', [ $this, 'ajax_save_rules' ] );
		add_action( 'wp_ajax_stackboost_tg_get_form_options', [ $this, 'ajax_get_form_options' ] );

		// Security Enforcement Hook (Backend)
		add_filter( 'wpsc_create_ticket_data', [ $this, 'enforce_backend_rules' ], 20, 1 );
	}

	/**
	 * Enqueue Admin Scripts and Styles for the Rule Builder.
	 *
	 * @param string $hook_suffix
	 */
	public function enqueue_admin_scripts( $hook_suffix ) {
		if ( false === strpos( $hook_suffix, 'ticket-guard' ) ) {
			return;
		}

		wp_register_style(
			'stackboost-tg-admin-css',
			STACKBOOST_PLUGIN_URL . 'src/Modules/TicketGuard/assets/css/admin-rules.css',
			[],
			STACKBOOST_VERSION
		);

		wp_register_script(
			'stackboost-tg-admin-js',
			STACKBOOST_PLUGIN_URL . 'src/Modules/TicketGuard/assets/js/admin-rules.js',
			[ 'jquery' ],
			STACKBOOST_VERSION,
			true
		);

		wp_enqueue_style( 'stackboost-tg-admin-css' );

		$core        = Core::get_instance();
		$form_data   = $core->get_form_options();

		wp_localize_script( 'stackboost-tg-admin-js', 'stackboostTicketGuardAdmin', [
			'rules'      => array_values( $core->get_rules() ),
			'enabled'    => $core->is_enabled(),
			'fields'     => $form_data['fields'],
			'categories' => $form_data['categories'],
			'dropdowns'  => $form_data['dropdowns'],
			'nonce'      => wp_create_nonce( 'stackboost_admin_nonce' ),
			'ajax_url'   => admin_url( 'admin-ajax.php' ),
			'i18n'       => [
				'confirm_delete' => __( 'Are you sure you want to delete this rule?', 'stackboost-for-supportcandy' ),
				'saved_success'  => __( 'Ticket Guard settings saved successfully.', 'stackboost-for-supportcandy' ),
				'save_error'     => __( 'An error occurred while saving rules.', 'stackboost-for-supportcandy' ),
			]
		] );

		wp_enqueue_script( 'stackboost-tg-admin-js' );
	}

	/**
	 * Render the administration page for Ticket Guard.
	 */
	public function render_page() {
		if ( ! current_user_can( STACKBOOST_CAP_MANAGE_TICKET_GUARD ) ) {
			return;
		}

		$core       = Core::get_instance();
		$is_enabled = $core->is_enabled();
		$form_data  = $core->get_form_options();
		$fields     = $form_data['fields'];
		$dropdowns  = $form_data['dropdowns'];
		$rules      = array_values( $core->get_rules() );

		$theme_class = 'sb-theme-clean-tech';
		if ( class_exists( 'StackBoost\ForSupportCandy\Modules\Appearance\WordPress' ) ) {
			$theme_class = \StackBoost\ForSupportCandy\Modules\Appearance\WordPress::get_active_theme_class();
		}
		?>
		<div class="wrap stackboost-dashboard <?php echo esc_attr( $theme_class ); ?>">
			<h1><?php esc_html_e( 'Ticket Guard', 'stackboost-for-supportcandy' ); ?></h1>
			<p><?php esc_html_e( 'Rules-based intake optimization engine to steer users toward appropriate ticket categories and eliminate generic "Other" submissions.', 'stackboost-for-supportcandy' ); ?></p>

			<!-- Hidden Inputs for Standard Form Submissions -->
			<input type="hidden" name="stackboost_settings[enable_ticket_guard]" id="sb_tg_enabled_hidden" value="<?php echo $is_enabled ? '1' : '0'; ?>" />
			<input type="hidden" name="stackboost_settings[ticket_guard_rules]" id="sb_tg_rules_hidden" value="<?php echo esc_attr( json_encode( $rules ) ); ?>" />

			<div id="stackboost-ticket-guard-app">
				<!-- Global Enable/Disable Toggle -->
				<div class="stackboost-card" style="margin-bottom: 20px;">
					<div class="pm-header">
						<h2 style="margin-bottom: 15px;"><?php esc_html_e( 'Feature Status', 'stackboost-for-supportcandy' ); ?></h2>
					</div>
					<div style="display: flex; align-items: center; gap: 15px;">
						<label class="switch">
							<input type="checkbox" id="stackboost_tg_enabled" <?php checked( $is_enabled, true ); ?>>
							<span class="slider round"></span>
						</label>
						<div>
							<strong><?php esc_html_e( 'Enable Ticket Guard Engine', 'stackboost-for-supportcandy' ); ?></strong>
							<p class="description" style="margin: 5px 0 0;"><?php esc_html_e( 'Toggle this feature on or off globally. Rules will not be enforced when disabled.', 'stackboost-for-supportcandy' ); ?></p>
						</div>
					</div>
				</div>

				<!-- Rules Card -->
				<div id="stackboost-tg-rules-card" class="stackboost-card <?php echo $is_enabled ? '' : 'stackboost-disabled-ui'; ?>" style="padding: 20px;">
					<div class="pm-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid #f0f0f0;">
						<h2 style="margin: 0;"><?php esc_html_e( 'Intake Steering Rules', 'stackboost-for-supportcandy' ); ?></h2>
						<button type="button" id="sb-tg-add-rule-btn" class="button button-primary" style="margin-right: 10px; padding: 4px 14px; position: relative; z-index: 10; cursor: pointer;"><?php esc_html_e( 'Add New Rule', 'stackboost-for-supportcandy' ); ?></button>
					</div>

					<p class="description" style="margin-bottom: 15px;">
						<?php esc_html_e( 'Configure keyword monitoring rules on text fields to steer users toward specific categories or options with real-time warnings, modals, submit button disabling, or field swaps.', 'stackboost-for-supportcandy' ); ?>
					</p>

					<div class="pm-rules-wrapper" style="margin-bottom: 20px;">
						<table class="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Rule Name', 'stackboost-for-supportcandy' ); ?></th>
									<th><?php esc_html_e( 'Monitored Field', 'stackboost-for-supportcandy' ); ?></th>
									<th><?php esc_html_e( 'Keywords / Phrases', 'stackboost-for-supportcandy' ); ?></th>
									<th><?php esc_html_e( 'Actions Enabled', 'stackboost-for-supportcandy' ); ?></th>
									<th style="width: 120px; text-align: right;"><?php esc_html_e( 'Actions', 'stackboost-for-supportcandy' ); ?></th>
								</tr>
							</thead>
							<tbody id="sb-tg-rules-tbody">
								<!-- Populated by JS -->
							</tbody>
						</table>
						<p id="sb-tg-no-rules-msg" style="display:none; text-align: center; padding: 20px; font-style: italic;">
							<?php esc_html_e( 'No intake steering rules configured yet. Click "Add New Rule" to create one.', 'stackboost-for-supportcandy' ); ?>
						</p>
					</div>

					<!-- Save Settings Footer -->
					<div style="margin-top: 25px; padding-top: 15px; border-top: 1px solid #eee; display: flex; align-items: center; gap: 15px;">
						<button type="button" id="sb-tg-main-save-btn" class="button button-primary button-large" style="padding: 6px 20px; font-size: 14px; cursor: pointer;"><?php esc_html_e( 'Save Settings', 'stackboost-for-supportcandy' ); ?></button>
						<span id="sb-tg-save-msg" style="display:none; font-weight: bold; color: green; font-size: 13px;"></span>
					</div>
				</div>
			</div>

			<!-- Rule Builder Modal -->
			<div id="sb-tg-modal-overlay" class="stackboost-modal-overlay" style="display:none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 999999;">
				<div class="stackboost-modal-box" style="background: #fff; border-radius: 6px; max-width: 700px; width: 90%; max-height: 90vh; margin: 50px auto; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.3);">
					<div class="stackboost-modal-header" style="padding: 15px 20px; background: #f8f9fa; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
						<h3 id="sb-tg-modal-title" class="stackboost-modal-title" style="margin: 0; font-size: 18px;"><?php esc_html_e( 'Configure Rule', 'stackboost-for-supportcandy' ); ?></h3>
						<button type="button" class="stackboost-modal-close sb-tg-modal-close" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">&times;</button>
					</div>

					<div class="stackboost-modal-body" style="max-height: 70vh; overflow-y: auto; padding: 20px;">
						<input type="hidden" id="sb-tg-rule-id" value="" />

						<!-- Rule Name -->
						<div class="sb-tg-field-group" style="margin-bottom: 15px;">
							<label for="sb-tg-rule-name"><strong><?php esc_html_e( 'Rule Name:', 'stackboost-for-supportcandy' ); ?></strong></label>
							<input type="text" id="sb-tg-rule-name" class="widefat" placeholder="<?php esc_attr_e( 'e.g., Steer Billing Keywords away from General Category', 'stackboost-for-supportcandy' ); ?>" />
						</div>

						<!-- Monitored Field -->
						<div class="sb-tg-field-group" style="margin-bottom: 15px;">
							<label for="sb-tg-monitored-field"><strong><?php esc_html_e( 'Monitored Field:', 'stackboost-for-supportcandy' ); ?></strong></label>
							<select id="sb-tg-monitored-field" class="widefat">
								<option value=""><?php esc_html_e( '-- Select Text Field --', 'stackboost-for-supportcandy' ); ?></option>
								<?php foreach ( $fields as $slug => $label ) : ?>
									<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>

						<!-- Keywords -->
						<div class="sb-tg-field-group" style="margin-bottom: 15px;">
							<label for="sb-tg-rule-keywords"><strong><?php esc_html_e( 'Keywords / Phrases (One per line):', 'stackboost-for-supportcandy' ); ?></strong></label>
							<textarea id="sb-tg-rule-keywords" class="widefat" rows="4" placeholder="<?php esc_attr_e( "refund\ninvoice\novercharge\ncredit card\nbilling", 'stackboost-for-supportcandy' ); ?>"></textarea>
							<p class="description" style="margin-top: 5px; font-size: 12px; color: #666;"><?php esc_html_e( 'Tip: Use & to require multiple phrases on the same line (e.g. "UC Portal & Password"). Each new line acts as an OR condition.', 'stackboost-for-supportcandy' ); ?></p>
						</div>

						<!-- Target Field & Option Selection (Permanently Visible) -->
						<div id="sb-tg-swap-container" style="margin-bottom: 15px; padding: 12px; border: 1px solid #e0e0e0; border-radius: 4px; background: #fff;">
							<label style="display: block; margin-bottom: 10px;"><strong><?php esc_html_e( 'Target Field & Option Selection:', 'stackboost-for-supportcandy' ); ?></strong></label>

							<!-- Primary Field to Swap & Target Option -->
							<div style="display: flex; gap: 20px; margin-bottom: 15px;">
								<div style="flex: 1;">
									<label for="sb-tg-swap-field"><strong><?php esc_html_e( 'Primary Field to Swap:', 'stackboost-for-supportcandy' ); ?></strong></label>
									<select id="sb-tg-swap-field" class="widefat">
										<option value=""><?php esc_html_e( '-- Select Primary Field --', 'stackboost-for-supportcandy' ); ?></option>
										<?php foreach ( $dropdowns as $slug => $d_info ) : ?>
											<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $d_info['label'] ); ?></option>
										<?php endforeach; ?>
									</select>
								</div>

								<div style="flex: 1;">
									<label for="sb-tg-swap-value"><strong><?php esc_html_e( 'Primary Target Option:', 'stackboost-for-supportcandy' ); ?></strong></label>
									<select id="sb-tg-swap-value" class="widefat">
										<option value=""><?php esc_html_e( '-- Select Target Option --', 'stackboost-for-supportcandy' ); ?></option>
									</select>
								</div>
							</div>

							<!-- Secondary Field to Swap (Dropdown/Radio/Checkbox) & Target Option -->
							<div style="display: flex; gap: 20px;">
								<div style="flex: 1;">
									<label for="sb-tg-sec-swap-field"><strong><?php esc_html_e( 'Secondary Field to Swap (Optional):', 'stackboost-for-supportcandy' ); ?></strong></label>
									<select id="sb-tg-sec-swap-field" class="widefat">
										<option value=""><?php esc_html_e( '-- Select Secondary Field --', 'stackboost-for-supportcandy' ); ?></option>
										<?php foreach ( $dropdowns as $slug => $d_info ) : ?>
											<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $d_info['label'] ); ?></option>
										<?php endforeach; ?>
									</select>
								</div>

								<div style="flex: 1;">
									<label for="sb-tg-sec-swap-value"><strong><?php esc_html_e( 'Secondary Target Option:', 'stackboost-for-supportcandy' ); ?></strong></label>
									<select id="sb-tg-sec-swap-value" class="widefat">
										<option value=""><?php esc_html_e( '-- Select Secondary Option --', 'stackboost-for-supportcandy' ); ?></option>
									</select>
								</div>
							</div>
						</div>

						<!-- Action Toggles -->
						<div class="sb-tg-field-group" style="margin-bottom: 15px; background: #f9f9f9; padding: 12px; border-radius: 4px;">
							<label><strong><?php esc_html_e( 'Action Toggles (Independently Selectable):', 'stackboost-for-supportcandy' ); ?></strong></label>
							<div style="display: flex; flex-direction: column; gap: 8px; margin-top: 8px;">
								<label><input type="checkbox" id="sb-tg-act-disable-submit" /> <?php esc_html_e( 'Disable Submit Button when matched', 'stackboost-for-supportcandy' ); ?></label>
								<label><input type="checkbox" id="sb-tg-act-show-modal" /> <?php esc_html_e( 'Display Guidance Modal Popup', 'stackboost-for-supportcandy' ); ?></label>
								<label><input type="checkbox" id="sb-tg-act-show-inline" /> <?php esc_html_e( 'Show Real-Time Inline Warning Banner', 'stackboost-for-supportcandy' ); ?></label>
								<label><input type="checkbox" id="sb-tg-act-auto-swap" /> <?php esc_html_e( 'Automatically Swap Selected Fields to Target Options', 'stackboost-for-supportcandy' ); ?></label>
							</div>
						</div>

						<!-- Submit Disabled Message (Conditional) -->
						<div id="sb-tg-submit-msg-container" style="display: none; margin-bottom: 15px; padding: 12px; border: 1px solid #e0e0e0; border-radius: 4px; background: #fff;">
							<div class="sb-tg-field-group">
								<label for="sb-tg-submit-disabled-msg"><strong><?php esc_html_e( 'Submit Disabled Message:', 'stackboost-for-supportcandy' ); ?></strong></label>
								<input type="text" id="sb-tg-submit-disabled-msg" class="widefat" placeholder="<?php esc_attr_e( 'Submit button disabled: Please review your entry or category selection.', 'stackboost-for-supportcandy' ); ?>" />
								<p class="description" style="margin-top: 5px; font-size: 12px; color: #666;"><?php esc_html_e( 'Displayed near the submit button when it is disabled by this rule.', 'stackboost-for-supportcandy' ); ?></p>
							</div>
						</div>

						<!-- Guidance Modal Customization (Conditional) -->
						<div id="sb-tg-modal-container" style="display: none; margin-bottom: 15px; padding: 12px; border: 1px solid #e0e0e0; border-radius: 4px; background: #fff;">
							<div class="sb-tg-field-group" style="margin-bottom: 15px;">
								<label for="sb-tg-modal-custom-title"><strong><?php esc_html_e( 'Guidance Modal Title:', 'stackboost-for-supportcandy' ); ?></strong></label>
								<input type="text" id="sb-tg-modal-custom-title" class="widefat" placeholder="<?php esc_attr_e( 'Looking for Billing Support?', 'stackboost-for-supportcandy' ); ?>" />
							</div>

							<div class="sb-tg-field-group">
								<label for="sb-tg-modal-custom-body"><strong><?php esc_html_e( 'Guidance Modal Message Body:', 'stackboost-for-supportcandy' ); ?></strong></label>
								<textarea id="sb-tg-modal-custom-body" class="widefat" rows="3" placeholder="<?php esc_attr_e( 'It looks like your ticket relates to billing or invoices. Switching to the Billing category ensures faster response times.', 'stackboost-for-supportcandy' ); ?>"></textarea>
								<p class="description" style="margin-top: 5px; font-size: 12px; color: #666;"><?php esc_html_e( 'Tip: You can use dynamic placeholders like {primary_field}, {primary_response}, {secondary_field}, and {secondary_response} in your title or body.', 'stackboost-for-supportcandy' ); ?></p>
							</div>
						</div>

						<!-- Inline Warning Message (Conditional) -->
						<div id="sb-tg-inline-container" style="display: none; margin-bottom: 15px; padding: 12px; border: 1px solid #e0e0e0; border-radius: 4px; background: #fff;">
							<div class="sb-tg-field-group" style="margin-bottom: 10px;">
								<label for="sb-tg-inline-level"><strong><?php esc_html_e( 'Notice Style:', 'stackboost-for-supportcandy' ); ?></strong></label>
								<select id="sb-tg-inline-level" class="widefat">
									<option value="info"><?php esc_html_e( 'Information (Green)', 'stackboost-for-supportcandy' ); ?></option>
									<option value="alert" selected><?php esc_html_e( 'Alert (Amber)', 'stackboost-for-supportcandy' ); ?></option>
									<option value="warning"><?php esc_html_e( 'Warning (Red)', 'stackboost-for-supportcandy' ); ?></option>
								</select>
							</div>

							<div class="sb-tg-field-group">
								<label for="sb-tg-inline-warning"><strong><?php esc_html_e( 'Inline Warning Message:', 'stackboost-for-supportcandy' ); ?></strong></label>
								<input type="text" id="sb-tg-inline-warning" class="widefat" placeholder="<?php esc_attr_e( 'Keywords detected: Consider selecting Billing Support for faster service.', 'stackboost-for-supportcandy' ); ?>" />
							</div>
						</div>
					</div>

					<div class="stackboost-modal-footer" style="padding: 12px 20px; background: #f8f9fa; border-top: 1px solid #eee; text-align: right; display: flex; justify-content: flex-end; gap: 10px;">
						<button type="button" class="button button-secondary sb-tg-modal-close"><?php esc_html_e( 'Cancel', 'stackboost-for-supportcandy' ); ?></button>
						<button type="button" id="sb-tg-save-rule-btn" class="button button-primary"><?php esc_html_e( 'Save Rule', 'stackboost-for-supportcandy' ); ?></button>
					</div>
				</div>
			</div>

		</div>
		<?php
	}

	/**
	 * AJAX Handler: Save Rules.
	 */
	public function ajax_save_rules() {
		check_ajax_referer( 'stackboost_admin_nonce', 'nonce' );

		if ( ! current_user_can( STACKBOOST_CAP_MANAGE_TICKET_GUARD ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'stackboost-for-supportcandy' ) ] );
		}

		$rules_json = Request::get_post( 'rules', '[]', 'raw' );
		$rules      = json_decode( wp_unslash( $rules_json ), true );
		$is_enabled = Request::get_post( 'enabled', '', 'text' ) === 'true';

		if ( ! is_array( $rules ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid rules data format.', 'stackboost-for-supportcandy' ) ] );
		}

		$result = Core::get_instance()->save_config( $rules, $is_enabled );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		wp_send_json_success( [ 'message' => __( 'Ticket Guard rules saved successfully.', 'stackboost-for-supportcandy' ) ] );
	}

	/**
	 * AJAX Handler: Get Form Options.
	 */
	public function ajax_get_form_options() {
		check_ajax_referer( 'stackboost_admin_nonce', 'nonce' );

		if ( ! current_user_can( STACKBOOST_CAP_MANAGE_TICKET_GUARD ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'stackboost-for-supportcandy' ) ] );
		}

		$data = Core::get_instance()->get_form_options();
		wp_send_json_success( $data );
	}

	/**
	 * Enqueue Frontend Script for real-time monitoring and category steering.
	 */
	public function enqueue_frontend_scripts() {
		$core = Core::get_instance();
		if ( ! $core->is_enabled() ) {
			return;
		}

		wp_register_style(
			'stackboost-tg-frontend-css',
			STACKBOOST_PLUGIN_URL . 'src/Modules/TicketGuard/assets/css/frontend-guard.css',
			[],
			STACKBOOST_VERSION
		);

		wp_register_script(
			'stackboost-tg-frontend',
			STACKBOOST_PLUGIN_URL . 'src/Modules/TicketGuard/assets/js/frontend-guard.js',
			[ 'jquery', 'stackboost-util' ],
			STACKBOOST_VERSION,
			true
		);

		wp_enqueue_style( 'stackboost-tg-frontend-css' );

		wp_localize_script( 'stackboost-tg-frontend', 'stackboostTicketGuard', [
			'enabled'   => true,
			'rules'     => array_values( $core->get_rules() ),
			'dropdowns' => $core->get_dropdown_fields_and_options(),
			'i18n'      => [
				'notice_title'    => __( 'Category Guidance', 'stackboost-for-supportcandy' ),
				'change_category' => __( 'Switch Options', 'stackboost-for-supportcandy' ),
				'proceed_anyway'  => __( 'Proceed Anyway', 'stackboost-for-supportcandy' ),
				'dismiss'         => __( 'Dismiss', 'stackboost-for-supportcandy' ),
			]
		] );

		wp_enqueue_script( 'stackboost-tg-frontend' );
	}

	/**
	 * Backend Enforcement Hook callback.
	 *
	 * @param array $ticket_data
	 * @return array
	 */
	public function enforce_backend_rules( array $ticket_data ): array {
		return Core::get_instance()->evaluate_submission_rules( $ticket_data );
	}
}
