<?php

namespace StackBoost\ForSupportCandy\Modules\AfterTicketSurvey;

use StackBoost\ForSupportCandy\Core\Module;

/**
 * WordPress Adapter for the After Ticket Survey module.
 *
 * Orchestrates the initialization of all components of this module.
 *
 * @package StackBoost\ForSupportCandy\Modules\AfterTicketSurvey
 */
class WordPress extends Module {

	/** @var WordPress|null */
	private static ?WordPress $instance = null;

	/** @var Install */
	private Install $install;

	/** @var Shortcode */
	private Shortcode $shortcode;

	/** @var AdminController */
	private AdminController $admin;

	/** @var Ajax */
	private Ajax $ajax;

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
		if ( ! stackboost_is_feature_active( 'after_ticket_survey' ) ) {
			return;
		}

		$this->install   = new Install();
		$this->shortcode = new Shortcode();
		$this->admin     = new AdminController();
		$this->ajax      = new Ajax();

		parent::__construct();
	}

	/**
	 * Get the slug for this module.
	 * @return string
	 */
	public function get_slug(): string {
		return 'after_ticket_survey';
	}

	/**
	 * Initialize hooks.
	 */
	public function init_hooks() {
		// Installation and DB checks
		register_activation_hook( STACKBOOST_PLUGIN_FILE, [ $this->install, 'run_install' ] );
		add_action( 'plugins_loaded', [ $this->install, 'check_db_version' ] );

		// Admin UI
		add_action( 'admin_menu', [ $this->admin, 'add_admin_menu' ], 99 );
        add_action( 'admin_post_stackboost_ats_admin_actions', [ $this->admin, 'handle_admin_post' ] );
        add_action( 'admin_notices', [ $this->admin, 'display_admin_notices' ] );

		// Frontend Shortcode
		add_shortcode( 'stackboost_after_ticket_survey', [ $this->shortcode, 'render_shortcode' ] );

		// AJAX handlers
		add_action( 'wp_ajax_stackboost_ats_update_report_heading', [ $this->ajax, 'update_report_heading' ] );

		// Settings registration
		add_action( 'admin_init', [ $this, 'register_settings' ] );

        // Asset Enqueueing
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
	}

    /**
     * Enqueue frontend styles and scripts.
     */
    public function enqueue_frontend_assets() {
        global $post;
        if ( is_a($post, 'WP_Post') && has_shortcode( $post->post_content, 'stackboost_after_ticket_survey' ) ) {
            wp_enqueue_style( 'stackboost-ats-frontend', STACKBOOST_PLUGIN_URL . 'assets/css/stackboost-ats-frontend.css', [], STACKBOOST_VERSION );
            $options = get_option( 'stackboost_settings' );
            $bg_color = ! empty( $options['ats_background_color'] ) ? $options['ats_background_color'] : '#f0f0f0';
            wp_add_inline_style( 'stackboost-ats-frontend', 'body { background-color: ' . esc_attr( $bg_color ) . ' !important; }' );
        }
    }

    /**
     * Enqueue admin styles and scripts.
     * @param string $hook_suffix
     */
    public function enqueue_admin_assets(string $hook_suffix) {
        if ( 'stackboost-for-supportcandy_page_stackboost-ats' !== $hook_suffix ) {
            return;
        }
        wp_enqueue_style( 'stackboost-ats-admin', STACKBOOST_PLUGIN_URL . 'assets/admin/css/stackboost-ats-admin.css', [], STACKBOOST_VERSION );

        $current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'main';
        if ( 'settings' === $current_tab ) {
            wp_enqueue_style( 'wp-color-picker' );
            wp_enqueue_script( 'stackboost-ats-color-picker', STACKBOOST_PLUGIN_URL . 'assets/admin/js/stackboost-ats-color-picker.js', [ 'wp-color-picker' ], STACKBOOST_VERSION, true );
        }
        if ( 'results' === $current_tab ) {
            wp_enqueue_script( 'stackboost-ats-results-modal', STACKBOOST_PLUGIN_URL . 'assets/admin/js/stackboost-ats-results-modal.js', ['jquery'], STACKBOOST_VERSION, true );
            wp_localize_script(
                'stackboost-ats-results-modal',
                'stackboost_ats_modal_ajax',
                [
                    'ajax_url' => admin_url( 'admin-ajax.php' ),
                    'nonce'    => wp_create_nonce( 'stackboost_ats_results_nonce' ),
                ]
            );
        }
    }

	/**
	 * Register settings fields for this module.
	 */
	public function register_settings() {
        $page = 'stackboost-ats-settings';
		add_settings_section( 'stackboost_ats_settings_section', '', null, $page );
		add_settings_field( 'ats_background_color', __('Survey Page Background Color', 'stackboost-for-supportcandy'), [ $this, 'render_color_picker' ], $page, 'stackboost_ats_settings_section' );
		add_settings_field( 'ats_ticket_question_id', __('Ticket Number Question', 'stackboost-for-supportcandy'), [ $this, 'render_question_dropdown' ], $page, 'stackboost_ats_settings_section', ['type' => 'any'] );
		add_settings_field( 'ats_technician_question_id', __('Technician Question', 'stackboost-for-supportcandy'), [ $this, 'render_question_dropdown' ], $page, 'stackboost_ats_settings_section', ['type' => 'dropdown'] );
		add_settings_field( 'ats_ticket_url_base', __('Ticket System Base URL', 'stackboost-for-supportcandy'), [ $this, 'render_text_field' ], $page, 'stackboost_ats_settings_section' );
	}

    // --- Settings Field Rendering Callbacks --- //

    public function render_color_picker() {
        $options = get_option( 'stackboost_settings' );
        echo '<input type="text" name="stackboost_settings[ats_background_color]" value="' . esc_attr( $options['ats_background_color'] ?? '#f0f0f0' ) . '" class="stackboost-color-picker" />';
    }

    public function render_question_dropdown(array $args) {
        global $wpdb;
        $options = get_option( 'stackboost_settings' );
        $field_id = 'ats_' . ($args['type'] === 'dropdown' ? 'technician' : 'ticket') . '_question_id';
        $selected = $options[$field_id] ?? '';

        $where_clause = $args['type'] === 'dropdown' ? "WHERE question_type = 'dropdown'" : '';
        $questions = $wpdb->get_results( "SELECT id, question_text FROM {$wpdb->prefix}stackboost_ats_questions {$where_clause} ORDER BY sort_order ASC" );

        echo '<select name="stackboost_settings[' . esc_attr($field_id) . ']"><option value="">-- ' . __('Select', 'stackboost-for-supportcandy') . ' --</option>';
        foreach ( $questions as $q ) {
            echo '<option value="' . esc_attr($q->id) . '"' . selected( $selected, $q->id, false ) . '>' . esc_html( $q->question_text ) . '</option>';
        }
        echo '</select>';
    }

    public function render_text_field() {
        $options = get_option( 'stackboost_settings' );
        echo '<input type="text" name="stackboost_settings[ats_ticket_url_base]" value="' . esc_attr( $options['ats_ticket_url_base'] ?? '' ) . '" class="regular-text" placeholder="https://support.example.com/ticket/">';
    }
}