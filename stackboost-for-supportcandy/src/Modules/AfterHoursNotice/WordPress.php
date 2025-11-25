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
        add_action( 'supportcandy_before_create_ticket_form', [ $this, 'display_notice' ] );
        add_filter( 'wpsc_create_ticket_email_data', [ $this, 'add_after_hours_notice_to_email' ] );
        add_filter( 'wpsc_agent_reply_email_data', [ $this, 'add_after_hours_notice_to_email' ] );
        add_filter( 'wpsc_cust_reply_email_data', [ $this, 'add_after_hours_notice_to_email' ] );
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
            $message = $options['after_hours_message'] ?? '';
            if ( ! empty( $message ) ) {
                // The message is saved via wp_kses_post, so it's safe to display.
                echo '<div class="stackboost-after-hours-notice">' . wpautop( $message ) . '</div>';
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

        add_settings_section(
            'stackboost_after_hours_section',
            __( 'After Hours Notice', 'stackboost-for-supportcandy' ),
            [ $this, 'render_section_description' ],
            $page_slug
        );

        add_settings_field( 'stackboost_enable_after_hours_notice', __( 'Enable Feature', 'stackboost-for-supportcandy' ), [ $this, 'render_checkbox_field' ], $page_slug, 'stackboost_after_hours_section', [ 'id' => 'enable_after_hours_notice', 'desc' => 'Displays a notice on the ticket form when submitted outside of business hours.' ] );
        add_settings_field( 'stackboost_after_hours_in_email', __( 'Add Notice to Emails', 'stackboost-for-supportcandy' ), [ $this, 'render_checkbox_field' ], $page_slug, 'stackboost_after_hours_section', [ 'id' => 'after_hours_in_email', 'desc' => 'Prepend the after-hours message to email notifications if the main feature is enabled.' ] );
        add_settings_field( 'stackboost_after_hours_start', __( 'After Hours Start (24h)', 'stackboost-for-supportcandy' ), [ $this, 'render_number_field' ], $page_slug, 'stackboost_after_hours_section', [ 'id' => 'after_hours_start', 'default' => '17', 'desc' => 'The hour when after-hours starts (e.g., 17 for 5 PM).' ] );
        add_settings_field( 'stackboost_before_hours_end', __( 'Before Hours End (24h)', 'stackboost-for-supportcandy' ), [ $this, 'render_number_field' ], $page_slug, 'stackboost_after_hours_section', [ 'id' => 'before_hours_end', 'default' => '8', 'desc' => 'The hour when business hours resume (e.g., 8 for 8 AM).' ] );
        add_settings_field( 'stackboost_include_all_weekends', __( 'Include All Weekends', 'stackboost-for-supportcandy' ), [ $this, 'render_checkbox_field' ], $page_slug, 'stackboost_after_hours_section', [ 'id' => 'include_all_weekends', 'desc' => 'Enable this to show the notice all day on Saturdays and Sundays.' ] );
        add_settings_field( 'stackboost_holidays', __( 'Holidays', 'stackboost-for-supportcandy' ), [ $this, 'render_textarea_field' ], $page_slug, 'stackboost_after_hours_section', [ 'id' => 'holidays', 'class' => 'regular-text', 'desc' => 'List holidays, one per line, in MM-DD-YYYY format (e.g., 12-25-2024). The notice will show all day on these dates.' ] );
        add_settings_field( 'stackboost_after_hours_message', __( 'After Hours Message', 'stackboost-for-supportcandy' ), [ $this, 'render_wp_editor_field' ], $page_slug, 'stackboost_after_hours_section', [ 'id' => 'after_hours_message', 'desc' => 'The message to display to users. Basic HTML is allowed.' ] );
    }

	/**
	 * Render the description for the section.
	 */
	public function render_section_description() {
		echo '<p>' . esc_html__( 'This feature shows a customizable message at the top of the "Create Ticket" form if a user is accessing it outside of your defined business hours.', 'stackboost-for-supportcandy' ) . '</p>';
	}

    // Note: The rendering functions (render_checkbox_field, etc.) will be moved to a shared Admin/Settings class.
    // For now, we'll assume they exist on the parent Module class for compilation.
}