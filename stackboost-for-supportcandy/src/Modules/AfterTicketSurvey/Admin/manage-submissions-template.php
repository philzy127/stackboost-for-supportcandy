<?php
/**
 * Template for the "Manage Submissions" tab in the After Ticket Survey admin page.
 *
 * @var array $submissions The list of all survey submissions.
 */
?>
<h2><?php _e('Manage Survey Submissions', 'stackboost-for-supportcandy'); ?></h2>
<p><?php _e('Select one or more submissions below and click "Delete" to permanently remove them.', 'stackboost-for-supportcandy'); ?></p>

<?php if ( ! empty( $submissions ) ) : ?>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="stackboost_ats_admin_actions">
        <input type="hidden" name="form_action" value="manage_submissions">
        <?php wp_nonce_field( 'stackboost_ats_manage_submissions_nonce' ); ?>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th class="check-column"><input type="checkbox" id="stackboost-ats-select-all"></th>
                    <th><?php _e('Submission ID', 'stackboost-for-supportcandy'); ?></th>
                    <th><?php _e('Date Submitted', 'stackboost-for-supportcandy'); ?></th>
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
        <button type="submit" class="button button-primary" onclick="return confirm('<?php _e('Are you sure you want to delete the selected submissions? This cannot be undone.', 'stackboost-for-supportcandy'); ?>');">
            <?php _e('Delete Selected Submissions', 'stackboost-for-supportcandy'); ?>
        </button>
    </form>
    <script>
        jQuery(document).ready(function($){
            $('#stackboost-ats-select-all').on('change', function(){
                $('input[name="selected_submissions[]"]').prop('checked', $(this).prop('checked'));
            });
        });
    </script>
<?php else : ?>
    <p><?php _e('No survey submissions to manage yet.', 'stackboost-for-supportcandy'); ?></p>
<?php endif; ?>