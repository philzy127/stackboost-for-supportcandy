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
        // Using admin_init to ensure reliability on admin pages, as activation hooks
        // may be registered too late in this architecture.
		add_action( 'admin_init', [ $this->install, 'check_db_version' ] );

		// Admin UI
		add_action( 'admin_menu', [ $this->admin, 'add_admin_menu' ], 11 );
        add_action( 'admin_post_stackboost_ats_admin_actions', [ $this->admin, 'handle_admin_post' ] );
        add_action( 'admin_notices', [ $this->admin, 'display_admin_notices' ] );

		// Frontend Shortcode
		add_shortcode( 'stackboost_after_ticket_survey', [ $this->shortcode, 'render_shortcode' ] );

		// AJAX handlers
		add_action( 'wp_ajax_stackboost_ats_update_report_heading', [ $this->ajax, 'update_report_heading' ] );
        add_action( 'wp_ajax_stackboost_ats_save_question', [ $this->ajax, 'save_question' ] );
        add_action( 'wp_ajax_stackboost_ats_get_question', [ $this->ajax, 'get_question' ] );
        add_action( 'wp_ajax_stackboost_ats_delete_question', [ $this->ajax, 'delete_question' ] );
        add_action( 'wp_ajax_stackboost_ats_reorder_questions', [ $this->ajax, 'reorder_questions' ] );

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
        }
    }

    /**
     * Enqueue admin styles and scripts.
     * @param string $hook_suffix
     */
    public function enqueue_admin_assets(string $hook_suffix) {
        // Robust check for the admin page
        if ( ! isset( $_GET['page'] ) || 'stackboost-ats' !== $_GET['page'] ) {
            return;
        }

        wp_enqueue_style( 'stackboost-ats-admin', STACKBOOST_PLUGIN_URL . 'assets/admin/css/stackboost-ats-admin.css', [], STACKBOOST_VERSION );

        $current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'main';
        $options = get_option( 'stackboost_settings', [] );
        $diagnostic_log_enabled = ! empty( $options['diagnostic_log_enabled'] );

        if ( 'questions' === $current_tab ) {
            wp_enqueue_script( 'jquery-ui-dialog' );
            wp_enqueue_script( 'jquery-ui-sortable' );
            wp_enqueue_style( 'wp-jquery-ui-dialog' ); // Load WP's default jQuery UI styles

            wp_enqueue_script( 'stackboost-ats-manage-questions', STACKBOOST_PLUGIN_URL . 'assets/admin/js/stackboost-ats-manage-questions.js', [ 'jquery', 'jquery-ui-dialog', 'jquery-ui-sortable' ], STACKBOOST_VERSION, true );
            wp_localize_script(
                'stackboost-ats-manage-questions',
                'stackboost_ats_manage',
                [
                    'ajax_url' => admin_url( 'admin-ajax.php' ),
                    'nonce'    => wp_create_nonce( 'stackboost_ats_manage_questions_nonce' ),
                    'diagnostic_log_enabled' => $diagnostic_log_enabled
                ]
            );
        }

        if ( 'results' === $current_tab ) {
            wp_enqueue_script( 'stackboost-ats-results-modal', STACKBOOST_PLUGIN_URL . 'assets/admin/js/stackboost-ats-results-modal.js', ['jquery'], STACKBOOST_VERSION, true );
            wp_localize_script(
                'stackboost-ats-results-modal',
                'stackboost_ats_modal_ajax',
                [
                    'ajax_url' => admin_url( 'admin-ajax.php' ),
                    'nonce'    => wp_create_nonce( 'stackboost_ats_results_nonce' ),
                    'diagnostic_log_enabled' => $diagnostic_log_enabled
                ]
            );
        }
    }

	/**
	 * Public wrapper to render the admin page, delegating to AdminController.
	 */
	public function render_admin_page() {
		$this->admin->render_page();
	}
}
