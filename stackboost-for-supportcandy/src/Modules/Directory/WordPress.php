<?php
/**
 * WordPress integration for the Directory module.
 *
 * @package StackBoost
 * @subpackage Modules\Directory
 */

namespace StackBoost\ForSupportCandy\Modules\Directory;

use StackBoost\ForSupportCandy\Modules\Directory\Admin\DepartmentsListTable;
use StackBoost\ForSupportCandy\Modules\Directory\Admin\HowToUse;
use StackBoost\ForSupportCandy\Modules\Directory\Admin\LocationsListTable;
use StackBoost\ForSupportCandy\Modules\Directory\Admin\Management;
use StackBoost\ForSupportCandy\Modules\Directory\Admin\Settings;
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
	 * The single instance of the class.
	 *
	 * @var WordPress|null
	 */
	private static ?WordPress $instance = null;

	/**
	 * Core instance.
	 *
	 * @var Core
	 */
	private $core;

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
	private function __construct() {
		$this->core = new Core();
		add_action( 'admin_init', array( $this, 'register_module_settings' ) );
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_scripts' ) );
		Management::register_ajax_actions();
	}

	/**
	 * Register module settings.
	 */
	public function register_module_settings() {
		Settings::register_settings();
	}

	/**
	 * Check if the current user can edit directory entries.
	 *
	 * @return bool
	 */
	public function can_user_edit(): bool {
		$user = wp_get_current_user();
		if ( in_array( 'administrator', $user->roles, true ) ) {
			return true;
		}
		$options    = get_option( Settings::OPTION_NAME, array() );
		$edit_roles = $options['edit_roles'] ?? array( 'administrator', 'editor' );
		return ! empty( array_intersect( $user->roles, $edit_roles ) );
	}

	/**
	 * Check if the current user can access the management tab.
	 *
	 * @return bool
	 */
	public function can_user_manage(): bool {
		$user = wp_get_current_user();
		if ( in_array( 'administrator', $user->roles, true ) ) {
			return true;
		}
		$options          = get_option( Settings::OPTION_NAME, array() );
		$management_roles = $options['management_roles'] ?? array( 'administrator' );
		return ! empty( array_intersect( $user->roles, $management_roles ) );
	}


	/**
	 * Add the admin menu page.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'stackboost-for-supportcandy',
			__( 'Company Directory', 'stackboost-for-supportcandy' ),
			__( 'Company Directory', 'stackboost-for-supportcandy' ),
			'manage_options', // This is a base capability, we do finer checks in the render function.
			'stackboost-directory',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin scripts and styles.
	 */
	public function enqueue_admin_scripts() {
		$screen = get_current_screen();
		if ( ! $screen || 'stackboost_page_stackboost-directory' !== $screen->id ) {
			return;
		}

		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'staff';

		if ( 'management' === $active_tab ) {
			// Enqueue scripts for import.
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

			// Enqueue scripts for clear actions.
			wp_enqueue_script(
				'stackboost-management-ajax',
				\STACKBOOST_PLUGIN_URL . 'assets/js/management-ajax.js',
				array( 'jquery' ),
				\STACKBOOST_VERSION,
				true
			);
			wp_localize_script(
				'stackboost-management-ajax',
				'stackboostManagementAjax',
				array(
					'ajax_url'                  => admin_url( 'admin-ajax.php' ),
					'clear_nonce'               => wp_create_nonce( 'stackboost_directory_clear_db_nonce' ),
					'fresh_start_nonce'         => wp_create_nonce( 'stackboost_directory_fresh_start_nonce' ),
					'clearingMessage'           => __( 'Clearing data... please wait.', 'stackboost-for-supportcandy' ),
					'clearConfirm'              => __( 'Are you sure you want to clear all staff data?', 'stackboost-for-supportcandy' ),
					'freshStartConfirm'         => __( 'Are you sure you want to proceed? This will permanently delete all staff, locations, and departments.', 'stackboost-for-supportcandy' ),
					'freshStartConfirmDouble'   => __( 'This action cannot be undone. Are you absolutely sure?', 'stackboost-for-supportcandy' ),
					'freshStartSuccess'         => __( 'Fresh start complete. All data has been deleted.', 'stackboost-for-supportcandy' ),
					'errorMessage'              => __( 'An error occurred:', 'stackboost-for-supportcandy' ),
					'cancelMessage'             => __( 'Action cancelled.', 'stackboost-for-supportcandy' ),
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
			'how_to_use'  => __( 'How to Use', 'stackboost-for-supportcandy' ),
			'settings'    => __( 'Settings', 'stackboost-for-supportcandy' ),
		);

		if ( $this->can_user_manage() ) {
			$tabs['management'] = __( 'Management', 'stackboost-for-supportcandy' );
			$tabs['testing']    = __( 'Testing', 'stackboost-for-supportcandy' );
		}

		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'staff';
		?>
		<div class="wrap">
			<h1>
				<?php esc_html_e( 'Company Directory', 'stackboost-for-supportcandy' ); ?>
				<?php if ( $this->can_user_edit() ) : ?>
					<?php if ( 'staff' === $active_tab ) : ?>
						<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . $this->core->cpts->post_type ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New', 'stackboost-for-supportcandy' ); ?></a>
					<?php elseif ( 'locations' === $active_tab ) : ?>
						<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . $this->core->cpts->location_post_type ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New', 'stackboost-for-supportcandy' ); ?></a>
					<?php elseif ( 'departments' === $active_tab ) : ?>
						<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . $this->core->cpts->department_post_type ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New', 'stackboost-for-supportcandy' ); ?></a>
					<?php endif; ?>
				<?php endif; ?>
			</h1>
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
						$staff_list_table = new StaffListTable( $this->core->cpts->post_type );
						$staff_list_table->prepare_items();
						?>
						<form method="post">
							<?php
							$staff_list_table->views();
							$staff_list_table->search_box( 'search', 'search_id' );
							$staff_list_table->display();
							?>
						</form>
						<?php
						break;
					case 'locations':
						$locations_list_table = new LocationsListTable( $this->core->cpts->location_post_type );
						$locations_list_table->prepare_items();
						?>
						<form method="post">
							<?php
							$locations_list_table->views();
							$locations_list_table->search_box( 'search', 'search_id' );
							$locations_list_table->display();
							?>
						</form>
						<?php
						break;
					case 'departments':
						$departments_list_table = new DepartmentsListTable( $this->core->cpts->department_post_type );
						$departments_list_table->prepare_items();
						?>
						<form method="post">
							<?php
							$departments_list_table->views();
							$departments_list_table->search_box( 'search', 'search_id' );
							$departments_list_table->display();
							?>
						</form>
						<?php
						break;
					case 'management':
						if ( $this->can_user_manage() ) {
							Management::render_management_page();
						} else {
							echo '<p>' . esc_html__( 'You do not have permission to access this page.', 'stackboost-for-supportcandy' ) . '</p>';
						}
						break;
					case 'settings':
						Settings::render_settings_page();
						break;
					case 'how_to_use':
						HowToUse::render_how_to_use_page();
						break;
					case 'testing':
						if ( $this->can_user_manage() ) {
							self::render_testing_page();
						} else {
							echo '<p>' . esc_html__( 'You do not have permission to access this page.', 'stackboost-for-supportcandy' ) . '</p>';
						}
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the testing page for the Directory Service.
	 */
	public static function render_testing_page() {
		$test_action = isset( $_POST['stackboost_test_action'] ) ? sanitize_key( $_POST['stackboost_test_action'] ) : '';
		$result      = null;

		if ( ! empty( $test_action ) && check_admin_referer( 'stackboost_directory_test_nonce', 'stackboost_directory_test_nonce' ) ) {
			$directory_service = \StackBoost\ForSupportCandy\Services\DirectoryService::get_instance();

			if ( 'find_profile' === $test_action ) {
				$user_id_or_email = isset( $_POST['user_id_or_email'] ) ? sanitize_text_field( wp_unslash( $_POST['user_id_or_email'] ) ) : '';
				if ( ! empty( $user_id_or_email ) ) {
					$result = $directory_service->find_employee_profile( $user_id_or_email );
				}
			} elseif ( 'retrieve_data' === $test_action ) {
				$profile_id = isset( $_POST['profile_id'] ) ? absint( $_POST['profile_id'] ) : 0;
				if ( ! empty( $profile_id ) ) {
					$result = $directory_service->retrieve_employee_data( $profile_id );
				}
			}
		}
		?>
		<div class="wrap">
			<h2><?php esc_html_e( 'Directory Service Testing', 'stackboost-for-supportcandy' ); ?></h2>
			<p><?php esc_html_e( 'This page allows you to test the Directory Service directly. The service is responsible for finding and retrieving employee data from the directory.', 'stackboost-for-supportcandy' ); ?></p>

			<?php if ( ! is_null( $result ) ) : ?>
				<div id="message" class="updated notice is-dismissible">
					<h3><?php esc_html_e( 'Test Result', 'stackboost-for-supportcandy' ); ?></h3>
					<pre><?php echo esc_html( print_r( $result, true ) ); ?></pre>
				</div>
			<?php endif; ?>

			<hr>

			<h3><?php esc_html_e( 'Test 1: Find Employee Profile', 'stackboost-for-supportcandy' ); ?></h3>
			<p><?php esc_html_e( 'Enter a WordPress User ID or an email address to find the corresponding directory profile ID.', 'stackboost-for-supportcandy' ); ?></p>
			<form method="post">
				<input type="hidden" name="stackboost_test_action" value="find_profile">
				<?php wp_nonce_field( 'stackboost_directory_test_nonce', 'stackboost_directory_test_nonce' ); ?>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><label for="user_id_or_email"><?php esc_html_e( 'User ID or Email', 'stackboost-for-supportcandy' ); ?></label></th>
						<td><input type="text" id="user_id_or_email" name="user_id_or_email" class="regular-text" value="<?php echo isset( $_POST['user_id_or_email'] ) ? esc_attr( $_POST['user_id_or_email'] ) : ''; ?>"/></td>
					</tr>
				</table>
				<?php submit_button( __( 'Find Profile ID', 'stackboost-for-supportcandy' ) ); ?>
			</form>

			<hr>

			<h3><?php esc_html_e( 'Test 2: Retrieve Employee Data', 'stackboost-for-supportcandy' ); ?></h3>
			<p><?php esc_html_e( 'Enter a directory profile ID (which is a post ID) to retrieve the full, structured data object for that employee.', 'stackboost-for-supportcandy' ); ?></p>
			<form method="post">
				<input type="hidden" name="stackboost_test_action" value="retrieve_data">
				<?php wp_nonce_field( 'stackboost_directory_test_nonce', 'stackboost_directory_test_nonce' ); ?>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><label for="profile_id"><?php esc_html_e( 'Directory Profile ID', 'stackboost-for-supportcandy' ); ?></label></th>
						<td><input type="number" id="profile_id" name="profile_id" class="regular-text" value="<?php echo isset( $_POST['profile_id'] ) ? esc_attr( $_POST['profile_id'] ) : ''; ?>" /></td>
					</tr>
				</table>
				<?php submit_button( __( 'Retrieve Employee Data', 'stackboost-for-supportcandy' ) ); ?>
			</form>
		</div>
		<?php
	}
}