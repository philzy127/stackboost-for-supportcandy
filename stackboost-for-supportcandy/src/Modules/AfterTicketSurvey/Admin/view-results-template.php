<?php

if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * Template for the "View Results" tab in the After Ticket Survey admin page.
 *
 * @var array $questions   List of survey questions.
 * @var array $submissions List of survey submissions with answers.
 */
?>
<h2><?php esc_html_e( 'View Survey Results', 'stackboost-for-supportcandy' ); ?></h2>
<table class="wp-list-table widefat fixed striped stackboost-ats-results-table">
    <thead>
        <tr>
            <th><?php esc_html_e( 'ID', 'stackboost-for-supportcandy' ); ?></th>
            <th><?php esc_html_e( 'Date', 'stackboost-for-supportcandy' ); ?></th>
            <th><?php esc_html_e( 'User', 'stackboost-for-supportcandy' ); ?></th>
            <?php foreach ( $questions as $stackboost_q ) : ?>
                <th>
                    <?php echo esc_html( ! empty( $stackboost_q['report_heading'] ) ? $stackboost_q['report_heading'] : $stackboost_q['question_text'] ); ?>
                    <span class="dashicons dashicons-edit stackboost-ats-edit-heading"
                          data-question-id="<?php echo esc_attr( $stackboost_q['id'] ); ?>"
                          data-question-text="<?php echo esc_attr( $stackboost_q['question_text'] ); ?>"
                          data-report-heading="<?php echo esc_attr( $stackboost_q['report_heading'] ); ?>"
                          title="<?php esc_attr_e( 'Edit Report Heading', 'stackboost-for-supportcandy' ); ?>"></span>
                </th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
    <?php foreach ( $submissions as $stackboost_sub ) : ?>
        <tr>
            <td><?php echo esc_html($stackboost_sub['id']); ?></td>
            <td><?php echo esc_html($stackboost_sub['submission_date']); ?></td>
            <td><?php echo esc_html( $stackboost_sub['display_name'] ?? __('Guest', 'stackboost-for-supportcandy') ); ?></td>
            <?php
            global $wpdb;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $stackboost_answers = $wpdb->get_results( $wpdb->prepare( "SELECT question_id, answer_value FROM {$wpdb->prefix}stackboost_ats_survey_answers WHERE submission_id = %d", $stackboost_sub['id'] ), OBJECT_K );
            foreach ( $questions as $stackboost_q ) {
                $stackboost_answer = $stackboost_answers[ $stackboost_q['id'] ]->answer_value ?? '';
                if ( $stackboost_q['question_type'] === 'ticket_number' && is_numeric( $stackboost_answer ) ) {
                    echo '<td><a href="' . esc_url( \WPSC_Functions::get_ticket_url( $stackboost_answer, '1' ) ) . '" target="_blank">' . esc_html( $stackboost_answer ) . '</a></td>';
                } else {
                    echo '<td>' . nl2br(esc_html( $stackboost_answer )) . '</td>';
                }
            }
            ?>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<!-- Modal for editing heading -->
<div id="stackboost-ats-heading-modal" class="stackboost-ats-modal" style="display: none;">
    <div class="modal-content">
        <span class="close-modal">&times;</span>
        <h3><?php esc_html_e( 'Edit Report Heading', 'stackboost-for-supportcandy' ); ?></h3>
        <p><strong><?php esc_html_e( 'Full Question:', 'stackboost-for-supportcandy' ); ?></strong> <span id="stackboost-ats-modal-question-text"></span></p>
        <form id="stackboost-ats-heading-form">
            <input type="hidden" id="stackboost-ats-modal-question-id" name="question_id">
            <p>
                <label for="stackboost-ats-modal-report-heading"><strong><?php esc_html_e( 'Report Heading:', 'stackboost-for-supportcandy' ); ?></strong></label><br>
                <input type="text" id="stackboost-ats-modal-report-heading" name="report_heading" class="regular-text">
            </p>
            <button type="submit" class="button button-primary"><?php esc_html_e( 'Save Heading', 'stackboost-for-supportcandy' ); ?></button>
        </form>
    </div>
</div>