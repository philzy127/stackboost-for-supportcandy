<?php

namespace StackBoost\ForSupportCandy\Modules\ConditionalViews;

use StackBoost\ForSupportCandy\Core\Module;
use StackBoost\ForSupportCandy\Modules\ConditionalViews\Core as ConditionalViewsCore;
use StackBoost\ForSupportCandy\WordPress\Plugin;

/**
 * WordPress Adapter for the Conditional Views feature.
 *
 * @package StackBoost\ForSupportCandy\Modules\ConditionalViews
 */
class WordPress extends Module {

	/** @var WordPress|null */
	private static ?WordPress $instance = null;

	/** @var ConditionalViewsCore */
	private ConditionalViewsCore $core;

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
		if ( ! stackboost_is_feature_active( 'conditional_views' ) ) {
			return;
		}

		$this->core = new ConditionalViewsCore();
		parent::__construct();
	}

	/**
	 * Get the slug for this module.
	 * @return string
	 */
	public function get_slug(): string {
		return 'conditional_views';
	}

	/**
	 * Initialize hooks.
	 */
	public function init_hooks() {
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	/**
	 * Register settings fields for this module.
	 */
	public function register_settings() {
		$page_slug = 'stackboost-conditional-views';

		add_settings_section(
			'stackboost_conditional_hiding_section',
			__( 'Conditional Column Hiding Rules', 'stackboost-for-supportcandy' ),
			[ $this, 'render_section_description' ],
			$page_slug
		);
		add_settings_field(
			'stackboost_enable_conditional_hiding',
			__( 'Enable Feature', 'stackboost-for-supportcandy' ),
			[ $this, 'render_checkbox_field' ],
			$page_slug,
			'stackboost_conditional_hiding_section',
			[ 'id' => 'enable_conditional_hiding', 'desc' => 'Enable the rule-based system to show or hide columns.' ]
		);
		add_settings_field(
			'stackboost_conditional_hiding_rules',
			__( 'Rules', 'stackboost-for-supportcandy' ),
			[ $this, 'render_rule_builder' ],
			$page_slug,
			'stackboost_conditional_hiding_section'
		);
	}

	/**
	 * Render the description for the section.
	 */
	public function render_section_description() {
		echo '<p>' . esc_html__( 'Create rules to show or hide columns based on the selected ticket view. This allows for powerful customization of the ticket list for different contexts.', 'stackboost-for-supportcandy' ) . '</p>';
	}

	/**
	 * Render the rule builder interface.
	 */
	public function render_rule_builder() {
		$options = get_option( 'stackboost_settings', [] );
		$rules   = $options['conditional_hiding_rules'] ?? [];
		$views   = $this->get_supportcandy_views();
        $plugin_instance = Plugin::get_instance();
		$columns = $plugin_instance->get_supportcandy_columns();
		?>
		<div id="stackboost-rules-container">
			<?php
			if ( ! empty( $rules ) ) {
				foreach ( $rules as $index => $rule ) {
					$this->render_rule_template( $index, $rule, $views, $columns );
				}
			} else {
				echo '<p id="stackboost-no-rules-message">' . esc_html__( 'No rules defined yet. Click "Add New Rule" to start.', 'stackboost-for-supportcandy' ) . '</p>';
			}
			?>
		</div>
		<button type="button" class="button" id="stackboost-add-rule"><?php esc_html_e( 'Add New Rule', 'stackboost-for-supportcandy' ); ?></button>

		<div class="stackboost-rule-template-wrapper" style="display: none;">
			<script type="text/template" id="stackboost-rule-template">
				<?php $this->render_rule_template( '__INDEX__', [], $views, $columns ); ?>
			</script>
		</div>
		<?php
	}

	/**
	 * Renders the HTML for a single rule row.
	 */
	private function render_rule_template( $index, $rule, $views, $columns ) {
		$action    = $rule['action'] ?? 'hide';
		$condition = $rule['condition'] ?? 'in_view';
		$view_id   = $rule['view'] ?? '';
		$selected_col = $rule['columns'] ?? '';
		?>
		<div class="stackboost-rule">
			<select name="stackboost_settings[conditional_hiding_rules][<?php echo esc_attr( $index ); ?>][action]">
				<option value="show" <?php selected( $action, 'show' ); ?>><?php esc_html_e( 'SHOW', 'stackboost-for-supportcandy' ); ?></option>
				<option value="show_only" <?php selected( $action, 'show_only' ); ?>><?php esc_html_e( 'SHOW ONLY', 'stackboost-for-supportcandy' ); ?></option>
				<option value="hide" <?php selected( $action, 'hide' ); ?>><?php esc_html_e( 'HIDE', 'stackboost-for-supportcandy' ); ?></option>
			</select>

			<select name="stackboost_settings[conditional_hiding_rules][<?php echo esc_attr( $index ); ?>][columns]" class="stackboost-rule-columns">
                <option value=""><?php _e('-- Select Column --', 'stackboost-for-supportcandy'); ?></option>
				<?php foreach ( $columns as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $selected_col, $key ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<select name="stackboost_settings[conditional_hiding_rules][<?php echo esc_attr( $index ); ?>][condition]">
				<option value="in_view" <?php selected( $condition, 'in_view' ); ?>><?php esc_html_e( 'WHEN IN VIEW', 'stackboost-for-supportcandy' ); ?></option>
				<option value="not_in_view" <?php selected( $condition, 'not_in_view' ); ?>><?php esc_html_e( 'WHEN NOT IN VIEW', 'stackboost-for-supportcandy' ); ?></option>
			</select>

			<select name="stackboost_settings[conditional_hiding_rules][<?php echo esc_attr( $index ); ?>][view]">
                <option value=""><?php _e('-- Select View --', 'stackboost-for-supportcandy'); ?></option>
				<?php foreach ( $views as $id => $name ) : ?>
					<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $view_id, $id ); ?>><?php echo esc_html( $name ); ?></option>
				<?php endforeach; ?>
			</select>
			<button type="button" class="button stackboost-remove-rule">&times;</button>
		</div>
		<?php
	}

	/**
	 * Gets the list of available SupportCandy views/filters.
	 */
	private function get_supportcandy_views(): array {
		$views = [ '0' => __( 'Default View (All Tickets)', 'stackboost-for-supportcandy' ) ];

		$raw_filters = get_option( 'wpsc-atl-default-filters' );
		if ( empty( $raw_filters ) ) {
			return $views;
		}

		$filter_data = maybe_unserialize( $raw_filters );
		if ( ! is_array( $filter_data ) ) {
			return $views;
		}

		foreach ( $filter_data as $id => $details ) {
			if ( ! empty( $details['is_enable'] ) && ! empty( $details['label'] ) ) {
				$views[ $id ] = $details['label'];
			}
		}

		return $views;
	}
}
