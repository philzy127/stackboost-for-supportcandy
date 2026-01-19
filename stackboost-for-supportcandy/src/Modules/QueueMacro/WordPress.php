<?php


namespace StackBoost\ForSupportCandy\Modules\QueueMacro;

if ( ! defined( 'ABSPATH' ) ) exit;

use StackBoost\ForSupportCandy\Core\Module;
use StackBoost\ForSupportCandy\Core\Request;
use StackBoost\ForSupportCandy\Modules\QueueMacro\Core as QueueMacroCore;
use StackBoost\ForSupportCandy\WordPress\Plugin;
use StackBoost\ForSupportCandy\Integration\SupportCandyRepository;

/**
 * WordPress Adapter for the Queue Macro feature.
 *
 * @package StackBoost\ForSupportCandy\Modules\QueueMacro
 */
class WordPress extends Module {

	/** @var WordPress|null */
	private static ?WordPress $instance = null;

	/** @var QueueMacroCore */
	private QueueMacroCore $core;

	/** @var SupportCandyRepository */
	private SupportCandyRepository $sc_repository;

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
		if ( ! stackboost_is_feature_active( 'queue_macro' ) ) {
			return;
		}

		$this->core = new QueueMacroCore();
		$this->sc_repository = new SupportCandyRepository();
		parent::__construct();
	}

	/**
	 * Get the slug for this module.
	 * @return string
	 */
	public function get_slug(): string {
		return 'queue_macro';
	}

	/**
	 * Initialize hooks.
	 */
	public function init_hooks() {
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_filter( 'wpsc_macros', [ $this, 'register_macro' ] );
		add_filter( 'wpsc_create_ticket_email_data', [ $this, 'replace_macro_in_email' ], 10, 2 );
		add_action( 'wp_ajax_stackboost_test_queue_counts', [ $this, 'ajax_test_queue_counts' ] );
	}

	/**
	 * Register the {{queue_count}} macro with SupportCandy.
	 *
	 * @param array $macros
	 * @return array
	 */
	public function register_macro( array $macros ): array {
		$macros[] = [
			'tag'   => '{{queue_count}}',
			'title' => esc_attr__( 'Queue Count', 'stackboost-for-supportcandy' ),
		];
		return $macros;
	}

	/**
	 * Replace the macro in the new ticket email content.
	 *
	 * @param array $data   Email data.
	 * @param object $thread The ticket thread object.
	 * @return array
	 */
	public function replace_macro_in_email( array $data, object $thread ): array {
		if ( strpos( $data['body'], '{{queue_count}}' ) === false ) {
			return $data;
		}

		global $wpdb;
		$options    = get_option( 'stackboost_settings', [] );
		$type_field = $options['queue_macro_type_field'] ?? '';
		$statuses   = $options['queue_macro_statuses'] ?? [];

		// Get the type value from the submitted form data.
		$type_value = Request::get_post( $type_field, '', 'text' );

		stackboost_log( "QueueMacro: Calculating count for field '{$type_field}' with value '{$type_value}'.", 'queue_macro' );

		$count = $this->core->calculate_queue_count( $wpdb, $type_field, $type_value, $statuses );

		stackboost_log( "QueueMacro: Calculated count is {$count}. Replacing macro.", 'queue_macro' );

		$data['body'] = str_replace( '{{queue_count}}', (string) $count, $data['body'] );

		return $data;
	}

	/**
	 * AJAX handler for testing and displaying queue counts.
	 */
	public function ajax_test_queue_counts() {
		check_ajax_referer( 'stackboost_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'stackboost-for-supportcandy' ) );
		}

		global $wpdb;
		$options    = get_option( 'stackboost_settings', [] );
		$type_field = $options['queue_macro_type_field'] ?? '';
		$statuses   = $options['queue_macro_statuses'] ?? [];

		if ( empty( $type_field ) ) {
			wp_send_json_error( __( 'No "Ticket Type Field" is configured in settings.', 'stackboost-for-supportcandy' ) );
		}
		if ( empty( $statuses ) ) {
			wp_send_json_error( __( 'No "Non-Closed Statuses" are configured in settings.', 'stackboost-for-supportcandy' ) );
		}

		$results = $this->core->get_all_queue_counts( $wpdb, $type_field, $statuses );

		wp_send_json_success( $results );
	}

	/**
	 * Register settings fields for this module.
	 */
	public function register_settings() {
		$page_slug = 'stackboost-queue-macro';

		add_settings_section(
			'stackboost_queue_macro_section',
			__( 'Queue Macro Settings', 'stackboost-for-supportcandy' ),
			null,
			$page_slug
		);

		add_settings_field(
			'stackboost_enable_queue_macro',
			__( 'Enable Feature', 'stackboost-for-supportcandy' ),
			[ $this, 'render_checkbox_field' ],
			$page_slug,
			'stackboost_queue_macro_section',
			[
				'id'   => 'enable_queue_macro',
				'desc' => __( 'Adds a {{queue_count}} macro to show customers their queue position.', 'stackboost-for-supportcandy' ),
			]
		);

        // Prepare choices for the type field dropdown.
        $plugin_instance = Plugin::get_instance();
        $custom_fields = $plugin_instance->get_supportcandy_columns();
		$default_fields    = [
			'category' => __( 'Category', 'stackboost-for-supportcandy' ),
			'priority' => __( 'Priority', 'stackboost-for-supportcandy' ),
			'status'   => __( 'Status', 'stackboost-for-supportcandy' ),
		];
		$all_type_fields = array_merge( $default_fields, $custom_fields );
		asort( $all_type_fields );

		add_settings_field(
			'stackboost_queue_macro_type_field',
			__( 'Ticket Type Field', 'stackboost-for-supportcandy' ),
			[ $this, 'render_select_field' ],
			$page_slug,
			'stackboost_queue_macro_section',
			[
				'id'      => 'queue_macro_type_field',
				'choices' => $all_type_fields,
				'desc'    => __( 'The field that distinguishes your queues (e.g., category, priority).', 'stackboost-for-supportcandy' ),
			]
		);

		add_settings_field(
			'stackboost_queue_macro_statuses',
			__( 'Non-Closed Statuses', 'stackboost-for-supportcandy' ),
			[ $this, 'render_statuses_dual_list_field' ],
			$page_slug,
			'stackboost_queue_macro_section',
			[
				'id' => 'queue_macro_statuses',
				'desc' => __( 'Select which ticket statuses should count toward the queue.', 'stackboost-for-supportcandy' )
			]
		);
		add_settings_field( 'stackboost_queue_macro_test', __( 'Test Queue Counts', 'stackboost-for-supportcandy' ), [ $this, 'render_test_button_field' ], $page_slug, 'stackboost_queue_macro_section' );
	}

	/**
	 * Render the administration page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Get active theme class
		$theme_class = 'sb-theme-clean-tech'; // Default
		if ( class_exists( 'StackBoost\ForSupportCandy\Modules\Appearance\WordPress' ) ) {
			$theme_class = \StackBoost\ForSupportCandy\Modules\Appearance\WordPress::get_active_theme_class();
		}

		// Prepare data for the select field (duplicated logic from register_settings, ideal for refactor but kept inline for safety)
        $plugin_instance = Plugin::get_instance();
        $custom_fields = $plugin_instance->get_supportcandy_columns();
		$default_fields    = [
			'category' => __( 'Category', 'stackboost-for-supportcandy' ),
			'priority' => __( 'Priority', 'stackboost-for-supportcandy' ),
			'status'   => __( 'Status', 'stackboost-for-supportcandy' ),
		];
		$all_type_fields = array_merge( $default_fields, $custom_fields );
		asort( $all_type_fields );

		?>
		<!-- StackBoost Wrapper Start -->
		<!-- Theme: <?php echo esc_html( $theme_class ); ?> -->
		<div class="wrap stackboost-dashboard <?php echo esc_attr( $theme_class ); ?>">
			<h1><?php esc_html_e( 'Queue Macro', 'stackboost-for-supportcandy' ); ?></h1>
			<?php settings_errors(); ?>

			<form action="options.php" method="post">
				<?php
				settings_fields( 'stackboost_settings' );
				echo '<input type="hidden" name="stackboost_settings[page_slug]" value="' . esc_attr( 'stackboost-queue-macro' ) . '">';
				?>

				<div class="stackboost-dashboard-grid">
					<!-- Card 1: Queue Configuration -->
					<div class="stackboost-card">
						<h2><?php esc_html_e( 'Queue Configuration', 'stackboost-for-supportcandy' ); ?></h2>
						<p><?php esc_html_e( 'Configure how the queue position is calculated for the {{queue_count}} macro.', 'stackboost-for-supportcandy' ); ?></p>

						<table class="form-table">
							<tr>
								<th><?php esc_html_e( 'Enable Feature', 'stackboost-for-supportcandy' ); ?></th>
								<td><?php $this->render_checkbox_field( [ 'id' => 'enable_queue_macro', 'desc' => 'Adds a {{queue_count}} macro to show customers their queue position.' ] ); ?></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Ticket Type Field', 'stackboost-for-supportcandy' ); ?></th>
								<td><?php $this->render_select_field( [ 'id' => 'queue_macro_type_field', 'choices' => $all_type_fields, 'desc' => 'The field that distinguishes your queues (e.g., category, priority).', 'stackboost-for-supportcandy' ); ?></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Non-Closed Statuses', 'stackboost-for-supportcandy' ); ?></th>
								<td><?php $this->render_statuses_dual_list_field( [ 'id' => 'queue_macro_statuses', 'desc' => 'Select which ticket statuses should count toward the queue.' ] ); ?></td>
							</tr>
							<tr>
								<th><?php esc_html_e( 'Test Queue Counts', 'stackboost-for-supportcandy' ); ?></th>
								<td><?php $this->render_test_button_field(); ?></td>
							</tr>
						</table>
					</div>
				</div>

				<?php submit_button( __( 'Save Settings', 'stackboost-for-supportcandy' ) ); ?>
			</form>
		</div>
		<?php
	}

    /**
     * Render the dual list for statuses.
     * @param array $args
     */
    public function render_statuses_dual_list_field( array $args ) {
        $options           = get_option( 'stackboost_settings', [] );
        $selected_statuses = isset( $options[ $args['id'] ] ) ? $options[ $args['id'] ] : [];

        $all_statuses = $this->sc_repository->get_statuses();

        $available_statuses_map = [];
        $selected_statuses_map  = [];

        if ( $all_statuses ) {
            foreach ( $all_statuses as $status ) {
                if ( in_array( (int) $status->id, $selected_statuses, true ) ) {
                    $selected_statuses_map[ $status->id ] = $status->name;
                } else {
                    $available_statuses_map[ $status->id ] = $status->name;
                }
            }
        }
        ?>
        <div class="stackboost-dual-list-container">
            <div class="dual-list-box">
                <h3><?php esc_html_e( 'Available Statuses', 'stackboost-for-supportcandy' ); ?></h3>
                <select multiple id="stackboost_available_statuses" size="8">
                    <?php foreach ( $available_statuses_map as $id => $name ) : ?>
                        <option value="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $name ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="dual-buttons">
                <button type="button" class="button" id="stackboost_add_status"><span class="dashicons dashicons-arrow-right"></span></button>
                <button type="button" class="button" id="stackboost_remove_status"><span class="dashicons dashicons-arrow-left"></span></button>
            </div>
            <div class="dual-list-box">
                <h3><?php esc_html_e( 'Selected Statuses', 'stackboost-for-supportcandy' ); ?></h3>
                <select multiple name="stackboost_settings[<?php echo esc_attr( $args['id'] ); ?>][]" id="stackboost_selected_statuses" size="8">
                    <?php foreach ( $selected_statuses_map as $id => $name ) : ?>
                        <option value="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $name ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <p class="description"><?php echo esc_html( $args['desc'] ); ?></p>
        <?php
    }

    /**
     * Render the test button for queue macro.
     */
    public function render_test_button_field() {
        ?>
        <p><?php esc_html_e( 'Click the button to see the current queue counts based on your saved settings.', 'stackboost-for-supportcandy' ); ?></p>
        <p>
            <button type="button" id="stackboost_test_queue_macro_button" class="button"><?php esc_html_e( 'Run Test', 'stackboost-for-supportcandy' ); ?></button>
        </p>
        <div id="stackboost_test_results" style="display:none;">
            <h4><?php esc_html_e( 'Test Results', 'stackboost-for-supportcandy' ); ?></h4>
            <div id="stackboost_test_results_content"></div>
        </div>
        <?php
    }
}
