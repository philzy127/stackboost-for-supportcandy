<?php


namespace StackBoost\ForSupportCandy\WordPress;

if ( ! defined( 'ABSPATH' ) ) exit;

use StackBoost\ForSupportCandy\WordPress\Admin\Settings;
use StackBoost\ForSupportCandy\Modules\AfterHoursNotice;
use StackBoost\ForSupportCandy\Modules\QolEnhancements;
use StackBoost\ForSupportCandy\Modules\TicketView;
use StackBoost\ForSupportCandy\Modules\Appearance;
use StackBoost\ForSupportCandy\Modules\ChatBubbles;
use StackBoost\ForSupportCandy\Modules\Directory\Admin\TicketWidgetSettings;
use StackBoost\ForSupportCandy\Modules\DateTimeFormatting;
use StackBoost\ForSupportCandy\Modules\ConditionalOptions;
use StackBoost\ForSupportCandy\Integration\SupportCandyRepository;

/**
 * Main plugin class.
 *
 * This class handles the plugin's lifecycle, including initialization,
 * loading of modules, and enqueuing of scripts and styles.
 *
 * @package StackBoost\ForSupportCandy\WordPress
 */
final class Plugin {

	/** @var Plugin|null */
	private static ?Plugin $instance = null;

	/** @var array */
	private array $modules = [];

	/** @var SupportCandyRepository */
	private SupportCandyRepository $sc_repository;

	/**
	 * Get the single instance of the class.
	 */
	public static function get_instance(): Plugin {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->sc_repository = new SupportCandyRepository();
		$this->load_dependencies();
		$this->init_hooks();
	}

	/**
	 * Load all modules and admin components.
	 * Their constructors will register their own hooks.
	 */
	private function load_dependencies() {
		// Load admin settings handler first, so menus are created.
		Settings::get_instance();

		// Load all module adapters.
		// Note: Some modules are always loaded (like TicketView, QolEnhancements) because they are free/lite
		// or handle their own internal checks. However, for stricter control, we can wrap them.

		// Lite Features
		if ( stackboost_is_feature_active( 'qol_enhancements' ) ) {
			$this->modules['qol_enhancements'] = QolEnhancements\WordPress::get_instance();
		}

		// Appearance Module (Always Active)
        stackboost_log( 'Loading Appearance Module...', 'appearance' );
		$this->modules['appearance'] = Appearance\Core::get_instance();
        stackboost_log( 'Appearance Module Loaded.', 'appearance' );

		// Ticket View is currently not feature-gated (Core functionality) but we can gate it if needed.
		// For now, assuming it's part of the base package.
		$this->modules['ticket_view'] = TicketView\WordPress::get_instance();

		if ( stackboost_is_feature_active( 'after_hours_notice' ) ) {
			$this->modules['after_hours_notice'] = AfterHoursNotice\WordPress::get_instance();
		}

		if ( stackboost_is_feature_active( 'date_time_formatting' ) ) {
			$this->modules['date_time_formatting'] = DateTimeFormatting\WordPress::get_instance();
		}

		// Conditional Options (Lite)
		if ( stackboost_is_feature_active( 'conditional_options' ) ) {
			$class = 'StackBoost\ForSupportCandy\Modules\ConditionalOptions\WordPress';
			if ( class_exists( $class ) ) {
				$this->modules['conditional_options'] = $class::get_instance();
			}
		}

		// Pro Features
		if ( stackboost_is_feature_active( 'conditional_views' ) ) {
			$class = 'StackBoost\ForSupportCandy\Modules\ConditionalViews\WordPress';
			if ( class_exists( $class ) ) {
				$this->modules['conditional_views'] = $class::get_instance();
			}
		}

		// Contextual Views (Revamp)
		if ( stackboost_is_feature_active( 'contextual_views' ) ) {
			$class = 'StackBoost\ForSupportCandy\Modules\ContextualViews\WordPress';
			if ( class_exists( $class ) ) {
				$this->modules['contextual_views'] = $class::get_instance();
			}
		}

		if ( stackboost_is_feature_active( 'queue_macro' ) ) {
			$class = 'StackBoost\ForSupportCandy\Modules\QueueMacro\WordPress';
			if ( class_exists( $class ) ) {
				$this->modules['queue_macro'] = $class::get_instance();
			}
		}

		if ( stackboost_is_feature_active( 'after_ticket_survey' ) ) {
			$class = 'StackBoost\ForSupportCandy\Modules\AfterTicketSurvey\WordPress';
			if ( class_exists( $class ) ) {
				$this->modules['after_ticket_survey'] = $class::get_instance();
			}
		}

		if ( stackboost_is_feature_active( 'unified_ticket_macro' ) ) {
			$class = 'StackBoost\ForSupportCandy\Modules\UnifiedTicketMacro\WordPress';
			if ( class_exists( $class ) ) {
				$this->modules['unified_ticket_macro'] = $class::get_instance();
			}
		}

		if ( stackboost_is_feature_active( 'chat_bubbles' ) ) {
			$class = 'StackBoost\ForSupportCandy\Modules\ChatBubbles\WordPress';
			if ( class_exists( $class ) ) {
				$this->modules['chat_bubbles'] = $class::get_instance();
			}
		}

		// Business Features
		if ( stackboost_is_feature_active( 'staff_directory' ) ) {
			$class = 'StackBoost\ForSupportCandy\Modules\Directory\WordPress';
			if ( class_exists( $class ) ) {
				$this->modules['directory'] = $class::get_instance();
			}
		}

		if ( stackboost_is_feature_active( 'onboarding_dashboard' ) ) {
			$class = 'StackBoost\ForSupportCandy\Modules\OnboardingDashboard\OnboardingDashboard';
			if ( class_exists( $class ) ) {
				$this->modules['onboarding_dashboard'] = $class::get_instance();
			}
		}
	}

	/**
	 * Initialize WordPress hooks that are central to the plugin.
	 */
	private function init_hooks() {
		add_action( 'init', [ $this, 'register_global_assets' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_and_localize_frontend_scripts' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
		add_action( 'admin_bar_menu', [ $this, 'add_admin_bar_menu' ], 999 );
		add_filter( 'all_plugins', [ $this, 'filter_plugin_name' ] );
	}

	/**
	 * Register global assets (scripts/styles) that are available to all modules.
	 */
	public function register_global_assets() {
		// DataTables CSS
		wp_register_style(
			'stackboost-datatables-css',
			STACKBOOST_PLUGIN_URL . 'assets/libraries/datatables/datatables.min.css',
			[],
			'2.3.6'
		);

		// DataTables JS
		wp_register_script(
			'stackboost-datatables-js',
			STACKBOOST_PLUGIN_URL . 'assets/libraries/datatables/datatables.min.js',
			[ 'jquery' ],
			'2.3.6',
			true
		);

		// Tippy.js (Tooltip Library)
		wp_register_script(
			'stackboost-popper',
			STACKBOOST_PLUGIN_URL . 'assets/libraries/popper/popper.min.js',
			[],
			'2.0',
			true
		);
		wp_register_script(
			'stackboost-tippy',
			STACKBOOST_PLUGIN_URL . 'assets/libraries/tippy/tippy.min.js',
			[ 'stackboost-popper' ],
			'6.0',
			true
		);

		// jQuery UI CSS
		wp_register_style(
			'stackboost-jquery-ui',
			STACKBOOST_PLUGIN_URL . 'assets/libraries/jquery-ui/jquery-ui.min.css',
			[],
			'1.12.1'
		);

		// StackBoost Util (Shared JS/CSS)
		wp_register_script(
			'stackboost-util',
			STACKBOOST_PLUGIN_URL . 'assets/js/stackboost-util.js',
			[ 'jquery' ],
			STACKBOOST_VERSION,
			true
		);
		wp_register_style(
			'stackboost-util',
			STACKBOOST_PLUGIN_URL . 'assets/css/stackboost-util.css',
			[],
			STACKBOOST_VERSION
		);

		// SelectWoo (Fork of Select2)
		wp_register_script(
			'stackboost-selectwoo',
			STACKBOOST_PLUGIN_URL . 'assets/libraries/selectwoo/js/selectWoo.full.min.js',
			[ 'jquery' ],
			'1.0.8',
			true
		);
		wp_register_style(
			'stackboost-selectwoo',
			STACKBOOST_PLUGIN_URL . 'assets/libraries/selectwoo/css/selectWoo.min.css',
			[],
			'1.0.8'
		);
	}

	/**
	 * Dynamically update the plugin name in the plugins list based on the active license tier.
	 *
	 * @param array $all_plugins The array of all plugins.
	 * @return array The modified array of plugins.
	 */
	public function filter_plugin_name( $all_plugins ) {
		// Identify the plugin file relative to the plugins directory.
		// Since we are in src/WordPress/Plugin.php, the main file is likely ../../stackboost-for-supportcandy.php
		// However, a more robust way is to rely on the constant defined in the main file if available,
		// or construct it. The standard basename is 'stackboost-for-supportcandy/stackboost-for-supportcandy.php'.

		$plugin_basename = plugin_basename( STACKBOOST_PLUGIN_FILE );

		if ( isset( $all_plugins[ $plugin_basename ] ) ) {
			$tier = stackboost_get_license_tier();

			if ( 'pro' === $tier ) {
				$all_plugins[ $plugin_basename ]['Name'] .= ' - Pro';
			} elseif ( 'business' === $tier ) {
				$all_plugins[ $plugin_basename ]['Name'] .= ' - Business';
			}
		}

		return $all_plugins;
	}

	/**
	 * Add a "StackBoost" menu to the admin bar.
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar The WP_Admin_Bar instance.
	 */
	public function add_admin_bar_menu( \WP_Admin_Bar $wp_admin_bar ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$main_menu_slug = 'stackboost-for-supportcandy';

		// Add the main "StackBoost" parent menu item.
		$wp_admin_bar->add_node( [
			'id'    => 'stackboost-menu',
			'title' => '<span class="ab-icon dashicons-superhero"></span>' . __( 'StackBoost', 'stackboost-for-supportcandy' ),
			'href'  => admin_url( 'admin.php?page=' . $main_menu_slug ),
		] );

		// Fetch the centralized menu config.
		$menu_config = Settings::get_instance()->get_menu_config();

		foreach ( $menu_config as $index => $item ) {
			// We skip the parent item since we added it manually above (or it could be duplicated if we are not careful).
			// The config includes the general settings page which is also the parent page.
			// The key difference is we want to use the 'menu_title' for the admin bar as it is shorter.

			$slug = $item['slug'];
			$title = $item['menu_title'];

			// Unique ID for the admin bar node
			$node_id = 'stackboost-' . sanitize_title( $title ) . '-' . $index;

			// Determine the correct href
			// For standard admin pages: admin.php?page=slug
			// For CPT archives or others: slug might be full filename or URL
			$href = str_contains( $slug, '.php' ) ? admin_url( $slug ) : admin_url( 'admin.php?page=' . $slug );

			$wp_admin_bar->add_node( [
				'id'     => $node_id,
				'parent' => 'stackboost-menu',
				'title'  => $title,
				'href'   => $href,
			] );
		}
	}

	/**
	 * Enqueue and localize scripts for the frontend.
	 * This method now centralizes localization to prevent conflicts.
	 */
	public function enqueue_and_localize_frontend_scripts() {
		wp_register_script(
			'stackboost-frontend',
			STACKBOOST_PLUGIN_URL . 'assets/js/stackboost-frontend.js',
			[ 'jquery', 'stackboost-tippy', 'stackboost-util' ],
			STACKBOOST_VERSION,
			true
		);

		// Enqueue dedicated CSS for frontend features (e.g., ticket history images)
		wp_enqueue_style(
			'stackboost-frontend-css',
			STACKBOOST_PLUGIN_URL . 'assets/css/stackboost-frontend.css',
			[],
			STACKBOOST_VERSION
		);

		$options         = get_option( 'stackboost_settings', [] );
		$features_data   = [];

		// Pass global Ticket View settings that are needed in JS
		$localized_data = [
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'wpsc_get_individual_ticket' ),
			'ticket_details_view_type' => $options['ticket_details_view_type'] ?? 'standard',
		];

		// Gather data from QOL Enhancements module
		if ( stackboost_is_feature_active( 'qol_enhancements' ) ) {
			$qol_core                       = new QolEnhancements\Core();
			$features_data['ticket_details_card']    = [ 'enabled' => ! empty( $options['enable_ticket_details_card'] ) ];
			$features_data['hide_empty_columns'] = [
				'enabled'       => ! empty( $options['enable_hide_empty_columns'] ),
				'hide_priority' => ! empty( $options['enable_hide_priority_column'] ),
			];
			// Removed legacy ticket_type_hiding logic
		}

		// Gather data from Conditional Views module
		if ( stackboost_is_feature_active( 'conditional_views' ) ) {
			// Check if ConditionalViews Core exists before usage
			if ( class_exists( 'StackBoost\ForSupportCandy\Modules\ConditionalViews\Core' ) ) {
				$cv_core = new \StackBoost\ForSupportCandy\Modules\ConditionalViews\Core();
				$features_data['conditional_hiding'] = [
					'enabled' => ! empty( $options['enable_conditional_hiding'] ),
					'rules'   => $cv_core->get_processed_rules( $options['conditional_hiding_rules'] ?? [] ),
					'columns' => $this->get_supportcandy_columns(),
				];
			}
		}

		// Gather data from Contextual Views module (Revamp)
		if ( stackboost_is_feature_active( 'contextual_views' ) ) {
			if ( class_exists( 'StackBoost\ForSupportCandy\Modules\ContextualViews\Core' ) ) {
				$ctx_core = new \StackBoost\ForSupportCandy\Modules\ContextualViews\Core();
				$features_data['contextual_views'] = [
					'enabled' => true, // Always enabled if module is active
					'rules'   => $ctx_core->get_frontend_rules(),
					'all_columns' => $this->get_supportcandy_columns(),
				];
			}
		}

		if ( ! empty( $features_data ) ) {
			$localized_data['features'] = $features_data;
		}

		// Localize unconditionally so base settings (nonce, ajax_url) are always available.
		wp_localize_script( 'stackboost-frontend', 'stackboost_settings', $localized_data );

		wp_enqueue_script( 'stackboost-frontend' );
	}

	/**
	 * Enqueue scripts and styles for the admin area.
	 * @param string $hook_suffix The current admin page.
	 */
	public function enqueue_admin_scripts( string $hook_suffix ) {
        stackboost_log( "Enqueueing admin scripts for hook: " . $hook_suffix, 'core' );

		// This is a general admin script for common UI elements like rule builders.
		// Specific modules enqueue their own assets as needed (e.g., After Ticket Survey).
		$pages_with_common_script = [
			'toplevel_page_stackboost-for-supportcandy',
			'stackboost_page_stackboost-conditional-views',
			'stackboost_page_stackboost-queue-macro',
            'stackboost_page_stackboost-onboarding-dashboard',
			'stackboost_page_stackboost-tools',
			'stackboost_page_stackboost-date-time',
			'stackboost_page_stackboost-appearance',
            // Missing Pages added:
			'stackboost_page_stackboost-chat-bubbles',
            'stackboost_page_stackboost-ticket-view',
            'stackboost_page_stackboost-after-hours',
            'stackboost_page_stackboost-utm',
            'stackboost_page_stackboost-ats',
            'stackboost_page_stackboost-directory',
            'stackboost_page_stackboost-conditional-options',
            // Robust fallback for standard hook naming convention
            'stackboost-for-supportcandy_page_stackboost-ticket-view',
            'stackboost-for-supportcandy_page_stackboost-after-hours',
            'stackboost-for-supportcandy_page_stackboost-utm',
            'stackboost-for-supportcandy_page_stackboost-ats',
            'stackboost-for-supportcandy_page_stackboost-directory',
            'stackboost-for-supportcandy_page_stackboost-conditional-views',
            'stackboost-for-supportcandy_page_stackboost-contextual-views',
            'stackboost-for-supportcandy_page_stackboost-queue-macro',
            'stackboost-for-supportcandy_page_stackboost-onboarding-dashboard',
            'stackboost-for-supportcandy_page_stackboost-tools',
            'stackboost-for-supportcandy_page_stackboost-date-time',
            'stackboost-for-supportcandy_page_stackboost-appearance',
            'stackboost-for-supportcandy_page_stackboost-chat-bubbles',
            'stackboost-for-supportcandy_page_stackboost-conditional-options',
            // Explicitly ensure the Date & Time page hook is covered for AJAX nonce
            'stackboost_page_stackboost-date-time',
		];

		// Enqueue Frontend Features in Admin (Ticket List)
		// This ensures features like the Ticket Details Card work for agents in the backend.
		if ( 'supportcandy_page_wpsc-tickets' === $hook_suffix ) {
			$this->enqueue_and_localize_frontend_scripts();
		}

		if ( in_array( $hook_suffix, $pages_with_common_script, true ) ) {
            stackboost_log( "Common scripts (and nonce) enqueued for hook: " . $hook_suffix, 'core' );

			// Enqueue General Dashboard Styles if on main page
			if ( 'toplevel_page_stackboost-for-supportcandy' === $hook_suffix ) {
				wp_enqueue_style(
					'stackboost-admin-general',
					STACKBOOST_PLUGIN_URL . 'assets/css/admin-general.css',
					[],
					STACKBOOST_VERSION . '.1' // Cache bust
				);
			}

            // Enqueue Clean Uninstall Script on Tools page
            if ( 'stackboost_page_stackboost-tools' === $hook_suffix ) {
                wp_enqueue_script(
                    'stackboost-clean-uninstall',
                    STACKBOOST_PLUGIN_URL . 'assets/js/clean-uninstall.js',
                    [ 'jquery' ],
                    STACKBOOST_VERSION,
                    true
                );

                // We need to localize the script with the generic admin nonce
                wp_localize_script(
                    'stackboost-clean-uninstall',
                    'stackboost_admin',
                    [
                        'nonce' => wp_create_nonce( 'stackboost_admin_nonce' ),
                    ]
                );
            }

			// Enqueue shared utilities (Modals, etc.)
			wp_enqueue_style( 'stackboost-util' );
			wp_enqueue_script( 'stackboost-util' );

			wp_enqueue_style(
				'stackboost-admin-tabs',
				STACKBOOST_PLUGIN_URL . 'assets/admin/css/admin-tabs.css',
				[],
				STACKBOOST_VERSION
			);

			wp_enqueue_style(
				'stackboost-admin-common',
				STACKBOOST_PLUGIN_URL . 'assets/admin/css/stackboost-admin-common.css',
				[],
				STACKBOOST_VERSION . '.1' // Cache bust
			);
			wp_enqueue_script(
				'stackboost-admin-common',
				STACKBOOST_PLUGIN_URL . 'assets/admin/js/stackboost-admin-common.js',
				[ 'jquery', 'jquery-ui-sortable', 'stackboost-util' ], // Added util dependency
				STACKBOOST_VERSION,
				true
			);
			// Check debug mode for centralized JS logging
			$options = get_option( 'stackboost_settings', [] );
			$debug_enabled = isset( $options['diagnostic_log_enabled'] ) ? (bool) $options['diagnostic_log_enabled'] : false;

			wp_localize_script(
				'stackboost-admin-common',
				'stackboost_admin_ajax',
				[
					'ajax_url'           => admin_url( 'admin-ajax.php' ),
					'nonce'              => wp_create_nonce( 'stackboost_admin_nonce' ),
					'i18n_select_option' => __( '-- Select Option --', 'stackboost-for-supportcandy' ),
					'i18n_loading'       => __( 'Loading...', 'stackboost-for-supportcandy' ),
					'debug_enabled'      => $debug_enabled,
				]
			);
		}

		// Enqueue datepicker for Staff CPT add/edit screens.
		if ( 'post-new.php' === $hook_suffix || 'post.php' === $hook_suffix ) {
			$screen = get_current_screen();
			if ( $screen && 'sb_staff_dir' === $screen->post_type ) {
				wp_enqueue_script( 'jquery-ui-datepicker' );
				wp_enqueue_style( 'stackboost-jquery-ui' );
			}
		}

	}

	/**
	 * A utility function to get SupportCandy custom fields.
	 * Results are cached for 1 hour to improve performance.
	 *
	 * @return array
	 */
	public function get_supportcandy_columns(): array {
		$cached_columns = get_transient( 'stackboost_sc_columns_cache' );
		if ( false !== $cached_columns ) {
			return $cached_columns;
		}

		$custom_fields = $this->sc_repository->get_custom_fields();
		$columns       = [];

		if ( $custom_fields ) {
			foreach ( $custom_fields as $field ) {
				// SupportCandy uses object or array depending on query mode, but get_results(ARRAY_A) returns array.
				// However, if we change repo, we should ensure consistency.
				// My new repository uses ARRAY_A.
				$columns[ $field['slug'] ] = $field['name'];
			}
		}

		// 2. Add Standard Fields (Safe Hardcoding)
		$standard_fields = [
			'status'      => __( 'Status', 'stackboost-for-supportcandy' ),
			'df_status'   => __( 'Status', 'stackboost-for-supportcandy' ),
			'category'    => __( 'Category', 'stackboost-for-supportcandy' ),
			'df_category' => __( 'Category', 'stackboost-for-supportcandy' ),
			'priority'    => __( 'Priority', 'stackboost-for-supportcandy' ),
			'df_priority' => __( 'Priority', 'stackboost-for-supportcandy' ),
		];

		foreach ( $standard_fields as $slug => $name ) {
			if ( ! isset( $columns[ $slug ] ) ) {
				$columns[ $slug ] = $name;
			}
		}

		asort( $columns );

		set_transient( 'stackboost_sc_columns_cache', $columns, HOUR_IN_SECONDS );

		return $columns;
	}

	/**
	 * A utility function to get SupportCandy statuses.
	 * Results are cached for 1 hour.
	 *
	 * @return array Associative array of [ ID => Name ]
	 */
	public function get_supportcandy_statuses(): array {
		$cached_statuses = get_transient( 'stackboost_sc_statuses_cache' );
		if ( false !== $cached_statuses ) {
			return $cached_statuses;
		}

		$results  = $this->sc_repository->get_statuses();
		$statuses = [];

		if ( $results ) {
			foreach ( $results as $status ) {
				// get_results (default OBJECT) returns objects.
				// My repository uses get_results (default) for this query, so it returns objects.
				// Wait, let me check Repository implementation.
				// In SupportCandyRepository: $wpdb->get_results(..., OBJECT) is default if not specified.
				// I specified nothing for get_statuses in repo, so it returns objects.
				$statuses[ $status->id ] = $status->name;
			}
		}

		set_transient( 'stackboost_sc_statuses_cache', $statuses, HOUR_IN_SECONDS );

		return $statuses;
	}

	/**
	 * A utility function to get a custom field ID by its name.
	 * @param string $field_name The name of the custom field.
	 * @return int
	 */
	public function get_custom_field_id_by_name( string $field_name ): int {
		return $this->sc_repository->get_custom_field_id_by_name( $field_name );
	}
}
