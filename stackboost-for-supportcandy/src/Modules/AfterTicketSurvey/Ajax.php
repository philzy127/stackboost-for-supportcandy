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

    /** @var string The name of the dropdown options table. */
    private string $dropdown_options_table_name;

    /**
     * Constructor.
     */
    public function __construct() {
        global $wpdb;
        $this->questions_table_name        = $wpdb->prefix . 'stackboost_ats_questions';
        $this->dropdown_options_table_name = $wpdb->prefix . 'stackboost_ats_dropdown_options';
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

    /**
     * Get a single question's data.
     */
    public function get_question() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied.' );
        }
        check_ajax_referer( 'stackboost_ats_manage_questions_nonce', 'nonce' );

        global $wpdb;
        $question_id = isset( $_POST['question_id'] ) ? intval( $_POST['question_id'] ) : 0;

        if ( ! $question_id ) {
            wp_send_json_error( 'Invalid question ID.' );
        }

        $question = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->questions_table_name} WHERE id = %d", $question_id ), ARRAY_A );

        if ( ! $question ) {
            wp_send_json_error( 'Question not found.' );
        }

        if ( $question['question_type'] === 'dropdown' ) {
            $options = $wpdb->get_results( $wpdb->prepare( "SELECT option_value FROM {$this->dropdown_options_table_name} WHERE question_id = %d ORDER BY sort_order ASC", $question_id ), ARRAY_A );
            $question['options_str'] = implode( ', ', array_column( $options, 'option_value' ) );
        } else {
            $question['options_str'] = '';
        }

        wp_send_json_success( $question );
    }

    /**
     * Save a question (add or update).
     */
    public function save_question() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied.' );
        }
        check_ajax_referer( 'stackboost_ats_manage_questions_nonce', 'nonce' );

        global $wpdb;
        $question_id = isset( $_POST['question_id'] ) ? intval( $_POST['question_id'] ) : 0;

        // Get the current max sort order if adding new
        $current_max_order = 0;
        if ( ! $question_id ) {
            $current_max_order = (int) $wpdb->get_var( "SELECT MAX(sort_order) FROM {$this->questions_table_name}" );
        }

        $data = [
            'question_text' => sanitize_text_field( $_POST['question_text'] ),
            'question_type' => sanitize_text_field( $_POST['question_type'] ),
            'is_required'   => isset( $_POST['is_required'] ) && $_POST['is_required'] === '1' ? 1 : 0,
            'sort_order'    => isset( $_POST['sort_order'] ) ? intval( $_POST['sort_order'] ) : ($question_id ? 0 : $current_max_order + 1)
        ];

        if ( empty( $data['question_text'] ) ) {
            wp_send_json_error( 'Question text is required.' );
        }

        if ( $question_id ) {
            // Update
            $result = $wpdb->update( $this->questions_table_name, $data, [ 'id' => $question_id ] );
            if ( false === $result ) {
                wp_send_json_error( 'Failed to update question.' );
            }
        } else {
            // Add
            $result = $wpdb->insert( $this->questions_table_name, $data );
            if ( false === $result ) {
                wp_send_json_error( 'Failed to add question.' );
            }
            $question_id = $wpdb->insert_id;
        }

        // Handle Dropdown Options
        if ( $data['question_type'] === 'dropdown' ) {
            $wpdb->delete( $this->dropdown_options_table_name, [ 'question_id' => $question_id ] );
            if ( ! empty( $_POST['dropdown_options'] ) ) {
                $options = array_map( 'trim', explode( ',', $_POST['dropdown_options'] ) );
                foreach ( $options as $index => $opt ) {
                    if ( ! empty( $opt ) ) {
                        $wpdb->insert( $this->dropdown_options_table_name, [
                            'question_id'  => $question_id,
                            'option_value' => $opt,
                            'sort_order'   => $index
                        ] );
                    }
                }
            }
        }

        wp_send_json_success( [ 'id' => $question_id, 'message' => 'Question saved successfully.' ] );
    }

    /**
     * Delete a question.
     */
    public function delete_question() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied.' );
        }
        check_ajax_referer( 'stackboost_ats_manage_questions_nonce', 'nonce' );

        global $wpdb;
        $question_id = isset( $_POST['question_id'] ) ? intval( $_POST['question_id'] ) : 0;

        if ( ! $question_id ) {
            wp_send_json_error( 'Invalid question ID.' );
        }

        $wpdb->delete( $this->questions_table_name, [ 'id' => $question_id ] );
        $wpdb->delete( $this->dropdown_options_table_name, [ 'question_id' => $question_id ] );

        wp_send_json_success( 'Question deleted successfully.' );
    }

    /**
     * Reorder questions.
     */
    public function reorder_questions() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied.' );
        }
        check_ajax_referer( 'stackboost_ats_manage_questions_nonce', 'nonce' );

        global $wpdb;
        $order = isset( $_POST['order'] ) ? $_POST['order'] : [];

        if ( empty( $order ) || ! is_array( $order ) ) {
            wp_send_json_error( 'Invalid order data.' );
        }

        foreach ( $order as $position => $question_id ) {
            $wpdb->update(
                $this->questions_table_name,
                [ 'sort_order' => intval( $position ) ],
                [ 'id' => intval( $question_id ) ]
            );
        }

        wp_send_json_success( 'Questions reordered successfully.' );
    }
}
