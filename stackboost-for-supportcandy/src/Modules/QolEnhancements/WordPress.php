<?php

namespace StackBoost\ForSupportCandy\Modules\QolEnhancements;

use StackBoost\ForSupportCandy\Core\Module;
use StackBoost\ForSupportCandy\Modules\QolEnhancements\Core as QolCore;
use StackBoost\ForSupportCandy\WordPress\Plugin;

/**
 * WordPress Adapter for Quality of Life Enhancements.
 *
 * @package StackBoost\ForSupportCandy\Modules\QolEnhancements
 */
class WordPress extends Module {

	/** @var WordPress|null */
	private static ?WordPress $instance = null;

	/** @var QolCore */
	private QolCore $core;

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
		if ( ! stackboost_is_feature_active( 'qol_enhancements' ) ) {
			return;
		}

		$this->core = new QolCore();
		parent::__construct();
	}

	/**
	 * Get the slug for this module.
	 * @return string
	 */
	public function get_slug(): string {
		return 'qol_enhancements';
	}

	/**
	 * Initialize hooks.
	 */
	public function init_hooks() {
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_scripts' ] );
	}

	/**
	 * Enqueue and localize frontend scripts for QOL features.
	 */
	public function enqueue_frontend_scripts() {
		$options = get_option( 'stackboost_settings', [] );

		// Data for ticket details card (hover card)
		$features['hover_card'] = [
			'enabled' => ! empty( $options['enable_ticket_details_card'] ),
		];

		// Data for general cleanup
		$features['hide_empty_columns'] = [
			'enabled'       => ! empty( $options['enable_hide_empty_columns'] ),
			'hide_priority' => ! empty( $options['enable_hide_priority_column'] ),
		];

		// Data for ticket type hiding
        $plugin_instance = Plugin::get_instance();
		$features['ticket_type_hiding'] = [
			'enabled'       => ! empty( $options['enable_ticket_type_hiding'] ),
			'field_id'      => $plugin_instance->get_custom_field_id_by_name( $options['ticket_type_custom_field_name'] ?? '' ),
			'types_to_hide' => $this->core->parse_types_to_hide( $options['ticket_types_to_hide'] ?? '' ),
		];

		// This function will be defined in the main Plugin class to add features from all modules.
		// For now, we call it directly. A better approach would be a filter.
        $this->add_localized_features($features);
	}

    /**
     * Helper to add features to the main localized script object.
     * This prevents multiple wp_localize_script calls for the same handle.
     *
     * @param array $features
     */
    private function add_localized_features(array $features) {
        // In a more advanced implementation, this would use a filter.
        // add_filter('stackboost_frontend_script_features', function($all_features) use ($features) {
        //     return array_merge($all_features, $features);
        // });
        // For now, we'll just localize what we have.
        $plugin_instance = Plugin::get_instance();
        if (wp_script_is('stackboost-frontend', 'registered')) {
            $existing_data = wp_scripts()->get_data('stackboost-frontend', 'data');
            $existing_features = json_decode(str_replace('var stackboost_settings = ', '', rtrim($existing_data, ';')), true)['features'] ?? [];

            $localized_data = [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('wpsc_get_individual_ticket'),
                'features' => array_merge($existing_features, $features),
            ];
            wp_localize_script('stackboost-frontend', 'stackboost_settings', $localized_data);
        }
    }


	/**
	 * Register settings fields for this module.
	 */
	public function register_settings() {
		$page_slug = 'stackboost-for-supportcandy'; // The main/general settings page.

		// Section: Ticket Details Card
		add_settings_section( 'stackboost_ticket_details_card_section', __( 'Ticket Details Card', 'stackboost-for-supportcandy' ), null, $page_slug );
		add_settings_field( 'stackboost_enable_ticket_details_card', __( 'Enable Feature', 'stackboost-for-supportcandy' ), [ $this, 'render_checkbox_field' ], $page_slug, 'stackboost_ticket_details_card_section', [ 'id' => 'enable_ticket_details_card', 'desc' => 'Shows a card with ticket details on right-click.' ] );

		add_settings_section( 'stackboost_separator_1', '', [ $this, 'render_hr_separator' ], $page_slug );

		// Section: General Cleanup
		add_settings_section( 'stackboost_general_cleanup_section', __( 'General Cleanup', 'stackboost-for-supportcandy' ), null, $page_slug );
		add_settings_field( 'stackboost_enable_hide_empty_columns', __( 'Hide Empty Columns', 'stackboost-for-supportcandy' ), [ $this, 'render_checkbox_field' ], $page_slug, 'stackboost_general_cleanup_section', [ 'id' => 'enable_hide_empty_columns', 'desc' => 'Automatically hide any column in the ticket list that is completely empty.' ] );
		add_settings_field( 'stackboost_enable_hide_priority_column', __( 'Hide Priority Column', 'stackboost-for-supportcandy' ), [ $this, 'render_checkbox_field' ], $page_slug, 'stackboost_general_cleanup_section', [ 'id' => 'enable_hide_priority_column', 'desc' => 'Hides the "Priority" column if all visible tickets have a priority of "Low".' ] );

		add_settings_section( 'stackboost_separator_2', '', [ $this, 'render_hr_separator' ], $page_slug );

		// Section: Ticket Type Hiding
		add_settings_section( 'stackboost_ticket_type_hiding_section', __( 'Hide Ticket Types from Non-Agents', 'stackboost-for-supportcandy' ), [ $this, 'render_ticket_type_hiding_description' ], $page_slug );
		add_settings_field( 'stackboost_enable_ticket_type_hiding', __( 'Enable Feature', 'stackboost-for-supportcandy' ), [ $this, 'render_checkbox_field' ], $page_slug, 'stackboost_ticket_type_hiding_section', [ 'id' => 'enable_ticket_type_hiding', 'desc' => 'Hide specific ticket types from non-agent users.' ] );

		$plugin_instance = Plugin::get_instance();
		$custom_fields_choices = [];
		foreach ( $plugin_instance->get_supportcandy_columns() as $name ) {
			$custom_fields_choices[ $name ] = $name;
		}

		add_settings_field(
			'stackboost_ticket_type_custom_field_name',
			__( 'Custom Field Name', 'stackboost-for-supportcandy' ),
			[ $this, 'render_select_field' ],
			$page_slug,
			'stackboost_ticket_type_hiding_section',
			[
				'id'          => 'ticket_type_custom_field_name',
				'placeholder' => __( '-- Select a Custom Field --', 'stackboost-for-supportcandy' ),
				'choices'     => $custom_fields_choices,
				'desc'        => __( 'The custom field that represents the ticket type (e.g., "Ticket Category").', 'stackboost-for-supportcandy' ),
			]
		);

		add_settings_field( 'stackboost_ticket_types_to_hide', __( 'Ticket Types to Hide', 'stackboost-for-supportcandy' ), [ $this, 'render_textarea_field' ], $page_slug, 'stackboost_ticket_type_hiding_section', [ 'id' => 'ticket_types_to_hide', 'class' => 'regular-text', 'desc' => 'One ticket type per line. e.g., Network Access Request' ] );
	}

    /**
     * Renders a horizontal rule separator for settings pages.
     */
    public function render_hr_separator() {
        echo '<hr>';
    }

	/**
	 * Render the description for the Hide Ticket Types section.
	 */
	public function render_ticket_type_hiding_description() {
		echo '<p>' . esc_html__( 'This feature hides specified ticket categories from the dropdown menu for any user who is not an agent.', 'stackboost-for-supportcandy' ) . '</p>';
	}
}