<?php

namespace StackBoost\ForSupportCandy\Modules\OnboardingDashboard\Admin;

class Page {

	/**
	 * Initialize the main Onboarding Dashboard page.
	 */
	public static function init() {
		add_action( 'admin_menu', [ __CLASS__, 'add_menu_page' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );
	}

	/**
	 * Add the submenu page.
	 */
	public static function add_menu_page() {
		add_submenu_page(
			'stackboost-for-supportcandy',
			__( 'Onboarding Dashboard', 'stackboost-for-supportcandy' ),
			__( 'Onboarding', 'stackboost-for-supportcandy' ),
			'manage_options', // Base capability
			'stackboost-onboarding-dashboard',
			[ __CLASS__, 'render_page' ]
		);
	}

	/**
	 * Enqueue general scripts for the page.
	 * Specific tabs may enqueue their own via their own classes.
	 */
	public static function enqueue_scripts( $hook ) {
		if ( 'stackboost-for-supportcandy_page_stackboost-onboarding-dashboard' !== $hook ) {
			return;
		}
		// Enqueue any shared assets here if needed.
	}

	/**
	 * Render the main page with tabs.
	 */
	public static function render_page() {
		$tabs = [
			'staff'    => __( 'Staff', 'stackboost-for-supportcandy' ),
			'sequence' => __( 'Sequence', 'stackboost-for-supportcandy' ),
			'settings' => __( 'Settings', 'stackboost-for-supportcandy' ),
			'search'   => __( 'Ticket Search', 'stackboost-for-supportcandy' ),
		];

		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'staff';

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Onboarding Dashboard', 'stackboost-for-supportcandy' ); ?></h1>
			<h2 class="nav-tab-wrapper">
				<?php
				foreach ( $tabs as $tab_id => $tab_name ) {
					$tab_url = add_query_arg(
						[
							'page' => 'stackboost-onboarding-dashboard',
							'tab'  => $tab_id,
						],
						admin_url( 'admin.php' )
					);
					$active  = ( $active_tab === $tab_id ) ? ' nav-tab-active' : '';
					echo '<a href="' . esc_url( $tab_url ) . '" class="nav-tab' . $active . '">' . esc_html( $tab_name ) . '</a>';
				}
				?>
			</h2>

			<div class="tab-content">
				<?php
				switch ( $active_tab ) {
					case 'staff':
						Staff::render_page();
						break;
					case 'sequence':
						Sequence::render_page();
						break;
					case 'settings':
						Settings::render_page();
						break;
					case 'search':
						TicketSearch::render_page();
						break;
					default:
						Staff::render_page();
						break;
				}
				?>
			</div>
		</div>
		<?php
	}
}
