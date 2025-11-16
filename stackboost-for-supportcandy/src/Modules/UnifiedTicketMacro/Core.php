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
	 * It safely searches its arguments for a WPSC_Ticket object and, if found, triggers the cache update.
	 *
	 * @param mixed ...$args The arguments passed by the hook.
	 */
	public function maybe_update_utm_cache( ...$args ) {
		$ticket = null;
		foreach ( $args as $arg ) {
			if ( is_a( $arg, 'WPSC_Ticket' ) ) {
				$ticket = $arg;
				break;
			}
			if ( is_a( $arg, 'WPSC_Thread' ) ) {
				$ticket = $arg->ticket;
				break;
			}
		}

		if ( $ticket && $ticket->id ) {
			\stackboost_log( '[UTM HOOK] A ticket event fired. Updating cache for ticket ID: ' . $ticket->id );
			$this->update_utm_cache( $ticket );
		}
	}

	/**
	 * Primes the UTM cache on new ticket creation.
	 *
	 * @param \WPSC_Ticket $ticket The ticket object.
	 */
	public function prime_cache_on_creation( \WPSC_Ticket $ticket ) {
		if ( ! $ticket->id ) {
			return;
		}
		\stackboost_log( '[UTM HOOK] wpsc_create_ticket fired. Priming cache for ticket ID: ' . $ticket->id );
		$this->update_utm_cache( $ticket );
	}

	/**
	 * Log ticket deletion.
	 *
	 * @param \WPSC_Ticket $ticket The ticket object.
	 */
	public function log_ticket_deletion( \WPSC_Ticket $ticket ) {
		if ( $ticket && $ticket->id ) {
			\stackboost_log( '[UTM HOOK] Ticket deleted. Ticket ID: ' . $ticket->id );
		}
	}


	/**
	 * Updates the UTM cache when a ticket is updated.
	 *
	 * @param \WPSC_Ticket $ticket The ticket object.
	 */
	public function update_utm_cache( \WPSC_Ticket $ticket ) {
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
		if ( ! isset( $data['body'] ) || false === strpos( $data['body'], '{{stackboost_unified_ticket}}' ) ) {
			return $data;
		}
		$ticket = $thread->ticket;
		if ( ! is_a( $ticket, 'WPSC_Ticket' ) ) {
			return $data;
		}

		$cached_html = get_transient( 'stackboost_utm_temp_cache_' . $ticket->id );
		if ( false === $cached_html ) {
			$cached_html = $this->build_live_utm_html( $ticket );
		}

		$data['body'] = str_replace( '{{stackboost_unified_ticket}}', $cached_html, $data['body'] );
		return $data;
	}

	/**
	 * Builds the HTML table for the UTM based on current settings and ticket data.
	 *
	 * @param \WPSC_Ticket $ticket The ticket object.
	 * @return string The generated HTML.
	 */
	private function build_live_utm_html( \WPSC_Ticket $ticket ): string {
		$options          = get_option( 'stackboost_settings', [] );
		$is_enabled       = $options['utm_enabled'] ?? false;
		if ( ! $is_enabled ) {
			return '';
		}
		$selected_fields  = $options['utm_columns'] ?? [];
		$rename_rules_raw = $options['utm_rename_rules'] ?? [];

		$rename_rules_map = [];
		foreach ( $rename_rules_raw as $rule ) {
			if ( isset( $rule['field'] ) && ! empty( $rule['name'] ) ) {
				$rename_rules_map[ $rule['field'] ] = $rule['name'];
			}
		}

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

		$all_fields      = \WPSC_Custom_Field::$custom_fields;
		$field_types_map = [];
		foreach ( $all_fields as $slug => $field_object ) {
			$field_type_class         = $field_object->type;
			$field_types_map[ $slug ] = $field_type_class::$slug;
		}

		$html_output = '<table>';
		foreach ( $selected_fields as $field_slug ) {
			$field_value = $ticket->{$field_slug};

			if ( empty( $field_value ) || ( is_string( $field_value ) && '0000-00-00 00:00:00' === $field_value ) ) {
				continue;
			}

			$field_name    = $rename_rules_map[ $field_slug ] ?? ( $all_columns[ $field_slug ] ?? $field_slug );
			$display_value = '';
			$field_type    = $field_types_map[ $field_slug ] ?? 'unknown';

			if ( 'name' === $field_slug ) {
				if ( isset( $ticket->customer ) && is_a( $ticket->customer, 'WPSC_Customer' ) && isset( $ticket->customer->name ) ) {
					$display_value = $ticket->customer->name;
				}
			} elseif ( 'email' === $field_slug ) {
				if ( isset( $ticket->customer ) && is_a( $ticket->customer, 'WPSC_Customer' ) && isset( $ticket->customer->email ) ) {
					$display_value = $ticket->customer->email;
				}
			} else {
				$display_value = \WPSC_Custom_Field::get_field_value_display( $field_slug, $field_value, $ticket );
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
