<?php

namespace StackBoost\ForSupportCandy\Modules\ContextualViews;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StackBoost\ForSupportCandy\Core\Module;
use StackBoost\ForSupportCandy\WordPress\Plugin;

/**
 * WordPress Adapter for the Contextual Views (Revamp) feature.
 *
 * @package StackBoost\ForSupportCandy\Modules\ContextualViews
 */
class WordPress extends Module {

	/** @var WordPress|null */
	private static ?WordPress $instance = null;

	/** @var Core */
	private Core $core;

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
		// We might want to check a feature flag, but for now we load it.
		// if ( ! stackboost_is_feature_active( 'contextual_views' ) ) return;

		$this->core = new Core();
		parent::__construct();
	}

	/**
	 * Get the slug for this module.
	 * @return string
	 */
	public function get_slug(): string {
		return 'contextual_views';
	}

	/**
	 * Initialize hooks.
	 */
	public function init_hooks() {
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_menu', [ $this, 'register_menu_page' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
        add_action( 'wp_ajax_stackboost_save_contextual_rule', [ $this, 'ajax_save_rule' ] );
        add_action( 'wp_ajax_stackboost_delete_contextual_rule', [ $this, 'ajax_delete_rule' ] );
        add_action( 'wp_ajax_stackboost_migrate_contextual_rules', [ $this, 'ajax_migrate_rules' ] );
	}

    /**
     * Register the admin menu page.
     */
    public function register_menu_page() {
        add_submenu_page(
            'stackboost-for-supportcandy',
            __( 'Contextual Views', 'stackboost-for-supportcandy' ),
            __( 'Contextual Views', 'stackboost-for-supportcandy' ),
            'manage_options', // TODO: Use capability
            'stackboost-contextual-views',
            [ $this, 'render_page' ]
        );
    }

	/**
	 * Register settings fields (if needed, but we are using custom UI).
	 */
	public function register_settings() {
		// Basic settings registration if we use options.php, but we might use AJAX for the list.
	}

    /**
     * Enqueue Admin Scripts.
     *
     * @param string $hook
     */
    public function enqueue_admin_scripts( $hook ) {
        if ( 'stackboost-for-supportcandy_page_stackboost-contextual-views' !== $hook ) {
            return;
        }

        wp_enqueue_script(
            'stackboost-contextual-views-admin',
            STACKBOOST_PLUGIN_URL . 'assets/admin/js/contextual-views-admin.js',
            [ 'jquery', 'stackboost-util' ],
            STACKBOOST_VERSION,
            true
        );

        wp_enqueue_style(
            'stackboost-contextual-views-admin',
            STACKBOOST_PLUGIN_URL . 'assets/admin/css/contextual-views-admin.css',
            [],
            STACKBOOST_VERSION
        );

        wp_localize_script( 'stackboost-contextual-views-admin', 'sb_cv_admin', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'sb_cv_nonce' ),
        ]);
    }

	/**
	 * Render the administration page.
	 */
	public function render_page() {
		// Get active theme class
		$theme_class = 'sb-theme-clean-tech';
		if ( class_exists( 'StackBoost\ForSupportCandy\Modules\Appearance\WordPress' ) ) {
			$theme_class = \StackBoost\ForSupportCandy\Modules\Appearance\WordPress::get_active_theme_class();
		}

		?>
		<div class="wrap stackboost-dashboard <?php echo esc_attr( $theme_class ); ?>">
			<h1><?php esc_html_e( 'Contextual Ticket Views', 'stackboost-for-supportcandy' ); ?></h1>

            <div class="stackboost-dashboard-grid">
                <div class="stackboost-card">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <h2><?php esc_html_e( 'Defined Workspaces', 'stackboost-for-supportcandy' ); ?></h2>
                        <div>
                            <button class="button" id="sb-cv-migrate" style="margin-right: 10px;"><?php esc_html_e( 'Migrate Legacy Rules', 'stackboost-for-supportcandy' ); ?></button>
                            <button class="button button-primary" id="sb-cv-add-rule"><?php esc_html_e( 'Add New Workspace', 'stackboost-for-supportcandy' ); ?></button>
                        </div>
                    </div>
                    <p><?php esc_html_e( 'Define specific column layouts (workspaces) for different SupportCandy views.', 'stackboost-for-supportcandy' ); ?></p>

                    <table class="wp-list-table widefat fixed striped table-view-list" id="sb-cv-rules-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'View Name', 'stackboost-for-supportcandy' ); ?></th>
                                <th><?php esc_html_e( 'Active Columns', 'stackboost-for-supportcandy' ); ?></th>
                                <th><?php esc_html_e( 'Status', 'stackboost-for-supportcandy' ); ?></th>
                                <th><?php esc_html_e( 'Actions', 'stackboost-for-supportcandy' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $this->render_rules_table_rows(); ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php $this->render_modal_template(); ?>
		</div>
		<?php
	}

    private function render_rules_table_rows() {
        $rules = $this->core->get_rules();
        if ( empty( $rules ) ) {
            echo '<tr><td colspan="4">' . esc_html__( 'No workspaces defined.', 'stackboost-for-supportcandy' ) . '</td></tr>';
            return;
        }

        $views = $this->get_supportcandy_views();

        foreach ( $rules as $id => $rule ) {
            $view_name = $views[$rule['view_id']] ?? $rule['view_id'];
            $col_count = count( $rule['columns'] ?? [] );

            echo '<tr data-id="' . esc_attr( $id ) . '">';
            echo '<td>' . esc_html( $view_name ) . '</td>';
            echo '<td>' . esc_html( $col_count . ' columns' ) . '</td>';
            echo '<td><span class="dashicons dashicons-yes" style="color: green;"></span> ' . esc_html__( 'Active', 'stackboost-for-supportcandy' ) . '</td>';
            echo '<td>';
            echo '<button class="button sb-cv-edit-rule" data-rule=\'' . json_encode( $rule ) . '\'>' . esc_html__( 'Edit', 'stackboost-for-supportcandy' ) . '</button> ';
            echo '<button class="button sb-cv-delete-rule" data-id="' . esc_attr( $id ) . '">' . esc_html__( 'Delete', 'stackboost-for-supportcandy' ) . '</button>';
            echo '</td>';
            echo '</tr>';
        }
    }

    private function render_modal_template() {
        $all_columns = Plugin::get_instance()->get_supportcandy_columns();
        $views = $this->get_supportcandy_views();
        ?>
        <div id="sb-cv-modal" style="display:none;">
            <div class="sb-cv-modal-content">
                <input type="hidden" id="sb-cv-rule-id" value="">

                <div class="sb-form-group">
                    <label for="sb-cv-view-selector"><strong><?php esc_html_e( 'Select View', 'stackboost-for-supportcandy' ); ?></strong></label>
                    <select id="sb-cv-view-selector" style="width: 100%;">
                        <option value=""><?php esc_html_e( '-- Select a View --', 'stackboost-for-supportcandy' ); ?></option>
                        <?php foreach ( $views as $id => $label ) : ?>
                            <option value="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="sb-cv-dual-list-box">
                    <div class="sb-cv-box">
                        <h4><?php esc_html_e( 'Available Fields', 'stackboost-for-supportcandy' ); ?></h4>
                        <select multiple id="sb-cv-available-fields" class="sb-cv-select" size="10">
                            <?php foreach ( $all_columns as $slug => $label ) : ?>
                                <option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="sb-cv-controls">
                         <button type="button" class="button" id="sb-cv-add-all">&gt;&gt;</button>
                         <button type="button" class="button" id="sb-cv-add">&gt;</button>
                         <button type="button" class="button" id="sb-cv-remove">&lt;</button>
                         <button type="button" class="button" id="sb-cv-remove-all">&lt;&lt;</button>
                    </div>

                    <div class="sb-cv-box">
                        <h4><?php esc_html_e( 'Selected Fields (Order Matters)', 'stackboost-for-supportcandy' ); ?></h4>
                        <select multiple id="sb-cv-selected-fields" class="sb-cv-select" size="10">
                            <!-- Populated via JS -->
                        </select>
                        <div class="sb-cv-order-controls">
                            <button type="button" class="button" id="sb-cv-move-up"><?php esc_html_e( 'Up', 'stackboost-for-supportcandy' ); ?></button>
                            <button type="button" class="button" id="sb-cv-move-down"><?php esc_html_e( 'Down', 'stackboost-for-supportcandy' ); ?></button>
                        </div>
                    </div>
                </div>

                <div class="sb-cv-modal-footer" style="margin-top: 20px; text-align: right;">
                    <button class="button button-primary" id="sb-cv-save-rule"><?php esc_html_e( 'Save Workspace', 'stackboost-for-supportcandy' ); ?></button>
                    <button class="button" id="sb-cv-cancel"><?php esc_html_e( 'Cancel', 'stackboost-for-supportcandy' ); ?></button>
                </div>
            </div>
        </div>
        <?php
    }

	/**
	 * Gets the list of available SupportCandy views/filters.
     * Reuse logic from ConditionalViews.
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

    /**
     * AJAX Handler to save a rule.
     */
    public function ajax_save_rule() {
        check_ajax_referer( 'sb_cv_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }

        $rule_id = isset( $_POST['rule_id'] ) ? sanitize_text_field( $_POST['rule_id'] ) : '';
        $view_id = isset( $_POST['view_id'] ) ? sanitize_text_field( $_POST['view_id'] ) : '';
        $columns = isset( $_POST['columns'] ) ? (array) $_POST['columns'] : [];

        if ( empty( $view_id ) ) {
            wp_send_json_error( 'View ID is required' );
        }

        $rules = $this->core->get_rules();

        if ( empty( $rule_id ) ) {
            $rule_id = uniqid( 'cv_' );
        }

        $rules[ $rule_id ] = [
            'id' => $rule_id,
            'view_id' => $view_id,
            'columns' => array_map( 'sanitize_text_field', $columns ),
        ];

        $this->core->save_rules( $rules );

        wp_send_json_success( [ 'message' => 'Workspace saved successfully.' ] );
    }

    /**
     * AJAX Handler to delete a rule.
     */
    public function ajax_delete_rule() {
        check_ajax_referer( 'sb_cv_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }

        $rule_id = isset( $_POST['rule_id'] ) ? sanitize_text_field( $_POST['rule_id'] ) : '';

        $rules = $this->core->get_rules();
        if ( isset( $rules[ $rule_id ] ) ) {
            unset( $rules[ $rule_id ] );
            $this->core->save_rules( $rules );
            wp_send_json_success( [ 'message' => 'Rule deleted.' ] );
        }

        wp_send_json_error( 'Rule not found.' );
    }

    /**
     * AJAX Handler to migrate legacy rules.
     */
    public function ajax_migrate_rules() {
        check_ajax_referer( 'sb_cv_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }

        $options = get_option( 'stackboost_settings', [] );
        $legacy_rules = $options['conditional_hiding_rules'] ?? [];

        if ( empty( $legacy_rules ) ) {
            wp_send_json_error( 'No legacy rules found.' );
        }

        $all_columns = array_keys( Plugin::get_instance()->get_supportcandy_columns() );
        $views_to_process = [];

        // Group hidden columns by view
        foreach ( $legacy_rules as $rule ) {
            if ( isset( $rule['view'], $rule['columns'] ) && ( $rule['action'] ?? 'hide' ) === 'hide' && ( $rule['condition'] ?? 'in_view' ) === 'in_view' ) {
                $view_id = $rule['view'];
                $col = $rule['columns'];
                if ( ! isset( $views_to_process[ $view_id ] ) ) {
                    $views_to_process[ $view_id ] = [];
                }
                $views_to_process[ $view_id ][] = $col;
            }
        }

        if ( empty( $views_to_process ) ) {
            wp_send_json_error( 'No compatible "Hide in View" rules found.' );
        }

        $new_rules = $this->core->get_rules();
        $count = 0;

        foreach ( $views_to_process as $view_id => $hidden_cols ) {
            // Calculate Visible Columns: All Columns - Hidden Columns
            $visible_cols = array_diff( $all_columns, $hidden_cols );

            $rule_id = uniqid( 'cv_mig_' );
            $new_rules[ $rule_id ] = [
                'id' => $rule_id,
                'view_id' => $view_id,
                'columns' => array_values( $visible_cols ),
            ];
            $count++;
        }

        $this->core->save_rules( $new_rules );
        wp_send_json_success( [ 'message' => sprintf( 'Migrated %d views successfully.', $count ) ] );
    }
}
