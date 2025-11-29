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
                <a href="?page=stackboost-ats&tab=settings" class="nav-tab <?php if($current_tab === 'settings') echo 'nav-tab-active'; ?>"><?php _e('Settings', 'stackboost-for-supportcandy'); ?></a>
            </nav>
            <div class="tab-content" style="margin-top: 20px;">
            <?php
                switch ( $current_tab ) {
                    case 'questions': $this->render_questions_tab(); break;
                    case 'submissions': $this->render_submissions_tab(); break;
                    case 'results': $this->render_results_tab(); break;
                    case 'settings': $this->render_settings_tab(); break;
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
            case 'manage_questions':
                check_admin_referer( 'stackboost_ats_manage_questions_nonce' );
                $this->process_question_form();
                break;
            case 'manage_submissions':
                check_admin_referer( 'stackboost_ats_manage_submissions_nonce' );
                $this->process_submissions_form();
                break;
        }
    }

    private function process_question_form() {
        global $wpdb;
        $action = $_POST['ats_action'] ?? '';
        $question_id = isset( $_POST['question_id'] ) ? intval( $_POST['question_id'] ) : 0;
        $data = [
            'question_text' => sanitize_text_field( $_POST['question_text'] ),
            'question_type' => sanitize_text_field( $_POST['question_type'] ),
            'is_required'   => isset( $_POST['is_required'] ) ? 1 : 0,
            'sort_order'    => intval( $_POST['sort_order'] )
        ];

        if ( $action === 'add' ) {
            $wpdb->insert( $this->questions_table_name, $data );
            $question_id = $wpdb->insert_id;
            $message = 'added';
        } elseif ( $action === 'update' && $question_id ) {
            $wpdb->update( $this->questions_table_name, $data, array( 'id' => $question_id ) );
            $message = 'updated';
        }

        if ( $question_id && $data['question_type'] === 'dropdown' ) {
            $wpdb->delete( $this->dropdown_options_table_name, array( 'question_id' => $question_id ) );
            $options = array_map( 'trim', explode( ',', $_POST['dropdown_options'] ) );
            foreach ( $options as $opt ) {
                if ( ! empty( $opt ) ) {
                    $wpdb->insert( $this->dropdown_options_table_name, [ 'question_id' => $question_id, 'option_value' => $opt ] );
                }
            }
        }
        wp_redirect( admin_url( 'admin.php?page=stackboost-ats&tab=questions&message=' . ( $message ?? 'error' ) ) );
        exit;
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
                'added' => 'Question added successfully!',
                'updated' => 'Question updated successfully!',
                'deleted' => 'Question deleted successfully!',
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
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && isset( $_GET['question_id'] ) ) {
            check_admin_referer( 'stackboost_ats_delete_q' );
            $wpdb->delete( $this->questions_table_name, array( 'id' => intval( $_GET['question_id'] ) ) );
            wp_redirect( admin_url( 'admin.php?page=stackboost-ats&tab=questions&message=deleted' ) );
            exit;
        }
        $editing_question = null;
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'edit' && isset( $_GET['question_id'] ) ) {
            $editing_question = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->questions_table_name} WHERE id = %d", intval( $_GET['question_id'] ) ), ARRAY_A );
            if ( $editing_question && $editing_question['question_type'] === 'dropdown' ) {
                $options = $wpdb->get_results( $wpdb->prepare( "SELECT option_value FROM {$this->dropdown_options_table_name} WHERE question_id = %d", $editing_question['id'] ) );
                $editing_question['options_str'] = implode( ', ', array_column( $options, 'option_value' ) );
            }
        }
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
        $options = get_option( 'stackboost_settings' );
        $ticket_question_id = ! empty( $options['ats_ticket_question_id'] ) ? (int) $options['ats_ticket_question_id'] : 0;
        $ticket_url_base = ! empty( $options['ats_ticket_url_base'] ) ? $options['ats_ticket_url_base'] : '';
        $questions = $wpdb->get_results( "SELECT id, question_text, report_heading, question_type FROM {$this->questions_table_name} ORDER BY sort_order ASC", ARRAY_A );
        $submissions = $wpdb->get_results( "SELECT s.*, u.display_name FROM {$this->survey_submissions_table_name} s LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID ORDER BY submission_date DESC", ARRAY_A );
        include __DIR__ . '/Admin/view-results-template.php';
    }

    private function render_settings_tab() {
        ?>
        <h2><?php _e('After Ticket Survey Settings', 'stackboost-for-supportcandy'); ?></h2>
        <form method="post" action="options.php">
            <?php
                settings_fields( 'stackboost_settings' );
                echo '<input type="hidden" name="stackboost_settings[page_slug]" value="stackboost-ats-settings">';
                do_settings_sections( 'stackboost-ats-settings' );
                submit_button();
            ?>
        </form>
        <?php
    }
}