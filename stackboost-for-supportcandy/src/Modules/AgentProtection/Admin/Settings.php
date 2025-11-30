<?php
namespace StackBoost\ForSupportCandy\Modules\AgentProtection\Admin;

class Settings {
    /**
     * @var Settings
     */
    private static $instance = null;

    /**
     * Get the singleton instance.
     *
     * @return Settings
     */
    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Register Settings Sections and Fields
        add_action( 'admin_init', [ $this, 'register_settings_fields' ] );
    }

    /**
     * Register settings sections and fields for the Agent Protection module.
     */
    public function register_settings_fields() {
        // Main Section
        add_settings_section(
            'stackboost_ap_general_section',
            __( 'General Settings', 'stackboost-for-supportcandy' ),
            '__return_null',
            'stackboost-agent-protection'
        );

        add_settings_field(
            'enable_agent_protection',
            __( 'Enable Agent Protection Suite', 'stackboost-for-supportcandy' ),
            [ $this, 'render_toggle' ],
            'stackboost-agent-protection',
            'stackboost_ap_general_section',
            [
                'label_for' => 'enable_agent_protection',
                'description' => __( 'Master switch for the entire protection suite.', 'stackboost-for-supportcandy' )
            ]
        );

        // Live Ticket Monitor Section
        add_settings_section(
            'stackboost_ap_monitor_section',
            __( '1. Live Ticket Monitor (Radar)', 'stackboost-for-supportcandy' ),
            [ $this, 'render_monitor_section_desc' ],
            'stackboost-agent-protection'
        );

        add_settings_field(
            'enable_live_monitor',
            __( 'Enable Live Monitor', 'stackboost-for-supportcandy' ),
            [ $this, 'render_toggle' ],
            'stackboost-agent-protection',
            'stackboost_ap_monitor_section',
            [
                'label_for' => 'enable_live_monitor',
                'description' => __( 'Silently polls for changes and alerts the agent if the ticket is updated.', 'stackboost-for-supportcandy' )
            ]
        );

        add_settings_field(
            'live_monitor_interval',
            __( 'Polling Interval', 'stackboost-for-supportcandy' ),
            [ $this, 'render_interval_select' ],
            'stackboost-agent-protection',
            'stackboost_ap_monitor_section',
            [ 'label_for' => 'live_monitor_interval' ]
        );

        // Auto-Save Section
        add_settings_section(
            'stackboost_ap_autosave_section',
            __( '2. Local Draft Auto-Save', 'stackboost-for-supportcandy' ),
            [ $this, 'render_autosave_section_desc' ],
            'stackboost-agent-protection'
        );

        add_settings_field(
            'enable_auto_save',
            __( 'Enable Auto-Save', 'stackboost-for-supportcandy' ),
            [ $this, 'render_toggle' ],
            'stackboost-agent-protection',
            'stackboost_ap_autosave_section',
            [
                'label_for' => 'enable_auto_save',
                'description' => __( 'Automatically saves reply drafts to browser storage to prevent data loss.', 'stackboost-for-supportcandy' )
            ]
        );

        // Content Scanner Section
        add_settings_section(
            'stackboost_ap_scanner_section',
            __( '3. Smart Content Scanner', 'stackboost-for-supportcandy' ),
            [ $this, 'render_scanner_section_desc' ],
            'stackboost-agent-protection'
        );

        add_settings_field(
            'enable_content_scanner',
            __( 'Enable Content Scanner', 'stackboost-for-supportcandy' ),
            [ $this, 'render_toggle' ],
            'stackboost-agent-protection',
            'stackboost_ap_scanner_section',
            [
                'label_for' => 'enable_content_scanner',
                'description' => __( 'Scans replies for missing attachments or sensitive data before submission.', 'stackboost-for-supportcandy' )
            ]
        );

        add_settings_field(
            'content_scanner_keywords',
            __( 'Credential Keywords', 'stackboost-for-supportcandy' ),
            [ $this, 'render_textarea' ],
            'stackboost-agent-protection',
            'stackboost_ap_scanner_section',
            [
                'label_for' => 'content_scanner_keywords',
                'default' => "password\nlogin\ncredential\nsecret\nkey",
                'description' => __( 'One per line. If found, prompts user to use Secure Credentials.', 'stackboost-for-supportcandy' )
            ]
        );

        add_settings_field(
            'attachment_scanner_keywords',
            __( 'Attachment Keywords', 'stackboost-for-supportcandy' ),
            [ $this, 'render_textarea' ],
            'stackboost-agent-protection',
            'stackboost_ap_scanner_section',
            [
                'label_for' => 'attachment_scanner_keywords',
                'default' => "attached\nattaching\nattachment\nincluded",
                'description' => __( 'One per line. If found without a file, prompts user to attach file.', 'stackboost-for-supportcandy' )
            ]
        );
    }

    // Render Callbacks

    public function render_monitor_section_desc() {
        echo '<p>' . esc_html__( 'Prevents "stale" replies by warning agents if the ticket changes while they are working.', 'stackboost-for-supportcandy' ) . '</p>';
    }

    public function render_autosave_section_desc() {
        echo '<p>' . esc_html__( 'Protects against browser crashes and accidental refreshes.', 'stackboost-for-supportcandy' ) . '</p>';
    }

    public function render_scanner_section_desc() {
        echo '<p>' . esc_html__( 'Enforces security policies and prevents embarrassing "forgot attachment" emails.', 'stackboost-for-supportcandy' ) . '</p>';
    }

    public function render_toggle( $args ) {
        $options = get_option( 'stackboost_settings', [] );
        $id = $args['label_for'];
        $checked = ! empty( $options[ $id ] );
        ?>
        <label class="stackboost-switch">
            <input type="checkbox" name="stackboost_settings[<?php echo esc_attr( $id ); ?>]" value="1" <?php checked( $checked ); ?>>
            <span class="slider round"></span>
        </label>
        <?php if ( ! empty( $args['description'] ) ) : ?>
            <p class="description"><?php echo esc_html( $args['description'] ); ?></p>
        <?php endif;
    }

    public function render_interval_select( $args ) {
        $options = get_option( 'stackboost_settings', [] );
        $id = $args['label_for'];
        $value = ! empty( $options[ $id ] ) ? $options[ $id ] : 30;
        ?>
        <select name="stackboost_settings[<?php echo esc_attr( $id ); ?>]">
            <option value="15" <?php selected( $value, 15 ); ?>>15 <?php esc_html_e( 'seconds', 'stackboost-for-supportcandy' ); ?></option>
            <option value="30" <?php selected( $value, 30 ); ?>>30 <?php esc_html_e( 'seconds', 'stackboost-for-supportcandy' ); ?></option>
            <option value="60" <?php selected( $value, 60 ); ?>>60 <?php esc_html_e( 'seconds', 'stackboost-for-supportcandy' ); ?></option>
        </select>
        <p class="description"><?php esc_html_e( 'How often the system checks for updates.', 'stackboost-for-supportcandy' ); ?></p>
        <?php
    }

    public function render_textarea( $args ) {
        $options = get_option( 'stackboost_settings', [] );
        $id = $args['label_for'];
        $value = ! empty( $options[ $id ] ) ? $options[ $id ] : $args['default'];
        ?>
        <textarea name="stackboost_settings[<?php echo esc_attr( $id ); ?>]" rows="5" cols="50" class="large-text code"><?php echo esc_textarea( $value ); ?></textarea>
        <?php if ( ! empty( $args['description'] ) ) : ?>
            <p class="description"><?php echo esc_html( $args['description'] ); ?></p>
        <?php endif;
    }

    /**
     * Render the Agent Protection settings page.
     */
    public static function render_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Active Agent Protection Suite', 'stackboost-for-supportcandy' ); ?></h1>
            <p><?php esc_html_e( 'Active features that protect agents while they work. This suite reduces friction, prevents data loss, and enforces compliance.', 'stackboost-for-supportcandy' ); ?></p>

            <form action="options.php" method="post">
                <?php
                settings_fields( 'stackboost_settings' );
                echo '<input type="hidden" name="stackboost_settings[page_slug]" value="stackboost-agent-protection">';
                do_settings_sections( 'stackboost-agent-protection' );
                submit_button( __( 'Save Settings', 'stackboost-for-supportcandy' ) );
                ?>
            </form>
        </div>
        <?php
    }
}
