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
use StackBoost\ForSupportCandy\Modules\Directory\Admin\TicketWidgetSettings;
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
		add_filter( 'single_template', array( $this, 'load_single_staff_template' ) );
		add_action( 'wp_ajax_stackboost_get_staff_details', array( $this, 'ajax_get_staff_details' ) );
		add_action( 'wp_ajax_nopriv_stackboost_get_staff_details', array( $this, 'ajax_get_staff_details' ) );
		Management::register_ajax_actions();

		// Hook for rendering the ticket widget.
		add_action( 'wpsc_after_ticket_widget', array( $this, 'render_ticket_widget' ) );

		// Hide the redundant "Add New" button on the CPT edit screen.
		add_action( 'admin_head', array( $this, 'hide_add_new_button_on_cpt' ) );

		// Filters for post update messages.
		add_filter( 'post_updated_messages', array( $this, 'post_updated_messages' ) );

		// Filter for redirecting after post update.
		add_filter( 'redirect_post_location', array( $this, 'redirect_after_staff_update' ), 10, 2 );

		// Action to add hidden fields to the edit form.
		add_action( 'edit_form_top', array( $this, 'add_hidden_fields_to_edit_form' ) );
	}

	/**
	 * Register module settings.
	 */
	public function register_module_settings() {
		Settings::register_settings();
		TicketWidgetSettings::register_settings();
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
	 *
	 * @param string $hook_suffix The current admin page hook.
	 */
	public function enqueue_admin_scripts( $hook_suffix ) {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		// Enqueue scripts for the staff CPT add/edit screens.
		if ( 'post' === $screen->base && isset( $this->core->cpts->post_type ) && $this->core->cpts->post_type === $screen->post_type ) {
			// Enqueue phone formatting script.
			wp_enqueue_script(
				'stackboost-admin-phone-format',
				\STACKBOOST_PLUGIN_URL . 'assets/js/admin-phone-format.js',
				[ 'jquery' ],
				\STACKBOOST_VERSION,
				true
			);
		}

		// Enqueue scripts for the main directory admin page.
		if ( 'stackboost_page_stackboost-directory' === $screen->id ) {
			$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'staff';

			// Enqueue scripts for the Contact Widget settings tab.
			if ( 'contact_widget' === $active_tab ) {
				wp_enqueue_style(
					'stackboost-directory-settings',
					\STACKBOOST_PLUGIN_URL . 'assets/css/directory-settings.css',
					[],
					\STACKBOOST_VERSION
				);
				wp_enqueue_script(
					'stackboost-directory-settings',
					\STACKBOOST_PLUGIN_URL . 'assets/js/directory-settings.js',
					[ 'jquery', 'jquery-ui-sortable' ],
					\STACKBOOST_VERSION,
					true
				);
			}

			// Enqueue scripts for the Management tab.
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
				'ajax_url'                   => admin_url( 'admin-ajax.php' ),
				'stackboost_directory_nonce' => wp_create_nonce( 'stackboost_directory_public_nonce' ),
				'no_entries_found'           => __( 'No directory entries found.', 'stackboost-for-supportcandy' ),
			)
		);

		// Only enqueue modal assets if the setting is active.
		$settings             = get_option( Settings::OPTION_NAME, array() );
		$listing_display_mode = $settings['listing_display_mode'] ?? 'page';

		if ( 'modal' === $listing_display_mode ) {
			wp_enqueue_style(
				'stackboost-modal-style',
				\STACKBOOST_PLUGIN_URL . 'assets/css/stackboost-modal.css',
				array(),
				\STACKBOOST_VERSION
			);
			wp_enqueue_script(
				'stackboost-modal-js',
				\STACKBOOST_PLUGIN_URL . 'assets/js/stackboost-modal.js',
				array( 'jquery', 'stackboost-directory-js' ),
				\STACKBOOST_VERSION,
				true
			);
		}
	}

	/**
	 * Render the admin page.
	 */
	public function render_admin_page() {
		$base_tabs = array(
			'staff'           => __( 'Staff', 'stackboost-for-supportcandy' ),
			'departments'     => __( 'Departments', 'stackboost-for-supportcandy' ),
			'locations'       => __( 'Locations', 'stackboost-for-supportcandy' ),
			'contact_widget'  => __( 'Contact Widget', 'stackboost-for-supportcandy' ),
			'settings'        => __( 'Settings', 'stackboost-for-supportcandy' ),
		);

		$advanced_tabs = array();
		if ( $this->can_user_manage() ) {
			$advanced_tabs['management'] = __( 'Management', 'stackboost-for-supportcandy' );
			$advanced_tabs['how_to_use'] = __( 'How to Use', 'stackboost-for-supportcandy' );
			$advanced_tabs['testing']    = __( 'Testing', 'stackboost-for-supportcandy' );
		} else {
			$advanced_tabs['how_to_use'] = __( 'How to Use', 'stackboost-for-supportcandy' );
		}

		$tabs = array_merge( $base_tabs, $advanced_tabs );

		// Reorder per user request: Staff > Departments > Locations > Contact Widget > Settings > Management > How to Use > Testing
		$ordered_tabs = [];
		$order = ['staff', 'departments', 'locations', 'contact_widget', 'settings', 'management', 'how_to_use', 'testing'];
		foreach($order as $key) {
			if(isset($tabs[$key])) {
				$ordered_tabs[$key] = $tabs[$key];
			}
		}
		// Add any other tabs that might not be in the order array to the end
		$tabs = array_merge($ordered_tabs, array_diff_key($tabs, $ordered_tabs));

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
					case 'contact_widget':
						TicketWidgetSettings::render_page();
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

	/**
	 * AJAX handler to get staff details for modal view.
	 */
	public function ajax_get_staff_details() {
		check_ajax_referer( 'stackboost_directory_public_nonce', 'stackboost_directory_nonce' );

		if ( ! isset( $_POST['post_id'] ) ) {
			wp_send_json_error( 'Missing post ID.' );
		}

		$post_id = absint( $_POST['post_id'] );

		// Fetch the raw post object. We still need this for some template functions.
		$post = get_post( $post_id );
		if ( ! $post || 'sb_staff_dir' !== $post->post_type ) {
			wp_send_json_error( array( 'message' => 'Invalid post or post not found.' ) );
		}

		// Fetch the structured employee data. This is the safe way to get all data.
		$directory_service = \StackBoost\ForSupportCandy\Services\DirectoryService::get_instance();
		$employee_data     = $directory_service->retrieve_employee_data( $post_id );

		if ( ! $employee_data ) {
			wp_send_json_error( array( 'message' => 'Could not retrieve employee data.' ) );
		}

		// Set up global post data so functions like `post_class` work in the template.
		global $post;
		setup_postdata( $post );

		ob_start();
		// Load the template part, passing the fetched employee data directly to it.
		load_template(
			\STACKBOOST_PLUGIN_PATH . 'template-parts/directory-modal-content.php',
			false,
			array( 'employee' => $employee_data )
		);
		$content = ob_get_clean();
		wp_reset_postdata();

		wp_send_json_success( array( 'html' => $content ) );
	}

	/**
	 * Load the single staff template from the plugin.
	 *
	 * @param string $template The path of the template to include.
	 * @return string
	 */
	public function load_single_staff_template( $template ): string {
		if ( is_singular( $this->core->cpts->post_type ) ) {
			$plugin_template = \STACKBOOST_PLUGIN_PATH . 'single-sb_staff_dir.php';
			if ( file_exists( $plugin_template ) ) {
				return $plugin_template;
			}
		}
		return $template;
	}

	/**
	 * AJAX handler for searching WordPress users.
	 */
	public function ajax_search_users() {
		check_ajax_referer( 'stackboost_user_search_nonce', '_ajax_nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Forbidden' );
		}

		$term = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';

		if ( empty( $term ) ) {
			wp_send_json_error( 'Missing search term.' );
		}

		$results = [];
		$args    = [
			'number'      => 20,
			'search'      => '*' . esc_attr( $term ) . '*',
			'search_columns' => [
				'user_login',
				'user_email',
				'user_nicename',
				'display_name',
			],
		];

		$user_query = new \WP_User_Query( $args );

		if ( ! empty( $user_query->get_results() ) ) {
			foreach ( $user_query->get_results() as $user ) {
				$results[] = [
					'id'   => $user->ID,
					'text' => sprintf(
						'%s (%s)',
						$user->display_name,
						$user->user_email
					),
				];
			}
		}

		wp_send_json( [ 'results' => $results ] );
	}

	/**
	 * Render the pseudo-widget for the ticket screen.
	 *
	 * @param mixed $ticket The SupportCandy ticket object. Can be null on some hook fires.
	 */
	public function render_ticket_widget( $ticket ) {
		$widget_options = get_option( TicketWidgetSettings::WIDGET_OPTION_NAME, [] );

		// Exit early if the widget is not enabled.
		if ( empty( $widget_options['enabled'] ) || '1' !== $widget_options['enabled'] ) {
			return;
		}

		// Guard against the hook firing multiple times in a single request, which would
		// lead to duplicate HTML and invalid element IDs.
		static $has_rendered_once = false;
		if ( $has_rendered_once ) {
			// Output a console log to make it clear why the second render is being skipped.
			echo '<script>console.log("StackBoost Widget: Skipping duplicate render call in the same request.");</script>';
			return;
		}
		$has_rendered_once = true;

		if ( ! is_a( $ticket, 'WPSC_Ticket' ) ) {
			return;
		}

		$customer = $ticket->customer;
		if ( ! is_a( $customer, 'WPSC_Customer' ) || ! $customer->id ) {
			return;
		}

		$customer_email = $customer->email;

		$widget_options = get_option( TicketWidgetSettings::WIDGET_OPTION_NAME, [] );

		$directory_service = \StackBoost\ForSupportCandy\Services\DirectoryService::get_instance();
		$staff_member      = $directory_service->get_staff_by_email( $customer_email );

		$target_widget_slug = $widget_options['target_widget'] ?? '';
		$target_selector    = TicketWidgetSettings::get_widget_selector_by_slug( $target_widget_slug );
		$placement          = $widget_options['placement'] ?? 'before';


		$widget_content = '';
		if ( $staff_member && ! empty( $widget_options['display_fields'] ) ) {
			$all_fields = TicketWidgetSettings::get_directory_fields();
			$list_items = '';

			foreach ( $widget_options['display_fields'] as $field_key ) {
				$label   = $all_fields[ $field_key ] ?? '';
				$value   = '';
				$is_html = false;

				switch ( $field_key ) {
					case 'name':
						$value = $staff_member->name;
						break;
					case 'chp_staff_job_title':
						$value = $staff_member->job_title;
						break;
					case 'phone':
						$value   = $directory_service->get_formatted_phone_numbers_html( $staff_member );
						$is_html = true;
						break;
					case 'email_address':
						$value = $staff_member->email;
						break;
					case 'location':
						$value = $staff_member->location_name;
						break;
					case 'room_number':
						$value = $staff_member->room_number;
						break;
					case 'department_program':
						$value = $staff_member->department_program;
						break;
				}

				if ( ! empty( $value ) ) {
					if ( $is_html ) {
						// This value is pre-formatted, trusted HTML from the DirectoryService.
						$list_items .= '<div>' . $value . '</div>';
					} else {
						$list_items .= '<div><strong>' . esc_html( $label ) . ':</strong> ' . esc_html( $value ) . '</div>';
					}
				}
			}

			if ( ! empty( $list_items ) ) {
				$widget_content = $list_items;
			} else {
				$widget_content = '<p>' . esc_html__( 'No directory information available for this user.', 'stackboost-for-supportcandy' ) . '</p>';
			}
		} else {
			$widget_content = '<p>' . esc_html__( 'No directory entry found for this user.', 'stackboost-for-supportcandy' ) . '</p>';
		}
		$widget_unique_id = 'stackboost-contact-widget-' . bin2hex(random_bytes(8));
		?>
		<div id="<?php echo esc_attr( $widget_unique_id ); ?>" class="wpsc-it-widget stackboost-contact-widget-instance stackboost-contact-widget">
			<div class="wpsc-widget-header">
				<h2><?php echo esc_html__( 'Contact Information', 'stackboost-for-supportcandy' ); ?></h2>

				<?php
				if ( $staff_member && $this->can_user_edit() ) {
					$edit_link = get_edit_post_link( $staff_member->id );

					// Try to get the ticket ID from the ticket object first.
					$current_ticket_id = 0;
					if ( $ticket && isset( $ticket->id ) ) {
						$current_ticket_id = $ticket->id;
					} elseif ( isset( $_GET['ticket-id'] ) ) {
						// As a fallback for front-end views, get it from the URL.
						$current_ticket_id = absint( $_GET['ticket-id'] );
					}

					if ( $current_ticket_id > 0 ) {
						$edit_link = add_query_arg(
							array(
								'from'      => 'ticket',
								'ticket_id' => $current_ticket_id,
							),
							$edit_link
						);
					}
					?>
					<span onclick="window.location.href = '<?php echo esc_js( esc_url( $edit_link ) ); ?>';">
						<?php \WPSC_Icons::get( 'edit' ); ?>
					</span>
					<?php
				}
				?>

				<span class="wpsc-itw-toggle" data-widget="stackboost-contact-widget">
					<?php \WPSC_Icons::get( 'chevron-up' ); ?>
				</span>
			</div>
			<div class="wpsc-widget-body">
				<?php
				// The content is already escaped.
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $widget_content;
				?>
			</div>
		</div>
		<script>
			// Using a closure to keep variables local and avoid polluting the global scope.
			(function() {
				// --- Self-Contained Widget Positioning Logic ---

				/**
				 * Main function to find and reposition the widget. This script is designed
				 * to be idempotent. If a previous version of the widget exists (e.g., from
				 * a previous AJAX load), it will be removed before the new one is placed.
				 * @param {string} widgetId - The unique ID of the widget div to move.
				 * @param {string} targetSelector - The CSS selector of the widget to position against.
				 * @param {string} placement - 'before' or 'after'.
				 */
				var positionTicketWidget = function(serverWidgetId, targetSelector, placement) {

					// Find the widget this script is associated with.
					const customWidget = document.getElementById(serverWidgetId);
					if (!customWidget) {
						return;
					}

					// Create a guaranteed unique ID in the browser to avoid issues with server-side caching.
					const browserUniqueId = 'sb-widget-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
					customWidget.id = browserUniqueId;

					// Idempotency: Find and remove any stale widget instances from previous renders.
					const allWidgetInstances = document.querySelectorAll('.stackboost-contact-widget-instance');
					allWidgetInstances.forEach(function(instance) {
						// Remove any instance that is NOT the one we just assigned our unique ID to.
						if (instance.id !== browserUniqueId) {
							instance.remove();
						}
					});

					// Find all potential target widgets. This is crucial because SupportCandy renders
					// a hidden container for mobile, and we must select the visible one.
					const allTargets = document.querySelectorAll(targetSelector);
					let visibleTargetWidget = null;

					for (let i = 0; i < allTargets.length; i++) {
						// An element is visible if its offsetParent is not null.
						if (allTargets[i].offsetParent !== null) {
							visibleTargetWidget = allTargets[i];
							break;
						}
					}

					if (!visibleTargetWidget) {
						return;
					}

					if (placement === 'after') {
						visibleTargetWidget.parentNode.insertBefore(customWidget, visibleTargetWidget.nextSibling);
					} else {
						visibleTargetWidget.parentNode.insertBefore(customWidget, visibleTargetWidget);
					}
				};

				// Immediately call the function with the values from PHP.
				positionTicketWidget(
					<?php echo json_encode( $widget_unique_id ); ?>,
					<?php echo json_encode( $target_selector ); ?>,
					<?php echo json_encode( $placement ); ?>
				);

			})();
		</script>
		<?php
	}

	/**
	 * Hides the "Add New" button on the "Add New Staff" page.
	 */
	public function hide_add_new_button_on_cpt() {
		$screen = get_current_screen();

		// Check if we are on the 'Add New' screen for any of our CPTs.
		$post_types = [
			$this->core->cpts->post_type,
			$this->core->cpts->location_post_type,
			$this->core->cpts->department_post_type,
		];

		if ( $screen && 'add' === $screen->action && in_array( $screen->post_type, $post_types, true ) ) {
			echo '<style>.page-title-action { display: none; }</style>';
		}
	}

	/**
	 * Customize the post updated messages for the staff CPT.
	 *
	 * @param array $messages The existing post update messages.
	 * @return array The modified messages.
	 */
	public function post_updated_messages( $messages ) {
		// We only want to modify the messages for our CPT.
		if ( ! isset( $this->core->cpts->post_type ) || get_post_type() !== $this->core->cpts->post_type ) {
			return $messages;
		}

		$post = get_post();
		if ( ! $post ) {
			return $messages;
		}

		$post_type_object = get_post_type_object( $post->post_type );
		if ( ! $post_type_object ) {
			return $messages;
		}

		// Check for our custom query arg to determine the return link.
		$from      = isset( $_GET['from'] ) ? sanitize_key( $_GET['from'] ) : '';
		$ticket_id = isset( $_GET['ticket_id'] ) ? absint( $_GET['ticket_id'] ) : 0;

		$return_link = '';
		if ( 'ticket' === $from && $ticket_id > 0 ) {
			// Construct the URL to the SupportCandy ticket.
			// The base URL for a ticket is admin.php?page=wpsc-view-ticket&id=TICKET_ID
			$ticket_url  = admin_url( 'admin.php?page=wpsc-view-ticket&id=' . $ticket_id );
			$return_link = sprintf(
				' <a href="%s">%s</a>',
				esc_url( $ticket_url ),
				__( 'Return to Ticket', 'stackboost-for-supportcandy' )
			);
		} else {
			// Default to the staff directory list.
			$directory_url = admin_url( 'admin.php?page=stackboost-directory&tab=staff' );
			$return_link   = sprintf(
				' <a href="%s">%s</a>',
				esc_url( $directory_url ),
				__( 'Return to Directory', 'stackboost-for-supportcandy' )
			);
		}

		$messages[ $this->core->cpts->post_type ] = array(
			0  => '', // Unused. Messages start at index 1.
			1  => __( 'Staff updated.', 'stackboost-for-supportcandy' ) . $return_link,
			2  => __( 'Custom field updated.', 'stackboost-for-supportcandy' ),
			3  => __( 'Custom field deleted.', 'stackboost-for-supportcandy' ),
			4  => __( 'Staff updated.', 'stackboost-for-supportcandy' ),
			5  => isset( $_GET['revision'] ) ? sprintf( __( 'Staff restored to revision from %s.', 'stackboost-for-supportcandy' ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
			6  => __( 'Staff published.', 'stackboost-for-supportcandy' ) . $return_link,
			7  => __( 'Staff saved.', 'stackboost-for-supportcandy' ),
			8  => __( 'Staff submitted.', 'stackboost-for-supportcandy' ),
			9  => sprintf(
				// translators: %1$s: date and time of the scheduled post.
				__( 'Staff scheduled for: %1$s.', 'stackboost-for-supportcandy' ),
				'<strong>' . date_i18n( __( 'M j, Y @ G:i' ), strtotime( $post->post_date ) ) . '</strong>'
			),
			10 => __( 'Staff draft updated.', 'stackboost-for-supportcandy' ),
		);

		return $messages;
	}

	/**
	 * Redirect user back to the ticket after updating a staff member.
	 *
	 * @param string $location The destination URL.
	 * @param int    $post_id  The ID of the post being updated.
	 * @return string The modified destination URL.
	 */
	public function redirect_after_staff_update( $location, $post_id ) {
		// Only apply this logic to our staff CPT.
		if ( get_post_type( $post_id ) !== $this->core->cpts->post_type ) {
			return $location;
		}

		// Check if the save was triggered from the ticket context, using $_POST from the hidden fields.
		$from      = isset( $_POST['from'] ) ? sanitize_key( $_POST['from'] ) : '';
		$ticket_id = isset( $_POST['ticket_id'] ) ? absint( $_POST['ticket_id'] ) : 0;

		if ( 'ticket' === $from && $ticket_id > 0 ) {
			// Construct the URL to the SupportCandy ticket and redirect.
			$ticket_url = admin_url( 'admin.php?page=wpsc-view-ticket&id=' . $ticket_id );
			return $ticket_url;
		}

		// If we are not redirecting to the ticket, we still need to pass the context
		// to the post-update message function. We do this by adding the context from POST
		// as query arguments to the redirect URL, where they will become GET parameters.
		if ( ! empty( $from ) && ! empty( $ticket_id ) ) {
			$location = add_query_arg(
				array(
					'from'      => $from,
					'ticket_id' => $ticket_id,
				),
				$location
			);
		}

		return $location;
	}

	/**
	 * Add hidden fields to the staff edit form to preserve context.
	 *
	 * @param \WP_Post $post The post object being edited.
	 */
	public function add_hidden_fields_to_edit_form( $post ) {
		// Only apply this logic to our staff CPT.
		if ( get_post_type( $post ) !== $this->core->cpts->post_type ) {
			return;
		}

		// Check for our custom query arg to determine the return link.
		$from      = isset( $_GET['from'] ) ? sanitize_key( $_GET['from'] ) : '';
		$ticket_id = isset( $_GET['ticket_id'] ) ? absint( $_GET['ticket_id'] ) : 0;

		if ( 'ticket' === $from && $ticket_id > 0 ) {
			echo '<input type="hidden" name="from" value="' . esc_attr( $from ) . '" />';
			echo '<input type="hidden" name="ticket_id" value="' . esc_attr( $ticket_id ) . '" />';
		}
	}
}
