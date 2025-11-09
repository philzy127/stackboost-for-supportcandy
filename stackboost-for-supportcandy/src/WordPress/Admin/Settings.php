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
		add_action( 'admin_menu', [ $this, 'reorder_admin_menu' ], 100 );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	/**
	 * Add the admin menu and submenu pages.
	 */
	public function add_admin_menu() {
		// Main Menu Page (General Settings)
		add_menu_page(
			__( 'StackBoost', 'stackboost-for-supportcandy' ),
			__( 'StackBoost', 'stackboost-for-supportcandy' ),
			'manage_options',
			'stackboost-for-supportcandy',
			[ $this, 'render_settings_page' ],
			'dashicons-superhero', // A more fitting icon
			30
		);

		// General Settings Submenu (duplicates the main menu link)
		add_submenu_page(
			'stackboost-for-supportcandy',
			__( 'General Settings', 'stackboost-for-supportcandy' ),
			__( 'General Settings', 'stackboost-for-supportcandy' ),
			'manage_options',
			'stackboost-for-supportcandy', // Slug for the general settings page
			[ $this, 'render_settings_page' ]
		);

		// Placeholder for the Ticket View Submenu, which will be registered by its own module.
		// This ensures it appears in the menu even if the module fails.
		add_submenu_page(
			'stackboost-for-supportcandy',
			__( 'Ticket View', 'stackboost-for-supportcandy' ),
			__( 'Ticket View', 'stackboost-for-supportcandy' ),
			'manage_options',
			'stackboost-ticket-view', // New slug
			[ $this, 'render_settings_page' ]
		);

		// Conditional Views Submenu
		if ( stackboost_is_feature_active( 'conditional_views' ) ) {
			add_submenu_page(
				'stackboost-for-supportcandy',
				__( 'Conditional Views', 'stackboost-for-supportcandy' ),
				__( 'Conditional Views', 'stackboost-for-supportcandy' ),
				'manage_options',
				'stackboost-conditional-views',
				[ $this, 'render_settings_page' ]
			);
		}

		// Queue Macro Submenu
		if ( stackboost_is_feature_active( 'queue_macro' ) ) {
			add_submenu_page(
				'stackboost-for-supportcandy',
				__( 'Queue Macro', 'stackboost-for-supportcandy' ),
				__( 'Queue Macro', 'stackboost-for-supportcandy' ),
				'manage_options',
				'stackboost-queue-macro',
				[ $this, 'render_settings_page' ]
			);
		}

		// After Hours Notice Submenu
		if ( stackboost_is_feature_active( 'after_hours_notice' ) ) {
			add_submenu_page(
				'stackboost-for-supportcandy',
				__( 'After-Hours Notice', 'stackboost-for-supportcandy' ),
				__( 'After-Hours Notice', 'stackboost-for-supportcandy' ),
				'manage_options',
				'stackboost-after-hours',
				[ $this, 'render_settings_page' ]
			);
		}

		// How To Use Submenu (add last)
		add_submenu_page(
			'stackboost-for-supportcandy',
			__( 'How To Use', 'stackboost-for-supportcandy' ),
			__( 'How To Use', 'stackboost-for-supportcandy' ),
			'manage_options',
			'stackboost-how-to-use',
			[ $this, 'render_how_to_use_page' ]
		);
	}

	/**
	 * Reorder the admin submenu pages for StackBoost.
	 * This runs late to ensure all modules have added their submenus.
	 */
	public function reorder_admin_menu() {
		global $submenu;
		$parent_slug = 'stackboost-for-supportcandy';

		if ( ! isset( $submenu[ $parent_slug ] ) ) {
			return;
		}

		$menu_items = $submenu[ $parent_slug ];
		$ordered_menu = [];
		$order = [
			'stackboost-for-supportcandy',  // General Settings
			'stackboost-ticket-view',       // Ticket View
			'stackboost-conditional-views', // Conditional Views
			'stackboost-queue-macro',       // Queue Macro
			'stackboost-after-hours',       // After Hours Notice
			'stackboost-directory',         // Company Directory
			'stackboost-ats',               // After Ticket Survey
		];

		// Create a map of slug => menu_item array for easy lookup
		$menu_map = [];
		foreach ( $menu_items as $item ) {
			$menu_map[ $item[2] ] = $item;
		}

		// Add items to the ordered menu according to the specified order
		foreach ( $order as $slug ) {
			if ( isset( $menu_map[ $slug ] ) ) {
				$ordered_menu[] = $menu_map[ $slug ];
				unset( $menu_map[ $slug ] ); // Remove it from the map
			}
		}

		// Add any remaining items that were not in our specific order to the end
		if ( ! empty( $menu_map ) ) {
			$ordered_menu = array_merge( $ordered_menu, array_values( $menu_map ) );
		}

		$submenu[ $parent_slug ] = $ordered_menu;
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

			<?php if ( 'stackboost-for-supportcandy' === $page_slug ) : ?>
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
	 * Register all settings groups used by the plugin.
	 * This centralizes settings registration to prevent conflicts.
	 */
	public function register_settings() {
		die('REGISTER SETTINGS HOOK FIRED');
		// Main plugin settings, used by most modules.
		register_setting( 'stackboost_settings', 'stackboost_settings', [ $this, 'sanitize_settings' ] );

		// Settings for the Directory module.
		register_setting( 'stackboost_directory_settings', 'stackboost_directory_settings', [ $this, 'sanitize_directory_settings' ] );

		// Settings for the Directory Contact Widget.
		register_setting( 'stackboost_directory_widget_settings', 'stackboost_directory_widget_settings', [ $this, 'sanitize_directory_widget_settings' ] );
	}

	/**
	 * Sanitize all settings.
	 */
	public function sanitize_settings( array $input ): array {
		error_log('[SB] sanitize_settings() START. Input: ' . print_r($input, true));

		$saved_settings = get_option( 'stackboost_settings', [] );
		if ( ! is_array( $saved_settings ) ) {
			$saved_settings = [];
		}

		$page_slug = $input['page_slug'] ?? '';
		if (empty($page_slug)) {
			error_log('[SB] sanitize_settings() WARNING: No page_slug provided in input.');
			// Do not proceed if we can't identify the page, to prevent data loss.
			return $saved_settings;
		}
		error_log("[SB] sanitize_settings() Processing for page_slug: {$page_slug}");

		$page_options = apply_filters('stackboost_settings_page_options', [
			'stackboost-for-supportcandy' => [], // All settings moved to Ticket View
			'stackboost-ticket-view' => [ 'enable_ticket_details_card', 'enable_hide_empty_columns', 'enable_hide_priority_column', 'enable_ticket_type_hiding', 'ticket_type_custom_field_name', 'ticket_types_to_hide' ],
			'stackboost-conditional-views' => [ 'enable_conditional_hiding', 'conditional_hiding_rules' ],
			'stackboost-after-hours'        => [ 'enable_after_hours_notice', 'after_hours_start', 'before_hours_end', 'include_all_weekends', 'holidays', 'after_hours_message' ],
			'stackboost-queue-macro'        => [ 'enable_queue_macro', 'queue_macro_type_field', 'queue_macro_statuses' ],
            'stackboost-ats-settings'       => [ 'ats_background_color', 'ats_ticket_question_id', 'ats_technician_question_id', 'ats_ticket_url_base' ],
		]);

		$current_page_options = $page_options[ $page_slug ] ?? [];
		if (empty($current_page_options)) {
			error_log("[SB] sanitize_settings() WARNING: No options defined for page_slug: {$page_slug}. Aborting save.");
			return $saved_settings;
		}

		foreach ( $current_page_options as $key ) {
			if ( array_key_exists( $key, $input ) ) {
				$saved_settings[ $key ] = $input[ $key ];
			} else {
				// This handles unchecking a checkbox, which means the key won't be in the input.
				if ( str_starts_with($key, 'enable_') || str_starts_with($key, 'include_') ) {
					$saved_settings[ $key ] = 0;
				} elseif ( str_ends_with($key, '_rules') || str_ends_with($key, '_statuses')) {
					// This handles cases where a repeatable field (like rules) is completely removed.
					$saved_settings[ $key ] = [];
				}
			}
		}

		error_log('[SB] sanitize_settings() END. Final saved settings: ' . print_r($saved_settings, true));
		return $saved_settings;
	}

	/**
	 * Sanitize Directory settings.
	 * This is a new callback for the centralized settings registration.
	 * @param array $input The input data.
	 * @return array The sanitized data.
	 */
	public function sanitize_directory_settings(array $input): array
	{
		return \StackBoost\ForSupportCandy\Modules\Directory\Admin\Settings::sanitize_settings($input);
	}

	/**
	 * Sanitize Directory Widget settings.
	 * This is a new callback for the centralized settings registration.
	 * @param array $input The input data.
	 * @return array The sanitized data.
	 */
	public function sanitize_directory_widget_settings(array $input): array
	{
		return \StackBoost\ForSupportCandy\Modules\Directory\Admin\TicketWidgetSettings::sanitize_widget_settings($input);
	}
}
