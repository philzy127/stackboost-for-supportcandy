<?php


namespace StackBoost\ForSupportCandy\Modules\DateTimeFormatting;

if ( ! defined( 'ABSPATH' ) ) exit;

use StackBoost\ForSupportCandy\Core\Module;
use StackBoost\ForSupportCandy\Core\Request;
use StackBoost\ForSupportCandy\WordPress\Plugin;
use DateTime;

/**
 * WordPress Adapter for Date & Time Formatting.
 *
 * @package StackBoost\ForSupportCandy\Modules\DateTimeFormatting
 */
class WordPress extends Module {

	/** @var WordPress|null */
	private static ?WordPress $instance = null;

	/** @var array */
	private array $formatted_rules = [];

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
	 * Get the slug for this module.
	 * @return string
	 */
	public function get_slug(): string {
		return 'date_time_formatting';
	}

	/**
	 * Initialize hooks.
	 */
	public function init_hooks() {
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
		add_action( 'init', [ $this, 'apply_date_time_formats' ] );
		add_action( 'wp_ajax_stackboost_save_date_time_settings', [ $this, 'ajax_save_settings' ] );
	}

	/**
	 * Register the isolated settings for this module.
	 *
	 * NOTE: This module uses a separate option group ('stackboost_date_time_settings')
	 * instead of the central 'stackboost_settings' array. This is intentional.
	 * The dynamic nature of the rules array proved fragile when merged into the
	 * monolithic central setting via AJAX. Isolation guarantees data stability.
	 */
	public function register_settings() {
		register_setting( 'stackboost_date_time_settings', 'stackboost_date_time_settings', [ $this, 'sanitize_settings' ] );
	}

	/**
	 * Sanitize the settings.
	 *
	 * @param array $input
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$output = [];
		$output['enable_date_time_formatting'] = ! empty( $input['enable_date_time_formatting'] ) ? 1 : 0;

		if ( isset( $input['date_format_rules'] ) && is_array( $input['date_format_rules'] ) ) {
			$sanitized_rules = [];
			foreach ( $input['date_format_rules'] as $rule ) {
				if ( ! is_array( $rule ) || empty( $rule['column'] ) ) {
					continue;
				}
				$sanitized_rule                   = [];
				$sanitized_rule['column']         = sanitize_text_field( $rule['column'] );
				$sanitized_rule['format_type']    = in_array( $rule['format_type'], [ 'default', 'date_only', 'time_only', 'date_and_time', 'custom' ], true ) ? $rule['format_type'] : 'default';
				$sanitized_rule['custom_format']  = sanitize_text_field( $rule['custom_format'] );
				$sanitized_rule['use_long_date']    = ! empty( $rule['use_long_date'] ) ? 1 : 0;
				$sanitized_rule['show_day_of_week'] = ! empty( $rule['show_day_of_week'] ) ? 1 : 0;
				$sanitized_rules[]              = $sanitized_rule;
			}
			$output['date_format_rules'] = $sanitized_rules;
		} else {
			$output['date_format_rules'] = [];
		}

		return $output;
	}

	/**
	 * Custom AJAX handler to save these specific settings.
	 */
	public function ajax_save_settings() {
		check_ajax_referer( 'stackboost_admin_nonce', 'nonce' );

		if ( ! current_user_can( STACKBOOST_CAP_MANAGE_DATE_TIME ) ) {
			wp_send_json_error( __( 'Permission denied.', 'stackboost-for-supportcandy' ) );
		}

		if ( ! Request::has_post( 'stackboost_date_time_settings' ) ) {
			wp_send_json_error( __( 'Invalid settings data.', 'stackboost-for-supportcandy' ) );
		}

		// Use 'array' type to handle nested array structure of settings
		$input = Request::get_post( 'stackboost_date_time_settings', [], 'raw' );
		// Note: 'raw' is used because sanitize_settings expects the array structure.
		// Since sanitize_settings() iterates and runs sanitize_text_field(), this is safe.

		$sanitized = $this->sanitize_settings( $input );

		if ( update_option( 'stackboost_date_time_settings', $sanitized ) ) {
			wp_send_json_success( __( 'Settings saved successfully.', 'stackboost-for-supportcandy' ) );
		} else {
			// update_option returns false if value is unchanged. We still treat this as success for UX.
			wp_send_json_success( __( 'Settings saved (no changes detected).', 'stackboost-for-supportcandy' ) );
		}
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook_suffix
	 */
	public function enqueue_admin_scripts( $hook_suffix ) {
		// The hook suffix for our page.
		// Pattern: stackboost_page_{page_slug}
		$page_slug = 'stackboost-date-time';

		if ( function_exists( 'stackboost_log' ) ) {
			stackboost_log( "DateTimeFormatting::enqueue_admin_scripts called. Hook: {$hook_suffix}", 'date_time_formatting' );
		}

		$current_page = Request::get_get( 'page', '', 'key' );

		if ( $current_page !== $page_slug ) {
			if ( function_exists( 'stackboost_log' ) ) {
				stackboost_log( "DateTimeFormatting: Current page '{$current_page}' does not match slug '{$page_slug}'. Skipping enqueue.", 'date_time_formatting' );
			}
			return;
		}

		wp_enqueue_style(
			'stackboost-date-time-formatting',
			STACKBOOST_PLUGIN_URL . 'src/Modules/DateTimeFormatting/assets/admin/css/date-time-formatting.css',
			[],
			STACKBOOST_VERSION
		);

		wp_enqueue_script(
			'stackboost-date-time-formatting',
			STACKBOOST_PLUGIN_URL . 'src/Modules/DateTimeFormatting/assets/admin/js/date-time-formatting.js',
			[ 'jquery' ],
			STACKBOOST_VERSION,
			true
		);
	}

	/**
	 * Apply the date/time formatting rules.
	 */
	public function apply_date_time_formats() {
		$options = get_option( 'stackboost_settings', [] );
		if ( empty( $options['enable_date_time_formatting'] ) ) {
			return;
		}

		$rules = isset( $options['date_format_rules'] ) && is_array( $options['date_format_rules'] ) ? $options['date_format_rules'] : [];

		if ( empty( $rules ) ) {
			return;
		}

		// Store rules in a more accessible format.
		$this->formatted_rules = [];
		foreach ( $rules as $rule ) {
			if ( ! empty( $rule['column'] ) && 'default' !== $rule['format_type'] ) {
				$this->formatted_rules[ $rule['column'] ] = $rule;
			}
		}

		if ( empty( $this->formatted_rules ) ) {
			return;
		}

		// Add a single filter for all datetime custom fields.
		add_filter( 'wpsc_ticket_field_val_datetime', [ $this, 'format_date_time_callback' ], 10, 4 );

		// Add filters for all potential standard fields. The callback will check if a rule exists.
		$standard_fields = [ 'date_created', 'last_reply_on', 'date_closed', 'date_updated' ];
		foreach ( $standard_fields as $field ) {
			add_filter( 'wpsc_ticket_field_val_' . $field, [ $this, 'format_date_time_callback' ], 10, 4 );
		}
	}

	/**
	 * Callback function to format the date/time value.
	 *
	 * @param mixed  $value  The original value.
	 * @param object $cf     The custom field object.
	 * @param object $ticket The ticket object.
	 * @param string $module The module name.
	 * @return mixed The formatted value.
	 */
	public function format_date_time_callback( $value, $cf, $ticket, $module ) {

		// CONTEXT CHECK
		// We want to apply formatting in:
		// 1. Admin Ticket Lists (AJAX or direct)
		// 2. Frontend Ticket Lists (AJAX)
		// 3. Ticket Details (AJAX)
		// But NOT in edit forms where raw DB value is needed.

		$action_req = Request::get_request( 'action', '', 'key' );
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		$is_target_context = false;

		// Check 1: Known AJAX actions for displaying tickets
		if ( in_array( $action_req, [ 'wpsc_get_tickets', 'wpsc_get_individual_ticket' ], true ) ) {
			$is_target_context = true;
		}

		// Check 2: Explicit frontend flag
		if ( Request::get_post( 'is_frontend', '0', 'text' ) === '1' ) {
			$is_target_context = true;
		}

		// Check 3: Admin Screen (if not AJAX)
		if ( is_admin() && $screen && strpos( $screen->id, 'wpsc-tickets' ) !== false ) {
			$is_target_context = true;
		}

		if ( ! $is_target_context ) {
			return $value;
		}

		// stackboost_log( 'format_date_time_callback: Passed context check.', 'date_time_formatting' );

		// GET SLUG
		$current_filter = current_filter();
		$field_slug     = null;
		if ( strpos( $current_filter, 'wpsc_ticket_field_val_datetime' ) !== false ) {
			if ( is_object( $cf ) && isset( $cf->slug ) ) {
				$field_slug = $cf->slug;
			}
		} else {
			if ( strpos( $current_filter, 'wpsc_ticket_field_val_' ) === 0 ) {
				$field_slug = substr( $current_filter, 22 );
			}
		}

		if ( ! $field_slug ) {
			return $value;
		}

		// FIND RULE
		if ( ! isset( $this->formatted_rules[ $field_slug ] ) ) {
			return $value;
		}
		$rule = $this->formatted_rules[ $field_slug ];

		stackboost_log( "format_date_time_callback: Rule found for slug: {$field_slug}", 'date_time_formatting' );

		// THE OFFICIAL METHOD: Change the display mode on the field object.
		// This tells SupportCandy to render the returned value as the visible date.
		if ( is_object( $cf ) ) {
			$cf->date_display_as = 'date';
		}

		// GET AND VALIDATE DATE OBJECT
		// Note: The property name on the ticket object typically matches the slug.
		$date_object = isset($ticket->{$field_slug}) ? $ticket->{$field_slug} : null;

		stackboost_log( "format_date_time_callback: Initial Date Object Type: " . gettype($date_object), 'date_time_formatting' );

		// Fallback: If ticket property is null, try using the filter value itself.
		if ( empty( $date_object ) && ! empty( $value ) ) {
			stackboost_log( "format_date_time_callback: Ticket property is empty. Using filter value: " . $value, 'date_time_formatting' );
			$date_object = $value;
		}

		if ( is_string($date_object) ) {
			stackboost_log( "format_date_time_callback: Initial Date String: " . $date_object, 'date_time_formatting' );
		}

		// If the date object is a string (raw DB format), convert it to DateTime.
		if ( is_string( $date_object ) && ! empty( $date_object ) ) {
			try {
				$date_object = new DateTime( $date_object );
				$date_object->setTimezone( wp_timezone() );
				stackboost_log( "format_date_time_callback: Successfully converted string to DateTime.", 'date_time_formatting' );
			} catch ( \Exception $e ) {
				stackboost_log( "format_date_time_callback: Failed to convert string to DateTime. Error: " . $e->getMessage(), 'date_time_formatting' );
				return $value;
			}
		}

		if ( ! ( $date_object instanceof DateTime ) ) {
			stackboost_log( "format_date_time_callback: Not a valid DateTime object after processing. Returning original value.", 'date_time_formatting' );
			return $value;
		}

		// APPLY FORMAT
		$timestamp         = $date_object->getTimestamp();
		$new_value         = $value;
		$short_date_format = 'm/d/Y';
		$long_date_format  = 'F j, Y';
		$time_format       = get_option( 'time_format' );
		$date_format       = ! empty( $rule['use_long_date'] ) ? $long_date_format : $short_date_format;

		if ( ! empty( $rule['show_day_of_week'] ) ) {
			$day_prefix  = ! empty( $rule['use_long_date'] ) ? 'l, ' : 'D, ';
			$date_format = $day_prefix . $date_format;
		}

		switch ( $rule['format_type'] ) {
			case 'date_only':
				$new_value = wp_date( $date_format, $timestamp );
				break;
			case 'time_only':
				$new_value = wp_date( $time_format, $timestamp );
				break;
			case 'date_and_time':
				$new_value = wp_date( $date_format . ' ' . $time_format, $timestamp );
				break;
			case 'custom':
				if ( ! empty( $rule['custom_format'] ) ) {
					$new_value = wp_date( $rule['custom_format'], $timestamp );
				}
				break;
		}

		stackboost_log( "format_date_time_callback: Returning new value: {$new_value}", 'date_time_formatting' );

		return $new_value;
	}
}
