<?php

namespace StackBoost\ForSupportCandy\Modules\TicketGuard;

if ( ! defined( 'ABSPATH' ) ) exit;

use StackBoost\ForSupportCandy\Core\Module;
use StackBoost\ForSupportCandy\Core\Request;

/**
 * WordPress Adapter for Ticket Guard.
 * Handles hooks, scripts, AJAX endpoints, and admin settings page.
 *
 * @package StackBoost\ForSupportCandy\Modules\TicketGuard
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
	 *
	 * @return string
	 */
	public function get_slug(): string {
		return 'ticket_guard';
	}

	/**
	 * Initialize hooks.
	 */
	public function init_hooks() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_scripts' ] );

		// Security Enforcement Hook (Backend)
		add_filter( 'wpsc_create_ticket_data', [ $this, 'enforce_backend_rules' ], 20, 1 );
	}

	/**
	 * Render the administration page for Ticket Guard.
	 */
	public function render_page() {
		if ( ! current_user_can( STACKBOOST_CAP_MANAGE_TICKET_GUARD ) ) {
			return;
		}

		$core       = Core::get_instance();
		$is_enabled = $core->is_enabled();
		$rules      = $core->get_rules();

		$theme_class = 'sb-theme-clean-tech';
		if ( class_exists( 'StackBoost\ForSupportCandy\Modules\Appearance\WordPress' ) ) {
			$theme_class = \StackBoost\ForSupportCandy\Modules\Appearance\WordPress::get_active_theme_class();
		}
		?>
		<div class="wrap stackboost-dashboard <?php echo esc_attr( $theme_class ); ?>">
			<h1><?php esc_html_e( 'Ticket Guard', 'stackboost-for-supportcandy' ); ?></h1>
			<p><?php esc_html_e( 'Rules-based intake optimization engine to steer users toward appropriate ticket categories and eliminate generic "Other" submissions.', 'stackboost-for-supportcandy' ); ?></p>

			<div id="stackboost-ticket-guard-app">
				<!-- Global Enable/Disable Toggle -->
				<div class="stackboost-card" style="margin-bottom: 20px;">
					<div class="pm-header">
						<h2 style="margin-bottom: 15px;"><?php esc_html_e( 'Feature Status', 'stackboost-for-supportcandy' ); ?></h2>
					</div>
					<div style="display: flex; align-items: center; gap: 15px;">
						<label class="switch">
							<input type="checkbox" id="stackboost_tg_enabled" <?php checked( $is_enabled, true ); ?>>
							<span class="slider round"></span>
						</label>
						<div>
							<strong><?php esc_html_e( 'Enable Ticket Guard Engine', 'stackboost-for-supportcandy' ); ?></strong>
							<p class="description" style="margin: 5px 0 0;"><?php esc_html_e( 'Toggle this feature on or off globally. Rules will not be enforced when disabled.', 'stackboost-for-supportcandy' ); ?></p>
						</div>
					</div>
				</div>

				<!-- Rules Card -->
				<div id="stackboost-tg-rules-card" class="stackboost-card <?php echo $is_enabled ? '' : 'stackboost-disabled-ui'; ?>">
					<div class="pm-header" style="display: flex; justify-content: space-between; align-items: center;">
						<h2><?php esc_html_e( 'Intake Steering Rules', 'stackboost-for-supportcandy' ); ?></h2>
					</div>

					<p class="description" style="margin-bottom: 15px;">
						<?php esc_html_e( 'Configure keyword monitoring rules on text fields to steer users toward specific categories with real-time warnings, modals, submit button disabling, or category swaps.', 'stackboost-for-supportcandy' ); ?>
					</p>

					<div class="pm-rules-wrapper" style="margin-top: 15px;">
						<table class="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Rule Name', 'stackboost-for-supportcandy' ); ?></th>
									<th><?php esc_html_e( 'Monitored Fields', 'stackboost-for-supportcandy' ); ?></th>
									<th><?php esc_html_e( 'Keywords / Phrases', 'stackboost-for-supportcandy' ); ?></th>
									<th><?php esc_html_e( 'Actions Enabled', 'stackboost-for-supportcandy' ); ?></th>
									<th style="width: 100px; text-align: right;"><?php esc_html_e( 'Status', 'stackboost-for-supportcandy' ); ?></th>
								</tr>
							</thead>
							<tbody id="pm-tg-rules-table-body">
								<?php if ( empty( $rules ) ) : ?>
									<tr>
										<td colspan="5" style="text-align: center; padding: 20px; font-style: italic;">
											<?php esc_html_e( 'No intake steering rules configured yet. Rules can be added using the StackBoost Rule Builder.', 'stackboost-for-supportcandy' ); ?>
										</td>
									</tr>
								<?php else : ?>
									<?php foreach ( $rules as $rule ) : ?>
										<tr>
											<td><strong><?php echo esc_html( $rule['name'] ?? __( 'Unnamed Rule', 'stackboost-for-supportcandy' ) ); ?></strong></td>
											<td><?php echo esc_html( implode( ', ', (array) ( $rule['monitored_fields'] ?? [] ) ) ); ?></td>
											<td><?php echo esc_html( implode( ', ', (array) ( $rule['keywords'] ?? [] ) ) ); ?></td>
											<td>
												<?php
												$active_actions = [];
												if ( ! empty( $rule['actions']['disable_submit'] ) ) $active_actions[] = __( 'Disable Submit', 'stackboost-for-supportcandy' );
												if ( ! empty( $rule['actions']['show_modal'] ) ) $active_actions[] = __( 'Modal Guidance', 'stackboost-for-supportcandy' );
												if ( ! empty( $rule['actions']['show_inline_warning'] ) ) $active_actions[] = __( 'Inline Warning', 'stackboost-for-supportcandy' );
												if ( ! empty( $rule['actions']['auto_swap_category'] ) ) $active_actions[] = __( 'Category Swap', 'stackboost-for-supportcandy' );
												echo esc_html( implode( ' | ', $active_actions ) );
												?>
											</td>
											<td style="text-align: right;">
												<span class="dashicons <?php echo ! empty( $rule['enabled'] ) ? 'dashicons-yes-alt' : 'dashicons-minus'; ?>" style="color: <?php echo ! empty( $rule['enabled'] ) ? 'green' : '#666'; ?>;"></span>
											</td>
										</tr>
									<?php endforeach; ?>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Enqueue Frontend Script for real-time monitoring and category steering.
	 */
	public function enqueue_frontend_scripts() {
		$core = Core::get_instance();
		if ( ! $core->is_enabled() ) {
			return;
		}

		wp_register_style(
			'stackboost-tg-frontend-css',
			STACKBOOST_PLUGIN_URL . 'src/Modules/TicketGuard/assets/css/frontend-guard.css',
			[],
			STACKBOOST_VERSION
		);

		wp_register_script(
			'stackboost-tg-frontend',
			STACKBOOST_PLUGIN_URL . 'src/Modules/TicketGuard/assets/js/frontend-guard.js',
			[ 'jquery', 'stackboost-util' ],
			STACKBOOST_VERSION,
			true
		);

		wp_enqueue_style( 'stackboost-tg-frontend-css' );

		wp_localize_script( 'stackboost-tg-frontend', 'stackboostTicketGuard', [
			'enabled' => true,
			'rules'   => $core->get_rules(),
			'i18n'    => [
				'notice_title'   => __( 'Category Guidance', 'stackboost-for-supportcandy' ),
				'change_category' => __( 'Switch Category', 'stackboost-for-supportcandy' ),
				'dismiss'         => __( 'Dismiss', 'stackboost-for-supportcandy' ),
			]
		] );

		wp_enqueue_script( 'stackboost-tg-frontend' );
	}

	/**
	 * Backend Enforcement Hook callback.
	 *
	 * @param array $ticket_data
	 * @return array
	 */
	public function enforce_backend_rules( array $ticket_data ): array {
		return Core::get_instance()->evaluate_submission_rules( $ticket_data );
	}
}
