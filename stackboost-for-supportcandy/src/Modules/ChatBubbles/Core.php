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
		// Log initialization to confirm the module is loaded
		if ( function_exists( 'stackboost_log' ) ) {
			stackboost_log( 'ChatBubbles: Core Initialized.', 'chat_bubbles' );
		}
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

		// Hook into SupportCandy Option retrieval to inject markers around history macros
		// Targeting the main templates option which holds all email templates
		add_filter( 'option_wpsc-email-templates', [ $this, 'inject_bubble_markers' ] );
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
			// $hook_label = $is_frontend ? 'frontend' : ( $hook_suffix ?? 'unknown' );
			// stackboost_log( 'ChatBubbles: enqueue_ticket_styles called. Hook: ' . $hook_label, 'chat_bubbles' );
		}

		// Check ticket specific enable switch
		// Note: We use the same 'chat_bubbles_enable_ticket' setting for both Admin and Frontend uniformity.
		$options = get_option( 'stackboost_settings', [] );
		if ( empty( $options['chat_bubbles_enable_ticket'] ) ) {
			return;
		}

		$css = $this->generate_css();
		wp_add_inline_style( $handle, $css );
	}

	/**
	 * Inject markers around history and current reply macros in email templates.
	 *
	 * @param mixed $value The option value (array of templates).
	 * @return mixed The modified option value.
	 */
	public function inject_bubble_markers( $value ) {
		// Check email specific enable switch
		$options = get_option( 'stackboost_settings', [] );
		if ( empty( $options['chat_bubbles_enable_email'] ) ) {
			return $value;
		}

		if ( ! is_array( $value ) ) {
			return $value;
		}

		// Regex to find history macros: {ticket_history...} or {{ticket_history...}}
		// Now optionally matches surrounding <p> tags.
		$history_pattern = '/(<p>\s*)?(\{\{?(ticket_history(?:_[a-z_]+)?)\}?})(\s*<\/p>)?/i';

		// Regex to find current message macros: {last_reply}, {last_note}, {ticket_description}
		// Now optionally matches surrounding <p> tags.
		// Note: We include ticket_description because for a 'create ticket' event, that IS the message.
		$current_pattern = '/(<p>\s*)?(\{\{?(last_reply|last_note|ticket_description)\}?})(\s*<\/p>)?/i';

		foreach ( $value as $key => $template ) {
			if ( isset( $template['body']['text'] ) ) {

				$original_text = $template['body']['text'];
				$modified_text = $original_text;
				$changed = false;

				// Log raw template (Before) - Unconditional check for existence of macros to reduce log noise,
				// but logging "Before" state if we are about to attempt modification.
				if ( preg_match( $history_pattern, $original_text ) || preg_match( $current_pattern, $original_text ) ) {
					if ( function_exists( 'stackboost_log' ) ) {
						stackboost_log( "DEBUG: Template [{$key}] raw content (BEFORE INJECTION):\n" . $original_text, 'chat_bubbles' );
					}
				}

				// 1. Inject History Markers
				// Re-doing History Injection with Idempotency Regex
				// Assert NOT preceded by <!--SB_HISTORY_START--> to avoid double wrapping.
				// We use $history_pattern which now includes optional P tags.
				// Since we are replacing the WHOLE MATCH, we can just check if markers exist?
				// A simpler idempotency check for this complex pattern:
				// If the string contains marker+macro, skip?
				// But we need to handle multiple occurrences? No, usually one history macro per email.

				// Let's stick to the callback method for replacement, but check if we are already inside a marker block?
				// Regex Lookbehind is fixed length in PHP < 8 (ish), so variable length <p> makes lookbehind hard.

				// Strategy: Check if markers are already present in the string. If so, we assume injection happened.
				// This is safest to avoid corruption.
				if ( strpos( $modified_text, '<!--SB_HISTORY_START-->' ) === false ) {
					if ( preg_match( $history_pattern, $modified_text ) ) {
						$modified_text = preg_replace_callback(
							$history_pattern,
							function( $matches ) {
								// Wrap the ENTIRE matched string (including P tags if found)
								return '<!--SB_HISTORY_START-->' . $matches[0] . '<!--SB_HISTORY_END-->';
							},
							$modified_text
						);
						if ( $modified_text !== $original_text ) {
							$changed = true;
						}
					}
				}

				// 2. Inject Current Message Markers (If Enabled)
				if ( ! empty( $options['chat_bubbles_enable_email_current_message'] ) ) {
					if ( strpos( $modified_text, '<!--SB_CURRENT_START-->' ) === false ) {
						if ( preg_match( $current_pattern, $modified_text ) ) {
							$modified_text = preg_replace_callback(
								$current_pattern,
								function( $matches ) {
									// Wrap the ENTIRE matched string (including P tags if found)
									return '<!--SB_CURRENT_START-->' . $matches[0] . '<!--SB_CURRENT_END-->';
								},
								$modified_text
							);
							$changed = true;
						}
					}
				}

				if ( $changed ) {
					// Update the value
					$value[ $key ]['body']['text'] = $modified_text;

					// Log modified template (After)
					if ( function_exists( 'stackboost_log' ) ) {
						stackboost_log( "DEBUG: Template [{$key}] modified content (AFTER INJECTION):\n" . $modified_text, 'chat_bubbles' );
					}
				}
			}
		}

		return $value;
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
			stackboost_log( "DEBUG: process_email_content called.", 'chat_bubbles' );
		}

		// Check email specific enable switch
		$options = get_option( 'stackboost_settings', [] );
		if ( empty( $options['chat_bubbles_enable_email'] ) ) {
			return $en;
		}

		// Only proceed if we have a valid thread object
		if ( ! isset( $en->thread ) || ! is_object( $en->thread ) ) {
			if ( function_exists( 'stackboost_log' ) ) {
				stackboost_log( "DEBUG: No valid thread object. Skipping.", 'chat_bubbles' );
			}
			return $en;
		}

		// -------------------------------------------------------------------------
		// DIAGNOSTIC LOGGING: Log the RAW Body we received
		// -------------------------------------------------------------------------
		if ( function_exists( 'stackboost_log' ) ) {
			// Log the first 2000 chars to cover the header and likely the history block
			$raw_snippet = substr( $en->body, 0, 2000 );
			stackboost_log( "DEBUG: RAW EMAIL BODY RECEIVED (First 2000 chars):\n" . $raw_snippet, 'chat_bubbles' );

			// Specifically search for our marker in the raw body
			if ( strpos( $en->body, '<!--SB_HISTORY_START-->' ) !== false ) {
				stackboost_log( "DEBUG: SUCCESS! Found '<!--SB_HISTORY_START-->' in email body.", 'chat_bubbles' );
			} else {
				stackboost_log( "DEBUG: FAILURE! Did NOT find '<!--SB_HISTORY_START-->' in email body.", 'chat_bubbles' );
			}
		}

		$ticket = null;
		if ( isset( $en->thread->ticket ) ) {
			$ticket = $en->thread->ticket;
		} elseif ( isset( $en->ticket ) ) {
			$ticket = $en->ticket;
		} elseif ( isset( $en->thread->ticket_id ) && class_exists( 'WPSC_Ticket' ) ) {
			$ticket = new \WPSC_Ticket( $en->thread->ticket_id );
		}

		// -------------------------------------------------------------------------
		// STEP 1: Process History (Post-Process HTML via Markers)
		// We look for <!--SB_HISTORY_START-->...<!--SB_HISTORY_END-->
		// -------------------------------------------------------------------------

		if ( $ticket ) {
			// Find the block between markers
			// Use #s modifier for dot-matches-newline
			$marker_pattern = '/<!--SB_HISTORY_START-->(.*?)<!--SB_HISTORY_END-->/s';

			if ( preg_match( $marker_pattern, $en->body, $block_matches ) ) {

				$history_block = $block_matches[1];

				// Now parse the ugly HTML INSIDE this block
				$inner_pattern = '#<strong>\s*(.*?)\s*<small>\s*<i>\s*(.*?)\s*</i>\s*</small>\s*</strong>\s*<div style="font-size:10px;">\s*(.*?)\s*</div>\s*(.*?)(?=<br><hr><br>|$)#si';

				$new_history_html = preg_replace_callback( $inner_pattern, function( $matches ) use ( $ticket ) {
					$name = trim( strip_tags( $matches[1] ) );
					$action = trim( strip_tags( $matches[2] ) ); // reported, replied, added a note
					$date_str = trim( $matches[3] );
					$content = $matches[4]; // Preserve HTML content

					// Determine User Type based on Name/Action
					$user_type = 'agent'; // Default

					if ( stripos( $action, 'note' ) !== false ) {
						$user_type = 'note';
					} elseif ( $ticket && isset( $ticket->customer ) && $name === $ticket->customer->name ) {
						$user_type = 'customer';
					}

					// Get Styling
					$inline_css = $this->get_email_inline_styles( $user_type );

					if ( empty( $inline_css ) ) {
						return $matches[0];
					}

					// Build Bubble HTML
					$html = '<div style="margin-bottom: 20px;">'; // Wrapper for spacing

					// Bubble Body
					$html .= '<div style="' . esc_attr( $inline_css ) . '">';

					// Header inside the bubble
					$html .= '<div style="margin-bottom: 5px; font-size: 12px; color: inherit; opacity: 0.8; border-bottom: 1px solid rgba(0,0,0,0.1); padding-bottom: 5px;">';
					$html .= '<strong>' . esc_html( $name ) . '</strong>';
					if ( $date_str ) {
						$html .= ' - ' . esc_html( $date_str );
					}
					$html .= '</div>';

					// Content
					$html .= $content;
					$html .= '</div>';

					$html .= '</div>';

					return $html;

				}, $history_block );

				$new_history_html = str_replace( '<br><hr><br>', '', $new_history_html );

				$en->body = str_replace( $block_matches[0], $new_history_html, $en->body );

				if ( function_exists( 'stackboost_log' ) ) {
					stackboost_log( "DEBUG: Replaced history block with styled bubbles.", 'chat_bubbles' );
				}

			}
		}

		// -------------------------------------------------------------------------
		// STEP 2: Process Current Reply (Existing Logic with NEW Marker Strategy)
		// -------------------------------------------------------------------------

		// Only process reply and note types for the CURRENT message wrapping
		if ( ! in_array( $en->thread->type, [ 'reply', 'note', 'report', 'create' ] ) ) { // Added 'create' just in case
			return $en;
		}

		// Find the Current Message block between markers
		$current_marker_pattern = '/<!--SB_CURRENT_START-->(.*?)<!--SB_CURRENT_END-->/s';

		if ( preg_match( $current_marker_pattern, $en->body, $current_matches ) ) {

			$content_to_wrap = $current_matches[1];

			// Determine user type
			$user_type = $this->get_thread_user_type( $en->thread );

			// Get Inline CSS
			$inline_css = $this->get_email_inline_styles( $user_type );

			if ( ! empty( $inline_css ) ) {
				// Build Bubble HTML
				$html = '<div style="margin-bottom: 20px;">'; // Wrapper for spacing

				// Bubble Body
				$html .= '<div style="' . esc_attr( $inline_css ) . '">';

				// Header Info
				// Correctly determine name based on user type
				$name = '';
				$date_str = '';

				if ( $user_type === 'customer' ) {
					if ( isset( $en->thread->customer ) && is_object( $en->thread->customer ) ) {
						$name = $en->thread->customer->name;
					} elseif ( isset( $ticket->customer ) && is_object( $ticket->customer ) ) {
						$name = $ticket->customer->name;
					}
				} else {
					// Agent or Note (usually Agent)
					// Try to find agent info in thread
					if ( isset( $en->thread->agent ) && is_object( $en->thread->agent ) ) {
						$name = $en->thread->agent->name; // SupportCandy agent object typically has a name property
					} elseif ( isset( $en->thread->created_by ) ) {
						// SupportCandy stores creator ID in created_by usually.
						// If created_by is available, we can try to fetch the user.
						$user_id = $en->thread->created_by;
						$user_info = get_userdata( $user_id );
						if ( $user_info ) {
							$name = $user_info->display_name;
						}
					}

					// Fallback if name is still empty (e.g. system note?)
					if ( empty( $name ) ) {
						$name = __( 'Support Agent', 'stackboost-for-supportcandy' );
					}
				}

				if ( isset( $en->thread->date ) ) {
					// Format date. $en->thread->date is likely a DateTime object or string.
					// SupportCandy usually stores it as DateTime object in properties or returns formatted string.
					// Let's use standard WP date format if possible, or just raw if string.
					$date_obj = $en->thread->date;
					if ( is_a( $date_obj, 'DateTime' ) ) {
						$date_str = $date_obj->format( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
					} else {
						$date_str = (string) $date_obj;
					}
				}

				// Header inside the bubble
				$html .= '<div style="margin-bottom: 5px; font-size: 12px; color: inherit; opacity: 0.8; border-bottom: 1px solid rgba(0,0,0,0.1); padding-bottom: 5px;">';
				$html .= '<strong>' . esc_html( $name ) . '</strong>';
				if ( $date_str ) {
					$html .= ' - ' . esc_html( $date_str );
				}
				$html .= '</div>';

				// Content
				$html .= $content_to_wrap;
				$html .= '</div>'; // End styled bubble
				$html .= '</div>'; // End wrapper

				// Replace the entire marked block with our new HTML
				$en->body = str_replace( $current_matches[0], $html, $en->body );

				if ( function_exists( 'stackboost_log' ) ) {
					stackboost_log( "DEBUG: Successfully wrapped current reply (using markers).", 'chat_bubbles' );
				}
			}

		} else {
			// Fallback? Or just log failure.
			if ( function_exists( 'stackboost_log' ) ) {
				stackboost_log( "DEBUG: Failed to find '<!--SB_CURRENT_START-->' markers for current reply.", 'chat_bubbles' );
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
			}

			// Switched back to box-shadow to support spread radius as explicitly requested
			$shadow_css = "box-shadow: {$shadow_distance}px {$shadow_distance}px {$shadow_blur}px {$shadow_spread}px {$shadow_color} !important;";
		}

		foreach ( $types as $type ) {
			$styles = $this->get_styles_for_type( $type );
			if ( empty( $styles ) ) {
				continue;
			}

			// Roots
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
			if ( $styles['alignment'] === 'right' ) {
				$css .= "margin-left: auto !important; margin-right: 0 !important;";
			} elseif ( $styles['alignment'] === 'center' ) {
				$css .= "margin: 0 auto !important;";
			} else {
				$css .= "margin-right: auto !important; margin-left: 0 !important;";
			}

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

			// Hide Avatar
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

			// Text Color inside
			$color_selectors = [];
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

			// Image Bounding Box
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
	 * Helper to get StackBoost Theme Colors.
	 */
	public function get_stackboost_theme_colors( $slug ) {
		$themes = [
			'sb-theme-wordpress-sync' => [
				'accent' => '#2271b1',
				'text_on_accent' => '#ffffff',
				'bg_main' => '#f0f0f1',
			],
			'sb-theme-supportcandy-sync' => [
				'accent' => '#2271b1',
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
						'text_color'  => '#3c434a',
						'alignment'   => 'left',
						'radius'      => '15',
						'padding'     => '15',
					]);
				}
				$styles['font_family'] = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif';
				break;

			case 'supportcandy':
				$sc_settings = get_option( 'wpsc-ap-individual-ticket', [] );
				$reply_primary = $sc_settings['reply-primary-color'] ?? '#2c3e50';
				$note_primary = $sc_settings['note-primary-color'] ?? '#fffbcc';
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
				if ( $type === 'agent' ) {
					$styles = array_merge( $defaults, [
						'bg_color'    => '#007aff',
						'text_color'  => '#ffffff',
						'alignment'   => 'right',
						'width'       => '75',
						'radius'      => '20',
					]);
				} elseif ( $type === 'note' ) {
					$styles = array_merge( $defaults, [
						'bg_color'    => '#fffbcc',
						'text_color'  => '#333333',
						'alignment'   => 'center',
						'width'       => '85',
						'radius'      => '10',
					]);
				} else {
					$styles = array_merge( $defaults, [
						'bg_color'    => '#34c759',
						'text_color'  => '#ffffff',
						'alignment'   => 'left',
						'width'       => '75',
						'radius'      => '20',
					]);
				}
				$styles['font_family'] = '-apple-system, BlinkMacSystemFont, sans-serif';
				break;

			case 'android':
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
							'bg_color'    => '#e6e6e6',
							'text_color'  => '#3c434a',
							'alignment'   => 'left',
							'radius'      => '15',
						]);
					}
					$styles['font_family'] = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif';
				}
				break;
		}

		if ( strpos( $styles['bg_color'], 'var(' ) === 0 ) {
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
