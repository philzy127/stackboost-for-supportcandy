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
     * @param array $atts Shortcode attributes.
	 *
	 * @return string The HTML content for the shortcode.
	 */
	public function render_shortcode( $atts = [] ): string {
        $atts = shortcode_atts( [
            'formTitle' => '',
            'introText' => 'Your feedback is important to us. Please take a moment to complete this survey.',
            'successMessage' => 'Thank you for completing our survey! Your feedback is invaluable and helps us improve our services.',
            'submitButtonText' => 'Submit Survey',
            'redirectUrl' => '',
            'layout' => 'list',
            'submitButtonBackgroundColor' => '',
            'submitButtonTextColor' => ''
        ], $atts );

		ob_start();
		if ( ! is_admin() && isset( $_POST['stackboost_ats_submit_survey'] ) && isset( $_POST['stackboost_ats_survey_nonce'] ) && wp_verify_nonce( $_POST['stackboost_ats_survey_nonce'], 'stackboost_ats_survey_form_nonce' ) ) {
			$this->handle_submission( $atts );
		} else {
			$this->display_form( $atts );
		}
		return ob_get_clean();
	}

	/**
	 * Handles the processing of a survey submission.
     *
     * @param array $atts Attributes.
	 */
	private function handle_submission( $atts ) {
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

		$questions = $wpdb->get_results( "SELECT id, question_type FROM {$this->questions_table_name}", ARRAY_A );

		// VALIDATION PHASE
		$errors = [];
		foreach ( $questions as $question ) {
			$input_name = 'stackboost_ats_q_' . $question['id'];
			if ( isset( $_POST[ $input_name ] ) ) {
				$val = is_array( $_POST[ $input_name ] ) ? implode( '', $_POST[ $input_name ] ) : $_POST[ $input_name ];
				if ( 'ticket_number' === $question['question_type'] && ! is_numeric( $val ) ) {
					$errors[] = "Ticket Number must be numeric.";
				}
			}
		}

		if ( ! empty( $errors ) ) {
			// Clean up the empty submission created above
			$wpdb->delete( $this->survey_submissions_table_name, [ 'id' => $submission_id ] );

			foreach ( $errors as $err ) {
				echo '<div class="stackboost-ats-error-message">' . esc_html( $err ) . '</div>';
			}
			$this->display_form( $atts );
			return;
		}

		// SAVING PHASE
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

        // Handle Redirect or Success Message
        if ( ! empty( $atts['redirectUrl'] ) ) {
            $url = esc_url_raw( $atts['redirectUrl'] );
            echo "<script>window.location.href = '{$url}';</script>";
            return;
        }

		echo '<div class="stackboost-ats-success-message">' . esc_html( $atts['successMessage'] ) . '</div>';
	}

	/**
	 * Displays the HTML for the survey form.
     *
     * @param array $atts Attributes.
	 */
	private function display_form( $atts ) {
		global $wpdb;
		$options = get_option( 'stackboost_settings', [] );

		// We fetch prefill_key as well
		$questions = $wpdb->get_results( "SELECT id, question_text, question_type, is_required, prefill_key, is_readonly_prefill FROM {$this->questions_table_name} ORDER BY sort_order ASC", ARRAY_A );
		if ( empty( $questions ) ) {
			echo '<p class="stackboost-ats-no-questions">No survey questions have been configured.</p>';
			return;
		}

		// Legacy support
		$prefill_ticket_id = isset( $_GET['ticket_id'] ) ? sanitize_text_field( $_GET['ticket_id'] ) : '';
		$prefill_tech_name = isset( $_GET['tech'] ) ? sanitize_text_field( $_GET['tech'] ) : '';

        // Layout classes
        $container_classes = 'stackboost-ats-survey-container';
        if ( 'grid' === $atts['layout'] ) {
            $container_classes .= ' stackboost-ats-layout-grid';
        }

        // Button Styles
        $button_style = '';
        if ( ! empty( $atts['submitButtonBackgroundColor'] ) ) {
            $button_style .= 'background-color: ' . esc_attr( $atts['submitButtonBackgroundColor'] ) . ' !important;';
        }
        if ( ! empty( $atts['submitButtonTextColor'] ) ) {
            $button_style .= 'color: ' . esc_attr( $atts['submitButtonTextColor'] ) . ' !important;';
        }

		?>
		<div class="<?php echo esc_attr( $container_classes ); ?>">
            <?php if ( ! empty( $atts['formTitle'] ) ) : ?>
                <h2 class="stackboost-ats-main-title"><?php echo esc_html( $atts['formTitle'] ); ?></h2>
            <?php endif; ?>
			<?php if ( ! empty( $atts['introText'] ) ) : ?>
                <p class="stackboost-ats-intro"><?php echo nl2br( esc_html( $atts['introText'] ) ); ?></p>
            <?php endif; ?>
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
				<button type="submit" name="stackboost_ats_submit_survey" class="stackboost-ats-submit-button" style="<?php echo esc_attr( $button_style ); ?>"><?php echo esc_html( $atts['submitButtonText'] ); ?></button>
			</form>
		</div>
		<?php
	}

	/**
	 * Renders the correct HTML input for a given question.
	 *
	 * @param array  $question          The question data from the database.
	 * @param array  $options           The plugin's settings.
	 * @param string $prefill_ticket_id The ticket ID from the URL (legacy param).
	 * @param string $prefill_tech_name The technician name from the URL (legacy param).
	 */
	private function render_question_field( array $question, array $options, string $prefill_ticket_id, string $prefill_tech_name ) {
		global $wpdb;
		$input_name    = 'stackboost_ats_q_' . $question['id'];
		$required_attr = $question['is_required'] ? 'required' : '';
		$input_value   = '';

		// 0. Sticky Input (Validation Errors)
		if ( isset( $_POST[ $input_name ] ) ) {
			$input_value = sanitize_text_field( is_array( $_POST[ $input_name ] ) ? implode( ',', $_POST[ $input_name ] ) : $_POST[ $input_name ] );
		}
		// 1. Check for specific prefill key first (Generic logic)
		elseif ( ! empty( $question['prefill_key'] ) && isset( $_GET[ $question['prefill_key'] ] ) ) {
			$input_value = sanitize_text_field( $_GET[ $question['prefill_key'] ] );
		}
		// 2. Fallback to legacy logic
		elseif ( ( $options['ats_ticket_question_id'] ?? 0 ) == $question['id'] && ! empty( $prefill_ticket_id ) ) {
			$input_value = esc_attr( $prefill_ticket_id );
		}

        // Validate Pre-filled Values
        $validation_failed = false;
        $best_match_value = '';

        if ( ! empty( $input_value ) && ! isset( $_POST[ $input_name ] ) ) { // Only validate if from URL/prefill, not POST
            if ( $question['question_type'] === 'ticket_number' ) {
                if ( ! is_numeric( $input_value ) ) {
                    $validation_failed = true;
                }
            }
            elseif ( $question['question_type'] === 'dropdown' ) {
                // We need to resolve the best match here for validation purposes
                $dd_options = $wpdb->get_results( $wpdb->prepare( "SELECT option_value FROM {$this->dropdown_options_table_name} WHERE question_id = %d ORDER BY sort_order ASC", $question['id'] ) );
                $highest_score = 0;
                $input_lower = strtolower( $input_value );

                foreach ( $dd_options as $opt ) {
                    $opt_lower = strtolower( $opt->option_value );
                    $score = 0;
                    if ( $opt_lower === $input_lower ) $score = 100;
                    elseif ( strpos( $opt_lower, $input_lower ) === 0 ) $score = 50 + strlen( $input_lower );
                    elseif ( strpos( $input_lower, $opt_lower ) === 0 ) $score = 50 + strlen( $opt_lower );
                    elseif ( strpos( $opt_lower, $input_lower ) !== false ) $score = 10 + strlen( $input_lower );
                    elseif ( strpos( $input_lower, $opt_lower ) !== false ) $score = 10 + strlen( $opt_lower );

                    if ( $score > $highest_score ) {
                        $highest_score = $score;
                        $best_match_value = $opt->option_value;
                    }
                }

                if ( empty( $best_match_value ) ) {
                    $validation_failed = true;
                }
            }
        }

        // Handle Validation Failure
        if ( $validation_failed ) {
            $input_value = ''; // Reset invalid value
            $best_match_value = '';
            stackboost_log( "Invalid pre-fill value for field ID {$question['id']} ignored.", 'ats' );
            echo "<script>if(typeof console !== 'undefined') { console.warn('[StackBoost ATS] Invalid pre-fill value for field ID {$question['id']} ignored.'); }</script>";
        }

        // Determine Read-Only State
        $readonly_style = '';
        if ( ! $validation_failed && ! empty( $input_value ) && ! empty( $question['is_readonly_prefill'] ) ) {
             $readonly_style = 'style="pointer-events: none;" tabindex="-1"';
        }

		switch ( $question['question_type'] ) {
			case 'ticket_number':
				echo "<input type='text' name='{$input_name}' value='{$input_value}' class='stackboost-ats-input' {$required_attr} {$readonly_style}>";
				break;
			case 'short_text':
				echo "<input type='text' name='{$input_name}' value='{$input_value}' class='stackboost-ats-input' {$required_attr} {$readonly_style}>";
				break;
			case 'long_text':
				// If textarea has a prefill value, put it inside the tags
				echo "<textarea name='{$input_name}' rows='4' class='stackboost-ats-input' {$required_attr} {$readonly_style}>" . esc_textarea( $input_value ) . "</textarea>";
				break;
			case 'rating':
				echo '<div class="stackboost-ats-rating-options" ' . $readonly_style . '>';
				for ( $i = 1; $i <= 5; $i++ ) {
                    // Check if prefill value matches the rating option
                    $checked = ( $input_value == $i ) ? 'checked' : '';
					echo "<label class='stackboost-ats-radio-label'><input type='radio' name='{$input_name}' value='{$i}' class='stackboost-ats-radio' {$required_attr} {$checked}><span class='stackboost-ats-radio-text'>{$i}</span></label>";
				}
				echo '<span class="stackboost-ats-rating-guide">(1 = Poor, 5 = Excellent)</span></div>';
				break;
			case 'dropdown':
                // Note: $dd_options and $best_match_value might already be calculated above during validation
                if ( ! isset( $dd_options ) ) {
				    $dd_options = $wpdb->get_results( $wpdb->prepare( "SELECT option_value FROM {$this->dropdown_options_table_name} WHERE question_id = %d ORDER BY sort_order ASC", $question['id'] ) );
                }

                // Recalculate match if we didn't do it for validation (i.e. not prefilled/readonly check)
                // Or use the one we found during validation
                if ( empty( $best_match_value ) && ! empty( $input_value ) ) {
                     // Reuse logic for standard prefill without readonly constraint...
                     // Actually, we can just use the same logic block.
                     // Copy-paste logic here for completeness if not run above
                     $highest_score = 0;
                     $input_lower = strtolower( $input_value );
                     foreach ( $dd_options as $opt ) {
                        $opt_lower = strtolower( $opt->option_value );
                        $score = 0;
                        if ( $opt_lower === $input_lower ) $score = 100;
                        elseif ( strpos( $opt_lower, $input_lower ) === 0 ) $score = 50 + strlen( $input_lower );
                        elseif ( strpos( $input_lower, $opt_lower ) === 0 ) $score = 50 + strlen( $opt_lower );
                        elseif ( strpos( $opt_lower, $input_lower ) !== false ) $score = 10 + strlen( $input_lower );
                        elseif ( strpos( $input_lower, $opt_lower ) !== false ) $score = 10 + strlen( $opt_lower );

                        if ( $score > $highest_score ) {
                            $highest_score = $score;
                            $best_match_value = $opt->option_value;
                        }
                    }
                }

                echo "<select name='{$input_name}' class='stackboost-ats-input' {$required_attr} {$readonly_style}>";
				echo '<option value="">-- Select --</option>';
				foreach ( $dd_options as $opt ) {
                    $selected = '';

                    // Use fuzzy match if available
                    if ( ! empty( $best_match_value ) ) {
                        $selected = selected( $best_match_value, $opt->option_value, false );
                    }
                    // Legacy Fallback
                    elseif ( ( $options['ats_technician_question_id'] ?? 0 ) == $question['id'] && ! empty( $prefill_tech_name ) ) {
                        $selected = selected( strtolower( $prefill_tech_name ), strtolower( $opt->option_value ), false );
                    }

					echo '<option value="' . esc_attr( $opt->option_value ) . '" ' . $selected . '>' . esc_html( $opt->option_value ) . '</option>';
				}
				echo '</select>';
				break;
		}
	}
}
