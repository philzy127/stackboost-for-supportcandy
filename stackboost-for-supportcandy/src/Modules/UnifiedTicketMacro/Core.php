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
	 * Universal cache update handler and logger.
	 * It logs all arguments passed by the hook and then updates the cache.
	 *
	 * @param mixed ...$args The arguments passed by the hook.
	 */
	public function maybe_update_utm_cache( ...$args ) {
		\stackboost_log( '[UTM HOOK] A ticket event fired. Full arguments:' );
		\stackboost_log( $args );

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
			$this->update_utm_cache( $ticket );
		}
	}

	/**
	 * Logs ticket creation and primes the UTM cache.
	 *
	 * @param \WPSC_Ticket $ticket The ticket object.
	 */
	public function prime_cache_on_creation( \WPSC_Ticket $ticket ) {
		\stackboost_log( '[UTM HOOK] wpsc_create_ticket fired. Ticket object:' );
		\stackboost_log( $ticket );

		if ( ! $ticket->id ) {
			return;
		}
		$this->update_utm_cache( $ticket );
	}

	/**
	 * Log ticket deletion.
	 *
	 * @param \WPSC_Ticket $ticket The ticket object.
	 */
	public function log_ticket_deletion( \WPSC_Ticket $ticket ) {
		\stackboost_log( '[UTM HOOK] wpsc_delete_ticket fired. Ticket object:' );
		\stackboost_log( $ticket );
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

		$all_fields = \WPSC_Custom_Field::$custom_fields;

		$html_output = '<table>';
		foreach ( $selected_fields as $field_slug ) {
			$display_value = $this->get_formatted_field_value( $field_slug, $ticket );

			if ( empty( $display_value ) || ( is_string( $display_value ) && '0000-00-00 00:00:00' === $display_value ) ) {
				continue;
			}

			$field_name    = $rename_rules_map[ $field_slug ] ?? ( $all_columns[ $field_slug ] ?? $field_slug );
			$custom_field  = $all_fields[ $field_slug ] ?? null;
			$field_type_slug = $custom_field ? $custom_field->type::$slug : '';

			if ( 'cf_html' === $field_type_slug || 'df_description' === $field_type_slug ) {
				// Use wp_kses_post for fields that are allowed to have HTML.
				$html_output .= '<tr><td><strong>' . esc_html( $field_name ) . ':</strong></td><td>' . wp_kses_post( $display_value ) . '</td></tr>';
			} else {
				$html_output .= '<tr><td><strong>' . esc_html( $field_name ) . ':</strong></td><td>' . esc_html( $display_value ) . '</td></tr>';
			}
		}
		$html_output .= '</table>';
		return $html_output;
	}

	/**
	 * Get the formatted display value for a given ticket field.
	 *
	 * This function provides a robust, multi-path retrieval logic to handle
	 * the different ways SupportCandy fields store and format their values.
	 *
	 * @param string      $field_slug The slug of the field.
	 * @param \WPSC_Ticket $ticket     The ticket object.
	 * @return string The formatted value.
	 */
	private function get_formatted_field_value( string $field_slug, \WPSC_Ticket $ticket ): string {
		$all_fields   = \WPSC_Custom_Field::$custom_fields;
		$custom_field = $all_fields[ $field_slug ] ?? null;
		$raw_value    = $ticket->{$field_slug} ?? '';

		if ( ! $custom_field ) {
			// Not a custom field, or field registry failed. Return the raw value.
			return (string) $raw_value;
		}

		$type_class = $custom_field->type;

		// Path 1: Check for the 'get_field_value' method (used by most complex custom fields).
		if ( method_exists( $type_class, 'get_field_value' ) ) {
			return $type_class::get_field_value( $custom_field, $raw_value );
		}

		// Path 2: Check for the 'get_ticket_field_val' method (used by some default fields).
		if ( method_exists( $type_class, 'get_ticket_field_val' ) ) {
			return $type_class::get_ticket_field_val( $custom_field, $ticket );
		}

		// Path 3: Fallback for simple fields (like 'Browser') that have no special formatter.
		return (string) $raw_value;
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
