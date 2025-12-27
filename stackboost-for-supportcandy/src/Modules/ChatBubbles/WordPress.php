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
		// Note: Settings registration is handled centrally, but we might need admin scripts
		if ( is_admin() ) {
			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
		}

		// Core Logic Initialization
		// We only run core logic if license is valid?
		// Module class usually handles is_active check before init_hooks is called in Plugin.php?
		// No, Plugin.php checks is_feature_active before calling init_hooks.
		// So we are safe to init core.

		Core::get_instance();
	}

	/**
	 * Enqueue Admin Scripts for Settings Page.
	 */
	public function enqueue_admin_scripts( $hook_suffix ) {
		// Hook suffix for our page: stackboost-chat-bubbles
		// Or generic stackboost page check
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
		}
	}

	/**
	 * Render the settings page.
	 */
	public function render_page() {
		// Delegate to Admin Settings class
		Admin\Settings::render_page();
	}
}
