<?php

namespace StackBoost\ForSupportCandy\Modules\AfterTicketSurvey;

/**
 * Repository for managing database interactions for the After Ticket Survey module.
 *
 * Encapsulates all SQL queries to ensure centralization and reduce linter exclusions.
 *
 * @package StackBoost\ForSupportCandy\Modules\AfterTicketSurvey
 */
// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Repository pattern encapsulates DB access.
class Repository {


	/** @var string The name of the questions table. */
	private string $questions_table_name;

	/** @var string The name of the dropdown options table. */
	private string $dropdown_options_table_name;

	/** @var string The name of the survey submissions table. */
	private string $survey_submissions_table_name;

	/** @var string The name of the survey answers table. */
	private string $survey_answers_table_name;

	/** @var string The name of the question categories table. */
	private string $question_categories_table_name;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->question_categories_table_name = $wpdb->prefix . 'stackboost_ats_question_categories';
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
		$safe_categories_table = $this->question_categories_table_name;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is derived from trusted property.
		return $wpdb->get_results( "SELECT q.*, c.name as category_name FROM `{$safe_table}` q LEFT JOIN `{$safe_categories_table}` c ON q.category_id = c.id ORDER BY q.sort_order ASC", ARRAY_A ) ?: [];
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
		$safe_categories_table = $this->question_categories_table_name;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is derived from trusted property.
		return $wpdb->get_row( $wpdb->prepare( "SELECT q.*, c.name as category_name FROM `{$safe_table}` q LEFT JOIN `{$safe_categories_table}` c ON q.category_id = c.id WHERE q.id = %d", $id ), ARRAY_A );
	}

	/**
	 * Get max sort order.
	 *
	 * @return int Max sort order.
	 */
	public function get_max_sort_order(): int {
		global $wpdb;
		$safe_table = $this->questions_table_name;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is derived from trusted property.
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
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is derived from trusted property.
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
	 * Get all categories.
	 *
	 * @return array List of categories.
	 */
	public function get_categories(): array {
		global $wpdb;
		$safe_table = $this->question_categories_table_name;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is derived from trusted property.
		return $wpdb->get_results( "SELECT * FROM `{$safe_table}` ORDER BY name ASC", ARRAY_A ) ?: [];
	}

	/**
	 * Get specific category by ID.
	 *
	 * @param int $id Category ID.
	 * @return array|null Category data or null.
	 */
	public function get_category( int $id ): ?array {
		global $wpdb;
		$safe_table = $this->question_categories_table_name;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is derived from trusted property.
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$safe_table}` WHERE id = %d", $id ), ARRAY_A );
	}

	/**
	 * Insert a new category.
	 *
	 * @param array $data Category data.
	 * @return int|false Inserted ID or false.
	 */
	public function insert_category( array $data ) {
		global $wpdb;
		$result = $wpdb->insert( $this->question_categories_table_name, $data );
		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Update a category.
	 *
	 * @param int   $id   Category ID.
	 * @param array $data Update data.
	 * @return int|false Result or false.
	 */
	public function update_category( int $id, array $data ) {
		global $wpdb;
		return $wpdb->update( $this->question_categories_table_name, $data, [ 'id' => $id ] );
	}

	/**
	 * Delete a category.
	 *
	 * @param int $id Category ID.
	 * @return int|false Result or false.
	 */
	public function delete_category( int $id ) {
		global $wpdb;
		return $wpdb->delete( $this->question_categories_table_name, [ 'id' => $id ] );
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
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is derived from trusted property.
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
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is derived from trusted property.
		return $wpdb->get_results( "SELECT id, submission_date FROM `{$safe_table}` ORDER BY submission_date DESC", ARRAY_A ) ?: [];
	}

	/**
	 * Get submissions with user details, optionally filtered by date and agent.
	 *
	 * @param string $agent_id   Optional. Agent ID to filter by.
	 * @param string $start_date Optional. Start date (YYYY-MM-DD).
	 * @param string $end_date   Optional. End date (YYYY-MM-DD).
	 * @return array List of submissions with display name.
	 */
	public function get_submissions_with_users( string $agent_id = '', string $start_date = '', string $end_date = '' ): array {
		global $wpdb;
		$safe_table = $this->survey_submissions_table_name;
		$safe_answers = $this->survey_answers_table_name;

		$sql = "SELECT s.*, u.display_name FROM `{$safe_table}` s LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID";
		$where = [];
		$args = [];

		if ( ! empty( $start_date ) && ! empty( $end_date ) ) {
			$start_dt = gmdate( 'Y-m-d 00:00:00', strtotime( $start_date ) );
			$end_dt   = gmdate( 'Y-m-d 23:59:59', strtotime( $end_date ) );
			$where[] = "s.submission_date >= %s AND s.submission_date <= %s";
			$args[] = $start_dt;
			$args[] = $end_dt;
		}

		if ( ! empty( $agent_id ) ) {
			$ticket_question_id = $this->get_ticket_number_question_id();
			if ( $ticket_question_id ) {
				$tickets_table = $wpdb->prefix . 'psmsc_tickets';
				// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				if ( $wpdb->get_var("SHOW TABLES LIKE '{$tickets_table}'") !== $tickets_table ) {
					$tickets_table = $wpdb->prefix . 'wpsc_tickets';
				}

				$sql .= " INNER JOIN `{$safe_answers}` a ON s.id = a.submission_id";
				$sql .= " INNER JOIN `{$tickets_table}` t ON a.answer_value = t.id";
				$where[] = "a.question_id = %d";
				$args[] = $ticket_question_id;
				$where[] = "FIND_IN_SET(%d, REPLACE(t.assigned_agent, '|', ',')) > 0";
				$args[] = $agent_id;
			}
		}

		if ( ! empty( $where ) ) {
			$sql .= " WHERE " . implode( " AND ", $where );
		}

		$sql .= " ORDER BY s.submission_date DESC";

		if ( ! empty( $args ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$sql = $wpdb->prepare( $sql, $args );
		}

		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $sql, ARRAY_A ) ?: [];
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

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic table name and IN clause placeholders.
		$wpdb->query( $wpdb->prepare( "DELETE FROM `{$safe_submissions}` WHERE id IN ($placeholders)", $ids ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic table name and IN clause placeholders.
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
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is derived from trusted property.
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
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is derived from trusted property.
		return $wpdb->get_results( $wpdb->prepare( "SELECT question_id, answer_value FROM `{$safe_answers}` WHERE submission_id = %d", $submission_id ), OBJECT_K ) ?: [];
	}

}
