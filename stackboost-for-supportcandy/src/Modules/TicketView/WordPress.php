<?php

namespace StackBoost\ForSupportCandy\Modules\TicketView;

use StackBoost\ForSupportCandy\Core\Module;

/**
 * WordPress Adapter for the Ticket View module.
 *
 * @package StackBoost\ForSupportCandy\Modules\TicketView
 */
class WordPress extends Module {

	/** @var WordPress|null */
	private static ?WordPress $instance = null;

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
		parent::__construct();
	}

	/**
	 * Get the slug for this module.
	 * @return string
	 */
	public function get_slug(): string {
		return 'ticket_view';
	}

	/**
	 * Initialize hooks.
	 */
	public function init_hooks() {
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		// Frontend enqueue might be needed if the ticket list is shown on frontend.
		// SupportCandy frontend usually uses a shortcode.
		// We can hook into wp_enqueue_scripts.
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

		// Register AJAX endpoints for ticket card
		add_action( 'wp_ajax_stackboost_get_ticket_card', [ $this, 'ajax_get_ticket_card_content' ] );
	}

	/**
	 * Enqueue scripts.
	 *
	 * @param string $hook_suffix
	 */
	public function enqueue_scripts( $hook_suffix = '' ) {
		stackboost_log( "TicketView::enqueue_scripts called with hook_suffix: {$hook_suffix}", 'ticket_view' );

		// Only enqueue if feature is enabled.
		$options = get_option( 'stackboost_settings' );

		// Debug log the options relevant to this feature
		stackboost_log( "TicketView::enqueue_scripts options: " . print_r( [
			'enable_page_last_loaded'      => $options['enable_page_last_loaded'] ?? 'not set',
			'page_last_loaded_placement'   => $options['page_last_loaded_placement'] ?? 'not set',
			'page_last_loaded_label'       => $options['page_last_loaded_label'] ?? 'not set',
			'page_last_loaded_format'      => $options['page_last_loaded_format'] ?? 'not set',
		], true ), 'ticket_view' );

		if ( empty( $options['enable_page_last_loaded'] ) ) {
			stackboost_log( "TicketView::enqueue_scripts: Feature disabled, skipping enqueue.", 'ticket_view' );
			return;
		}

		// For admin, check hook suffix.
		if ( is_admin() ) {
			// supportcandy_page_wpsc-tickets is the ticket list page.
			// supportcandy_page_wpsc-view-ticket is the individual ticket page.
			if ( 'supportcandy_page_wpsc-tickets' !== $hook_suffix ) {
				stackboost_log( "TicketView::enqueue_scripts: Not the target admin page. Hook suffix mismatch.", 'ticket_view' );
				return;
			}
		}
		// For frontend, we can't easily check for shortcode presence without parsing post content,
		// but we can rely on SupportCandy's assets usually being loaded.
		// To be safe and performant, we might want to check if WPSC is loaded or if we are on a page with the shortcode.
		// However, for simplicity and robustness (as user requested "Visuals: Both"), we'll enqueue if it's not admin.
		// A more refined check could be added if needed.

		stackboost_log( "TicketView::enqueue_scripts: Enqueueing script.", 'ticket_view' );

		wp_enqueue_script(
			'stackboost-page-last-loaded',
			STACKBOOST_PLUGIN_URL . 'src/Modules/TicketView/assets/js/page-last-loaded.js',
			[ 'jquery' ],
			STACKBOOST_VERSION,
			true
		);

		$placement = $options['page_last_loaded_placement'] ?? 'header';
		$label     = $options['page_last_loaded_label'] ?? 'Page Last Loaded: ';
		$format    = $options['page_last_loaded_format'] ?? 'default';

		wp_localize_script( 'stackboost-page-last-loaded', 'stackboostPageLastLoaded', [
			'enabled'        => true,
			'placement'      => $placement,
			'label'          => $label,
			'format'         => $format,
			'wp_time_format' => get_option( 'time_format' ),
		] );
	}

	/**
	 * Register settings fields for this module.
	 */
	public function register_settings() {
		$page_slug = 'stackboost-ticket-view'; // The new settings page.

		// Section: Ticket Details Card
		add_settings_section( 'stackboost_ticket_details_card_section', __( 'Ticket Details Card', 'stackboost-for-supportcandy' ), null, $page_slug );
		add_settings_field( 'stackboost_enable_ticket_details_card', __( 'Enable Feature', 'stackboost-for-supportcandy' ), [ $this, 'render_checkbox_field' ], $page_slug, 'stackboost_ticket_details_card_section', [ 'id' => 'enable_ticket_details_card', 'desc' => 'Shows a card with ticket details on right-click.' ] );

		add_settings_field(
			'stackboost_card_content_source',
			__( 'Content Source', 'stackboost-for-supportcandy' ),
			[ $this, 'render_select_field' ],
			$page_slug,
			'stackboost_ticket_details_card_section',
			[
				'id'      => 'card_content_source',
				'choices' => [
					'standard' => 'Standard (All Fields)',
					'utm'      => 'Unified Ticket Macro (Customizable)',
				],
				'desc'    => 'Choose how the main content of the card is generated.',
			]
		);

		add_settings_field( 'stackboost_card_show_description', __( 'Include Initial Description', 'stackboost-for-supportcandy' ), [ $this, 'render_checkbox_field' ], $page_slug, 'stackboost_ticket_details_card_section', [ 'id' => 'card_show_description', 'desc' => 'Append the ticket initial description below the fields.' ] );
		add_settings_field( 'stackboost_card_show_notes', __( 'Include Notes/Threads', 'stackboost-for-supportcandy' ), [ $this, 'render_checkbox_field' ], $page_slug, 'stackboost_ticket_details_card_section', [ 'id' => 'card_show_notes', 'desc' => 'Append recent ticket notes and replies.' ] );

		add_settings_field(
			'stackboost_card_notes_limit',
			__( 'Notes Limit', 'stackboost-for-supportcandy' ),
			[ $this, 'render_text_field' ],
			$page_slug,
			'stackboost_ticket_details_card_section',
			[
				'id'      => 'card_notes_limit',
				'default' => '5',
				'desc'    => 'Maximum number of notes to display (if enabled).',
			]
		);

		add_settings_field( 'stackboost_card_strip_images', __( 'Strip Images from Notes', 'stackboost-for-supportcandy' ), [ $this, 'render_checkbox_field' ], $page_slug, 'stackboost_ticket_details_card_section', [ 'id' => 'card_strip_images', 'desc' => 'Replace images in notes with [Image] to save space.' ] );


		add_settings_section( 'stackboost_separator_1', '', [ $this, 'render_hr_separator' ], $page_slug );

		// Section: General Cleanup
		add_settings_section( 'stackboost_general_cleanup_section', __( 'General Cleanup', 'stackboost-for-supportcandy' ), null, $page_slug );
		add_settings_field( 'stackboost_enable_hide_empty_columns', __( 'Hide Empty Columns', 'stackboost-for-supportcandy' ), [ $this, 'render_checkbox_field' ], $page_slug, 'stackboost_general_cleanup_section', [ 'id' => 'enable_hide_empty_columns', 'desc' => 'Automatically hide any column in the ticket list that is completely empty.' ] );
		add_settings_field( 'stackboost_enable_hide_priority_column', __( 'Hide Priority Column', 'stackboost-for-supportcandy' ), [ $this, 'render_checkbox_field' ], $page_slug, 'stackboost_general_cleanup_section', [ 'id' => 'enable_hide_priority_column', 'desc' => 'Hides the "Priority" column if all visible tickets have a priority of "Low".' ] );

		add_settings_section( 'stackboost_separator_general_cleanup_1', '', [ $this, 'render_hr_separator' ], $page_slug );

		// Section: Page Last Loaded Indicator
		add_settings_section( 'stackboost_page_last_loaded_section', __( 'Page Last Loaded Indicator', 'stackboost-for-supportcandy' ), null, $page_slug );
		add_settings_field( 'stackboost_enable_page_last_loaded', __( 'Enable Feature', 'stackboost-for-supportcandy' ), [ $this, 'render_checkbox_field' ], $page_slug, 'stackboost_page_last_loaded_section', [ 'id' => 'enable_page_last_loaded', 'desc' => 'Shows the time when the ticket list was last refreshed.' ] );
		add_settings_field(
			'stackboost_page_last_loaded_placement',
			__( 'Placement', 'stackboost-for-supportcandy' ),
			[ $this, 'render_select_field' ],
			$page_slug,
			'stackboost_page_last_loaded_section',
			[
				'id'      => 'page_last_loaded_placement',
				'choices' => [
					'header' => 'Header',
					'footer' => 'Footer',
					'both'   => 'Both',
				],
				'desc'    => 'Where to display the indicator.',
			]
		);
		add_settings_field(
			'stackboost_page_last_loaded_label',
			__( 'Label', 'stackboost-for-supportcandy' ),
			[ $this, 'render_text_field' ],
			$page_slug,
			'stackboost_page_last_loaded_section',
			[
				'id'      => 'page_last_loaded_label',
				'default' => 'Page Last Loaded: ',
				'desc'    => 'The text to display before the time.',
			]
		);
		add_settings_field(
			'stackboost_page_last_loaded_format',
			__( 'Time Format', 'stackboost-for-supportcandy' ),
			[ $this, 'render_select_field' ],
			$page_slug,
			'stackboost_page_last_loaded_section',
			[
				'id'      => 'page_last_loaded_format',
				'choices' => [
					'default' => 'WordPress Default',
					'12'      => '12-hour (e.g., 2:30 PM)',
					'24'      => '24-hour (e.g., 14:30)',
				],
				'desc'    => 'The format of the time display.',
			]
		);

		add_settings_section( 'stackboost_separator_2', '', [ $this, 'render_hr_separator' ], $page_slug );

		// Section: Ticket Type Hiding
		add_settings_section( 'stackboost_ticket_type_hiding_section', __( 'Hide Ticket Types from Non-Agents', 'stackboost-for-supportcandy' ), [ $this, 'render_ticket_type_hiding_description' ], $page_slug );
		add_settings_field( 'stackboost_enable_ticket_type_hiding', __( 'Enable Feature', 'stackboost-for-supportcandy' ), [ $this, 'render_checkbox_field' ], $page_slug, 'stackboost_ticket_type_hiding_section', [ 'id' => 'enable_ticket_type_hiding', 'desc' => 'Hide specific ticket types from non-agent users.' ] );

		$plugin_instance = \StackBoost\ForSupportCandy\WordPress\Plugin::get_instance();
		$custom_fields_choices = [];
		foreach ( $plugin_instance->get_supportcandy_columns() as $name ) {
			$custom_fields_choices[ $name ] = $name;
		}

		add_settings_field(
			'stackboost_ticket_type_custom_field_name',
			__( 'Custom Field Name', 'stackboost-for-supportcandy' ),
			[ $this, 'render_select_field' ],
			$page_slug,
			'stackboost_ticket_type_hiding_section',
			[
				'id'          => 'ticket_type_custom_field_name',
				'placeholder' => __( '-- Select a Custom Field --', 'stackboost-for-supportcandy' ),
				'choices'     => $custom_fields_choices,
				'desc'        => __( 'The custom field that represents the ticket type (e.g., "Ticket Category").', 'stackboost-for-supportcandy' ),
			]
		);

		add_settings_field( 'stackboost_ticket_types_to_hide', __( 'Ticket Types to Hide', 'stackboost-for-supportcandy' ), [ $this, 'render_textarea_field' ], $page_slug, 'stackboost_ticket_type_hiding_section', [ 'id' => 'ticket_types_to_hide', 'class' => 'regular-text', 'desc' => 'One ticket type per line. e.g., Network Access Request' ] );
	}

	/**
	 * Renders a checkbox field for a settings page.
	 */
	public function render_checkbox_field( array $args ) {
		$options = get_option( 'stackboost_settings' );
		$id = $args['id'];
		$checked = isset( $options[ $id ] ) && $options[ $id ] ? 'checked' : '';
		echo '<input type="checkbox" id="' . esc_attr( $id ) . '" name="stackboost_settings[' . esc_attr( $id ) . ']" value="1" ' . $checked . '>';
		if ( ! empty( $args['desc'] ) ) {
			echo '<p class="description">' . esc_html( $args['desc'] ) . '</p>';
		}
	}

	/**
	 * Renders a select field for a settings page.
	 */
	public function render_select_field( $args ) {
		$options = get_option( 'stackboost_settings' );
		$id = $args['id'];
		$selected = $options[ $id ] ?? '';
		echo '<select id="' . esc_attr( $id ) . '" name="stackboost_settings[' . esc_attr( $id ) . ']">';
		if ( ! empty( $args['placeholder'] ) ) {
			echo '<option value="">' . esc_html( $args['placeholder'] ) . '</option>';
		}
		foreach ( $args['choices'] as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $selected, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		if ( ! empty( $args['desc'] ) ) {
			echo '<p class="description">' . esc_html( $args['desc'] ) . '</p>';
		}
	}

	/**
	 * Renders a textarea field for a settings page.
	 */
	public function render_textarea_field( array $args ) {
		$options = get_option( 'stackboost_settings' );
		$id = $args['id'];
		$value = $options[ $id ] ?? '';
		echo '<textarea id="' . esc_attr( $id ) . '" name="stackboost_settings[' . esc_attr( $id ) . ']" class="' . esc_attr( $args['class'] ) . '">' . esc_textarea( $value ) . '</textarea>';
		if ( ! empty( $args['desc'] ) ) {
			echo '<p class="description">' . esc_html( $args['desc'] ) . '</p>';
		}
	}

	/**
	 * Renders a text field for a settings page.
	 */
	public function render_text_field( array $args ) {
		$options = get_option( 'stackboost_settings' );
		$id = $args['id'];
		$value = isset( $options[ $id ] ) ? $options[ $id ] : ( $args['default'] ?? '' );
		echo '<input type="text" id="' . esc_attr( $id ) . '" name="stackboost_settings[' . esc_attr( $id ) . ']" value="' . esc_attr( $value ) . '" class="regular-text">';
		if ( ! empty( $args['desc'] ) ) {
			echo '<p class="description">' . esc_html( $args['desc'] ) . '</p>';
		}
	}

	/**
	 * Renders a horizontal rule separator for settings pages.
	 */
	public function render_hr_separator() {
		echo '<hr>';
	}

	/**
	 * Render the description for the Hide Ticket Types section.
	 */
	public function render_ticket_type_hiding_description() {
		echo '<p>' . esc_html__( 'This feature hides specified ticket categories from the dropdown menu for any user who is not an agent.', 'stackboost-for-supportcandy' ) . '</p>';
	}

	/**
	 * AJAX Handler: Get Ticket Card Content
	 */
	public function ajax_get_ticket_card_content() {
		check_ajax_referer( 'stackboost_settings_nonce', 'nonce' );

		$ticket_id = isset( $_POST['ticket_id'] ) ? intval( $_POST['ticket_id'] ) : 0;
		if ( ! $ticket_id ) {
			wp_send_json_error( 'Invalid Ticket ID' );
		}

		// Security/Permissions Check
		$ticket = new \WPSC_Ticket( $ticket_id );
		if ( ! $ticket->id ) {
			wp_send_json_error( 'Ticket not found' );
		}

		// Use SupportCandy's permission check logic.
		// For frontend customers, we need to ensure they can only see their own tickets.
		// WPSC_Individual_Ticket::check_permission() is good but often redirects.
		// We can manually check or rely on the fact that if a user can't access it, we shouldn't show it.
		// A simple check:
		$current_user_id = get_current_user_id();
		$is_agent        = \WPSC_Functions::is_agent();
		$is_customer     = ! $is_agent;

		if ( $is_agent ) {
			// Agents usually have full access.
			// Assuming agent role implies access for now as per standard WPSC behavior for lists.
		} else {
			// Customer check: Must match customer ID.
			// SupportCandy stores customer details in the ticket object.
			if ( (int) $ticket->customer->id !== $current_user_id ) {
				// If not the owner and not an agent, deny.
				wp_send_json_error( 'Access Denied' );
			}
		}

		// Prepare Content
		$options = get_option( 'stackboost_settings' );
		$source  = $options['card_content_source'] ?? 'standard';
		$html    = '';

		// 1. Main Content
		if ( 'utm' === $source ) {
			// Use UTM Logic
			if ( class_exists( '\StackBoost\ForSupportCandy\Modules\UnifiedTicketMacro\Core' ) ) {
				$html .= '<div class="stackboost-card-section stackboost-card-main">';
				$html .= \StackBoost\ForSupportCandy\Modules\UnifiedTicketMacro\Core::get_instance()->build_live_utm_html( $ticket );
				$html .= '</div>';
			} else {
				$html .= '<p>UTM Module not active.</p>';
			}
		} else {
			// Standard View (Recreated for speed/robustness)
			$html .= '<div class="stackboost-card-section stackboost-card-main">';
			$html .= '<table class="wpsc-ticket-fields-table">'; // Use standard class or similar style

			// We iterate through fields similar to UTM but without the custom selection logic,
			// just showing all fields that are usually visible.
			// For simplicity and matching "Standard", we can use all custom fields.
			$all_fields = \WPSC_Custom_Field::$custom_fields;
			foreach ( $all_fields as $slug => $field ) {
				// Skip hidden or internal fields if necessary?
				// WPSC usually filters these.
				// For the "Standard" view, users expect to see what's in the widget.
				// The widget loops through enabled fields.
				// We'll trust the $custom_fields array is what's active.

				// Get formatted value using a helper if possible or manual switch
				// Re-using UTM helper logic would be smart, but we can't depend on UTM being active.
				// So we implement a lightweight renderer here.

				$val = $ticket->{$field->slug};
				if ( ! empty( $val ) ) {
					$label = $field->name;
					$display_val = $this->get_formatted_field_value( $ticket, $field );

					if ( $display_val !== '' ) {
						$html .= '<tr><th style="text-align:left; vertical-align:top;">' . esc_html( $label ) . ':</th><td style="vertical-align:top;">' . $display_val . '</td></tr>';
					}
				}
			}
			$html .= '</table>';
			$html .= '</div>';
		}

		// 2. Initial Description
		if ( ! empty( $options['card_show_description'] ) ) {
			$desc_thread = $ticket->get_description_thread();
			if ( $desc_thread && ! empty( $desc_thread->body ) ) {
				$html .= '<div class="stackboost-card-section stackboost-card-description">';
				$html .= '<h4>' . esc_html__( 'Description', 'stackboost-for-supportcandy' ) . '</h4>';
				$html .= '<div class="stackboost-card-body">' . wp_kses_post( $desc_thread->body ) . '</div>';
				$html .= '</div>';
			}
		}

		// 3. Notes / Threads
		if ( ! empty( $options['card_show_notes'] ) ) {
			$limit = isset( $options['card_notes_limit'] ) ? intval( $options['card_notes_limit'] ) : 5;
			$strip_images = ! empty( $options['card_strip_images'] );

			// Get threads
			// $ticket->get_threads() returns all threads.
			$threads = $ticket->get_threads();

			// Filter and Sort
			// Usually returned by ID ASC or DESC? We want DESC (newest first).
			// Let's reverse if needed. WPSC usually returns ASC (oldest first).
			$threads = array_reverse( $threads );

			$display_threads = [];
			$count = 0;

			foreach ( $threads as $thread ) {
				if ( $count >= $limit ) break;

				// Skip description thread (usually the first one created) if it's strictly a note list?
				// User said: "Initial Description... option... Include Notes... separate".
				// But "Description" is a thread.
				// We usually want to exclude the actual *description* thread from the "Notes" list if it's already shown above,
				// or just treat it as a thread.
				// Given the "Initial Description" feature, let's skip the thread that is the description
				// to avoid obvious duplication if that option is enabled.
				// However, user said "No" to de-duplication logic.
				// So we show ALL threads if asked.

				// Visibility Check
				if ( $is_customer ) {
					// Customers only see public threads
					if ( ! $thread->is_customer_view ) {
						continue;
					}
				} else {
					// Agents see everything
				}

				$display_threads[] = $thread;
				$count++;
			}

			if ( ! empty( $display_threads ) ) {
				$html .= '<div class="stackboost-card-section stackboost-card-notes">';
				$html .= '<h4>' . esc_html__( 'Recent Activity', 'stackboost-for-supportcandy' ) . '</h4>';
				$html .= '<ul class="stackboost-card-thread-list" style="padding-left: 0; list-style: none;">';

				foreach ( $display_threads as $thread ) {
					$body = $thread->body;

					// Strip Images
					if ( $strip_images ) {
						$body = preg_replace( '/<img[^>]+\>/i', ' <em>[' . __( 'Image', 'stackboost-for-supportcandy' ) . ']</em> ', $body );
					}

					// Format Date
					$date_obj = new \DateTime( $thread->date_created ); // UTC
					$date_obj->setTimezone( wp_timezone() );
					$date_str = $date_obj->format( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );

					$sender_name = 'Unknown';
					if ( isset( $thread->sender ) && is_object( $thread->sender ) ) {
						$sender_name = $thread->sender->name;
					}

					$html .= '<li style="margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 10px;">';
					$html .= '<strong>' . esc_html( $sender_name ) . '</strong> <small style="color:#777;">' . esc_html( $date_str ) . '</small><br/>';
					// Use specific allowed HTML for safety but allow formatting
					$html .= '<div class="stackboost-thread-body">' . wp_kses_post( $body ) . '</div>';
					$html .= '</li>';
				}

				$html .= '</ul>';
				$html .= '</div>';
			}
		}

		echo $html;
		wp_die();
	}

	/**
	 * Helper: Format Field Value (Simplified version of UTM logic)
	 *
	 * @param \WPSC_Ticket $ticket
	 * @param mixed $field
	 * @return string
	 */
	private function get_formatted_field_value( $ticket, $field ) {
		$value = $ticket->{$field->slug};
		$type = $field->type::$slug;

		if ( empty( $value ) ) return '';

		// Basic formatting for common types
		switch ( $type ) {
			case 'cf_textfield':
			case 'cf_textarea':
			case 'cf_email':
			case 'cf_url':
			case 'cf_number':
				return esc_html( $value );

			case 'cf_html':
				return wp_kses_post( $value );

			case 'cf_date':
			case 'cf_datetime':
			case 'df_date_created':
			case 'df_date_updated':
				if ( $value instanceof \DateTime ) {
					$d = clone $value;
					$d->setTimezone( wp_timezone() );
					return $d->format( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
				}
				if ( is_string( $value ) && $value !== '0000-00-00 00:00:00' ) {
					return esc_html( $value );
				}
				return '';

			case 'df_status':
			case 'df_priority':
			case 'df_category':
			case 'cf_single_select':
				return isset( $value->name ) ? esc_html( $value->name ) : '';

			case 'cf_multi_select':
			case 'df_assigned_agent':
				if ( is_array( $value ) ) {
					$names = array_map( function( $v ) { return $v->name; }, $value );
					return esc_html( implode( ', ', $names ) );
				}
				return '';

			default:
				// Fallback for objects/arrays to string if possible
				if ( is_string( $value ) ) return esc_html( $value );
				return '';
		}
	}
}
