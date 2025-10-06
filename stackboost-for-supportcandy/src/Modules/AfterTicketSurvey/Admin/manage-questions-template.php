<?php
/**
 * Template for the "Manage Questions" tab in the After Ticket Survey admin page.
 *
 * @var array|null $editing_question The question being edited, or null if adding a new one.
 * @var array      $questions        The list of all existing questions.
 */
?>
<div>
	<!-- Existing Questions Table -->
	<div class="stackboost-ats-questions-list">
		<h2><?php _e('Existing Questions', 'stackboost-for-supportcandy'); ?></h2>
		<table class="wp-list-table widefat fixed striped stackboost-ats-admin-table">
			<thead>
				<tr>
					<th class="manage-column"><?php _e('Order', 'stackboost-for-supportcandy'); ?></th>
					<th class="manage-column"><?php _e('Question Text', 'stackboost-for-supportcandy'); ?></th>
					<th class="manage-column"><?php _e('Type', 'stackboost-for-supportcandy'); ?></th>
					<th class="manage-column"><?php _e('Required', 'stackboost-for-supportcandy'); ?></th>
					<th class="manage-column"><?php _e('Options (for Dropdown)', 'stackboost-for-supportcandy'); ?></th>
					<th class="manage-column"><?php _e('Actions', 'stackboost-for-supportcandy'); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php if ( empty( $questions ) ) : ?>
				<tr><td colspan="6"><?php _e('No questions found.', 'stackboost-for-supportcandy'); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $questions as $q ) : ?>
				<tr>
					<td><?php echo esc_html( $q['sort_order'] ); ?></td>
					<td><?php echo esc_html( $q['question_text'] ); ?></td>
					<td><?php echo esc_html( str_replace('_', ' ', ucfirst( $q['question_type'] ) ) ); ?></td>
					<td><?php echo $q['is_required'] ? __('Yes', 'stackboost-for-supportcandy') : __('No', 'stackboost-for-supportcandy'); ?></td>
					<td>
						<?php
						if ( $q['question_type'] === 'dropdown' ) {
							global $wpdb;
							$options = $wpdb->get_results( $wpdb->prepare( "SELECT option_value FROM {$wpdb->prefix}stackboost_ats_dropdown_options WHERE question_id = %d ORDER BY sort_order ASC", $q['id'] ), ARRAY_A );
							echo esc_html( implode(', ', array_column($options, 'option_value')) );
						} else {
							echo 'N/A';
						}
						?>
					</td>
					<td>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=stackboost-ats&tab=questions&action=edit&question_id=' . $q['id'] ) ); ?>" class="button button-secondary"><?php _e('Edit', 'stackboost-for-supportcandy'); ?></a>
						<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=stackboost-ats&tab=questions&action=delete&question_id=' . $q['id'] ), 'stackboost_ats_delete_q' ) ); ?>" class="button button-secondary" onclick="return confirm('<?php _e('Are you sure?', 'stackboost-for-supportcandy'); ?>');"><?php _e('Delete', 'stackboost-for-supportcandy'); ?></a>
					</td>
				</tr>
				<?php endforeach; ?>
			<?php endif; ?>
			</tbody>
		</table>
	</div>

	<!-- Add/Edit Question Form -->
	<div class="stackboost-ats-question-form-container">
		<div class="stackboost-ats-question-form">
		<h2><?php echo $editing_question ? __('Edit Question', 'stackboost-for-supportcandy') : __('Add New Question', 'stackboost-for-supportcandy'); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="stackboost_ats_admin_actions">
            <input type="hidden" name="form_action" value="manage_questions">
			<input type="hidden" name="ats_action" value="<?php echo $editing_question ? 'update' : 'add'; ?>">
			<?php if ( $editing_question ) : ?><input type="hidden" name="question_id" value="<?php echo esc_attr( $editing_question['id'] ); ?>"><?php endif; ?>
			<?php wp_nonce_field( 'stackboost_ats_manage_questions_nonce' ); ?>

			<div class="form-row">
				<div class="form-group full-width">
					<label for="question_text"><?php _e('Question Text:', 'stackboost-for-supportcandy'); ?></label>
					<input type="text" id="question_text" name="question_text" value="<?php echo esc_attr( $editing_question['question_text'] ?? '' ); ?>" required>
				</div>
			</div>

			<div class="form-row">
				<div class="form-group">
					<label for="question_type"><?php _e('Question Type:', 'stackboost-for-supportcandy'); ?></label>
					<select id="question_type" name="question_type" required onchange="toggleDropdownOptions(this)">
						<option value="short_text" <?php selected( $editing_question['question_type'] ?? '', 'short_text' ); ?>><?php _e('Short Text', 'stackboost-for-supportcandy'); ?></option>
						<option value="long_text" <?php selected( $editing_question['question_type'] ?? '', 'long_text' ); ?>><?php _e('Long Text', 'stackboost-for-supportcandy'); ?></option>
						<option value="rating" <?php selected( $editing_question['question_type'] ?? '', 'rating' ); ?>><?php _e('Rating (1-5)', 'stackboost-for-supportcandy'); ?></option>
						<option value="dropdown" <?php selected( $editing_question['question_type'] ?? '', 'dropdown' ); ?>><?php _e('Dropdown', 'stackboost-for-supportcandy'); ?></option>
					</select>
				</div>
				<div class="form-group">
					<label for="ats_sort_order"><?php _e('Sort Order:', 'stackboost-for-supportcandy'); ?></label>
					<input type="number" id="ats_sort_order" name="sort_order" value="<?php echo esc_attr( $editing_question['sort_order'] ?? count($questions) ); ?>" min="0">
				</div>
				<div class="form-group">
					<label for="ats_is_required"><?php _e('Required?', 'stackboost-for-supportcandy'); ?></label>
					<input type="checkbox" id="ats_is_required" name="is_required" value="1" <?php checked( $editing_question['is_required'] ?? 1 ); ?>>
				</div>
			</div>

			<div class="form-group" id="ats_dropdown_options_group" style="display: none;">
				<label for="ats_dropdown_options"><?php _e('Dropdown Options (comma-separated):', 'stackboost-for-supportcandy'); ?></label>
				<textarea id="ats_dropdown_options" name="dropdown_options" rows="3" placeholder="e.g., Option 1, Option 2"><?php echo esc_textarea( $editing_question['options_str'] ?? '' ); ?></textarea>
			</div>

			<div class="form-actions">
				<button type="submit" class="button button-primary"><?php echo $editing_question ? __('Update Question', 'stackboost-for-supportcandy') : __('Add Question', 'stackboost-for-supportcandy'); ?></button>
				<?php if ( $editing_question ) : ?><a href="<?php echo esc_url( admin_url( 'admin.php?page=stackboost-ats&tab=questions' ) ); ?>" class="button button-secondary"><?php _e('Cancel Edit', 'stackboost-for-supportcandy'); ?></a><?php endif; ?>
			</div>
		</form>
		</div>
	</div>
</div>
<script>
function toggleDropdownOptions(selectElement) {
    document.getElementById('ats_dropdown_options_group').style.display = selectElement.value === 'dropdown' ? 'block' : 'none';
}
document.addEventListener('DOMContentLoaded', function() {
    toggleDropdownOptions(document.getElementById('question_type'));
});
</script>