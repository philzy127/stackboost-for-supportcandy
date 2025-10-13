<?php
/**
 * StackBoost Company Directory Importer.
 *
 * This file handles the CSV import functionality for the Company Directory module.
 * It's a migration of the importer from the standalone plugin, adapted for
 * the StackBoost framework.
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
 * Importer Class
 *
 * Handles the CSV import functionality.
 */
class Importer {

	/**
	 * The custom post type slug for staff entries.
	 *
	 * @var string
	 */
	private static $staff_post_type_static;

	/**
	 * The custom post type slug for locations.
	 *
	 * @var string
	 */
	private static $location_post_type_static;

	/**
	 * The custom post type slug for departments.
	 *
	 * @var string
	 */
	private static $department_post_type_static;

	/**
	 * Constructor.
	 */
	public function __construct( CustomPostTypes $cpts ) {
		self::$staff_post_type_static      = $cpts->post_type;
		self::$location_post_type_static   = $cpts->location_post_type;
		self::$department_post_type_static = $cpts->department_post_type;
		add_action( 'wp_ajax_stackboost_directory_import_csv', array( __CLASS__, 'handle_csv_upload' ) );
	}

	/**
	 * Render the CSV import page.
	 */
	public static function render_import_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'StackBoost Company Directory CSV Import', 'stackboost-for-supportcandy' ); ?></h1>
			<p><?php esc_html_e( 'Upload a CSV file to import ONLY NEW directory entries. Existing entries will NOT be updated. The CSV must contain the following columns:', 'stackboost-for-supportcandy' ); ?></p>
			<ul>
				<li><code>Name</code> (Required)</li>
				<li><code>Office Phone</code></li>
				<li><code>Extension</code></li>
				<li><code>Mobile Phone</code></li>
				<li><code>Location</code></li>
				<li><code>Room #</code></li>
				<li><code>Department / Program</code></li>
				<li><code>Title</code></li>
				<li><code>Email Address</code></li>
				<li><code>Active</code> (Yes/No or 1/0 - 'Yes' or '1' for active, 'No' or '0' for inactive)</li>
			</ul>
			<form id="stackboost-import-form" method="post" enctype="multipart/form-data">
				<?php wp_nonce_field( 'stackboost_directory_csv_import', 'nonce' ); ?>
				<input type="file" name="csv_file" id="csv_file" accept=".csv" required />
				<p class="submit">
					<input type="submit" name="submit" id="submit" class="button button-primary" value="<?php esc_attr_e( 'Upload and Import', 'stackboost-for-supportcandy' ); ?>">
				</p>
				<div id="stackboost-import-message" style="margin-top: 15px;"></div>
				<div id="stackboost-import-progress" style="display:none; margin-top: 20px;">
					<progress id="stackboost-progress-bar" value="0" max="100" style="width:100%;"></progress>
					<p id="stackboost-progress-text">0%</p>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Handle the CSV file upload and import process.
	 */
	public static function handle_csv_upload() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have sufficient permissions to perform this action.', 'stackboost-for-supportcandy' ) ) );
		}

		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'stackboost_directory_csv_import' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'stackboost-for-supportcandy' ) ) );
		}

		if ( ! isset( $_FILES['csv_file'] ) || empty( $_FILES['csv_file']['tmp_name'] ) ) {
			$error_message = __( 'No file uploaded or file is empty.', 'stackboost-for-supportcandy' );
			if ( isset( $_FILES['csv_file'] ) && UPLOAD_ERR_OK !== $_FILES['csv_file']['error'] ) {
				$error_message .= ' PHP Upload Error Code: ' . $_FILES['csv_file']['error'];
			}
			wp_send_json_error( array( 'message' => $error_message ) );
		}

		$file = $_FILES['csv_file'];

		if ( UPLOAD_ERR_OK !== $file['error'] ) {
			wp_send_json_error( array( 'message' => __( 'File upload error: ', 'stackboost-for-supportcandy' ) . esc_html( $file['error'] ) ) );
		}

		$mimes = array( 'text/csv', 'application/csv', 'text/plain' );
		if ( ! in_array( $file['type'], $mimes, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid file type. Please upload a CSV file.', 'stackboost-for-supportcandy' ) ) );
		}

		$filepath = $file['tmp_name'];
		$handle   = fopen( $filepath, 'r' );

		if ( false === $handle ) {
			wp_send_json_error( array( 'message' => __( 'Could not open CSV file.', 'stackboost-for-supportcandy' ) ) );
		}

		$header           = fgetcsv( $handle );
		$expected_headers = array(
			'Name',
			'Office Phone',
			'Extension',
			'Mobile Phone',
			'Location',
			'Room #',
			'Department / Program',
			'Title',
			'Email Address',
			'Active',
		);

		$header                 = array_map( 'trim', $header );
		$header_lower           = array_map( 'strtolower', $header );
		$expected_headers_lower = array_map( 'strtolower', $expected_headers );

		$missing_headers = array_diff( $expected_headers_lower, $header_lower );
		if ( count( $missing_headers ) > 0 ) {
			wp_send_json_error( array( 'message' => __( 'CSV header mismatch. Missing required columns: ', 'stackboost-for-supportcandy' ) . implode( ', ', $missing_headers ) ) );
			fclose( $handle );
			return;
		}

		$imported_count      = 0;
		$skipped_count       = 0;
		$skipped_details     = array();
		$current_import_date = current_time( 'Y-m-d' );

		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			if ( empty( array_filter( $row ) ) ) {
				$skipped_count++;
				$skipped_details[] = array(
					'reason' => __( 'Skipped empty row', 'stackboost-for-supportcandy' ),
					'data'   => '',
				);
				continue;
			}
			if ( count( $row ) !== count( $header ) ) {
				$skipped_count++;
				$skipped_details[] = array(
					'reason' => __( 'Incorrect number of columns', 'stackboost-for-supportcandy' ),
					'data'   => implode( ', ', $row ),
				);
				continue;
			}

			$data = array_combine( $header, $row );

			$name          = sanitize_text_field( $data['Name'] );
			$location_name = sanitize_text_field( $data['Location'] );

			if ( empty( $name ) ) {
				$skipped_count++;
				$skipped_details[] = array(
					'reason' => __( 'Missing Name (required)', 'stackboost-for-supportcandy' ),
					'data'   => __( 'Entry with incomplete data', 'stackboost-for-supportcandy' ),
				);
				continue;
			}

			$post_data = array(
				'post_title'  => $name,
				'post_status' => 'publish',
				'post_type'   => self::$staff_post_type_static,
			);

			$inserted_staff_post_id = wp_insert_post( $post_data, true );

			if ( is_wp_error( $inserted_staff_post_id ) || 0 === $inserted_staff_post_id ) {
				$error_message = is_wp_error( $inserted_staff_post_id ) ? $inserted_staff_post_id->get_error_message() : 'wp_insert_post returned 0.';
				$skipped_count++;
				$skipped_details[] = array(
					'reason' => __( 'Staff post insertion failed', 'stackboost-for-supportcandy' ),
					'data'   => 'Name: ' . $name . ' | Error: ' . $error_message . ' | Post Data: ' . wp_json_encode( $post_data ),
				);
				continue;
			}

			$imported_count++;

			global $wpdb;
			$max_id        = $wpdb->get_var( "SELECT MAX(CAST(meta_value AS UNSIGNED)) FROM {$wpdb->postmeta} WHERE meta_key = '_unique_id'" );
			$new_unique_id = ( $max_id ) ? $max_id + 1 : 1;
			update_post_meta( $inserted_staff_post_id, '_unique_id', $new_unique_id );

			$location_id_for_staff = '';
			$processed_location_name = trim( sanitize_text_field( $data['Location'] ) );

			if ( ! empty( $processed_location_name ) ) {
				$existing_location_query = new \WP_Query(
					array(
						'post_type'      => self::$location_post_type_static,
						'title'          => $processed_location_name,
						'post_status'    => 'publish',
						'posts_per_page' => 1,
					)
				);
				$existing_location_post  = $existing_location_query->get_posts();

				if ( empty( $existing_location_post ) ) {
					$new_location_post_data = array(
						'post_title'  => $processed_location_name,
						'post_status' => 'publish',
						'post_type'   => self::$location_post_type_static,
					);
					$new_location_id        = wp_insert_post( $new_location_post_data );

					if ( is_wp_error( $new_location_id ) || 0 === $new_location_id ) {
						$error_message = is_wp_error( $new_location_id ) ? $new_location_id->get_error_message() : 'wp_insert_post returned 0.';
						$skipped_details[] = array(
							'reason' => __( 'Failed to create new location', 'stackboost-for-supportcandy' ),
							'data'   => 'Name: ' . $processed_location_name . ' | Error: ' . $error_message . ' | Post Data: ' . wp_json_encode( $new_location_post_data ),
						);
					} else {
						update_post_meta( $new_location_id, '_needs_completion', 'yes' );
						$location_id_for_staff = $new_location_id;
					}
				} else {
					$location_id_for_staff = $existing_location_post[0]->ID;
				}
			}

			update_post_meta( $inserted_staff_post_id, '_office_phone', sanitize_text_field( $data['Office Phone'] ) );
			update_post_meta( $inserted_staff_post_id, '_extension', sanitize_text_field( $data['Extension'] ) );
			update_post_meta( $inserted_staff_post_id, '_mobile_phone', sanitize_text_field( $data['Mobile Phone'] ) );
			update_post_meta( $inserted_staff_post_id, '_location', $location_name );
			if ( ! empty( $location_id_for_staff ) ) {
				update_post_meta( $inserted_staff_post_id, '_location_id', $location_id_for_staff );
			}
			update_post_meta( $inserted_staff_post_id, '_room_number', sanitize_text_field( $data['Room #'] ) );
			$department_name = sanitize_text_field( $data['Department / Program'] );
			if ( ! empty( $department_name ) ) {
				$existing_department_query = new \WP_Query(
					array(
						'post_type'      => self::$department_post_type_static,
						'title'          => $department_name,
						'post_status'    => 'publish',
						'posts_per_page' => 1,
					)
				);
				$existing_department     = $existing_department_query->get_posts();

				if ( empty( $existing_department ) ) {
					$new_department_id = wp_insert_post(
						array(
							'post_title'  => $department_name,
							'post_status' => 'publish',
							'post_type'   => self::$department_post_type_static,
						)
					);
					if ( is_wp_error( $new_department_id ) || 0 === $new_department_id ) {
						$error_message = is_wp_error( $new_department_id ) ? $new_department_id->get_error_message() : 'wp_insert_post returned 0.';
						$skipped_details[] = array(
							'reason' => __( 'Failed to create new department', 'stackboost-for-supportcandy' ),
							'data'   => 'Name: ' . $department_name . ' | Error: ' . $error_message . ' | Post Data: ' . wp_json_encode( array( 'post_title' => $department_name, 'post_status' => 'publish', 'post_type' => self::$department_post_type_static ) ),
						);
					}
				}
			}
			update_post_meta( $inserted_staff_post_id, '_department_program', $department_name );
			update_post_meta( $inserted_staff_post_id, '_chp_staff_job_title', sanitize_text_field( $data['Title'] ) );
			update_post_meta( $inserted_staff_post_id, '_email_address', sanitize_email( $data['Email Address'] ) );

			$active_status_from_csv = sanitize_text_field( $data['Active'] );
			$active_meta_value      = 'No';

			if ( in_array( strtolower( $active_status_from_csv ), array( 'yes', '1', 'true' ), true ) ) {
				$active_meta_value = 'Yes';
				update_post_meta( $inserted_staff_post_id, '_active_as_of_date', $current_import_date );
				delete_post_meta( $inserted_staff_post_id, '_planned_exit_date' );
			} else {
				update_post_meta( $inserted_staff_post_id, '_planned_exit_date', $current_import_date );
				delete_post_meta( $inserted_staff_post_id, '_active_as_of_date' );
			}
			update_post_meta( $inserted_staff_post_id, '_active', $active_meta_value );

			$current_user = wp_get_current_user();
			if ( $current_user && $current_user->display_name ) {
				update_post_meta( $inserted_staff_post_id, '_last_updated_by', sanitize_text_field( $current_user->display_name ) );
			} else {
				update_post_meta( $inserted_staff_post_id, '_last_updated_by', __( 'System (Import)', 'stackboost-for-supportcandy' ) );
			}
			update_post_meta( $inserted_staff_post_id, '_last_updated_on', date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) );
		}

		fclose( $handle );

		wp_send_json_success(
			array(
				'message'         => sprintf( __( '%d new entries imported, %d entries skipped.', 'stackboost-for-supportcandy' ), $imported_count, $skipped_count ),
				'imported_count'  => $imported_count,
				'skipped_count'   => $skipped_count,
				'skipped_details' => $skipped_details,
			)
		);
	}
}