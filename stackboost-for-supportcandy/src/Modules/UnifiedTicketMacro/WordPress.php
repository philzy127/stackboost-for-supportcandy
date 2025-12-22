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

	/**
	 * Core instance.
	 *
	 * @var Core
	 */
	private $core;

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
		$this->core = Core::get_instance();
		// Menu page is now registered centrally in Settings.php
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		// Modern filter for replacing the macro in all outgoing emails.
		add_filter( 'wpsc_replace_macros', array( $this->core, 'replace_utm_macro' ), 10, 3 );

		add_filter( 'wpsc_macros', array( $this, 'register_macro' ) );

		// Comprehensive logging hooks.
		add_action( 'wpsc_create_new_ticket', array( $this, 'log_wpsc_create_new_ticket' ), 10, 1 );
		add_action( 'wpsc_post_reply', array( $this, 'log_wpsc_post_reply' ), 10, 1 );
		add_action( 'wpsc_submit_note', array( $this, 'log_wpsc_submit_note' ), 10, 1 );
		add_action( 'wpsc_change_assignee', array( $this, 'log_wpsc_change_assignee' ), 10, 4 );
		add_action( 'wpsc_change_ticket_status', array( $this, 'log_wpsc_change_ticket_status' ), 10, 4 );
		add_action( 'wpsc_change_ticket_priority', array( $this, 'log_wpsc_change_ticket_priority' ), 10, 4 );
		add_action( 'wpsc_delete_ticket', array( $this, 'log_wpsc_delete_ticket' ), 10, 1 );
	}

	/**
	 * Register the macro with SupportCandy.
	 *
	 * @param array $macros The existing macros.
	 * @return array The modified macros.
	 */
	public function register_macro( array $macros ): array {
		$macros[] = array(
			'tag'   => '{{stackboost_unified_ticket}}',
			'title' => esc_attr__( 'Unified Ticket Macro', 'stackboost-for-supportcandy' ),
		);
		return $macros;
	}

	/**
	 * Register settings sections and fields with the WordPress Settings API.
	 */
	public function register_settings() {
		$page = 'stackboost-utm';

		add_settings_section(
			'stackboost_utm_main_section',
			__( 'General Settings', 'stackboost-for-supportcandy' ),
			null,
			$page
		);

		add_settings_field(
			'stackboost_utm_enabled',
			__( 'Enable Feature', 'stackboost-for-supportcandy' ),
			[ $this, 'render_enable_checkbox' ],
			$page,
			'stackboost_utm_main_section'
		);

		add_settings_section(
			'stackboost_utm_fields_section',
			__( 'Fields to Display', 'stackboost-for-supportcandy' ),
			null,
			$page
		);

		add_settings_field(
			'stackboost_utm_columns',
			__( 'Fields', 'stackboost-for-supportcandy' ),
			[ $this, 'render_fields_selector' ],
			$page,
			'stackboost_utm_fields_section'
		);

		add_settings_field(
			'stackboost_utm_use_sc_order',
			__( 'Field Order', 'stackboost-for-supportcandy' ),
			[ $this, 'render_use_sc_order_checkbox' ],
			$page,
			'stackboost_utm_fields_section'
		);

		add_settings_section(
			'stackboost_utm_rename_section',
			__( 'Rename Field Titles', 'stackboost-for-supportcandy' ),
			null,
			$page
		);

		add_settings_field(
			'stackboost_utm_rename_rules',
			__( 'Renaming Rules', 'stackboost-for-supportcandy' ),
			[ $this, 'render_rules_builder' ],
			$page,
			'stackboost_utm_rename_section'
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
			\STACKBOOST_VERSION, // Corrected: Use the global constant.
			true
		);

		wp_enqueue_style(
			'stackboost-admin-utm',
			$plugin_url . 'assets/admin/css/utm-admin.css',
			[],
			\STACKBOOST_VERSION // Corrected: Use the global constant.
		);
	}

	/**
	 * Render the main settings page.
	 */
	public function render_settings_page() {
        $theme_class = 'sb-theme-clean-tech';
        if ( class_exists( '\StackBoost\ForSupportCandy\Modules\Appearance\WordPress' ) ) {
            $theme_class = \StackBoost\ForSupportCandy\Modules\Appearance\WordPress::get_active_theme_class();
        }
		?>
		<div class="wrap stackboost-dashboard <?php echo esc_attr( $theme_class ); ?>">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form method="post" action="options.php">
				<?php
				// This will output the nonces and other fields for the 'stackboost_settings' group.
				settings_fields( 'stackboost_settings' );
				// Add the hidden page slug field, which is critical for the central sanitizer.
				echo '<input type="hidden" name="stackboost_settings[page_slug]" value="stackboost-utm">';
				?>

				<div class="stackboost-dashboard-grid">
					<!-- Card 1: General Settings & Fields -->
					<div class="stackboost-card">
						<h2><?php esc_html_e( 'General Settings & Fields', 'stackboost-for-supportcandy' ); ?></h2>
						<table class="form-table">
							<?php
							do_settings_fields( 'stackboost-utm', 'stackboost_utm_main_section' );
							do_settings_fields( 'stackboost-utm', 'stackboost_utm_fields_section' );
							?>
						</table>
					</div>

					<!-- Card 2: Rename Rules -->
					<div class="stackboost-card">
						<h2><?php esc_html_e( 'Rename Field Titles', 'stackboost-for-supportcandy' ); ?></h2>
						<table class="form-table">
							<?php
							do_settings_fields( 'stackboost-utm', 'stackboost_utm_rename_section' );
							?>
						</table>
					</div>
				</div>

				<?php submit_button(); ?>
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
				<select multiple id="stackboost_utm_available_fields" size="10" class="stackboost-utm-multiselect">
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
					<select multiple name="stackboost_settings[utm_columns][]" id="stackboost_utm_selected_fields" size="10" class="stackboost-utm-multiselect">
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
				foreach ( $rename_rules as $index => $rule ) :
					?>
					<div class="stackboost-utm-rule-row">
						<span><?php esc_html_e( 'Display', 'stackboost-for-supportcandy' ); ?></span>
						<select name="stackboost_settings[utm_rename_rules][<?php echo (int) $index; ?>][field]" class="stackboost-utm-rule-field">
							<?php foreach ( $all_columns as $slug => $name ) : ?>
								<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $slug, $rule['field'] ); ?>><?php echo esc_html( $name ); ?></option>
							<?php endforeach; ?>
						</select>
						<span><?php esc_html_e( 'as', 'stackboost-for-supportcandy' ); ?></span>
						<input type="text" name="stackboost_settings[utm_rename_rules][<?php echo (int) $index; ?>][name]" class="stackboost-utm-rule-name" value="<?php echo esc_attr( $rule['name'] ); ?>" />
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
				<select name="stackboost_settings[utm_rename_rules][__INDEX__][field]" class="stackboost-utm-rule-field">
					<?php foreach ( $all_columns as $slug => $name ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $name ); ?></option>
					<?php endforeach; ?>
				</select>
				<span><?php esc_html_e( 'as', 'stackboost-for-supportcandy' ); ?></span>
				<input type="text" name="stackboost_settings[utm_rename_rules][__INDEX__][name]" class="stackboost-utm-rule-name" value="" />
				<button type="button" class="button stackboost-utm-remove-rule" title="<?php esc_attr_e( 'Remove Rule', 'stackboost-for-supportcandy' ); ?>"><span class="dashicons dashicons-trash"></span></button>
			</div>
		</script>
		<?php
	}

	/**
	 * Wrapper functions to call the central logger.
	 */
	public function log_wpsc_create_new_ticket( $ticket ) {
		try {
			if ( ! is_a( $ticket, 'WPSC_Ticket' ) ) {
				\stackboost_log(
					array(
						'message' => '[UTM HOOK WARNING] wpsc_create_new_ticket: $ticket is not a WPSC_Ticket object.',
						'ticket'  => $ticket,
					),
					'utm-hook-create-ticket'
				);
				return;
			}
			\stackboost_log(
				array(
					'message' => '[UTM HOOK FIRED] wpsc_create_new_ticket',
					'ticket'  => $ticket,
				),
				'utm-hook-create-ticket'
			);
		} catch ( \Throwable $e ) {
			\stackboost_log(
				array(
					'message'   => '[UTM HOOK FATAL ERROR] wpsc_create_new_ticket: An error occurred.',
					'error'     => $e->getMessage(),
					'file'      => $e->getFile(),
					'line'      => $e->getLine(),
				),
				'utm-hook-create-ticket'
			);
		}
	}

	public function log_wpsc_post_reply( $thread ) {
		\stackboost_log(
			array(
				'message' => '[UTM HOOK FIRED] wpsc_post_reply',
				'thread'  => $thread,
				'ticket'  => $thread->ticket,
			),
			'utm-hook-post-reply'
		);
	}

	public function log_wpsc_submit_note( $thread ) {
		\stackboost_log(
			array(
				'message' => '[UTM HOOK FIRED] wpsc_submit_note',
				'thread'  => $thread,
				'ticket'  => $thread->ticket,
			),
			'utm-hook-submit-note'
		);
	}

	public function log_wpsc_change_assignee( $ticket, $prev, $new, $customer_id ) {
		\stackboost_log(
			array(
				'message'     => '[UTM HOOK FIRED] wpsc_change_assignee',
				'ticket'      => $ticket,
				'prev'        => $prev,
				'new'         => $new,
				'customer_id' => $customer_id,
			),
			'utm-hook-change-assignee'
		);
	}

	public function log_wpsc_change_ticket_status( $ticket, $prev, $new, $customer_id ) {
		\stackboost_log(
			array(
				'message'     => '[UTM HOOK FIRED] wpsc_change_ticket_status',
				'ticket'      => $ticket,
				'prev'        => $prev,
				'new'         => $new,
				'customer_id' => $customer_id,
			),
			'utm-hook-change-status'
		);
	}

	public function log_wpsc_change_ticket_priority( $ticket, $prev, $new, $customer_id ) {
		\stackboost_log(
			array(
				'message'     => '[UTM HOOK FIRED] wpsc_change_ticket_priority',
				'ticket'      => $ticket,
				'prev'        => $prev,
				'new'         => $new,
				'customer_id' => $customer_id,
			),
			'utm-hook-change-priority'
		);
	}

	public function log_wpsc_delete_ticket( $ticket ) {
		\stackboost_log(
			array(
				'message' => '[UTM HOOK FIRED] wpsc_delete_ticket',
				'ticket'  => $ticket,
			),
			'utm-hook-delete-ticket'
		);
	}
}
