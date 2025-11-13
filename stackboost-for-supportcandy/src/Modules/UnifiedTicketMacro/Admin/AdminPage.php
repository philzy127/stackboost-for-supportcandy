<?php

namespace StackBoost\ForSupportCandy\Modules\UnifiedTicketMacro\Admin;

/**
 * Admin handler for the Unified Ticket Macro feature.
 *
 * @package StackBoost\ForSupportCandy\Modules\UnifiedTicketMacro\Admin
 */
class AdminPage {

	const PAGE_SLUG = 'stackboost-utm';

	/**
	 * Initialize the admin hooks.
	 */
	public function init_hooks() {
		// Intentionally left blank. Settings are registered in the main Settings class.
	}

	/**
	 * Render the settings page.
	 */
	public function render_settings_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Unified Ticket Macro', 'stackboost-for-supportcandy' ); ?></h1>
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

	public function render_field_utm_enabled( $args ) {
		$options = get_option( 'stackboost_settings', [] );
		$id      = 'utm_enabled';
		$value   = $options[ $id ] ?? '0';
		?>
		<label>
			<input type="hidden" name="stackboost_settings[<?php echo esc_attr( $id ); ?>]" value="0">
			<input type="checkbox" name="stackboost_settings[<?php echo esc_attr( $id ); ?>]" value="1" <?php checked( $value, '1' ); ?>>
			<?php esc_html_e( 'Enable the {{stackboost_unified_ticket}} macro for use in email templates.', 'stackboost-for-supportcandy' ); ?>
		</label>
		<?php
	}

	public function render_field_utm_use_sc_order( $args ) {
		$options = get_option( 'stackboost_settings', [] );
		$id      = 'utm_use_sc_order';
		$value   = $options[ $id ] ?? '0';
		?>
		<label>
			<input type="hidden" name="stackboost_settings[<?php echo esc_attr( $id ); ?>]" value="0">
			<input type="checkbox" name="stackboost_settings[<?php echo esc_attr( $id ); ?>]" value="1" <?php checked( $value, '1' ); ?>>
			<?php esc_html_e( 'When checked, selected fields will be ordered according to your SupportCandy Ticket Form Fields settings.', 'stackboost-for-supportcandy' ); ?>
		</label>
		<?php
	}

	public function render_field_utm_selected_fields( $args ) {
		$options         = get_option( 'stackboost_settings', [] );
		$id              = 'utm_selected_fields';
		$selected_values = $options[ $id ] ?? [];
		$all_columns     = \StackBoost\ForSupportCandy\WordPress\Plugin::get_instance()->get_supportcandy_columns();
		?>
		<div class="dual-list-selector">
			<div class="available-fields">
				<strong><?php esc_html_e( 'Available Fields', 'stackboost-for-supportcandy' ); ?></strong>
				<select id="utm-available-fields" multiple size="10">
					<?php foreach ( $all_columns as $slug => $name ) : ?>
						<?php if ( ! in_array( $slug, $selected_values, true ) ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $name ); ?></option>
						<?php endif; ?>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="controls">
				<button type="button" class="button" id="utm-add-field">&gt;</button>
				<button type="button" class="button" id="utm-remove-field">&lt;</button>
			</div>
			<div class="selected-fields">
				<strong><?php esc_html_e( 'Selected Fields (Drag to Reorder)', 'stackboost-for-supportcandy' ); ?></strong>
				<select id="utm-selected-fields-list" name="stackboost_settings[<?php echo esc_attr( $id ); ?>][]" multiple size="10">
					<?php foreach ( $selected_values as $slug ) : ?>
						<?php if ( isset( $all_columns[ $slug ] ) ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $all_columns[ $slug ] ); ?></option>
						<?php endif; ?>
					<?php endforeach; ?>
				</select>
			</div>
		</div>
		<?php
	}

	public function render_field_utm_rename_rules( $args ) {
		$options = get_option( 'stackboost_settings', [] );
		$id      = 'utm_rename_rules';
		$rules   = $options[ $id ] ?? [];
		?>
		<div id="utm-rename-rules-wrapper" class="stackboost-rules-builder">
			<div class="rules-container">
				<?php if ( ! empty( $rules ) ) : ?>
					<?php foreach ( $rules as $index => $rule ) : ?>
						<div class="rule-group">
							<select name="stackboost_settings[<?php echo esc_attr( $id ); ?>][<?php echo esc_attr( $index ); ?>][field]">
								<?php // Options will be populated by JS ?>
							</select>
							<input type="text" name="stackboost_settings[<?php echo esc_attr( $id ); ?>][<?php echo esc_attr( $index ); ?>][name]" value="<?php echo esc_attr( $rule['name'] ); ?>" placeholder="<?php esc_attr_e( 'New Name', 'stackboost-for-supportcandy' ); ?>">
							<button type="button" class="button remove-rule"><span class="dashicons dashicons-trash"></span></button>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
			<button type="button" class="button add-rule"><?php esc_html_e( 'Add Rename Rule', 'stackboost-for-supportcandy' ); ?></button>
		</div>
		<?php
	}
}
