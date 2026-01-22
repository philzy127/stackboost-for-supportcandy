<?php
/**
 * Admin Management for the Directory module.
 *
 * @package StackBoost
 * @subpackage Modules\Directory\Admin
 */

namespace StackBoost\ForSupportCandy\Modules\Directory\Admin;

use StackBoost\ForSupportCandy\Core\Request;
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
		$theme_class = 'sb-theme-clean-tech';
		if ( class_exists( '\StackBoost\ForSupportCandy\Modules\Appearance\WordPress' ) ) {
			$theme_class = \StackBoost\ForSupportCandy\Modules\Appearance\WordPress::get_active_theme_class();
		}
		?>
		<div class="stackboost-card stackboost-card-connected">
			<h2 style="margin-top: 0; padding-top: 10px;"><?php esc_html_e( 'Data Migration', 'stackboost-for-supportcandy' ); ?></h2>
			<?php self::render_json_section(); ?>
			<hr>
			<?php self::render_import_section(); ?>
		</div>

		<div class="stackboost-card">
			<h2 style="margin-top: 0; padding-top: 10px;"><?php esc_html_e( 'Reset Database', 'stackboost-for-supportcandy' ); ?></h2>
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

		$cpts  = new CustomPostTypes();
		$posts = get_posts(
			array(
				'post_type'   => $cpts->post_type,
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);

		foreach ( $posts as $post_id ) {
			wp_delete_post( $post_id, true );
		}

		wp_send_json_success( 'All staff data has been cleared.' );
	}

	/**
	 * Render the JSON import/export section.
	 */
	private static function render_json_section() {
		?>
		<h3><?php esc_html_e( 'JSON Import / Export', 'stackboost-for-supportcandy' ); ?></h3>
		<p class="description">
			<?php esc_html_e( 'Export all directory data to a JSON file, or import data from a JSON file. ', 'stackboost-for-supportcandy' ); ?>
			<strong style="color: red;"><?php esc_html_e( 'Warning: Importing JSON will REPLACE ALL EXISTING DATA.', 'stackboost-for-supportcandy' ); ?></strong>
		</p>

		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Export', 'stackboost-for-supportcandy' ); ?></th>
				<td>
					<button id="stackboost-json-export-button" class="button button-secondary"><?php esc_html_e( 'Export JSON', 'stackboost-for-supportcandy' ); ?></button>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Import', 'stackboost-for-supportcandy' ); ?></th>
				<td>
					<form id="stackboost-json-import-form" method="post" enctype="multipart/form-data">
						<input type="file" name="json_file" id="json_file" accept=".json" required>
						<input type="submit" name="submit" class="button button-primary" value="<?php esc_attr_e( 'Import JSON (Full Replacement)', 'stackboost-for-supportcandy' ); ?>">
					</form>
					<div id="json-import-progress"></div>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render the import section.
	 */
	private static function render_import_section() {
		?>
		<h3><?php esc_html_e( 'Import Staff from CSV', 'stackboost-for-supportcandy' ); ?></h3>
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
		<h3><?php esc_html_e( 'Clear Directory Data', 'stackboost-for-supportcandy' ); ?></h3>
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
		<h3><?php esc_html_e( 'Fresh Start', 'stackboost-for-supportcandy' ); ?></h3>
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
		add_action( 'wp_ajax_stackboost_directory_import_csv', array( __CLASS__, 'ajax_import_csv' ) );
		add_action( 'wp_ajax_stackboost_directory_fresh_start', array( __CLASS__, 'ajax_fresh_start' ) );
		add_action( 'wp_ajax_stackboost_directory_clear_data', array( __CLASS__, 'ajax_clear_data' ) );
		add_action( 'wp_ajax_stackboost_directory_export_json', array( __CLASS__, 'ajax_export_json' ) );
		add_action( 'wp_ajax_stackboost_directory_import_json', array( __CLASS__, 'ajax_import_json' ) );
	}

	/**
	 * AJAX handler for exporting directory data to JSON.
	 */
	public static function ajax_export_json() {
		check_ajax_referer( 'stackboost_directory_json_export', 'nonce' );

		if ( ! self::can_user_manage() ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
		}

		$cpts = new CustomPostTypes();
		$data = array(
			'version'     => '1.0',
			'departments' => array(),
			'locations'   => array(),
			'staff'       => array(),
		);

		// Helper to get posts with meta
		$get_data = function ( $post_type ) {
			$posts = get_posts(
				array(
					'post_type'      => $post_type,
					'posts_per_page' => -1,
					'post_status'    => 'any',
				)
			);

			$result = array();
			foreach ( $posts as $post ) {
				$item = array(
					'title'   => $post->post_title,
					'status'  => $post->post_status,
					'content' => $post->post_content, // Added content
					'meta'    => get_post_meta( $post->ID ),
				);

				if ( has_post_thumbnail( $post->ID ) ) {
					$item['featured_image_url'] = wp_get_attachment_url( get_post_thumbnail_id( $post->ID ) );
				} else {
					$item['featured_image_url'] = false;
				}

				$result[] = $item;
			}
			return $result;
		};

		$data['departments'] = $get_data( $cpts->department_post_type );
		$data['locations']   = $get_data( $cpts->location_post_type );
		$data['staff']       = $get_data( $cpts->post_type );

		header( 'Content-Description: File Transfer' );
		header( 'Content-Type: application/json' );
		header( 'Content-Disposition: attachment; filename="stackboost-directory-export-' . gmdate( 'Y-m-d' ) . '.json"' );
		header( 'Expires: 0' );
		header( 'Cache-Control: must-revalidate' );
		header( 'Pragma: public' );

		echo json_encode( $data, JSON_PRETTY_PRINT );
		wp_die();
	}

	/**
	 * AJAX handler for importing directory data from JSON.
	 */
	public static function ajax_import_json() {
		check_ajax_referer( 'stackboost_directory_json_import', 'nonce' );

		// Attempt to override execution time limit for large imports.
		if ( function_exists( 'set_time_limit' ) ) {
			// phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Essential for large data imports.
			set_time_limit( 0 );
		}

		if ( ! self::can_user_manage() ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
		}

		$json_file = Request::get_file( 'json_file' );

		if ( ! $json_file || ! file_exists( $json_file['tmp_name'] ) ) {
			wp_send_json_error( array( 'message' => 'JSON file not found.' ), 400 );
		}

		stackboost_log( 'Starting Directory JSON Import...', 'directory-import' );

		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( ! WP_Filesystem() ) {
			wp_send_json_error( array( 'message' => 'Filesystem init failed.' ), 500 );
		}

		$file_content = $wp_filesystem->get_contents( sanitize_text_field( $json_file['tmp_name'] ) );
		$data         = json_decode( $file_content, true );

		if ( ! $data || ! is_array( $data ) ) {
			stackboost_log( 'Import failed: Invalid JSON file.', 'directory-import' );
			wp_send_json_error( array( 'message' => 'Invalid JSON file.' ), 400 );
		}

		// Step 1: Clear Data (Full Replacement)
		stackboost_log( 'Clearing existing directory data...', 'directory-import' );
		$cpts       = new CustomPostTypes();
		$post_types = array(
			$cpts->post_type,
			$cpts->location_post_type,
			$cpts->department_post_type,
		);

		foreach ( $post_types as $post_type ) {
			$posts = get_posts(
				array(
					'post_type'      => $post_type,
					'posts_per_page' => -1,
					'post_status'    => 'any',
					'fields'         => 'ids',
				)
			);
			foreach ( $posts as $post_id ) {
				wp_delete_post( $post_id, true );
			}
		}
		stackboost_log( 'Existing data cleared.', 'directory-import' );

		// Counters & Errors
		$counts   = array(
			'departments' => 0,
			'locations'   => 0,
			'staff'       => 0,
		);
		$failures = array();

		// Step 2: Import Departments
		if ( ! empty( $data['departments'] ) ) {
			stackboost_log( sprintf( 'Importing %d departments...', count( $data['departments'] ) ), 'directory-import' );
			foreach ( $data['departments'] as $dept ) {
				$post_data = array(
					'post_title'  => sanitize_text_field( $dept['title'] ),
					'post_type'   => $cpts->department_post_type,
					'post_status' => isset( $dept['status'] ) ? sanitize_text_field( $dept['status'] ) : 'publish',
				);
				$post_id   = wp_insert_post( $post_data );
				if ( ! is_wp_error( $post_id ) ) {
					$counts['departments']++;
					// Restore meta if present
					if ( ! empty( $dept['meta'] ) ) {
						foreach ( $dept['meta'] as $key => $values ) {
							foreach ( (array) $values as $value ) {
								add_post_meta( $post_id, $key, $value );
							}
						}
					}
				} else {
					$error_msg = "Failed to import Department '{$dept['title']}': " . $post_id->get_error_message();
					stackboost_log( $error_msg, 'directory-import' );
					$failures[] = $error_msg;
				}
			}
		}

		// Step 3: Import Locations
		$location_name_map = array(); // Map Title -> New ID
		if ( ! empty( $data['locations'] ) ) {
			stackboost_log( sprintf( 'Importing %d locations...', count( $data['locations'] ) ), 'directory-import' );
			foreach ( $data['locations'] as $loc ) {
				$post_data = array(
					'post_title'  => sanitize_text_field( $loc['title'] ),
					'post_type'   => $cpts->location_post_type,
					'post_status' => isset( $loc['status'] ) ? sanitize_text_field( $loc['status'] ) : 'publish',
				);
				$post_id   = wp_insert_post( $post_data );
				if ( ! is_wp_error( $post_id ) ) {
					$counts['locations']++;
					$location_name_map[ $loc['title'] ] = $post_id;

					// Restore meta
					if ( ! empty( $loc['meta'] ) ) {
						foreach ( $loc['meta'] as $key => $values ) {
							foreach ( (array) $values as $value ) {
								add_post_meta( $post_id, $key, $value );
							}
						}
					}
				} else {
					$error_msg = "Failed to import Location '{$loc['title']}': " . $post_id->get_error_message();
					stackboost_log( $error_msg, 'directory-import' );
					$failures[] = $error_msg;
				}
			}
		}

		// Step 4: Build Location ID Map from Staff Data (Old ID -> New Name -> New ID)
		$legacy_location_id_map = array(); // Old ID -> Location Title
		if ( ! empty( $data['staff'] ) ) {
			foreach ( $data['staff'] as $staff ) {
				if ( ! empty( $staff['meta']['_location_id'][0] ) && ! empty( $staff['meta']['_location'][0] ) ) {
					$legacy_location_id_map[ $staff['meta']['_location_id'][0] ] = $staff['meta']['_location'][0];
				}
			}
		}

		// Step 5: Import Staff
		if ( ! empty( $data['staff'] ) ) {
			$total_staff = count( $data['staff'] );
			stackboost_log( sprintf( 'Importing %d staff entries...', $total_staff ), 'directory-import' );

			foreach ( $data['staff'] as $index => $staff ) {
				// Log progress every 50 items
				if ( ( $index + 1 ) % 50 === 0 ) {
					stackboost_log( sprintf( 'Processed %d of %d staff entries...', $index + 1, $total_staff ), 'directory-import' );
				}

				$post_data = array(
					'post_title'   => sanitize_text_field( $staff['title'] ),
					'post_content' => wp_kses_post( isset( $staff['content'] ) ? $staff['content'] : '' ),
					'post_type'    => $cpts->post_type,
					'post_status'  => isset( $staff['status'] ) ? sanitize_text_field( $staff['status'] ) : 'publish',
				);
				$post_id   = wp_insert_post( $post_data );

				if ( ! is_wp_error( $post_id ) ) {
					$counts['staff']++;

					// Handle Meta
					if ( ! empty( $staff['meta'] ) ) {
						foreach ( $staff['meta'] as $key => $values ) {
							$value = $values[0]; // Take the first value

							// Map Legacy Job Title
							if ( '_chp_staff_job_title' === $key ) {
								update_post_meta( $post_id, '_stackboost_staff_job_title', sanitize_text_field( $value ) );
								continue;
							}

							// Skip keys we handle specifically or want to ignore
							if ( in_array( $key, array( '_location_id', '_location', '_thumbnail_id', '_edit_lock', '_edit_last' ) ) ) {
								continue;
							}

							add_post_meta( $post_id, $key, $value );
						}
					}

					// Resolve Location
					$new_location_id   = '';
					$new_location_name = '';

					// First try name from meta
					if ( ! empty( $staff['meta']['_location'][0] ) ) {
						$loc_name = $staff['meta']['_location'][0];
						if ( isset( $location_name_map[ $loc_name ] ) ) {
							$new_location_id   = $location_name_map[ $loc_name ];
							$new_location_name = $loc_name;
						}
					} elseif ( ! empty( $staff['meta']['_location_id'][0] ) ) {
						// Fallback to legacy ID map
						$legacy_id = $staff['meta']['_location_id'][0];
						if ( isset( $legacy_location_id_map[ $legacy_id ] ) ) {
							$loc_name = $legacy_location_id_map[ $legacy_id ];
							if ( isset( $location_name_map[ $loc_name ] ) ) {
								$new_location_id   = $location_name_map[ $loc_name ];
								$new_location_name = $loc_name;
							}
						}
					}

					if ( $new_location_id ) {
						update_post_meta( $post_id, '_location_id', $new_location_id );
						update_post_meta( $post_id, '_location', $new_location_name );
					}

					// Resolve Department
					if ( ! empty( $staff['meta']['_department_program'][0] ) ) {
						update_post_meta( $post_id, '_department_program', sanitize_text_field( $staff['meta']['_department_program'][0] ) );
					}

					// Handle Image
					if ( ! empty( $staff['featured_image_url'] ) ) {
						$image_url = $staff['featured_image_url'];
						// Extract path from URL assuming standard WP structure
						// URL: https://site.com/wp-content/uploads/2025/12/file.png
						// We want: /wp-content/uploads/2025/12/file.png

						// Simple parse: find '/wp-content/' and take everything after
						$path_pos = strpos( $image_url, '/wp-content/' );
						if ( $path_pos !== false ) {
							$rel_path = substr( $image_url, $path_pos );
							$abs_path = ABSPATH . ltrim( $rel_path, '/' );

							if ( file_exists( $abs_path ) ) {
								// Check if attachment already exists for this file
								// (Since we cleared DB, likely not, but good practice if we were merging)
								// Actually we cleared data, so we need to create new attachment post?
								// Or check if attachment post exists in DB (we didn't delete attachments)?
								// We only deleted directory CPTs. Attachments are 'attachment' post type.

								// Try to find attachment ID by GUID or similar?
								// Or just insert a new attachment linked to this file.

								// To avoid duplicates, let's search for an attachment with this GUID (URL)
								$attachment_id = attachment_url_to_postid( $image_url );

								if ( ! $attachment_id ) {
									// Create attachment
									$filetype      = wp_check_filetype( basename( $abs_path ), null );
									$attachment    = array(
										'guid'           => $image_url,
										'post_mime_type' => $filetype['type'],
										'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $abs_path ) ),
										'post_content'   => '',
										'post_status'    => 'inherit',
									);
									$attachment_id = wp_insert_attachment( $attachment, $abs_path, $post_id );
								}

								if ( $attachment_id ) {
									set_post_thumbnail( $post_id, $attachment_id );
								}
							}
						}
					}
				} else {
					$error_msg = "Failed to import Staff '{$staff['title']}': " . $post_id->get_error_message();
					stackboost_log( $error_msg, 'directory-import' );
					$failures[] = $error_msg;
				}
			}
		}

		stackboost_log( 'Import completed successfully.', 'directory-import' );

		wp_send_json_success(
			array(
				'message'  => sprintf(
					'Import Complete. Imported: %d Departments, %d Locations, %d Staff Entries.',
					$counts['departments'],
					$counts['locations'],
					$counts['staff']
				),
				'failures' => $failures,
			)
		);
	}

	/**
	 * AJAX handler for importing staff from a CSV file.
	 */
	public static function ajax_import_csv() {
		check_ajax_referer( 'stackboost_directory_csv_import', 'nonce' );

		if ( ! self::can_user_manage() ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
		}

		$csv_file = Request::get_file( 'csv_file' );

		if ( ! $csv_file || ! file_exists( $csv_file['tmp_name'] ) ) {
			wp_send_json_error( array( 'message' => 'CSV file not found.' ), 400 );
		}

		$file_path = sanitize_text_field( $csv_file['tmp_name'] );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Robust CSV parsing requires fopen.
		$handle = fopen( $file_path, 'r' );

		if ( false === $handle ) {
			wp_send_json_error( array( 'message' => 'Could not open CSV file.' ), 500 );
		}

		// Skip header row
		fgetcsv( $handle );

		$cpts            = new CustomPostTypes();
		$imported_count  = 0;
		$skipped_count   = 0;
		$skipped_details = array();

		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			$name         = isset( $row[0] ) ? sanitize_text_field( $row[0] ) : '';
			$email        = isset( $row[1] ) ? sanitize_email( $row[1] ) : '';
			$office_phone = isset( $row[2] ) ? preg_replace( '/\D/', '', $row[2] ) : '';
			$extension    = isset( $row[3] ) ? sanitize_text_field( $row[3] ) : '';
			$mobile_phone = isset( $row[4] ) ? preg_replace( '/\D/', '', $row[4] ) : '';
			$job_title    = isset( $row[5] ) ? sanitize_text_field( $row[5] ) : '';
			$department   = isset( $row[6] ) ? sanitize_text_field( $row[6] ) : '';

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
					'_email_address'              => $email,
					'_office_phone'               => $office_phone,
					'_extension'                  => $extension,
					'_mobile_phone'               => $mobile_phone,
					'_stackboost_staff_job_title' => $job_title,
					'_department_program'         => $department,
					'_active'                     => 'Yes',
					'_last_updated_by'            => 'CSV Import',
					'_last_updated_on'            => date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
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

		wp_send_json_success(
			array(
				'message'         => sprintf( '%d entries imported successfully.', $imported_count ),
				'skipped_count'   => $skipped_count,
				'skipped_details' => $skipped_details,
			)
		);
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

		$cpts       = new CustomPostTypes();
		$post_types = array(
			$cpts->post_type,
			$cpts->location_post_type,
			$cpts->department_post_type,
		);

		foreach ( $post_types as $post_type ) {
			$posts = get_posts(
				array(
					'post_type'   => $post_type,
					'post_status' => 'any',
					'numberposts' => -1,
					'fields'      => 'ids',
				)
			);

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
		$options          = get_option( Settings::OPTION_NAME, array() );
		$management_roles = $options['management_roles'] ?? array( 'administrator' );
		$user             = wp_get_current_user();
		return ! empty( array_intersect( $user->roles, $management_roles ) );
	}
}
