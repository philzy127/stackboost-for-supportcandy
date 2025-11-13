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
        // No admin-side hooks needed for this class anymore,
        // as the settings are registered centrally.
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
				// This function will render all settings sections and fields that have been
				// registered for this page. The registration now happens in the main
				// Settings.php file to ensure our custom sanitization is applied.
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
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
				<button type="button" class="button" id="stackboost_utm_add_all" title="<?php esc_attr_e( 'Add All', 'stackboost-for-supportcandy' ); ?>"><span class="dashicons dashicons-controls-forward"></span></button>
				<button type="button" class="button" id="stackboost_utm_add" title="<?php esc_attr_e( 'Add', 'stackboost-for-supportcandy' ); ?>"><span class="dashicons dashicons-arrow-right-alt2"></span></button>
				<button type="button" class="button" id="stackboost_utm_remove" title="<?php esc_attr_e( 'Remove', 'stackboost-for-supportcandy' ); ?>"><span class="dashicons dashicons-arrow-left-alt2"></span></button>
				<button type="button" class="button" id="stackboost_utm_remove_all" title="<?php esc_attr_e( 'Remove All', 'stackboost-for-supportcandy' ); ?>"><span class="dashicons dashicons-controls-back"></span></button>
			</div>
			<div class="stackboost-utm-box">
				<h3><?php esc_html_e( 'Selected Fields', 'stackboost-for-supportcandy' ); ?></h3>
				<div class="stackboost-utm-selected-wrapper">
					<select multiple name="stackboost_settings[utm_selected_fields][]" id="stackboost_utm_selected_fields" size="10">
						<?php foreach ( $ordered_selected as $slug => $name ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $name ); ?></option>
						<?php endforeach; ?>
					</select>
					<div class="stackboost-utm-buttons vertical">
						<button type="button" class="button" id="stackboost_utm_move_top" title="<?php esc_attr_e( 'Move to Top', 'stackboost-for-supportcandy' ); ?>"><span class="dashicons dashicons-controls-forward"></span></button>
						<button type="button" class="button" id="stackboost_utm_move_up" title="<?php esc_attr_e( 'Move Up', 'stackboost-for-supportcandy' ); ?>"><span class="dashicons dashicons-arrow-right-alt2"></span></button>
						<button type="button" class="button" id="stackboost_utm_move_down" title="<?php esc_attr_e( 'Move Down', 'stackboost-for-supportcandy' ); ?>"><span class="dashicons dashicons-arrow-left-alt2"></span></button>
						<button type="button" class="button" id="stackboost_utm_move_bottom" title="<?php esc_attr_e( 'Move to Bottom', 'stackboost-for-supportcandy' ); ?>"><span class="dashicons dashicons-controls-back"></span></button>
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
						<span><?php esc_html_e( 'Display', 'stackboost-for-supportcandy' ); ?></span>
						<select class="stackboost-utm-rule-field">
							<?php foreach ( $all_columns as $slug => $name ) : ?>
								<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $slug, $rule['field'] ); ?>><?php echo esc_html( $name ); ?></option>
							<?php endforeach; ?>
						</select>
						<span><?php esc_html_e( 'as', 'stackboost-for-supportcandy' ); ?></span>
						<input type="text" class="stackboost-utm-rule-name" value="<?php echo esc_attr( $rule['name'] ); ?>" />
						<button type="button" class="button stackboost-utm-remove-rule"><span class="dashicons dashicons-trash"></span></button>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
		<button type="button" id="stackboost-utm-add-rule" class="button"><?php esc_html_e( 'Add Rule', 'stackboost-for-supportcandy' ); ?></button>
		<script type="text/template" id="stackboost-utm-rule-template">
			<div class="stackboost-utm-rule-row">
				<span><?php esc_html_e( 'Display', 'stackboost-for-supportcandy' ); ?></span>
				<select class="stackboost-utm-rule-field">
					<?php foreach ( $all_columns as $slug => $name ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $name ); ?></option>
					<?php endforeach; ?>
				</select>
				<span><?php esc_html_e( 'as', 'stackboost-for-supportcandy' ); ?></span>
				<input type="text" class="stackboost-utm-rule-name" value="" />
				<button type="button" class="button stackboost-utm-remove-rule"><span class="dashicons dashicons-trash"></span></button>
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
			<?php esc_html_e( 'Enable this feature', 'stackboost-for-supportcandy' ); ?>
		</label>
		<?php
	}

	/**
	 * Render descriptions for the settings sections.
	 */
	public function render_description_enable() {
		echo '<p>' . esc_html__( 'When enabled, the {{stackboost_unified_ticket}} macro will be available in email notifications.', 'stackboost-for-supportcandy' ) . '</p>';
	}

	public function render_description_fields() {
		echo '<p>' . esc_html__( 'Select the fields you want to include in the macro. The order of fields in the "Selected Fields" box will be the order they appear in the email.', 'stackboost-for-supportcandy' ) . '</p>';
	}

	public function render_description_rename() {
		echo '<p>' . esc_html__( 'Here you can rename the titles of fields for the email output. For example, you could change "ID" to "Ticket Number".', 'stackboost-for-supportcandy' ) . '</p>';
	}
}
