<?php

namespace StackBoost\ForSupportCandy\Modules\AfterTicketSurvey;

/**
 * Handles the admin interface for the After Ticket Survey module.
 *
 * @package StackBoost\ForSupportCandy\Modules\AfterTicketSurvey
 */
class AdminController {

    /** @var string The name of the questions table. */
    private string $questions_table_name;

    /** @var string The name of the dropdown options table. */
    private string $dropdown_options_table_name;

    /** @var string The name of the survey submissions table. */
    private string $survey_submissions_table_name;

    /** @var string The name of the survey answers table. */
    private string $survey_answers_table_name;

	/**
	 * Constructor.
	 */
	public function __construct() {
        global $wpdb;
        $this->questions_table_name        = $wpdb->prefix . 'stackboost_ats_questions';
        $this->dropdown_options_table_name = $wpdb->prefix . 'stackboost_ats_dropdown_options';
        $this->survey_submissions_table_name = $wpdb->prefix . 'stackboost_ats_survey_submissions';
        $this->survey_answers_table_name   = $wpdb->prefix . 'stackboost_ats_survey_answers';
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
        $current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'main';
        ?>
        <div class="wrap">
            <h1><?php _e( 'After Ticket Survey', 'stackboost-for-supportcandy' ); ?></h1>
            <nav class="nav-tab-wrapper">
                <a href="?page=stackboost-ats&tab=main" class="nav-tab <?php if($current_tab === 'main') echo 'nav-tab-active'; ?>"><?php _e('How to Use', 'stackboost-for-supportcandy'); ?></a>
                <a href="?page=stackboost-ats&tab=questions" class="nav-tab <?php if($current_tab === 'questions') echo 'nav-tab-active'; ?>"><?php _e('Manage Questions', 'stackboost-for-supportcandy'); ?></a>
                <a href="?page=stackboost-ats&tab=submissions" class="nav-tab <?php if($current_tab === 'submissions') echo 'nav-tab-active'; ?>"><?php _e('Manage Submissions', 'stackboost-for-supportcandy'); ?></a>
                <a href="?page=stackboost-ats&tab=results" class="nav-tab <?php if($current_tab === 'results') echo 'nav-tab-active'; ?>"><?php _e('View Results', 'stackboost-for-supportcandy'); ?></a>
            </nav>
            <div class="tab-content" style="margin-top: 20px;">
            <?php
                switch ( $current_tab ) {
                    case 'questions': $this->render_questions_tab(); break;
                    case 'submissions': $this->render_submissions_tab(); break;
                    case 'results': $this->render_results_tab(); break;
                    default: $this->render_main_tab(); break;
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
        if ( ! current_user_can( 'manage_options' ) ) return;

        $post_action = $_POST['form_action'] ?? '';

        switch ($post_action) {
            // Question management is now handled via AJAX in Ajax.php
            case 'manage_submissions':
                check_admin_referer( 'stackboost_ats_manage_submissions_nonce' );
                $this->process_submissions_form();
                break;
        }
    }

    private function process_submissions_form() {
        global $wpdb;
        if ( ! empty( $_POST['selected_submissions'] ) ) {
            $ids = implode( ',', array_map( 'absint', $_POST['selected_submissions'] ) );
            $wpdb->query( "DELETE FROM {$this->survey_submissions_table_name} WHERE id IN ($ids)" );
            $wpdb->query( "DELETE FROM {$this->survey_answers_table_name} WHERE submission_id IN ($ids)" );
        }
        wp_redirect( admin_url( 'admin.php?page=stackboost-ats&tab=submissions&message=submissions_deleted' ) );
        exit;
    }

    /**
     * Display admin notices for this module.
     */
    public function display_admin_notices() {
        if ( isset( $_GET['page'] ) && 'stackboost-ats' === $_GET['page'] && isset( $_GET['message'] ) ) {
            $type = strpos( $_GET['message'], 'fail' ) !== false || $_GET['message'] === 'error' ? 'error' : 'success';
            $messages = [
                'submissions_deleted' => 'Selected submissions deleted!',
                'error' => 'An error occurred.',
            ];
            $message_text = $messages[ $_GET['message'] ] ?? 'Action completed.';
            echo "<div class=\"notice notice-{$type} is-dismissible\"><p>{$message_text}</p></div>";
        }
    }

    // --- Tab Rendering Methods --- //

    private function render_main_tab() {
        include __DIR__ . '/Admin/how-to-use-template.php';
    }

    private function render_questions_tab() {
        global $wpdb;
        // Logic for handling GET based delete/edit has been moved to AJAX.
        // We only need to fetch the list for initial display.
        $questions = $wpdb->get_results( "SELECT * FROM {$this->questions_table_name} ORDER BY sort_order ASC", ARRAY_A );
        include __DIR__ . '/Admin/manage-questions-template.php';
    }

    private function render_submissions_tab() {
        global $wpdb;
        $submissions = $wpdb->get_results( "SELECT id, submission_date FROM {$this->survey_submissions_table_name} ORDER BY submission_date DESC", ARRAY_A );
        include __DIR__ . '/Admin/manage-submissions-template.php';
    }

    private function render_results_tab() {
        global $wpdb;
        $questions = $wpdb->get_results( "SELECT id, question_text, report_heading, question_type, prefill_key FROM {$this->questions_table_name} ORDER BY sort_order ASC", ARRAY_A );
        $submissions = $wpdb->get_results( "SELECT s.*, u.display_name FROM {$this->survey_submissions_table_name} s LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID ORDER BY submission_date DESC", ARRAY_A );
        include __DIR__ . '/Admin/view-results-template.php';
    }
}
