<?php
/**
 * Unified Ticket Macro - Admin Settings
 *
 * @package StackBoost\ForSupportCandy\Modules\UnifiedTicketMacro
 */

namespace StackBoost\ForSupportCandy\Modules\UnifiedTicketMacro;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WordPress class.
 */
class WordPress {

	private static $instance = null;

	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize the admin settings.
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'wp_ajax_stackboost_utm_save_settings', array( $this, 'ajax_save_settings' ) );
	}

	/**
	 * Add the admin menu page.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'stackboost-for-supportcandy',
			__( 'Unified Ticket Macro', 'stackboost-for-supportcandy' ),
			__( 'Unified Ticket Macro', 'stackboost-for-supportcandy' ),
			'manage_options',
			'stackboost-utm',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue admin scripts and styles.
	 */
	public function enqueue_admin_scripts( $hook ) {
		// This is the final, correct hook, identified from working modules in the plugin.
		if ( 'stackboost_page_stackboost-utm' !== $hook ) {
			return;
		}

		$plugin_url = plugin_dir_url( \STACKBOOST_PLUGIN_FILE );

		wp_enqueue_script(
			'stackboost-admin-utm',
			$plugin_url . 'assets/admin/js/utm-admin.js',
			array( 'jquery' ),
			\STACKBOOST_VERSION, // Corrected: Use the global constant to prevent a fatal error.
			true
		);

		wp_localize_script(
			'stackboost-admin-utm',
			'stackboost_utm_admin_params',
			array(
				'nonce' => wp_create_nonce( 'stackboost_utm_save_settings_nonce' ),
			)
		);

		wp_enqueue_style(
			'stackboost-admin-utm',
			$plugin_url . 'assets/admin/css/utm-admin.css',
			[],
			\STACKBOOST_VERSION // Corrected: Use the global constant to prevent a fatal error.
		);
	}

	/**
	 * Render the main settings page.
	 */
	public function render_settings_page() {
		?>
		<div class="wrap">
			<div id="stackboost-utm-toast-container"></div>
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php _e( 'Enable Feature', 'stackboost-for-supportcandy' ); ?></th>
						<td><?php $this->render_enable_checkbox(); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php _e( 'Fields to Display', 'stackboost-for-supportcandy' ); ?></th>
						<td><?php $this->render_fields_selector(); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php _e( 'Field Order', 'stackboost-for-supportcandy' ); ?></th>
						<td><?php $this->render_use_sc_order_checkbox(); ?></td>
					</tr>
				</tbody>
			</table>

			<h2><?php _e( 'Rename Field Titles', 'stackboost-for-supportcandy' ); ?></h2>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php _e( 'Renaming Rules', 'stackboost-for-supportcandy' ); ?></th>
						<td><?php $this->render_rules_builder(); ?></td>
					</tr>
				</tbody>
			</table>

			<p class="submit">
				<button type="button" id="stackboost-utm-save-settings" class="button button-primary"><?php esc_html_e( 'Save Settings', 'stackboost-for-supportcandy' ); ?></button>
				<span class="spinner"></span>
			</p>
		</div>
		<?php
	}

	/**
	 * Render the dual-column fields selector.
	 */
	public function render_fields_selector() {
		$options         = get_option( 'stackboost_settings', [] );
		$all_columns     = \StackBoost\ForSupportCandy\WordPress\Plugin::get_instance()->get_supportcandy_columns();
		$selected_slugs  = isset( $options['utm_columns'] ) && is_array( $options['utm_columns'] ) ? $options['utm_columns'] : [];

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
				<button type="button" class="button" id="stackboost_utm_add" title="<?php esc_attr_e( 'Add', 'stackboost-for-supportcandy' ); ?>"><span class="dashicons dashicons-arrow-right"></span></button>
				<button type="button" class="button" id="stackboost_utm_remove" title="<?php esc_attr_e( 'Remove', 'stackboost-for-supportcandy' ); ?>"><span class="dashicons dashicons-arrow-left"></span></button>
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
					<div class="stackboost-utm-buttons">
						<button type="button" class="button" id="stackboost_utm_move_top" title="<?php esc_attr_e( 'Move to Top', 'stackboost-for-supportcandy' ); ?>"><span class="dashicons dashicons-controls-forward"></span></button>
						<button type="button" class="button" id="stackboost_utm_move_up" title="<?php esc_attr_e( 'Move Up', 'stackboost-for-supportcandy' ); ?>"><span class="dashicons dashicons-arrow-right"></span></button>
						<button type="button" class="button" id="stackboost_utm_move_down" title="<?php esc_attr_e( 'Move Down', 'stackboost-for-supportcandy' ); ?>"><span class="dashicons dashicons-arrow-left"></span></button>
						<button type="button" class="button" id="stackboost_utm_move_bottom" title="<?php esc_attr_e( 'Move to Bottom', 'stackboost-for-supportcandy' ); ?>"><span class="dashicons dashicons-controls-back"></span></button>
					</div>
				</div>
			</div>
		</div>
		<p class="description"><?php esc_html_e( 'Select the fields you want to include in the macro. The order of fields in the "Selected Fields" box will be the order they appear in the email.', 'stackboost-for-supportcandy' ); ?></p>
		<?php
	}

	/**
	 * Render the checkbox for enabling the feature.
	 */
	public function render_enable_checkbox() {
		$options      = get_option( 'stackboost_settings', [] );
		$is_enabled = isset( $options['utm_enabled'] ) ? (bool) $options['utm_enabled'] : false;
		?>
		<label>
			<input type="checkbox" name="stackboost_settings[utm_enabled]" id="stackboost_utm_enabled" value="1" <?php checked( $is_enabled ); ?> />
			<?php esc_html_e( 'Enable the Unified Ticket Macro feature.', 'stackboost-for-supportcandy' ); ?>
		</label>
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
			<input type="checkbox" name="stackboost_settings[utm_use_sc_order]" id="stackboost_use_sc_order" value="1" <?php checked( $use_sc_order ); ?> />
			<?php esc_html_e( 'Use SupportCandy Field Order', 'stackboost-for-supportcandy' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'If checked, the fields will be ordered according to the global settings in SupportCandy -> Ticket Form Fields. The manual sorting controls will be disabled.', 'stackboost-for-supportcandy' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the rules builder UI.
	 */
	public function render_rules_builder() {
		$options      = get_option( 'stackboost_settings', [] );
		$all_columns  = \StackBoost\ForSupportCandy\WordPress\Plugin::get_instance()->get_supportcandy_columns();
		$rename_rules = isset( $options['utm_rename_rules'] ) && is_array( $options['utm_rename_rules'] ) ? $options['utm_rename_rules'] : [];
		?>
		<div id="stackboost-utm-rules-container">
			<?php
			if ( ! empty( $rename_rules ) ) :
				foreach ( $rename_rules as $rule ) :
					?>
					<div class="stackboost-utm-rule-row">
						<span><?php esc_html_e( 'Display', 'stackboost-for-supportcandy' ); ?></span>
						<select class="stackboost-utm-rule-field">
							<?php foreach ( $all_columns as $slug => $name ) : ?>
								<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $slug, $rule['field'] ); ?>><?php echo esc_html( $name ); ?></option>
							<?php endforeach; ?>
						</select>
						<span><?php esc_html_e( 'as', 'stackboost-for-supportcandy' ); ?></span>
						<input type="text" class="stackboost-utm-rule-name" value="<?php echo esc_attr( $rule['name'] ); ?>" />
						<button type="button" class="button stackboost-utm-remove-rule" title="<?php esc_attr_e( 'Remove Rule', 'stackboost-for-supportcandy' ); ?>"><span class="dashicons dashicons-trash"></span></button>
					</div>
					<?php
				endforeach;
			endif;
			?>
		</div>
		<button type="button" id="stackboost-utm-add-rule" class="button"><?php esc_html_e( 'Add Rule', 'stackboost-for-supportcandy' ); ?></button>
		<p class="description"><?php esc_html_e( 'Here you can rename the titles of fields for the email output. For example, you could change "ID" to "Ticket Number".', 'stackboost-for-supportcandy' ); ?></p>

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
				<button type="button" class="button stackboost-utm-remove-rule" title="<?php esc_attr_e( 'Remove Rule', 'stackboost-for-supportcandy' ); ?>"><span class="dashicons dashicons-trash"></span></button>
			</div>
		</script>
		<?php
	}

	/**
	 * AJAX handler for saving settings.
	 */
	public function ajax_save_settings() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'stackboost_utm_save_settings_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'stackboost-for-supportcandy' ) ) );
		}

		// Sanitize and get the selected fields
		$selected_fields = isset( $_POST['selected_fields'] ) && is_array( $_POST['selected_fields'] )
			? array_map( 'sanitize_text_field', wp_unslash( $_POST['selected_fields'] ) )
			: array();

		// Sanitize and get the rename rules
		$rename_rules = array();
		if ( isset( $_POST['rename_rules'] ) && is_array( $_POST['rename_rules'] ) ) {
			foreach ( wp_unslash( $_POST['rename_rules'] ) as $rule ) {
				if ( ! empty( $rule['field'] ) && ! empty( $rule['name'] ) ) {
					$rename_rules[] = array(
						'field' => sanitize_text_field( $rule['field'] ),
						'name'  => sanitize_text_field( $rule['name'] ),
					);
				}
			}
		}

		// Sanitize and get the order setting
		$use_sc_order = isset( $_POST['use_sc_order'] ) && 'true' === $_POST['use_sc_order'];

		// Sanitize and get the enabled setting
		$is_enabled = isset( $_POST['is_enabled'] ) && 'true' === $_POST['is_enabled'];

		// Get all settings, update the UTM fields, and save
		$settings = get_option( 'stackboost_settings', array() );
		$settings['utm_enabled'] = $is_enabled;
		$settings['utm_columns'] = $selected_fields;
		$settings['utm_rename_rules'] = $rename_rules;
		$settings['utm_use_sc_order'] = $use_sc_order;
		update_option( 'stackboost_settings', $settings );

		wp_send_json_success(
			array(
				'message' => __( 'Settings saved successfully!', 'stackboost-for-supportcandy' ),
			)
		);
	}
}
