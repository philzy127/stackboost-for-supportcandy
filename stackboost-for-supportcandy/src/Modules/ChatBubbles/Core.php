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
		$inline_css = "background-color: {$styles['bg_color']}; color: {$styles['text_color']}; padding: 15px; margin-bottom: 10px; border-radius: {$styles['radius']}px; width: {$styles['width']}%;";

		if ( $styles['alignment'] === 'right' ) {
			$inline_css .= " margin-left: auto; margin-right: 0;";
		} else {
			$inline_css .= " margin-right: auto; margin-left: 0;";
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
		// Use str_replace directly. Since $search_html comes from the same method called by the macro, it should match.
		// However, macro replacement might have trimmed it or processed it?
		// WPSC_Macros::replace just calls get_printable_string(), so it should be exact.
		$en->body = str_replace( $search_html, $replace_html, $en->body );

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
				$css .= "font-family: {$styles['font_family']} !important;";
			}

			// Alignment
			if ( $styles['alignment'] === 'right' ) {
				$css .= "margin-left: auto !important; margin-right: 0 !important;";
			} else {
				$css .= "margin-right: auto !important; margin-left: 0 !important;";
			}

			// Padding (Standardize)
			$css .= "padding: 15px !important;";

			$css .= "}";

			// Tail Logic (Pseudo-elements)
			if ( $styles['tail'] !== 'none' ) {
				$tail_color = $styles['bg_color'];
				$css .= "{$selector} { position: relative !important; }";
				$css .= "{$selector}::after { content: ''; position: absolute; width: 0; height: 0; border-style: solid; }";

				if ( $styles['alignment'] === 'right' ) {
					// Right Tail
					if ( $styles['tail'] === 'sharp' ) {
						$css .= "{$selector}::after { border-width: 10px 0 10px 15px; border-color: transparent transparent transparent {$tail_color}; right: -10px; bottom: 10px; }";
					} else {
						// Roundish Tail (Simplified)
						$css .= "{$selector}::after { border-width: 15px 0 0 15px; border-color: transparent transparent transparent {$tail_color}; right: -8px; bottom: 0; transform: skewX(-10deg); }";
					}
				} else {
					// Left Tail
					if ( $styles['tail'] === 'sharp' ) {
						$css .= "{$selector}::after { border-width: 10px 15px 10px 0; border-color: transparent {$tail_color} transparent transparent; left: -10px; bottom: 10px; }";
					} else {
						// Roundish Tail
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

		// Key prefix based on type
		$prefix = "chat_bubbles_{$type}_";

		// Theme Preset
		$theme = $options["{$prefix}theme"] ?? 'custom'; // Default to custom if not set

		// Default Styles
		$defaults = [
			'bg_color'    => '#f1f1f1',
			'text_color'  => '#333333',
			'font_family' => '',
			'alignment'   => 'left',
			'width'       => '85',
			'radius'      => '15',
			'tail'        => 'none'
		];

		// Apply Preset Logic
		switch ( $theme ) {
			case 'stackboost':
				// StackBoost Theme (Blue/Dark)
				if ( $type === 'agent' ) {
					return [
						'bg_color'    => '#2271b1', // WP Blue
						'text_color'  => '#ffffff',
						'font_family' => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif',
						'alignment'   => 'right',
						'width'       => '85',
						'radius'      => '15',
						'tail'        => 'round'
					];
				} else {
					return [
						'bg_color'    => '#f0f0f1', // WP Grey
						'text_color'  => '#3c434a',
						'font_family' => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif',
						'alignment'   => 'left',
						'width'       => '85',
						'radius'      => '15',
						'tail'        => 'round'
					];
				}

			case 'supportcandy':
				// SupportCandy (Classic) - We actually just return empty to let SC CSS handle it?
				// User wants "SupportCandy Colors".
				// We can try to fetch them.
				$wpsc_settings = get_option( 'wpsc_appearance_settings', [] );
				$primary = $wpsc_settings['primary_color'] ?? '#2271b1';

				if ( $type === 'agent' ) {
					return [
						'bg_color'    => $primary,
						'text_color'  => '#ffffff',
						'font_family' => '',
						'alignment'   => 'right',
						'width'       => '85',
						'radius'      => '5',
						'tail'        => 'none'
					];
				} else {
					return [
						'bg_color'    => '#e5e5e5',
						'text_color'  => '#333333',
						'font_family' => '',
						'alignment'   => 'left',
						'width'       => '85',
						'radius'      => '5',
						'tail'        => 'none'
					];
				}

			case 'ios':
				// iMessage Style
				if ( $type === 'agent' ) {
					return [
						'bg_color'    => '#007aff', // iOS Blue
						'text_color'  => '#ffffff',
						'font_family' => '-apple-system, BlinkMacSystemFont, sans-serif',
						'alignment'   => 'right',
						'width'       => '75',
						'radius'      => '20',
						'tail'        => 'round'
					];
				} else {
					return [
						'bg_color'    => '#e5e5ea', // iOS Grey
						'text_color'  => '#000000',
						'font_family' => '-apple-system, BlinkMacSystemFont, sans-serif',
						'alignment'   => 'left',
						'width'       => '75',
						'radius'      => '20',
						'tail'        => 'round'
					];
				}

			case 'android':
				// WhatsApp / Android Style
				if ( $type === 'agent' ) {
					return [
						'bg_color'    => '#d9fdd3', // WhatsApp Green
						'text_color'  => '#111b21',
						'font_family' => 'Roboto, sans-serif',
						'alignment'   => 'right',
						'width'       => '80',
						'radius'      => '8',
						'tail'        => 'sharp'
					];
				} else {
					return [
						'bg_color'    => '#ffffff',
						'text_color'  => '#111b21',
						'font_family' => 'Roboto, sans-serif',
						'alignment'   => 'left',
						'width'       => '80',
						'radius'      => '8',
						'tail'        => 'sharp'
					];
				}

			case 'modern':
				// Minimal Modern
				if ( $type === 'agent' ) {
					return [
						'bg_color'    => '#000000',
						'text_color'  => '#ffffff',
						'font_family' => 'Helvetica, Arial, sans-serif',
						'alignment'   => 'right',
						'width'       => '60',
						'radius'      => '0',
						'tail'        => 'none'
					];
				} else {
					return [
						'bg_color'    => '#f2f2f2',
						'text_color'  => '#000000',
						'font_family' => 'Helvetica, Arial, sans-serif',
						'alignment'   => 'left',
						'width'       => '60',
						'radius'      => '0',
						'tail'        => 'none'
					];
				}

			case 'custom':
			default:
				// Load from user settings
				return [
					'bg_color'    => $options["{$prefix}bg_color"] ?? $defaults['bg_color'],
					'text_color'  => $options["{$prefix}text_color"] ?? $defaults['text_color'],
					'font_family' => $options["{$prefix}font_family"] ?? $defaults['font_family'],
					'alignment'   => $options["{$prefix}alignment"] ?? $defaults['alignment'],
					'width'       => $options["{$prefix}width"] ?? $defaults['width'],
					'radius'      => $options["{$prefix}radius"] ?? $defaults['radius'],
					'tail'        => $options["{$prefix}tail"] ?? $defaults['tail'],
				];
		}
	}
}
