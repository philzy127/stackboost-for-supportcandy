<?php

namespace StackBoost\ForSupportCandy\Modules\ChatBubbles;

use StackBoost\ForSupportCandy\Modules\ChatBubbles\Admin\Settings;

/**
 * Chat Bubbles Module Core.
 *
 * Handles the generation of CSS for Chat Bubbles and Email modifications.
 */
class Core {

	/** @var Core|null */
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
		// Reduced noise: Core init is implied by WP Adapter init.
		// if ( function_exists( 'stackboost_log' ) ) {
		// 	stackboost_log( 'ChatBubbles: Core Initialized.', 'chat_bubbles' );
		// }
		// Initialize the WordPress adapter
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 */
	public function init_hooks() {
		// Enqueue CSS for Ticket View
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_ticket_styles' ] );

		// Hook into Email Notifications to style the body
		// We use `wpsc_en_before_sending` to intercept all emails just before they go out.
		// This gives us access to the fully constructed body and the thread object.
		add_filter( 'wpsc_en_before_sending', [ $this, 'process_email_content' ] );

		// Hook to replace ticket history macros with styled content
		// Priority 1 to attempt to run BEFORE SupportCandy core replacement
		add_filter( 'wpsc_replace_macros', [ $this, 'replace_history_macro' ], 1, 3 );
	}

	/**
	 * Enqueue Ticket View Styles.
	 * Now supports both Admin and Frontend contexts.
	 *
	 * @param string|null $hook_suffix The hook suffix for admin pages, or 'frontend' string if called manually for frontend.
	 */
	public function enqueue_ticket_styles( $hook_suffix = null ) {
		// Determine context
		$is_frontend = ( $hook_suffix === 'frontend' );

		// Context Check: Admin vs Frontend
		$handle = '';
		if ( ! $is_frontend ) {
			// Admin Check
			if ( ! is_string( $hook_suffix ) ) {
				return;
			}
			// Only load on Ticket View or Ticket List
			if ( strpos( $hook_suffix, 'wpsc-tickets' ) === false && strpos( $hook_suffix, 'wpsc-view-ticket' ) === false ) {
				return; // Exit silent if not a ticket page
			}
			$handle = 'wpsc-admin';
		} else {
			// Frontend Check
			// We register a dummy handle to attach our inline styles to, ensuring they load anywhere
			// SupportCandy might be present (shortcodes, widgets, etc).
			$handle = 'stackboost-chat-bubbles-frontend';
			wp_register_style( $handle, false );
			wp_enqueue_style( $handle );
		}

		// Only log if we passed the checks and are actually about to work
		if ( function_exists( 'stackboost_log' ) ) {
			$hook_label = $is_frontend ? 'frontend' : ( $hook_suffix ?? 'unknown' );
			stackboost_log( 'ChatBubbles: enqueue_ticket_styles called. Hook: ' . $hook_label, 'chat_bubbles' );
		}

		// Check ticket specific enable switch
		// Note: We use the same 'chat_bubbles_enable_ticket' setting for both Admin and Frontend uniformity.
		$options = get_option( 'stackboost_settings', [] );
		if ( empty( $options['chat_bubbles_enable_ticket'] ) ) {
			if ( function_exists( 'stackboost_log' ) ) {
				stackboost_log( 'ChatBubbles: Disabled via settings (Ticket View).', 'chat_bubbles' );
			}
			return;
		}

		$css = $this->generate_css();
		wp_add_inline_style( $handle, $css );

		if ( function_exists( 'stackboost_log' ) ) {
			stackboost_log( 'ChatBubbles: Styles enqueued successfully for context: ' . ( $is_frontend ? 'Frontend' : 'Admin' ), 'chat_bubbles' );
		}
	}

	/**
	 * Replaces {ticket_history} macro with styled bubbles.
	 *
	 * @param string       $str    The full email body string (or replacement value).
	 * @param \WPSC_Ticket $ticket The ticket object.
	 * @param string       $macro  The macro being replaced (without braces).
	 * @return string The modified body string.
	 */
	public function replace_history_macro( $str, $ticket, $macro ) {
		// Log EVERY macro passing through to debug why history isn't firing
		if ( function_exists( 'stackboost_log' ) ) {
			// We use a lighter log level if possible, or just a prefix to identify it easily
			stackboost_log( "DEBUG: Macro check: '{$macro}' (Ticket ID: {$ticket->id})", 'chat_bubbles' );
		}

		// Only target history macros
		if ( ! in_array( $macro, [ 'ticket_history', 'ticket_threads' ] ) ) {
			return $str;
		}

		if ( function_exists( 'stackboost_log' ) ) {
			stackboost_log( "ChatBubbles: replace_history_macro MATCHED for macro '{$macro}'", 'chat_bubbles' );
		}

		// Check email specific enable switch
		$options = get_option( 'stackboost_settings', [] );
		if ( empty( $options['chat_bubbles_enable_email'] ) ) {
			if ( function_exists( 'stackboost_log' ) ) {
				stackboost_log( "ChatBubbles: Email feature disabled in settings. Skipping history replacement.", 'chat_bubbles' );
			}
			return $str;
		}

		// Generate the bubble history HTML
		$html = $this->generate_history_html( $ticket );

		// If we are in the filter, $str is likely the original text or empty.
		// We want to return OUR html.
		// NOTE: If $str is the FULL BODY, we replace. If $str is the REPLACEMENT VALUE, we just return it.
		// Based on standard WP filters for macros, it usually expects the replacement value.
		// However, to be safe against `wpsc_replace_macros` behavior (which might pass the full string?),
		// we should just return the HTML because we matched the specific macro key.

		return $html;
	}

	/**
	 * Generates the HTML for the ticket history bubbles.
	 */
	private function generate_history_html( $ticket ) {
		// Fetch threads (Report and Reply)
		// Assuming we want to show the conversation.
		// get_threads( $page_no, $items_per_page, $types, $orderby, $order )
		// Fetch all (0 limit), DESC order (newest first)
		$types = [ 'report', 'reply' ];
		$threads = $ticket->get_threads( 1, 0, $types, 'date_created', 'DESC' );

		if ( function_exists( 'stackboost_log' ) ) {
			stackboost_log( "ChatBubbles: Found " . count( $threads ) . " threads for history.", 'chat_bubbles' );
		}

		if ( empty( $threads ) ) {
			if ( function_exists( 'stackboost_log' ) ) {
				stackboost_log( "ChatBubbles: No threads found. Returning empty string.", 'chat_bubbles' );
			}
			return '';
		}

		$html = '';
		$date_format = get_option( 'date_format' );
		$time_format = get_option( 'time_format' );

		foreach ( $threads as $index => $thread ) {
			$user_type  = $this->get_thread_user_type( $thread );
			$inline_css = $this->get_email_inline_styles( $user_type );

			if ( function_exists( 'stackboost_log' ) ) {
				stackboost_log( "ChatBubbles: Processing thread #{$thread->id} (Type: {$thread->type}, User: {$user_type})", 'chat_bubbles' );
			}

			// Author Name
			$author_name = __( 'Unknown', 'stackboost-for-supportcandy' );
			if ( isset( $thread->customer ) && is_object( $thread->customer ) ) {
				$author_name = $thread->customer->name;
			}

			// Date
			$date_str = '';
			if ( isset( $thread->date_created ) && is_object( $thread->date_created ) ) {
				$date_obj = clone $thread->date_created;
				$date_obj->setTimezone( wp_timezone() );
				$date_str = $date_obj->format( $date_format . ' ' . $time_format );
			}

			// Build Header (Author - Date)
			$html .= '<div style="margin-bottom: 5px; font-size: 12px; color: #777;">';
			$html .= '<strong>' . esc_html( $author_name ) . '</strong>';
			if ( $date_str ) {
				$html .= ' - ' . esc_html( $date_str );
			}
			$html .= '</div>';

			if ( empty( $inline_css ) ) {
				// Fallback to plain if no styles
				if ( function_exists( 'stackboost_log' ) ) {
					stackboost_log( "ChatBubbles: No inline styles generated for thread #{$thread->id}. Using plain fallback.", 'chat_bubbles' );
				}
				$html .= '<div style="margin-bottom: 20px;">' . $thread->get_printable_string() . '</div>';
				continue;
			}

			// Get content
			$search_html = $thread->get_printable_string();

			// Wrap in bubble
			$html .= '<div style="' . esc_attr( $inline_css ) . '">' . $search_html . '</div>';
			// Add a spacer div since we are building a list
			$html .= '<div style="height: 15px;"></div>';
		}

		if ( function_exists( 'stackboost_log' ) ) {
			stackboost_log( "ChatBubbles: History HTML generated. Length: " . strlen( $html ), 'chat_bubbles' );
		}

		return $html;
	}

	/**
	 * Process Email Content.
	 * Wraps the new message content in a styled bubble container.
	 *
	 * @param object $en The Email Notification object.
	 * @return object The modified Email Notification object.
	 */
	public function process_email_content( $en ) {
		if ( function_exists( 'stackboost_log' ) ) {
			stackboost_log( "ChatBubbles: process_email_content called.", 'chat_bubbles' );
		}

		// Check email specific enable switch
		$options = get_option( 'stackboost_settings', [] );
		if ( empty( $options['chat_bubbles_enable_email'] ) ) {
			if ( function_exists( 'stackboost_log' ) ) {
				stackboost_log( "ChatBubbles: Email feature disabled in settings. Skipping.", 'chat_bubbles' );
			}
			return $en;
		}

		// Only proceed if we have a valid thread object
		if ( ! isset( $en->thread ) || ! is_object( $en->thread ) ) {
			if ( function_exists( 'stackboost_log' ) ) {
				stackboost_log( "ChatBubbles: No valid thread object in email notification. Skipping.", 'chat_bubbles' );
			}
			return $en;
		}

		if ( function_exists( 'stackboost_log' ) ) {
			stackboost_log( "ChatBubbles: Processing Thread ID: {$en->thread->id}, Type: {$en->thread->type}", 'chat_bubbles' );
		}

		// Fallback History Replacement: Check if {ticket_history} or {ticket_threads} is in the body
		// This handles the case where the filter hook didn't fire.
		$has_history = strpos( $en->body, '{ticket_history}' ) !== false;
		$has_threads = strpos( $en->body, '{ticket_threads}' ) !== false;

		if ( function_exists( 'stackboost_log' ) ) {
			stackboost_log( "ChatBubbles: Checking body for history macros. {ticket_history}: " . ($has_history ? 'YES' : 'NO') . ", {ticket_threads}: " . ($has_threads ? 'YES' : 'NO'), 'chat_bubbles' );
			// Log a snippet to see what the body looks like
			stackboost_log( "ChatBubbles: Body Snippet: " . substr( strip_tags( $en->body ), 0, 200 ) . "...", 'chat_bubbles' );
		}

		if ( $has_history || $has_threads ) {
			// Generate History
			// We need the parent ticket. The thread object usually has a `ticket_id` property or relation.
			// Let's try to find the ticket object.
			$ticket = null;
			if ( isset( $en->thread->ticket ) ) {
				$ticket = $en->thread->ticket;
			} elseif ( isset( $en->ticket ) ) {
				$ticket = $en->ticket;
			} elseif ( isset( $en->thread->ticket_id ) ) {
				// Load ticket manually if we have ID
				// Assuming standard SC logic class exists
				if ( class_exists( 'WPSC_Ticket' ) ) {
					$ticket = new \WPSC_Ticket( $en->thread->ticket_id );
				}
			}

			if ( $ticket ) {
				if ( function_exists( 'stackboost_log' ) ) {
					stackboost_log( "ChatBubbles: Found Ticket Object. Attempting manual history replacement.", 'chat_bubbles' );
				}
				$history_html = $this->generate_history_html( $ticket );

				if ( $has_history ) {
					$en->body = str_replace( '{ticket_history}', $history_html, $en->body );
				}
				if ( $has_threads ) {
					$en->body = str_replace( '{ticket_threads}', $history_html, $en->body );
				}
			} else {
				if ( function_exists( 'stackboost_log' ) ) {
					stackboost_log( "ChatBubbles: Could not resolve Ticket object. Skipping manual history replacement.", 'chat_bubbles' );
				}
			}
		}

		// Only process reply and note types for the CURRENT message wrapping
		if ( ! in_array( $en->thread->type, [ 'reply', 'note', 'report' ] ) ) {
			if ( function_exists( 'stackboost_log' ) ) {
				stackboost_log( "ChatBubbles: Thread type '{$en->thread->type}' not supported. Skipping.", 'chat_bubbles' );
			}
			return $en;
		}

		// Determine user type
		$user_type = $this->get_thread_user_type( $en->thread );
		if ( function_exists( 'stackboost_log' ) ) {
			stackboost_log( "ChatBubbles: Resolved User Type: {$user_type}", 'chat_bubbles' );
		}

		// Get Inline CSS
		$inline_css = $this->get_email_inline_styles( $user_type );
		if ( empty( $inline_css ) ) {
			if ( function_exists( 'stackboost_log' ) ) {
				stackboost_log( "ChatBubbles: No inline styles generated. Skipping.", 'chat_bubbles' );
			}
			return $en;
		}

		// Get the content we want to wrap
		$search_html = $en->thread->get_printable_string();

		// Create the wrapper
		$replace_html = '<div style="' . esc_attr( $inline_css ) . '">' . $search_html . '</div>';

		// Perform the replacement
		$pattern = '/' . preg_quote( $search_html, '/' ) . '/';
		$result = preg_replace( $pattern, $replace_html, $en->body, 1, $count );

		if ( $count > 0 ) {
			$en->body = $result;
			if ( function_exists( 'stackboost_log' ) ) {
				stackboost_log( "ChatBubbles: Successfully wrapped email content (Length: " . strlen( $replace_html ) . ")", 'chat_bubbles' );
			}
		} else {
			if ( function_exists( 'stackboost_log' ) ) {
				stackboost_log( "ChatBubbles: Failed to find/replace content in email body. Regex pattern: {$pattern}", 'chat_bubbles' );
				// Log a snippet of the body to see why it might have failed
				stackboost_log( "ChatBubbles: Body Snippet (start): " . substr( strip_tags( $en->body ), 0, 100 ), 'chat_bubbles' );
			}
		}

		return $en;
	}

	/**
	 * Determine User Type for a Thread.
	 */
	private function get_thread_user_type( $thread ): string {
		if ( $thread->type === 'note' ) {
			return 'note';
		}

		$is_agent = false;

		// Attempt to identify if author is agent
		if ( isset( $thread->customer ) && is_object( $thread->customer ) ) {
			$email = $thread->customer->email;
			$user = get_user_by( 'email', $email );

			if ( $user ) {
				// Check for Agent capability
				if ( $user->has_cap( 'wpsc_agent' ) ) {
					$is_agent = true;
				}

				if ( function_exists( 'stackboost_log' ) ) {
					stackboost_log( "ChatBubbles: User Check for {$email} -> Agent: " . ( $is_agent ? 'Yes' : 'No' ) . " (Roles: " . implode(',', $user->roles) . ")", 'chat_bubbles' );
				}
			} else {
				if ( function_exists( 'stackboost_log' ) ) {
					stackboost_log( "ChatBubbles: User Check for {$email} -> No WP User found.", 'chat_bubbles' );
				}
			}
		} else {
			if ( function_exists( 'stackboost_log' ) ) {
				stackboost_log( "ChatBubbles: User Check -> No customer object in thread.", 'chat_bubbles' );
			}
		}

		return $is_agent ? 'agent' : 'customer';
	}

	/**
	 * Helper to generate inline CSS string for emails.
	 *
	 * @param string $user_type The user type ('agent', 'customer', 'note').
	 * @return string The inline CSS string.
	 */
	public function get_email_inline_styles( string $user_type ): string {
		$styles = $this->get_styles_for_type( $user_type );
		if ( empty( $styles ) ) {
			if ( function_exists( 'stackboost_log' ) ) {
				stackboost_log( "ChatBubbles: get_styles_for_type returned empty for {$user_type}", 'chat_bubbles' );
			}
			return '';
		}

		$inline_css = sprintf(
			"background-color: %s; color: %s; padding: 15px; margin-bottom: 10px; border-radius: %dpx; width: %d%%;",
			$styles['bg_color'],
			$styles['text_color'],
			$styles['radius'],
			$styles['width']
		);

		if ( $styles['alignment'] === 'right' ) {
			$inline_css .= " margin-left: auto; margin-right: 0;";
		} elseif ( $styles['alignment'] === 'center' ) {
			$inline_css .= " margin: 0 auto;";
		} else {
			$inline_css .= " margin-right: auto; margin-left: 0;";
		}

		// Font Styles
		if ( ! empty( $styles['font_bold'] ) ) {
			$inline_css .= " font-weight: bold;";
		}
		if ( ! empty( $styles['font_italic'] ) ) {
			$inline_css .= " font-style: italic;";
		}
		if ( ! empty( $styles['font_underline'] ) ) {
			$inline_css .= " text-decoration: underline;";
		}

		// Border Styles
		if ( ! empty( $styles['border_style'] ) && $styles['border_style'] !== 'none' ) {
			$inline_css .= sprintf( " border: %s %dpx %s;", $styles['border_style'], $styles['border_width'], $styles['border_color'] );
		}

		return $inline_css;
	}

	/**
	 * Generate CSS for Admin View.
	 */
	public function generate_css(): string {
		$options = get_option( 'stackboost_settings', [] );
		$types = [ 'agent', 'customer', 'note' ];
		$css   = '';

		// Global Drop Shadow
		$shadow_css = '';
		if ( ! empty( $options['chat_bubbles_shadow_enable'] ) ) {
			$shadow_color = $options['chat_bubbles_shadow_color'] ?? '#000000';
			$shadow_distance = isset( $options['chat_bubbles_shadow_distance'] ) ? intval( $options['chat_bubbles_shadow_distance'] ) : 2;
			$shadow_blur  = isset( $options['chat_bubbles_shadow_blur'] ) ? intval( $options['chat_bubbles_shadow_blur'] ) : 5;
			$shadow_spread = isset( $options['chat_bubbles_shadow_spread'] ) ? intval( $options['chat_bubbles_shadow_spread'] ) : 0;
			$opacity_pct  = $options['chat_bubbles_shadow_opacity'] ?? '40';
			$opacity_val  = intval( $opacity_pct ) / 100;

			// Convert Hex to RGBA for Opacity Control
			if ( strpos( $shadow_color, '#' ) === 0 && strlen( $shadow_color ) === 7 ) {
				list( $r, $g, $b ) = sscanf( $shadow_color, "#%02x%02x%02x" );
				$shadow_color = "rgba({$r}, {$g}, {$b}, {$opacity_val})";
			} elseif ( strpos( $shadow_color, 'rgba' ) !== false ) {
				// If already rgba, we respect user input, maybe override alpha?
				// Let's rely on the hex conversion for now as the picker defaults to hex.
			}

			// Switched back to box-shadow to support spread radius as explicitly requested
			$shadow_css = "box-shadow: {$shadow_distance}px {$shadow_distance}px {$shadow_blur}px {$shadow_spread}px {$shadow_color} !important;";
		}

		foreach ( $types as $type ) {
			$styles = $this->get_styles_for_type( $type );
			if ( empty( $styles ) ) {
				continue;
			}

			// Increased specificity to override SupportCandy default styles
			// Using .wpsc-it-container (CLASS) + class hierarchy.
			// Fixed bug where #wpsc-it-container (ID) was used incorrectly.

			// Support both Admin (.wpsc-it-container) and Frontend (.wpsc-shortcode-container, .wpsc-container) contexts
			// Note: .wpsc-container is used in newer SC versions for frontend shortcodes.
			$roots = ['.wpsc-it-container', '.wpsc-shortcode-container', '#wpsc-container'];

			$selectors = [];
			foreach ($roots as $root) {
				if ( $type === 'agent' ) {
					$selectors[] = "{$root} .wpsc-thread.reply.agent .thread-body";
					$selectors[] = "{$root} .wpsc-thread.report.agent .thread-body";
				} elseif ( $type === 'customer' ) {
					$selectors[] = "{$root} .wpsc-thread.reply.customer .thread-body";
					$selectors[] = "{$root} .wpsc-thread.report.customer .thread-body";
				} elseif ( $type === 'note' ) {
					$selectors[] = "{$root} .wpsc-thread.note .thread-body";
				}
			}
			$selector = implode(', ', $selectors);

			// Build CSS Rule
			$css .= "{$selector} {";
			$css .= "background-color: {$styles['bg_color']} !important;";
			$css .= "color: {$styles['text_color']} !important;";
			$css .= "border-radius: {$styles['radius']}px !important;";
			$css .= "width: {$styles['width']}% !important;";
			$css .= "max-width: {$styles['width']}% !important;";

			// Override flex behavior to ensure width is respected (Fix for frontend flex-grow issue)
			$css .= "flex-grow: 0 !important;";

			// Font Family
			if ( ! empty( $styles['font_family'] ) ) {
				$font = sanitize_text_field( $styles['font_family'] );
				$font = str_replace( [';', '}', '{'], '', $font );
				$css .= "font-family: {$font} !important;";
			}

			// Font Size
			if ( ! empty( $styles['font_size'] ) ) {
				$css .= "font-size: {$styles['font_size']}px !important;";
			}

			// Font Styles
			if ( ! empty( $styles['font_bold'] ) ) {
				$css .= "font-weight: bold !important;";
			}
			if ( ! empty( $styles['font_italic'] ) ) {
				$css .= "font-style: italic !important;";
			}
			if ( ! empty( $styles['font_underline'] ) ) {
				$css .= "text-decoration: underline !important;";
			}

			// Alignment
			// Note: Since .thread-body is a flex child, margin auto works to push it.
			if ( $styles['alignment'] === 'right' ) {
				$css .= "margin-left: auto !important; margin-right: 0 !important;";
			} elseif ( $styles['alignment'] === 'center' ) {
				$css .= "margin: 0 auto !important;";
			} else {
				$css .= "margin-right: auto !important; margin-left: 0 !important;";
			}

			// Padding (Standardize)
			$css .= "padding: 15px !important;";

			// Borders
			if ( ! empty( $styles['border_style'] ) && $styles['border_style'] !== 'none' ) {
				$css .= "border: {$styles['border_width']}px {$styles['border_style']} {$styles['border_color']} !important;";
			} else {
				$css .= "border: none !important;";
			}

			// Shadow
			if ( $shadow_css ) {
				$css .= $shadow_css;
			}

			$css .= "}";

			// Hide Avatar (Relative to the thread container)
			// $selector targets .thread-body inside the thread container. We need to go up one level to the thread container
			// to find the .thread-avatar sibling, OR target the .thread-avatar relative to the same parent.
			// The selector logic above builds paths like: ".wpsc-it-container .wpsc-thread.reply.agent .thread-body".
			// The avatar is at: ".wpsc-it-container .wpsc-thread.reply.agent .thread-avatar".
			// So we need to strip ".thread-body" from the selector parts and append ".thread-avatar".

			$avatar_selectors = [];
			$selector_parts = explode(', ', $selector);

			foreach ($selector_parts as $part) {
				$part = trim($part);
				if ( substr($part, -12) === '.thread-body' ) {
					$base = substr($part, 0, -12);
					$avatar_selectors[] = $base . ' .thread-avatar';
				}
			}

			if ( ! empty( $avatar_selectors ) ) {
				$avatar_str = implode(', ', $avatar_selectors);
				$css .= "{$avatar_str} { display: none !important; }";
			}

			// Text Color inside (links, etc)
			// Updated to target specific user-info elements and links which often override colors
			$color_selectors = [];
			// Helper to create sub-selectors for the list of root selectors
			// Added .user-name specifically
			$sub_elements = ['.thread-text', '.user-info h2', '.user-info h2.user-name', '.user-info span', 'a', '.thread-header h2', '.thread-header span'];

			foreach ($selector_parts as $part) {
				foreach ($sub_elements as $el) {
					$color_selectors[] = trim($part) . ' ' . $el;
				}
			}
			$color_selector_str = implode(', ', $color_selectors);

			$css .= "{$color_selector_str} { color: {$styles['text_color']} !important; }";

			// Header layout adjustment
			$header_selectors = [];
			foreach ($selector_parts as $part) {
				$header_selectors[] = trim($part) . ' .thread-header';
			}
			$header_selector_str = implode(', ', $header_selectors);
			$css .= "{$header_selector_str} { margin-bottom: 10px; border-bottom: 1px solid rgba(0,0,0,0.1); padding-bottom: 5px; }";

			// Image Bounding Box (Global setting affecting all types)
			if ( ! empty( $options['chat_bubbles_image_box'] ) ) {
				$img_selectors = [];
				foreach ($selector_parts as $part) {
					$img_selectors[] = trim($part) . ' img';
				}
				$img_selector_str = implode(', ', $img_selectors);
				$css .= "{$img_selector_str} { border: 1px solid rgba(0,0,0,0.2) !important; padding: 3px !important; background: rgba(255,255,255,0.5) !important; border-radius: 3px !important; }";
			}
		}

		return $css;
	}

	/**
	 * Helper to get StackBoost Theme Colors (Mirroring admin-themes.css).
	 * This ensures colors work on the frontend without enqueueing the full admin CSS.
	 */
	public function get_stackboost_theme_colors( $slug ) {
		$themes = [
			'sb-theme-wordpress-sync' => [
				'accent' => '#2271b1', // Fallback, variable not available on frontend
				'text_on_accent' => '#ffffff',
				'bg_main' => '#f0f0f1',
			],
			'sb-theme-supportcandy-sync' => [
				'accent' => '#2271b1', // Fallback
				'text_on_accent' => '#ffffff',
				'bg_main' => '#f6f7f7',
			],
			'sb-theme-cloud-dancer' => [
				'accent' => '#722ed1',
				'text_on_accent' => '#ffffff',
				'bg_main' => '#F0EEE9',
			],
			'sb-theme-heroic' => [
				'accent' => '#d63638',
				'text_on_accent' => '#ffffff',
				'bg_main' => '#f6f7f7',
			],
			'sb-theme-clean-tech' => [
				'accent' => '#1a3d5c',
				'text_on_accent' => '#ffffff',
				'bg_main' => '#ffffff',
			],
			'sb-theme-hudson-valley-eco' => [
				'accent' => '#2d5a27',
				'text_on_accent' => '#ffffff',
				'bg_main' => '#e9edeb',
			],
		];

		return $themes[ $slug ] ?? $themes['sb-theme-clean-tech'];
	}

	/**
	 * Get styles for a specific type based on settings/theme.
	 */
	private function get_styles_for_type( $type ): array {
		$options = get_option( 'stackboost_settings', [] );
		$prefix = "chat_bubbles_{$type}_";
		$theme = $options['chat_bubbles_theme'] ?? 'default';

		if ( function_exists( 'stackboost_log' ) ) {
			// Log theme selection to debug "wrong theme" issues
			stackboost_log( "ChatBubbles: get_styles_for_type({$type}) - Theme: {$theme}", 'chat_bubbles' );
		}

		// Default Styles
		$defaults = [
			'bg_color'    => '#f1f1f1',
			'text_color'  => '#333333',
			'font_family' => '',
			'font_size'   => '',
			'alignment'   => 'left',
			'width'       => '85',
			'radius'      => '15',
			'font_bold'   => 0,
			'font_italic' => 0,
			'font_underline' => 0,
			'border_style' => 'none',
			'border_width' => '1',
			'border_color' => '#cccccc',
		];

		$styles = [];

		// Theme Logic
		switch ( $theme ) {
			case 'stackboost':
				// StackBoost Theme
				// Retrieve Active Theme Colors explicitly for frontend compatibility
				$active_theme_slug = $options['admin_theme'] ?? 'sb-theme-clean-tech';
				$theme_colors = $this->get_stackboost_theme_colors( $active_theme_slug );

				if ( $type === 'agent' ) {
					$styles = array_merge( $defaults, [
						'bg_color'    => $theme_colors['accent'],
						'text_color'  => $theme_colors['text_on_accent'],
						'alignment'   => 'right',
						'radius'      => '15',
						'padding'     => '15',
					]);
				} elseif ( $type === 'note' ) {
					$styles = array_merge( $defaults, [
						'bg_color'    => '#fff8e5',
						'text_color'  => '#333333',
						'alignment'   => 'center',
						'radius'      => '5',
						'padding'     => '10',
					]);
				} else {
					$styles = array_merge( $defaults, [
						'bg_color'    => $theme_colors['bg_main'],
						'text_color'  => '#3c434a', // Dark text on main bg
						'alignment'   => 'left',
						'radius'      => '15',
						'padding'     => '15',
					]);
				}
				$styles['font_family'] = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif';
				break;

			case 'supportcandy':
				// SupportCandy
				$sc_settings = get_option( 'wpsc-ap-individual-ticket', [] );
				$reply_primary = $sc_settings['reply-primary-color'] ?? '#2c3e50';
				$note_primary = $sc_settings['note-primary-color'] ?? '#fffbcc';
				// Third Color: Reply & Close Button Color for Customer
				$reply_close_bg = $sc_settings['reply-close-bg-color'] ?? '#e5e5e5';
				$reply_close_text = $sc_settings['reply-close-text-color'] ?? '#333333';

				if ( $type === 'agent' ) {
					$styles = array_merge( $defaults, [
						'bg_color'    => $reply_primary,
						'text_color'  => '#ffffff',
						'alignment'   => 'right',
						'radius'      => '5',
					]);
				} elseif ( $type === 'note' ) {
					$styles = array_merge( $defaults, [
						'bg_color'    => $note_primary,
						'text_color'  => '#333333',
						'alignment'   => 'center',
						'radius'      => '0',
					]);
				} else {
					$styles = array_merge( $defaults, [
						'bg_color'    => $reply_close_bg,
						'text_color'  => $reply_close_text,
						'alignment'   => 'left',
						'radius'      => '5',
					]);
				}
				break;

			case 'classic':
				// Classic
				if ( $type === 'agent' ) {
					$styles = array_merge( $defaults, [
						'bg_color'    => '#2271b1',
						'text_color'  => '#ffffff',
						'alignment'   => 'right',
						'radius'      => '5',
					]);
				} elseif ( $type === 'note' ) {
					$styles = array_merge( $defaults, [
						'bg_color'    => '#fdfdfd',
						'text_color'  => '#333333',
						'alignment'   => 'center',
						'radius'      => '0',
					]);
				} else {
					$styles = array_merge( $defaults, [
						'bg_color'    => '#e5e5e5',
						'text_color'  => '#333333',
						'alignment'   => 'left',
						'radius'      => '5',
					]);
				}
				break;

			case 'ios':
				// Fruit: Agent=Blue, Customer=Green
				if ( $type === 'agent' ) {
					$styles = array_merge( $defaults, [
						'bg_color'    => '#007aff', // Blue (iMessage)
						'text_color'  => '#ffffff',
						'alignment'   => 'right',
						'width'       => '75',
						'radius'      => '20',
					]);
				} elseif ( $type === 'note' ) {
					$styles = array_merge( $defaults, [
						'bg_color'    => '#fffbcc', // Standard yellow note
						'text_color'  => '#333333',
						'alignment'   => 'center',
						'width'       => '85',
						'radius'      => '10',
					]);
				} else {
					$styles = array_merge( $defaults, [
						'bg_color'    => '#34c759', // Green (SMS/RCS)
						'text_color'  => '#ffffff',
						'alignment'   => 'left',
						'width'       => '75',
						'radius'      => '20',
					]);
				}
				$styles['font_family'] = '-apple-system, BlinkMacSystemFont, sans-serif';
				break;

			case 'android':
				// Droid
				if ( $type === 'agent' ) {
					$styles = array_merge( $defaults, [
						'bg_color'    => '#d9fdd3',
						'text_color'  => '#111b21',
						'alignment'   => 'right',
						'width'       => '80',
						'radius'      => '8',
					]);
				} elseif ( $type === 'note' ) {
					$styles = array_merge( $defaults, [
						'bg_color'    => '#fffbcc',
						'text_color'  => '#333333',
						'alignment'   => 'center',
						'width'       => '85',
						'radius'      => '5',
					]);
				} else {
					$styles = array_merge( $defaults, [
						'bg_color'    => '#ffffff',
						'text_color'  => '#111b21',
						'alignment'   => 'left',
						'width'       => '80',
						'radius'      => '8',
					]);
				}
				$styles['font_family'] = 'Roboto, sans-serif';
				break;

			case 'modern':
				// Modern
				if ( $type === 'agent' ) {
					$styles = array_merge( $defaults, [
						'bg_color'    => '#000000',
						'text_color'  => '#ffffff',
						'alignment'   => 'right',
						'width'       => '60',
						'radius'      => '0',
					]);
				} elseif ( $type === 'note' ) {
					$styles = array_merge( $defaults, [
						'bg_color'    => '#f9f9f9',
						'text_color'  => '#555555',
						'alignment'   => 'center',
						'width'       => '85',
						'radius'      => '0',
					]);
				} else {
					$styles = array_merge( $defaults, [
						'bg_color'    => '#f2f2f2',
						'text_color'  => '#000000',
						'alignment'   => 'left',
						'width'       => '60',
						'radius'      => '0',
					]);
				}
				$styles['font_family'] = 'Helvetica, Arial, sans-serif';
				break;

			case 'custom':
			case 'default':
			default:
				// Load from user settings if Custom, else use Defaults
				if ( $theme === 'custom' ) {
					$styles = [
						'bg_color'    => $options["{$prefix}bg_color"] ?? $defaults['bg_color'],
						'text_color'  => $options["{$prefix}text_color"] ?? $defaults['text_color'],
						'font_family' => $options["{$prefix}font_family"] ?? $defaults['font_family'],
						'font_size'   => $options["{$prefix}font_size"] ?? $defaults['font_size'],
						'alignment'   => $options["{$prefix}alignment"] ?? $defaults['alignment'],
						'width'       => $options["{$prefix}width"] ?? $defaults['width'],
						'radius'      => $options["{$prefix}radius"] ?? $defaults['radius'],
						'font_bold'      => ! empty( $options["{$prefix}font_bold"] ) ? 1 : 0,
						'font_italic'    => ! empty( $options["{$prefix}font_italic"] ) ? 1 : 0,
						'font_underline' => ! empty( $options["{$prefix}font_underline"] ) ? 1 : 0,
						'border_style'   => $options["{$prefix}border_style"] ?? 'none',
						'border_width'   => $options["{$prefix}border_width"] ?? '1',
						'border_color'   => $options["{$prefix}border_color"] ?? '#cccccc',
					];
				} else {
					// Default Theme (Blue/Grey)
					if ( $type === 'agent' ) {
						$styles = array_merge( $defaults, [
							'bg_color'    => '#2271b1',
							'text_color'  => '#ffffff',
							'alignment'   => 'right',
							'radius'      => '15',
						]);
					} elseif ( $type === 'note' ) {
						$styles = array_merge( $defaults, [
							'bg_color'    => '#fff8e5',
							'text_color'  => '#333333',
							'alignment'   => 'center',
							'radius'      => '5',
						]);
					} else {
						$styles = array_merge( $defaults, [
							'bg_color'    => '#e6e6e6', // Darker than bg for contrast
							'text_color'  => '#3c434a',
							'alignment'   => 'left',
							'radius'      => '15',
						]);
					}
					$styles['font_family'] = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif';
				}
				break;
		}

		// SANITIZATION
		// Allow CSS variables (starting with var(--) ) to bypass hex sanitization.
		// Note: We check the value BEFORE attempting sanitize_hex_color which would clear it.
		if ( strpos( $styles['bg_color'], 'var(' ) === 0 ) {
			// Basic sanitization for CSS var syntax
			$styles['bg_color'] = sanitize_text_field( $styles['bg_color'] );
		} else {
			$styles['bg_color'] = sanitize_hex_color( $styles['bg_color'] ) ?: $defaults['bg_color'];
		}

		$styles['text_color'] = sanitize_hex_color($styles['text_color']) ?: $defaults['text_color'];
		$styles['width'] = absint($styles['width']);
		if ($styles['width'] < 0 || $styles['width'] > 100) $styles['width'] = 85;
		$styles['radius'] = absint($styles['radius']);
		if ($styles['radius'] > 100) $styles['radius'] = 100;
		$styles['padding'] = absint($styles['padding']);
		$styles['font_size'] = absint($styles['font_size']);
		$styles['font_family'] = sanitize_text_field($styles['font_family']);
		$styles['border_width'] = absint($styles['border_width']);
		$styles['border_color'] = sanitize_hex_color($styles['border_color']) ?: '#cccccc';
		$styles['border_style'] = in_array($styles['border_style'], ['none', 'solid', 'dashed', 'dotted']) ? $styles['border_style'] : 'none';

		return $styles;
	}
}
