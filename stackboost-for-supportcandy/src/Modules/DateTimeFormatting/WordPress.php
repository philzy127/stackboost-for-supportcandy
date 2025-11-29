<?php

namespace StackBoost\ForSupportCandy\Modules\DateTimeFormatting;

use StackBoost\ForSupportCandy\Core\Module;
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
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
		add_action( 'init', [ $this, 'apply_date_time_formats' ] );
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

		// Note: The main plugin uses 'toplevel_page_stackboost-for-supportcandy' for the main menu,
		// and 'stackboost_page_stackboost-...' for submenus.
		// We need to match the specific hook.

		if ( strpos( $hook_suffix, $page_slug ) === false ) {
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
		$is_admin_list    = is_admin() && function_exists( 'get_current_screen' ) && get_current_screen() && get_current_screen()->id === 'toplevel_page_wpsc-tickets';
		$is_frontend_list = isset( $_REQUEST['action'] ) && $_REQUEST['action'] === 'wpsc_get_tickets'; // Approximate check for frontend AJAX list
		// Also check strict frontend context param if sent by custom scripts
		$is_frontend_explicit = isset( $_POST['is_frontend'] ) && '1' === $_POST['is_frontend'];

		// We apply formatting in admin lists and potentially frontend lists.
		// The reference implementation was strict about contexts.
		// If neither, return original.
		// Note: We might want this on the ticket detail page too?
		// The requirement says "SupportCandy ticket list".
		// I will stick to the reference implementation's logic.

		if ( ! $is_admin_list && ! $is_frontend_list && ! $is_frontend_explicit ) {
			return $value;
		}

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

		// THE OFFICIAL METHOD: Change the display mode on the field object.
		// This tells SupportCandy to render the returned value as the visible date.
		if ( is_object( $cf ) ) {
			$cf->date_display_as = 'date';
		}

		// GET AND VALIDATE DATE OBJECT
		// Note: The property name on the ticket object typically matches the slug.
		$date_object = isset($ticket->{$field_slug}) ? $ticket->{$field_slug} : null;

		if ( ! ( $date_object instanceof DateTime ) ) {
			return $value;
		}

		// APPLY FORMAT
		$timestamp         = $date_object->getTimestamp();
		$new_value         = $value;
		$short_date_format = 'm/d/Y'; // Fallback
		$long_date_format  = 'F j, Y'; // Fallback

		// Get WP formats
		$wp_date_format = get_option('date_format');
		if ($wp_date_format) {
			$short_date_format = $wp_date_format;
			$long_date_format = $wp_date_format; // Or define a specific long format if needed
		}

		$time_format       = get_option( 'time_format' );
		$date_format       = ! empty( $rule['use_long_date'] ) ? 'F j, Y' : $short_date_format;

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

		return $new_value;
	}
}
