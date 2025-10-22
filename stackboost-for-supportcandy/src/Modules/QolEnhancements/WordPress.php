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
}
