<?php
namespace StackBoost\ForSupportCandy\Modules\AgentProtection;

class WordPress {
    /**
     * @var WordPress
     */
    private static $instance = null;

    /**
     * Get the singleton instance.
     *
     * @return WordPress
     */
    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init();
    }

    private function init() {
        // Initialize Admin Settings
        if ( is_admin() ) {
            Admin\Settings::get_instance();
        }

        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

        // Register AJAX endpoints
        add_action( 'wp_ajax_stackboost_check_ticket_updates', [ Core::get_instance(), 'ajax_check_ticket_updates' ] );
    }

    /**
     * Enqueue scripts for Agent Protection.
     * Needed on both frontend (ticket page) and backend (admin ticket view).
     */
    public function enqueue_scripts( $hook_suffix = '' ) {
        $settings = get_option( 'stackboost_settings', [] );
        if ( empty( $settings['enable_agent_protection'] ) ) {
            return;
        }

        // Determine if we are on a ticket page.
        // Frontend: Use wpsc_is_ticket_page() if available, or check URL/Shortcode.
        // Backend: Check $hook_suffix for SupportCandy pages.
        $is_ticket_page = false;

        // Backend check
        if ( is_admin() ) {
            // Adjust this check based on actual SupportCandy admin page slugs
            if ( strpos( $hook_suffix, 'wpsc-tickets' ) !== false || strpos( $hook_suffix, 'wpsc-individual-ticket' ) !== false ) {
                $is_ticket_page = true;
            }
        } else {
            // Frontend check
            // We can check for the presence of the [supportcandy] shortcode or specific div classes via JS,
            // but it's cleaner to check global state if possible.
            // For now, we'll enqueue globally on frontend but limit execution via JS checks.
            $is_ticket_page = true;
        }

        if ( ! $is_ticket_page ) {
            return;
        }

        // Register Live Monitor
        if ( ! empty( $settings['enable_live_monitor'] ) ) {
            wp_enqueue_script(
                'stackboost-live-monitor',
                STACKBOOST_PLUGIN_URL . 'assets/js/live-monitor.js',
                [ 'jquery' ],
                STACKBOOST_VERSION,
                true
            );
        }

        // Register Auto Save
        if ( ! empty( $settings['enable_auto_save'] ) ) {
            wp_enqueue_script(
                'stackboost-auto-save',
                STACKBOOST_PLUGIN_URL . 'assets/js/auto-save.js',
                [ 'jquery' ],
                STACKBOOST_VERSION,
                true
            );
        }

        // Register Content Scanner
        if ( ! empty( $settings['enable_content_scanner'] ) ) {
            wp_enqueue_script(
                'stackboost-content-scanner',
                STACKBOOST_PLUGIN_URL . 'assets/js/content-scanner.js',
                [ 'jquery' ],
                STACKBOOST_VERSION,
                true
            );
        }

        // Process keywords
        $att_keywords_raw = ! empty( $settings['attachment_scanner_keywords'] ) ? $settings['attachment_scanner_keywords'] : "attached\nattaching\nattachment\nincluded";
        $cred_keywords_raw = ! empty( $settings['content_scanner_keywords'] ) ? $settings['content_scanner_keywords'] : "password\nlogin\ncredential\nsecret\nkey";

        $att_keywords = array_filter( array_map( 'trim', explode( "\n", $att_keywords_raw ) ) );
        $cred_keywords = array_filter( array_map( 'trim', explode( "\n", $cred_keywords_raw ) ) );

        // Localize script with settings (shared object)
        $localization_data = [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'stackboost_agent_protection_nonce' ),
            'live_monitor_enabled' => ! empty( $settings['enable_live_monitor'] ),
            'live_monitor_interval' => ! empty( $settings['live_monitor_interval'] ) ? intval( $settings['live_monitor_interval'] ) : 30,
            'auto_save_enabled' => ! empty( $settings['enable_auto_save'] ),
            'content_scanner_enabled' => ! empty( $settings['enable_content_scanner'] ),
            'scanner_keywords' => [
                'attachment' => array_values( $att_keywords ),
                'credential' => array_values( $cred_keywords )
            ],
            'i18n' => [
                'heads_up' => __( 'Heads up: Ticket Updated', 'stackboost-for-supportcandy' ),
                'ticket_updated_msg' => __( 'This ticket has been updated since you started working. Please refresh to see the latest changes.', 'stackboost-for-supportcandy' ),
                'refresh_page' => __( 'Refresh Page', 'stackboost-for-supportcandy' ),
                'draft_restored' => __( 'Draft restored from auto-save.', 'stackboost-for-supportcandy' ),
                'scanner_att_title' => __( 'Missing Attachment?', 'stackboost-for-supportcandy' ),
                'scanner_att_msg' => __( 'You mentioned an attachment but no file was detected. Send anyway?', 'stackboost-for-supportcandy' ),
                'scanner_cred_title' => __( 'Sensitive Data Warning', 'stackboost-for-supportcandy' ),
                'scanner_cred_msg' => __( 'It looks like you are sending credentials. Please use the Secure Credentials feature instead.', 'stackboost-for-supportcandy' ),
                'scanner_btn_cancel' => __( 'Edit Reply', 'stackboost-for-supportcandy' ),
                'scanner_btn_proceed' => __( 'Send Anyway', 'stackboost-for-supportcandy' ),
            ]
        ];

        wp_localize_script( 'stackboost-live-monitor', 'stackboost_agent_protection', $localization_data );
        wp_localize_script( 'stackboost-auto-save', 'stackboost_agent_protection', $localization_data );
        wp_localize_script( 'stackboost-content-scanner', 'stackboost_agent_protection', $localization_data );
    }
}
