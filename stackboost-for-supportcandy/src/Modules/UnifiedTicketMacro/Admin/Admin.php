<?php

namespace StackBoost\ForSupportCandy\Modules\UnifiedTicketMacro\Admin;

/**
 * Admin handler for the Unified Ticket Macro feature.
 *
 * @package StackBoost\ForSupportCandy\Modules\UnifiedTicketMacro\Admin
 */
class Admin {

	const PAGE_SLUG = 'stackboost-utm';

	public function init_hooks() {
		// All settings are now registered centrally in Settings.php
		// All hooks are now registered in the module's WordPress.php
	}
	/**
	 * Render the main settings page.
	 */
	public function render_settings_page() {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form id="stackboost-utm-form" action="options.php" method="post">
				<?php
				settings_fields( 'stackboost_settings' );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	public function render_field_utm_enabled() {
		$options = get_option( 'stackboost_settings', [] );
		$value   = $options['utm_enabled'] ?? '0';
		?>
		<label>
			<input type="checkbox" name="stackboost_settings[utm_enabled]" value="1" <?php checked( $value, '1' ); ?>>
			<?php esc_html_e( 'Enable the {{stackboost_unified_ticket}} macro for use in email templates.', 'stackboost-for-supportcandy' ); ?>
		</label>
		<?php
	}

	public function render_field_utm_use_sc_order() {
		$options = get_option( 'stackboost_settings', [] );
		$value   = $options['utm_use_sc_order'] ?? '0';
		?>
		<label>
			<input type="checkbox" id="utm_use_sc_order" name="stackboost_settings[utm_use_sc_order]" value="1" <?php checked( $value, '1' ); ?>>
			<?php esc_html_e( 'When checked, selected fields will be ordered according to your SupportCandy Ticket Form Fields settings.', 'stackboost-for-supportcandy' ); ?>
		</label>
		<?php
	}

	public function render_field_utm_selected_fields() {
		$options         = get_option( 'stackboost_settings', [] );
		$all_columns     = \StackBoost\ForSupportCandy\WordPress\Plugin::get_instance()->get_supportcandy_columns();
		$selected_slugs  = $options['utm_selected_fields'] ?? [];

		$available_columns = array_diff_key( $all_columns, array_flip( $selected_slugs ) );
		$selected_columns  = array_intersect_key( $all_columns, array_flip( $selected_slugs ) );

		// Ensure the order of selected columns is preserved.
		$ordered_selected = [];
		foreach ($selected_slugs as $slug) {
			if (isset($selected_columns[$slug])) {
				$ordered_selected[$slug] = $selected_columns[$slug];
			}
		}
		?>
		<div class="scp-utm-container">
			<div class="scp-utm-box">
				<h3><?php esc_html_e( 'Available Fields', 'stackboost-for-supportcandy' ); ?></h3>
				<select multiple id="utm-available-fields" size="10">
					<?php foreach ( $available_columns as $slug => $name ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="scp-utm-buttons">
				<button type="button" class="button" id="utm-add-all" title="<?php esc_attr_e( 'Add All', 'stackboost-for-supportcandy' ); ?>">&gt;&gt;</button>
				<button type="button" class="button" id="utm-add-field" title="<?php esc_attr_e( 'Add', 'stackboost-for-supportcandy' ); ?>">&gt;</button>
				<button type="button" class="button" id="utm-remove-field" title="<?php esc_attr_e( 'Remove', 'stackboost-for-supportcandy' ); ?>">&lt;</button>
				<button type="button" class="button" id="utm-remove-all" title="<?php esc_attr_e( 'Remove All', 'stackboost-for-supportcandy' ); ?>">&lt;&lt;</button>
			</div>
			<div class="scp-utm-box">
				<h3><?php esc_html_e( 'Selected Fields', 'stackboost-for-supportcandy' ); ?></h3>
				<div class="scp-utm-selected-wrapper">
					<select multiple name="stackboost_settings[utm_selected_fields][]" id="utm-selected-fields-list" size="10">
						<?php foreach ( $ordered_selected as $slug => $name ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $name ); ?></option>
						<?php endforeach; ?>
					</select>
					<div class="scp-utm-buttons">
						<button type="button" class="button" id="utm-move-top" title="<?php esc_attr_e( 'Move to Top', 'stackboost-for-supportcandy' ); ?>">Top</button>
						<button type="button" class="button" id="utm-move-up" title="<?php esc_attr_e( 'Move Up', 'stackboost-for-supportcandy' ); ?>">Up</button>
						<button type="button" class="button" id="utm-move-down" title="<?php esc_attr_e( 'Move Down', 'stackboost-for-supportcandy' ); ?>">Down</button>
						<button type="button" class="button" id="utm-move-bottom" title="<?php esc_attr_e( 'Move to Bottom', 'stackboost-for-supportcandy' ); ?>">Bottom</button>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	public function render_field_utm_rename_rules() {
		$options      = get_option( 'stackboost_settings', [] );
		$all_columns  = \StackBoost\ForSupportCandy\WordPress\Plugin::get_instance()->get_supportcandy_columns();
		$rename_rules = $options['utm_rename_rules'] ?? [];
		?>
		<div id="utm-rename-rules-wrapper" class="stackboost-rules-builder">
			<div class="rules-container">
				<?php
				if ( ! empty( $rename_rules ) ) :
					foreach ( $rename_rules as $index => $rule ) :
						?>
						<div class="rule-group">
							<select name="stackboost_settings[utm_rename_rules][<?php echo esc_attr( $index ); ?>][field]">
								<?php foreach ( $all_columns as $slug => $name ) : ?>
									<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $slug, $rule['field'] ); ?>><?php echo esc_html( $name ); ?></option>
								<?php endforeach; ?>
							</select>
							<input type="text" name="stackboost_settings[utm_rename_rules][<?php echo esc_attr( $index ); ?>][name]" value="<?php echo esc_attr( $rule['name'] ); ?>" placeholder="<?php esc_attr_e( 'New Name', 'stackboost-for-supportcandy' ); ?>">
							<button type="button" class="button remove-rule"><span class="dashicons dashicons-trash"></span></button>
						</div>
						<?php
					endforeach;
				endif;
				?>
			</div>
			<button type="button" class="button add-rule"><?php esc_html_e( 'Add Rule', 'stackboost-for-supportcandy' ); ?></button>
		</div>

		<script type="text/template" id="utm-rule-template">
			<div class="rule-group">
				<select name="stackboost_settings[utm_rename_rules][__INDEX__][field]">
					<?php foreach ( $all_columns as $slug => $name ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $name ); ?></option>
					<?php endforeach; ?>
				</select>
				<input type="text" name="stackboost_settings[utm_rename_rules][__INDEX__][name]" value="" placeholder="<?php esc_attr_e( 'New Name', 'stackboost-for-supportcandy' ); ?>">
				<button type="button" class="button remove-rule"><span class="dashicons dashicons-trash"></span></button>
			</div>
		</script>
		<?php
	}
}
