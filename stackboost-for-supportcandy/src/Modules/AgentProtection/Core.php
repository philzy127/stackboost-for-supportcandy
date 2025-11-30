<?php
namespace StackBoost\ForSupportCandy\Modules\AgentProtection;

class Core {
    /**
     * @var Core
     */
    private static $instance = null;

    /**
     * Get the singleton instance.
     *
     * @return Core
     */
    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Initialization
    }

    /**
     * Helper to get the correct tickets table name.
     * Checks for modern 'wpsc_ticket' first, then legacy 'psmsc_tickets'.
     *
     * @return string|false Table name or false if not found.
     */
    public function get_tickets_table_name() {
        global $wpdb;

        // Check for modern table (SupportCandy v2+)
        $modern_table = $wpdb->prefix . 'wpsc_ticket';
        if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $modern_table ) ) === $modern_table ) {
            return $modern_table;
        }

        // Check for legacy table (WP Support Plus / Old SupportCandy)
        $legacy_table = $wpdb->prefix . 'psmsc_tickets';
        if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $legacy_table ) ) === $legacy_table ) {
            return $legacy_table;
        }

        return false;
    }

    /**
     * AJAX handler to check for ticket updates.
     */
    public function ajax_check_ticket_updates() {
        check_ajax_referer( 'stackboost_agent_protection_nonce', 'nonce' );

        $ticket_id = isset( $_POST['ticket_id'] ) ? intval( $_POST['ticket_id'] ) : 0;
        $client_known_updated = isset( $_POST['current_updated_timestamp'] ) ? intval( $_POST['current_updated_timestamp'] ) : 0;

        if ( ! $ticket_id ) {
            wp_send_json_error( 'Invalid ticket ID' );
        }

        global $wpdb;
        $table_name = $this->get_tickets_table_name();

        if ( ! $table_name ) {
             wp_send_json_error( 'Tickets table not found' );
        }

        $date_updated = $wpdb->get_var( $wpdb->prepare(
            "SELECT date_updated FROM $table_name WHERE id = %d",
            $ticket_id
        ) );

        if ( ! $date_updated ) {
            wp_send_json_error( 'Ticket not found' );
        }

        // SupportCandy stores dates as 'YYYY-MM-DD HH:MM:SS' in UTC usually
        $server_current_updated = strtotime( $date_updated . ' UTC' );

        // Compare: Is the DB's current updated time NEWER than what the client knows?
        // If client sends 0 (unknown), we might treat it as modified or just return current.
        // Let's assume if 0, we just return the current timestamp for them to sync.

        if ( $client_known_updated === 0 ) {
             wp_send_json_success( [
                'modified' => false,
                'current_updated_timestamp' => $server_current_updated
            ] );
        }

        $modified = $server_current_updated > $client_known_updated;

        wp_send_json_success( [
            'modified' => $modified,
            'current_updated_timestamp' => $server_current_updated
        ] );
    }
}
