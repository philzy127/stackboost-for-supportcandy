<?php

namespace StackBoost\ForSupportCandy\Modules\UnifiedTicketMacro\Admin;

/**
 * Admin settings page for the Unified Ticket Macro feature.
 *
 * This class is responsible for rendering the settings page UI,
 * including the dual-list selector for fields and the renaming rules builder.
 *
 * @package StackBoost\ForSupportCandy\Modules\UnifiedTicketMacro\Admin
 */
class Admin {

	/**
	 * The slug for the admin page.
	 */
	const PAGE_SLUG = 'stackboost-utm';

	/**
	 * Initialize hooks for the admin page.
	 */
	public function init_hooks() {
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	/**
	 * Render the main settings page.
	 */
	public function render_settings_page() {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'stackboost_settings' );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Register settings sections and fields.
	 */
	public function register_settings() {
		add_settings_section(
			'stackboost_utm_enable_section',
			__( 'General Settings', 'stackboost-for-supportcandy' ),
			'__return_false',
			self::PAGE_SLUG
		);

		add_settings_field(
			'stackboost_utm_enabled',
			__( 'Enable Feature', 'stackboost-for-supportcandy' ),
			[ $this, 'render_enabled_checkbox' ],
			self::PAGE_SLUG,
			'stackboost_utm_enable_section'
		);

		add_settings_section(
			'stackboost_utm_fields_section',
			__( 'Unified Ticket Macro Fields', 'stackboost-for-supportcandy' ),
			'__return_false',
			self::PAGE_SLUG
		);

		add_settings_field(
			'stackboost_utm_selected_fields',
			__( 'Fields to Display', 'stackboost-for-supportcandy' ),
			[ $this, 'render_fields_selector' ],
			self::PAGE_SLUG,
			'stackboost_utm_fields_section'
		);

		add_settings_field(
			'stackboost_utm_rename_rules',
			__( 'Rename Field Titles', 'stackboost-for-supportcandy' ),
			[ $this, 'render_rules_builder' ],
			self::PAGE_SLUG,
			'stackboost_utm_fields_section'
		);

		add_settings_field(
			'stackboost_utm_use_sc_order',
			__( 'Field Order', 'stackboost-for-supportcandy' ),
			[ $this, 'render_use_sc_order_checkbox' ],
			self::PAGE_SLUG,
			'stackboost_utm_fields_section'
		);
	}

	/**
	 * Render the dual-column fields selector.
	 */
	public function render_fields_selector() {
		$options         = get_option( 'stackboost_settings', [] );
		$all_columns     = \StackBoost\ForSupportCandy\WordPress\Plugin::get_instance()->get_supportcandy_columns();
		$selected_slugs  = $options['utm_selected_fields'] ?? [];

		$available_columns = array_diff_key( $all_columns, array_flip( $selected_slugs ) );
		$selected_columns  = array_intersect_key( $all_columns, array_flip( $selected_slugs ) );

		// Ensure the order of selected columns is preserved.
		$ordered_selected = [];
		foreach ( $selected_slugs as $slug ) {
			if ( isset( $selected_columns[ $slug ] ) ) {
				$ordered_selected[ $slug ] = $selected_columns[ $slug ];
			}
		}
		?>
		<div class="stackboost-utm-container">
			<div class="stackboost-utm-box">
				<h3><?php esc_html_e( 'Available Fields', 'stackboost-for-supportcandy' ); ?></h3>
				<select multiple id="stackboost_utm_available_fields" size="10">
					<?php foreach ( $available_columns as $slug => $name ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="stackboost-utm-buttons">
				<button type="button" class="button" id="stackboost_utm_add_all">&gt;&gt;</button>
				<button type="button" class="button" id="stackboost_utm_add">&gt;</button>
				<button type="button" class="button" id="stackboost_utm_remove">&lt;</button>
				<button type="button" class="button" id="stackboost_utm_remove_all">&lt;&lt;</button>
			</div>
			<div class="stackboost-utm-box">
				<h3><?php esc_html_e( 'Selected Fields', 'stackboost-for-supportcandy' ); ?></h3>
				<div class="stackboost-utm-selected-wrapper">
					<select multiple name="stackboost_settings[utm_selected_fields][]" id="stackboost_utm_selected_fields" size="10">
						<?php foreach ( $ordered_selected as $slug => $name ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $name ); ?></option>
						<?php endforeach; ?>
					</select>
					<div class="stackboost-utm-buttons">
						<button type="button" class="button" id="stackboost_utm_move_top">Top</button>
						<button type="button" class="button" id="stackboost_utm_move_up">Up</button>
						<button type="button" class="button" id="stackboost_utm_move_down">Down</button>
						<button type="button" class="button" id="stackboost_utm_move_bottom">Bottom</button>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the rules builder UI.
	 */
	public function render_rules_builder() {
		$options      = get_option( 'stackboost_settings', [] );
		$all_columns  = \StackBoost\ForSupportCandy\WordPress\Plugin::get_instance()->get_supportcandy_columns();
		$rename_rules = $options['utm_rename_rules'] ?? [];
		?>
		<div id="stackboost-utm-rules-container">
			<?php if ( ! empty( $rename_rules ) ) : ?>
				<?php foreach ( $rename_rules as $rule ) : ?>
					<div class="stackboost-utm-rule-row">
						<select class="stackboost-utm-rule-field">
							<?php foreach ( $all_columns as $slug => $name ) : ?>
								<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $slug, $rule['field'] ); ?>><?php echo esc_html( $name ); ?></option>
							<?php endforeach; ?>
						</select>
						<input type="text" class="stackboost-utm-rule-name" value="<?php echo esc_attr( $rule['name'] ); ?>" />
						<button type="button" class="button stackboost-utm-remove-rule">X</button>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
		<button type="button" id="stackboost-utm-add-rule" class="button"><?php esc_html_e( 'Add Rule', 'stackboost-for-supportcandy' ); ?></button>
		<script type="text/template" id="stackboost-utm-rule-template">
			<div class="stackboost-utm-rule-row">
				<select class="stackboost-utm-rule-field">
					<?php foreach ( $all_columns as $slug => $name ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $name ); ?></option>
					<?php endforeach; ?>
				</select>
				<input type="text" class="stackboost-utm-rule-name" value="" />
				<button type="button" class="button stackboost-utm-remove-rule">X</button>
			</div>
		</script>
		<?php
	}

	/**
	 * Render the checkbox for using SupportCandy field order.
	 */
	public function render_use_sc_order_checkbox() {
		$options      = get_option( 'stackboost_settings', [] );
		$use_sc_order = isset( $options['utm_use_sc_order'] ) ? (bool) $options['utm_use_sc_order'] : false;
		?>
		<label>
			<input type="checkbox" name="stackboost_settings[utm_use_sc_order]" id="stackboost_utm_use_sc_order" value="1" <?php checked( $use_sc_order ); ?> />
			<?php esc_html_e( 'Use SupportCandy Field Order', 'stackboost-for-supportcandy' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'If checked, the fields will be ordered according to the global settings in SupportCandy -> Ticket Form Fields. The manual sorting controls will be disabled.', 'stackboost-for-supportcandy' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the checkbox for enabling the feature.
	 */
	public function render_enabled_checkbox() {
		$options = get_option( 'stackboost_settings', [] );
		$enabled = isset( $options['utm_enabled'] ) ? (bool) $options['utm_enabled'] : false;
		?>
		<label>
			<input type="checkbox" name="stackboost_settings[utm_enabled]" id="stackboost_utm_enabled" value="1" <?php checked( $enabled ); ?> />
			<?php esc_html_e( 'Enable the Unified Ticket Macro feature.', 'stackboost-for-supportcandy' ); ?>
		</label>
		<?php
	}
}
