<?php
/**
 * WordPress integration for the Directory module.
 *
 * @package StackBoost
 * @subpackage Modules\Directory
 */

namespace StackBoost\ForSupportCandy\Modules\Directory;

use StackBoost\ForSupportCandy\Modules\Directory\Admin\Clearer;
use StackBoost\ForSupportCandy\Modules\Directory\Admin\DepartmentsListTable;
use StackBoost\ForSupportCandy\Modules\Directory\Admin\HowToUse;
use StackBoost\ForSupportCandy\Modules\Directory\Admin\Importer;
use StackBoost\ForSupportCandy\Modules\Directory\Admin\LocationsListTable;
use StackBoost\ForSupportCandy\Modules\Directory\Admin\StaffListTable;
use StackBoost\ForSupportCandy\Modules\Directory\Data\CustomPostTypes;


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WordPress class for the Directory module.
 */
class WordPress {

	/**
	 * CustomPostTypes instance.
	 *
	 * @var CustomPostTypes
	 */
	private $cpts;

	/**
	 * Constructor.
	 *
	 * @param CustomPostTypes $cpts An instance of the CustomPostTypes class.
	 */
	public function __construct( CustomPostTypes $cpts ) {
		$this->cpts = $cpts;
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_scripts' ) );
	}

	/**
	 * Add the admin menu page.
	 */
	public function add_admin_menu() {
		add_menu_page(
			__( 'Company Directory', 'stackboost-for-supportcandy' ),
			__( 'Company Directory', 'stackboost-for-supportcandy' ),
			'manage_options',
			'stackboost-directory',
			array( $this, 'render_admin_page' ),
			'dashicons-groups',
			26
		);
	}

	/**
	 * Enqueue admin scripts and styles.
	 */
	public function enqueue_admin_scripts() {
		$screen = get_current_screen();
		if ( 'toplevel_page_stackboost-directory' !== $screen->id ) {
			return;
		}

		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'staff';

		if ( 'import' === $active_tab ) {
			wp_enqueue_script(
				'stackboost-import-ajax',
				\STACKBOOST_PLUGIN_URL . 'assets/js/import-ajax.js',
				array( 'jquery' ),
				\STACKBOOST_VERSION,
				true
			);
			wp_localize_script(
				'stackboost-import-ajax',
				'stackboostImportAjax',
				array(
					'ajax_url'           => admin_url( 'admin-ajax.php' ),
					'nonce'              => wp_create_nonce( 'stackboost_directory_csv_import' ),
					'processing_message' => __( 'Processing import... please wait.', 'stackboost-for-supportcandy' ),
					'uploading_message'  => __( 'Uploading...', 'stackboost-for-supportcandy' ),
					'success_message'    => __( 'Import complete:', 'stackboost-for-supportcandy' ),
					'error_message'      => __( 'Import failed:', 'stackboost-for-supportcandy' ),
					'no_file_selected'   => __( 'Please select a CSV file to upload.', 'stackboost-for-supportcandy' ),
				)
			);
		}

		if ( 'clear' === $active_tab ) {
			wp_enqueue_script(
				'stackboost-clear-db-modal',
				\STACKBOOST_PLUGIN_URL . 'assets/js/clear-db-modal.js',
				array( 'jquery' ),
				\STACKBOOST_VERSION,
				true
			);
			wp_localize_script(
				'stackboost-clear-db-modal',
				'stackboostClearDbAjax',
				array(
					'ajax_url'        => admin_url( 'admin-ajax.php' ),
					'nonce'           => wp_create_nonce( 'stackboost_directory_clear_db_nonce' ),
					'clearingMessage' => __( 'Clearing data... please wait.', 'stackboost-for-supportcandy' ),
					'errorMessage'    => __( 'An error occurred:', 'stackboost-for-supportcandy' ),
					'cancelMessage'   => __( 'Data clearing cancelled.', 'stackboost-for-supportcandy' ),
				)
			);
		}
	}

	/**
	 * Enqueue public scripts and styles.
	 */
	public function enqueue_public_scripts() {
		wp_enqueue_style(
			'stackboost-directory-datatables-style',
			'https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css',
			array(),
			'1.11.5'
		);
		wp_enqueue_style(
			'stackboost-directory-style',
			\STACKBOOST_PLUGIN_URL . 'assets/css/stackboost-directory.css',
			array(),
			\STACKBOOST_VERSION
		);

		wp_enqueue_script(
			'stackboost-directory-datatables',
			'https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js',
			array( 'jquery' ),
			'1.11.5',
			true
		);
		wp_enqueue_script(
			'stackboost-directory-js',
			\STACKBOOST_PLUGIN_URL . 'assets/js/stackboost-directory.js',
			array( 'jquery', 'stackboost-directory-datatables' ),
			\STACKBOOST_VERSION,
			true
		);
		wp_localize_script(
			'stackboost-directory-js',
			'stackboostPublicAjax',
			array(
				'ajax_url'         => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( 'stackboost_directory_public_nonce' ),
				'no_entries_found' => __( 'No directory entries found.', 'stackboost-for-supportcandy' ),
			)
		);
	}

	/**
	 * Render the admin page.
	 */
	public function render_admin_page() {
		$tabs = array(
			'staff'       => __( 'Staff', 'stackboost-for-supportcandy' ),
			'locations'   => __( 'Locations', 'stackboost-for-supportcandy' ),
			'departments' => __( 'Departments', 'stackboost-for-supportcandy' ),
			'import'      => __( 'Import', 'stackboost-for-supportcandy' ),
			'clear'       => __( 'Clear Data', 'stackboost-for-supportcandy' ),
			'how_to_use'  => __( 'How to Use', 'stackboost-for-supportcandy' ),
		);

		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'staff';
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Company Directory', 'stackboost-for-supportcandy' ); ?></h1>
			<h2 class="nav-tab-wrapper">
				<?php
				foreach ( $tabs as $tab_id => $tab_name ) {
					$tab_url = add_query_arg(
						array(
							'page' => 'stackboost-directory',
							'tab'  => $tab_id,
						)
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
						$staff_list_table = new StaffListTable( $this->cpts->post_type );
						$staff_list_table->prepare_items();
						?>
						<form method="post">
							<?php
							$staff_list_table->display();
							?>
						</form>
						<?php
						break;
					case 'locations':
						$locations_list_table = new LocationsListTable( $this->cpts->location_post_type );
						$locations_list_table->prepare_items();
						?>
						<form method="post">
							<?php
							$locations_list_table->display();
							?>
						</form>
						<?php
						break;
					case 'departments':
						$departments_list_table = new DepartmentsListTable( $this->cpts->department_post_type );
						$departments_list_table->prepare_items();
						?>
						<form method="post">
							<?php
							$departments_list_table->display();
							?>
						</form>
						<?php
						break;
					case 'import':
						Importer::render_import_page();
						break;
					case 'clear':
						Clearer::render_clear_db_page();
						break;
					case 'how_to_use':
						HowToUse::render_how_to_use_page();
						break;
				}
				?>
			</div>
		</div>
		<?php
	}
}