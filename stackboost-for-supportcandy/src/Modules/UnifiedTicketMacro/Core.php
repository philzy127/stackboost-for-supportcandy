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
	 * The ticket object to be saved during the shutdown hook.
	 *
	 * @var \WPSC_Ticket|null
	 */
	private ?\WPSC_Ticket $deferred_ticket_to_save = null;

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

		// The transient is the highest priority for new tickets.
		$transient_html = get_transient( 'stackboost_utm_temp_cache_' . $ticket->id );
		if ( false !== $transient_html ) {
			$cached_html = $transient_html;
			\stackboost_log( '[UTM] replace_utm_macro() - SUCCESS: Found and using TRANSIENT cache.', 'module-utm' );
		} else {
			\stackboost_log( '[UTM] replace_utm_macro() - INFO: Transient not found. Checking permanent cache.', 'module-utm' );
			$misc_data   = $ticket->misc;
			$cached_html = $misc_data['stackboost_utm_html'] ?? '';
			if ( ! empty( $cached_html ) ) {
				\stackboost_log( '[UTM] replace_utm_macro() - SUCCESS: Found and using PERMANENT cache.', 'module-utm' );
			} else {
				\stackboost_log( '[UTM] replace_utm_macro() - WARNING: No permanent cache found. Generating on-the-fly for ticket ID: ' . $ticket->id, 'module-utm' );
				$cached_html = $this->build_live_utm_html( $ticket );
				set_transient( 'stackboost_utm_temp_cache_' . $ticket->id, $cached_html, 60 );
				if ( ! has_action( 'shutdown', array( $this, 'deferred_save' ) ) ) {
					add_action( 'shutdown', array( $this, 'deferred_save' ) );
				}
				$this->deferred_ticket_to_save = $ticket;
			}
		}

		$str = str_replace( '{{stackboost_unified_ticket}}', $cached_html, $str );
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
	 * Primes the UTM cache on new ticket creation using a transient.
	 *
	 * @param \WPSC_Ticket $ticket The ticket object.
	 */
	public function prime_cache_on_creation( \WPSC_Ticket $ticket ) {
		\stackboost_log( '[UTM] prime_cache_on_creation() - ENTER for ticket ID: ' . $ticket->id, 'module-utm' );
		if ( ! $ticket->id ) {
			\stackboost_log( '[UTM] prime_cache_on_creation() - EXIT - Invalid ticket object.', 'module-utm' );
			return;
		}

		$html_to_cache = $this->build_live_utm_html( $ticket );
		\stackboost_log( '[UTM] prime_cache_on_creation() - HTML built. Length: ' . strlen( $html_to_cache ), 'module-utm' );

		// Use a transient for instant availability. Expires in 1 minute.
		set_transient( 'stackboost_utm_temp_cache_' . $ticket->id, $html_to_cache, 60 );
		\stackboost_log( '[UTM] prime_cache_on_creation() - Transient set for key: stackboost_utm_temp_cache_' . $ticket->id, 'module-utm' );

		// Defer the permanent save to avoid recursion.
		add_action( 'shutdown', array( $this, 'deferred_save' ) );
		\stackboost_log( '[UTM] prime_cache_on_creation() - Shutdown action registered.', 'module-utm' );

		// Pass the ticket object to the shutdown action.
		$this->deferred_ticket_to_save = $ticket;
		\stackboost_log( '[UTM] prime_cache_on_creation() - EXIT', 'module-utm' );
	}

	/**
	 * Saves the UTM HTML from the transient to permanent ticket meta.
	 * This runs on the 'shutdown' hook to avoid recursion.
	 */
	public function deferred_save() {
		\stackboost_log( '[UTM] deferred_save() - ENTER', 'module-utm' );
		if ( isset( $this->deferred_ticket_to_save ) && is_a( $this->deferred_ticket_to_save, 'WPSC_Ticket' ) ) {
			$ticket        = $this->deferred_ticket_to_save;
			\stackboost_log( '[UTM] deferred_save() - Processing ticket ID: ' . $ticket->id, 'module-utm' );
			$html_to_cache = get_transient( 'stackboost_utm_temp_cache_' . $ticket->id );

			if ( false !== $html_to_cache ) {
				\stackboost_log( '[UTM] deferred_save() - Transient found. Saving to ticket meta.', 'module-utm' );
				$misc_data                    = $ticket->misc;
				$misc_data['stackboost_utm_html'] = $html_to_cache;
				$ticket->misc                 = $misc_data;

				// This is now safe to call.
				$ticket->save();
				\stackboost_log( '[UTM] deferred_save() - Permanent cache saved.', 'module-utm' );

				// Clean up the transient.
				delete_transient( 'stackboost_utm_temp_cache_' . $ticket->id );
				\stackboost_log( '[UTM] deferred_save() - Transient deleted.', 'module-utm' );
			} else {
				\stackboost_log( '[UTM] deferred_save() - WARNING: Transient was not found for ticket ID: ' . $ticket->id, 'module-utm' );
			}
			unset( $this->deferred_ticket_to_save );
		} else {
			\stackboost_log( '[UTM] deferred_save() - EXIT - No deferred ticket to save.', 'module-utm' );
		}
		\stackboost_log( '[UTM] deferred_save() - EXIT', 'module-utm' );
	}

	/**
	 * Updates the permanent UTM cache when a ticket is updated.
	 *
	 * @param mixed $ticket_or_thread_or_id Can be a WPSC_Ticket, WPSC_Thread, or ticket ID.
	 */
	public function update_utm_cache( $ticket_or_thread_or_id ) {
		\stackboost_log( '[UTM] update_utm_cache() - ENTER', 'module-utm' );
		$ticket = null;
		if ( is_a( $ticket_or_thread_or_id, 'WPSC_Ticket' ) ) {
			$ticket = $ticket_or_thread_or_id;
		} elseif ( is_a( $ticket_or_thread_or_id, 'WPSC_Thread' ) ) {
			$ticket = $ticket_or_thread_or_id->ticket;
		} elseif ( is_numeric( $ticket_or_thread_or_id ) ) {
			$ticket = new \WPSC_Ticket( intval( $ticket_or_thread_or_id ) );
		}

		if ( ! is_a( $ticket, 'WPSC_Ticket' ) || ! $ticket->id ) {
			return;
		}

		$html_to_cache = $this->build_live_utm_html( $ticket );

		$misc_data                    = $ticket->misc;
		$misc_data['stackboost_utm_html'] = $html_to_cache;
		$ticket->misc                 = $misc_data;
		$ticket->save();
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
			$field_value = $ticket->{$field_slug};

			if ( empty( $field_value ) ) {
				continue;
			}
			if ( ( is_string( $field_value ) && '0000-00-00 00:00:00' === $field_value ) ||
				 ( $field_value instanceof \DateTime && '0000-00-00 00:00:00' === $field_value->format( 'Y-m-d H:i:s' ) ) ) {
				continue;
			}

			$field_name    = $rename_rules_map[ $field_slug ] ?? ( $all_columns[ $field_slug ] ?? $field_slug );
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
