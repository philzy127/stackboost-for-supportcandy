<?php

namespace StackBoost\ForSupportCandy\WordPress\Admin;

use StackBoost\ForSupportCandy\Services\LicenseService;

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
		add_action( 'wp_ajax_stackboost_save_settings', [ $this, 'ajax_save_settings' ] );
		add_action( 'wp_ajax_stackboost_activate_license', [ $this, 'ajax_activate_license' ] );
		add_action( 'wp_ajax_stackboost_deactivate_license', [ $this, 'ajax_deactivate_license' ] );
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

		// 3. Date & Time Formatting (New Module) - Lite
		if ( stackboost_is_feature_active( 'date_time_formatting' ) ) {
			$menu_config[] = [
				'slug'        => 'stackboost-date-time',
				'parent'      => 'stackboost-for-supportcandy',
				'page_title'  => __( 'Date & Time Formatting', 'stackboost-for-supportcandy' ),
				'menu_title'  => __( 'Date & Time', 'stackboost-for-supportcandy' ),
				'capability'  => 'manage_options',
				'callback'    => [ \StackBoost\ForSupportCandy\Modules\DateTimeFormatting\Admin\Page::class, 'render_page' ],
			];
		}

		// 4. After Hours Notice - Lite
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

		// 5. Conditional Views - Pro
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

		// 6. Queue Macro - Pro
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

		// 7. Unified Ticket Macro - Pro
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

		// 8. After Ticket Survey - Pro
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

		// 9. Company Directory - Business
		if ( stackboost_is_feature_active( 'staff_directory' ) ) {
			$menu_config[] = [
				'slug'        => 'stackboost-directory',
				'parent'      => 'stackboost-for-supportcandy',
				'page_title'  => __( 'Company Directory', 'stackboost-for-supportcandy' ),
				'menu_title'  => __( 'Directory', 'stackboost-for-supportcandy' ), // Shortened for menu
				'capability'  => 'manage_options',
				'callback'    => [ \StackBoost\ForSupportCandy\Modules\Directory\WordPress::get_instance(), 'render_admin_page' ],
			];
		}

		// 10. Onboarding Dashboard - Business
		if ( stackboost_is_feature_active( 'onboarding_dashboard' ) ) {
			$menu_config[] = [
				'slug'        => 'stackboost-onboarding-dashboard',
				'parent'      => 'stackboost-for-supportcandy',
				'page_title'  => __( 'Onboarding Dashboard', 'stackboost-for-supportcandy' ),
				'menu_title'  => __( 'Onboarding', 'stackboost-for-supportcandy' ),
				'capability'  => 'manage_options',
				'callback'    => [ \StackBoost\ForSupportCandy\Modules\OnboardingDashboard\Admin\Page::class, 'render_page' ],
			];
		}

		// 11. Tools / Diagnostics
		$menu_config[] = [
			'slug'        => 'stackboost-tools',
			'parent'      => 'stackboost-for-supportcandy',
			'page_title'  => __( 'Diagnostics', 'stackboost-for-supportcandy' ),
			'menu_title'  => __( 'Diagnostics', 'stackboost-for-supportcandy' ),
			'capability'  => 'manage_options',
			'callback'    => [ $this, 'render_settings_page' ],
		];

		// 12. How To Use
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
            <p><?php printf( esc_html__( 'StackBoost Version: %s', 'stackboost-for-supportcandy' ), STACKBOOST_VERSION ); ?></p>

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
                <form action="options.php" method="post">
                    <?php
                    // Render License Settings Section specifically for the general page
                    do_settings_sections( 'stackboost-for-supportcandy' );
                    ?>
                </form>
				<p><?php esc_html_e( 'More general settings coming soon.', 'stackboost-for-supportcandy' ); ?></p>
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

		// Directory Module Settings
		register_setting( 'stackboost_directory_settings', 'stackboost_directory_settings', [ \StackBoost\ForSupportCandy\Modules\Directory\Admin\Settings::class, 'sanitize_settings' ] );
		register_setting( 'stackboost_directory_widget_settings', 'stackboost_directory_widget_settings', [ \StackBoost\ForSupportCandy\Modules\Directory\Admin\TicketWidgetSettings::class, 'sanitize_widget_settings' ] );

        // License Settings (General Page)
        add_settings_section(
            'stackboost_license_section',
            __( 'License Activation', 'stackboost-for-supportcandy' ),
            '__return_null',
            'stackboost-for-supportcandy'
        );

        add_settings_field(
            'stackboost_license_key',
            __( 'License Key', 'stackboost-for-supportcandy' ),
            [ $this, 'render_license_input' ],
            'stackboost-for-supportcandy',
            'stackboost_license_section'
        );

		// Diagnostic Log Settings Section
		add_settings_section(
			'stackboost_tools_section',
			__( 'Diagnostic Log', 'stackboost-for-supportcandy' ),
			'__return_null',
			'stackboost-tools'
		);

		add_settings_field(
			'stackboost_diagnostic_log_enabled',
			__( 'Master Switch', 'stackboost-for-supportcandy' ),
			[ $this, 'render_diagnostic_log_enable_checkbox' ],
			'stackboost-tools',
			'stackboost_tools_section'
		);

		add_settings_field(
			'stackboost_diagnostic_module_toggles',
			__( 'Module Logging', 'stackboost-for-supportcandy' ),
			[ $this, 'render_diagnostic_module_toggles' ],
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
     * Render the License Key input and activation controls.
     */
    public function render_license_input() {
        $license_key = get_option( 'stackboost_license_key', '' );
        $license_tier = get_option( 'stackboost_license_tier', 'lite' );
        $is_active = ! empty( $license_key );

        ?>
        <div id="stackboost-license-wrapper">
            <?php if ( $is_active ) : ?>
                <div class="stackboost-license-status" style="margin-bottom: 10px;">
                    <span class="dashicons dashicons-yes-alt" style="color: green;"></span>
                    <strong><?php esc_html_e( 'Active', 'stackboost-for-supportcandy' ); ?></strong>
                    <span style="color: #666;">(<?php echo esc_html( ucfirst( $license_tier ) ); ?> Plan)</span>
                </div>
                <input type="password" value="<?php echo esc_attr( $license_key ); ?>" class="regular-text" readonly disabled />
                <button type="button" id="stackboost-deactivate-license" class="button"><?php esc_html_e( 'Deactivate', 'stackboost-for-supportcandy' ); ?></button>
            <?php else : ?>
                <input type="text" id="stackboost-license-key" class="regular-text" placeholder="<?php esc_attr_e( 'Enter your license key', 'stackboost-for-supportcandy' ); ?>" />
                <button type="button" id="stackboost-activate-license" class="button button-primary"><?php esc_html_e( 'Activate', 'stackboost-for-supportcandy' ); ?></button>
            <?php endif; ?>
            <p class="description" id="stackboost-license-message"></p>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Activate License
            $('#stackboost-activate-license').on('click', function() {
                var btn = $(this);
                var key = $('#stackboost-license-key').val().trim();
                var msg = $('#stackboost-license-message');

                if (!key) {
                    msg.css('color', 'red').text('<?php esc_html_e( 'Please enter a license key.', 'stackboost-for-supportcandy' ); ?>');
                    return;
                }

                btn.prop('disabled', true).text('<?php esc_html_e( 'Activating...', 'stackboost-for-supportcandy' ); ?>');
                msg.text('');

                $.post(ajaxurl, {
                    action: 'stackboost_activate_license',
                    license_key: key,
                    nonce: '<?php echo wp_create_nonce( 'stackboost_license_nonce' ); ?>'
                }, function(response) {
                    if (response.success) {
                        msg.css('color', 'green').text('<?php esc_html_e( 'License activated successfully! Reloading...', 'stackboost-for-supportcandy' ); ?>');
                        setTimeout(function() { location.reload(); }, 1500);
                    } else {
                        btn.prop('disabled', false).text('<?php esc_html_e( 'Activate', 'stackboost-for-supportcandy' ); ?>');
                        msg.css('color', 'red').text(response.data);
                    }
                });
            });

            // Deactivate License
            $('#stackboost-deactivate-license').on('click', function() {
                if (!confirm('<?php esc_html_e( 'Are you sure you want to deactivate this license?', 'stackboost-for-supportcandy' ); ?>')) {
                    return;
                }

                var btn = $(this);
                var msg = $('#stackboost-license-message');

                btn.prop('disabled', true).text('<?php esc_html_e( 'Deactivating...', 'stackboost-for-supportcandy' ); ?>');

                $.post(ajaxurl, {
                    action: 'stackboost_deactivate_license',
                    nonce: '<?php echo wp_create_nonce( 'stackboost_license_nonce' ); ?>'
                }, function(response) {
                    if (response.success) {
                        msg.css('color', 'green').text('<?php esc_html_e( 'License deactivated. Reloading...', 'stackboost-for-supportcandy' ); ?>');
                        setTimeout(function() { location.reload(); }, 1500);
                    } else {
                        btn.prop('disabled', false).text('<?php esc_html_e( 'Deactivate', 'stackboost-for-supportcandy' ); ?>');
                        msg.css('color', 'red').text(response.data);
                    }
                });
            });
        });
        </script>
        <?php
    }

	/**
	 * Sanitize all settings.
	 */
	public function sanitize_settings( array $input ): array {
		$saved_settings = get_option('stackboost_settings', []);
		if (!is_array($saved_settings)) {
			$saved_settings = [];
		}

		$page_slug = sanitize_key($input['page_slug'] ?? '');
		if (empty($page_slug)) {
			return $saved_settings;
		}

		$page_options = apply_filters('stackboost_settings_page_options', [
			'stackboost-for-supportcandy' => [],
			'stackboost-ticket-view' => ['enable_ticket_details_card', 'enable_hide_empty_columns', 'enable_hide_priority_column', 'enable_ticket_type_hiding', 'ticket_type_custom_field_name', 'ticket_types_to_hide', 'enable_page_last_loaded', 'page_last_loaded_placement', 'page_last_loaded_label', 'page_last_loaded_format'],
			'stackboost-conditional-views' => ['enable_conditional_hiding', 'conditional_hiding_rules'],
			'stackboost-after-hours'        => ['enable_after_hours_notice', 'after_hours_in_email', 'after_hours_start', 'before_hours_end', 'include_all_weekends', 'holidays', 'after_hours_message'],
			'stackboost-queue-macro'        => ['enable_queue_macro', 'queue_macro_type_field', 'queue_macro_statuses'],
			'stackboost-ats-settings'       => ['ats_background_color', 'ats_ticket_question_id', 'ats_technician_question_id', 'ats_ticket_url_base'],
			'stackboost-utm'                => ['utm_enabled', 'utm_columns', 'utm_use_sc_order', 'utm_rename_rules'],
			'stackboost-tools'              => [
				'diagnostic_log_enabled',
				'enable_log_general',
				'enable_log_ticket_view',
				'enable_log_date_time',
				'enable_log_after_hours',
				'enable_log_conditional_views',
				'enable_log_queue_macro',
				'enable_log_utm',
				'enable_log_ats',
				'enable_log_directory',
				'enable_log_onboarding',
			],
			'stackboost-date-time'          => ['enable_date_time_formatting', 'date_format_rules'],
		]);

		$current_page_options = $page_options[$page_slug] ?? [];
		if (empty($current_page_options)) {
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
					case 'enable_date_time_formatting':
					case 'enable_page_last_loaded':
					// Logging toggles
					case 'enable_log_general':
					case 'enable_log_ticket_view':
					case 'enable_log_date_time':
					case 'enable_log_after_hours':
					case 'enable_log_conditional_views':
					case 'enable_log_queue_macro':
					case 'enable_log_utm':
					case 'enable_log_ats':
					case 'enable_log_directory':
					case 'enable_log_onboarding':
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

					case 'date_format_rules':
						// Check if rules are present in the submission.
						if ( is_array( $value ) ) {
							$sanitized_rules = [];
							foreach ( $value as $rule ) {
								if ( ! is_array( $rule ) || empty( $rule['column'] ) ) {
									continue;
								}
								$sanitized_rule                   = [];
								$sanitized_rule['column']         = sanitize_text_field( $rule['column'] );
								$sanitized_rule['format_type']    = in_array( $rule['format_type'], [ 'default', 'date_only', 'time_only', 'date_and_time', 'custom' ], true ) ? $rule['format_type'] : 'default';
								$sanitized_rule['custom_format']  = sanitize_text_field( $rule['custom_format'] );
								$sanitized_rule['use_long_date']    = ! empty( $rule['use_long_date'] ) ? 1 : 0;
								$sanitized_rule['show_day_of_week'] = ! empty( $rule['show_day_of_week'] ) ? 1 : 0;
								$sanitized_rules[]              = $sanitized_rule;
							}
							$saved_settings[$key] = $sanitized_rules;
						} else {
							// If value is not an array but exists, it might be empty (deleted all rules)
							$saved_settings[$key] = [];
						}
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

					case 'ticket_types_to_hide':
						$saved_settings[$key] = sanitize_textarea_field($value);
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
			<?php esc_html_e( 'Enable the diagnostic logging system (Master Switch).', 'stackboost-for-supportcandy' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'When enabled, the plugin will write detailed diagnostic information to a log file. Individual modules must also be enabled below.', 'stackboost-for-supportcandy' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the individual module logging toggles.
	 */
	public function render_diagnostic_module_toggles() {
		$options = get_option( 'stackboost_settings', [] );

		$modules = [
			'enable_log_general'           => __( 'General Settings', 'stackboost-for-supportcandy' ),
			'enable_log_ticket_view'       => __( 'Ticket View', 'stackboost-for-supportcandy' ),
			'enable_log_date_time'         => __( 'Date & Time Formatting', 'stackboost-for-supportcandy' ),
			'enable_log_after_hours'       => __( 'After-Hours Notice', 'stackboost-for-supportcandy' ),
			'enable_log_conditional_views' => __( 'Conditional Views', 'stackboost-for-supportcandy' ),
			'enable_log_queue_macro'       => __( 'Queue Macro', 'stackboost-for-supportcandy' ),
			'enable_log_utm'               => __( 'Unified Ticket Macro', 'stackboost-for-supportcandy' ),
			'enable_log_ats'               => __( 'After Ticket Survey', 'stackboost-for-supportcandy' ),
			'enable_log_directory'         => __( 'Company Directory', 'stackboost-for-supportcandy' ),
			'enable_log_onboarding'        => __( 'Onboarding Dashboard', 'stackboost-for-supportcandy' ),
		];

		foreach ( $modules as $key => $label ) {
			$is_enabled = ! empty( $options[ $key ] );
			?>
			<label style="display: block; margin-bottom: 5px;">
				<input type="checkbox" name="stackboost_settings[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( $is_enabled ); ?> />
				<?php echo esc_html( $label ); ?>
			</label>
			<?php
		}
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

	/**
	 * AJAX handler to save settings via the central sanitizer.
	 */
	public function ajax_save_settings() {
		check_ajax_referer( 'stackboost_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'stackboost-for-supportcandy' ) );
		}

		if ( ! isset( $_POST['stackboost_settings'] ) || ! is_array( $_POST['stackboost_settings'] ) ) {
			wp_send_json_error( __( 'Invalid settings data.', 'stackboost-for-supportcandy' ) );
		}

		// Retrieve the new settings from the POST request.
		$new_settings = $_POST['stackboost_settings'];

		// Get the existing settings.
		$current_settings = get_option( 'stackboost_settings', [] );

		// Call the sanitize_settings method directly.
		// NOTE: sanitize_settings() retrieves the current option from DB to merge with unchecked values.
		// But here, we are simulating a form submission. The $new_settings array should contain the
		// 'page_slug' which tells sanitize_settings() which fields to process.
		// It will merge these new values into the existing saved_settings and return the full array.
		$sanitized_settings = $this->sanitize_settings( $new_settings );

		// Update the option.
		update_option( 'stackboost_settings', $sanitized_settings );

		wp_send_json_success( __( 'Settings saved successfully.', 'stackboost-for-supportcandy' ) );
	}

    /**
     * AJAX handler to activate a license.
     */
    public function ajax_activate_license() {
        check_ajax_referer( 'stackboost_license_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'stackboost-for-supportcandy' ) );
        }

        $license_key = sanitize_text_field( $_POST['license_key'] ?? '' );
        if ( empty( $license_key ) ) {
            wp_send_json_error( __( 'Missing license key.', 'stackboost-for-supportcandy' ) );
        }

        $instance_name = get_site_url();
        $service = new LicenseService();
        $response = $service->activate_license( $license_key, $instance_name );

        if ( ! $response['success'] ) {
            wp_send_json_error( $response['error'] );
        }

        // Mapping Logic: Product Variant -> Internal Tier
        // Expected strings: "StackBoost Pro", "StackBoost Business"
        // Default to "lite" if no match (though valid license usually implies at least pro).
        $tier = 'lite';
        $variant = $response['meta']['variant_name'] ?? '';

        if ( stripos( $variant, 'Business' ) !== false ) {
            $tier = 'business';
        } elseif ( stripos( $variant, 'Pro' ) !== false ) {
            $tier = 'pro';
        }

        update_option( 'stackboost_license_key', $license_key );
        update_option( 'stackboost_license_instance_id', $response['instance']['id'] ?? '' );
        update_option( 'stackboost_license_tier', $tier );

        wp_send_json_success();
    }

    /**
     * AJAX handler to deactivate a license.
     */
    public function ajax_deactivate_license() {
        check_ajax_referer( 'stackboost_license_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'stackboost-for-supportcandy' ) );
        }

        $license_key = get_option( 'stackboost_license_key', '' );
        $instance_id = get_option( 'stackboost_license_instance_id', '' );

        if ( ! empty( $license_key ) && ! empty( $instance_id ) ) {
            $service = new LicenseService();
            $service->deactivate_license( $license_key, $instance_id );
        }

        // Clean up options
        delete_option( 'stackboost_license_key' );
        delete_option( 'stackboost_license_instance_id' );
        update_option( 'stackboost_license_tier', 'lite' );

        wp_send_json_success();
    }
}
