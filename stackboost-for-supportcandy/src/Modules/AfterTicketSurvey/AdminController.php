<?php

namespace StackBoost\ForSupportCandy\Modules\AfterTicketSurvey;

use StackBoost\ForSupportCandy\Core\Request;

/**
 * Handles the admin interface for the After Ticket Survey module.
 *
 * @package StackBoost\ForSupportCandy\Modules\AfterTicketSurvey
 */
class AdminController {

	/** @var Repository The repository instance. */
	private Repository $repository;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->repository = new Repository();
	}

	/**
	 * Add the submenu page for the survey module.
	 */
	public function add_admin_menu() {
		// Menu page is now registered centrally in Settings.php
	}

	/**
	 * Render the main admin page with its tabbed interface.
	 */
	public function render_page() {
		$current_tab = Request::get_get( 'tab', 'questions', 'key' );

		$theme_class = 'sb-theme-clean-tech';
		if ( class_exists( '\StackBoost\ForSupportCandy\Modules\Appearance\WordPress' ) ) {
			$theme_class = \StackBoost\ForSupportCandy\Modules\Appearance\WordPress::get_active_theme_class();
		}
		?>
		<div class="wrap stackboost-dashboard <?php echo esc_attr( $theme_class ); ?>">
			<h1><?php esc_html_e( 'After Ticket Survey', 'stackboost-for-supportcandy' ); ?></h1>
			<nav class="nav-tab-wrapper stackboost-tabs-connected">
				<a href="?page=stackboost-ats&tab=questions" class="nav-tab <?php if ( $current_tab === 'questions' ) echo 'nav-tab-active'; ?>"><?php esc_html_e( 'Manage Questions', 'stackboost-for-supportcandy' ); ?></a>
				<a href="?page=stackboost-ats&tab=categories" class="nav-tab <?php if ( $current_tab === 'categories' ) echo 'nav-tab-active'; ?>"><?php esc_html_e( 'Manage Categories', 'stackboost-for-supportcandy' ); ?></a>
				<a href="?page=stackboost-ats&tab=submissions" class="nav-tab <?php if ( $current_tab === 'submissions' ) echo 'nav-tab-active'; ?>"><?php esc_html_e( 'Manage Submissions', 'stackboost-for-supportcandy' ); ?></a>
				<a href="?page=stackboost-ats&tab=results" class="nav-tab <?php if ( $current_tab === 'results' ) echo 'nav-tab-active'; ?>"><?php esc_html_e( 'View Results', 'stackboost-for-supportcandy' ); ?></a>
			</nav>
			<div class="stackboost-card stackboost-card-connected">
			<?php
				switch ( $current_tab ) {
					case 'submissions':
						$this->render_submissions_tab();
						break;
					case 'results':
						$this->render_results_tab();
						break;
					case 'categories':
						$this->render_categories_tab();
						break;
					case 'questions':
					default:
						$this->render_questions_tab();
						break;
				}
			?>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle form submissions from the admin page (e.g., adding/updating questions).
	 */
	public function handle_admin_post() {
		if ( ! current_user_can( STACKBOOST_CAP_MANAGE_ATS ) ) {
			return;
		}

		$post_action = Request::get_post( 'form_action', '', 'text' );

		switch ( $post_action ) {
			// Question management is now handled via AJAX in Ajax.php
			case 'manage_submissions':
				check_admin_referer( 'stackboost_ats_manage_submissions_nonce' );
				$this->process_submissions_form();
				break;
		}
	}

	private function process_submissions_form() {
		$selected_submissions = Request::get_post( 'selected_submissions', [], 'array' );

		if ( ! empty( $selected_submissions ) ) {
			$ids_array = array_map( 'absint', $selected_submissions );

			// Use Repository for deletion
			$this->repository->bulk_delete_submissions( $ids_array );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=stackboost-ats&tab=submissions&message=submissions_deleted' ) );
		exit;
	}

	/**
	 * Display admin notices for this module.
	 */
	public function display_admin_notices() {
		$page = Request::get_get( 'page', '', 'text' );
		if ( 'stackboost-ats' === $page && Request::has_get( 'message' ) ) {
			$message      = Request::get_get( 'message', '', 'text' );
			$type         = strpos( $message, 'fail' ) !== false || $message === 'error' ? 'error' : 'success';
			$messages     = [
				'submissions_deleted' => 'Selected submissions deleted!',
				'error'               => 'An error occurred.',
			];
			$message_text = $messages[ $message ] ?? 'Action completed.';
			echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $message_text ) . '</p></div>';
		}
	}

	// --- Tab Rendering Methods --- //

	private function render_questions_tab() {
		$questions = $this->repository->get_questions();
		$categories = $this->repository->get_categories();
		include __DIR__ . '/Admin/manage-questions-template.php';
	}

	private function render_categories_tab() {
		$categories = $this->repository->get_categories();
		include __DIR__ . '/Admin/manage-categories-template.php';
	}

	private function render_submissions_tab() {
		$submissions = $this->repository->get_submissions();
		include __DIR__ . '/Admin/manage-submissions-template.php';
	}

	private function render_results_tab() {
		$agent_id   = Request::get_get( 'agent_id', '', 'text' );
		$start_date = Request::get_get( 'start_date', '', 'text' );
		$end_date   = Request::get_get( 'end_date', '', 'text' );

		global $wpdb;
		$all_agents = [];
		$agents_table = $wpdb->prefix . 'psmsc_agents';
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $wpdb->get_var("SHOW TABLES LIKE '{$agents_table}'") !== $agents_table ) {
			$agents_table = $wpdb->prefix . 'wpsc_agents';
		}

		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$agents_table}'") === $agents_table;
		if ( $table_exists ) {
			// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$agent_results = $wpdb->get_results("SELECT id, name FROM {$agents_table} ORDER BY name ASC");
			if ( is_array($agent_results) ) {
				foreach ( $agent_results as $a ) {
					$all_agents[$a->id] = $a->name;
				}
			}
		}

		$questions   = $this->repository->get_questions();
		$submissions = $this->repository->get_submissions_with_users( $agent_id, $start_date, $end_date );
		include __DIR__ . '/Admin/view-results-template.php';
	}
}
