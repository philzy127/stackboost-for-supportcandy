<?php

namespace StackBoost\ForSupportCandy\Modules\AfterTicketSurvey;

/**
 * Repository for managing database interactions for the After Ticket Survey module.
 *
 * Encapsulates all SQL queries to ensure centralization and reduce linter exclusions.
 *
 * @package StackBoost\ForSupportCandy\Modules\AfterTicketSurvey
 */
class Repository {


	/** @var string The name of the questions table. */
	private string $questions_table_name;

	/** @var string The name of the dropdown options table. */
	private string $dropdown_options_table_name;

	/** @var string The name of the survey submissions table. */
	private string $survey_submissions_table_name;

	/** @var string The name of the survey answers table. */
	private string $survey_answers_table_name;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->questions_table_name          = $wpdb->prefix . 'stackboost_ats_questions';
		$this->dropdown_options_table_name   = $wpdb->prefix . 'stackboost_ats_dropdown_options';
		$this->survey_submissions_table_name = $wpdb->prefix . 'stackboost_ats_survey_submissions';
		$this->survey_answers_table_name     = $wpdb->prefix . 'stackboost_ats_survey_answers';
	}

	/**
	 * Get all questions ordered by sort order.
	 *
	 * @return array List of questions.
	 */
	public function get_questions(): array {
		global $wpdb;
		$safe_table = $this->questions_table_name;
		return $wpdb->get_results( "SELECT * FROM `{$safe_table}` ORDER BY sort_order ASC", ARRAY_A ) ?: [];
	}

	/**
	 * Get specific question by ID.
	 *
	 * @param int $id Question ID.
	 * @return array|null Question data or null.
	 */
	public function get_question( int $id ): ?array {
		global $wpdb;
		$safe_table = $this->questions_table_name;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$safe_table}` WHERE id = %d", $id ), ARRAY_A );
	}

	/**
	 * Get max sort order.
	 *
	 * @return int Max sort order.
	 */
	public function get_max_sort_order(): int {
		global $wpdb;
		$safe_table = $this->questions_table_name;
		return (int) $wpdb->get_var( "SELECT MAX(sort_order) FROM `{$safe_table}`" );
	}

	/**
	 * Get existing ticket number question ID.
	 *
	 * @return int|null ID or null.
	 */
	public function get_ticket_number_question_id(): ?int {
		global $wpdb;
		$safe_table = $this->questions_table_name;
		return $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$safe_table}` WHERE question_type = %s", 'ticket_number' ) );
	}

	/**
	 * Insert a new question.
	 *
	 * @param array $data Question data.
	 * @return int|false Inserted ID or false.
	 */
	public function insert_question( array $data ) {
		global $wpdb;
		$result = $wpdb->insert( $this->questions_table_name, $data );
		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Update a question.
	 *
	 * @param int   $id   Question ID.
	 * @param array $data Update data.
	 * @return int|false Result or false.
	 */
	public function update_question( int $id, array $data ) {
		global $wpdb;
		return $wpdb->update( $this->questions_table_name, $data, [ 'id' => $id ] );
	}

	/**
	 * Delete a question.
	 *
	 * @param int $id Question ID.
	 * @return int|false Result or false.
	 */
	public function delete_question( int $id ) {
		global $wpdb;
		return $wpdb->delete( $this->questions_table_name, [ 'id' => $id ] );
	}

	/**
	 * Get dropdown options for a question.
	 *
	 * @param int $question_id Question ID.
	 * @return array List of options.
	 */
	public function get_dropdown_options( int $question_id ): array {
		global $wpdb;
		$safe_table = $this->dropdown_options_table_name;
		return $wpdb->get_results( $wpdb->prepare( "SELECT option_value FROM `{$safe_table}` WHERE question_id = %d ORDER BY sort_order ASC", $question_id ), ARRAY_A ) ?: [];
	}

	/**
	 * Delete dropdown options for a question.
	 *
	 * @param int $question_id Question ID.
	 * @return int|false Result or false.
	 */
	public function delete_dropdown_options( int $question_id ) {
		global $wpdb;
		return $wpdb->delete( $this->dropdown_options_table_name, [ 'question_id' => $question_id ] );
	}

	/**
	 * Insert a dropdown option.
	 *
	 * @param array $data Option data.
	 * @return int|false Result or false.
	 */
	public function insert_dropdown_option( array $data ) {
		global $wpdb;
		return $wpdb->insert( $this->dropdown_options_table_name, $data );
	}

	/**
	 * Get all submissions.
	 *
	 * @return array List of submissions.
	 */
	public function get_submissions(): array {
		global $wpdb;
		$safe_table = $this->survey_submissions_table_name;
		return $wpdb->get_results( "SELECT id, submission_date FROM `{$safe_table}` ORDER BY submission_date DESC", ARRAY_A ) ?: [];
	}

	/**
	 * Get submissions with user details.
	 *
	 * @return array List of submissions with display name.
	 */
	public function get_submissions_with_users(): array {
		global $wpdb;
		$safe_table = $this->survey_submissions_table_name;
		return $wpdb->get_results( "SELECT s.*, u.display_name FROM `{$safe_table}` s LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID ORDER BY submission_date DESC", ARRAY_A ) ?: [];
	}

	/**
	 * Insert a submission.
	 *
	 * @param array $data Submission data.
	 * @return int|false Inserted ID or false.
	 */
	public function insert_submission( array $data ) {
		global $wpdb;
		$result = $wpdb->insert( $this->survey_submissions_table_name, $data );
		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Delete a submission.
	 *
	 * @param int $id Submission ID.
	 * @return int|false Result.
	 */
	public function delete_submission( int $id ) {
		global $wpdb;
		return $wpdb->delete( $this->survey_submissions_table_name, [ 'id' => $id ] );
	}

	/**
	 * Bulk delete submissions.
	 *
	 * @param array $ids List of IDs.
	 */
	public function bulk_delete_submissions( array $ids ) {
		global $wpdb;
		if ( empty( $ids ) ) {
			return;
		}

		// Standard WP method for IN clauses:
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		$safe_submissions = $this->survey_submissions_table_name;
		$safe_answers     = $this->survey_answers_table_name;

		$wpdb->query( $wpdb->prepare( "DELETE FROM `{$safe_submissions}` WHERE id IN ($placeholders)", $ids ) );

		$wpdb->query( $wpdb->prepare( "DELETE FROM `{$safe_answers}` WHERE submission_id IN ($placeholders)", $ids ) );
	}

	/**
	 * Insert an answer.
	 *
	 * @param array $data Answer data.
	 * @return int|false Inserted ID or false.
	 */
	public function insert_answer( array $data ) {
		global $wpdb;
		return $wpdb->insert( $this->survey_answers_table_name, $data );
	}

	/**
	 * Get answers for a specific question.
	 *
	 * @param int $question_id Question ID.
	 * @return array List of answers.
	 */
	public function get_answers_for_question( int $question_id ): array {
		global $wpdb;
		$safe_answers = $this->survey_answers_table_name;
		return $wpdb->get_results( $wpdb->prepare( "SELECT answer_text, rating FROM `{$safe_answers}` WHERE question_id = %d", $question_id ), ARRAY_A ) ?: [];
	}

	/**
	 * Get all answers for a specific submission ID.
	 *
	 * @param int $submission_id Submission ID.
	 * @return array List of answer objects keyed by question_id.
	 */
	public function get_answers_by_submission_id( int $submission_id ): array {
		global $wpdb;
		$safe_answers = $this->survey_answers_table_name;
		return $wpdb->get_results( $wpdb->prepare( "SELECT question_id, answer_value FROM `{$safe_answers}` WHERE submission_id = %d", $submission_id ), OBJECT_K ) ?: [];
	}

}
