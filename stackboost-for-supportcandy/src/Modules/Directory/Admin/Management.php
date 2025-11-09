<?php
/**
 * Admin Management for the Directory module.
 *
 * @package StackBoost
 * @subpackage Modules\Directory\Admin
 */

namespace StackBoost\ForSupportCandy\Modules\Directory\Admin;

use StackBoost\ForSupportCandy\Modules\Directory\Data\CustomPostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Management class.
 */
class Management {

	/**
	 * Render the management page.
	 */
	public static function render_management_page() {
		?>
		<div class="wrap">
			<?php self::render_import_section(); ?>
			<hr>
			<?php self::render_clear_section(); ?>
			<hr>
			<?php self::render_fresh_start_section(); ?>
		</div>
		<?php
	}

	/**
	 * AJAX handler for clearing staff data.
	 */
	public static function ajax_clear_data() {
		check_ajax_referer( 'stackboost_directory_clear_db_nonce', 'nonce' );

		if ( ! self::can_user_manage() ) {
			wp_send_json_error( 'Permission denied.' );
		}

		$cpts = new CustomPostTypes();
		$posts = get_posts( array(
			'post_type' => $cpts->post_type,
			'post_status' => 'any',
			'numberposts' => -1,
			'fields' => 'ids',
		) );

		foreach ( $posts as $post_id ) {
			wp_delete_post( $post_id, true );
		}

		wp_send_json_success( 'All staff data has been cleared.' );
	}

	/**
	 * Render the import section.
	 */
	private static function render_import_section() {
		?>
		<h2><?php esc_html_e( 'Import Staff from CSV', 'stackboost-for-supportcandy' ); ?></h2>
		<p><?php esc_html_e( 'Upload a CSV file to import staff members. The CSV should have the following columns: Name, Email, Office Phone, Extension, Mobile Phone, Job Title, Department/Program.', 'stackboost-for-supportcandy' ); ?></p>
		<form id="stackboost-csv-import-form" method="post" enctype="multipart/form-data">
			<p>
				<input type="file" name="csv_file" id="csv_file" accept=".csv">
			</p>
			<p>
				<input type="submit" name="submit" class="button button-primary" value="<?php esc_attr_e( 'Import', 'stackboost-for-supportcandy' ); ?>">
			</p>
			<div id="import-progress"></div>
		</form>
		<?php
	}

	/**
	 * Render the clear section.
	 */
	private static function render_clear_section() {
		?>
		<h2><?php esc_html_e( 'Clear Directory Data', 'stackboost-for-supportcandy' ); ?></h2>
		<p><?php esc_html_e( 'This will delete all staff members from the directory, but not locations or departments.', 'stackboost-for-supportcandy' ); ?></p>
		<button id="stackboost-clear-db-button" class="button"><?php esc_html_e( 'Clear Staff Data', 'stackboost-for-supportcandy' ); ?></button>
		<div id="clear-progress"></div>
		<?php
	}

	/**
	 * Render the fresh start section.
	 */
	private static function render_fresh_start_section() {
		?>
		<h2><?php esc_html_e( 'Fresh Start', 'stackboost-for-supportcandy' ); ?></h2>
		<p class="description" style="color: red; font-weight: bold;">
			<?php esc_html_e( 'WARNING: This is a destructive action. It will permanently delete all staff, locations, and departments, and clear the trash. This cannot be undone.', 'stackboost-for-supportcandy' ); ?>
		</p>
		<p>
			<button id="stackboost-fresh-start-button" class="button button-danger"><?php esc_html_e( 'Initiate Fresh Start', 'stackboost-for-supportcandy' ); ?></button>
		</p>
		<div id="fresh-start-progress"></div>
		<?php
	}

	/**
	 * Register AJAX actions.
	 */
	public static function register_ajax_actions() {
		add_action( 'wp_ajax_stackboost_import_csv', array( __CLASS__, 'ajax_import_csv' ) );
		add_action( 'wp_ajax_stackboost_directory_fresh_start', array( __CLASS__, 'ajax_fresh_start' ) );
		add_action( 'wp_ajax_stackboost_directory_clear_data', array( __CLASS__, 'ajax_clear_data' ) );
	}

	/**
	 * AJAX handler for importing staff from a CSV file.
	 */
	public static function ajax_import_csv() {
		check_ajax_referer( 'stackboost_directory_csv_import', 'nonce' );

		if ( ! self::can_user_manage() ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
		}

		if ( ! isset( $_FILES['csv_file'] ) || ! file_exists( $_FILES['csv_file']['tmp_name'] ) ) {
			wp_send_json_error( array( 'message' => 'CSV file not found.' ), 400 );
		}

		$file_path = sanitize_text_field( $_FILES['csv_file']['tmp_name'] );
		$handle = fopen( $file_path, 'r' );

		if ( false === $handle ) {
			wp_send_json_error( array( 'message' => 'Could not open CSV file.' ), 500 );
		}

		// Skip header row
		fgetcsv( $handle );

		$cpts = new CustomPostTypes();
		$imported_count = 0;
		$skipped_count = 0;
		$skipped_details = array();

		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			$name = sanitize_text_field( $row[0] );
			$email = sanitize_email( $row[1] );
			$office_phone = preg_replace( '/\D/', '', $row[2] );
			$extension = sanitize_text_field( $row[3] );
			$mobile_phone = preg_replace( '/\D/', '', $row[4] );
			$job_title = sanitize_text_field( $row[5] );
			$department = sanitize_text_field( $row[6] );

			if ( empty( $name ) ) {
				$skipped_count++;
				$skipped_details[] = array(
					'reason' => 'Missing Name',
					'data'   => implode( ', ', $row ),
				);
				continue;
			}

			$post_data = array(
				'post_title'  => $name,
				'post_type'   => $cpts->post_type,
				'post_status' => 'publish',
				'meta_input'  => array(
					'_stackboost_email_address' => $email,
					'_stackboost_phone_number'  => $office_phone,
					'_stackboost_job_title'     => $job_title,
					'_stackboost_department_ids' => $department,
					'_active'                   => 'Yes',
					'_last_updated_by'          => 'CSV Import',
					'_last_updated_on'     => date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
				),
			);

			$post_id = wp_insert_post( $post_data );

			if ( is_wp_error( $post_id ) ) {
				$skipped_count++;
				$skipped_details[] = array(
					'reason' => 'Failed to create post',
					'data'   => $name,
				);
			} else {
				$imported_count++;
			}
		}

		fclose( $handle );

		wp_send_json_success( array(
			'message'         => sprintf( '%d entries imported successfully.', $imported_count ),
			'skipped_count'   => $skipped_count,
			'skipped_details' => $skipped_details,
		) );
	}

	/**
	 * AJAX handler for the fresh start.
	 */
	public static function ajax_fresh_start() {
		check_ajax_referer( 'stackboost_directory_fresh_start_nonce', 'nonce' );

		// Double-check permissions.
		if ( ! self::can_user_manage() ) {
			wp_send_json_error( 'Permission denied.' );
		}

		$cpts = new CustomPostTypes();
		$post_types = array(
			$cpts->post_type,
			$cpts->location_post_type,
			$cpts->department_post_type,
		);

		foreach ( $post_types as $post_type ) {
			$posts = get_posts( array(
				'post_type' => $post_type,
				'post_status' => 'any',
				'numberposts' => -1,
				'fields' => 'ids',
			) );

			foreach ( $posts as $post_id ) {
				wp_delete_post( $post_id, true ); // true to force delete and bypass trash.
			}
		}

		wp_send_json_success( 'All directory data has been permanently deleted.' );
	}

	/**
	 * Check if the current user has management capabilities.
	 *
	 * @return bool
	 */
	public static function can_user_manage(): bool {
		$options = get_option( Settings::OPTION_NAME, array() );
		$management_roles = $options['management_roles'] ?? array( 'administrator' );
		$user = wp_get_current_user();
		return ! empty( array_intersect( $user->roles, $management_roles ) );
	}
}