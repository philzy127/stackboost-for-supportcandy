<?php

if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * Template for the "Manage Submissions" tab in the After Ticket Survey admin page.
 *
 * @var array $submissions The list of all survey submissions.
 */
?>
<h2><?php esc_html_e( 'Manage Survey Submissions', 'stackboost-for-supportcandy' ); ?></h2>
<p><?php esc_html_e( 'Select one or more submissions below and click "Delete" to permanently remove them.', 'stackboost-for-supportcandy' ); ?></p>

<?php if ( ! empty( $submissions ) ) : ?>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="stackboost-ats-submissions-form">
        <input type="hidden" name="action" value="stackboost_ats_admin_actions">
        <input type="hidden" name="form_action" value="manage_submissions">
        <?php wp_nonce_field( 'stackboost_ats_manage_submissions_nonce' ); ?>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th class="check-column"><input type="checkbox" id="stackboost-ats-select-all"></th>
                    <th><?php esc_html_e( 'Submission ID', 'stackboost-for-supportcandy' ); ?></th>
                    <th><?php esc_html_e( 'Date Submitted', 'stackboost-for-supportcandy' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $submissions as $sub ) : ?>
                <tr>
                    <th scope="row" class="check-column">
                        <input type="checkbox" name="selected_submissions[]" value="<?php echo esc_attr( $sub['id'] ); ?>">
                    </th>
                    <td><?php echo esc_html( $sub['id'] ); ?></td>
                    <td><?php echo esc_html( $sub['submission_date'] ); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <br>
        <button type="submit" class="button button-primary" id="stackboost-delete-submissions-btn">
            <?php esc_html_e( 'Delete Selected Submissions', 'stackboost-for-supportcandy' ); ?>
        </button>
    </form>
    <script>
        jQuery(document).ready(function($){
            $('#stackboost-ats-select-all').on('change', function(){
                $('input[name="selected_submissions[]"]').prop('checked', $(this).prop('checked'));
            });

            $('#stackboost-delete-submissions-btn').on('click', function(e) {
                e.preventDefault();
                var $form = $('#stackboost-ats-submissions-form');

                stackboostConfirm(
                    '<?php echo esc_js( __( 'Are you sure you want to delete the selected submissions? This cannot be undone.', 'stackboost-for-supportcandy' ) ); ?>',
                    '<?php echo esc_js( __( 'Confirm Delete', 'stackboost-for-supportcandy' ) ); ?>',
                    function() {
                        $form.submit();
                    },
                    null,
                    '<?php echo esc_js( __( 'Yes, Delete', 'stackboost-for-supportcandy' ) ); ?>',
                    '<?php echo esc_js( __( 'Cancel', 'stackboost-for-supportcandy' ) ); ?>',
                    true
                );
            });
        });
    </script>
<?php else : ?>
    <p><?php esc_html_e( 'No survey submissions to manage yet.', 'stackboost-for-supportcandy' ); ?></p>
<?php endif; ?>
