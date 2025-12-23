<?php
/**
 * Template for the "Manage Questions" tab in the After Ticket Survey admin page.
 *
 * @var array $questions The list of all existing questions.
 */
?>
<div>
    <h2 class="nav-tab-wrapper-header" style="margin-top: 0; padding-top: 10px;">
        <?php _e('Manage Questions', 'stackboost-for-supportcandy'); ?>
        <button id="stackboost-ats-add-question" class="page-title-action"><?php _e('Add New Question', 'stackboost-for-supportcandy'); ?></button>
    </h2>

    <div class="stackboost-ats-questions-list">
        <table class="wp-list-table widefat fixed striped stackboost-ats-admin-table" id="stackboost-ats-questions-list">
            <thead>
                <tr>
                    <th class="manage-column column-sort" style="width: 30px;"></th> <!-- Handle column -->
                    <th class="manage-column"><?php _e('Question Text', 'stackboost-for-supportcandy'); ?></th>
                    <th class="manage-column"><?php _e('Type', 'stackboost-for-supportcandy'); ?></th>
                    <th class="manage-column"><?php _e('Required', 'stackboost-for-supportcandy'); ?></th>
                    <th class="manage-column"><?php _e('Options', 'stackboost-for-supportcandy'); ?></th>
                    <th class="manage-column"><?php _e('Prefill Key', 'stackboost-for-supportcandy'); ?></th>
                    <th class="manage-column"><?php _e('Actions', 'stackboost-for-supportcandy'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if ( empty( $questions ) ) : ?>
                <tr class="no-items"><td colspan="7"><?php _e('No questions found.', 'stackboost-for-supportcandy'); ?></td></tr>
            <?php else : ?>
                <?php foreach ( $questions as $q ) : ?>
                <tr data-id="<?php echo esc_attr($q['id']); ?>" data-type="<?php echo esc_attr($q['question_type']); ?>">
                    <td class="stackboost-ats-sort-handle" style="cursor: move;"><span class="dashicons dashicons-menu"></span></td>
                    <td><?php echo esc_html( $q['question_text'] ); ?></td>
                    <td><?php echo esc_html( str_replace('_', ' ', ucfirst( $q['question_type'] ) ) ); ?></td>
                    <td><?php echo $q['is_required'] ? __('Yes', 'stackboost-for-supportcandy') : __('No', 'stackboost-for-supportcandy'); ?></td>
                    <td>
                        <?php
                        if ( $q['question_type'] === 'dropdown' ) {
                            global $wpdb;
                            $options_table = $wpdb->prefix . 'stackboost_ats_dropdown_options';
                            $options = $wpdb->get_results( $wpdb->prepare( "SELECT option_value FROM {$options_table} WHERE question_id = %d ORDER BY sort_order ASC", $q['id'] ), ARRAY_A );
                            echo esc_html( implode(', ', array_column($options, 'option_value')) );
                        } else {
                            echo '-';
                        }
                        ?>
                    </td>
                    <td><?php echo esc_html( $q['prefill_key'] ?? '-' ); ?></td>
                    <td>
                        <button type="button" class="stackboost-icon-btn stackboost-ats-edit-question" data-id="<?php echo esc_attr($q['id']); ?>" title="<?php esc_attr_e('Edit', 'stackboost-for-supportcandy'); ?>"><span class="dashicons dashicons-edit"></span></button>
                        <button type="button" class="stackboost-icon-btn stackboost-ats-delete-question" data-id="<?php echo esc_attr($q['id']); ?>" title="<?php esc_attr_e('Delete', 'stackboost-for-supportcandy'); ?>"><span class="dashicons dashicons-trash"></span></button>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Hidden Modal Container -->
    <div id="stackboost-ats-question-modal" title="<?php _e('Question Details', 'stackboost-for-supportcandy'); ?>" style="display:none;">
        <form id="stackboost-ats-question-form">
            <input type="hidden" id="question_id" name="question_id" value="">
            <input type="hidden" id="ats_sort_order" name="sort_order" value="">

            <div class="form-group">
                <label for="question_text" style="display:block; margin-bottom:5px;"><strong><?php _e('Question Text:', 'stackboost-for-supportcandy'); ?></strong></label>
                <input type="text" id="question_text" name="question_text" class="widefat" required>
            </div>

            <div class="form-group" style="margin-top:10px;">
                <label for="question_type" style="display:block; margin-bottom:5px;"><strong><?php _e('Question Type:', 'stackboost-for-supportcandy'); ?></strong></label>
                <select id="question_type" name="question_type" class="widefat">
                    <option value="ticket_number"><?php _e('Ticket Number', 'stackboost-for-supportcandy'); ?></option>
                    <option value="short_text"><?php _e('Short Text', 'stackboost-for-supportcandy'); ?></option>
                    <option value="long_text"><?php _e('Long Text', 'stackboost-for-supportcandy'); ?></option>
                    <option value="rating"><?php _e('Rating (1-5)', 'stackboost-for-supportcandy'); ?></option>
                    <option value="dropdown"><?php _e('Dropdown', 'stackboost-for-supportcandy'); ?></option>
                </select>
                <div id="ats_limit_reached_msg" class="notice notice-warning inline" style="display:none; margin-top: 5px;">
                    <p><?php _e('Only one Ticket Number field is allowed per form.', 'stackboost-for-supportcandy'); ?></p>
                </div>
            </div>

            <!-- New Prefill Key Field -->
            <div class="form-group" id="ats_prefill_key_group" style="margin-top:10px;">
                <label for="ats_prefill_key" style="display:block; margin-bottom:5px;"><strong><?php _e('URL Parameter (Prefill Key):', 'stackboost-for-supportcandy'); ?></strong></label>
                <input type="text" id="ats_prefill_key" name="prefill_key" class="widefat" placeholder="e.g. ticket_id">
                <p class="description"><?php _e('Optional. Matches a URL parameter to pre-fill this field.', 'stackboost-for-supportcandy'); ?></p>
            </div>

            <div class="form-group" style="margin-top:10px; display: flex; gap: 20px;">
                <label>
                    <input type="checkbox" id="ats_is_readonly_prefill" name="is_readonly_prefill" value="1">
                    <?php _e('Read-only if Pre-filled?', 'stackboost-for-supportcandy'); ?>
                </label>
                <label>
                    <input type="checkbox" id="ats_is_required" name="is_required" value="1">
                    <?php _e('Required?', 'stackboost-for-supportcandy'); ?>
                </label>
            </div>

            <div class="form-group" id="ats_dropdown_options_group" style="display: none; margin-top:10px;">
                <label for="ats_dropdown_options" style="display:block; margin-bottom:5px;"><strong><?php _e('Dropdown Options (comma-separated):', 'stackboost-for-supportcandy'); ?></strong></label>
                <textarea id="ats_dropdown_options" name="dropdown_options" class="widefat" rows="3" placeholder="e.g., Option 1, Option 2"></textarea>
            </div>
        </form>
    </div>
</div>
