<?php

namespace StackBoost\ForSupportCandy\Modules\AfterTicketSurvey;

use StackBoost\ForSupportCandy\Core\Request;

/**
 * Handles the frontend survey shortcode.
 *
 * @package StackBoost\ForSupportCandy\Modules\AfterTicketSurvey
 */
class Shortcode {

	/** @var Repository The repository instance. */
	private Repository $repository;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->repository = new Repository();
	}

	/**
	 * Main shortcode rendering logic.
	 *
	 * @param array $atts Shortcode attributes.
	 *
	 * @return string The HTML content for the shortcode.
	 */
	public function render_shortcode( $atts = [] ): string {
		$atts = shortcode_atts(
			[
				'formTitle'                   => '',
				'introText'                   => 'Your feedback is important to us. Please take a moment to complete this survey.',
				'successMessage'              => 'Thank you for completing our survey! Your feedback is invaluable and helps us improve our services.',
				'submitButtonText'            => 'Submit Survey',
				'redirectUrl'                 => '',
				'layout'                      => 'list',
				'submitButtonBackgroundColor' => '',
				'submitButtonTextColor'       => '',
				'inputBackgroundColor'        => '',
				'inputTextColor'              => '',
				'formTitleColor'              => '',
				'introTextColor'              => '',
			],
			$atts
		);

		ob_start();
		// Request::get_post() handles unslash/sanitization. wp_verify_nonce handles the rest.
		$nonce = Request::get_post( 'stackboost_ats_survey_nonce' );

		if ( ! is_admin() && Request::has_post( 'stackboost_ats_submit_survey' ) && ! empty( $nonce ) && wp_verify_nonce( $nonce, 'stackboost_ats_survey_form_nonce' ) ) {
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
		$user_id = get_current_user_id();

		$submission_id = $this->repository->insert_submission(
			[
				'user_id'         => $user_id,
				'submission_date' => current_time( 'mysql' ),
			]
		);

		if ( ! $submission_id ) {
			echo '<div class="stackboost-ats-error-message">There was an error submitting your survey. Please try again.</div>';
			return;
		}

		$questions = $this->repository->get_questions();

		// VALIDATION PHASE
		$errors = [];
		foreach ( $questions as $question ) {
			$input_name = 'stackboost_ats_q_' . $question['id'];

			if ( Request::has_post( $input_name ) ) {
				$val = Request::get_post( $input_name );
				if ( is_array( $val ) ) {
					$val = implode( '', $val );
				}

				if ( 'ticket_number' === $question['question_type'] && ! is_numeric( $val ) ) {
					$errors[] = 'Ticket Number must be numeric.';
				}
			}
		}

		if ( ! empty( $errors ) ) {
			// Clean up the empty submission created above
			$this->repository->delete_submission( $submission_id );

			foreach ( $errors as $err ) {
				echo '<div class="stackboost-ats-error-message">' . esc_html( $err ) . '</div>';
			}
			$this->display_form( $atts );
			return;
		}

		// SAVING PHASE
		foreach ( $questions as $question ) {
			$input_name = 'stackboost_ats_q_' . $question['id'];

			if ( Request::has_post( $input_name ) ) {
				// Use 'textarea' type for long_text questions to preserve line breaks
				$sanitization_type = ( $question['question_type'] === 'long_text' ) ? 'textarea' : 'text';

				$post_val = Request::get_post( $input_name, '', $sanitization_type );

				$answer = '';
				if ( is_array( $post_val ) ) {
					$answer = implode( ', ', $post_val );
				} else {
					$answer = $post_val;
				}

				$this->repository->insert_answer(
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
			echo "<script>window.location.href = '" . esc_js( $url ) . "';</script>";
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
		$options = get_option( 'stackboost_settings', [] );

		$questions = $this->repository->get_questions();
		if ( empty( $questions ) ) {
			echo '<p class="stackboost-ats-no-questions">No survey questions have been configured.</p>';
			return;
		}

		// Legacy support
		$prefill_ticket_id = Request::get_get( 'ticket_id' );
		$prefill_tech_name = Request::get_get( 'tech' );

		// Layout classes
		$container_classes = 'stackboost-ats-survey-container';
		if ( 'grid' === $atts['layout'] ) {
			$container_classes .= ' stackboost-ats-layout-grid';
		}

		// Construct CSS Variables Style Block
		$css_vars = [];
		if ( ! empty( $atts['submitButtonBackgroundColor'] ) ) {
			$css_vars[] = '--ats-btn-bg: ' . esc_attr( $atts['submitButtonBackgroundColor'] ) . ';';
		}
		if ( ! empty( $atts['submitButtonTextColor'] ) ) {
			$css_vars[] = '--ats-btn-text: ' . esc_attr( $atts['submitButtonTextColor'] ) . ';';
		}
		if ( ! empty( $atts['inputBackgroundColor'] ) ) {
			$css_vars[] = '--ats-input-bg: ' . esc_attr( $atts['inputBackgroundColor'] ) . ';';
		}
		if ( ! empty( $atts['inputTextColor'] ) ) {
			$css_vars[] = '--ats-input-text: ' . esc_attr( $atts['inputTextColor'] ) . ';';
		}
		if ( ! empty( $atts['formTitleColor'] ) ) {
			$css_vars[] = '--ats-title-color: ' . esc_attr( $atts['formTitleColor'] ) . ';';
		}
		if ( ! empty( $atts['introTextColor'] ) ) {
			$css_vars[] = '--ats-intro-color: ' . esc_attr( $atts['introTextColor'] ) . ';';
		}

		$css_style_string = '';
		if ( ! empty( $css_vars ) ) {
			$css_style_string = implode( ' ', $css_vars );
		}

		?>
		<div class="<?php echo esc_attr( $container_classes ); ?>" <?php if ( $css_style_string ) echo 'style="' . esc_attr( $css_style_string ) . '"'; ?>>
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
				<button type="submit" name="stackboost_ats_submit_survey" class="stackboost-ats-submit-button"><?php echo esc_html( $atts['submitButtonText'] ); ?></button>
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
		$input_name    = 'stackboost_ats_q_' . $question['id'];
		$required_attr = $question['is_required'] ? 'required' : '';
		$input_value   = '';

		// 0. Sticky Input (Validation Errors)
		if ( Request::has_post( $input_name ) ) {
			$post_val = Request::get_post( $input_name );
			$input_value = is_array( $post_val ) ? implode( ',', $post_val ) : $post_val;
		}
		// 1. Check for specific prefill key first (Generic logic)
		elseif ( ! empty( $question['prefill_key'] ) && Request::has_get( $question['prefill_key'] ) ) {
			$input_value = Request::get_get( $question['prefill_key'] );
		}
		// 2. Fallback to legacy logic
		elseif ( ( $options['ats_ticket_question_id'] ?? 0 ) == $question['id'] && ! empty( $prefill_ticket_id ) ) {
			$input_value = esc_attr( $prefill_ticket_id );
		}

		// Validate Pre-filled Values
		$validation_failed = false;
		$best_match_value  = '';

		if ( ! empty( $input_value ) && ! Request::has_post( $input_name ) ) { // Only validate if from URL/prefill, not POST
			if ( $question['question_type'] === 'ticket_number' ) {
				if ( ! is_numeric( $input_value ) ) {
					$validation_failed = true;
				}
			} elseif ( $question['question_type'] === 'dropdown' ) {
				// We need to resolve the best match here for validation purposes
				$dd_options    = $this->repository->get_dropdown_options( $question['id'] );
				$highest_score = 0;
				$input_lower   = strtolower( $input_value );

				foreach ( $dd_options as $opt ) {
					$opt_lower = strtolower( $opt['option_value'] );
					$score     = 0;
					if ( $opt_lower === $input_lower ) {
						$score = 100;
					} elseif ( strpos( $opt_lower, $input_lower ) === 0 ) {
						$score = 50 + strlen( $input_lower );
					} elseif ( strpos( $input_lower, $opt_lower ) === 0 ) {
						$score = 50 + strlen( $opt_lower );
					} elseif ( strpos( $opt_lower, $input_lower ) !== false ) {
						$score = 10 + strlen( $input_lower );
					} elseif ( strpos( $input_lower, $opt_lower ) !== false ) {
						$score = 10 + strlen( $opt_lower );
					}

					if ( $score > $highest_score ) {
						$highest_score    = $score;
						$best_match_value = $opt['option_value'];
					}
				}

				if ( empty( $best_match_value ) ) {
					$validation_failed = true;
				}
			}
		}

		// Handle Validation Failure
		if ( $validation_failed ) {
			$input_value      = ''; // Reset invalid value
			$best_match_value = '';
			stackboost_log( "Invalid pre-fill value for field ID {$question['id']} ignored.", 'ats' );
			echo "<script>if(typeof window.stackboostLog === 'function') { window.stackboostLog('Invalid pre-fill value for field ID " . esc_js( $question['id'] ) . " ignored.', null, 'warn'); }</script>";
		}

		// Determine Read-Only State
		$is_readonly = false;
		if ( ! $validation_failed && ! empty( $input_value ) && ! empty( $question['is_readonly_prefill'] ) ) {
				$is_readonly = true;
		}

		switch ( $question['question_type'] ) {
			case 'ticket_number':
				echo '<input type="text" name="' . esc_attr( $input_name ) . '" value="' . esc_attr( $input_value ) . '" class="stackboost-ats-input" ' . esc_html( $required_attr ) . ( $is_readonly ? ' style="pointer-events: none;" tabindex="-1"' : '' ) . '>';
				break;
			case 'short_text':
				echo '<input type="text" name="' . esc_attr( $input_name ) . '" value="' . esc_attr( $input_value ) . '" class="stackboost-ats-input" ' . esc_html( $required_attr ) . ( $is_readonly ? ' style="pointer-events: none;" tabindex="-1"' : '' ) . '>';
				break;
			case 'long_text':
				// If textarea has a prefill value, put it inside the tags
				echo '<textarea name="' . esc_attr( $input_name ) . '" rows="4" class="stackboost-ats-input" ' . esc_html( $required_attr ) . ( $is_readonly ? ' style="pointer-events: none;" tabindex="-1"' : '' ) . '>' . esc_textarea( $input_value ) . '</textarea>';
				break;
			case 'rating':
				echo '<div class="stackboost-ats-rating-options" ' . ( $is_readonly ? ' style="pointer-events: none;" tabindex="-1"' : '' ) . '>';
				for ( $i = 1; $i <= 5; $i++ ) {
					// Check if prefill value matches the rating option
					$checked = ( $input_value == $i ) ? 'checked' : '';
					echo '<label class="stackboost-ats-radio-label"><input type="radio" name="' . esc_attr( $input_name ) . '" value="' . esc_attr( $i ) . '" class="stackboost-ats-radio" ' . esc_html( $required_attr ) . ' ' . esc_attr( $checked ) . '><span class="stackboost-ats-radio-text">' . esc_html( $i ) . '</span></label>';
				}
				echo '<span class="stackboost-ats-rating-guide">' . esc_html__( '(1 = Poor, 5 = Excellent)', 'stackboost-for-supportcandy' ) . '</span></div>';
				break;
			case 'dropdown':
				// Note: $dd_options and $best_match_value might already be calculated above during validation
				if ( ! isset( $dd_options ) ) {
					$dd_options = $this->repository->get_dropdown_options( $question['id'] );
				}

				// Recalculate match if we didn't do it for validation (i.e. not prefilled/readonly check)
				// Or use the one we found during validation
				if ( empty( $best_match_value ) && ! empty( $input_value ) ) {
						// Reuse logic for standard prefill without readonly constraint...
						// Actually, we can just use the same logic block.
						// Copy-paste logic here for completeness if not run above
						$highest_score = 0;
						$input_lower   = strtolower( $input_value );
					foreach ( $dd_options as $opt ) {
						$opt_lower = strtolower( $opt['option_value'] );
						$score     = 0;
						if ( $opt_lower === $input_lower ) {
							$score = 100;
						} elseif ( strpos( $opt_lower, $input_lower ) === 0 ) {
							$score = 50 + strlen( $input_lower );
						} elseif ( strpos( $input_lower, $opt_lower ) === 0 ) {
							$score = 50 + strlen( $opt_lower );
						} elseif ( strpos( $opt_lower, $input_lower ) !== false ) {
							$score = 10 + strlen( $input_lower );
						} elseif ( strpos( $input_lower, $opt_lower ) !== false ) {
							$score = 10 + strlen( $opt_lower );
						}

						if ( $score > $highest_score ) {
							$highest_score    = $score;
							$best_match_value = $opt['option_value'];
						}
					}
				}

				echo '<select name="' . esc_attr( $input_name ) . '" class="stackboost-ats-input" ' . esc_html( $required_attr ) . ( $is_readonly ? ' style="pointer-events: none;" tabindex="-1"' : '' ) . '>';
				echo '<option value="">-- Select --</option>';
				foreach ( $dd_options as $opt ) {
					echo '<option value="' . esc_attr( $opt['option_value'] ) . '" ';
					// Use fuzzy match if available
					if ( ! empty( $best_match_value ) ) {
						selected( $best_match_value, $opt['option_value'] );
					}
					// Legacy Fallback
					elseif ( ( $options['ats_technician_question_id'] ?? 0 ) == $question['id'] && ! empty( $prefill_tech_name ) ) {
						selected( strtolower( $prefill_tech_name ), strtolower( $opt['option_value'] ) );
					}
					echo '>' . esc_html( $opt['option_value'] ) . '</option>';
				}
				echo '</select>';
				break;
		}
	}
}
