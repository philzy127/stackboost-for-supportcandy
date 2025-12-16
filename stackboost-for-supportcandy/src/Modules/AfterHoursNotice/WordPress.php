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
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
    }

    /**
     * Enqueue admin scripts and styles for the settings page.
     *
     * @param string $hook The current admin page hook.
     */
    public function enqueue_admin_scripts( $hook ) {
        // Only load on our settings page.
        if ( isset( $_GET['page'] ) && 'stackboost-after-hours' === $_GET['page'] ) {

            wp_enqueue_script(
                'stackboost-flatpickr',
                STACKBOOST_PLUGIN_URL . 'assets/admin/js/flatpickr.min.js',
                [],
                '4.6.13',
                true
            );
            wp_enqueue_style(
                'stackboost-flatpickr-css',
                STACKBOOST_PLUGIN_URL . 'assets/admin/css/flatpickr.min.css',
                [],
                '4.6.13'
            );

            wp_enqueue_script(
                'stackboost-admin-after-hours',
                STACKBOOST_PLUGIN_URL . 'assets/admin/js/admin-after-hours.js',
                [ 'jquery', 'stackboost-flatpickr' ],
                STACKBOOST_VERSION,
                true
            );
        }
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
            'start_hour'       => $options['after_hours_start'] ?? '17:00',
            'end_hour'         => $options['before_hours_end'] ?? '08:00',
            'include_weekends' => ! empty( $options['include_all_weekends'] ),
            'holidays'         => $this->core->parse_holidays( $options['holidays'] ?? '' ),
        ];

        // Use the WordPress timezone string.
        $timezone_string = wp_timezone_string();

        if ( $this->core->is_after_hours( $settings, null, $timezone_string ) ) {
            stackboost_log( 'AfterHoursNotice: Currently after hours. Displaying notice on form.', 'after_hours' );
            $message = $options['after_hours_message'] ?? '';
            if ( ! empty( $message ) ) {
                // The message is saved via wp_kses_post, so it's safe to display.
                echo '<div class="stackboost-after-hours-notice" style="margin-left: 15px; margin-bottom: 15px;">' . wpautop( $message ) . '</div>';
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
            'start_hour'       => $options['after_hours_start'] ?? '17:00',
            'end_hour'         => $options['before_hours_end'] ?? '08:00',
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

        add_settings_section(
            'stackboost_after_hours_section',
            __( 'After Hours Notice', 'stackboost-for-supportcandy' ),
            [ $this, 'render_section_description' ],
            $page_slug
        );

        add_settings_field( 'stackboost_enable_after_hours_notice', __( 'Enable Feature', 'stackboost-for-supportcandy' ), [ $this, 'render_checkbox_field' ], $page_slug, 'stackboost_after_hours_section', [ 'id' => 'enable_after_hours_notice', 'desc' => 'Displays a notice on the ticket form when submitted outside of business hours.' ] );
        add_settings_field( 'stackboost_after_hours_in_email', __( 'Add Notice to Emails', 'stackboost-for-supportcandy' ), [ $this, 'render_checkbox_field' ], $page_slug, 'stackboost_after_hours_section', [ 'id' => 'after_hours_in_email', 'desc' => 'Prepend the after-hours message to email notifications if the main feature is enabled.' ] );

        // Use render_time_field for time inputs
        add_settings_field( 'stackboost_after_hours_start', __( 'After Hours Start', 'stackboost-for-supportcandy' ), [ $this, 'render_time_field' ], $page_slug, 'stackboost_after_hours_section', [ 'id' => 'after_hours_start', 'default' => '17:00', 'desc' => 'The time when after-hours starts.' ] );
        add_settings_field( 'stackboost_before_hours_end', __( 'Before Hours End', 'stackboost-for-supportcandy' ), [ $this, 'render_time_field' ], $page_slug, 'stackboost_after_hours_section', [ 'id' => 'before_hours_end', 'default' => '08:00', 'desc' => 'The time when business hours resume.' ] );

        add_settings_field( 'stackboost_include_all_weekends', __( 'Include All Weekends', 'stackboost-for-supportcandy' ), [ $this, 'render_checkbox_field' ], $page_slug, 'stackboost_after_hours_section', [ 'id' => 'include_all_weekends', 'desc' => 'Enable this to show the notice all day on Saturdays and Sundays.' ] );
        add_settings_field( 'stackboost_holidays', __( 'Holidays', 'stackboost-for-supportcandy' ), [ $this, 'render_textarea_field' ], $page_slug, 'stackboost_after_hours_section', [ 'id' => 'holidays', 'class' => 'regular-text', 'desc' => 'List holidays, one per line, in MM-DD-YYYY format (e.g., 12-25-2024). The notice will show all day on these dates.' ] );

        $default_message = '<strong>StackBoost Helpdesk -- After Hours</strong><br><br>You have submitted an IT ticket outside of normal business hours, and it will be handled in the order it was received. If this is an emergency, or has caused a complete stoppage of work, please call the IT On-Call number at: <u>(719) 266-2837</u> <br><br> (Available <b>5pm</b> to <b>11pm(EST) M-F, 8am to 11pm</b> weekends and Holidays)';
        add_settings_field( 'stackboost_after_hours_message', __( 'After Hours Message', 'stackboost-for-supportcandy' ), [ $this, 'render_wp_editor_field' ], $page_slug, 'stackboost_after_hours_section', [ 'id' => 'after_hours_message', 'default' => $default_message, 'desc' => 'The message to display to users. Basic HTML is allowed.' ] );
    }

	/**
	 * Render the description for the section.
	 */
	public function render_section_description() {
		echo '<p>' . esc_html__( 'This feature shows a customizable message at the top of the "Create Ticket" form if a user is accessing it outside of your defined business hours.', 'stackboost-for-supportcandy' ) . '</p>';
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

    /**
     * Render a time picker field.
     *
     * @param array $args The arguments for the field.
     */
    public function render_time_field( array $args ) {
        $options = get_option( 'stackboost_settings', [] );
        $value   = isset( $options[ $args['id'] ] ) ? $options[ $args['id'] ] : ( $args['default'] ?? '' );

        // Compatibility check: Convert legacy integer values (e.g., '17') to 24h string (e.g., '17:00').
        // This is a necessary intermediate step because date() expects a valid time string.
        if ( is_numeric( $value ) ) {
            $value = sprintf( '%02d:00', (int) $value );
        }

        // Force 12-hour format with AM/PM for display.
        // This handles standardizing '17:00' to '05:00 PM', or ensuring '5:00 PM' is clean.
        // If strtotime fails (empty), it falls back to empty string.
        if ( ! empty( $value ) ) {
            $timestamp = strtotime( $value );
            if ( $timestamp ) {
                $value = date( 'h:i A', $timestamp );
            }
        }

        echo '<input type="text" id="' . esc_attr( $args['id'] ) . '" name="stackboost_settings[' . esc_attr( $args['id'] ) . ']" value="' . esc_attr( $value ) . '" class="regular-text stackboost-timepicker">';
        if ( ! empty( $args['desc'] ) ) {
            echo '<p class="description">' . esc_html( $args['desc'] ) . '</p>';
        }
    }
}
