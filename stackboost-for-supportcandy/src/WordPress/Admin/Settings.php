<?php

namespace StackBoost\ForSupportCandy\WordPress\Admin;

/**
 * Manages the admin settings pages and sanitization for the plugin.
 *
 * @package StackBoost\ForSupportCandy\WordPress\Admin
 */
class Settings {

	/** @var Settings|null */
	private static ?Settings $instance = null;

	/**
	 * Get the single instance of the class.
	 */
	public static function get_instance(): Settings {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_init', [ $this, 'handle_log_actions' ] );
		add_action( 'wp_ajax_stackboost_clear_log', [ $this, 'ajax_clear_log' ] );
	}

	/**
	 * Returns the central configuration for all admin menu items.
	 * This is the Single Source of Truth for menu ordering and structure.
	 *
	 * @return array
	 */
	public function get_menu_config(): array {
		$menu_config = [];

		// 1. General Settings (Main Menu & First Submenu)
		$menu_config[] = [
			'slug'        => 'stackboost-for-supportcandy',
			'parent'      => 'stackboost-for-supportcandy', // Self-referencing for first submenu
			'page_title'  => __( 'General Settings', 'stackboost-for-supportcandy' ),
			'menu_title'  => __( 'General Settings', 'stackboost-for-supportcandy' ),
			'capability'  => 'manage_options',
			'callback'    => [ $this, 'render_settings_page' ],
		];

		// 2. Ticket View
		$menu_config[] = [
			'slug'        => 'stackboost-ticket-view',
			'parent'      => 'stackboost-for-supportcandy',
			'page_title'  => __( 'Ticket View', 'stackboost-for-supportcandy' ),
			'menu_title'  => __( 'Ticket View', 'stackboost-for-supportcandy' ),
			'capability'  => 'manage_options',
			'callback'    => [ $this, 'render_settings_page' ],
		];

		// 3. Conditional Views
		if ( stackboost_is_feature_active( 'conditional_views' ) ) {
			$menu_config[] = [
				'slug'        => 'stackboost-conditional-views',
				'parent'      => 'stackboost-for-supportcandy',
				'page_title'  => __( 'Conditional Views', 'stackboost-for-supportcandy' ),
				'menu_title'  => __( 'Conditional Views', 'stackboost-for-supportcandy' ),
				'capability'  => 'manage_options',
				'callback'    => [ $this, 'render_settings_page' ],
			];
		}

		// 4. After Hours Notice
		if ( stackboost_is_feature_active( 'after_hours_notice' ) ) {
			$menu_config[] = [
				'slug'        => 'stackboost-after-hours',
				'parent'      => 'stackboost-for-supportcandy',
				'page_title'  => __( 'After-Hours Notice', 'stackboost-for-supportcandy' ),
				'menu_title'  => __( 'After-Hours Notice', 'stackboost-for-supportcandy' ),
				'capability'  => 'manage_options',
				'callback'    => [ $this, 'render_settings_page' ],
			];
		}

		// 5. Queue Macro
		if ( stackboost_is_feature_active( 'queue_macro' ) ) {
			$menu_config[] = [
				'slug'        => 'stackboost-queue-macro',
				'parent'      => 'stackboost-for-supportcandy',
				'page_title'  => __( 'Queue Macro', 'stackboost-for-supportcandy' ),
				'menu_title'  => __( 'Queue Macro', 'stackboost-for-supportcandy' ),
				'capability'  => 'manage_options',
				'callback'    => [ $this, 'render_settings_page' ],
			];
		}

		// 6. Unified Ticket Macro
		if ( stackboost_is_feature_active( 'unified_ticket_macro' ) ) {
			$menu_config[] = [
				'slug'        => 'stackboost-utm',
				'parent'      => 'stackboost-for-supportcandy',
				'page_title'  => __( 'Unified Ticket Macro', 'stackboost-for-supportcandy' ),
				'menu_title'  => __( 'Unified Ticket Macro', 'stackboost-for-supportcandy' ),
				'capability'  => 'manage_options',
				'callback'    => [ \StackBoost\ForSupportCandy\Modules\UnifiedTicketMacro\WordPress::get_instance(), 'render_settings_page' ],
			];
		}

		// 7. After Ticket Survey
		if ( stackboost_is_feature_active( 'after_ticket_survey' ) ) {
			$menu_config[] = [
				'slug'        => 'stackboost-ats',
				'parent'      => 'stackboost-for-supportcandy',
				'page_title'  => __( 'After Ticket Survey', 'stackboost-for-supportcandy' ),
				'menu_title'  => __( 'After Ticket Survey', 'stackboost-for-supportcandy' ),
				'capability'  => 'manage_options',
				'callback'    => [ \StackBoost\ForSupportCandy\Modules\AfterTicketSurvey\WordPress::get_instance(), 'render_admin_page' ],
			];
		}

		// 8. Company Directory
		$menu_config[] = [
			'slug'        => 'stackboost-directory',
			'parent'      => 'stackboost-for-supportcandy',
			'page_title'  => __( 'Company Directory', 'stackboost-for-supportcandy' ),
			'menu_title'  => __( 'Directory', 'stackboost-for-supportcandy' ), // Shortened for menu
			'capability'  => 'manage_options',
			'callback'    => [ \StackBoost\ForSupportCandy\Modules\Directory\WordPress::get_instance(), 'render_admin_page' ],
		];

		// 9. Onboarding Dashboard
		$menu_config[] = [
			'slug'        => 'stackboost-onboarding-dashboard',
			'parent'      => 'stackboost-for-supportcandy',
			'page_title'  => __( 'Onboarding Dashboard', 'stackboost-for-supportcandy' ),
			'menu_title'  => __( 'Onboarding', 'stackboost-for-supportcandy' ),
			'capability'  => 'manage_options',
			'callback'    => [ \StackBoost\ForSupportCandy\Modules\OnboardingDashboard\Admin\Page::class, 'render_page' ],
		];

		// 10. Tools
		$menu_config[] = [
			'slug'        => 'stackboost-tools',
			'parent'      => 'stackboost-for-supportcandy',
			'page_title'  => __( 'Tools', 'stackboost-for-supportcandy' ),
			'menu_title'  => __( 'Tools', 'stackboost-for-supportcandy' ),
			'capability'  => 'manage_options',
			'callback'    => [ $this, 'render_settings_page' ],
		];

		// 11. How To Use
		$menu_config[] = [
			'slug'        => 'stackboost-how-to-use',
			'parent'      => 'stackboost-for-supportcandy',
			'page_title'  => __( 'How To Use', 'stackboost-for-supportcandy' ),
			'menu_title'  => __( 'How To Use', 'stackboost-for-supportcandy' ),
			'capability'  => 'manage_options',
			'callback'    => [ $this, 'render_how_to_use_page' ],
		];

		return $menu_config;
	}

	/**
	 * Add the admin menu and submenu pages using the central configuration.
	 */
	public function add_admin_menu() {
		// Register the Main Parent Menu first
		add_menu_page(
			__( 'StackBoost', 'stackboost-for-supportcandy' ),
			__( 'StackBoost', 'stackboost-for-supportcandy' ),
			'manage_options',
			'stackboost-for-supportcandy',
			[ $this, 'render_settings_page' ],
			'dashicons-superhero',
			30
		);

		// Iterate through config to add submenus
		$config = $this->get_menu_config();
		foreach ( $config as $item ) {
			add_submenu_page(
				$item['parent'],
				$item['page_title'],
				$item['menu_title'],
				$item['capability'],
				$item['slug'],
				$item['callback']
			);
		}
	}

	/**
	 * Render the generic wrapper for a settings page.
	 */
	public function render_settings_page() {
		$screen = get_current_screen();
		$page_slug = $screen->base === 'toplevel_page_stackboost-for-supportcandy' ? 'stackboost-for-supportcandy' : $screen->id;
        $page_slug = str_replace(['stackboost_page_', 'toplevel_page_'], '', $page_slug);

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php if ( 'stackboost-for-supportcandy' === $page_slug ) : ?>
				<div class="notice notice-info inline">
					<p>
						<?php
						if ( is_supportcandy_pro_active() ) {
							echo '<strong>' . esc_html__( 'SupportCandy Pro detected.', 'stackboost-for-supportcandy' ) . '</strong>';
						} else {
							echo '<strong>' . esc_html__( 'SupportCandy (Free) detected.', 'stackboost-for-supportcandy' ) . '</strong>';
						}
						?>
					</p>
				</div>
				<p><?php esc_html_e( 'More settings coming soon.', 'stackboost-for-supportcandy' ); ?></p>
			<?php else : ?>
				<form action="options.php" method="post">
					<?php
					settings_fields( 'stackboost_settings' );
					echo '<input type="hidden" name="stackboost_settings[page_slug]" value="' . esc_attr( $page_slug ) . '">';
					do_settings_sections( $page_slug );
					submit_button( __( 'Save Settings', 'stackboost-for-supportcandy' ) );
					?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render the "How To Use" page content.
	 */
	public function render_how_to_use_page() {
        echo '<div class="wrap"><h1>' . esc_html__( 'How To Use StackBoost', 'stackboost-for-supportcandy' ) . '</h1>';
        echo '<p>' . esc_html__( 'Thank you for using StackBoost! Please refer to the individual settings pages for instructions on how to use each feature.', 'stackboost-for-supportcandy' ) . '</p>';
        echo '</div>';
	}

	/**
	 * Register the main settings group and its sanitization callback.
	 */
	public function register_settings() {
		register_setting( 'stackboost_settings', 'stackboost_settings', [ $this, 'sanitize_settings' ] );

		// Diagnostic Log Settings Section
		add_settings_section(
			'stackboost_tools_section',
			__( 'Diagnostic Log', 'stackboost-for-supportcandy' ),
			'__return_null',
			'stackboost-tools'
		);

		add_settings_field(
			'stackboost_diagnostic_log_enabled',
			__( 'Enable Diagnostic Log', 'stackboost-for-supportcandy' ),
			[ $this, 'render_diagnostic_log_enable_checkbox' ],
			'stackboost-tools',
			'stackboost_tools_section'
		);

		add_settings_field(
			'stackboost_diagnostic_log_actions',
			__( 'Log Actions', 'stackboost-for-supportcandy' ),
			[ $this, 'render_diagnostic_log_actions' ],
			'stackboost-tools',
			'stackboost_tools_section'
		);
	}

	/**
	 * Sanitize all settings.
	 */
	public function sanitize_settings( array $input ): array {
		// error_log('[SB] sanitize_settings() START. Input: ' . print_r($input, true));

		$saved_settings = get_option('stackboost_settings', []);
		if (!is_array($saved_settings)) {
			$saved_settings = [];
		}

		$page_slug = sanitize_key($input['page_slug'] ?? '');
		if (empty($page_slug)) {
			// error_log('[SB] sanitize_settings() WARNING: No page_slug provided in input.');
			return $saved_settings;
		}
		// error_log("[SB] sanitize_settings() Processing for page_slug: {$page_slug}");

		$page_options = apply_filters('stackboost_settings_page_options', [
			'stackboost-for-supportcandy' => [],
			'stackboost-ticket-view' => ['enable_ticket_details_card', 'enable_hide_empty_columns', 'enable_hide_priority_column', 'enable_ticket_type_hiding', 'ticket_type_custom_field_name', 'ticket_types_to_hide'],
			'stackboost-conditional-views' => ['enable_conditional_hiding', 'conditional_hiding_rules'],
			'stackboost-after-hours'        => ['enable_after_hours_notice', 'after_hours_in_email', 'after_hours_start', 'before_hours_end', 'include_all_weekends', 'holidays', 'after_hours_message'],
			'stackboost-queue-macro'        => ['enable_queue_macro', 'queue_macro_type_field', 'queue_macro_statuses'],
			'stackboost-ats-settings'       => ['ats_background_color', 'ats_ticket_question_id', 'ats_technician_question_id', 'ats_ticket_url_base'],
			'stackboost-utm'                => ['utm_enabled', 'utm_columns', 'utm_use_sc_order', 'utm_rename_rules'],
			'stackboost-tools'              => ['diagnostic_log_enabled'],
		]);

		$current_page_options = $page_options[$page_slug] ?? [];
		if (empty($current_page_options)) {
			// error_log("[SB] sanitize_settings() WARNING: No options defined for page_slug: {$page_slug}. Aborting save.");
			return $saved_settings;
		}

		foreach ($current_page_options as $key) {
			// Determine if the key exists in the input or if it's a checkbox that was unchecked.
			if (array_key_exists($key, $input)) {
				$value = $input[$key];

				// Sanitize based on the key. This is the crucial step.
				switch ($key) {
					case 'enable_ticket_details_card':
					case 'enable_hide_empty_columns':
					case 'enable_hide_priority_column':
					case 'enable_ticket_type_hiding':
					case 'enable_conditional_hiding':
					case 'enable_queue_macro':
					case 'enable_after_hours_notice':
					case 'after_hours_in_email':
					case 'include_all_weekends':
					case 'utm_enabled':
					case 'utm_use_sc_order':
						$saved_settings[$key] = intval($value);
						break;

					case 'holidays':
						$saved_settings[$key] = sanitize_textarea_field($value);
						break;

					case 'utm_columns':
						$saved_settings[$key] = is_array($value) ? array_map('sanitize_key', $value) : [];
						break;

					case 'utm_rename_rules':
						$saved_settings[$key] = is_array($value) ? $this->sanitize_rules_array($value, ['field', 'name']) : [];
						break;

					case 'after_hours_start':
					case 'before_hours_end':
					case 'ats_ticket_question_id':
					case 'ats_technician_question_id':
						$saved_settings[$key] = intval($value);
						break;

					case 'queue_macro_statuses':
						$saved_settings[$key] = is_array($value) ? array_map('intval', $value) : [];
						break;

					case 'conditional_hiding_rules':
						$saved_settings[$key] = is_array($value) ? $this->sanitize_rules_array($value, ['action', 'columns', 'condition', 'view']) : [];
						break;

					case 'after_hours_message':
						$saved_settings[$key] = wp_kses_post($value);
						break;

					case 'ats_background_color':
						$saved_settings[$key] = sanitize_hex_color($value);
						break;

					case 'ats_ticket_url_base':
						$saved_settings[$key] = esc_url_raw($value);
						break;

					default:
						$saved_settings[$key] = sanitize_text_field($value);
						break;
				}
			} else {
				// Handle unchecked checkboxes, which are not present in the form submission.
				if (str_starts_with($key, 'enable_') || str_starts_with($key, 'include_') || $key === 'utm_enabled' || $key === 'utm_use_sc_order' || $key === 'diagnostic_log_enabled') {
					$saved_settings[$key] = 0;
				} elseif (str_ends_with($key, '_rules') || str_ends_with($key, '_statuses')) {
					$saved_settings[$key] = [];
				}
			}
		}

		// error_log('[SB] sanitize_settings() END. Final sanitized settings: ' . print_r($saved_settings, true));
		return $saved_settings;
	}

	/**
	 * Render the checkbox for enabling the diagnostic log.
	 */
	public function render_diagnostic_log_enable_checkbox() {
		$options    = get_option( 'stackboost_settings', [] );
		$is_enabled = isset( $options['diagnostic_log_enabled'] ) ? (bool) $options['diagnostic_log_enabled'] : false;
		?>
		<label>
			<input type="checkbox" name="stackboost_settings[diagnostic_log_enabled]" value="1" <?php checked( $is_enabled ); ?> />
			<?php esc_html_e( 'Enable the diagnostic logging system.', 'stackboost-for-supportcandy' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'When enabled, the plugin will write detailed diagnostic information to a log file. This should only be enabled when requested by support.', 'stackboost-for-supportcandy' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the action buttons for the diagnostic log.
	 */
	public function render_diagnostic_log_actions() {
		$download_url = wp_nonce_url( add_query_arg( 'stackboost_action', 'download_log' ), 'stackboost_download_log_nonce', '_wpnonce' );
		?>
		<a href="<?php echo esc_url( $download_url ); ?>" class="button"><?php esc_html_e( 'Download Log', 'stackboost-for-supportcandy' ); ?></a>

		<button type="button" class="button button-primary" id="stackboost-clear-log-btn">
			<?php esc_html_e( 'Clear Log', 'stackboost-for-supportcandy' ); ?>
		</button>

		<p class="description">
			<?php esc_html_e( 'Download the log file for support or clear it to start fresh.', 'stackboost-for-supportcandy' ); ?>
		</p>
		<?php
	}

	/**
	 * Helper function to sanitize an array of rules, where each rule is an associative array.
	 * @param array $rules        The array of rule arrays to sanitize.
	 * @param array $allowed_keys The keys that are allowed in each rule array.
	 * @return array The sanitized array of rules.
	 */
	private function sanitize_rules_array(array $rules, array $allowed_keys): array
	{
		$sanitized_rules = [];
		foreach ($rules as $rule) {
			if (is_array($rule)) {
				$sanitized_rule = [];
				foreach ($allowed_keys as $key) {
					if (isset($rule[$key])) {
						// A basic sanitization; can be improved if more specific types are needed.
						$sanitized_rule[$key] = sanitize_text_field($rule[$key]);
					}
				}
				if (!empty($sanitized_rule)) {
					$sanitized_rules[] = $sanitized_rule;
				}
			}
		}
		return $sanitized_rules;
	}

	/**
	 * Handle the download log action.
	 * Clear log is now handled via AJAX.
	 */
	public function handle_log_actions() {
		if ( ! isset( $_GET['stackboost_action'] ) ) {
			return;
		}

		$upload_dir = wp_upload_dir();
		$log_file   = $upload_dir['basedir'] . '/stackboost-logs/debug.log';

		switch ( $_GET['stackboost_action'] ) {
			case 'download_log':
				if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'stackboost_download_log_nonce' ) ) {
					if ( file_exists( $log_file ) ) {
						header( 'Content-Description: File Transfer' );
						header( 'Content-Type: application/octet-stream' );
						header( 'Content-Disposition: attachment; filename="stackboost-debug.log"' );
						header( 'Expires: 0' );
						header( 'Cache-Control: must-revalidate' );
						header( 'Pragma: public' );
						header( 'Content-Length: ' . filesize( $log_file ) );
						readfile( $log_file );
						exit;
					} else {
						wp_die( 'Log file not found.' );
					}
				}
				break;
		}
	}

	/**
	 * AJAX handler to clear the debug log.
	 */
	public function ajax_clear_log() {
		check_ajax_referer( 'stackboost_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'stackboost-for-supportcandy' ) );
		}

		$upload_dir = wp_upload_dir();
		$log_file   = $upload_dir['basedir'] . '/stackboost-logs/debug.log';

		if ( file_exists( $log_file ) ) {
			file_put_contents( $log_file, '' );
			wp_send_json_success( __( 'Log file cleared successfully.', 'stackboost-for-supportcandy' ) );
		} else {
			wp_send_json_error( __( 'Log file not found.', 'stackboost-for-supportcandy' ) );
		}
	}
}
