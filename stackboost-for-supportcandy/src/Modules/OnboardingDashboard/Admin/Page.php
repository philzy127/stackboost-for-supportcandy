<?php


namespace StackBoost\ForSupportCandy\Modules\OnboardingDashboard\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

use StackBoost\ForSupportCandy\Core\Request;

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

		// Enqueue SelectWoo
		wp_enqueue_script( 'stackboost-selectwoo' );
		wp_enqueue_style( 'stackboost-selectwoo' );
	}

	/**
	 * Render the main page with tabs.
	 */
	public static function render_page() {
		$tabs = [
			'steps' => __( 'Onboarding Steps', 'stackboost-for-supportcandy' ),
			'staff' => __( 'Staff', 'stackboost-for-supportcandy' ),
		];

		// Restrict administrative tabs to the Settings capability
		if ( current_user_can( STACKBOOST_CAP_MANAGE_SETTINGS ) ) {
			$tabs['certificate']   = __( 'Certificate', 'stackboost-for-supportcandy' );
			$tabs['settings']      = __( 'Settings', 'stackboost-for-supportcandy' );
			$tabs['import_export'] = __( 'Import / Export', 'stackboost-for-supportcandy' );
		}

		$active_tab = Request::get_get( 'tab', 'steps', 'key' );

		// Security check: if user tries to access a restricted tab directly via URL
		if ( in_array( $active_tab, [ 'certificate', 'settings', 'import_export' ], true ) && ! current_user_can( STACKBOOST_CAP_MANAGE_SETTINGS ) ) {
			$active_tab = 'steps'; // Fallback
		}

        $theme_class = 'sb-theme-clean-tech';
        if ( class_exists( '\StackBoost\ForSupportCandy\Modules\Appearance\WordPress' ) ) {
            $theme_class = \StackBoost\ForSupportCandy\Modules\Appearance\WordPress::get_active_theme_class();
        }
		?>
		<div class="wrap stackboost-dashboard <?php echo esc_attr( $theme_class ); ?>">
			<h1><?php esc_html_e( 'Onboarding Dashboard', 'stackboost-for-supportcandy' ); ?></h1>
			<h2 class="nav-tab-wrapper stackboost-tabs-connected">
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
					echo '<a href="' . esc_url( $tab_url ) . '" class="nav-tab' . esc_attr( $active ) . '">' . esc_html( $tab_name ) . '</a>';
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
