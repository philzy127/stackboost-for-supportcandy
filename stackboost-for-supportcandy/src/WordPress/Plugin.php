<?php

namespace StackBoost\ForSupportCandy\WordPress;

use StackBoost\ForSupportCandy\WordPress\Admin\Settings;
use StackBoost\ForSupportCandy\Modules\AfterHoursNotice;
use StackBoost\ForSupportCandy\Modules\ConditionalViews;
use StackBoost\ForSupportCandy\Modules\QolEnhancements;
use StackBoost\ForSupportCandy\Modules\QueueMacro;
use StackBoost\ForSupportCandy\Modules\AfterTicketSurvey;
use StackBoost\ForSupportCandy\Modules\Directory;
use StackBoost\ForSupportCandy\Modules\DirectoryIntegration;

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
		$this->include_module_files();
		$this->load_dependencies();
		$this->init_hooks();
	}

	/**
	 * Include module files that need to be loaded on every request.
	 */
	private function include_module_files() {
		require_once \STACKBOOST_PLUGIN_DIR . 'src/Modules/DirectoryIntegration/plugin.php';
	}

	/**
	 * Load all modules and admin components.
	 * Their constructors will register their own hooks.
	 */
	private function load_dependencies() {
		// Load admin settings handler first, so menus are created.
		Settings::get_instance();

		// Load all module adapters.
		$this->modules['directory']              = Directory\WordPress::get_instance();
		$this->modules['directory_integration']  = new DirectoryIntegration\WordPress();
		$this->modules['qol_enhancements']       = QolEnhancements\WordPress::get_instance();
		$this->modules['after_hours_notice']     = AfterHoursNotice\WordPress::get_instance();
		$this->modules['conditional_views']      = ConditionalViews\WordPress::get_instance();
		$this->modules['queue_macro']            = QueueMacro\WordPress::get_instance();
		$this->modules['after_ticket_survey']    = AfterTicketSurvey\WordPress::get_instance();
	}

	/**
	 * Initialize WordPress hooks that are central to the plugin.
	 */
	private function init_hooks() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_and_localize_frontend_scripts' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
	}

	/**
	 * Enqueue and localize scripts for the frontend.
	 * This method now centralizes localization to prevent conflicts.
	 */
	public function enqueue_and_localize_frontend_scripts() {
		wp_register_script(
			'stackboost-frontend',
			\STACKBOOST_PLUGIN_URL . 'assets/js/stackboost-frontend.js',
			[ 'jquery' ],
			\STACKBOOST_VERSION,
			true
		);

		$options         = get_option( 'stackboost_settings', [] );
		$features_data   = [];

		// Gather data from QOL Enhancements module
		if ( stackboost_is_feature_active( 'qol_enhancements' ) ) {
			$qol_core                       = new QolEnhancements\Core();
			$features_data['hover_card']    = [ 'enabled' => ! empty( $options['enable_ticket_details_card'] ) ];
			$features_data['hide_empty_columns'] = [
				'enabled'       => ! empty( $options['enable_hide_empty_columns'] ),
				'hide_priority' => ! empty( $options['enable_hide_priority_column'] ),
			];
			$features_data['ticket_type_hiding'] = [
				'enabled'       => ! empty( $options['enable_ticket_type_hiding'] ),
				'field_id'      => $this->get_custom_field_id_by_name( $options['ticket_type_custom_field_name'] ?? '' ),
				'types_to_hide' => $qol_core->parse_types_to_hide( $options['ticket_types_to_hide'] ?? '' ),
			];
		}

		// Gather data from Conditional Views module
		if ( stackboost_is_feature_active( 'conditional_views' ) ) {
			$cv_core                        = new ConditionalViews\Core();
			$features_data['conditional_hiding'] = [
				'enabled' => ! empty( $options['enable_conditional_hiding'] ),
				'rules'   => $cv_core->get_processed_rules( $options['conditional_hiding_rules'] ?? [] ),
				'columns' => $this->get_supportcandy_columns(),
			];
		}

		if ( ! empty( $features_data ) ) {
			$localized_data = [
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'stackboost_frontend_nonce' ),
				'features' => $features_data,
			];
			wp_localize_script( 'stackboost-frontend', 'stackboost_settings', $localized_data );
		}

		wp_enqueue_script( 'stackboost-frontend' );
	}

	/**
	 * Enqueue scripts and styles for the admin area.
	 * @param string $hook_suffix The current admin page.
	 */
	public function enqueue_admin_scripts( string $hook_suffix ) {
		// This is a general admin script for common UI elements like rule builders.
		// Specific modules enqueue their own assets as needed (e.g., After Ticket Survey).
		$pages_with_common_script = [
			'toplevel_page_stackboost-for-supportcandy',
			'stackboost_page_stackboost-conditional-views',
			'stackboost_page_stackboost-queue-macro',
		];

		if ( in_array( $hook_suffix, $pages_with_common_script, true ) ) {
			wp_enqueue_style(
				'stackboost-admin-common',
				\STACKBOOST_PLUGIN_URL . 'assets/admin/css/stackboost-admin-common.css',
				[],
				\STACKBOOST_VERSION
			);
			wp_enqueue_script(
				'stackboost-admin-common',
				\STACKBOOST_PLUGIN_URL . 'assets/admin/js/stackboost-admin-common.js',
				[ 'jquery', 'jquery-ui-sortable' ],
				\STACKBOOST_VERSION,
				true
			);
			wp_localize_script(
				'stackboost-admin-common',
				'stackboost_admin_ajax',
				[
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'stackboost_admin_nonce' ),
				]
			);
		}
	}

	/**
	 * A utility function to get SupportCandy custom fields.
	 * @return array
	 */
	public function get_supportcandy_columns(): array {
		global $wpdb;
		$columns             = [];
		$custom_fields_table = 'wpya_psmsc_custom_fields';
		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $custom_fields_table ) ) ) {
			$custom_fields = $wpdb->get_results( "SELECT slug, name FROM `{$custom_fields_table}`", ARRAY_A );
			if ( $custom_fields ) {
				foreach ( $custom_fields as $field ) {
					$columns[ $field['slug'] ] = $field['name'];
				}
			}
		}
		asort( $columns );
		return $columns;
	}

	/**
	 * A utility function to get a custom field ID by its name.
	 * @param string $field_name The name of the custom field.
	 * @return int
	 */
	public function get_custom_field_id_by_name( string $field_name ): int {
		global $wpdb;
		if ( empty( $field_name ) ) {
			return 0;
		}
		$table_name = 'wpya_psmsc_custom_fields';
		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) !== $table_name ) {
			return 0;
		}
		$field_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM `{$table_name}` WHERE name = %s",
				$field_name
			)
		);
		return $field_id ? (int) $field_id : 0;
	}
}