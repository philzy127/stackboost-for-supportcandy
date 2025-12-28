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
			// If theme is NOT custom, logic is handled in get_styles_for_type via 'tail' key from preset.
			if ( $styles['tail'] !== 'none' && $type !== 'note' && $styles['alignment'] !== 'center' ) {
				$tail_color = $styles['bg_color'];

				$css .= "{$selector} { position: relative !important; overflow: visible !important;}";
				$css .= "{$selector}::after { content: ''; position: absolute; width: 0; height: 0; border-style: solid; z-index: 1; }";

				if ( $styles['alignment'] === 'right' ) {
					// Right Tail
					if ( $styles['tail'] === 'sharp' ) {
						// Sharp Triangle
						$css .= "{$selector}::after { border-width: 10px 0 10px 15px; border-color: transparent transparent transparent {$tail_color}; right: -10px; bottom: 10px; }";
					} else {
						// Rounded Tail (Skew approximation)
						$css .= "{$selector}::after { border-width: 15px 0 0 15px; border-color: transparent transparent transparent {$tail_color}; right: -8px; bottom: 0; transform: skewX(-10deg); }";
					}
				} else {
					// Left Tail
					if ( $styles['tail'] === 'sharp' ) {
						$css .= "{$selector}::after { border-width: 10px 15px 10px 0; border-color: transparent {$tail_color} transparent transparent; left: -10px; bottom: 10px; }";
					} else {
						// Rounded Tail
						$css .= "{$selector}::after { border-width: 15px 15px 0 0; border-color: transparent {$tail_color} transparent transparent; left: -8px; bottom: 0; transform: skewX(10deg); }";
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
		$theme = $options['chat_bubbles_theme'] ?? 'default';
		// Note: Tail is now handled by preset unless Custom/Default/Classic?
		// User: "Tail selection belongs on the Settings Tab as the theme selector... it applies to both agent and customer replies."
		// So we always respect global tail, unless the Theme specifically forbids it (like Modern)?
		// The updated logic says "remove the tails for now unless they are theme specific".
		// This means for Custom/Default, we might NOT show a tail unless we add a setting back.
		// BUT the user also said "Tail selection belongs on the Settings Tab".
		// Contradiction? "Remove the tails for now unless they are theme specific" vs "Tail selection belongs on Settings Tab".
		// Interpretation: Remove the visual 'Tail Style' DROPDOWN from the UI (done in Settings.php), but apply theme-specific tails in logic.
		// Wait, user said: "Tail selection belongs on the Settings Tab as the theme selector...".
		// Then said: "Let's remove the tails for now unless they are theme specific."
		// This likely means: Remove the manual control. Let the Theme decide.

		$tail = 'none'; // Default to none if theme doesn't specify

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
		switch ( $theme ) {
			case 'stackboost':
				// StackBoost Theme (Dynamic from Appearance)
				// We use CSS variables because we can't easily read JS/CSS state in PHP.
				// This works for Admin View. For Email, it will fall back to Defaults (white/grey) or we try to map common ones.
				// We'll use the variables.
				if ( $type === 'agent' ) {
					$styles = array_merge( $defaults, [
						'bg_color'    => 'var(--sb-accent, #2271b1)', // Dynamic variable
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
						'bg_color'    => 'var(--sb-bg-main, #f0f0f1)', // Dynamic variable (approx)
						'text_color'  => '#3c434a',
						'alignment'   => 'left',
						'radius'      => '15',
					]);
				}
				$styles['font_family'] = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif';
				$tail = 'round';
				break;

			case 'supportcandy':
				// SupportCandy (Dynamic from SC Options)
				$sc_settings = get_option( 'wpsc-ap-individual-ticket', [] );

				// Fallbacks
				$reply_primary = $sc_settings['reply-primary-color'] ?? '#2c3e50';
				$reply_secondary = $sc_settings['reply-secondary-color'] ?? '#777777';
				$note_primary = $sc_settings['note-primary-color'] ?? '#8e6600';
				$note_secondary = $sc_settings['note-secondary-color'] ?? '#8e8d45';

				if ( $type === 'agent' ) {
					$styles = array_merge( $defaults, [
						'bg_color'    => $reply_primary,
						'text_color'  => '#ffffff',
						'alignment'   => 'right',
						'radius'      => '5',
					]);
				} elseif ( $type === 'note' ) {
					$styles = array_merge( $defaults, [
						'bg_color'    => '#fdfdfd', // SC doesn't have a bg color for note body usually, just border?
						// SC settings have 'note-primary' (border/title).
						'bg_color'    => $note_primary, // Using primary for bubble BG? Might be too dark.
						// Let's stick to a safe light yellow or use white with border color.
						// Since we removed borders, let's use a lighter shade or white.
						'bg_color'    => '#fffbcc',
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
				$tail = 'none';
				break;

			case 'classic':
				// Previous "SupportCandy" blue/grey hardcoded theme
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
				$tail = 'none';
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
				$tail = 'round';
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
				$tail = 'sharp';
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
				$tail = 'none';
				break;

			case 'custom':
			case 'default': // Default mimics Custom/StackBoost logic but without dynamic vars? Or just blue/grey defaults.
			default:
				// Load from user settings if Custom, else use Defaults for "Default" theme
				if ( $theme === 'custom' ) {
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
				} else {
					// Default Theme (Blue/Grey Standard)
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
					$tail = 'round';
				}
				break;
		}

		// Apply Tail Logic (Theme Specific)
		$styles['tail'] = $tail;

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
