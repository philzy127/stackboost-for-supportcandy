<?php

namespace StackBoost\ForSupportCandy\Modules\OnboardingDashboard\Admin;

class Page {

	/**
	 * Initialize the main Onboarding Dashboard page.
	 */
	public static function init() {
		// Menu page is now registered centrally in Settings.php
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );
	}

	/**
	 * Enqueue general scripts for the page.
	 * Specific tabs may enqueue their own via their own classes.
	 */
	public static function enqueue_scripts( $hook ) {
        stackboost_log( "Onboarding Page Enqueue Scripts called for hook: " . $hook, 'onboarding' );
		if ( 'stackboost_page_stackboost-onboarding-dashboard' !== $hook ) {
			return;
		}
		// Enqueue any shared assets here if needed.
	}

	/**
	 * Render the main page with tabs.
	 */
	public static function render_page() {
		$tabs = [
			'steps'         => __( 'Onboarding Steps', 'stackboost-for-supportcandy' ),
			'staff'         => __( 'Staff', 'stackboost-for-supportcandy' ),
			'certificate'   => __( 'Certificate', 'stackboost-for-supportcandy' ),
			'settings'      => __( 'Settings', 'stackboost-for-supportcandy' ),
			'import_export' => __( 'Import / Export', 'stackboost-for-supportcandy' ),
		];

		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'steps';

        $theme_class = 'sb-theme-clean-tech';
        if ( class_exists( '\StackBoost\ForSupportCandy\Modules\Appearance\WordPress' ) ) {
            $theme_class = \StackBoost\ForSupportCandy\Modules\Appearance\WordPress::get_active_theme_class();
        }
		?>
		<div class="wrap stackboost-dashboard <?php echo esc_attr( $theme_class ); ?>">
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
					case 'steps':
						Sequence::render_page();
						break;
					case 'staff':
						Staff::render_page();
						break;
					case 'settings':
						Settings::render_page();
						break;
					case 'certificate':
						Settings::render_certificate_page();
						break;
					case 'import_export':
						ImportExport::render_page();
						break;
					default:
						Sequence::render_page();
						break;
				}
				?>
			</div>
		</div>
		<?php
	}
}
