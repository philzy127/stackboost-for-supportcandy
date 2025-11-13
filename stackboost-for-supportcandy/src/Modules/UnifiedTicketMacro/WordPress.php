<?php

namespace StackBoost\ForSupportCandy\Modules\UnifiedTicketMacro;

use StackBoost\ForSupportCandy\Core\Module;
use StackBoost\ForSupportCandy\Modules\UnifiedTicketMacro\Admin\Admin;
use WPSC_Ticket;
use WPSC_Thread;

/**
 * WordPress Adapter for the Unified Ticket Macro feature.
 *
 * This class handles all WordPress-specific implementations, such as
 * registering hooks, settings, the admin page, and script enqueueing.
 *
 * @package StackBoost\ForSupportCandy\Modules\UnifiedTicketMacro
 */
class WordPress extends Module {

	private static ?WordPress $instance = null;
	private Core $core;
	private Admin $admin;
	private ?WPSC_Ticket $deferred_ticket_to_save = null;

	public static function get_instance(): WordPress {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		$this->core  = new Core();
		$this->admin = new Admin();
		parent::__construct();
	}

	public function get_slug(): string {
		return 'unified_ticket_macro';
	}

	public function init_hooks() {
		error_log('[SB UTM] >>>>>>>>>> UnifiedTicketMacro WordPress::init_hooks() - Module loading.');
		// Admin page setup is always active
		$this->admin->init_hooks();
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );

		// Register the macro tag itself so it appears in the list
		add_filter( 'wpsc_macros', [ $this, 'register_macro_tag' ] );

		// Only activate the core functionality if the feature is enabled
		$options = get_option( 'stackboost_settings', [] );
		if ( empty( $options['utm_enabled'] ) ) {
			error_log('[SB UTM] >>>>>>>>>> init_hooks() - Feature is DISABLED. Aborting hook registration.');
			return;
		}
		error_log('[SB UTM] >>>>>>>>>> init_hooks() - Feature is ENABLED. Registering core hooks.');

		// Caching hooks
		add_action( 'wpsc_create_new_ticket', [ $this, 'prime_cache_on_creation' ], 5, 1 );
		add_action( 'wpsc_after_reply_ticket', [ $this, 'update_utm_cache' ], 10, 1 );
		add_action( 'wpsc_after_change_ticket_status', [ $this, 'update_utm_cache' ], 10, 1 );
		add_action( 'wpsc_after_change_ticket_priority', [ $this, 'update_utm_cache' ], 10, 1 );
		add_action( 'wpsc_after_assign_agent', [ $this, 'update_utm_cache' ], 10, 1 );

		// Macro replacement hooks
		add_filter( 'wpsc_create_ticket_email_data', [ $this, 'replace_utm_macro' ], 10, 2 );
		add_filter( 'wpsc_agent_reply_email_data', [ $this, 'replace_utm_macro' ], 10, 2 );
		add_filter( 'wpsc_customer_reply_email_data', [ $this, 'replace_utm_macro' ], 10, 2 );
		add_filter( 'wpsc_close_ticket_email_data', [ $this, 'replace_utm_macro' ], 10, 2 );
		add_filter( 'wpsc_assign_agent_email_data', [ $this, 'replace_utm_macro' ], 10, 2 );
	}

	/**
	 * Add the admin menu page for UTM settings.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'stackboost-for-supportcandy',
			__( 'Unified Ticket Macro', 'stackboost-for-supportcandy' ),
			__( 'Unified Ticket Macro', 'stackboost-for-supportcandy' ),
			'manage_options',
			Admin::PAGE_SLUG,
			[ $this->admin, 'render_settings_page' ]
		);
	}

	/**
	 * Enqueue admin scripts for the settings page.
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( 'stackboost_page_' . Admin::PAGE_SLUG !== $hook ) {
			return;
		}
		wp_enqueue_style(
			'stackboost-admin-utm',
			\STACKBOOST_PLUGIN_URL . 'assets/admin/css/stackboost-admin-utm.css',
			[],
			\STACKBOOST_VERSION
		);
		wp_enqueue_script(
			'stackboost-admin-utm',
			\STACKBOOST_PLUGIN_URL . 'assets/js/stackboost-admin-utm.js',
			[ 'jquery' ],
			\STACKBOOST_VERSION,
			true
		);
	}

	/**
	 * Prime the cache on new ticket creation using a transient.
	 */
	public function prime_cache_on_creation( WPSC_Ticket $ticket ) {
		if ( ! $ticket->id ) {
			return;
		}
		$html_to_cache = $this->build_html_for_ticket( $ticket );
		set_transient( 'stackboost_utm_temp_cache_' . $ticket->id, $html_to_cache, 60 );
		add_action( 'shutdown', [ $this, 'deferred_save' ] );
		$this->deferred_ticket_to_save = $ticket;
	}

	/**
	 * Defer the permanent save to the end of the request to avoid recursion.
	 */
	public function deferred_save() {
		error_log('[SB UTM] deferred_save() - Enter');
		if ( isset( $this->deferred_ticket_to_save ) && $this->deferred_ticket_to_save instanceof WPSC_Ticket ) {
			$ticket = $this->deferred_ticket_to_save;
			$html_to_cache = get_transient( 'stackboost_utm_temp_cache_' . $ticket->id );

			if ( false !== $html_to_cache ) {
				$misc_data = $ticket->misc;
				$misc_data['stackboost_utm_html'] = $html_to_cache;
				$ticket->misc = $misc_data;
				$ticket->save();
				error_log('[SB UTM] deferred_save() - Permanent cache saved for ticket ' . $ticket->id);
				delete_transient( 'stackboost_utm_temp_cache_' . $ticket->id );
			}
			unset( $this->deferred_ticket_to_save );
		}
		error_log('[SB UTM] deferred_save() - Exit');
	}

	/**
	 * Update the permanent cache when a ticket is updated.
	 */
	public function update_utm_cache( $ticket_or_thread ) {
		error_log('[SB UTM] update_utm_cache() - Enter');
		$ticket = null;
		if ( $ticket_or_thread instanceof WPSC_Ticket ) {
			$ticket = $ticket_or_thread;
		} elseif ( $ticket_or_thread instanceof WPSC_Thread ) {
			$ticket = $ticket_or_thread->ticket;
		}

		if ( $ticket instanceof WPSC_Ticket && $ticket->id ) {
			$html_to_cache = $this->build_html_for_ticket( $ticket );
			$misc_data = $ticket->misc;
			$misc_data['stackboost_utm_html'] = $html_to_cache;
			$ticket->misc = $misc_data;
			$ticket->save();
			error_log('[SB UTM] update_utm_cache() - Cache updated for ticket ' . $ticket->id);
		}
		error_log('[SB UTM] update_utm_cache() - Exit');
	}

	/**
	 * Replace the macro tag in outgoing emails.
	 */
	public function replace_utm_macro( array $data, WPSC_Thread $thread ): array {
		error_log('[SB UTM] replace_utm_macro() - Enter for ticket ' . $thread->ticket->id);

		if ( ! isset( $data['body'] ) || strpos( $data['body'], '{{stackboost_unified_ticket}}' ) === false ) {
			return $data;
		}
		$ticket = $thread->ticket;
		if ( ! ( $ticket instanceof WPSC_Ticket ) ) {
			return $data;
		}

		// Prioritize the transient for the initial "new ticket" email.
		$cached_html = get_transient( 'stackboost_utm_temp_cache_' . $ticket->id );
		if ( false !== $cached_html ) {
			error_log('[SB UTM] replace_utm_macro() - Using transient cache.');
		} else {
			// For all other cases, fall back to the permanently stored HTML.
			$misc_data   = $ticket->misc;
			$cached_html = $misc_data['stackboost_utm_html'] ?? '';
			error_log('[SB UTM] replace_utm_macro() - Using permanent cache.');
		}

		$data['body'] = str_replace( '{{stackboost_unified_ticket}}', $cached_html, $data['body'] );
		error_log('[SB UTM] replace_utm_macro() - Exit');
		return $data;
	}

	/**
	 * Register the macro tag with SupportCandy.
	 */
	public function register_macro_tag( array $macros ): array {
		$macros[] = [
			'tag'   => '{{stackboost_unified_ticket}}',
			'title' => esc_attr__( 'Unified Ticket Macro', 'stackboost-for-supportcandy' ),
		];
		return $macros;
	}

	/**
	 * Helper function to build the HTML for a given ticket.
	 */
	private function build_html_for_ticket( WPSC_Ticket $ticket ): string {
		$options = get_option( 'stackboost_settings', [] );
		$plugin  = \StackBoost\ForSupportCandy\WordPress\Plugin::get_instance();

		$selected_fields = $options['utm_selected_fields'] ?? [];

		if ( ! empty( $options['utm_use_sc_order'] ) ) {
			$supportcandy_tff_fields = get_option( 'wpsc-tff', [] );
			$sc_ordered_slugs        = array_keys( $supportcandy_tff_fields );
			$ordered_part            = array_intersect( $sc_ordered_slugs, $selected_fields );
			$unmatched_part          = array_diff( $selected_fields, $sc_ordered_slugs );
			$selected_fields         = array_merge( $ordered_part, $unmatched_part );
		}

		$config = [
			'selected_fields' => $selected_fields,
			'rename_rules'    => $options['utm_rename_rules'] ?? [],
			'all_columns'     => $plugin->get_supportcandy_columns(),
		];

		return $this->core->build_utm_html( $ticket, $config );
	}
}
