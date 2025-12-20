<?php

namespace StackBoost\ForSupportCandy\Modules\TicketView;

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
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'wpsc_get_individual_ticket' ) ) {
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

		// Determine View Type
		$view_type = $options['ticket_details_view_type'] ?? 'standard';
		if ( 'utm' === $view_type && ! stackboost_is_feature_active( 'unified_ticket_macro' ) ) {
			$view_type = 'standard'; // Fallback if Pro feature is disabled
		}

		// Determine Content to Include
		$content_type = $options['ticket_details_content'] ?? 'details_only';
		$image_handling = $options['ticket_details_image_handling'] ?? 'fit';
		$limit = isset( $options['ticket_details_history_limit'] ) ? intval( $options['ticket_details_history_limit'] ) : 0;

		$html = '';

		// 1. Generate Base Details (Table/Card)
		if ( 'utm' === $view_type ) {
			if ( class_exists( 'StackBoost\ForSupportCandy\Modules\UnifiedTicketMacro\Core' ) ) {
				$html .= \StackBoost\ForSupportCandy\Modules\UnifiedTicketMacro\Core::get_instance()->build_live_utm_html( $ticket );
			} else {
				$html .= '<p>' . __( 'UTM Module not available.', 'stackboost-for-supportcandy' ) . '</p>';
			}
		}
		// 'standard' view returns empty html for the base part, letting JS scrape standard fields.

		// 2. Append Threads (Description / History)
		if ( 'details_only' !== $content_type ) {
			if ( class_exists( 'StackBoost\ForSupportCandy\Modules\UnifiedTicketMacro\Core' ) ) {
				$include_private = $current_user->is_agent; // Bonus point logic

				// Special handling: 'with_description' only wants the report.
				// 'with_history' wants everything.
				// The `render_ticket_threads` function in UTM Core fetches based on types.
				// We might need to adjust it or filter the result.
				// Actually, `render_ticket_threads` fetches ALL history.
				// If we only want description, we should just fetch the description thread.

				if ( 'with_description' === $content_type ) {
					$desc_thread = $ticket->get_description_thread();
					if ( $desc_thread ) {
						// Reuse logic? Or manually render?
						// Let's use a temporary ticket object or modify parameters? No.
						// Let's just manually render the description using the same style as `render_ticket_threads`.
						// OR better: Update `render_ticket_threads` to accept a 'limit' or 'type' filter.
						// But I already wrote `render_ticket_threads`.
						// Let's just use it and filter strictly by type 'report' if needed?
						// No, `render_ticket_threads` is built to fetch all.

						// Let's implement a simple render for single thread here to avoid modifying Core again if not needed.
						// Wait, consistency is key. I'll use `render_ticket_threads` but I need to make sure it only returns what I want.
						// I'll update `render_ticket_threads` in the next step if I need to, but for now,
						// let's just use it for 'with_history'.
						// For 'with_description', I can just fetch the body.

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
						$html .= '<div class="wpsc-it-widget stackboost-ticket-card-extension" style="margin-top: 10px;">';
						$html .= '<div class="wpsc-widget-header"><h2>' . __( 'Description', 'stackboost-for-supportcandy' ) . '</h2></div>';
						$html .= '<div class="wpsc-widget-body stackboost-thread-body">';
						$html .= '<div>' . wp_kses_post( $body ) . '</div>';
						$html .= '</div></div>';

					}
				} elseif ( 'with_history' === $content_type ) {
					// Render threads but strip the internal header from Core since we wrap it here
					$threads_html = \StackBoost\ForSupportCandy\Modules\UnifiedTicketMacro\Core::get_instance()->render_ticket_threads( $ticket, $include_private, $image_handling, $limit );

					if ( ! empty( $threads_html ) ) {
						$html .= '<div class="wpsc-it-widget stackboost-ticket-card-extension" style="margin-top: 10px;">';
						$html .= '<div class="wpsc-widget-header"><h2>' . __( 'Conversation History', 'stackboost-for-supportcandy' ) . '</h2></div>';
						$html .= '<div class="wpsc-widget-body">';
						$html .= $threads_html;
						$html .= '</div></div>';
					}
				}
			}
		}

		wp_send_json_success( [ 'html' => $html ] );
	}

	/**
	 * Enqueue scripts.
	 *
	 * @param string $hook_suffix
	 */
	public function enqueue_scripts( $hook_suffix = '' ) {
		stackboost_log( "TicketView::enqueue_scripts called with hook_suffix: {$hook_suffix}", 'ticket_view' );

		$options = get_option( 'stackboost_settings' );

		// Debug log options
		stackboost_log( "TicketView::enqueue_scripts options: " . print_r( [
			'enable_page_last_loaded'      => $options['enable_page_last_loaded'] ?? 'not set',
			'enable_ticket_details_card'   => $options['enable_ticket_details_card'] ?? 'not set',
		], true ), 'ticket_view' );

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
					'details_only'     => __( 'Details Only (None)', 'stackboost-for-supportcandy' ),
					'with_description' => __( 'Initial Description Only', 'stackboost-for-supportcandy' ),
					'with_history'     => __( 'Full Conversation History (Public)', 'stackboost-for-supportcandy' ),
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
					'fit'         => __( 'Fit to Container (Default)', 'stackboost-for-supportcandy' ),
					'strip'       => __( 'Remove Images (Text Only)', 'stackboost-for-supportcandy' ),
					'placeholder' => __( 'Replace with [Image] Placeholder', 'stackboost-for-supportcandy' ),
				],
				'desc' => 'How to handle images within the description and notes.'
			]
		);

		add_settings_section( 'stackboost_separator_1', '', [ $this, 'render_hr_separator' ], $page_slug );

		// Section: General Cleanup
		add_settings_section( 'stackboost_general_cleanup_section', __( 'General Cleanup', 'stackboost-for-supportcandy' ), null, $page_slug );
		add_settings_field( 'stackboost_enable_hide_empty_columns', __( 'Hide Empty Columns', 'stackboost-for-supportcandy' ), [ $this, 'render_checkbox_field' ], $page_slug, 'stackboost_general_cleanup_section', [ 'id' => 'enable_hide_empty_columns', 'desc' => 'Automatically hide any column in the ticket list that is completely empty.' ] );
		add_settings_field( 'stackboost_enable_hide_priority_column', __( 'Hide Priority Column', 'stackboost-for-supportcandy' ), [ $this, 'render_checkbox_field' ], $page_slug, 'stackboost_general_cleanup_section', [ 'id' => 'enable_hide_priority_column', 'desc' => 'Hides the "Priority" column if all visible tickets have a priority of "Low".' ] );

		add_settings_section( 'stackboost_separator_general_cleanup_1', '', [ $this, 'render_hr_separator' ], $page_slug );

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

		add_settings_section( 'stackboost_separator_2', '', [ $this, 'render_hr_separator' ], $page_slug );

		// Section: Ticket Type Hiding
		add_settings_section( 'stackboost_ticket_type_hiding_section', __( 'Hide Ticket Types from Non-Agents', 'stackboost-for-supportcandy' ), [ $this, 'render_ticket_type_hiding_description' ], $page_slug );
		add_settings_field( 'stackboost_enable_ticket_type_hiding', __( 'Enable Feature', 'stackboost-for-supportcandy' ), [ $this, 'render_checkbox_field' ], $page_slug, 'stackboost_ticket_type_hiding_section', [ 'id' => 'enable_ticket_type_hiding', 'desc' => 'Hide specific ticket types from non-agent users.' ] );

		$plugin_instance = \StackBoost\ForSupportCandy\WordPress\Plugin::get_instance();
		$custom_fields_choices = [];
		foreach ( $plugin_instance->get_supportcandy_columns() as $name ) {
			$custom_fields_choices[ $name ] = $name;
		}

		add_settings_field(
			'stackboost_ticket_type_custom_field_name',
			__( 'Custom Field Name', 'stackboost-for-supportcandy' ),
			[ $this, 'render_select_field' ],
			$page_slug,
			'stackboost_ticket_type_hiding_section',
			[
				'id'          => 'ticket_type_custom_field_name',
				'placeholder' => __( '-- Select a Custom Field --', 'stackboost-for-supportcandy' ),
				'choices'     => $custom_fields_choices,
				'desc'        => __( 'The custom field that represents the ticket type (e.g., "Ticket Category").', 'stackboost-for-supportcandy' ),
			]
		);

		add_settings_field( 'stackboost_ticket_types_to_hide', __( 'Ticket Types to Hide', 'stackboost-for-supportcandy' ), [ $this, 'render_textarea_field' ], $page_slug, 'stackboost_ticket_type_hiding_section', [ 'id' => 'ticket_types_to_hide', 'class' => 'regular-text', 'desc' => 'One ticket type per line. e.g., Network Access Request' ] );
	}

	/**
	 * Renders a checkbox field for a settings page.
	 */
	public function render_checkbox_field( array $args ) {
		$options = get_option( 'stackboost_settings' );
		$id = $args['id'];
		$checked = isset( $options[ $id ] ) && $options[ $id ] ? 'checked' : '';
		echo '<input type="checkbox" id="' . esc_attr( $id ) . '" name="stackboost_settings[' . esc_attr( $id ) . ']" value="1" ' . $checked . '>';
		if ( ! empty( $args['desc'] ) ) {
			echo '<p class="description">' . esc_html( $args['desc'] ) . '</p>';
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
		echo '<option value="standard" ' . selected( $selected, 'standard', false ) . '>' . esc_html__( 'Standard (Scraped)', 'stackboost-for-supportcandy' ) . '</option>';

		// UTM (Pro)
		$utm_label = esc_html__( 'Unified Ticket Macro', 'stackboost-for-supportcandy' );
		$disabled_attr = '';
		if ( ! $is_pro_active ) {
			$utm_label .= ' (Pro)'; // Badge in text for simpler select
			$disabled_attr = 'disabled';
		}

		echo '<option value="utm" ' . selected( $selected, 'utm', false ) . ' ' . $disabled_attr . '>' . $utm_label . '</option>';

		echo '</select>';

		if ( ! $is_pro_active ) {
			echo ' <span class="dashicons dashicons-lock" title="' . esc_attr__( 'Upgrade to Pro or Business to enable this feature.', 'stackboost-for-supportcandy' ) . '" style="color: #666; vertical-align: middle;"></span>';
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

			// Conditional Logic for History Limit
			var $limitRow = $('#ticket_details_history_limit').closest('tr');
			var $contentSelect = $('#ticket_details_content');

			function toggleLimitField() {
				if ($contentSelect.val() === 'with_history') {
					$limitRow.show();
				} else {
					$limitRow.hide();
				}
			}

			// Initial State
			toggleLimitField();

			// Change Listener
			$contentSelect.on('change', toggleLimitField);
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
	 * Render the description for the Hide Ticket Types section.
	 */
	public function render_ticket_type_hiding_description() {
		echo '<p>' . esc_html__( 'This feature hides specified ticket categories from the dropdown menu for any user who is not an agent.', 'stackboost-for-supportcandy' ) . '</p>';
	}
}
