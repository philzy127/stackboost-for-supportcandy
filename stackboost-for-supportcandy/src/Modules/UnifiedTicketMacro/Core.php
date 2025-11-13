<?php

namespace StackBoost\ForSupportCandy\Modules\UnifiedTicketMacro;

use DateTime;
use WPSC_Custom_Field;
use WPSC_Ticket;

/**
 * Core business logic for the Unified Ticket Macro feature.
 *
 * This class is responsible for building the HTML table for the macro
 * based on a ticket object and a set of configuration options.
 * It is designed to be as decoupled from WordPress as possible.
 *
 * @package StackBoost\ForSupportCandy\Modules\UnifiedTicketMacro
 */
class Core {

	/**
	 * Builds the HTML for the Unified Ticket Macro.
	 *
	 * @param WPSC_Ticket $ticket The SupportCandy ticket object.
	 * @param array       $config {
	 *     Configuration options for building the macro.
	 *
	 *     @type array $selected_fields An ordered array of field slugs to include.
	 *     @type array $rename_rules    A map of [field_slug => new_name].
	 *     @type array $all_columns     A map of [field_slug => friendly_name] for all available fields.
	 * }
	 *
	 * @return string The generated HTML table.
	 */
	public function build_utm_html( WPSC_Ticket $ticket, array $config ): string {
		error_log('[SB UTM Core] Starting HTML build for ticket ID: ' . $ticket->id);
		$selected_fields = $config['selected_fields'] ?? [];
		$rename_rules_raw = $config['rename_rules'] ?? [];
		$all_columns     = $config['all_columns'] ?? [];

		error_log('[SB UTM Core] Raw rename rules received: ' . print_r($rename_rules_raw, true));

		// Convert the rename rules from an array of objects to a simple map for easier lookup.
		$rename_map = [];
		foreach ($rename_rules_raw as $rule) {
			if (!empty($rule['field']) && !empty($rule['name'])) {
				$rename_map[$rule['field']] = $rule['name'];
			}
		}

		error_log('[SB UTM Core] Generated rename map: ' . print_r($rename_map, true));

		if ( empty( $selected_fields ) ) {
			return '<table></table>';
		}

		// Use the official API to get a complete list of all field types.
		$all_sc_fields   = WPSC_Custom_Field::$custom_fields;
		$field_types_map = [];
		foreach ( $all_sc_fields as $slug => $field_object ) {
			$field_type_class         = $field_object->type;
			$field_types_map[ $slug ] = $field_type_class::$slug;
		}

		$html_output = '<table>';

		foreach ( $selected_fields as $field_slug ) {
			$field_value = $ticket->{$field_slug};

			// Skip empty fields.
			if ( empty( $field_value ) ) {
				continue;
			}

			// Skip "zero dates" in both string and object formats.
			if (
				( is_string( $field_value ) && '0000-00-00 00:00:00' === $field_value ) ||
				( $field_value instanceof DateTime && '0000-00-00 00:00:00' === $field_value->format( 'Y-m-d H:i:s' ) )
			) {
				continue;
			}

			$field_name    = $rename_map[ $field_slug ] ?? ( $all_columns[ $field_slug ] ?? $field_slug );
			error_log("[SB UTM Core] Processing field: {$field_slug}. Final name: {$field_name}");
			$display_value = '';
			$field_type    = $field_types_map[ $field_slug ] ?? 'unknown';

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
				case 'df_description':
					$display_value = $field_value; // Do not escape HTML content.
					break;

				case 'cf_date':
					$display_value = $field_value->format( get_option( 'date_format' ) );
					break;
				case 'cf_datetime':
				case 'df_date_created':
				case 'df_date_updated':
				case 'df_date_closed':
				case 'df_last_reply_on':
					$display_value = $field_value->format( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
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
							if ( is_object( $item ) && isset( $item->name ) ) {
								$names[] = $item->name;
							}
						}
						$display_value = implode( ', ', $names );
					}
					break;

				default:
					// Silently skip unsupported field types.
					$display_value = '';
					break;
			}

			if ( ! empty( $display_value ) ) {
				// Don't escape known HTML fields.
				if ( 'cf_html' === $field_type || 'df_description' === $field_type ) {
					$html_output .= '<tr><td><strong>' . esc_html( $field_name ) . ':</strong></td><td>' . $display_value . '</td></tr>';
				} else {
					$html_output .= '<tr><td><strong>' . esc_html( $field_name ) . ':</strong></td><td>' . esc_html( $display_value ) . '</td></tr>';
				}
			}
		}
		$html_output .= '</table>';
		return $html_output;
	}
}
