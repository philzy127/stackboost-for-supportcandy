<?php

namespace StackBoost\ForSupportCandy\Modules\AfterTicketSurvey;

/**
 * Handles the frontend survey shortcode.
 *
 * @package StackBoost\ForSupportCandy\Modules\AfterTicketSurvey
 */
class Shortcode {

	/** @var string The name of the survey submissions table. */
	private string $survey_submissions_table_name;

	/** @var string The name of the survey answers table. */
	private string $survey_answers_table_name;

    /** @var string The name of the questions table. */
    private string $questions_table_name;

    /** @var string The name of the dropdown options table. */
    private string $dropdown_options_table_name;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->survey_submissions_table_name = $wpdb->prefix . 'stackboost_ats_survey_submissions';
		$this->survey_answers_table_name   = $wpdb->prefix . 'stackboost_ats_survey_answers';
        $this->questions_table_name        = $wpdb->prefix . 'stackboost_ats_questions';
        $this->dropdown_options_table_name = $wpdb->prefix . 'stackboost_ats_dropdown_options';
	}

	/**
	 * Main shortcode rendering logic.
	 *
	 * @return string The HTML content for the shortcode.
	 */
	public function render_shortcode(): string {
		ob_start();
		if ( ! is_admin() && isset( $_POST['stackboost_ats_submit_survey'] ) && isset( $_POST['stackboost_ats_survey_nonce'] ) && wp_verify_nonce( $_POST['stackboost_ats_survey_nonce'], 'stackboost_ats_survey_form_nonce' ) ) {
			$this->handle_submission();
		} else {
			$this->display_form();
		}
		return ob_get_clean();
	}

	/**
	 * Handles the processing of a survey submission.
	 */
	private function handle_submission() {
		global $wpdb;
		$user_id = get_current_user_id();

		$wpdb->insert(
			$this->survey_submissions_table_name,
			[
				'user_id'         => $user_id,
				'submission_date' => current_time( 'mysql' ),
			]
		);
		$submission_id = $wpdb->insert_id;

		if ( ! $submission_id ) {
			echo '<div class="stackboost-ats-error-message">There was an error submitting your survey. Please try again.</div>';
			return;
		}

		$questions = $wpdb->get_results( "SELECT id FROM {$this->questions_table_name}", ARRAY_A );
		foreach ( $questions as $question ) {
			$input_name = 'stackboost_ats_q_' . $question['id'];
			if ( isset( $_POST[ $input_name ] ) ) {
				$answer = is_array($_POST[$input_name]) ? sanitize_text_field(implode(', ', $_POST[$input_name])) : sanitize_textarea_field($_POST[$input_name]);
				$wpdb->insert(
					$this->survey_answers_table_name,
					[
						'submission_id' => $submission_id,
						'question_id'   => $question['id'],
						'answer_value'  => $answer,
					]
				);
			}
		}

		echo '<div class="stackboost-ats-success-message">Thank you for completing our survey! Your feedback is invaluable and helps us improve our services.</div>';
	}

	/**
	 * Displays the HTML for the survey form.
	 */
	private function display_form() {
		global $wpdb;
		$options = get_option( 'stackboost_settings', [] );

		$questions = $wpdb->get_results( "SELECT id, question_text, question_type, is_required FROM {$this->questions_table_name} ORDER BY sort_order ASC", ARRAY_A );
		if ( empty( $questions ) ) {
			echo '<p class="stackboost-ats-no-questions">No survey questions have been configured.</p>';
			return;
		}

		$prefill_ticket_id = isset( $_GET['ticket_id'] ) ? sanitize_text_field( $_GET['ticket_id'] ) : '';
		$prefill_tech_name = isset( $_GET['tech'] ) ? sanitize_text_field( $_GET['tech'] ) : '';

		?>
		<div class="stackboost-ats-survey-container">
			<p class="stackboost-ats-intro">Your feedback is important to us. Please take a moment to complete this survey.</p>
			<form method="post" class="stackboost-ats-form">
				<?php wp_nonce_field( 'stackboost_ats_survey_form_nonce', 'stackboost_ats_survey_nonce' ); ?>
				<?php foreach ( $questions as $q_num => $q ) : ?>
					<div class="stackboost-ats-form-group">
						<label for="stackboost_ats_q_<?php echo esc_attr( $q['id'] ); ?>" class="stackboost-ats-label">
							<?php echo esc_html( $q['question_text'] ); ?>
							<?php if ( $q['is_required'] ) : ?>
								<span class="stackboost-ats-required">*</span>
							<?php endif; ?>
						</label>
						<?php $this->render_question_field( $q, $options, $prefill_ticket_id, $prefill_tech_name ); ?>
					</div>
				<?php endforeach; ?>
				<button type="submit" name="stackboost_ats_submit_survey" class="stackboost-ats-submit-button">Submit Survey</button>
			</form>
		</div>
		<?php
	}

	/**
	 * Renders the correct HTML input for a given question.
	 *
	 * @param array  $question          The question data from the database.
	 * @param array  $options           The plugin's settings.
	 * @param string $prefill_ticket_id The ticket ID from the URL.
	 * @param string $prefill_tech_name The technician name from the URL.
	 */
	private function render_question_field( array $question, array $options, string $prefill_ticket_id, string $prefill_tech_name ) {
		global $wpdb;
		$input_name    = 'stackboost_ats_q_' . $question['id'];
		$required_attr = $question['is_required'] ? 'required' : '';
		$input_value   = '';

		if ( ( $options['ats_ticket_question_id'] ?? 0 ) == $question['id'] && ! empty( $prefill_ticket_id ) ) {
			$input_value = esc_attr( $prefill_ticket_id );
		}

		switch ( $question['question_type'] ) {
			case 'short_text':
				echo "<input type='text' name='{$input_name}' value='{$input_value}' class='stackboost-ats-input' {$required_attr}>";
				break;
			case 'long_text':
				echo "<textarea name='{$input_name}' rows='4' class='stackboost-ats-input' {$required_attr}></textarea>";
				break;
			case 'rating':
				echo '<div class="stackboost-ats-rating-options">';
				for ( $i = 1; $i <= 5; $i++ ) {
					echo "<label class='stackboost-ats-radio-label'><input type='radio' name='{$input_name}' value='{$i}' class='stackboost-ats-radio' {$required_attr}><span class='stackboost-ats-radio-text'>{$i}</span></label>";
				}
				echo '<span class="stackboost-ats-rating-guide">(1 = Poor, 5 = Excellent)</span></div>';
				break;
			case 'dropdown':
				$dd_options = $wpdb->get_results( $wpdb->prepare( "SELECT option_value FROM {$this->dropdown_options_table_name} WHERE question_id = %d ORDER BY sort_order ASC", $question['id'] ) );
				echo "<select name='{$input_name}' class='stackboost-ats-input' {$required_attr}>";
				echo '<option value="">-- Select --</option>';
				foreach ( $dd_options as $opt ) {
					$selected = selected( strtolower( $prefill_tech_name ), strtolower( $opt->option_value ), false );
					if ( ( $options['ats_technician_question_id'] ?? 0 ) == $question['id'] && ! empty( $prefill_tech_name ) ) {
						echo '<option value="' . esc_attr( $opt->option_value ) . '" ' . $selected . '>' . esc_html( $opt->option_value ) . '</option>';
					} else {
						echo '<option value="' . esc_attr( $opt->option_value ) . '">' . esc_html( $opt->option_value ) . '</option>';
					}
				}
				echo '</select>';
				break;
		}
	}
}