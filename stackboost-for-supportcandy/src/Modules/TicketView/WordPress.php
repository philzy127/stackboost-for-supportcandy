<?php


namespace StackBoost\ForSupportCandy\Modules\TicketView;

if ( ! defined( 'ABSPATH' ) ) exit;

use StackBoost\ForSupportCandy\Core\Module;

/**
 * WordPress Adapter for the Ticket View module.
 *
 * @package StackBoost\ForSupportCandy\Modules\TicketView
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
		return 'ticket_view';
	}

	/**
	 * Initialize hooks.
	 */
	public function init_hooks() {
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

		// AJAX handler for fetching ticket details card content.
		add_action( 'wp_ajax_stackboost_get_ticket_details_card', [ $this, 'ajax_get_ticket_details_card' ] );
		add_action( 'wp_ajax_nopriv_stackboost_get_ticket_details_card', [ $this, 'ajax_get_ticket_details_card' ] );
	}

	/**
	 * AJAX handler to get the content for the ticket details card.
	 */
	public function ajax_get_ticket_details_card() {
		// Use the same nonce action as the frontend script, which is 'wpsc_get_individual_ticket'.
		// We use wp_verify_nonce instead of check_ajax_referer to handle failures gracefully with JSON.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wpsc_get_individual_ticket' ) ) {
			wp_send_json_error( [ 'message' => 'Security check failed (Nonce mismatch)' ] );
		}

		$ticket_id = isset( $_POST['ticket_id'] ) ? intval( $_POST['ticket_id'] ) : 0;
		if ( ! $ticket_id ) {
			wp_send_json_error( [ 'message' => 'Invalid Ticket ID' ] );
		}

		$ticket = new \WPSC_Ticket( $ticket_id );
		if ( ! $ticket->id ) {
			wp_send_json_error( [ 'message' => 'Ticket not found' ] );
		}

		// Security check: Ensure user can access this ticket
		// WPSC_Ticket usually handles this via `get_tickets` or capabilities,
		// but since we loaded direct by ID, we must check permissions.
		// Standard WPSC logic for visibility:
		$current_user = \WPSC_Current_User::$current_user;
		if ( ! $current_user->is_agent && $ticket->customer->id !== $current_user->customer->id ) {
			// If not agent and not the owner
			wp_send_json_error( [ 'message' => 'Permission denied' ] );
		}

		$options = get_option( 'stackboost_settings', [] );

		// Get active theme class for wrapper
		$theme_class = 'sb-theme-clean-tech'; // Default
		if ( class_exists( 'StackBoost\ForSupportCandy\Modules\Appearance\WordPress' ) ) {
			$theme_class = \StackBoost\ForSupportCandy\Modules\Appearance\WordPress::get_active_theme_class();
		}

		// Determine View Type
		$view_type = $options['ticket_details_view_type'] ?? 'standard';
		if ( 'utm' === $view_type && ! stackboost_is_feature_active( 'unified_ticket_macro' ) ) {
			$view_type = 'standard'; // Fallback if Pro feature is disabled
		}

		// Determine Content to Include
		$content_type = $options['ticket_details_content'] ?? 'details_only';
		$image_handling = $options['ticket_details_image_handling'] ?? 'fit';
		$limit = isset( $options['ticket_details_history_limit'] ) ? intval( $options['ticket_details_history_limit'] ) : 0;
		$chat_bubbles = ! empty( $options['ticket_details_chat_bubbles'] );

		// Enforce Pro Check for Chat Bubbles
		if ( $chat_bubbles && ! stackboost_is_feature_active( 'unified_ticket_macro' ) ) {
			$chat_bubbles = false;
		}

		$details_html = '';
		$history_html = '';

		// 1. Generate Base Details (Table/Card)
		$description_in_utm = false;
		if ( 'utm' === $view_type ) {
			if ( class_exists( 'StackBoost\ForSupportCandy\Modules\UnifiedTicketMacro\Core' ) ) {
				$utm_content = \StackBoost\ForSupportCandy\Modules\UnifiedTicketMacro\Core::get_instance()->build_live_utm_html( $ticket, 'list' );

				// Check if Description is included in UTM
				$utm_options = get_option( 'stackboost_settings', [] );
				$utm_fields  = $utm_options['utm_columns'] ?? [];
				// We need to check if 'df_description' type exists in the selected columns.
				// Since we don't have the types mapped here easily, we can check known slug 'description' or reuse logic.
				// But wait, user might rename it.
				// Let's rely on checking the field SLUG against known description fields.
				// Standard SC description field slug is 'description'.
				if ( in_array( 'description', $utm_fields, true ) ) {
					$description_in_utm = true;
				}

				// Apply Theme Wrapper
				$details_html .= '<div class="stackboost-dashboard ' . esc_attr( $theme_class ) . '" style="background:none; padding:0; box-shadow:none; border:none; margin-top:0;">';
				$details_html .= '<div class="wpsc-it-widget stackboost-utm-details" style="margin-bottom: 10px;">';
				$details_html .= '<div class="wpsc-widget-header">';
				$details_html .= '<h2>' . __( 'Ticket Details', 'stackboost-for-supportcandy' ) . '</h2>';
				$details_html .= '<span class="wpsc-itw-toggle dashicons dashicons-arrow-up-alt2" data-widget="stackboost-utm-details"></span>';
				$details_html .= '</div>';
				$details_html .= '<div class="wpsc-widget-body" style="display: block;">' . $utm_content . '</div>';
				$details_html .= '</div>';
				$details_html .= '</div>'; // End wrapper
			} else {
				$details_html .= '<p>' . __( 'UTM Module not available.', 'stackboost-for-supportcandy' ) . '</p>';
			}
		}
		// 'standard' view returns empty html for the base part, letting JS scrape standard fields.

		// 2. Append Threads (Description / History)
		if ( 'details_only' !== $content_type ) {
			// If we only want description but it's already in UTM, we skip everything.
			if ( 'with_description' === $content_type && $description_in_utm ) {
				// Do nothing, description is already shown.
			} else {
				// We removed the class_exists check for UTM here because we now use a local renderer.
				$include_private = $current_user->is_agent;

				if ( 'with_description' === $content_type ) {
					$desc_thread = $ticket->get_description_thread();
					if ( $desc_thread ) {
						// We sanitize FIRST to ensure the content is safe.
						$body = wp_kses_post( $desc_thread->body );

						// Apply image handling (Matched to UTM Core logic with Lightbox support)
						if ( 'strip' === $image_handling ) {
							$body = preg_replace( '/<img[^>]+\>/i', '', $body );
						} elseif ( 'placeholder' === $image_handling ) {
							// Replace image tags with a clickable [Image] link that opens the Lightbox
							$body = preg_replace(
								'/<img\s+[^>]*?src=["\']([^"\']+)["\'][^>]*?>/i',
								'<a href="$1" onclick="if(window.stackboostOpenWidgetModal) { stackboostOpenWidgetModal(event, this.href); return false; } else { return true; }" style="font-style:italic; color:#0073aa; cursor:pointer;">[' . __( 'Image', 'stackboost-for-supportcandy' ) . ']</a>',
								$body
							);
						} else {
							// 'fit' (default) - inject max-width style AND lightbox link
							// Wrap images in a link that opens the Lightbox
							$body = preg_replace(
								'/(<img\s+[^>]*?src=["\']([^"\']+)["\'][^>]*?>)/i',
								'<a href="$2" onclick="if(window.stackboostOpenWidgetModal) { stackboostOpenWidgetModal(event, this.href); return false; } else { return true; }" style="cursor:pointer;">$1</a>',
								$body
							);

							// Ensure max-width style is present (backup to CSS)
							$body = preg_replace( '/(<img\s+[^>]*?style=["\'])([^"\']*?)(["\'])/i', '$1$2; max-width:100%; height:auto;$3', $body );
							$body = preg_replace( '/(<img\s+)(?![^>]*?style=)([^>]*?)(\/?>)/i', '$1$2 style="max-width:100%; height:auto;"$3', $body );
						}

						// Wrap in WPSC Widget Structure for seamless look
						$history_html .= '<div class="stackboost-dashboard ' . esc_attr( $theme_class ) . '" style="background:none; padding:0; box-shadow:none; border:none; margin-top:0;">';
						$history_html .= '<div class="wpsc-it-widget stackboost-ticket-card-extension" style="margin-top: 10px;">';
						$history_html .= '<div class="wpsc-widget-header">';
						$history_html .= '<h2>' . __( 'Description', 'stackboost-for-supportcandy' ) . '</h2>';
						$history_html .= '<span class="wpsc-itw-toggle dashicons dashicons-arrow-up-alt2" data-widget="stackboost-description"></span>';
						$history_html .= '</div>';
						$history_html .= '<div class="wpsc-widget-body stackboost-thread-body" style="display: block;">';
						$history_html .= '<div>' . wp_kses_post( $body ) . '</div>';
						$history_html .= '</div></div>';
						$history_html .= '</div>'; // End wrapper

					}
				} elseif ( 'with_history' === $content_type ) {
					// Render threads utilizing local method
					// Pass $description_in_utm as $exclude_description to avoid duplicate description.
					$threads_html = $this->render_ticket_threads( $ticket, $include_private, $image_handling, $limit, $description_in_utm, $chat_bubbles );

					if ( ! empty( $threads_html ) ) {
						$history_html .= '<div class="stackboost-dashboard ' . esc_attr( $theme_class ) . '" style="background:none; padding:0; box-shadow:none; border:none; margin-top:0;">';
						$history_html .= '<div class="wpsc-it-widget stackboost-ticket-card-extension" style="margin-top: 10px;">';
						$history_html .= '<div class="wpsc-widget-header">';
						$history_html .= '<h2>' . __( 'Conversation History', 'stackboost-for-supportcandy' ) . '</h2>';
						$history_html .= '<span class="wpsc-itw-toggle dashicons dashicons-arrow-up-alt2" data-widget="stackboost-history"></span>';
						$history_html .= '</div>';
						$history_html .= '<div class="wpsc-widget-body" style="display: block;">';
						$history_html .= $threads_html;
						$history_html .= '</div></div>';
						$history_html .= '</div>'; // End wrapper
					}
				}
			}
		}

		wp_send_json_success( [
			'details'             => $details_html,
			'history'             => $history_html,
			'effective_view_type' => $view_type,
		] );
	}

	/**
	 * Enqueue scripts.
	 *
	 * @param string $hook_suffix
	 */
	public function enqueue_scripts( $hook_suffix = '' ) {
		stackboost_log( "TicketView::enqueue_scripts called with hook_suffix: {$hook_suffix}", 'ticket_view' );

		// Enqueue Utility Script (contains Lightbox logic)
		// We load this on the admin ticket list OR on the frontend (where hook_suffix is empty/irrelevant)
		$is_ticket_page_admin = 'supportcandy_page_wpsc-tickets' === $hook_suffix;
		$is_frontend = ! is_admin();

		if ( $is_ticket_page_admin || $is_frontend ) {
			wp_enqueue_style(
				'stackboost-util',
				STACKBOOST_PLUGIN_URL . 'assets/css/stackboost-util.css',
				[],
				STACKBOOST_VERSION
			);

			wp_enqueue_script(
				'stackboost-util',
				STACKBOOST_PLUGIN_URL . 'assets/js/stackboost-util.js',
				[ 'jquery' ],
				STACKBOOST_VERSION,
				true
			);
		}

		$options = get_option( 'stackboost_settings' );

		// Debug log options
		stackboost_log( "TicketView::enqueue_scripts options: " . json_encode( [
			'enable_page_last_loaded'      => $options['enable_page_last_loaded'] ?? 'not set',
			'enable_ticket_details_card'   => $options['enable_ticket_details_card'] ?? 'not set',
		] ), 'ticket_view' );

		// Enqueue Page Last Loaded Script
		if ( ! empty( $options['enable_page_last_loaded'] ) ) {
			if ( is_admin() && 'supportcandy_page_wpsc-tickets' !== $hook_suffix ) {
				// Skip if admin but not the ticket list
			} else {
				wp_enqueue_script(
					'stackboost-page-last-loaded',
					STACKBOOST_PLUGIN_URL . 'src/Modules/TicketView/assets/js/page-last-loaded.js',
					[ 'jquery' ],
					STACKBOOST_VERSION,
					true
				);
				$placement = $options['page_last_loaded_placement'] ?? 'header';
				$label     = $options['page_last_loaded_label'] ?? 'Page Last Loaded: ';
				$format    = $options['page_last_loaded_format'] ?? 'default';

				wp_localize_script( 'stackboost-page-last-loaded', 'stackboostPageLastLoaded', [
					'enabled'        => true,
					'placement'      => $placement,
					'label'          => $label,
					'format'         => $format,
					'wp_time_format' => get_option( 'time_format' ),
				] );
			}
		}

		// Enqueue Ticket View Features (e.g., Hide Reply Close)
		if ( $is_ticket_page_admin || $is_frontend ) {
			wp_enqueue_script(
				'stackboost-ticket-view',
				STACKBOOST_PLUGIN_URL . 'src/Modules/TicketView/assets/js/ticket-view.js',
				[ 'jquery', 'stackboost-util' ],
				STACKBOOST_VERSION,
				true
			);

			$is_agent = false;
			if ( class_exists( '\WPSC_Current_User' ) && isset( \WPSC_Current_User::$current_user ) ) {
				$is_agent = \WPSC_Current_User::$current_user->is_agent;
			}

			wp_localize_script( 'stackboost-ticket-view', 'stackboostTicketView', [
				'features' => [
					'hide_reply_close' => [
						'enabled' => ! empty( $options['hide_reply_close_for_users'] ),
					],
				],
				'is_agent' => $is_agent,
			] );
		}
	}

	/**
	 * Render the administration page.
	 */
	public function render_page() {
		if ( ! current_user_can( STACKBOOST_CAP_MANAGE_TICKET_VIEW ) ) {
			return;
		}

		// Get active theme class
		$theme_class = 'sb-theme-clean-tech'; // Default
		if ( class_exists( 'StackBoost\ForSupportCandy\Modules\Appearance\WordPress' ) ) {
			$theme_class = \StackBoost\ForSupportCandy\Modules\Appearance\WordPress::get_active_theme_class();
		}

		?>
		<!-- StackBoost Wrapper Start -->
		<!-- Theme: <?php echo esc_html( $theme_class ); ?> -->
		<div class="wrap stackboost-dashboard <?php echo esc_attr( $theme_class ); ?>">
			<h1><?php esc_html_e( 'Ticket View', 'stackboost-for-supportcandy' ); ?></h1>
			<?php settings_errors(); ?>

			<form action="options.php" method="post">
				<?php
				settings_fields( 'stackboost_settings' );
				// stackboost_settings[page_slug] added below manually
				?>
				<input type="hidden" name="stackboost_settings[page_slug]" value="stackboost-ticket-view">

				<div class="stackboost-dashboard-grid">

					<!-- Card 1: Ticket Details Card -->
					<div class="stackboost-card">
						<h2><?php esc_html_e( 'Ticket Details Card', 'stackboost-for-supportcandy' ); ?></h2>
						<table class="form-table">
							<?php do_settings_fields( 'stackboost-ticket-view', 'stackboost_ticket_details_card_section' ); ?>
						</table>
					</div>

					<!-- Card 2: Organization (General Cleanup) -->
					<div class="stackboost-card">
						<h2><?php esc_html_e( 'Organization', 'stackboost-for-supportcandy' ); ?></h2>
						<table class="form-table">
							<?php do_settings_fields( 'stackboost-ticket-view', 'stackboost_general_cleanup_section' ); ?>
						</table>
					</div>

					<!-- Card 3: Page Last Loaded -->
					<div class="stackboost-card">
						<h2><?php esc_html_e( 'Page Last Loaded Indicator', 'stackboost-for-supportcandy' ); ?></h2>
						<table class="form-table">
							<?php do_settings_fields( 'stackboost-ticket-view', 'stackboost_page_last_loaded_section' ); ?>
						</table>
					</div>

				</div>

				<?php submit_button( __( 'Save Settings', 'stackboost-for-supportcandy' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Register settings fields for this module.
	 */
	public function register_settings() {
		$page_slug = 'stackboost-ticket-view';

		// Section: Ticket Details Card
		add_settings_section( 'stackboost_ticket_details_card_section', __( 'Ticket Details Card', 'stackboost-for-supportcandy' ), null, $page_slug );

		add_settings_field( 'stackboost_enable_ticket_details_card', __( 'Enable Feature', 'stackboost-for-supportcandy' ), [ $this, 'render_checkbox_field' ], $page_slug, 'stackboost_ticket_details_card_section', [ 'id' => 'enable_ticket_details_card', 'desc' => 'Shows a card with ticket details on right-click.' ] );

		// New Options for Ticket Details Card
		add_settings_field(
			'stackboost_ticket_details_view_type',
			__( 'Card View Type', 'stackboost-for-supportcandy' ),
			[ $this, 'render_view_type_select' ], // Custom renderer for Pro badge
			$page_slug,
			'stackboost_ticket_details_card_section',
			[ 'id' => 'ticket_details_view_type', 'desc' => 'Choose how the ticket details are displayed.' ]
		);

		add_settings_field(
			'stackboost_ticket_details_content',
			__( 'Include Content', 'stackboost-for-supportcandy' ),
			[ $this, 'render_select_field' ],
			$page_slug,
			'stackboost_ticket_details_card_section',
			[
				'id' => 'ticket_details_content',
				'choices' => [
					'details_only'     => __( 'None', 'stackboost-for-supportcandy' ),
					'with_description' => __( 'Initial Description Only', 'stackboost-for-supportcandy' ),
					'with_history'     => __( 'Full Conversation History', 'stackboost-for-supportcandy' ),
				],
				'desc' => 'Select what additional content to include below the details.'
			]
		);

		add_settings_field(
			'stackboost_ticket_details_history_limit',
			__( 'Conversation History Limit', 'stackboost-for-supportcandy' ),
			[ $this, 'render_number_field' ],
			$page_slug,
			'stackboost_ticket_details_card_section',
			[
				'id' => 'ticket_details_history_limit',
				'default' => '0',
				'desc' => 'Maximum number of items to show (0 = Unlimited).'
			]
		);

		add_settings_field(
			'stackboost_ticket_details_image_handling',
			__( 'Image Handling in Notes', 'stackboost-for-supportcandy' ),
			[ $this, 'render_select_field' ],
			$page_slug,
			'stackboost_ticket_details_card_section',
			[
				'id' => 'ticket_details_image_handling',
				'choices' => [
					'fit'         => __( 'Fit to Container', 'stackboost-for-supportcandy' ),
					'strip'       => __( 'Remove Images', 'stackboost-for-supportcandy' ),
					'placeholder' => __( '[Image] Placeholder', 'stackboost-for-supportcandy' ),
				],
				'desc' => 'How to handle images within the description and notes.'
			]
		);

		add_settings_field(
			'stackboost_ticket_details_chat_bubbles',
			__( 'Enable Chat Bubbles', 'stackboost-for-supportcandy' ) . ' <span class="stackboost-badge-pro">PRO</span>',
			[ $this, 'render_pro_checkbox_field' ],
			$page_slug,
			'stackboost_ticket_details_card_section',
			[
				'id'      => 'ticket_details_chat_bubbles',
				'desc'    => 'Display conversation history as chat bubbles (Requires Pro Plan).',
				'feature' => 'unified_ticket_macro',
			]
		);

		// Section: General Cleanup
		add_settings_section( 'stackboost_general_cleanup_section', __( 'General Cleanup', 'stackboost-for-supportcandy' ), null, $page_slug );
		add_settings_field( 'stackboost_enable_hide_empty_columns', __( 'Hide Empty Columns', 'stackboost-for-supportcandy' ), [ $this, 'render_checkbox_field' ], $page_slug, 'stackboost_general_cleanup_section', [ 'id' => 'enable_hide_empty_columns', 'desc' => 'Automatically hide any column in the ticket list that is completely empty.' ] );
		add_settings_field( 'stackboost_enable_hide_priority_column', __( 'Hide Priority Column', 'stackboost-for-supportcandy' ), [ $this, 'render_checkbox_field' ], $page_slug, 'stackboost_general_cleanup_section', [ 'id' => 'enable_hide_priority_column', 'desc' => 'Hides the "Priority" column if all visible tickets have a priority of "Low".' ] );
		add_settings_field( 'stackboost_hide_reply_close_for_users', __( 'Hide "Reply & Close" for Users', 'stackboost-for-supportcandy' ), [ $this, 'render_checkbox_field' ], $page_slug, 'stackboost_general_cleanup_section', [ 'id' => 'hide_reply_close_for_users', 'desc' => 'Hides the "Reply & Close" button for non-agent users on the ticket reply form.' ] );

		// Section: Page Last Loaded Indicator
		add_settings_section( 'stackboost_page_last_loaded_section', __( 'Page Last Loaded Indicator', 'stackboost-for-supportcandy' ), null, $page_slug );
		add_settings_field( 'stackboost_enable_page_last_loaded', __( 'Enable Feature', 'stackboost-for-supportcandy' ), [ $this, 'render_checkbox_field' ], $page_slug, 'stackboost_page_last_loaded_section', [ 'id' => 'enable_page_last_loaded', 'desc' => 'Shows the time when the ticket list was last refreshed.' ] );
		add_settings_field(
			'stackboost_page_last_loaded_placement',
			__( 'Placement', 'stackboost-for-supportcandy' ),
			[ $this, 'render_select_field' ],
			$page_slug,
			'stackboost_page_last_loaded_section',
			[
				'id'      => 'page_last_loaded_placement',
				'choices' => [
					'header' => 'Header',
					'footer' => 'Footer',
					'both'   => 'Both',
				],
				'desc'    => 'Where to display the indicator.',
			]
		);
		add_settings_field(
			'stackboost_page_last_loaded_label',
			__( 'Label', 'stackboost-for-supportcandy' ),
			[ $this, 'render_text_field' ],
			$page_slug,
			'stackboost_page_last_loaded_section',
			[
				'id'      => 'page_last_loaded_label',
				'default' => 'Page Last Loaded: ',
				'desc'    => 'The text to display before the time.',
			]
		);
		add_settings_field(
			'stackboost_page_last_loaded_format',
			__( 'Time Format', 'stackboost-for-supportcandy' ),
			[ $this, 'render_select_field' ],
			$page_slug,
			'stackboost_page_last_loaded_section',
			[
				'id'      => 'page_last_loaded_format',
				'choices' => [
					'default' => 'WordPress Default',
					'12'      => '12-hour (e.g., 2:30 PM)',
					'24'      => '24-hour (e.g., 14:30)',
				],
				'desc'    => 'The format of the time display.',
			]
		);
	}

	/**
	 * Renders a checkbox field for a settings page.
	 */
	public function render_checkbox_field( array $args ) {
		$options = get_option( 'stackboost_settings' );
		$id = $args['id'];
		$checked = isset( $options[ $id ] ) && $options[ $id ] ? 'checked' : '';
		echo '<input type="checkbox" id="' . esc_attr( $id ) . '" name="stackboost_settings[' . esc_attr( $id ) . ']" value="1" ' . esc_attr( $checked ) . '>';
		if ( ! empty( $args['desc'] ) ) {
			echo '<p class="description">' . esc_html( $args['desc'] ) . '</p>';
		}
	}

	/**
	 * Renders a checkbox field that is disabled if a Pro feature is not active.
	 */
	public function render_pro_checkbox_field( array $args ) {
		$options = get_option( 'stackboost_settings' );
		$id      = $args['id'];
		$feature = $args['feature'] ?? 'unified_ticket_macro';

		$is_active = stackboost_is_feature_active( $feature );
		// If active, check if option is set. If not active, force unchecked.
		$checked  = $is_active && ! empty( $options[ $id ] ) ? 'checked' : '';
		$disabled = $is_active ? '' : 'disabled';

		echo '<input type="checkbox" id="' . esc_attr( $id ) . '" name="stackboost_settings[' . esc_attr( $id ) . ']" value="1" ' . esc_attr( $checked ) . ' ' . esc_attr( $disabled ) . '>';
		if ( ! empty( $args['desc'] ) ) {
			echo '<p class="description">' . esc_html( $args['desc'] ) . '</p>';
		}
		if ( ! $is_active ) {
			echo '<p class="description" style="color: #d63638;">' . esc_html__( 'This feature is available in the Pro version.', 'stackboost-for-supportcandy' ) . '</p>';
		}
	}

	/**
	 * Renders a select field for a settings page.
	 */
	public function render_select_field( $args ) {
		$options = get_option( 'stackboost_settings' );
		$id = $args['id'];
		$selected = $options[ $id ] ?? '';
		echo '<select id="' . esc_attr( $id ) . '" name="stackboost_settings[' . esc_attr( $id ) . ']">';
		if ( ! empty( $args['placeholder'] ) ) {
			echo '<option value="">' . esc_html( $args['placeholder'] ) . '</option>';
		}
		foreach ( $args['choices'] as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $selected, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		if ( ! empty( $args['desc'] ) ) {
			echo '<p class="description">' . esc_html( $args['desc'] ) . '</p>';
		}
	}

	/**
	 * Renders the view type select field with Pro logic.
	 */
	public function render_view_type_select( $args ) {
		$options = get_option( 'stackboost_settings' );
		$id = $args['id'];
		$selected = $options[ $id ] ?? 'standard';

		$is_pro_active = stackboost_is_feature_active( 'unified_ticket_macro' );

		echo '<select id="' . esc_attr( $id ) . '" name="stackboost_settings[' . esc_attr( $id ) . ']">';

		// Standard
		echo '<option value="standard" ' . selected( $selected, 'standard', false ) . '>' . esc_html__( 'Standard', 'stackboost-for-supportcandy' ) . '</option>';

		// UTM (Pro)
		$utm_label = esc_html__( 'Unified Ticket Macro', 'stackboost-for-supportcandy' );
		$disabled_attr = '';
		if ( ! $is_pro_active ) {
			$disabled_attr = 'disabled';
		}

		echo '<option value="utm" ' . selected( $selected, 'utm', false ) . ' ' . esc_attr( $disabled_attr ) . '>' . esc_html( $utm_label ) . '</option>';

		echo '</select>';

		if ( ! $is_pro_active ) {
			echo ' <span class="stackboost-badge-pro" title="' . esc_attr__( 'Upgrade to Pro or Business to enable this feature.', 'stackboost-for-supportcandy' ) . '">PRO</span>';
		}

		// Inline script to warn about UTM configuration AND toggle limit field
		?>
		<script>
		jQuery(document).ready(function($) {
			// UTM Alert Logic
			var utmEnabled = <?php echo stackboost_is_feature_active( 'unified_ticket_macro' ) ? 'true' : 'false'; ?>;
			$('#ticket_details_view_type').on('change', function() {
				if ($(this).val() === 'utm') {
					if (!utmEnabled) {
						alert('<?php echo esc_js( __( 'The Unified Ticket Macro feature is not active on your plan.', 'stackboost-for-supportcandy' ) ); ?>');
					} else {
						alert('<?php echo esc_js( __( 'Reminder: Please ensure the Unified Ticket Macro module is enabled and configured in its settings page for this view to function correctly.', 'stackboost-for-supportcandy' ) ); ?>');
					}
				}
			});

			// Conditional Logic for Content Options
			var $limitRow = $('#ticket_details_history_limit').closest('tr');
			var $imageHandlingRow = $('#ticket_details_image_handling').closest('tr');
			var $contentSelect = $('#ticket_details_content');

			function toggleFields() {
				var val = $contentSelect.val();
				if (val === 'details_only') {
					// Hide both
					$limitRow.hide();
					$imageHandlingRow.hide();
				} else if (val === 'with_description') {
					// Show Image Handling, Hide Limit
					$limitRow.hide();
					$imageHandlingRow.show();
				} else if (val === 'with_history') {
					// Show Both
					$limitRow.show();
					$imageHandlingRow.show();
				}
			}

			// Initial State
			toggleFields();

			// Change Listener
			$contentSelect.on('change', toggleFields);
		});
		</script>
		<?php

		if ( ! empty( $args['desc'] ) ) {
			echo '<p class="description">' . esc_html( $args['desc'] ) . '</p>';
		}
	}

	/**
	 * Renders a textarea field for a settings page.
	 */
	public function render_textarea_field( array $args ) {
		$options = get_option( 'stackboost_settings' );
		$id = $args['id'];
		$value = $options[ $id ] ?? '';
		echo '<textarea id="' . esc_attr( $id ) . '" name="stackboost_settings[' . esc_attr( $id ) . ']" class="' . esc_attr( $args['class'] ) . '">' . esc_textarea( $value ) . '</textarea>';
		if ( ! empty( $args['desc'] ) ) {
			echo '<p class="description">' . esc_html( $args['desc'] ) . '</p>';
		}
	}

	/**
	 * Renders a text field for a settings page.
	 */
	public function render_text_field( array $args ) {
		$options = get_option( 'stackboost_settings' );
		$id = $args['id'];
		$value = isset( $options[ $id ] ) ? $options[ $id ] : ( $args['default'] ?? '' );
		echo '<input type="text" id="' . esc_attr( $id ) . '" name="stackboost_settings[' . esc_attr( $id ) . ']" value="' . esc_attr( $value ) . '" class="regular-text">';
		if ( ! empty( $args['desc'] ) ) {
			echo '<p class="description">' . esc_html( $args['desc'] ) . '</p>';
		}
	}

	/**
	 * Renders a horizontal rule separator for settings pages.
	 */
	public function render_hr_separator() {
		echo '<hr>';
	}

	/**
	 * Renders a number field for a settings page.
	 */
	public function render_number_field( array $args ) {
		$options = get_option( 'stackboost_settings' );
		$id      = $args['id'];
		$value   = isset( $options[ $id ] ) ? $options[ $id ] : ( $args['default'] ?? '0' );
		echo '<input type="number" id="' . esc_attr( $id ) . '" name="stackboost_settings[' . esc_attr( $id ) . ']" value="' . esc_attr( $value ) . '" class="regular-text" style="width: 80px;">';
		if ( ! empty( $args['desc'] ) ) {
			echo '<p class="description">' . esc_html( $args['desc'] ) . '</p>';
		}
	}

	/**
	 * Renders the conversation threads for a ticket.
	 *
	 * Ported from UnifiedTicketMacro\Core to allow availability in Free/Lite version.
	 *
	 * @param \WPSC_Ticket $ticket              The ticket object.
	 * @param bool         $include_private     Whether to include private notes (for agents).
	 * @param string       $image_handling      How to handle images ('fit', 'strip', 'placeholder').
	 * @param int          $limit               Maximum number of threads to return (0 for unlimited).
	 * @param bool         $exclude_description Whether to exclude the initial report thread.
	 * @param bool         $chat_bubbles        Whether to render as chat bubbles (Pro).
	 * @return string HTML of the threads.
	 */
	public function render_ticket_threads( \WPSC_Ticket $ticket, bool $include_private = false, string $image_handling = 'fit', int $limit = 0, bool $exclude_description = false, bool $chat_bubbles = false ): string {
		// Define which thread types to fetch
		// Public always gets 'report' and 'reply'.
		$types = [ 'report', 'reply' ];
		if ( $include_private ) {
			$types[] = 'note';
		}

		// Fetch threads using SupportCandy's method:
		// get_threads( $page_no = 1, $items_per_page = 0, $types = array(), $orderby = 'date_created', $order = 'DESC' )
		// If limit is 0, we want all threads.
		$threads = $ticket->get_threads( 1, $limit, $types, 'date_created', 'ASC' );

		if ( empty( $threads ) ) {
			return '';
		}

		$wrapper_classes = 'stackboost-ticket-history';
		if ( $exclude_description ) {
			$wrapper_classes .= ' stackboost-no-border';
		}

		$html = '<div class="' . esc_attr( $wrapper_classes ) . '">';

		foreach ( $threads as $thread ) {
			// Skip description if requested (typically 'report' type)
			if ( $exclude_description && 'report' === $thread->type ) {
				continue;
			}

			// Body content processing
			// We sanitize FIRST to ensure the content is safe.
			$body = wp_kses_post( $thread->body );

			// Handle Images
			// We apply our trusted regex replacements AFTER sanitization so our onclick attributes are not stripped.
			if ( 'strip' === $image_handling ) {
				$body = preg_replace( '/<img[^>]+\>/i', '', $body );
			} elseif ( 'placeholder' === $image_handling ) {
				// Replace image tags with a clickable [Image] link that opens the Lightbox
				$body = preg_replace(
					'/<img\s+[^>]*?src=["\']([^"\']+)["\'][^>]*?>/i',
					'<a href="$1" onclick="if(window.stackboostOpenWidgetModal) { stackboostOpenWidgetModal(event, this.href); return false; } else { return true; }" style="font-style:italic; color:#0073aa; cursor:pointer;">[' . __( 'Image', 'stackboost-for-supportcandy' ) . ']</a>',
					$body
				);
			} else {
				// 'fit' (default) - inject max-width style AND lightbox link
				// Wrap images in a link that opens the Lightbox
				$body = preg_replace(
					'/(<img\s+[^>]*?src=["\']([^"\']+)["\'][^>]*?>)/i',
					'<a href="$2" onclick="if(window.stackboostOpenWidgetModal) { stackboostOpenWidgetModal(event, this.href); return false; } else { return true; }" style="cursor:pointer;">$1</a>',
					$body
				);

				// Ensure max-width style is present (backup to CSS)
				$body = preg_replace( '/(<img\s+[^>]*?style=["\'])([^"\']*?)(["\'])/i', '$1$2; max-width:100%; height:auto;$3', $body );
				$body = preg_replace( '/(<img\s+)(?![^>]*?style=)([^>]*?)(\/?>)/i', '$1$2 style="max-width:100%; height:auto;"$3', $body );
			}

			// Common Data
			$author_name = $thread->customer ? $thread->customer->name : __( 'Unknown', 'stackboost-for-supportcandy' );
			$date_str = $thread->date_created->setTimezone( wp_timezone() )->format( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );

			// Render
			if ( $chat_bubbles ) {
				// Alignment logic
				$align_class = 'stackboost-chat-left'; // Default to Left (Customer)
				$is_note = 'note' === $thread->type;

				if ( $is_note ) {
					$align_class = 'stackboost-chat-right stackboost-chat-note';
				} elseif ( isset( $thread->customer ) && property_exists( $thread->customer, 'is_agent' ) && $thread->customer->is_agent ) {
					$align_class = 'stackboost-chat-right';
				} elseif ( isset( $thread->customer ) && isset( $thread->customer->user ) && in_array( 'administrator', $thread->customer->user->roles ?? [] ) ) {
					// Fallback for admins
					$align_class = 'stackboost-chat-right';
				}

				$html .= '<div class="stackboost-chat-row ' . esc_attr( $align_class ) . '">';
				$html .= '<div class="stackboost-chat-bubble">';
				$html .= '<div class="stackboost-chat-meta"><strong>' . esc_html( $author_name ) . '</strong> &bull; ' . esc_html( $date_str ) . '</div>';
				$html .= '<div class="stackboost-thread-body">' . $body . '</div>';
				$html .= '</div>';
				$html .= '</div>';

			} else {
				// Standard List View
				$html .= '<div class="stackboost-thread-item">';

				$type_label = '';
				switch ( $thread->type ) {
					case 'report': $type_label = __( 'Reported', 'stackboost-for-supportcandy' ); break;
					case 'reply': $type_label = __( 'Replied', 'stackboost-for-supportcandy' ); break;
					case 'note': $type_label = __( 'Private Note', 'stackboost-for-supportcandy' ); break;
				}

				$style_bg = ( 'note' === $thread->type ) ? 'background: #fff8e1;' : 'background: #f9f9f9;';
				$style_border = ( 'note' === $thread->type ) ? 'border-left: 4px solid #fbc02d;' : 'border-left: 4px solid #ddd;';

				$html .= '<div style="padding: 8px; margin-bottom: 10px; ' . $style_bg . $style_border . '">';
				$html .= '<strong>' . esc_html( $author_name ) . '</strong> <span style="color:#777; font-size: 0.9em;">(' . esc_html( $type_label ) . ')</span>';
				$html .= '<div style="font-size: 0.8em; color: #999;">' . esc_html( $date_str ) . '</div>';
				$html .= '<div class="stackboost-thread-body" style="margin-top: 5px;">' . $body . '</div>';
				$html .= '</div>'; // End container
				$html .= '</div>'; // End item
			}
		}
		$html .= '</div>';

		return $html;
	}

}
