<?php

namespace StackBoost\ForSupportCandy\Modules\AfterTicketSurvey;

use StackBoost\ForSupportCandy\Core\Request;

/**
 * Handles AJAX requests for the After Ticket Survey module.
 *
 * @package StackBoost\ForSupportCandy\Modules\AfterTicketSurvey
 */
class Ajax {

	/** @var Repository The repository instance. */
	private Repository $repository;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->repository = new Repository();
	}

	/**
	 * Handle the request to update a question's report heading.
	 */
	public function update_report_heading() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied.' );
		}
		check_ajax_referer( 'stackboost_ats_results_nonce', 'nonce' );

		$question_id    = Request::has_post( 'question_id' ) ? (int) Request::get_post( 'question_id' ) : 0;
		$report_heading = Request::get_post( 'report_heading' );

		if ( ! $question_id ) {
			wp_send_json_error( 'Invalid question ID.' );
		}

		$result = $this->repository->update_question( $question_id, [ 'report_heading' => $report_heading ] );

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

		$question_id = Request::has_post( 'question_id' ) ? (int) Request::get_post( 'question_id' ) : 0;

		stackboost_log( "ATS get_question requested for ID: {$question_id}", 'ats' );

		if ( ! $question_id ) {
			wp_send_json_error( 'Invalid question ID.' );
		}

		$question = $this->repository->get_question( $question_id );

		if ( ! $question ) {
			stackboost_log( "ATS get_question: Question not found.", 'ats' );
			wp_send_json_error( 'Question not found.' );
		}

		if ( $question['question_type'] === 'dropdown' ) {
			$options                 = $this->repository->get_dropdown_options( $question_id );
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

		$question_id = Request::has_post( 'question_id' ) ? (int) Request::get_post( 'question_id' ) : 0;

		stackboost_log( "ATS save_question called. ID: {$question_id}", 'ats' );

		// Get the current max sort order if adding new
		$current_max_order = 0;
		if ( ! $question_id ) {
			$current_max_order = $this->repository->get_max_sort_order();
		}

		$is_required = Request::get_post( 'is_required' ) === '1' ? 1 : 0;
		$is_readonly = Request::get_post( 'is_readonly_prefill' ) === '1' ? 1 : 0;
		$sort_order  = Request::has_post( 'sort_order' ) ? (int) Request::get_post( 'sort_order' ) : ( $question_id ? 0 : $current_max_order + 1 );

		$data = [
			'question_text'       => Request::get_post( 'question_text' ),
			'question_type'       => Request::get_post( 'question_type' ),
			'is_required'         => $is_required,
			'is_readonly_prefill' => $is_readonly,
			'sort_order'          => $sort_order,
			'prefill_key'         => Request::get_post( 'prefill_key' ),
		];

		if ( empty( $data['question_text'] ) ) {
			wp_send_json_error( 'Question text is required.' );
		}

		// Highlander Rule: Only one 'ticket_number' question allowed per form.
		if ( $data['question_type'] === 'ticket_number' ) {
			$existing_id = $this->repository->get_ticket_number_question_id();
			// If one exists AND (we are creating new OR we are updating a different question)
			if ( $existing_id && ( ! $question_id || $existing_id != $question_id ) ) {
					stackboost_log( "ATS save_question failed: Highlander Rule violated. Existing ID: $existing_id", 'ats' );
					wp_send_json_error( 'Only one Ticket Number question is allowed per form.' );
			}
		}

		if ( $question_id ) {
			// Update
			$result = $this->repository->update_question( $question_id, $data );
			if ( false === $result ) {
				// stackboost_log("ATS save_question update failed. DB Error: " . $wpdb->last_error, 'ats');
				wp_send_json_error( 'Failed to update question.' );
			}
		} else {
			// Add
			if ( ! isset( $data['report_heading'] ) ) {
				$data['report_heading'] = '';
			}

			$question_id = $this->repository->insert_question( $data );
			if ( false === $question_id ) {
				wp_send_json_error( 'Failed to add question.' );
			}
		}

		// Handle Dropdown Options
		if ( $data['question_type'] === 'dropdown' ) {
			$this->repository->delete_dropdown_options( $question_id );

			if ( Request::has_post( 'dropdown_options' ) ) {
				// Request::get_post returns sanitized string.
				$options_raw = Request::get_post( 'dropdown_options' );
				$options     = array_map( 'trim', explode( ',', $options_raw ) );

				foreach ( $options as $index => $opt ) {
					if ( ! empty( $opt ) ) {
						$this->repository->insert_dropdown_option(
							[
								'question_id'  => $question_id,
								'option_value' => $opt,
								'sort_order'   => $index,
							]
						);
					}
				}
			}
		} elseif ( $data['question_type'] !== 'dropdown' ) {
				// Clean up options if type changed away from dropdown
				$this->repository->delete_dropdown_options( $question_id );
		}

		stackboost_log( "ATS save_question success. ID: {$question_id}", 'ats' );
		wp_send_json_success( [
			'id'      => $question_id,
			'message' => 'Question saved successfully.',
		] );
	}

	/**
	 * Delete a question.
	 */
	public function delete_question() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied.' );
		}
		check_ajax_referer( 'stackboost_ats_manage_questions_nonce', 'nonce' );

		$question_id = Request::has_post( 'question_id' ) ? (int) Request::get_post( 'question_id' ) : 0;

		stackboost_log( "ATS delete_question: {$question_id}", 'ats' );

		if ( ! $question_id ) {
			wp_send_json_error( 'Invalid question ID.' );
		}

		$this->repository->delete_question( $question_id );
		$this->repository->delete_dropdown_options( $question_id );

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

		// Use Request::get_post with array mapping.
		// $_POST['order'] is expected to be an array of IDs.
		// Request::get_post('order') will sanitize it using sanitize_text_field by default for each element if it's an array.
		$order = Request::get_post( 'order' );

		if ( empty( $order ) || ! is_array( $order ) ) {
			wp_send_json_error( 'Invalid order data.' );
		}

		$order = array_map( 'intval', $order );

		foreach ( $order as $position => $question_id ) {
			$this->repository->update_question( intval( $question_id ), [ 'sort_order' => intval( $position ) ] );
		}

		wp_send_json_success( 'Questions reordered successfully.' );
	}
}
