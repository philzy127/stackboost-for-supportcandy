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
	 * Generate CSS for Admin View.
	 */
	public function generate_css(): string {
		$options = get_option( 'stackboost_settings', [] );
		$types = [ 'agent', 'customer', 'note', 'log' ]; // Added log
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

			$wrapper_selectors = [];
			foreach ($roots as $root) {
				// SIMPLIFIED LOGIC: Use native classes
				if ( $type === 'agent' ) {
					$wrapper_selectors[] = "{$root} .wpsc-thread.agent";
				} elseif ( $type === 'customer' ) {
					$wrapper_selectors[] = "{$root} .wpsc-thread.customer";
				} elseif ( $type === 'note' ) {
					$wrapper_selectors[] = "{$root} .wpsc-thread.note";
				} elseif ( $type === 'log' ) {
					$wrapper_selectors[] = "{$root} .wpsc-thread.log";
				}
			}
			$wrapper_selector_str = implode(', ', $wrapper_selectors);

			// 1. Style the Wrapper (The Bubble)
			$css .= "{$wrapper_selector_str} {";
			$css .= "background-color: {$styles['bg_color']} !important;";
			$css .= "color: {$styles['text_color']} !important;";
			$css .= "border-radius: {$styles['radius']}px !important;";
			$css .= "width: {$styles['width']}% !important;";
			$css .= "max-width: {$styles['width']}% !important;";
			$css .= "display: flex !important;";
			$css .= "flex-direction: column !important;"; // Ensure column layout for bubble content

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

			// --- Alignment Logic using separate property sets ---
			// We define standard properties for Left, Center, Right
			// and apply them cleanly.

			$align_css = "";
			$thread_header_extra = "";
			$user_info_extra = "";
			$user_info_name_extra = "";
			$user_info_time_extra = "";
			$thread_text_extra = "";
			$avatar_extra = "";

			if ( $styles['alignment'] === 'right' ) {
				// RIGHT ALIGNMENT
				// Wrapper: align-self end (via margin-left auto)
				$align_css .= "margin-left: auto !important; margin-right: 0 !important;";
				$align_css .= "align-items: flex-end !important;"; // Align content inside bubble to right
				$align_css .= "margin-bottom: 20px !important;";

				// Header Re-ordering for Right Align: [Time] [Name] [Avatar]
				// DOM: [Avatar] [UserInfo: [Name] [Time]]

				// 1. Thread Header: Use standard flex row.
				// We need to swap Avatar and UserInfo.
				// Avatar is first in DOM. UserInfo is second.
				// Use ORDER.
				$thread_header_extra .= "display: flex !important; flex-direction: row !important; justify-content: flex-end !important; width: 100% !important;";

				// Avatar: Move to End (Right)
				$avatar_extra .= "order: 2 !important; margin-left: 10px !important; margin-right: 0 !important;";

				// UserInfo: Move to Start (Left)
				$user_info_extra .= "order: 1 !important; text-align: right !important; justify-content: flex-end !important;";

				// Inside UserInfo: [Name Group] [Time]
				// We want [Time] [Name Group]
				// Time is 2nd in DOM. Name is 1st in DOM.
				// Wait, DOM is .user-info > [div(name), span(time)]
				// So Name is 1st, Time is 2nd.
				// We want Time first.
				$user_info_time_extra .= "order: 1 !important; margin-right: 10px !important; margin-left: 0 !important;";
				$user_info_name_extra .= "order: 2 !important;";

				// Text Body Alignment
				$thread_text_extra .= "text-align: right !important;";

			} elseif ( $styles['alignment'] === 'center' ) {
				// CENTER ALIGNMENT
				$align_css .= "margin-left: auto !important; margin-right: auto !important;";
				$align_css .= "align-items: center !important;";
				$align_css .= "margin-bottom: 20px !important;";

				$thread_header_extra .= "justify-content: center !important;";
				$user_info_extra .= "text-align: center !important; justify-content: center !important;";
				$thread_text_extra .= "text-align: center !important;";

			} else {
				// LEFT ALIGNMENT (Default)
				$align_css .= "margin-right: auto !important; margin-left: 0 !important;";
				$align_css .= "align-items: flex-start !important;";
				$align_css .= "margin-bottom: 20px !important;";

				// Standard Layout: [Avatar] [UserInfo: [Name] [Time]]
				$thread_header_extra .= "justify-content: flex-start !important;";

				// Avatar First
				$avatar_extra .= "order: 1 !important; margin-right: 15px !important;";

				// UserInfo Second
				$user_info_extra .= "order: 2 !important; text-align: left !important; justify-content: flex-start !important;";

				// Inside UserInfo: [Name] [Time] (Standard)
				// Name First
				$user_info_name_extra .= "order: 1 !important;";
				// Time Second (pushed right usually)
				$user_info_time_extra .= "order: 2 !important; margin-left: auto !important;";

				$thread_text_extra .= "text-align: left !important;";
			}

			$css .= $align_css;
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

			// 2. Reset Inner Body Styles
			$body_selectors = [];
			foreach ($wrapper_selectors as $sel) {
				$body_selectors[] = $sel . ' .thread-body';
			}
			$body_selector_str = implode(', ', $body_selectors);

			$css .= "{$body_selector_str} {";
			$css .= "background: transparent !important;";
			$css .= "border: none !important;";
			$css .= "box-shadow: none !important;";
			$css .= "padding: 0 !important;";
			$css .= "margin: 0 !important;";
			$css .= "width: 100% !important;"; // Ensure body takes full width of flex container
			$css .= "max-width: 100% !important;";
			$css .= "}";

			// 3. Avatar Handling
			$avatar_selectors = [];
			foreach ($wrapper_selectors as $sel) {
				$avatar_selectors[] = $sel . ' .thread-avatar';
			}
			$avatar_selector_str = implode(', ', $avatar_selectors);

			if ( empty( $options['chat_bubbles_show_avatars'] ) ) {
				$css .= "{$avatar_selector_str} { display: none !important; }";
			} else {
				$css .= "{$avatar_selector_str} {";
				$css .= "display: block !important;";
				$css .= $avatar_extra;
				$css .= "align-self: flex-start !important;";
				$css .= "}";
			}

			// 4. Thread Header & Sub-element Layout
			$header_selectors = [];
			$user_info_selectors = [];
			$user_info_name_selectors = [];
			$user_info_time_selectors = [];
			$thread_text_selectors = [];

			foreach ($wrapper_selectors as $sel) {
				$header_selectors[] = "{$sel} .thread-header";
				$user_info_selectors[] = "{$sel} .thread-header .user-info";
				// SupportCandy structure: .user-info > div (name group)
				$user_info_name_selectors[] = "{$sel} .thread-header .user-info > div";
				$user_info_time_selectors[] = "{$sel} .thread-header .user-info .thread-time";
				$thread_text_selectors[] = "{$sel} .thread-text";
			}

			// Apply Header Logic
			if ($thread_header_extra) {
				$css .= implode(', ', $header_selectors) . " { " . $thread_header_extra . " margin-bottom: 10px; border-bottom: 1px solid rgba(0,0,0,0.1); padding-bottom: 5px; }";
			}
			if ($user_info_extra) {
				$css .= implode(', ', $user_info_selectors) . " { display: flex !important; flex: 1 !important; align-items: center !important; " . $user_info_extra . " }";
			}
			if ($user_info_name_extra) {
				$css .= implode(', ', $user_info_name_selectors) . " { display: flex; align-items: center; " . $user_info_name_extra . " }";
			}
			if ($user_info_time_extra) {
				$css .= implode(', ', $user_info_time_selectors) . " { " . $user_info_time_extra . " }";
			}
			if ($thread_text_extra) {
				$css .= implode(', ', $thread_text_selectors) . " { " . $thread_text_extra . " }";
			}

			// 5. Text Color inside
			$color_selectors = [];
			$sub_elements = ['.thread-text', '.user-info h2', '.user-info h2.user-name', '.user-info span', 'a', '.thread-header h2', '.thread-header span', '.wpsc-log-diff'];

			foreach ($wrapper_selectors as $part) {
				foreach ($sub_elements as $el) {
					$color_selectors[] = trim($part) . ' ' . $el;
				}
			}
			$color_selector_str = implode(', ', $color_selectors);

			$css .= "{$color_selector_str} { color: {$styles['text_color']} !important; }";

			// 6. Image Bounding Box
			if ( ! empty( $options['chat_bubbles_image_box'] ) ) {
				$img_selectors = [];
				foreach ($wrapper_selectors as $part) {
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
				} elseif ( $type === 'log' ) {
					$styles = array_merge( $defaults, [
						'bg_color'    => '#f0f0f1',
						'text_color'  => '#666666',
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
				} elseif ( $type === 'log' ) {
					$styles = array_merge( $defaults, [
						'bg_color'    => '#f9f9f9',
						'text_color'  => '#666666',
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
				} elseif ( $type === 'log' ) {
					$styles = array_merge( $defaults, [
						'bg_color'    => '#f1f1f1',
						'text_color'  => '#666666',
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
				} elseif ( $type === 'log' ) {
					$styles = array_merge( $defaults, [
						'bg_color'    => '#f2f2f7',
						'text_color'  => '#8e8e93',
						'alignment'   => 'center',
						'width'       => '90',
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
				} elseif ( $type === 'log' ) {
					$styles = array_merge( $defaults, [
						'bg_color'    => '#f0f2f5',
						'text_color'  => '#54656f',
						'alignment'   => 'center',
						'width'       => '90',
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
				} elseif ( $type === 'log' ) {
					$styles = array_merge( $defaults, [
						'bg_color'    => '#f2f2f2',
						'text_color'  => '#999999',
						'alignment'   => 'center',
						'width'       => '90',
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
					} elseif ( $type === 'log' ) {
						$styles = array_merge( $defaults, [
							'bg_color'    => '#f0f0f1',
							'text_color'  => '#666666',
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
