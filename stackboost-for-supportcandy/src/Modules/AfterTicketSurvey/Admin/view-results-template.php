<?php

if ( ! defined( 'ABSPATH' ) ) exit;

use StackBoost\ForSupportCandy\Modules\AfterTicketSurvey\Repository;

/**
 * Template for the "View Results" tab in the After Ticket Survey admin page.
 *
 * @var array  $questions   List of survey questions.
 * @var array  $submissions List of survey submissions with answers.
 * @var string $agent_id    Selected agent ID for filtering.
 * @var string $start_date  Start date for filtering.
 * @var string $end_date    End date for filtering.
 * @var array  $all_agents  List of agents [id => name].
 */
?>
<h2><?php esc_html_e( 'View Survey Results', 'stackboost-for-supportcandy' ); ?></h2>

<div class="stackboost-card" style="margin-bottom: 20px;">
    <form method="get" action="">
        <input type="hidden" name="page" value="stackboost-ats">
        <input type="hidden" name="tab" value="results">

        <div style="display:flex; gap: 20px; align-items: flex-end; flex-wrap: wrap;">
            <div>
                <label for="agent_id" style="display:block; margin-bottom:5px; font-weight:600;"><?php esc_html_e( 'Agent', 'stackboost-for-supportcandy' ); ?></label>
                <select name="agent_id" id="agent_id">
                    <option value=""><?php esc_html_e( 'All Agents', 'stackboost-for-supportcandy' ); ?></option>
                    <?php foreach ( $all_agents as $id => $name ) : ?>
                        <option value="<?php echo esc_attr( $id ); ?>" <?php selected( $agent_id, (string) $id ); ?>><?php echo esc_html( $name ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="display:block; margin-bottom:5px; font-weight:600;"><?php esc_html_e( 'Date Range', 'stackboost-for-supportcandy' ); ?></label>
                <input type="date" name="start_date" id="start_date" value="<?php echo esc_attr( $start_date ); ?>" /> -
                <input type="date" name="end_date" id="end_date" value="<?php echo esc_attr( $end_date ); ?>" />
            </div>
            <div>
                <button type="submit" class="button button-primary"><?php esc_html_e( 'Filter', 'stackboost-for-supportcandy' ); ?></button>
                <a href="?page=stackboost-ats&tab=results" class="button"><?php esc_html_e( 'Clear', 'stackboost-for-supportcandy' ); ?></a>
            </div>
        </div>
    </form>
</div>

<div style="display:flex; justify-content:flex-end; margin-bottom:10px;">
    <button type="button" class="button" id="stackboost-ats-open-headings-modal">
        <span class="dashicons dashicons-edit" style="vertical-align:middle; margin-top:3px;"></span>
        <?php esc_html_e( 'Edit Report Headings', 'stackboost-for-supportcandy' ); ?>
    </button>
</div>

<table class="wp-list-table widefat fixed striped stackboost-ats-results-table">
    <thead>
        <tr>
            <th><?php esc_html_e( 'ID', 'stackboost-for-supportcandy' ); ?></th>
            <th><?php esc_html_e( 'Date', 'stackboost-for-supportcandy' ); ?></th>
            <th><?php esc_html_e( 'User', 'stackboost-for-supportcandy' ); ?></th>
            <?php foreach ( $questions as $stackboost_q ) : ?>
                <th class="stackboost-ats-report-heading-<?php echo esc_attr( $stackboost_q['id'] ); ?>">
                    <?php echo esc_html( ! empty( $stackboost_q['report_heading'] ) ? $stackboost_q['report_heading'] : $stackboost_q['question_text'] ); ?>
                </th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
    <?php
    // Use Repository for answers fetch inside the loop to avoid DirectDB
    // Ideally this should be eager loaded in the controller, but to fix the template quickly:
    $stackboost_ats_repository = new Repository();
    foreach ( $submissions as $stackboost_sub ) : ?>
        <tr>
            <td><?php echo esc_html($stackboost_sub['id']); ?></td>
            <td><?php echo esc_html($stackboost_sub['submission_date']); ?></td>
            <td><?php echo esc_html( $stackboost_sub['display_name'] ?? __('Guest', 'stackboost-for-supportcandy') ); ?></td>
            <?php
            $stackboost_ats_cache_key = 'stackboost_ats_sub_answers_' . $stackboost_sub['id'];
            $stackboost_answers = get_transient( $stackboost_ats_cache_key );

            if ( false === $stackboost_answers ) {
                $stackboost_answers = $stackboost_ats_repository->get_answers_by_submission_id( $stackboost_sub['id'] );
                // Cache for 1 hour to improve performance
                set_transient( $stackboost_ats_cache_key, $stackboost_answers, HOUR_IN_SECONDS );
            }

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

<!-- Modal for editing headings -->
<div id="stackboost-ats-heading-modal" class="stackboost-ats-modal" style="display: none;">
    <div class="modal-content" style="max-width:600px; max-height:80vh; overflow-y:auto;">
        <span class="close-modal">&times;</span>
        <h3><?php esc_html_e( 'Edit Report Headings', 'stackboost-for-supportcandy' ); ?></h3>
        <form id="stackboost-ats-heading-form">
            <table class="form-table">
                <?php foreach ( $questions as $stackboost_q ) : ?>
                    <tr>
                        <th scope="row" style="width:50%;">
                            <label for="report_heading_<?php echo esc_attr( $stackboost_q['id'] ); ?>">
                                <?php echo esc_html( $stackboost_q['question_text'] ); ?>
                            </label>
                        </th>
                        <td>
                            <input type="text"
                                   id="report_heading_<?php echo esc_attr( $stackboost_q['id'] ); ?>"
                                   name="report_headings[<?php echo esc_attr( $stackboost_q['id'] ); ?>]"
                                   value="<?php echo esc_attr( $stackboost_q['report_heading'] ); ?>"
                                   class="regular-text"
                                   placeholder="<?php echo esc_attr( $stackboost_q['question_text'] ); ?>">
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
            <p class="submit">
                <button type="submit" class="button button-primary"><?php esc_html_e( 'Save All Headings', 'stackboost-for-supportcandy' ); ?></button>
            </p>
        </form>
    </div>
</div>
