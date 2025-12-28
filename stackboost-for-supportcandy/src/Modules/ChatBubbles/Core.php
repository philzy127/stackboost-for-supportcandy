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
		if ( strpos( $hook_suffix, 'wpsc-tickets' ) === false && strpos( $hook_suffix, 'wpsc-view-ticket' ) === false ) {
			return;
		}

		// Check ticket specific enable switch
		$options = get_option( 'stackboost_settings', [] );
		if ( empty( $options['chat_bubbles_enable_ticket'] ) ) {
			return;
		}

		$css = $this->generate_css();
		wp_add_inline_style( 'wpsc-admin', $css );

		if ( function_exists( 'stackboost_log' ) ) {
			stackboost_log( 'ChatBubbles: Styles enqueued. Hook: ' . $hook_suffix, 'chat_bubbles' );
		}
	}

	/**
	 * Process Email Content.
	 * Wraps the new message content in a styled bubble container.
	 *
	 * @param object $en The Email Notification object.
	 * @return object The modified Email Notification object.
	 */
	public function process_email_content( $en ) {
		// Check email specific enable switch
		$options = get_option( 'stackboost_settings', [] );
		if ( empty( $options['chat_bubbles_enable_email'] ) ) {
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

		// Border Styles for Email
		if ( ! empty( $styles['border_style'] ) && $styles['border_style'] !== 'none' ) {
			$inline_css .= sprintf( " border: %s %dpx %s;", $styles['border_style'], $styles['border_width'], $styles['border_color'] );
		}

		// Get the content we want to wrap
		$search_html = $en->thread->get_printable_string();

		// Create the wrapper
		$replace_html = '<div style="' . esc_attr( $inline_css ) . '">' . $search_html . '</div>';

		// Perform the replacement
		$pattern = '/' . preg_quote( $search_html, '/' ) . '/';
		$en->body = preg_replace( $pattern, $replace_html, $en->body, 1 );

		if ( function_exists( 'stackboost_log' ) ) {
			stackboost_log( 'ChatBubbles: Email processed for thread type ' . $user_type, 'chat_bubbles' );
		}

		return $en;
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
			$shadow_depth = $options['chat_bubbles_shadow_depth'] ?? 'small';
			$opacity_pct  = $options['chat_bubbles_shadow_opacity'] ?? '40';
			$opacity_val  = intval( $opacity_pct ) / 100;

			$blur = '5px';
			$spread = '0px';

			if ( $shadow_depth === 'medium' ) {
				$blur = '10px';
			} elseif ( $shadow_depth === 'large' ) {
				$blur = '20px';
				$spread = '5px';
			}

			// Convert Hex to RGBA for Opacity Control
			if ( strpos( $shadow_color, '#' ) === 0 && strlen( $shadow_color ) === 7 ) {
				list( $r, $g, $b ) = sscanf( $shadow_color, "#%02x%02x%02x" );
				$shadow_color = "rgba({$r}, {$g}, {$b}, {$opacity_val})";
			} elseif ( strpos( $shadow_color, 'rgba' ) !== false ) {
				// If already rgba, we respect user input, maybe override alpha?
				// Let's rely on the hex conversion for now as the picker defaults to hex.
			}

			$shadow_css = "box-shadow: 0 2px {$blur} {$spread} {$shadow_color} !important;";
		}

		foreach ( $types as $type ) {
			$styles = $this->get_styles_for_type( $type );
			if ( empty( $styles ) ) {
				continue;
			}

			// Increased specificity to override SupportCandy default styles
			// Using ID selector + class hierarchy for maximum specificity

			$selector = '';
			if ( $type === 'agent' ) {
				$selector = '#wpsc-it-container .wpsc-thread.reply.agent .thread-body, #wpsc-it-container .wpsc-thread.report.agent .thread-body';
			} elseif ( $type === 'customer' ) {
				$selector = '#wpsc-it-container .wpsc-thread.reply.customer .thread-body, #wpsc-it-container .wpsc-thread.report.customer .thread-body';
			} elseif ( $type === 'note' ) {
				$selector = '#wpsc-it-container .wpsc-thread.note .thread-body';
			}

			// Build CSS Rule
			$css .= "{$selector} {";
			$css .= "background-color: {$styles['bg_color']} !important;";
			$css .= "color: {$styles['text_color']} !important;";
			$css .= "border-radius: {$styles['radius']}px !important;";
			$css .= "width: {$styles['width']}% !important;";

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

			// Text Color inside (links, etc)
			$css .= "{$selector} .thread-text, {$selector} .thread-header h2, {$selector} .thread-header span { color: {$styles['text_color']} !important; }";

			// Header layout adjustment
			$css .= "{$selector} .thread-header { margin-bottom: 10px; border-bottom: 1px solid rgba(0,0,0,0.1); padding-bottom: 5px; }";
		}

		return $css;
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
				// StackBoost Theme
				if ( $type === 'agent' ) {
					$styles = array_merge( $defaults, [
						'bg_color'    => 'var(--sb-accent, #2271b1)',
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
						'bg_color'    => 'var(--sb-bg-main, #f0f0f1)',
						'text_color'  => '#3c434a',
						'alignment'   => 'left',
						'radius'      => '15',
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
		$styles['font_size'] = absint($styles['font_size']);
		$styles['font_family'] = sanitize_text_field($styles['font_family']);
		$styles['border_width'] = absint($styles['border_width']);
		$styles['border_color'] = sanitize_hex_color($styles['border_color']) ?: '#cccccc';
		$styles['border_style'] = in_array($styles['border_style'], ['none', 'solid', 'dashed', 'dotted']) ? $styles['border_style'] : 'none';

		return $styles;
	}
}
