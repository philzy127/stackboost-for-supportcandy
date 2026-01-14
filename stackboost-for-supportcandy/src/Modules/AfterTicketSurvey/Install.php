<?php

namespace StackBoost\ForSupportCandy\Modules\AfterTicketSurvey;

/**
 * Handles the installation and database schema for the After Ticket Survey module.
 *
 * @package StackBoost\ForSupportCandy\Modules\AfterTicketSurvey
 */
class Install {

	/**
	 * The current database version for this module.
	 * @var string
	 */
	private string $db_version = '1.5';

	/**
	 * The name of the questions table.
	 * @var string
	 */
	private string $questions_table_name;

	/**
	 * The name of the dropdown options table.
	 * @var string
	 */
	private string $dropdown_options_table_name;

	/**
	 * The name of the survey submissions table.
	 * @var string
	 */
	private string $survey_submissions_table_name;

	/**
	 * The name of the survey answers table.
	 * @var string
	 */
	private string $survey_answers_table_name;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->questions_table_name        = $wpdb->prefix . 'stackboost_ats_questions';
		$this->dropdown_options_table_name = $wpdb->prefix . 'stackboost_ats_dropdown_options';
		$this->survey_submissions_table_name = $wpdb->prefix . 'stackboost_ats_survey_submissions';
		$this->survey_answers_table_name   = $wpdb->prefix . 'stackboost_ats_survey_answers';
	}

    /**
     * Run the installation process.
     *
     * This is intended to be called on plugin activation.
     */
    public function run_install() {
        stackboost_log('ATS: run_install triggered via activation hook.', 'ats');
        $this->install();
    }

	/**
	 * Check if the database needs to be updated and run the installer if so.
     * Includes a self-healing check for schema drift.
	 */
	public function check_db_version() {
        global $wpdb;
        $installed_ver = get_option( 'stackboost_ats_db_version' );

        // Self-healing: Check if critical columns exist. If not, force install.
        $needs_repair = false;

        // Check for 'prefill_key' and 'is_readonly_prefill' columns in questions table
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $wpdb->esc_like( $this->questions_table_name ) ) ) === $this->questions_table_name ) {
            $safe_table = $this->questions_table_name;
            // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $prefill_exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM `{$safe_table}` LIKE %s", 'prefill_key' ) );
            // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $readonly_exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM `{$safe_table}` LIKE %s", 'is_readonly_prefill' ) );

            if ( empty( $prefill_exists ) ) {
                stackboost_log("ATS: Schema Drift Detected. 'prefill_key' missing. forcing install.", 'ats');
                $needs_repair = true;
            }
            if ( empty( $readonly_exists ) ) {
                stackboost_log("ATS: Schema Drift Detected. 'is_readonly_prefill' missing. forcing install.", 'ats');
                $needs_repair = true;
            }
        } else {
            // Table doesn't exist at all
            $needs_repair = true;
        }

		if ( $needs_repair || $installed_ver !== $this->db_version ) {
            stackboost_log("ATS: DB Update/Repair needed. Installed: {$installed_ver}, Current: {$this->db_version}. Running install.", 'ats');
			$this->install();
		}
	}

	/**
	 * Create/update the necessary database tables.
	 */
	private function install() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        stackboost_log("ATS: Running dbDelta for tables.", 'ats');

		// SQL for Questions Table
        // dbDelta requires 2 spaces after PRIMARY KEY
		$sql_questions = "CREATE TABLE {$this->questions_table_name} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			question_text text NOT NULL,
			report_heading varchar(255) DEFAULT '' NOT NULL,
            prefill_key varchar(50) DEFAULT '' NOT NULL,
			question_type varchar(50) NOT NULL,
			sort_order int(11) DEFAULT 0 NOT NULL,
			is_required tinyint(1) DEFAULT 1 NOT NULL,
            is_readonly_prefill tinyint(1) DEFAULT 0 NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

        // SQL for Dropdown Options Table
		$sql_dropdown_options = "CREATE TABLE {$this->dropdown_options_table_name} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			question_id bigint(20) NOT NULL,
			option_value varchar(255) NOT NULL,
			sort_order int(11) DEFAULT 0 NOT NULL,
			PRIMARY KEY  (id),
			KEY question_id (question_id)
		) $charset_collate;";

        // SQL for Submissions Table
		$sql_submissions = "CREATE TABLE {$this->survey_submissions_table_name} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) DEFAULT 0 NOT NULL,
			submission_date datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

        // SQL for Answers Table
		$sql_answers = "CREATE TABLE {$this->survey_answers_table_name} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			submission_id bigint(20) NOT NULL,
			question_id bigint(20) NOT NULL,
			answer_value text,
			PRIMARY KEY  (id),
			KEY submission_id (submission_id),
			KEY question_id (question_id)
		) $charset_collate;";

        $result = dbDelta( [ $sql_questions, $sql_dropdown_options, $sql_submissions, $sql_answers ] );
        // stackboost_log("ATS: dbDelta result: " . print_r($result, true), 'ats');

		$this->seed_default_questions();

		update_option( 'stackboost_ats_db_version', $this->db_version );
        stackboost_log("ATS: DB Version updated to {$this->db_version}", 'ats');
	}

	/**
	 * Seed the database with default questions if it's a fresh install.
	 */
	private function seed_default_questions() {
		global $wpdb;

        // Check if table exists before querying
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $wpdb->esc_like( $this->questions_table_name ) ) ) != $this->questions_table_name ) {
            stackboost_log("ATS: seed_default_questions aborted. Table does not exist.", 'ats');
            return;
        }

        $safe_table = $this->questions_table_name;
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->get_var( "SELECT COUNT(*) FROM `{$safe_table}`" ) > 0 ) {
			return; // Don't seed if questions already exist.
		}

        stackboost_log("ATS: Seeding default questions.", 'ats');

		$default_questions = [
			[ 'text' => 'What is your ticket number?', 'type' => 'short_text', 'key' => 'ticket_id', 'required' => 1, 'order' => 0 ],
			[ 'text' => 'Who was your technician for this ticket?', 'type' => 'dropdown', 'options' => [ 'Technician A', 'Technician B', 'Technician C' ], 'required' => 1, 'order' => 1 ],
			[ 'text' => 'Overall, how would you rate the handling of your issue?', 'type' => 'rating', 'required' => 1, 'order' => 2 ],
			[ 'text' => 'Were you helped in a timely manner?', 'type' => 'rating', 'required' => 1, 'order' => 3 ],
            [ 'text' => 'Any additional comments or feedback?', 'type' => 'long_text', 'required' => 0, 'order' => 4 ],
		];

		foreach ( $default_questions as $q_data ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->insert(
				$this->questions_table_name,
				[
					'question_text' => $q_data['text'],
					'question_type' => $q_data['type'],
					'is_required'   => $q_data['required'],
					'sort_order'    => $q_data['order'],
                    'report_heading' => '', // Ensure default value
                    'prefill_key'   => $q_data['key'] ?? '',
				]
			);
			$question_id = $wpdb->insert_id;

			if ( 'dropdown' === $q_data['type'] && ! empty( $q_data['options'] ) ) {
				foreach ( $q_data['options'] as $index => $option_value ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->insert(
						$this->dropdown_options_table_name,
						[
							'question_id'  => $question_id,
							'option_value' => $option_value,
							'sort_order'   => $index,
						]
					);
				}
			}
		}
	}
}
