<?php

namespace StackBoost\ForSupportCandy\Modules\AfterTicketSurvey;

/**
 * Handles AJAX requests for the After Ticket Survey module.
 *
 * @package StackBoost\ForSupportCandy\Modules\AfterTicketSurvey
 */
class Ajax {

    /** @var string The name of the questions table. */
    private string $questions_table_name;

    /**
     * Constructor.
     */
    public function __construct() {
        global $wpdb;
        $this->questions_table_name = $wpdb->prefix . 'stackboost_ats_questions';
    }

    /**
     * Handle the request to update a question's report heading.
     */
    public function update_report_heading() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied.' );
        }
        check_ajax_referer( 'stackboost_ats_results_nonce', 'nonce' );

        global $wpdb;
        $question_id = isset( $_POST['question_id'] ) ? intval( $_POST['question_id'] ) : 0;
        $report_heading = isset( $_POST['report_heading'] ) ? sanitize_text_field( $_POST['report_heading'] ) : '';

        if ( ! $question_id ) {
            wp_send_json_error( 'Invalid question ID.' );
        }

        $result = $wpdb->update(
            $this->questions_table_name,
            [ 'report_heading' => $report_heading ],
            [ 'id' => $question_id ],
            [ '%s' ],
            [ '%d' ]
        );

        if ( false === $result ) {
            wp_send_json_error( 'Failed to update heading.' );
        } else {
            wp_send_json_success( 'Heading updated successfully.' );
        }
    }
}