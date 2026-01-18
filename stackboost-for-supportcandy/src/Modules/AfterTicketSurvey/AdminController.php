<?php

namespace StackBoost\ForSupportCandy\Modules\AfterTicketSurvey;

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
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'questions';

		$theme_class = 'sb-theme-clean-tech';
		if ( class_exists( '\StackBoost\ForSupportCandy\Modules\Appearance\WordPress' ) ) {
			$theme_class = \StackBoost\ForSupportCandy\Modules\Appearance\WordPress::get_active_theme_class();
		}
		?>
		<div class="wrap stackboost-dashboard <?php echo esc_attr( $theme_class ); ?>">
			<h1><?php esc_html_e( 'After Ticket Survey', 'stackboost-for-supportcandy' ); ?></h1>
			<nav class="nav-tab-wrapper stackboost-tabs-connected">
				<a href="?page=stackboost-ats&tab=questions" class="nav-tab <?php if ( $current_tab === 'questions' ) echo 'nav-tab-active'; ?>"><?php esc_html_e( 'Manage Questions', 'stackboost-for-supportcandy' ); ?></a>
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
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$post_action = isset( $_POST['form_action'] ) ? sanitize_text_field( wp_unslash( $_POST['form_action'] ) ) : '';

		switch ( $post_action ) {
			// Question management is now handled via AJAX in Ajax.php
			case 'manage_submissions':
				check_admin_referer( 'stackboost_ats_manage_submissions_nonce' );
				$this->process_submissions_form();
				break;
		}
	}

	private function process_submissions_form() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! empty( $_POST['selected_submissions'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$ids_array = array_map( 'absint', $_POST['selected_submissions'] );

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
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['page'] ) && 'stackboost-ats' === $_GET['page'] && isset( $_GET['message'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$message      = sanitize_text_field( wp_unslash( $_GET['message'] ) );
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
		include __DIR__ . '/Admin/manage-questions-template.php';
	}

	private function render_submissions_tab() {
		$submissions = $this->repository->get_submissions();
		include __DIR__ . '/Admin/manage-submissions-template.php';
	}

	private function render_results_tab() {
		$questions   = $this->repository->get_questions();
		$submissions = $this->repository->get_submissions_with_users();
		include __DIR__ . '/Admin/view-results-template.php';
	}
}
