<?php
/**
 * Unified Ticket Macro - Core Logic
 *
 * @package StackBoost\ForSupportCandy\Modules\UnifiedTicketMacro
 */

namespace StackBoost\ForSupportCandy\Modules\UnifiedTicketMacro;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Core class for the Unified Ticket Macro module.
 */
class Core {

	/**
	 * The single instance of the class.
	 *
	 * @var Core|null
	 */
	private static ?Core $instance = null;

	/**
	 * Get the single instance of the class.
	 */
	public static function get_instance(): Core {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Replaces the UTM macro in the email body.
	 *
	 * @param string       $str    The full email body string being processed.
	 * @param \WPSC_Ticket $ticket The fully formed WPSC_Ticket object.
	 * @param string       $macro  The specific tag name currently being checked.
	 * @return string The modified email body string.
	 */
	public function replace_utm_macro( string $str, \WPSC_Ticket $ticket, string $macro ): string {
		\stackboost_log( '[UTM] replace_utm_macro() - ENTER', 'module-utm' );

		// Check if the current macro is ours and if the placeholder exists.
		if ( 'stackboost_unified_ticket' !== $macro || false === strpos( $str, '{{stackboost_unified_ticket}}' ) ) {
			\stackboost_log( '[UTM] replace_utm_macro() - EXIT - Not our macro or placeholder not found.', 'module-utm' );
			return $str;
		}

		\stackboost_log( '[UTM] replace_utm_macro() - Processing for ticket ID: ' . $ticket->id, 'module-utm' );

		// Generate the HTML on-the-fly every time for maximum accuracy.
		$html_to_insert = $this->build_live_utm_html( $ticket );
		\stackboost_log( '[UTM] replace_utm_macro() - HTML generated on-the-fly.', 'module-utm' );

		$str = str_replace( '{{stackboost_unified_ticket}}', $html_to_insert, $str );
		\stackboost_log( '[UTM] replace_utm_macro() - EXIT - Macro replacement complete.', 'module-utm' );

		return $str;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		// Actions and filters will be added in the WordPress.php file.
	}

	/**
	 * Builds the HTML table for the UTM based on current settings and ticket data.
	 *
	 * @param \WPSC_Ticket $ticket The ticket object.
	 * @return string The generated HTML.
	 */
	private function build_live_utm_html( \WPSC_Ticket $ticket ): string {
		\stackboost_log( '[UTM] build_live_utm_html() - ENTER for ticket ID: ' . $ticket->id, 'module-utm' );
		$options          = get_option( 'stackboost_settings', [] );
		$is_enabled       = $options['utm_enabled'] ?? false;

		if ( ! $is_enabled ) {
			\stackboost_log( '[UTM] build_live_utm_html() - EXIT - Feature is disabled in settings.', 'module-utm' );
			return ''; // Return empty string if the feature is disabled.
		}

		$selected_fields  = $options['utm_columns'] ?? [];
		$rename_rules_raw = $options['utm_rename_rules'] ?? [];
		\stackboost_log( '[UTM] build_live_utm_html() - Found ' . count( $selected_fields ) . ' selected fields.', 'module-utm' );

		// Create a simple map for the rename rules for easy lookup.
		$rename_rules_map = [];
		foreach ( $rename_rules_raw as $rule ) {
			if ( isset( $rule['field'] ) && ! empty( $rule['name'] ) ) {
				$rename_rules_map[ $rule['field'] ] = $rule['name'];
			}
		}

		// Get all available columns to map slugs to friendly names.
		$all_columns = \StackBoost\ForSupportCandy\WordPress\Plugin::get_instance()->get_supportcandy_columns();

		$use_sc_order = ! empty( $options['utm_use_sc_order'] );
		if ( $use_sc_order ) {
			$supportcandy_tff_fields = get_option( 'wpsc-tff', [] );
			$sc_ordered_slugs        = array_keys( $supportcandy_tff_fields );
			$ordered_part            = array_intersect( $sc_ordered_slugs, $selected_fields );
			$unmatched_part          = array_diff( $selected_fields, $sc_ordered_slugs );
			$selected_fields         = array_merge( $ordered_part, $unmatched_part );
		}

		if ( empty( $selected_fields ) ) {
			return '<table></table>';
		}

		// Use the official API to get a complete list of all field types.
		$all_fields      = \WPSC_Custom_Field::$custom_fields;
		$field_types_map = [];
		foreach ( $all_fields as $slug => $field_object ) {
			$field_type_class         = $field_object->type;
			$field_types_map[ $slug ] = $field_type_class::$slug;
		}

		$html_output = '<table>';

		foreach ( $selected_fields as $field_slug ) {
			$field_value     = $ticket->{$field_slug};
			$field_type      = $field_types_map[ $field_slug ] ?? 'unknown';
			$field_name      = $rename_rules_map[ $field_slug ] ?? ( $all_columns[ $field_slug ] ?? $field_slug );
			$field_name      = rtrim( $field_name, ':' );
			$display_value   = '';

			\stackboost_log( "[UTM] Processing Field: Slug='{$field_slug}', Type='{$field_type}', Name='{$field_name}'", 'module-utm' );

			// Special handling for Description: It might be empty in the ticket object property,
			// but we can try to fetch it regardless of $field_value being empty initially.
			if ( 'df_description' === $field_type ) {
				\stackboost_log( "[UTM] Detected df_description field.", 'module-utm' );
				// Retrieve the initial report thread to get the description text.
				$description_thread = $ticket->get_description_thread();
				\stackboost_log( "[UTM] get_description_thread() result: " . ( $description_thread ? 'Object Found' : 'False/Null' ), 'module-utm' );

				if ( $description_thread && is_object( $description_thread ) ) {
					$display_value = $description_thread->body; // Direct access via __get magic method.
					\stackboost_log( "[UTM] Description body retrieved (length=" . strlen( $display_value ) . ")", 'module-utm' );
				} else {
					\stackboost_log( "[UTM] Description thread invalid.", 'module-utm' );
				}
			}

			if ( empty( $field_value ) && empty( $display_value ) ) {
				// If both original value and computed display value are empty, skip.
				\stackboost_log( "[UTM] Skipping '{$field_slug}' - Value is empty.", 'module-utm' );
				continue;
			}

			if ( ( is_string( $field_value ) && '0000-00-00 00:00:00' === $field_value ) ||
				 ( $field_value instanceof \DateTime && '0000-00-00 00:00:00' === $field_value->format( 'Y-m-d H:i:s' ) ) ) {
				\stackboost_log( "[UTM] Skipping '{$field_slug}' - Invalid Date.", 'module-utm' );
				continue;
			}

			switch ( $field_type ) {
				case 'cf_textfield':
				case 'cf_textarea':
				case 'cf_email':
				case 'cf_url':
				case 'cf_number':
				case 'cf_time':
				case 'df_id':
				case 'df_subject':
				case 'df_ip_address':
				case 'df_browser':
				case 'df_os':
				case 'df_source':
				case 'df_last_reply_source':
				case 'df_user_type':
				case 'df_customer_name':
				case 'df_customer_email':
					$display_value = (string) $field_value;
					break;
				case 'cf_html':
					$display_value = $field_value; // Do not escape HTML content.
					break;
				case 'df_description':
					// Already handled above to ensure it works even if $field_value is empty.
					break;
				case 'cf_date':
					$date_obj = clone $field_value;
					$date_obj->setTimezone( wp_timezone() );
					$display_value = $date_obj->format( get_option( 'date_format' ) );
					break;
				case 'cf_datetime':
				case 'df_date_created':
				case 'df_date_updated':
				case 'df_date_closed':
				case 'df_last_reply_on':
					$date_obj = clone $field_value;
					$date_obj->setTimezone( wp_timezone() );
					$display_value = $date_obj->format( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
					break;
				case 'cf_single_select':
				case 'cf_radio_button':
				case 'cf_file-attachment-single':
				case 'df_status':
				case 'df_priority':
				case 'df_category':
				case 'df_customer':
				case 'df_agent_created':
				case 'df_last_reply_by':
					$display_value = $field_value->name;
					break;
				case 'cf_multi_select':
				case 'cf_checkbox':
				case 'cf_file-attachment-multiple':
				case 'df_assigned_agent':
				case 'df_prev_assignee':
				case 'df_tags':
				case 'df_add_recipients':
					if ( is_array( $field_value ) ) {
						$names = [];
						foreach ( $field_value as $item ) {
							$names[] = $item->name;
						}
						$display_value = implode( ', ', $names );
					}
					break;
				default:
					$display_value = ''; // Skip unknown field types.
					break;
			}

			if ( ! empty( $display_value ) ) {
				if ( 'cf_html' === $field_type || 'df_description' === $field_type ) {
					$html_output .= '<tr><td style="white-space: nowrap;"><strong>' . esc_html( $field_name ) . ':</strong></td><td>' . $display_value . '</td></tr>';
				} else {
					$html_output .= '<tr><td style="white-space: nowrap;"><strong>' . esc_html( $field_name ) . ':</strong></td><td>' . esc_html( $display_value ) . '</td></tr>';
				}
			}
		}
		$html_output .= '</table>';
		return $html_output;
	}

}
