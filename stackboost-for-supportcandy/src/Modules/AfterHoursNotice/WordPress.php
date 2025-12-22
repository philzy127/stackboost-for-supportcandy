<?php

namespace StackBoost\ForSupportCandy\Modules\AfterHoursNotice;

use StackBoost\ForSupportCandy\Core\Module;
use StackBoost\ForSupportCandy\Modules\AfterHoursNotice\Core as AfterHoursNoticeCore;

/**
 * WordPress Adapter for the After Hours Notice feature.
 *
 * This class handles all the WordPress-specific implementations, like
 * registering hooks, settings, and displaying the notice on the frontend.
 *
 * @package StackBoost\ForSupportCandy\Modules\AfterHoursNotice
 */
class WordPress extends Module {

	/**
	 * The single instance of the class.
	 * @var WordPress|null
	 */
	private static ?WordPress $instance = null;

	/**
	 * The core logic class instance.
	 * @var AfterHoursNoticeCore
	 */
	private AfterHoursNoticeCore $core;

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
        // Feature gating check.
		if ( ! stackboost_is_feature_active( 'after_hours_notice' ) ) {
			return;
		}

		$this->core = new AfterHoursNoticeCore();
		parent::__construct();
	}

    /**
     * Get the slug for this module.
     * @return string
     */
    public function get_slug(): string {
        return 'after_hours_notice';
    }

    /**
     * Initialize hooks.
     */
    public function init_hooks() {
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'wpsc_before_create_ticket_form', [ $this, 'display_notice' ] );
        add_filter( 'wpsc_create_ticket_email_data', [ $this, 'add_after_hours_notice_to_email' ] );
        add_filter( 'wpsc_agent_reply_email_data', [ $this, 'add_after_hours_notice_to_email' ] );
        add_filter( 'wpsc_cust_reply_email_data', [ $this, 'add_after_hours_notice_to_email' ] );
    }

    /**
     * Render the administration page.
     */
    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Get active theme class
        $theme_class = 'sb-theme-clean-tech'; // Default
        if ( class_exists( 'StackBoost\ForSupportCandy\Modules\Appearance\WordPress' ) ) {
            $theme_class = \StackBoost\ForSupportCandy\Modules\Appearance\WordPress::get_active_theme_class();
        }

        ?>
        <!-- StackBoost Wrapper Start -->
        <!-- Theme: <?php echo esc_html( $theme_class ); ?> -->
        <div class="wrap stackboost-dashboard <?php echo esc_attr( $theme_class ); ?>">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

            <form action="options.php" method="post">
                <?php
                settings_fields( 'stackboost_settings' );
                echo '<input type="hidden" name="stackboost_settings[page_slug]" value="stackboost-after-hours">';
                ?>

                <div class="stackboost-dashboard-grid">

                    <!-- Card 1: Configuration -->
                    <div class="stackboost-card">
                        <h2><?php esc_html_e( 'Configuration', 'stackboost-for-supportcandy' ); ?></h2>
                        <table class="form-table">
                            <?php do_settings_fields( 'stackboost-after-hours', 'stackboost_ahn_config' ); ?>
                        </table>
                    </div>

                    <!-- Card 2: Schedule -->
                    <div class="stackboost-card">
                        <h2><?php esc_html_e( 'Schedule', 'stackboost-for-supportcandy' ); ?></h2>
                        <table class="form-table">
                            <?php do_settings_fields( 'stackboost-after-hours', 'stackboost_ahn_schedule' ); ?>
                        </table>
                    </div>

                    <!-- Card 3: Holidays -->
                    <div class="stackboost-card">
                        <h2><?php esc_html_e( 'Holidays', 'stackboost-for-supportcandy' ); ?></h2>
                        <table class="form-table">
                            <?php do_settings_fields( 'stackboost-after-hours', 'stackboost_ahn_holidays' ); ?>
                        </table>
                    </div>

                    <!-- Card 4: Message -->
                    <div class="stackboost-card">
                        <h2><?php esc_html_e( 'Notice Message', 'stackboost-for-supportcandy' ); ?></h2>
                        <table class="form-table">
                            <?php do_settings_fields( 'stackboost-after-hours', 'stackboost_ahn_message' ); ?>
                        </table>
                    </div>

                </div>

                <?php submit_button( __( 'Save Settings', 'stackboost-for-supportcandy' ) ); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Display the notice on the frontend if it's after hours.
     */
    public function display_notice() {

        $options = get_option( 'stackboost_settings', [] );
        if ( empty( $options['enable_after_hours_notice'] ) ) {
            return;
        }

        $settings = [
            'start_hour'       => $options['after_hours_start'] ?? 17,
            'end_hour'         => $options['before_hours_end'] ?? 8,
            'include_weekends' => ! empty( $options['include_all_weekends'] ),
            'holidays'         => $this->core->parse_holidays( $options['holidays'] ?? '' ),
        ];

        // Use the WordPress timezone string.
        $timezone_string = wp_timezone_string();

        if ( $this->core->is_after_hours( $settings, null, $timezone_string ) ) {
            stackboost_log( 'AfterHoursNotice: Currently after hours. Displaying notice on form.', 'after_hours' );
            $message = $options['after_hours_message'] ?? '';
            if ( ! empty( $message ) ) {

                // Get active theme class for wrapper
                $theme_class = 'sb-theme-clean-tech'; // Default
                if ( class_exists( 'StackBoost\ForSupportCandy\Modules\Appearance\WordPress' ) ) {
                    $theme_class = \StackBoost\ForSupportCandy\Modules\Appearance\WordPress::get_active_theme_class();
                }

                // Wrap in theme container if we are in admin area
                if ( is_admin() ) {
                    echo '<div class="stackboost-dashboard ' . esc_attr( $theme_class ) . '" style="background:none; padding:0; margin:0; border:none; box-shadow:none;">';
                }

                // The message is saved via wp_kses_post, so it's safe to display.
                echo '<div class="stackboost-after-hours-notice" style="margin-left: 15px; margin-bottom: 15px;">' . wpautop( $message ) . '</div>';

                if ( is_admin() ) {
                    echo '</div>';
                }
            }
        }
    }

    /**
     * Add the after-hours notice to the email body if conditions are met.
     *
     * @param array $email_data The email data array.
     * @return array The modified email data array.
     */
    public function add_after_hours_notice_to_email( array $email_data ): array {
        $options = get_option( 'stackboost_settings', [] );

        // Exit early if the main feature or the email feature is disabled.
        if ( empty( $options['enable_after_hours_notice'] ) || empty( $options['after_hours_in_email'] ) ) {
            return $email_data;
        }

        $settings = [
            'start_hour'       => $options['after_hours_start'] ?? 17,
            'end_hour'         => $options['before_hours_end'] ?? 8,
            'include_weekends' => ! empty( $options['include_all_weekends'] ),
            'holidays'         => $this->core->parse_holidays( $options['holidays'] ?? '' ),
        ];

        // Use the WordPress timezone string.
        $timezone_string = wp_timezone_string();

        if ( $this->core->is_after_hours( $settings, null, $timezone_string ) ) {
            stackboost_log( 'AfterHoursNotice: Currently after hours. Prepending notice to email.', 'after_hours' );
            $message = $options['after_hours_message'] ?? '';
            if ( ! empty( $message ) ) {
                $notice      = '<div class="stackboost-after-hours-notice" style="margin-bottom: 20px; padding: 15px; border-left: 5px solid #ffba00; background-color: #fff8e5;">' . wpautop( $message ) . '</div>';
                $email_data['body'] = $notice . $email_data['body'];
            }
        }

        return $email_data;
    }

    /**
     * Register settings fields for this module.
     */
    public function register_settings() {
        $page_slug = 'stackboost-after-hours';

        // 1. Configuration Section
        add_settings_section( 'stackboost_ahn_config', __( 'Configuration', 'stackboost-for-supportcandy' ), null, $page_slug );
        add_settings_field( 'stackboost_enable_after_hours_notice', __( 'Enable Feature', 'stackboost-for-supportcandy' ), [ $this, 'render_checkbox_field' ], $page_slug, 'stackboost_ahn_config', [ 'id' => 'enable_after_hours_notice', 'desc' => 'Displays a notice on the ticket form when submitted outside of business hours.' ] );
        add_settings_field( 'stackboost_after_hours_in_email', __( 'Add Notice to Emails', 'stackboost-for-supportcandy' ), [ $this, 'render_checkbox_field' ], $page_slug, 'stackboost_ahn_config', [ 'id' => 'after_hours_in_email', 'desc' => 'Prepend the after-hours message to email notifications if the main feature is enabled.' ] );

        // 2. Schedule Section
        add_settings_section( 'stackboost_ahn_schedule', __( 'Schedule', 'stackboost-for-supportcandy' ), null, $page_slug );
        add_settings_field( 'stackboost_after_hours_start', __( 'After Hours Start (24h)', 'stackboost-for-supportcandy' ), [ $this, 'render_number_field' ], $page_slug, 'stackboost_ahn_schedule', [ 'id' => 'after_hours_start', 'default' => '17', 'desc' => 'The hour when after-hours starts (e.g., 17 for 5 PM).' ] );
        add_settings_field( 'stackboost_before_hours_end', __( 'Before Hours End (24h)', 'stackboost-for-supportcandy' ), [ $this, 'render_number_field' ], $page_slug, 'stackboost_ahn_schedule', [ 'id' => 'before_hours_end', 'default' => '8', 'desc' => 'The hour when business hours resume (e.g., 8 for 8 AM).' ] );
        add_settings_field( 'stackboost_include_all_weekends', __( 'Include All Weekends', 'stackboost-for-supportcandy' ), [ $this, 'render_checkbox_field' ], $page_slug, 'stackboost_ahn_schedule', [ 'id' => 'include_all_weekends', 'desc' => 'Enable this to show the notice all day on Saturdays and Sundays.' ] );

        // 3. Holidays Section
        add_settings_section( 'stackboost_ahn_holidays', __( 'Holidays', 'stackboost-for-supportcandy' ), null, $page_slug );
        add_settings_field( 'stackboost_holidays', __( 'Holidays', 'stackboost-for-supportcandy' ), [ $this, 'render_textarea_field' ], $page_slug, 'stackboost_ahn_holidays', [ 'id' => 'holidays', 'class' => 'regular-text', 'desc' => 'List holidays, one per line, in MM-DD-YYYY format (e.g., 12-25-2024). The notice will show all day on these dates.' ] );

        // 4. Message Section
        add_settings_section( 'stackboost_ahn_message', __( 'Notice Message', 'stackboost-for-supportcandy' ), null, $page_slug );
        $default_message = '<strong>StackBoost Helpdesk -- After Hours</strong><br><br>You have submitted an IT ticket outside of normal business hours, and it will be handled in the order it was received. If this is an emergency, or has caused a complete stoppage of work, please call the IT On-Call number at: <u>(719) 266-2837</u> <br><br> (Available <b>5pm</b> to <b>11pm(EST) M-F, 8am to 11pm</b> weekends and Holidays)';
        add_settings_field( 'stackboost_after_hours_message', __( 'After Hours Message', 'stackboost-for-supportcandy' ), [ $this, 'render_wp_editor_field' ], $page_slug, 'stackboost_ahn_message', [ 'id' => 'after_hours_message', 'default' => $default_message, 'desc' => 'The message to display to users. Basic HTML is allowed.' ] );
    }

    // Note: The rendering functions (render_checkbox_field, etc.) will be moved to a shared Admin/Settings class.
    // For now, we'll assume they exist on the parent Module class for compilation.

    /**
     * Render a WP Editor (WYSIWYG) field.
     * Overrides the parent method to add specific toolbar options.
     *
     * @param array $args The arguments for the field.
     */
    public function render_wp_editor_field( array $args ) {
        $options = get_option( 'stackboost_settings', [] );
        $content = isset( $options[ $args['id'] ] ) ? $options[ $args['id'] ] : ( $args['default'] ?? '' );

        wp_editor(
            $content,
            'stackboost_settings_' . esc_attr( $args['id'] ),
            [
                'textarea_name' => 'stackboost_settings[' . esc_attr( $args['id'] ) . ']',
                'media_buttons' => false,
                'textarea_rows' => 10,
                'teeny'         => true,
                'tinymce'       => [
                    'toolbar1' => 'formatselect,styleselect,bold,italic,underline,forecolor,blockquote,strikethrough,bullist,numlist,outdent,indent,alignleft,aligncenter,alignright,undo,redo,link,unlink,hr,removeformat,fullscreen',
                    'plugins'  => 'lists,wplink,wordpress,paste,fullscreen,textcolor,hr',
                ],
            ]
        );
        if ( ! empty( $args['desc'] ) ) {
            echo '<p class="description">' . esc_html( $args['desc'] ) . '</p>';
        }
    }
}