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
        add_action( 'admin_notices', [ $this, 'display_license_notices' ] );
		add_action( 'wp_ajax_stackboost_clear_log', [ $this, 'ajax_clear_log' ] );
		add_action( 'wp_ajax_stackboost_save_settings', [ $this, 'ajax_save_settings' ] );
		add_action( 'wp_ajax_stackboost_activate_license', [ $this, 'ajax_activate_license' ] );
		add_action( 'wp_ajax_stackboost_deactivate_license', [ $this, 'ajax_deactivate_license' ] );
		add_action( 'wp_ajax_stackboost_authorize_uninstall', [ $this, 'ajax_authorize_uninstall' ] );
		add_action( 'wp_ajax_stackboost_cancel_uninstall', [ $this, 'ajax_cancel_uninstall' ] );
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
			'callback'    => [ \StackBoost\ForSupportCandy\Modules\TicketView\WordPress::get_instance(), 'render_page' ],
		];

		// 2.1 Conditional Options - Lite
		if ( stackboost_is_feature_active( 'conditional_options' ) && class_exists( 'StackBoost\ForSupportCandy\Modules\ConditionalOptions\WordPress' ) ) {
			$menu_config[] = [
				'slug'        => 'stackboost-conditional-options',
				'parent'      => 'stackboost-for-supportcandy',
				'page_title'  => __( 'Conditional Options', 'stackboost-for-supportcandy' ),
				'menu_title'  => __( 'Conditional Options', 'stackboost-for-supportcandy' ),
				'capability'  => 'manage_options',
				'callback'    => [ \StackBoost\ForSupportCandy\Modules\ConditionalOptions\WordPress::get_instance(), 'render_page' ],
			];
		}

		// 3. Date & Time Formatting (New Module) - Lite
		if ( stackboost_is_feature_active( 'date_time_formatting' ) && class_exists( 'StackBoost\ForSupportCandy\Modules\DateTimeFormatting\Admin\Page' ) ) {
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
			// Note: This module uses the central renderer, so no specific class check for the callback is strictly needed,
			// but we should ensure the module logic is present if we were instantiating it.
			// Since we just use $this->render_settings_page, it's safe as long as Settings.php exists.
			$menu_config[] = [
				'slug'        => 'stackboost-after-hours',
				'parent'      => 'stackboost-for-supportcandy',
				'page_title'  => __( 'After-Hours Notice', 'stackboost-for-supportcandy' ),
				'menu_title'  => __( 'After-Hours Notice', 'stackboost-for-supportcandy' ),
				'capability'  => 'manage_options',
				'callback'    => [ \StackBoost\ForSupportCandy\Modules\AfterHoursNotice\WordPress::get_instance(), 'render_page' ],
			];
		}

		// 4.1 Chat Bubbles - Pro
		if ( stackboost_is_feature_active( 'chat_bubbles' ) && class_exists( 'StackBoost\ForSupportCandy\Modules\ChatBubbles\WordPress' ) ) {
			$menu_config[] = [
				'slug'        => 'stackboost-chat-bubbles',
				'parent'      => 'stackboost-for-supportcandy',
				'page_title'  => __( 'Chat Bubbles', 'stackboost-for-supportcandy' ),
				'menu_title'  => __( 'Chat Bubbles', 'stackboost-for-supportcandy' ),
				'capability'  => 'manage_options',
				'callback'    => [ \StackBoost\ForSupportCandy\Modules\ChatBubbles\WordPress::get_instance(), 'render_page' ],
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
				'callback'    => [ \StackBoost\ForSupportCandy\Modules\ConditionalViews\WordPress::get_instance(), 'render_page' ],
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
				'callback'    => [ \StackBoost\ForSupportCandy\Modules\QueueMacro\WordPress::get_instance(), 'render_page' ],
			];
		}

		// 7. Unified Ticket Macro - Pro
		if ( stackboost_is_feature_active( 'unified_ticket_macro' ) && class_exists( 'StackBoost\ForSupportCandy\Modules\UnifiedTicketMacro\WordPress' ) ) {
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
		if ( stackboost_is_feature_active( 'after_ticket_survey' ) && class_exists( 'StackBoost\ForSupportCandy\Modules\AfterTicketSurvey\WordPress' ) ) {
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
		if ( stackboost_is_feature_active( 'staff_directory' ) && class_exists( 'StackBoost\ForSupportCandy\Modules\Directory\WordPress' ) ) {
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
		if ( stackboost_is_feature_active( 'onboarding_dashboard' ) && class_exists( 'StackBoost\ForSupportCandy\Modules\OnboardingDashboard\Admin\Page' ) ) {
			$menu_config[] = [
				'slug'        => 'stackboost-onboarding-dashboard',
				'parent'      => 'stackboost-for-supportcandy',
				'page_title'  => __( 'Onboarding Dashboard', 'stackboost-for-supportcandy' ),
				'menu_title'  => __( 'Onboarding', 'stackboost-for-supportcandy' ),
				'capability'  => 'manage_options',
				'callback'    => [ \StackBoost\ForSupportCandy\Modules\OnboardingDashboard\Admin\Page::class, 'render_page' ],
			];
		}

		// 11. Appearance (Themification)
		$menu_config[] = [
			'slug'        => 'stackboost-appearance',
			'parent'      => 'stackboost-for-supportcandy',
			'page_title'  => __( 'Appearance', 'stackboost-for-supportcandy' ),
			'menu_title'  => __( 'Appearance', 'stackboost-for-supportcandy' ),
			'capability'  => 'manage_options',
			'callback'    => [ \StackBoost\ForSupportCandy\Modules\Appearance\Admin\Page::class, 'render' ], // Using static call for consistency, though class is not static
		];

		// 12. Tools / Diagnostics
		$menu_config[] = [
			'slug'        => 'stackboost-tools',
			'parent'      => 'stackboost-for-supportcandy',
			'page_title'  => __( 'Diagnostics', 'stackboost-for-supportcandy' ),
			'menu_title'  => __( 'Diagnostics', 'stackboost-for-supportcandy' ),
			'capability'  => 'manage_options',
			'callback'    => [ $this, 'render_settings_page' ],
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

        // Get active theme class
        $theme_class = 'sb-theme-clean-tech'; // Default
        if ( class_exists( 'StackBoost\ForSupportCandy\Modules\Appearance\WordPress' ) ) {
            $theme_class = \StackBoost\ForSupportCandy\Modules\Appearance\WordPress::get_active_theme_class();
        }

		?>
		<!-- StackBoost Wrapper Start -->
		<!-- Theme: <?php echo esc_html( $theme_class ); ?> -->
		<div class="wrap stackboost-dashboard <?php echo esc_attr( $theme_class ); ?>">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php if ( 'stackboost-for-supportcandy' === $page_slug ) : ?>
                <div class="stackboost-dashboard-grid">
                    <!-- Card 1: System Status -->
                    <div class="stackboost-card">
                        <h2><?php esc_html_e( 'System Status', 'stackboost-for-supportcandy' ); ?></h2>
                        <div class="stackboost-status-item">
                            <span class="stackboost-status-label"><?php esc_html_e( 'StackBoost Version', 'stackboost-for-supportcandy' ); ?></span>
                            <span class="stackboost-status-value"><?php echo esc_html( STACKBOOST_VERSION ); ?></span>
                        </div>
                        <div class="stackboost-status-item">
                            <span class="stackboost-status-label"><?php esc_html_e( 'SupportCandy Status', 'stackboost-for-supportcandy' ); ?></span>
                            <?php if ( is_supportcandy_pro_active() ) : ?>
                                <span class="stackboost-status-value success"><?php esc_html_e( 'Pro Active', 'stackboost-for-supportcandy' ); ?></span>
                            <?php else : ?>
                                <span class="stackboost-status-value warning"><?php esc_html_e( 'Free Version', 'stackboost-for-supportcandy' ); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Card 2: License -->
                    <div class="stackboost-card">
                        <!-- Form wrapper is needed for options.php submission logic if we add normal fields here later,
                             but for the license AJAX buttons it's not strictly required. Keeping it for consistency. -->
                        <form action="options.php" method="post">
                            <?php
                            // Render License Settings Section specifically for the general page
                            // This outputs the "License Activation" title and the fields.
                            // The CSS will target #stackboost-license-wrapper to fit it nicely.
                            do_settings_sections( 'stackboost-for-supportcandy' );
                            ?>
                        </form>
                    </div>

                    <!-- Card 3: Resources -->
                    <div class="stackboost-card">
                        <h2><?php esc_html_e( 'Resources', 'stackboost-for-supportcandy' ); ?></h2>
                        <p><?php esc_html_e( 'For the latest documentation, changelog, and support, please visit our website.', 'stackboost-for-supportcandy' ); ?></p>
                        <a href="https://stackboost.net" target="_blank" class="stackboost-resources-btn"><?php esc_html_e( 'Visit StackBoost.net', 'stackboost-for-supportcandy' ); ?></a>
                    </div>
                </div>

				<?php
				// Feature Spotlight (Upsell Logic)
				$upsell_pool = $this->get_upsell_pool();
				if ( ! empty( $upsell_pool ) ) {
					// Encode pool for JS
					$upsell_json = wp_json_encode( $upsell_pool );
					// Determine start index from transient to maintain rotation logic
					// We map the key back to index, or default to random/0
					$transient_key = 'stackboost_upsell_rotation_v1';
					$cached_key    = get_transient( $transient_key ); // The key string, e.g., 'queue_macros'
					$start_index   = 0;

					// If transient exists, find its index in the current pool
					if ( $cached_key ) {
						$content_map = array_keys( $this->get_upsell_content() );
						$pool_keys   = [];
						// Reconstruct keys for the pool to find index
						foreach( $this->get_upsell_content() as $k => $v ) {
							foreach( $upsell_pool as $p_index => $p_data ) {
								if ( $p_data['hook'] === $v['hook'] ) { // Match by unique hook since pool is values-only
									if ( $k === $cached_key ) {
										$start_index = $p_index;
										break 2;
									}
								}
							}
						}
					} else {
						// Random start if no transient
						$start_index = array_rand( $upsell_pool );
						// Set transient for consistency (though less critical with carousel)
						// We need to find the key for this index to save it.
						// For simplicity in carousel mode, we might skip saving transient or just let it be.
					}

					?>
					<div id="stackboost-spotlight-widget" class="stackboost-feature-spotlight" style="display:none;">
						<div class="stackboost-spotlight-nav prev">
							<span class="dashicons dashicons-arrow-left-alt2"></span>
						</div>

						<div class="stackboost-spotlight-inner">
							<div class="stackboost-spotlight-icon">
								<span class="dashicons" id="sb-spot-icon"></span>
							</div>
							<div class="stackboost-spotlight-content">
								<div class="stackboost-spotlight-header">
									<h2 id="sb-spot-title"></h2>
									<span id="sb-spot-badge" class="stackboost-spotlight-badge"></span>
								</div>
								<p id="sb-spot-copy"></p>
							</div>
							<div class="stackboost-spotlight-action">
								<a href="#" target="_blank" class="button button-primary" id="sb-spot-link">
									<?php esc_html_e( 'Learn More', 'stackboost-for-supportcandy' ); ?>
								</a>
							</div>
						</div>

						<div class="stackboost-spotlight-nav next">
							<span class="dashicons dashicons-arrow-right-alt2"></span>
						</div>
					</div>

					<script>
					(function($) {
						$(document).ready(function() {
							var pool = <?php echo $upsell_json; ?>;
							var currentIndex = <?php echo (int) $start_index; ?>;
							var $widget = $('#stackboost-spotlight-widget');
							var timer = null;
							var intervalTime = 60000; // 60 seconds

							function renderCard(index) {
								if (index < 0) index = pool.length - 1;
								if (index >= pool.length) index = 0;
								currentIndex = index;

								var card = pool[currentIndex];

								// Update content
								$('#sb-spot-icon').attr('class', 'dashicons ' + card.icon);
								$('#sb-spot-title').text(card.hook);
								$('#sb-spot-copy').text(card.copy);
								$('#sb-spot-link').attr('href', card.url);

								// Update badge
								var badgeText = '';
								var badgeClass = 'stackboost-spotlight-badge';

								if (card.pool === 'business') {
									badgeText = '<?php echo esc_js( __( 'Business Feature Highlight', 'stackboost-for-supportcandy' ) ); ?>';
									badgeClass += ' business';
								} else {
									badgeText = '<?php echo esc_js( __( 'Pro Feature Highlight', 'stackboost-for-supportcandy' ) ); ?>';
									badgeClass += ' pro';
								}

								$('#sb-spot-badge').text(badgeText).attr('class', badgeClass);

								// Update border class (remove old, add new)
								$widget.removeClass('stackboost-upsell-pro stackboost-upsell-biz').addClass(card.class);

								$widget.show();
							}

							function nextCard() {
								renderCard(currentIndex + 1);
							}

							function prevCard() {
								renderCard(currentIndex - 1);
							}

							function startTimer() {
								if (timer) clearInterval(timer);
								timer = setInterval(nextCard, intervalTime);
							}

							function stopTimer() {
								if (timer) clearInterval(timer);
							}

							// Controls
							$widget.find('.next').on('click', function() {
								nextCard();
								startTimer(); // Reset timer on interaction
							});

							$widget.find('.prev').on('click', function() {
								prevCard();
								startTimer();
							});

							// Hover pause
							$widget.on('mouseenter', stopTimer).on('mouseleave', startTimer);

							// Init
							if (pool.length > 0) {
								renderCard(currentIndex);
								startTimer();
							}
						});
					})(jQuery);
					</script>
					<?php
				}
				?>

			<?php elseif ( 'stackboost-tools' === $page_slug ) : ?>
				<form action="options.php" method="post">
					<?php
					settings_fields( 'stackboost_settings' );
					echo '<input type="hidden" name="stackboost_settings[page_slug]" value="stackboost-tools">';
					?>

					<div class="stackboost-dashboard-grid">
						<!-- Card 1: Diagnostic Log -->
						<div class="stackboost-card">
							<h2><?php esc_html_e( 'Diagnostic Log', 'stackboost-for-supportcandy' ); ?></h2>
							<table class="form-table">
								<?php do_settings_fields( 'stackboost-tools', 'stackboost_tools_section' ); ?>
							</table>
						</div>

						<!-- Card 2: Data Management -->
						<div class="stackboost-card">
							<h2><?php esc_html_e( 'Data Management', 'stackboost-for-supportcandy' ); ?></h2>
							<table class="form-table">
								<?php do_settings_fields( 'stackboost-tools', 'stackboost_tools_uninstall_section' ); ?>
							</table>
						</div>
					</div>

					<?php submit_button( __( 'Save Settings', 'stackboost-for-supportcandy' ) ); ?>
				</form>

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
	 * Register the main settings group and its sanitization callback.
	 */
	public function register_settings() {
		register_setting( 'stackboost_settings', 'stackboost_settings', [ $this, 'sanitize_settings' ] );

		// Directory Module Settings
		if ( stackboost_is_feature_active( 'staff_directory' ) ) {
			// Ensure classes exist before using them in callback (though feature gate should handle this, safety first for packaging)
			if ( class_exists( 'StackBoost\ForSupportCandy\Modules\Directory\Admin\Settings' ) ) {
				register_setting( 'stackboost_directory_settings', 'stackboost_directory_settings', [ \StackBoost\ForSupportCandy\Modules\Directory\Admin\Settings::class, 'sanitize_settings' ] );
			}
			if ( class_exists( 'StackBoost\ForSupportCandy\Modules\Directory\Admin\TicketWidgetSettings' ) ) {
				register_setting( 'stackboost_directory_widget_settings', 'stackboost_directory_widget_settings', [ \StackBoost\ForSupportCandy\Modules\Directory\Admin\TicketWidgetSettings::class, 'sanitize_widget_settings' ] );
			}
		}

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

		// Uninstall Protocol Section
		add_settings_section(
			'stackboost_tools_uninstall_section',
			__( 'Data Management', 'stackboost-for-supportcandy' ),
			'__return_null',
			'stackboost-tools'
		);

		add_settings_field(
			'stackboost_uninstall_actions',
			__( 'Uninstall Protocols', 'stackboost-for-supportcandy' ),
			[ $this, 'render_uninstall_actions' ],
			'stackboost-tools',
			'stackboost_tools_uninstall_section'
		);
	}

	/**
	 * Render the Uninstall Protocol actions.
	 */
	public function render_uninstall_actions() {
		// Enqueue the JS specifically here or via a general enqueue hook.
		// Since we are in the render callback, we should enqueue it if not already done,
		// but typically enqueueing happens in 'admin_enqueue_scripts'.
		// We'll add it to 'admin_enqueue_scripts' logic or verify it's loaded.
		// For now, let's rely on the separate JS file which we will create and enqueue.

		$settings = get_option( 'stackboost_settings', [] );
		$auth_timestamp = isset( $settings['uninstall_authorized_timestamp'] ) ? (int) $settings['uninstall_authorized_timestamp'] : 0;

		// Calculate remaining time if any
		$remaining = 0;
		if ( $auth_timestamp > 0 ) {
			$remaining = 300 - ( time() - $auth_timestamp );
			if ( $remaining < 0 ) {
				$remaining = 0;
				// Auto-cleanup stale timestamp could be done here, but let's just treat it as 0.
			}
		}

		$is_authorized = ( $remaining > 0 );

		// Data attributes for JS to pick up state
		?>
		<div id="stackboost-uninstall-wrapper"
		     data-authorized="<?php echo $is_authorized ? '1' : '0'; ?>"
		     data-remaining="<?php echo esc_attr( $remaining ); ?>"
		     class="<?php echo $is_authorized ? 'authorized-mode' : 'safe-mode'; ?>">

			<div class="stackboost-uninstall-status">
				<strong><?php esc_html_e( 'Current Status:', 'stackboost-for-supportcandy' ); ?></strong>
				<span class="status-text">
					<?php echo $is_authorized ? esc_html__( 'AUTHORIZED FOR REMOVAL', 'stackboost-for-supportcandy' ) : esc_html__( 'Standard Uninstall (Safe)', 'stackboost-for-supportcandy' ); ?>
				</span>
			</div>

			<p class="description stackboost-uninstall-desc">
				<?php esc_html_e( 'By default, deleting this plugin keeps your Staff Directory and Settings in the database so you can reinstall later without losing work.', 'stackboost-for-supportcandy' ); ?>
			</p>

			<?php if ( $is_authorized ) : ?>
				<div class="stackboost-uninstall-timer-warning">
					<p><?php esc_html_e( 'This authorization expires in:', 'stackboost-for-supportcandy' ); ?> <span id="stackboost-uninstall-timer"></span></p>
					<p class="warning-text"><?php esc_html_e( 'Go to the Plugins page and delete StackBoost now to wipe all data.', 'stackboost-for-supportcandy' ); ?></p>
				</div>
			<?php endif; ?>

			<div class="stackboost-uninstall-controls">
				<button type="button" id="stackboost-authorize-uninstall-btn" class="button button-link-delete" style="<?php echo $is_authorized ? 'display:none;' : ''; ?>">
					<?php esc_html_e( 'Authorize Complete Data Removal', 'stackboost-for-supportcandy' ); ?>
				</button>

				<button type="button" id="stackboost-cancel-uninstall-btn" class="button" style="<?php echo $is_authorized ? '' : 'display:none;'; ?>">
					<?php esc_html_e( 'Cancel Authorization', 'stackboost-for-supportcandy' ); ?>
				</button>
			</div>

			<!-- Hidden Modal Markup -->
			<div id="stackboost-uninstall-modal" class="stackboost-modal" style="display:none;">
				<div class="stackboost-modal-content">
					<span class="stackboost-modal-close-button">&times;</span>
					<h2><?php esc_html_e( 'Confirm Full Data Removal?', 'stackboost-for-supportcandy' ); ?></h2>
					<div class="stackboost-modal-body">
						<p class="warning-text" style="color: #d63638; font-weight: bold;">
							<?php esc_html_e( 'Warning: This action allows the permanent deletion of:', 'stackboost-for-supportcandy' ); ?>
						</p>
						<ul style="text-align: left; display: inline-block; margin-bottom: 20px;">
							<li>- <?php esc_html_e( 'All General Settings', 'stackboost-for-supportcandy' ); ?></li>
							<li>- <?php esc_html_e( 'The entire Staff Directory (Staff, Locations, Departments)', 'stackboost-for-supportcandy' ); ?></li>
							<li>- <?php esc_html_e( 'Onboarding Dashboard progress', 'stackboost-for-supportcandy' ); ?></li>
						</ul>
						<p><?php esc_html_e( 'You will have 5 minutes to delete the plugin after clicking Yes.', 'stackboost-for-supportcandy' ); ?></p>
					</div>
					<div class="stackboost-modal-footer">
						<button type="button" class="button button-secondary stackboost-modal-close-btn"><?php esc_html_e( 'Cancel', 'stackboost-for-supportcandy' ); ?></button>
						<button type="button" id="stackboost-confirm-uninstall-auth" class="button button-primary" style="background-color: #d63638; border-color: #d63638;">
							<?php esc_html_e( 'Yes, Authorize Removal', 'stackboost-for-supportcandy' ); ?>
						</button>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

    /**
     * Render the License Key input and activation controls.
     */
    public function render_license_input() {
        $license_key = get_option( 'stackboost_license_key', '' );
        $license_tier = stackboost_get_license_tier();
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
     * Display license-related notices if an error flag is set.
     */
    public function display_license_notices() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $error_message = get_transient( 'stackboost_license_error_msg' );
        if ( ! empty( $error_message ) ) {
            ?>
            <div class="notice notice-error is-dismissible">
                <p>
                    <strong><?php esc_html_e( 'StackBoost License Alert:', 'stackboost-for-supportcandy' ); ?></strong>
                    <?php echo esc_html( $error_message ); ?>
                </p>
            </div>
            <?php
            // Optionally clear the transient on display if desired, or let it expire/be cleared by a re-check.
            // For now, we leave it until the user takes action or it expires (12 hours) to ensure visibility.
        }
    }

	/**
	 * Sanitize all settings.
	 */
	public function sanitize_settings( array $input ): array {
		if ( function_exists( 'stackboost_log' ) ) {
			stackboost_log( 'Settings::sanitize_settings called.', 'core' );
			stackboost_log( 'Input Data: ' . print_r( $input, true ), 'core' );
		}

		$saved_settings = get_option('stackboost_settings', []);
		if (!is_array($saved_settings)) {
			$saved_settings = [];
		}

		$page_slug = sanitize_key($input['page_slug'] ?? '');
		if ( function_exists( 'stackboost_log' ) ) {
			stackboost_log( "Processing page_slug: {$page_slug}", 'core' );
		}

		if (empty($page_slug)) {
			return $saved_settings;
		}

		$page_options = apply_filters('stackboost_settings_page_options', [
			'stackboost-for-supportcandy' => [],
			'stackboost-ticket-view' => [
				'enable_ticket_details_card',
				'ticket_details_view_type',
				'ticket_details_content',
				'ticket_details_history_limit',
				'ticket_details_image_handling',
				'enable_hide_empty_columns',
				'enable_hide_priority_column',
				'hide_reply_close_for_users',
				'enable_ticket_type_hiding',
				'ticket_type_custom_field_name',
				'ticket_types_to_hide',
				'enable_page_last_loaded',
				'page_last_loaded_placement',
				'page_last_loaded_label',
				'page_last_loaded_format'
			],
			'stackboost-conditional-options' => ['conditional_options_rules'], // Correctly whitelisted
			'stackboost-conditional-views' => ['enable_conditional_hiding', 'conditional_hiding_rules'],
			'stackboost-after-hours'        => ['enable_after_hours_notice', 'after_hours_in_email', 'use_sc_working_hours', 'use_sc_holidays', 'after_hours_start', 'before_hours_end', 'include_all_weekends', 'holidays', 'after_hours_message'],
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
				'enable_log_appearance', // Added Appearance Logging
				'enable_log_chat_bubbles', // Added Chat Bubbles Logging
				'enable_log_conditional_options', // Added Conditional Options Logging
			],
			// 'stackboost-date-time' removed - uses isolated option group via custom AJAX
			'stackboost-chat-bubbles' => class_exists( 'StackBoost\ForSupportCandy\Modules\ChatBubbles\Admin\Settings' ) ? \StackBoost\ForSupportCandy\Modules\ChatBubbles\Admin\Settings::get_settings_keys() : [],
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
					case 'hide_reply_close_for_users':
					case 'enable_ticket_type_hiding':
					case 'enable_conditional_hiding':
					case 'enable_queue_macro':
					case 'enable_after_hours_notice':
					case 'after_hours_in_email':
					case 'use_sc_working_hours':
					case 'use_sc_holidays':
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
					case 'enable_log_appearance':
					case 'enable_log_chat_bubbles':
					case 'enable_log_conditional_options':
					case 'chat_bubbles_enable_ticket':
					case 'chat_bubbles_enable_email':
					case 'chat_bubbles_shadow_enable':
					case 'chat_bubbles_image_box':
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
						if ( function_exists( 'stackboost_log' ) ) {
							stackboost_log( 'Sanitizing date_format_rules. Raw Value: ' . print_r( $value, true ), 'core' );
						}
						// Check if rules are present in the submission.
						if ( is_array( $value ) ) {
							$sanitized_rules = [];
							foreach ( $value as $index => $rule ) {
								if ( ! is_array( $rule ) || empty( $rule['column'] ) ) {
									if ( function_exists( 'stackboost_log' ) ) {
										stackboost_log( "Skipping rule at index {$index}: Invalid structure or missing column.", 'core' );
									}
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
							if ( function_exists( 'stackboost_log' ) ) {
								stackboost_log( 'Final Sanitized Rules: ' . print_r( $sanitized_rules, true ), 'core' );
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

					case 'conditional_options_rules':
						// Decode JSON if it's a string, or trust array if already array
						// Since sanitization usually receives the raw POST data, it might be an array if PHP handles nested inputs
						// But in our case it's saved via AJAX as a JSON string, or potentially via options.php as hidden input?
						// Our `save_rules` logic in Core.php handles saving directly.
						// BUT if we are here, it means we are saving via `stackboost_save_settings` AJAX or options.php.
						// We need to support this to ensure safety if standard save is used.

						// Note: The UI saves via `stackboost_co_save_rules` which calls Core->save_rules directly.
						// But if we whitelist it here, we ensure that if a full options save occurs, it is NOT stripped.
						// We don't need complex sanitization here if we trust the custom handler,
						// but let's implement basic struct check.
						$saved_settings[$key] = is_array($value) ? $value : []; // Basic array check
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
				if (str_starts_with($key, 'enable_') || str_starts_with($key, 'include_') || str_starts_with($key, 'use_sc_') || str_starts_with($key, 'chat_bubbles_') || $key === 'utm_enabled' || $key === 'utm_use_sc_order' || $key === 'diagnostic_log_enabled') {
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
			'enable_log_chat_bubbles'      => __( 'Chat Bubbles', 'stackboost-for-supportcandy' ),
			'enable_log_conditional_views' => __( 'Conditional Views', 'stackboost-for-supportcandy' ),
			'enable_log_queue_macro'       => __( 'Queue Macro', 'stackboost-for-supportcandy' ),
			'enable_log_utm'               => __( 'Unified Ticket Macro', 'stackboost-for-supportcandy' ),
			'enable_log_ats'               => __( 'After Ticket Survey', 'stackboost-for-supportcandy' ),
			'enable_log_directory'         => __( 'Company Directory', 'stackboost-for-supportcandy' ),
			'enable_log_onboarding'        => __( 'Onboarding Dashboard', 'stackboost-for-supportcandy' ),
			'enable_log_appearance'        => __( 'Appearance / Theme', 'stackboost-for-supportcandy' ),
			'enable_log_conditional_options' => __( 'Conditional Options', 'stackboost-for-supportcandy' ),
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
		if ( function_exists( 'stackboost_log' ) ) {
			stackboost_log( 'AJAX Save Settings Called', 'core' );
			stackboost_log( 'POST Data: ' . print_r( $_POST, true ), 'core' );
		}

		check_ajax_referer( 'stackboost_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'stackboost-for-supportcandy' ) );
		}

		if ( ! isset( $_POST['stackboost_settings'] ) || ! is_array( $_POST['stackboost_settings'] ) ) {
            if ( function_exists( 'stackboost_log' ) ) {
                stackboost_log( 'Invalid settings data structure.', 'core' );
            }
			wp_send_json_error( __( 'Invalid settings data.', 'stackboost-for-supportcandy' ) );
		}

		// Retrieve the new settings from the POST request.
		$new_settings = $_POST['stackboost_settings'];

        if ( function_exists( 'stackboost_log' ) ) {
            stackboost_log( 'New Settings to Process: ' . print_r( $new_settings, true ), 'core' );
        }

		// Get the existing settings.
		$current_settings = get_option( 'stackboost_settings', [] );

		// Call the sanitize_settings method directly.
		// NOTE: sanitize_settings() retrieves the current option from DB to merge with unchecked values.
		// But here, we are simulating a form submission. The $new_settings array should contain the
		// 'page_slug' which tells sanitize_settings() which fields to process.
		// It will merge these new values into the existing saved_settings and return the full array.
		$sanitized_settings = $this->sanitize_settings( $new_settings );

        if ( function_exists( 'stackboost_log' ) ) {
            stackboost_log( 'Final Sanitized Settings: ' . print_r( $sanitized_settings, true ), 'core' );
        }

		// Update the option.
		update_option( 'stackboost_settings', $sanitized_settings );

		wp_send_json_success( __( 'Settings saved successfully.', 'stackboost-for-supportcandy' ) );
	}

	/**
	 * Get the content definitions for the Feature Spotlight.
	 *
	 * @return array
	 */
	private function get_upsell_content(): array {
		$base_pro_url = 'https://stackboost.net/stackboost-for-supportcandy-pro/?utm_source=plugin_settings';
		$base_biz_url = 'https://stackboost.net/stackboost-for-supportcandy-business/?utm_source=plugin_settings';

		return [
			// --- Pool A: Pro Features ---
			'unified_ticket_macro' => [
				'hook'  => __( 'Unified Ticket Macro', 'stackboost-for-supportcandy' ),
				'copy'  => __( 'Revolutionize your email setup. This single macro automatically generates a dynamic table of all your ticket data, smartly hiding empty fields. It’s a game changer—users have reduced their notification templates from 58 down to just 6!', 'stackboost-for-supportcandy' ),
				'url'   => $base_pro_url,
				'icon'  => 'dashicons-editor-expand',
				'class' => 'stackboost-upsell-pro',
				'pool'  => 'pro',
			],
			'after_ticket_survey' => [
				'hook'  => __( 'Automated Feedback', 'stackboost-for-supportcandy' ),
				'copy'  => __( 'Go beyond simple star ratings. Deploy fully customizable, multi-question surveys that track deep satisfaction metrics. Gather detailed feedback on agent performance and resolution quality to drive real support improvements.', 'stackboost-for-supportcandy' ),
				'url'   => $base_pro_url,
				'icon'  => 'dashicons-feedback',
				'class' => 'stackboost-upsell-pro',
				'pool'  => 'pro',
			],
			'queue_macros' => [
				'hook'  => __( 'Customer Transparency', 'stackboost-for-supportcandy' ),
				'copy'  => __( 'Set expectations from the very first email. Automatically show customers their exact position in line instantly upon ticket creation. This transparency lets them know you\'re busy but organized, stopping "did you get this?" follow-ups before they happen.', 'stackboost-for-supportcandy' ),
				'url'   => $base_pro_url,
				'icon'  => 'dashicons-list-view',
				'class' => 'stackboost-upsell-pro',
				'pool'  => 'pro',
			],
			'chat_bubbles' => [
				'hook'  => __( 'Modern Chat Interface', 'stackboost-for-supportcandy' ),
				'copy'  => __( 'Transform your ticket view into a modern, conversational interface. With customizable chat bubbles, tails, and colors, your agents will feel like they are using a top-tier messaging app, not a legacy helpdesk.', 'stackboost-for-supportcandy' ),
				'url'   => $base_pro_url,
				'icon'  => 'dashicons-format-chat',
				'class' => 'stackboost-upsell-pro',
				'pool'  => 'pro',
			],

			// --- Pool B: Business Features ---
			'staff_directory_mapping' => [
				'hook'  => __( 'Map Your Organization', 'stackboost-for-supportcandy' ),
				'copy'  => __( 'Build your internal company directory directly within WordPress. Centralize staff contact info, locations, and departments—laying the foundation for a complete intranet while enriching your ticketing system with vital employee data.', 'stackboost-for-supportcandy' ),
				'url'   => $base_biz_url,
				'icon'  => 'dashicons-location',
				'class' => 'stackboost-upsell-biz',
				'pool'  => 'business',
			],
			'staff_directory_widget' => [
				'hook'  => __( 'Visual Contact Cards', 'stackboost-for-supportcandy' ),
				'copy'  => __( 'Enhance the agent experience with a visual contact widget right in the ticket view. Featuring staff photos for a personal touch and universal click-to-dial, it gives agents instant access to their colleagues via mobile, Teams, or Slack without ever leaving the ticket.', 'stackboost-for-supportcandy' ),
				'url'   => $base_biz_url,
				'icon'  => 'dashicons-id-alt',
				'class' => 'stackboost-upsell-biz',
				'pool'  => 'business',
			],
			'onboarding_dashboard' => [
				'hook'  => __( 'Track Agent Setup', 'stackboost-for-supportcandy' ),
				'copy'  => __( 'Master your instructor-led orientation process. Use this dedicated dashboard to visualize every trainee\'s progress in real-time. Ensure no step is missed and every new hire gets the consistent, high-quality start they deserve.', 'stackboost-for-supportcandy' ),
				'url'   => $base_biz_url,
				'icon'  => 'dashicons-chart-area',
				'class' => 'stackboost-upsell-biz',
				'pool'  => 'business',
			],
			'onboarding_compliance' => [
				'hook'  => __( 'Automate Documentation', 'stackboost-for-supportcandy' ),
				'copy'  => __( 'Ensure full compliance with zero effort. The moment an agent completes their onboarding checklist, the system automatically generates a PDF certificate and attaches it to the ticket, creating a permanent, audit-ready record of their training.', 'stackboost-for-supportcandy' ),
				'url'   => $base_biz_url,
				'icon'  => 'dashicons-awards',
				'class' => 'stackboost-upsell-biz',
				'pool'  => 'business',
			],
		];
	}

	/**
	 * Get the list of Feature Spotlight cards available for the current user.
	 *
	 * Logic:
	 * - Business Users: Show nothing (return empty).
	 * - Pro Users: Show only Business features (Pool B).
	 * - Lite Users: Show all features (Pool A + B).
	 *
	 * @return array The list of available card data objects.
	 */
	private function get_upsell_pool(): array {
		$current_tier = stackboost_get_license_tier();

		// 1. Business Users: The Pinnacle (No Upsell)
		if ( 'business' === $current_tier ) {
			return [];
		}

		// 2. Define Pools
		$content = $this->get_upsell_content();
		$pool    = [];

		foreach ( $content as $key => $data ) {
			if ( 'pro' === $current_tier ) {
				// Pro Users: Only show Business features
				if ( 'business' === $data['pool'] ) {
					$pool[] = $data;
				}
			} else {
				// Lite Users: Show everything
				$pool[] = $data;
			}
		}

		return array_values( $pool ); // Re-index array
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

        $class = 'StackBoost\ForSupportCandy\Services\LicenseManager';
        if ( ! class_exists( $class ) ) {
             wp_send_json_error( __( 'License manager service is unavailable.', 'stackboost-for-supportcandy' ) );
        }

        $instance_name = get_site_url();
        $manager = new $class();
        $response = $manager->activate_license( $license_key, $instance_name );

        if ( ! $response['success'] ) {
            wp_send_json_error( $response['error'] );
        }

        // Use Variant ID to determine tier
        $variant_id = $response['meta']['variant_id'] ?? 0;
        $tier = $manager->get_tier_from_variant( $variant_id );

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
            $class = 'StackBoost\ForSupportCandy\Services\LicenseManager';
            if ( class_exists( $class ) ) {
                $manager = new $class();
                $manager->deactivate_license( $license_key, $instance_id );
            }
        }

        // Clean up options
        delete_option( 'stackboost_license_key' );
        delete_option( 'stackboost_license_instance_id' );
        delete_option( 'stackboost_license_variant_id' );
        delete_option( 'sb_last_verified_at' );
        update_option( 'stackboost_license_tier', 'lite' );

        wp_send_json_success();
    }

	/**
	 * AJAX Handler: Authorize Clean Uninstall.
	 */
	public function ajax_authorize_uninstall() {
		check_ajax_referer( 'stackboost_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'stackboost-for-supportcandy' ) );
		}

		$settings = get_option( 'stackboost_settings', [] );
		$timestamp = time();
		$settings['uninstall_authorized_timestamp'] = $timestamp;

		// IMPORTANT: Remove the sanitization filter for this programmatic update.
		// The central sanitization function requires a 'page_slug' which isn't present here.
		// Without removing the filter, the update is silently rejected/ignored because sanitize_settings returns the old value.
		remove_filter( 'sanitize_option_stackboost_settings', [ $this, 'sanitize_settings' ] );

		if ( update_option( 'stackboost_settings', $settings ) ) {
			// Log for debugging
			if ( function_exists( 'stackboost_log' ) ) {
				stackboost_log( "Uninstall Authorized at timestamp: {$timestamp}", 'core' );
			}
			// Attempt to clear object cache if persistent cache is used, ensuring uninstall.php reads fresh data
			if ( function_exists( 'wp_cache_delete' ) ) {
				wp_cache_delete( 'stackboost_settings', 'options' );
			}
		} else {
            // Update might fail if value hasn't changed (unlikely with time()) or DB error.
             if ( function_exists( 'stackboost_log' ) ) {
				stackboost_log( "Uninstall Authorization Failed to update DB option.", 'core' );
			}
        }

		wp_send_json_success( [ 'timestamp' => $timestamp ] );
	}

	/**
	 * AJAX Handler: Cancel Clean Uninstall.
	 */
	public function ajax_cancel_uninstall() {
		check_ajax_referer( 'stackboost_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'stackboost-for-supportcandy' ) );
		}

		$settings = get_option( 'stackboost_settings', [] );
		unset( $settings['uninstall_authorized_timestamp'] );

		// Remove sanitization filter for raw update
		remove_filter( 'sanitize_option_stackboost_settings', [ $this, 'sanitize_settings' ] );

		update_option( 'stackboost_settings', $settings );

		wp_send_json_success();
	}
}
