<?php

namespace StackBoost\ForSupportCandy\Modules\UnifiedTicketMacro;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
	public function build_live_utm_html( \WPSC_Ticket $ticket ): string {
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

					// Filter out "Not Applicable" text (often used as a placeholder).
					$clean_text = trim( wp_strip_all_tags( $display_value ) );
					if ( 0 === strcasecmp( $clean_text, 'Not Applicable' ) ) {
						\stackboost_log( "[UTM] Description is 'Not Applicable'. Clearing value.", 'module-utm' );
						$display_value = '';
					}
				} else {
					\stackboost_log( "[UTM] Description thread invalid.", 'module-utm' );
				}

				// If description is empty after retrieval/cleaning, skip it entirely.
				// We do this here to ignore the original $field_value which might be unreliable.
				if ( empty( $display_value ) || '' === trim( wp_strip_all_tags( $display_value ) ) ) {
					\stackboost_log( "[UTM] Skipping 'description' - Value is empty or effectively empty.", 'module-utm' );
					continue;
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
					// Fix alignment issue caused by paragraph margins in rich text fields.
					$display_value = str_replace( '<p>', '<p style="margin:0;">', $display_value );
					$html_output .= '<tr><td style="white-space: nowrap; vertical-align: top;"><strong>' . esc_html( $field_name ) . ':</strong></td><td style="vertical-align: top;">' . $display_value . '</td></tr>';
				} else {
					$html_output .= '<tr><td style="white-space: nowrap; vertical-align: top;"><strong>' . esc_html( $field_name ) . ':</strong></td><td style="vertical-align: top;">' . esc_html( $display_value ) . '</td></tr>';
				}
			}
		}
		$html_output .= '</table>';
		return $html_output;
	}

	/**
	 * Renders the conversation threads for a ticket.
	 *
	 * @param \WPSC_Ticket $ticket The ticket object.
	 * @param bool         $include_private Whether to include private notes (for agents).
	 * @param string       $image_handling How to handle images ('fit', 'strip', 'placeholder').
	 * @param int          $limit Maximum number of threads to return (0 for unlimited).
	 * @return string HTML of the threads.
	 */
	public function render_ticket_threads( \WPSC_Ticket $ticket, bool $include_private = false, string $image_handling = 'fit', int $limit = 0 ): string {
		// Define which thread types to fetch
		// Public always gets 'report' and 'reply'.
		$types = [ 'report', 'reply' ];
		if ( $include_private ) {
			$types[] = 'note';
		}

		// Fetch threads using SupportCandy's method:
		// get_threads( $page_no = 1, $items_per_page = 0, $types = array(), $orderby = 'date_created', $order = 'DESC' )
		// If limit is 0, we want all threads.
		// Note: WPSC's get_threads pagination might expect items_per_page > 0 for limiting.
		// If 0 is passed, it returns all.
		$threads = $ticket->get_threads( 1, $limit, $types, 'date_created', 'ASC' );

		if ( empty( $threads ) ) {
			return '';
		}

		$html = '<div class="stackboost-ticket-history">';
		// Internal header removed to allow wrapping in WPSC widget structure.
		// $html .= '<h4>' . esc_html__( 'Conversation History', 'stackboost-for-supportcandy' ) . '</h4>';

		foreach ( $threads as $thread ) {
			$html .= '<div class="stackboost-thread-item">';

			// Header: Author + Date + Type
			$author_name = $thread->customer ? $thread->customer->name : __( 'Unknown', 'stackboost-for-supportcandy' );
			$date_str = $thread->date_created->setTimezone( wp_timezone() )->format( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );

			$type_label = '';
			switch ( $thread->type ) {
				case 'report': $type_label = __( 'Reported', 'stackboost-for-supportcandy' ); break;
				case 'reply': $type_label = __( 'Replied', 'stackboost-for-supportcandy' ); break;
				case 'note': $type_label = __( 'Private Note', 'stackboost-for-supportcandy' ); break;
			}

			$style_bg = ( 'note' === $thread->type ) ? 'background: #fff8e1;' : 'background: #f9f9f9;';
			$style_border = ( 'note' === $thread->type ) ? 'border-left: 4px solid #fbc02d;' : 'border-left: 4px solid #ddd;';

			$html .= '<div style="padding: 8px; margin-bottom: 10px; ' . $style_bg . $style_border . '">';
			$html .= '<strong>' . esc_html( $author_name ) . '</strong> <span style="color:#777; font-size: 0.9em;">(' . esc_html( $type_label ) . ')</span>';
			$html .= '<div style="font-size: 0.8em; color: #999;">' . esc_html( $date_str ) . '</div>';

			// Body content processing
			// We sanitize FIRST to ensure the content is safe.
			$body = wp_kses_post( $thread->body );

			// Handle Images
			// We apply our trusted regex replacements AFTER sanitization so our onclick attributes are not stripped.
			if ( 'strip' === $image_handling ) {
				$body = preg_replace( '/<img[^>]+\>/i', '', $body );
			} elseif ( 'placeholder' === $image_handling ) {
				// Replace image tags with a clickable [Image] link that opens the Lightbox
				$body = preg_replace(
					'/<img\s+[^>]*?src=["\']([^"\']+)["\'][^>]*?>/i',
					'<a href="$1" onclick="if(window.stackboostOpenWidgetModal) { stackboostOpenWidgetModal(event, this.href); return false; } else { return true; }" style="font-style:italic; color:#0073aa; cursor:pointer;">[' . __( 'Image', 'stackboost-for-supportcandy' ) . ']</a>',
					$body
				);
			} else {
				// 'fit' (default) - inject max-width style AND lightbox link
				// Wrap images in a link that opens the Lightbox
				$body = preg_replace(
					'/(<img\s+[^>]*?src=["\']([^"\']+)["\'][^>]*?>)/i',
					'<a href="$2" onclick="if(window.stackboostOpenWidgetModal) { stackboostOpenWidgetModal(event, this.href); return false; } else { return true; }" style="cursor:pointer;">$1</a>',
					$body
				);

				// Ensure max-width style is present (backup to CSS)
				$body = preg_replace( '/(<img\s+[^>]*?style=["\'])([^"\']*?)(["\'])/i', '$1$2; max-width:100%; height:auto;$3', $body );
				$body = preg_replace( '/(<img\s+)(?![^>]*?style=)([^>]*?)(\/?>)/i', '$1$2 style="max-width:100%; height:auto;"$3', $body );
			}

			$html .= '<div class="stackboost-thread-body" style="margin-top: 5px;">' . $body . '</div>';

			$html .= '</div>'; // End container
			$html .= '</div>'; // End item
		}
		$html .= '</div>';

		return $html;
	}

}
