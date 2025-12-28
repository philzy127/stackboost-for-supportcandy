<?php

namespace StackBoost\ForSupportCandy\Modules\ChatBubbles;

use StackBoost\ForSupportCandy\Core\Module;

/**
 * Chat Bubbles WordPress Adapter.
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
		return 'chat_bubbles';
	}

	/**
	 * Initialize hooks.
	 */
	public function init_hooks() {
		// Initialize the Core logic if module is loaded
		if ( is_admin() ) {
			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
		}

		Core::get_instance();
	}

	/**
	 * Enqueue Admin Scripts for Settings Page.
	 */
	public function enqueue_admin_scripts( $hook_suffix ) {
		// Hook suffix for our page: stackboost-chat-bubbles
		if ( strpos( $hook_suffix, 'stackboost-chat-bubbles' ) !== false ) {
			// Enqueue specific JS for the conditional fields
			wp_enqueue_script(
				'stackboost-chat-bubbles-admin',
				STACKBOOST_PLUGIN_URL . 'src/Modules/ChatBubbles/Admin/assets/js/settings.js',
				[ 'jquery', 'wp-color-picker' ],
				STACKBOOST_VERSION,
				true
			);
			wp_enqueue_style( 'wp-color-picker' );

			// Enqueue CSS for layout
			wp_enqueue_style(
				'stackboost-chat-bubbles-admin-css',
				STACKBOOST_PLUGIN_URL . 'src/Modules/ChatBubbles/Admin/assets/css/chat-bubbles.css',
				[],
				STACKBOOST_VERSION
			);

			// Localize Data for JS Preview

			// 1. SupportCandy Colors
			// Check 'wpsc-ap-individual-ticket' option for reply-primary-color (Reply Header BG)
			// Wait, SC settings key is tricky.
			// In `Core.php`, I used `wpsc-ap-individual-ticket` and `reply-primary-color`.
			// Let's stick to that.
			$sc_settings = get_option( 'wpsc-ap-individual-ticket', [] );
			$sc_primary  = $sc_settings['reply-primary-color'] ?? '#2271b1';

			// 2. StackBoost Theme Colors (from Appearance module if active)
			$sb_theme = [
				'primary'    => '#2271b1',
				'background' => '#f0f0f1',
				'text'       => '#3c434a'
			];

			if ( class_exists( 'StackBoost\ForSupportCandy\Modules\Appearance\WordPress' ) ) {
				// We need to fetch the actual CSS variables or calculated colors.
				// The Appearance module stores settings in `stackboost_settings` under `appearance_theme` etc.
				// But simpler: The user likely wants to see the colors that match the *current admin interface* if they are using a StackBoost theme.
				// But if they are configuring this on a light theme but the frontend uses a dark theme...
				// For now, let's look at `stackboost_settings['appearance_accent_color']`.
				$options = get_option( 'stackboost_settings', [] );
				if ( ! empty( $options['appearance_accent_color'] ) ) {
					$sb_theme['primary'] = $options['appearance_accent_color'];
				}
				// Backgrounds are harder because they are CSS vars in the theme file.
				// We can try to guess based on `appearance_theme` key (e.g. 'dark', 'midnight').
				$active_theme = $options['appearance_theme'] ?? 'default';
				if ( $active_theme === 'dark' ) {
					$sb_theme['background'] = '#2b2d2f'; // Example dark bg
					$sb_theme['text'] = '#f0f0f1';
				} elseif ( $active_theme === 'midnight' ) {
					$sb_theme['background'] = '#1e1e1e';
					$sb_theme['text'] = '#d4d4d4';
				}
			}

			wp_localize_script( 'stackboost-chat-bubbles-admin', 'stackboostChatBubbles', [
				'scPrimaryColor' => $sc_primary,
				'sbTheme'        => $sb_theme,
			] );
		}
	}

	/**
	 * Render the settings page.
	 */
	public function render_page() {
		// Delegate to Admin Settings class
		\StackBoost\ForSupportCandy\Modules\ChatBubbles\Admin\Settings::render_page();
	}
}
