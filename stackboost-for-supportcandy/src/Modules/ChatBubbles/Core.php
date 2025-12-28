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
	}

	/**
	 * Enqueue Ticket View Styles.
	 */
	public function enqueue_ticket_styles( $hook_suffix ) {
		// Only load on Ticket View or Ticket List (if quick view exists)
		// For now, let's target the ticket view page specifically
		// The hook suffix for ticket view is typically 'supportcandy_page_wpsc-tickets'
		// or if viewing a single ticket, it relies on the URL parameters within that page.
		// But wait, SupportCandy loads individual tickets via AJAX on the ticket list page too!
		// So we must load styles on the main ticket page.

		if ( strpos( $hook_suffix, 'wpsc-tickets' ) === false && strpos( $hook_suffix, 'wpsc-view-ticket' ) === false ) {
			return;
		}

		// Check global enable switch
		$options = get_option( 'stackboost_settings', [] );
		if ( empty( $options['chat_bubbles_enable'] ) ) {
			return;
		}

		$css = $this->generate_css();
		wp_add_inline_style( 'supportcandy-admin-style', $css );
	}

	/**
	 * Process Email Content.
	 * Wraps the new message content in a styled bubble container.
	 *
	 * @param object $en The Email Notification object.
	 * @return object The modified Email Notification object.
	 */
	public function process_email_content( $en ) {
		// Check global enable switch
		$options = get_option( 'stackboost_settings', [] );
		if ( empty( $options['chat_bubbles_enable'] ) ) {
			return $en;
		}

		// Only proceed if we have a valid thread object
		if ( ! isset( $en->thread ) || ! is_object( $en->thread ) ) {
			return $en;
		}

		// Only process reply and note types
		if ( ! in_array( $en->thread->type, [ 'reply', 'note', 'report' ] ) ) {
			return $en;
		}

		// Generate the style for this thread type
		$thread_type = $en->thread->type;
		$user_type   = 'agent'; // Default

		if ( $thread_type === 'report' || $thread_type === 'reply' ) {
			// Determine if customer or agent
			$is_agent = false;
			if ( isset( $en->thread->customer ) ) {
				$user = get_user_by( 'email', $en->thread->customer->email );
				if ( $user && $user->has_cap( 'wpsc_agent' ) ) {
					$is_agent = true;
				}
			}
			$user_type = $is_agent ? 'agent' : 'customer';
		} elseif ( $thread_type === 'note' ) {
			$user_type = 'note';
		}

		// Get Styles
		$styles = $this->get_styles_for_type( $user_type );
		if ( empty( $styles ) ) {
			return $en;
		}

		// Generate Inline CSS for Email (Simpler than Ticket View)
		// We use a div wrapper with inline styles.
		// Outlook fallback: No border-radius, simple background.
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

		// Font Styles for Email
		if ( ! empty( $styles['font_bold'] ) ) {
			$inline_css .= " font-weight: bold;";
		}
		if ( ! empty( $styles['font_italic'] ) ) {
			$inline_css .= " font-style: italic;";
		}
		if ( ! empty( $styles['font_underline'] ) ) {
			$inline_css .= " text-decoration: underline;";
		}

		// Borders for Email
		if ( ! empty( $styles['border_width'] ) && $styles['border_width'] > 0 ) {
			$border_color = $styles['border_color'] ?: $styles['bg_color'];
			$inline_css .= sprintf( " border: %dpx solid %s;", $styles['border_width'], $border_color );
		}

		// Get the content we want to wrap
		// Since we can't easily find the replaced macro in the body,
		// and SupportCandy doesn't provide a filter for the macro output directly (except generic default),
		// We have to use a search-and-replace strategy on the body using the known thread content.

		// Get the printable string exactly as SupportCandy generates it
		$search_html = $en->thread->get_printable_string();

		// Create the wrapper
		// Note: We use a table for better Outlook compatibility if needed, but a div is safer for the text content itself.
		// Let's use a div.
		$replace_html = '<div style="' . esc_attr( $inline_css ) . '">' . $search_html . '</div>';

		// Perform the replacement
		// Use preg_replace with limit 1 to ensure we only replace the main body if it appears multiple times (unlikely but safe)
		// We need to escape special regex chars in the search string
		$pattern = '/' . preg_quote( $search_html, '/' ) . '/';
		$en->body = preg_replace( $pattern, $replace_html, $en->body, 1 );

		return $en;
	}

	/**
	 * Generate CSS for Admin View.
	 */
	public function generate_css(): string {
		$types = [ 'agent', 'customer', 'note' ];
		$css   = '';

		foreach ( $types as $type ) {
			$styles = $this->get_styles_for_type( $type );
			if ( empty( $styles ) ) {
				continue;
			}

			// Map type to SC classes
			$selector = '';
			if ( $type === 'agent' ) {
				$selector = '.wpsc-thread.reply.agent .thread-body, .wpsc-thread.report.agent .thread-body';
			} elseif ( $type === 'customer' ) {
				$selector = '.wpsc-thread.reply.customer .thread-body, .wpsc-thread.report.customer .thread-body';
			} elseif ( $type === 'note' ) {
				$selector = '.wpsc-thread.note .thread-body';
			}

			// Build CSS Rule
			$css .= "{$selector} {";
			$css .= "background-color: {$styles['bg_color']} !important;";
			$css .= "color: {$styles['text_color']} !important;";
			$css .= "border-radius: {$styles['radius']}px !important;";
			$css .= "width: {$styles['width']}% !important;";

			// Font Family
			if ( ! empty( $styles['font_family'] ) ) {
				// Basic sanitization to prevent breaking out of CSS
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

			// Border
			if ( ! empty( $styles['border_width'] ) && $styles['border_width'] > 0 ) {
				$border_color = $styles['border_color'] ?: '#cccccc';
				$css .= "border: {$styles['border_width']}px solid {$border_color} !important;";
			}

			// Alignment
			if ( $styles['alignment'] === 'right' ) {
				$css .= "margin-left: auto !important; margin-right: 0 !important;";
			} elseif ( $styles['alignment'] === 'center' ) {
				$css .= "margin: 0 auto !important;";
			} else {
				$css .= "margin-right: auto !important; margin-left: 0 !important;";
			}

			// Padding (Standardize)
			$css .= "padding: 15px !important;";

			$css .= "}";

			// Tail Logic (Pseudo-elements)
			// Only apply tail if enabled globally, NOT a note, and NOT center aligned
			if ( $styles['tail'] !== 'none' && $type !== 'note' && $styles['alignment'] !== 'center' ) {
				$tail_color = $styles['bg_color'];
				// If border exists, we need to try to match it, but CSS triangles don't support borders easily.
				// A common hack is to use a second pseudo element for the border, offset slightly.
				// For now, we will just use the background color. If users want borders + tails, it might look slightly off without SVG.
				// However, the user requirement "tail has to have that border too" implies we should try.

				$css .= "{$selector} { position: relative !important; overflow: visible !important;}";
				$css .= "{$selector}::after { content: ''; position: absolute; width: 0; height: 0; border-style: solid; z-index: 1; }";

				// Border Tail Logic (::before as border)
				if ( ! empty( $styles['border_width'] ) && $styles['border_width'] > 0 ) {
					$border_color = $styles['border_color'] ?: '#cccccc';
					$css .= "{$selector}::before { content: ''; position: absolute; width: 0; height: 0; border-style: solid; z-index: 0; }";
				}

				if ( $styles['alignment'] === 'right' ) {
					// Right Tail
					$right_pos = -10;
					$bottom_pos = 10;

					if ( $styles['tail'] === 'sharp' ) {
						// Sharp Triangle
						$css .= "{$selector}::after { border-width: 10px 0 10px 15px; border-color: transparent transparent transparent {$tail_color}; right: -10px; bottom: 10px; }";

						if ( ! empty( $styles['border_width'] ) && $styles['border_width'] > 0 ) {
							// Adjust positions for border width
							$offset = $styles['border_width'];
							$css .= "{$selector}::before { border-width: " . (10+$offset) . "px 0 " . (10+$offset) . "px " . (15+$offset) . "px; border-color: transparent transparent transparent {$border_color}; right: -" . (10+$offset) . "px; bottom: " . (10-$offset) . "px; }";
						}

					} else {
						// Rounded Tail (Using Mask/Radial Gradient is cleaner, but harder to inject via inline CSS string)
						// Let's use the 'skew' method which approximates a chat bubble tail
						$css .= "{$selector}::after { border-width: 15px 0 0 15px; border-color: transparent transparent transparent {$tail_color}; right: -8px; bottom: 0; transform: skewX(-10deg); }";

						if ( ! empty( $styles['border_width'] ) && $styles['border_width'] > 0 ) {
							$offset = $styles['border_width']; // Rough approximation
							$css .= "{$selector}::before { border-width: " . (15+$offset) . "px 0 0 " . (15+$offset) . "px; border-color: transparent transparent transparent {$border_color}; right: -" . (8+$offset) . "px; bottom: -" . $offset . "px; transform: skewX(-10deg); }";
						}
					}
				} else {
					// Left Tail
					if ( $styles['tail'] === 'sharp' ) {
						$css .= "{$selector}::after { border-width: 10px 15px 10px 0; border-color: transparent {$tail_color} transparent transparent; left: -10px; bottom: 10px; }";

						if ( ! empty( $styles['border_width'] ) && $styles['border_width'] > 0 ) {
							$offset = $styles['border_width'];
							$css .= "{$selector}::before { border-width: " . (10+$offset) . "px " . (15+$offset) . "px " . (10+$offset) . "px 0; border-color: transparent {$border_color} transparent transparent; left: -" . (10+$offset) . "px; bottom: " . (10-$offset) . "px; }";
						}

					} else {
						// Rounded Tail
						$css .= "{$selector}::after { border-width: 15px 15px 0 0; border-color: transparent {$tail_color} transparent transparent; left: -8px; bottom: 0; transform: skewX(10deg); }";

						if ( ! empty( $styles['border_width'] ) && $styles['border_width'] > 0 ) {
							$offset = $styles['border_width'];
							$css .= "{$selector}::before { border-width: " . (15+$offset) . "px " . (15+$offset) . "px 0 0; border-color: transparent {$border_color} transparent transparent; left: -" . (8+$offset) . "px; bottom: -" . $offset . "px; transform: skewX(10deg); }";
						}
					}
				}
			}

			// Text Color inside (links, etc)
			$css .= "{$selector} .thread-text, {$selector} .thread-header h2, {$selector} .thread-header span { color: {$styles['text_color']} !important; }";

			// Ensure header layout doesn't break
			// We might need to adjust header margin
			$css .= "{$selector} .thread-header { margin-bottom: 10px; border-bottom: 1px solid rgba(0,0,0,0.1); padding-bottom: 5px; }";
		}

		return $css;
	}

	/**
	 * Get styles for a specific type based on settings/theme.
	 */
	private function get_styles_for_type( $type ): array {
		$options = get_option( 'stackboost_settings', [] );

		// Prefix for local overrides
		$prefix = "chat_bubbles_{$type}_";

		// Global Settings
		$theme = $options['chat_bubbles_theme'] ?? 'custom';
		$tail  = $options['chat_bubbles_tail'] ?? 'none';
		$border_width = isset( $options['chat_bubbles_border_width'] ) ? $options['chat_bubbles_border_width'] : 0;
		$border_color = isset( $options['chat_bubbles_border_color'] ) ? $options['chat_bubbles_border_color'] : '#cccccc';

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
		];

		$styles = [];

		// Theme Logic
		// Note: Note types now default to Center/85% in themes as requested.
		switch ( $theme ) {
			case 'stackboost':
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
						'bg_color'    => '#f0f0f1',
						'text_color'  => '#3c434a',
						'alignment'   => 'left',
						'radius'      => '15',
					]);
				}
				$styles['font_family'] = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif';
				break;

			case 'supportcandy':
				$wpsc_settings = get_option( 'wpsc_appearance_settings', [] );
				$primary = $wpsc_settings['primary_color'] ?? '#2271b1';

				if ( $type === 'agent' ) {
					$styles = array_merge( $defaults, [
						'bg_color'    => $primary,
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
						'bg_color'    => '#fffae6',
						'text_color'  => '#333333',
						'alignment'   => 'center',
						'width'       => '85',
						'radius'      => '10',
					]);
				} else {
					$styles = array_merge( $defaults, [
						'bg_color'    => '#e5e5ea',
						'text_color'  => '#000000',
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
			default:
				// Load from user settings
				$styles = [
					'bg_color'    => $options["{$prefix}bg_color"] ?? $defaults['bg_color'],
					'text_color'  => $options["{$prefix}text_color"] ?? $defaults['text_color'],
					'font_family' => $options["{$prefix}font_family"] ?? $defaults['font_family'],
					'font_size'   => $options["{$prefix}font_size"] ?? $defaults['font_size'],
					'alignment'   => $options["{$prefix}alignment"] ?? $defaults['alignment'],
					'width'       => $options["{$prefix}width"] ?? $defaults['width'],
					'radius'      => $options["{$prefix}radius"] ?? $defaults['radius'],

					// Font Styles (Checkboxes)
					'font_bold'      => ! empty( $options["{$prefix}font_bold"] ) ? 1 : 0,
					'font_italic'    => ! empty( $options["{$prefix}font_italic"] ) ? 1 : 0,
					'font_underline' => ! empty( $options["{$prefix}font_underline"] ) ? 1 : 0,
				];
				break;
		}

		// Apply Global Tail setting (unless custom overrides? No, user said tail belongs on settings tab)
		// But note: Core logic above uses this. 'custom' theme might have had local tail, but now we use global.
		// However, if theme is NOT custom, we might want to respect the theme's inherent tail style?
		// User said: "Tail selection belongs on the Settings Tab as the theme selector... it applies to both agent and customer replies."
		// This implies the Global Setting overrides everything, OR acts as the configuration for "Custom".
		// But if I choose "iOS" theme, I expect rounded tails. If I choose "Modern", I expect none.
		// Let's assume Global Tail Setting applies when Theme is Custom OR acts as an override?
		// "The theme Preset should be in the new Settings Tab... Choosing a theme applies to all three... Tail selection belongs on the Settings Tab...".
		// If a Theme is selected, it should probably dictate the tail unless the user explicitly changes it?
		// But the UI shows them side-by-side.
		// Best approach: If Theme is Custom, use Global Tail. If Theme is Preset, use Preset Tail.
		// BUT the user moved the selector to the main tab alongside Theme. This suggests they want to mix and match?
		// Let's allow the Global Tail selector to override the theme default if set to something other than 'theme_default'?
		// Current selector options are None, Round, Sharp.
		// Let's use the Global Tail setting as the source of truth for ALL themes, to give user control.

		$styles['tail'] = $tail;

		// Apply Global Border
		$styles['border_width'] = absint( $border_width );
		$styles['border_color'] = sanitize_hex_color( $border_color );

		// SANITIZATION
		$styles['bg_color'] = sanitize_hex_color($styles['bg_color']) ?: $defaults['bg_color'];
		$styles['text_color'] = sanitize_hex_color($styles['text_color']) ?: $defaults['text_color'];
		$styles['width'] = absint($styles['width']);
		if ($styles['width'] < 0 || $styles['width'] > 100) $styles['width'] = 85;
		$styles['radius'] = absint($styles['radius']);
		if ($styles['radius'] > 100) $styles['radius'] = 100;
		$styles['font_size'] = absint($styles['font_size']);
		$styles['font_family'] = sanitize_text_field($styles['font_family']);

		return $styles;
	}
}
