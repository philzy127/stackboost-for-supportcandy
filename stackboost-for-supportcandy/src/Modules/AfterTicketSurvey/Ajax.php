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

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->update(
            esc_sql( $this->questions_table_name ),
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

        stackboost_log( "ATS get_question requested for ID: {$question_id}", 'ats' );

        if ( ! $question_id ) {
            wp_send_json_error( 'Invalid question ID.' );
        }

        $safe_table = esc_sql( $this->questions_table_name );
        $question = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$safe_table} WHERE id = %d", $question_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        if ( ! $question ) {
            stackboost_log( "ATS get_question: Question not found.", 'ats' );
            wp_send_json_error( 'Question not found.' );
        }

        if ( $question['question_type'] === 'dropdown' ) {
            $safe_dropdown_table = esc_sql( $this->dropdown_options_table_name );
            $options = $wpdb->get_results( $wpdb->prepare( "SELECT option_value FROM {$safe_dropdown_table} WHERE question_id = %d ORDER BY sort_order ASC", $question_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
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
            stackboost_log( "ATS save_question: Permission denied.", 'ats' );
            wp_send_json_error( 'Permission denied.' );
        }
        check_ajax_referer( 'stackboost_ats_manage_questions_nonce', 'nonce' );

        global $wpdb;
        $question_id = isset( $_POST['question_id'] ) ? intval( $_POST['question_id'] ) : 0;

        stackboost_log( "ATS save_question called. ID: {$question_id}", 'ats' );

        // Get the current max sort order if adding new
        $current_max_order = 0;
        if ( ! $question_id ) {
            $safe_table = esc_sql( $this->questions_table_name );
            $current_max_order = (int) $wpdb->get_var( "SELECT MAX(sort_order) FROM {$safe_table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        }

        $data = [
            'question_text' => sanitize_text_field( $_POST['question_text'] ),
            'question_type' => sanitize_text_field( $_POST['question_type'] ),
            'is_required'   => isset( $_POST['is_required'] ) && $_POST['is_required'] === '1' ? 1 : 0,
            'is_readonly_prefill' => isset( $_POST['is_readonly_prefill'] ) && $_POST['is_readonly_prefill'] === '1' ? 1 : 0,
            'sort_order'    => isset( $_POST['sort_order'] ) ? intval( $_POST['sort_order'] ) : ($question_id ? 0 : $current_max_order + 1),
            'prefill_key'   => sanitize_text_field( $_POST['prefill_key'] ?? '' )
        ];

        // Note: prefill_key is allowed for ALL types now.

        stackboost_log( "ATS save_question data: " . print_r($data, true), 'ats' );

        if ( empty( $data['question_text'] ) ) {
            wp_send_json_error( 'Question text is required.' );
        }

        // Highlander Rule: Only one 'ticket_number' question allowed per form.
        if ( $data['question_type'] === 'ticket_number' ) {
            $safe_table = esc_sql( $this->questions_table_name );
            $existing_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$safe_table} WHERE question_type = %s", 'ticket_number' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            // If one exists AND (we are creating new OR we are updating a different question)
            if ( $existing_id && ( ! $question_id || $existing_id != $question_id ) ) {
                 stackboost_log( "ATS save_question failed: Highlander Rule violated. Existing ID: $existing_id", 'ats' );
                 wp_send_json_error( 'Only one Ticket Number question is allowed per form.' );
            }
        }

        if ( $question_id ) {
            // Update
            // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter
            $result = $wpdb->update( esc_sql( $this->questions_table_name ), $data, [ 'id' => $question_id ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            if ( false === $result ) {
                stackboost_log( "ATS save_question update failed. DB Error: " . $wpdb->last_error, 'ats' );
                wp_send_json_error( 'Failed to update question.' );
            }
        } else {
            // Add
            if ( ! isset( $data['report_heading'] ) ) {
                $data['report_heading'] = '';
            }

            // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter
            $result = $wpdb->insert( esc_sql( $this->questions_table_name ), $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            if ( false === $result ) {
                stackboost_log( "ATS save_question insert failed. Data: " . print_r($data, true) . " DB Error: " . $wpdb->last_error, 'ats' );
                wp_send_json_error( 'Failed to add question.' );
            }
            $question_id = $wpdb->insert_id;
        }

        // Handle Dropdown Options
        if ( $data['question_type'] === 'dropdown' ) {
            $safe_dropdown_table = esc_sql( $this->dropdown_options_table_name );
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->delete( $safe_dropdown_table, [ 'question_id' => $question_id ] );

            if ( ! empty( $_POST['dropdown_options'] ) ) {
                $options = array_map( 'trim', explode( ',', $_POST['dropdown_options'] ) );
                foreach ( $options as $index => $opt ) {
                    if ( ! empty( $opt ) ) {
                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                        $wpdb->insert( esc_sql( $this->dropdown_options_table_name ), [
                            'question_id'  => $question_id,
                            'option_value' => $opt,
                            'sort_order'   => $index
                        ] );
                    }
                }
            }
        } elseif ( $data['question_type'] !== 'dropdown' ) {
             // Clean up options if type changed away from dropdown
             // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
             $wpdb->delete( esc_sql( $this->dropdown_options_table_name ), [ 'question_id' => $question_id ] );
        }

        stackboost_log( "ATS save_question success. ID: {$question_id}", 'ats' );
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

        stackboost_log( "ATS delete_question: {$question_id}", 'ats' );

        if ( ! $question_id ) {
            wp_send_json_error( 'Invalid question ID.' );
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete( esc_sql( $this->questions_table_name ), [ 'id' => $question_id ] );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete( esc_sql( $this->dropdown_options_table_name ), [ 'question_id' => $question_id ] );

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

        stackboost_log( "ATS reorder_questions: " . print_r($order, true), 'ats' );

        if ( empty( $order ) || ! is_array( $order ) ) {
            wp_send_json_error( 'Invalid order data.' );
        }

        foreach ( $order as $position => $question_id ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update(
                esc_sql( $this->questions_table_name ),
                [ 'sort_order' => intval( $position ) ],
                [ 'id' => intval( $question_id ) ]
            );
        }

        wp_send_json_success( 'Questions reordered successfully.' );
    }
}
