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
		// Frontend enqueue might be needed if the ticket list is shown on frontend.
		// SupportCandy frontend usually uses a shortcode.
		// We can hook into wp_enqueue_scripts.
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	/**
	 * Enqueue scripts.
	 *
	 * @param string $hook_suffix
	 */
	public function enqueue_scripts( $hook_suffix = '' ) {
		// Only enqueue if feature is enabled.
		$options = get_option( 'stackboost_settings' );
		if ( empty( $options['enable_page_last_loaded'] ) ) {
			return;
		}

		// For admin, check hook suffix.
		if ( is_admin() ) {
			// supportcandy_page_wpsc-tickets is the ticket list page.
			// supportcandy_page_wpsc-view-ticket is the individual ticket page.
			if ( 'supportcandy_page_wpsc-tickets' !== $hook_suffix ) {
				return;
			}
		}
		// For frontend, we can't easily check for shortcode presence without parsing post content,
		// but we can rely on SupportCandy's assets usually being loaded.
		// To be safe and performant, we might want to check if WPSC is loaded or if we are on a page with the shortcode.
		// However, for simplicity and robustness (as user requested "Visuals: Both"), we'll enqueue if it's not admin.
		// A more refined check could be added if needed.

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

	/**
	 * Register settings fields for this module.
	 */
	public function register_settings() {
		$page_slug = 'stackboost-ticket-view'; // The new settings page.

		// Section: Ticket Details Card
		add_settings_section( 'stackboost_ticket_details_card_section', __( 'Ticket Details Card', 'stackboost-for-supportcandy' ), null, $page_slug );
		add_settings_field( 'stackboost_enable_ticket_details_card', __( 'Enable Feature', 'stackboost-for-supportcandy' ), [ $this, 'render_checkbox_field' ], $page_slug, 'stackboost_ticket_details_card_section', [ 'id' => 'enable_ticket_details_card', 'desc' => 'Shows a card with ticket details on right-click.' ] );

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
