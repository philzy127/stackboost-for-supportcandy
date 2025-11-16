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
	 * Constructor.
	 */
	private function __construct() {
		// Actions and filters will be added in the WordPress.php file.
	}

	/**
	 * Universal cache update handler.
	 *
	 * This function can be called by any action or filter. It safely searches its
	 * arguments for a WPSC_Ticket object and, if found, triggers the cache update.
	 * This prevents fatal errors when hooked into functions with varying signatures.
	 */
	public function maybe_update_utm_cache() {
		\stackboost_log( '[UTM TRACE] Core.php -> maybe_update_utm_cache() - Universal handler fired.' );
		$ticket    = null;
		$ticket_id = 0;
		$args      = func_get_args();

		// First, try to find a direct WPSC_Ticket object.
		foreach ( $args as $arg ) {
			if ( is_a( $arg, 'WPSC_Ticket' ) ) {
				$ticket = $arg;
				$ticket_id = $ticket->id;
				\stackboost_log( '[UTM TRACE] Found direct WPSC_Ticket object with ID: ' . $ticket_id );
				break;
			}
		}

		// If no direct ticket object, try to find the ID from the email subject.
		if ( ! $ticket ) {
			\stackboost_log( '[UTM TRACE] No direct ticket object found. Searching for ticket ID in other arguments...' );
			$subject = '';
			foreach ( $args as $arg ) {
				if ( is_a( $arg, 'WPSC_Email_Notifications' ) && ! empty( $arg->subject ) ) {
					$subject = $arg->subject;
					break;
				}
				if ( is_a( $arg, 'WPSC_Background_Email' ) ) {
					// The subject is in a private property, so we need to reflect to get it.
					try {
						$reflection = new \ReflectionProperty( 'WPSC_Background_Email', 'data' );
						$reflection->setAccessible( true );
						$data    = $reflection->getValue( $arg );
						$subject = $data['subject'] ?? '';
						if ( ! empty( $subject ) ) {
							break;
						}
					} catch ( \ReflectionException $e ) {
						// Could not reflect.
					}
				}
			}

			if ( ! empty( $subject ) ) {
				\stackboost_log( '[UTM TRACE] Found email subject: "' . $subject . '"' );
				preg_match( '/\[Ticket #(\d+)\]/', $subject, $matches );
				if ( isset( $matches[1] ) ) {
					$ticket_id = (int) $matches[1];
					\stackboost_log( '[UTM TRACE] Extracted ticket ID ' . $ticket_id . ' from subject.' );
				}
			}
		}

		// If we have a ticket ID, but not a ticket object, create one.
		if ( ! $ticket && $ticket_id > 0 && class_exists( 'WPSC_Ticket' ) ) {
			$ticket = new \WPSC_Ticket( $ticket_id );
		}

		if ( $ticket && $ticket->id ) {
			\stackboost_log( '[UTM TRACE] Proceeding to update cache for ticket ID: ' . $ticket->id );
			$this->update_utm_cache( $ticket );
		} else {
			\stackboost_log( '[UTM TRACE] No WPSC_Ticket object or valid ticket ID could be found. Aborting cache update.' );
		}
	}

	/**
	 * A wrapper for WordPress filters.
	 *
	 * This calls the universal cache updater and then returns the first argument,
	 * ensuring the filter chain is not broken.
	 *
	 * @return mixed The first argument passed to the function.
	 */
	public function maybe_update_utm_cache_and_pass_through() {
		$args = func_get_args();
		call_user_func_array( array( $this, 'maybe_update_utm_cache' ), $args );

		// Filters must return the first argument passed to them.
		return $args[0];
	}

	/**
	 * Primes the UTM cache on new ticket creation using a transient.
	 *
	 * @param \WPSC_Ticket $ticket The ticket object.
	 */
	public function prime_cache_on_creation( \WPSC_Ticket $ticket ) {
		\stackboost_log( '[UTM HOOK] New ticket created. ID: ' . $ticket->id );
		\stackboost_log( '[UTM] prime_cache_on_creation() - ENTER for ticket ID: ' . $ticket->id );
		if ( ! $ticket->id ) {
			\stackboost_log( '[UTM] prime_cache_on_creation() - EXIT - Invalid ticket object.' );
			return;
		}

		$html_to_cache = $this->build_live_utm_html( $ticket );
		\stackboost_log( '[UTM] prime_cache_on_creation() - HTML built. Length: ' . strlen( $html_to_cache ) );

		// Use a transient for instant availability. Expires in 1 minute.
		set_transient( 'stackboost_utm_temp_cache_' . $ticket->id, $html_to_cache, 60 );
		\stackboost_log( '[UTM] prime_cache_on_creation() - Transient set for key: stackboost_utm_temp_cache_' . $ticket->id );
		\stackboost_log( '[UTM] prime_cache_on_creation() - EXIT' );
	}

	/**
	 * Updates the UTM cache when a ticket is updated.
	 *
	 * @param mixed $ticket_or_thread_or_id Can be a WPSC_Ticket, WPSC_Thread, or ticket ID.
	 */
	public function update_utm_cache( $ticket_or_thread_or_id ) {
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

		set_transient( 'stackboost_utm_temp_cache_' . $ticket->id, $html_to_cache, 60 );
	}

	/**
	 * Replaces the {{stackboost_unified_ticket}} macro in email data.
	 *
	 * @param array       $data   The email data array.
	 * @param \WPSC_Thread $thread The thread object.
	 * @return array The modified email data.
	 */
	public function replace_utm_macro( array $data, \WPSC_Thread $thread ): array {
		\stackboost_log( '[UTM] replace_utm_macro() - ENTER' );
		if ( ! isset( $data['body'] ) || false === strpos( $data['body'], '{{stackboost_unified_ticket}}' ) ) {
			\stackboost_log( '[UTM] replace_utm_macro() - EXIT - Macro not found in email body.' );
			return $data;
		}
		$ticket = $thread->ticket;
		if ( ! is_a( $ticket, 'WPSC_Ticket' ) ) {
			\stackboost_log( '[UTM] replace_utm_macro() - EXIT - Invalid ticket object from thread.' );
			return $data;
		}
		\stackboost_log( '[UTM] replace_utm_macro() - Processing for ticket ID: ' . $ticket->id );

		// Attempt to get the HTML from the transient.
		$cached_html = get_transient( 'stackboost_utm_temp_cache_' . $ticket->id );

		// If the transient is not found, build the HTML on-the-fly.
		if ( false === $cached_html ) {
			\stackboost_log( '[UTM] replace_utm_macro() - INFO: Transient cache not found. Building live HTML for ticket ID: ' . $ticket->id );
			$cached_html = $this->build_live_utm_html( $ticket );
		} else {
			\stackboost_log( '[UTM] replace_utm_macro() - SUCCESS: Found and using TRANSIENT cache for ticket ID: ' . $ticket->id );
		}

		$data['body'] = str_replace( '{{stackboost_unified_ticket}}', $cached_html, $data['body'] );
		\stackboost_log( '[UTM] replace_utm_macro() - EXIT - Macro replacement complete.' );
		return $data;
	}

	/**
	 * Builds the HTML table for the UTM based on current settings and ticket data.
	 *
	 * @param \WPSC_Ticket $ticket The ticket object.
	 * @return string The generated HTML.
	 */
	private function build_live_utm_html( \WPSC_Ticket $ticket ): string {
		\stackboost_log( '[UTM] build_live_utm_html() - ENTER for ticket ID: ' . $ticket->id );
		$options          = get_option( 'stackboost_settings', [] );
		$is_enabled       = $options['utm_enabled'] ?? false;

		if ( ! $is_enabled ) {
			\stackboost_log( '[UTM] build_live_utm_html() - EXIT - Feature is disabled in settings.' );
			return ''; // Return empty string if the feature is disabled.
		}

		$selected_fields  = $options['utm_columns'] ?? [];
		$rename_rules_raw = $options['utm_rename_rules'] ?? [];
		\stackboost_log( '[UTM] build_live_utm_html() - Found ' . count( $selected_fields ) . ' selected fields.' );

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
			\stackboost_log( '[UTM BUILDER] Processing field: ' . $field_slug );
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

			// Special handling for customer fields to prevent warnings.
			if ( 'name' === $field_slug ) {
				\stackboost_log('[UTM DIAGNOSTIC] Type of $ticket->customer: ' . gettype($ticket->customer));
				if (is_object($ticket->customer)) {
					\stackboost_log('[UTM DIAGNOSTIC] Class of $ticket->customer: ' . get_class($ticket->customer));
				}
				\stackboost_log('[UTM DIAGNOSTIC] Content of $ticket->customer: ' . print_r($ticket->customer, true));
				if ( isset( $ticket->customer ) && is_a( $ticket->customer, 'WPSC_Customer' ) && isset( $ticket->customer->name ) ) {
					$display_value = $ticket->customer->name;
				}
			} elseif ( 'email' === $field_slug ) {
				if ( isset( $ticket->customer ) && is_a( $ticket->customer, 'WPSC_Customer' ) && isset( $ticket->customer->email ) ) {
					$display_value = $ticket->customer->email;
				}
			} else {
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

	/**
	 * Register the macro with SupportCandy.
	 *
	 * @param array $macros The existing macros.
	 * @return array The modified macros.
	 */
	public function register_macro( array $macros ): array {
		$macros[] = array(
			'tag'   => '{{stackboost_unified_ticket}}',
			'title' => esc_attr__( 'Unified Ticket Macro', 'stackboost-for-supportcandy' ),
		);
		return $macros;
	}
}
