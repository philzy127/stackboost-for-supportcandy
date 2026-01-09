<?php

namespace StackBoost\ForSupportCandy\Modules\ChatBubbles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
		// Log only on relevant pages to reduce noise
		if ( function_exists( 'stackboost_log' ) ) {
			$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
			$allowed_pages = [ 'stackboost-chat-bubbles', 'wpsc-tickets', 'wpsc-view-ticket' ];
			if ( in_array( $page, $allowed_pages ) ) {
				stackboost_log( 'ChatBubbles WP Adapter Init Hooks. (Page: ' . $page . ')', 'chat_bubbles' );
			}
		}

		// Initialize the Core logic if module is loaded
		if ( is_admin() ) {
			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
		} else {
			// Frontend Enqueue
			add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_scripts' ] );
		}

		Core::get_instance();
	}

	/**
	 * Enqueue Frontend Scripts.
	 */
	public function enqueue_frontend_scripts() {
		Core::get_instance()->enqueue_ticket_styles( 'frontend' );
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
			$sc_settings = get_option( 'wpsc-ap-individual-ticket', [] );
			$sc_primary  = $sc_settings['reply-primary-color'] ?? '#2271b1';
			$sc_note     = $sc_settings['note-primary-color'] ?? '#fffbcc';
			// Third Color: Reply & Close Button Color for Customer
			$sc_reply_close_bg = $sc_settings['reply-close-bg-color'] ?? '#e5e5e5';
			$sc_reply_close_text = $sc_settings['reply-close-text-color'] ?? '#333333';

			// 2. StackBoost Theme Colors (Source of Truth from Core)
			$options = get_option( 'stackboost_settings', [] );
			$active_theme = $options['admin_theme'] ?? 'sb-theme-clean-tech';
			$theme_colors = Core::get_instance()->get_stackboost_theme_colors( $active_theme );

			$sb_theme = [
				'primary'    => $theme_colors['accent'],
				'background' => $theme_colors['bg_main'],
				'text'       => '#3c434a', // Matching Core hardcoded fallback for customer text
			];

			wp_localize_script( 'stackboost-chat-bubbles-admin', 'stackboostChatBubbles', [
				'scPrimaryColor'   => $sc_primary,
				'scNoteColor'      => $sc_note,
				'scReplyCloseBg'   => $sc_reply_close_bg,
				'scReplyCloseText' => $sc_reply_close_text,
				'sbTheme'          => $sb_theme,
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
