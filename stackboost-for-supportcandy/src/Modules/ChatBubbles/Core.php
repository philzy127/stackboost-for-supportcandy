<?php


namespace StackBoost\ForSupportCandy\Modules\ChatBubbles;

if ( ! defined( 'ABSPATH' ) ) exit;

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
			// Only load on Ticket View, Ticket List, or Settings Page
			if ( strpos( $hook_suffix, 'wpsc-tickets' ) === false && strpos( $hook_suffix, 'wpsc-view-ticket' ) === false && strpos( $hook_suffix, 'stackboost-chat-bubbles' ) === false ) {
				return; // Exit silent if not a ticket page
			}

			// If we are on our settings page, SupportCandy does NOT load its own styles. We must do it manually.
			if ( strpos( $hook_suffix, 'stackboost-chat-bubbles' ) !== false && defined( 'WPSC_PLUGIN_URL' ) && defined( 'WPSC_VERSION' ) ) {
				// Determine RTL
				$is_rtl = is_rtl();

				// Enqueue Framework
				$fw_css = $is_rtl ? 'framework/style-rtl.css' : 'framework/style.css';
				wp_enqueue_style( 'wpsc-framework', WPSC_PLUGIN_URL . $fw_css, [], WPSC_VERSION );

				// Enqueue Admin CSS
				$admin_css = $is_rtl ? 'asset/css/admin-rtl.css' : 'asset/css/admin.css';
				wp_enqueue_style( 'wpsc-admin', WPSC_PLUGIN_URL . $admin_css, ['wpsc-framework'], WPSC_VERSION );
			}

			$handle = 'wpsc-admin';
		} else {
			// Frontend Check
			// We register a dummy handle to attach our inline styles to, ensuring they load anywhere
			// SupportCandy might be present (shortcodes, widgets, etc).
			$handle = 'stackboost-chat-bubbles-frontend';
			wp_register_style( $handle, false, [], STACKBOOST_VERSION );
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
			$roots = ['.wpsc-it-container', '.wpsc-shortcode-container', '#wpsc-container', '.stackboost-chat-preview-container'];

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

			// Add StackBoost Ticket Card Selectors (Pro Feature)
			// These exist outside the standard WPSC containers, usually in a Tippy or dashboard wrapper.
			// We map our internal classes to the Chat Bubble types.
			if ( $type === 'agent' ) {
				$wrapper_selectors[] = '.stackboost-chat-right:not(.stackboost-chat-note) .stackboost-chat-bubble';
			} elseif ( $type === 'customer' ) {
				$wrapper_selectors[] = '.stackboost-chat-left .stackboost-chat-bubble';
			} elseif ( $type === 'note' ) {
				$wrapper_selectors[] = '.stackboost-chat-note .stackboost-chat-bubble';
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
			$css .= "align-items: flex-start !important;";

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

			// Alignment & Direction
			// Note: We deliberately set margin-bottom to ensure bubbles don't collapse on each other,
			// especially for 'center' alignment where '0 auto' was nuking the bottom margin.
			$child_css = ''; // Collect child rules separately to avoid nesting syntax errors

			if ( $styles['alignment'] === 'right' ) {
				$css .= "margin-left: auto !important; margin-right: 0 !important;";
				$css .= "margin-bottom: 20px !important;";
				$css .= "flex-direction: row-reverse !important;"; // Avatar on right

				// REVERSE HEADER LAYOUT FOR RIGHT ALIGNED
				// We must loop through wrapper selectors to append descendants correctly
				$header_flex_selectors = [];
				$user_info_selectors = [];
				$time_selectors = [];

				foreach ($wrapper_selectors as $sel) {
					$header_flex_selectors[] = "{$sel} .thread-header .user-info > div";
					$user_info_selectors[] = "{$sel} .thread-header .user-info";
					$time_selectors[] = "{$sel} .thread-header .user-info .thread-time";
				}
				$header_flex_str = implode(', ', $header_flex_selectors);
				$user_info_str = implode(', ', $user_info_selectors);
				$time_str = implode(', ', $time_selectors);

				// 1. Reverse the Name/Action container
				$child_css .= "{$header_flex_str} { flex-direction: row-reverse !important; justify-content: flex-start !important; gap: 6px !important; }";

				// 2. Reverse Header (Move Actions to Left)
				$header_selectors = [];
				foreach ($wrapper_selectors as $sel) {
					$header_selectors[] = "{$sel} .thread-header";
				}
				$header_str = implode(', ', $header_selectors);
				$child_css .= "{$header_str} { flex-direction: row-reverse !important; }";

				// 3. Align the User Info text block to the right
				$child_css .= "{$user_info_str} { text-align: right !important; width: 100% !important; }";

				// 4. Align the timestamp to the right
				$child_css .= "{$time_str} { text-align: right !important; display: block !important; }";

				// 5. Align content text to the right
				// Target container for width and alignment
				$text_selectors = [];
				foreach ($wrapper_selectors as $sel) {
					$text_selectors[] = "{$sel} .thread-text";
				}
				$text_str = implode(', ', $text_selectors);
				$child_css .= "{$text_str} { text-align: right !important; width: 100% !important; }";

				// Target children for forced alignment (without forced width)
				$text_child_selectors = [];
				foreach ($wrapper_selectors as $sel) {
					$text_child_selectors[] = "{$sel} .thread-text *";
				}
				$text_child_str = implode(', ', $text_child_selectors);
				$child_css .= "{$text_child_str} { text-align: right !important; }";

				// 6. Align Body Content Right (Attachments, Headers, etc)
				// .thread-body needs align-items: flex-end to push flex children to the right side
				$body_selectors_align = [];
				foreach ($wrapper_selectors as $sel) {
					$body_selectors_align[] = "{$sel} .thread-body";
				}
				$body_align_str = implode(', ', $body_selectors_align);
				$child_css .= "{$body_align_str} { align-items: flex-end !important; text-align: right !important; }";

			} elseif ( $styles['alignment'] === 'center' ) {
				$css .= "margin-left: auto !important; margin-right: auto !important;";
				$css .= "margin-bottom: 20px !important;";
				$css .= "flex-direction: row !important;";

				// Align Body Content Center
				$body_selectors_align = [];
				$header_selectors = [];
				$text_selectors = [];
				$text_child_selectors = [];
				$user_info_selectors = [];
				$header_flex_selectors = [];

				foreach ($wrapper_selectors as $sel) {
					$body_selectors_align[] = "{$sel} .thread-body";
					$header_selectors[] = "{$sel} .thread-header";
					$text_selectors[] = "{$sel} .thread-text";
					$text_child_selectors[] = "{$sel} .thread-text *";
					$user_info_selectors[] = "{$sel} .thread-header .user-info";
					$header_flex_selectors[] = "{$sel} .thread-header .user-info > div";
				}
				$body_align_str = implode(', ', $body_selectors_align);
				$header_str = implode(', ', $header_selectors);
				$text_str = implode(', ', $text_selectors);
				$text_child_str = implode(', ', $text_child_selectors);
				$user_info_str = implode(', ', $user_info_selectors);
				$header_flex_str = implode(', ', $header_flex_selectors);

				$child_css .= "{$body_align_str} { align-items: center !important; text-align: center !important; }";
				$child_css .= "{$header_str} { justify-content: center !important; }";
				$child_css .= "{$text_str} { text-align: center !important; width: 100% !important; }";
				$child_css .= "{$text_child_str} { text-align: center !important; }";
				$child_css .= "{$user_info_str} { align-items: center !important; }";
				$child_css .= "{$header_flex_str} { justify-content: center !important; }";

			} else {
				$css .= "margin-right: auto !important; margin-left: 0 !important;";
				$css .= "margin-bottom: 20px !important;";
				$css .= "flex-direction: row !important;";

				// Align Body Content Left
				$body_selectors_align = [];
				foreach ($wrapper_selectors as $sel) {
					$body_selectors_align[] = "{$sel} .thread-body";
				}
				$body_align_str = implode(', ', $body_selectors_align);
				$child_css .= "{$body_align_str} { align-items: flex-start !important; text-align: left !important; }";
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

			// Append Child CSS rules (must be outside the wrapper block)
			if ( ! empty( $child_css ) ) {
				$css .= $child_css;
			}

			// 2. Reset Inner Body Styles
			// We need to strip styles from the .thread-body because the wrapper now has them
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
			$css .= "width: auto !important;";
			$css .= "max-width: 100% !important;";
			$css .= "flex: 1 !important;"; // Take remaining space
			$css .= "}";

			// 3. Avatar Handling
			$avatar_selectors = [];
			foreach ($wrapper_selectors as $sel) {
				$avatar_selectors[] = $sel . ' .thread-avatar';
			}
			$avatar_selector_str = implode(', ', $avatar_selectors);

			if ( empty( $options['chat_bubbles_show_avatars'] ) ) {
				// Hide if option is disabled
				$css .= "{$avatar_selector_str} { display: none !important; }";
			} else {
				// Show and Style if enabled
				$css .= "{$avatar_selector_str} {";
				$css .= "display: block !important;";
				// Add margin to separate from body. Logic depends on alignment/direction.
				// Since we use flex-direction: row-reverse for Right align, 'margin-right' on avatar (which is first in DOM)
				// effectively puts space between it and the body in both visual orientations?
				// Row: [Avatar] --margin-right--> [Body]
				// Row-Reverse: [Body] <--margin-right-- [Avatar] (Visual Right)
				// Wait, in Row-Reverse, margin-right on the first item (Avatar) pushes it away from the flex start (Right edge)? No.
				// Let's stick to standard margins.
				$css .= "margin: 0 15px !important;";
				$css .= "align-self: flex-start !important;";
				$css .= "}";
			}

			// 4. Text Color inside
			$color_selectors = [];
			$sub_elements = ['.thread-text', '.user-info h2', '.user-info h2.user-name', '.user-info span', 'a', '.thread-header h2', '.thread-header span', '.wpsc-log-diff'];

			foreach ($wrapper_selectors as $part) {
				foreach ($sub_elements as $el) {
					$color_selectors[] = trim($part) . ' ' . $el;
				}
			}
			$color_selector_str = implode(', ', $color_selectors);

			$css .= "{$color_selector_str} { color: {$styles['text_color']} !important; }";

			// 5. Header layout adjustment
			$header_selectors = [];
			foreach ($wrapper_selectors as $part) {
				$header_selectors[] = trim($part) . ' .thread-header';
			}
			$header_selector_str = implode(', ', $header_selectors);
			$css .= "{$header_selector_str} { margin-bottom: 10px; border-bottom: 1px solid rgba(0,0,0,0.1); padding-bottom: 5px; }";

			// 6. Image Bounding Box
			if ( ! empty( $options['chat_bubbles_image_box'] ) ) {
				$img_selectors = [];
				foreach ($wrapper_selectors as $part) {
					$img_selectors[] = trim($part) . ' img';
				}
				$img_selector_str = implode(', ', $img_selectors);
				$css .= "{$img_selector_str} { border: 1px solid rgba(0,0,0,0.2) !important; padding: 3px !important; background: rgba(255,255,255,0.5) !important; border-radius: 3px !important; }";
			}

			// 7. Log Diff Alignment (Specific fix for centered status changes)
			$diff_selectors = [];
			foreach ($wrapper_selectors as $part) {
				$diff_selectors[] = trim($part) . ' .wpsc-log-diff';
			}
			$diff_selector_str = implode(', ', $diff_selectors);

			if ( $styles['alignment'] === 'center' ) {
				$css .= "{$diff_selector_str} { justify-content: center !important; }";
			} elseif ( $styles['alignment'] === 'right' ) {
				$css .= "{$diff_selector_str} { justify-content: flex-end !important; }";
			} else {
				$css .= "{$diff_selector_str} { justify-content: flex-start !important; }";
			}
		}

		// 8. Specific Override for Ticket Details Card (Force 95% Width)
		// We override the width set in step 1, but keep other styles.
		$css .= ".stackboost-chat-bubble { width: 95% !important; max-width: 95% !important; }";

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
		$styles['padding'] = isset($styles['padding']) ? absint($styles['padding']) : 15;
		$styles['font_size'] = absint($styles['font_size']);
		$styles['font_family'] = sanitize_text_field($styles['font_family']);
		$styles['border_width'] = absint($styles['border_width']);
		$styles['border_color'] = sanitize_hex_color($styles['border_color']) ?: '#cccccc';
		$styles['border_style'] = in_array($styles['border_style'], ['none', 'solid', 'dashed', 'dotted']) ? $styles['border_style'] : 'none';

		return $styles;
	}
}