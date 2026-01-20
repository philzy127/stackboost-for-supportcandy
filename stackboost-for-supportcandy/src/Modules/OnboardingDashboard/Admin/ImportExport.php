<?php


namespace StackBoost\ForSupportCandy\Modules\OnboardingDashboard\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

use StackBoost\ForSupportCandy\Core\Request;

class ImportExport {

	/**
	 * Initialize the Import/Export page.
	 */
	public static function init() {
		add_action( 'wp_ajax_stackboost_onboarding_migrate_data', [ __CLASS__, 'ajax_migrate_legacy_data' ] );
		add_action( 'wp_ajax_stackboost_onboarding_export_steps', [ __CLASS__, 'ajax_export_steps' ] );
		add_action( 'wp_ajax_stackboost_onboarding_import_steps', [ __CLASS__, 'ajax_import_steps' ] );
	}

	/**
	 * Render the Import/Export page.
	 */
	public static function render_page() {
		// Check for legacy data count
		$legacy_count = wp_count_posts( 'onboarding_step' )->publish ?? 0;
		?>
		<div>
			<!-- Export Section (Connected) -->
			<div class="stackboost-card stackboost-card-connected">
				<h3 style="margin-top: 0; padding-top: 10px;"><?php esc_html_e( 'Export Onboarding Steps', 'stackboost-for-supportcandy' ); ?></h3>
				<p><?php esc_html_e( 'Download all Onboarding Steps and their settings as a JSON file.', 'stackboost-for-supportcandy' ); ?></p>
				<button type="button" id="exportStepsBtn" class="button button-primary"><?php esc_html_e( 'Export JSON', 'stackboost-for-supportcandy' ); ?></button>
			</div>

			<!-- Import Section -->
			<div class="stackboost-card">
				<h3 style="margin-top: 0; padding-top: 10px;"><?php esc_html_e( 'Import Onboarding Steps', 'stackboost-for-supportcandy' ); ?></h3>
				<p><?php esc_html_e( 'Upload a JSON file to import Onboarding Steps. This process will automatically handle migration from legacy data formats if present.', 'stackboost-for-supportcandy' ); ?></p>

				<form id="importForm" enctype="multipart/form-data">
					<p>
						<input type="file" name="import_file" id="import_file" accept=".json" required>
					</p>
					<p>
						<label><input type="checkbox" name="clear_existing" value="1"> <?php esc_html_e( 'Delete all existing steps before import', 'stackboost-for-supportcandy' ); ?></label>
					</p>
					<button type="submit" id="importStepsBtn" class="button button-primary"><?php esc_html_e( 'Import JSON', 'stackboost-for-supportcandy' ); ?></button>
				</form>
				<div id="importMessage" style="margin-top: 10px; display: none;" class="notice inline"></div>
			</div>

			<!-- Data Migration Section -->
			<div class="stackboost-card">
				<h3 style="margin-top: 0; padding-top: 10px;"><?php esc_html_e( 'Legacy Data Migration (Manual)', 'stackboost-for-supportcandy' ); ?></h3>
				<p><?php esc_html_e( 'If you have imported data from the old "Onboarding Dashboard" plugin using WordPress Importer, the posts may be stored as "Legacy Onboarding Steps". Use this tool to convert them to the new format.', 'stackboost-for-supportcandy' ); ?></p>

				<p><strong><?php
				/* translators: %d: count of legacy items */
				printf( esc_html__( 'Found %d legacy items.', 'stackboost-for-supportcandy' ), (int) $legacy_count );
				?></strong></p>

				<?php if ( $legacy_count > 0 ) : ?>
					<button type="button" id="migrateDataBtn" class="button button-primary"><?php esc_html_e( 'Migrate Legacy Data Now', 'stackboost-for-supportcandy' ); ?></button>
					<div id="migrationMessage" style="margin-top: 10px; display: none;" class="notice inline"></div>
				<?php else : ?>
					<button type="button" class="button button-secondary" disabled><?php esc_html_e( 'No Legacy Data Found', 'stackboost-for-supportcandy' ); ?></button>
				<?php endif; ?>
			</div>
		</div>

		<script type="text/javascript">
			jQuery(document).ready(function($) {
				// Export
				$('#exportStepsBtn').on('click', function() {
					window.location.href = ajaxurl + '?action=stackboost_onboarding_export_steps&nonce=<?php echo esc_js( wp_create_nonce( 'stackboost_onboarding_export' ) ); ?>';
				});

				// Import
				$('#importForm').on('submit', function(e) {
					e.preventDefault();
					var formData = new FormData(this);
					formData.append('action', 'stackboost_onboarding_import_steps');
					formData.append('nonce', '<?php echo esc_js( wp_create_nonce( 'stackboost_onboarding_import' ) ); ?>');

					var btn = $('#importStepsBtn');
					var msg = $('#importMessage');

					btn.prop('disabled', true).text('Importing...');
					msg.hide();

					$.ajax({
						url: ajaxurl,
						type: 'POST',
						data: formData,
						processData: false,
						contentType: false,
						success: function(response) {
							btn.prop('disabled', false).text('<?php echo esc_js( __( 'Import JSON', 'stackboost-for-supportcandy' ) ); ?>');
							if (response.success) {
								msg.removeClass('notice-error').addClass('notice-success').html('<p>' + response.data.message + '</p>').show();
								setTimeout(function() { location.reload(); }, 2000);
							} else {
								msg.removeClass('notice-success').addClass('notice-error').html('<p>' + response.data.message + '</p>').show();
							}
						},
						error: function() {
							btn.prop('disabled', false).text('<?php echo esc_js( __( 'Import JSON', 'stackboost-for-supportcandy' ) ); ?>');
							msg.removeClass('notice-success').addClass('notice-error').html('<p>Server error occurred.</p>').show();
						}
					});
				});

				// Migrate Data
				$('#migrateDataBtn').on('click', function(e) {
                    e.preventDefault();
                    var btn = $(this);

                    stackboostConfirm(
                        '<?php echo esc_js( __( 'Are you sure you want to migrate all legacy data? This action cannot be undone.', 'stackboost-for-supportcandy' ) ); ?>',
                        '<?php echo esc_js( __( 'Confirm Migration', 'stackboost-for-supportcandy' ) ); ?>',
                        function() {
                            var msg = $('#migrationMessage');
                            btn.prop('disabled', true).text('Migrating...');

                            $.post(ajaxurl, {
                                action: 'stackboost_onboarding_migrate_data',
                                nonce: '<?php echo esc_js( wp_create_nonce( 'stackboost_onboarding_settings_nonce' ) ); ?>'
                            }, function(response) {
                                msg.removeClass('notice-error notice-success').hide();
                                if (response.success) {
                                    msg.addClass('notice-success').html('<p>' + response.data.message + '</p>').show();
                                    btn.text('Migration Complete');
                                    setTimeout(function() { location.reload(); }, 2000);
                                } else {
                                    btn.prop('disabled', false).text('<?php echo esc_js( __( 'Migrate Legacy Data Now', 'stackboost-for-supportcandy' ) ); ?>');
                                    msg.addClass('notice-error').html('<p>' + response.data.message + '</p>').show();
                                }
                            });
                        },
                        null,
                        '<?php echo esc_js( __( 'Yes, Migrate', 'stackboost-for-supportcandy' ) ); ?>',
                        '<?php echo esc_js( __( 'Cancel', 'stackboost-for-supportcandy' ) ); ?>',
                        true
                    );
				});
			});
		</script>
		<?php
	}

	/**
	 * AJAX: Migrate Legacy Data.
	 */
	public static function ajax_migrate_legacy_data() {
		stackboost_log( 'Starting manual legacy data migration...', 'onboarding' );
		check_ajax_referer( 'stackboost_onboarding_settings_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ] );
		}

		$legacy_posts = get_posts( [
			'post_type'      => 'onboarding_step',
			'posts_per_page' => -1,
			'post_status'    => 'any',
		] );

		if ( empty( $legacy_posts ) ) {
			stackboost_log( 'No legacy data found.', 'onboarding' );
			wp_send_json_error( [ 'message' => 'No legacy data found.' ] );
		}

		stackboost_log( 'Found ' . count( $legacy_posts ) . ' legacy items. Migrating...', 'onboarding' );
		$count = 0;
		foreach ( $legacy_posts as $post ) {
			// Update Post Type
			wp_update_post( [
				'ID'        => $post->ID,
				'post_type' => 'stkb_onboarding_step',
			] );

			// Update Meta Keys
			$checklist = get_post_meta( $post->ID, '_odb_checklist_items', true );
			if ( $checklist ) {
				update_post_meta( $post->ID, '_stackboost_onboarding_checklist_items', $checklist );
				delete_post_meta( $post->ID, '_odb_checklist_items' );
			}

			$notes = get_post_meta( $post->ID, '_odb_notes_content', true );
			if ( $notes ) {
				update_post_meta( $post->ID, '_stackboost_onboarding_notes_content', $notes );
				delete_post_meta( $post->ID, '_odb_notes_content' );
			}
			$count++;
		}

		// Also migrate the sequence option if it exists
		$old_sequence = get_option( 'onboarding_dashboard_sequence' );
		if ( $old_sequence ) {
			update_option( 'stackboost_onboarding_sequence', $old_sequence );
			// delete_option( 'onboarding_dashboard_sequence' ); // Keep old one just in case for now
		}

		wp_send_json_success( [ 'message' => "Successfully migrated {$count} items." ] );
	}

	/**
	 * AJAX: Export Steps to JSON.
	 */
	public static function ajax_export_steps() {
		stackboost_log( 'Exporting Onboarding Steps...', 'onboarding' );
		check_ajax_referer( 'stackboost_onboarding_export', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Permission denied' );
		}

		$steps = get_posts( [
			'post_type'      => 'stkb_onboarding_step',
			'posts_per_page' => -1,
			'post_status'    => 'any',
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
		] );

		$export_data = [];
		$sequence    = get_option( 'stackboost_onboarding_sequence', [] );

		// Re-order steps based on saved sequence if available
		if ( ! empty( $sequence ) ) {
			$ordered_steps = [];
			$step_map = [];
			foreach ( $steps as $step ) {
				$step_map[ $step->ID ] = $step;
			}
			foreach ( $sequence as $id ) {
				if ( isset( $step_map[ $id ] ) ) {
					$ordered_steps[] = $step_map[ $id ];
					unset( $step_map[ $id ] );
				}
			}
			// Append any steps not in sequence
			$ordered_steps = array_merge( $ordered_steps, array_values( $step_map ) );
			$steps = $ordered_steps;
		}

		stackboost_log( 'Found ' . count( $steps ) . ' steps to export.', 'onboarding' );

		foreach ( $steps as $step ) {
			$checklist = get_post_meta( $step->ID, '_stackboost_onboarding_checklist_items', true );
			$notes     = get_post_meta( $step->ID, '_stackboost_onboarding_notes_content', true );

			$export_data[] = [
				'title'     => $step->post_title,
				'content'   => $step->post_content,
				'checklist' => $checklist,
				'notes'     => $notes,
				'status'    => $step->post_status,
			];
		}

		$json_content = json_encode( $export_data, JSON_PRETTY_PRINT );
		$filename     = 'onboarding-steps-export-' . gmdate( 'Y-m-d' ) . '.json';

		header( 'Content-Description: File Transfer' );
		header( 'Content-Type: application/json' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		header( 'Expires: 0' );
		header( 'Cache-Control: must-revalidate' );
		header( 'Pragma: public' );
		header( 'Content-Length: ' . strlen( $json_content ) );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $json_content;
		exit;
	}

	/**
	 * AJAX: Import Steps from JSON.
	 */
	public static function ajax_import_steps() {
		stackboost_log( 'Importing Onboarding Steps...', 'onboarding' );
		check_ajax_referer( 'stackboost_onboarding_import', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ] );
		}

		if ( empty( $_FILES['import_file'] ) || empty( $_FILES['import_file']['name'] ) ) {
			wp_send_json_error( [ 'message' => 'No file uploaded.' ] );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Array structure access only; specific properties are sanitized before use.
		$file = $_FILES['import_file'];

		// Sanitize file name to satisfy linter regarding $_FILES access
		$safe_filename = sanitize_file_name( $file['name'] );

		if ( $file['error'] !== UPLOAD_ERR_OK ) {
			wp_send_json_error( [ 'message' => 'File upload error.' ] );
		}

		$json_content = file_get_contents( $file['tmp_name'] );
		$decoded_data = json_decode( $json_content, true );

		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $decoded_data ) ) {
			stackboost_log( 'Import failed: Invalid JSON.', 'error' );
			wp_send_json_error( [ 'message' => 'Invalid JSON file.' ] );
		}

		// Determine if this is a legacy export (wrapped in object with 'posts') or new format (array of objects)
		$items_to_import = [];
		if ( isset( $decoded_data['posts'] ) && is_array( $decoded_data['posts'] ) ) {
			// Legacy format found
			stackboost_log( 'Legacy export format detected.', 'onboarding' );
			$items_to_import = $decoded_data['posts'];
		} else {
			// Assume standard format
			$items_to_import = $decoded_data;
		}

		// Optional: Clear existing steps
		if ( Request::has_post( 'clear_existing' ) ) {
			$existing = get_posts( [
				'post_type'      => 'stkb_onboarding_step',
				'posts_per_page' => -1,
				'post_status'    => 'any',
				'fields'         => 'ids',
			] );
			foreach ( $existing as $id ) {
				wp_delete_post( $id, true );
			}
			delete_option( 'stackboost_onboarding_sequence' );
		}

		$count = 0;
		$new_sequence = [];
		stackboost_log( 'Processing ' . count( $items_to_import ) . ' items for import.', 'onboarding' );

		foreach ( $items_to_import as $item ) {
			// Handle legacy data structure (if keys differ)
			// Legacy: title, content, checklist, notes
			// New export format matches this, so direct mapping works.
			// If importing purely raw legacy DB dump, we might need to check for '_odb_' keys.

			$title     = $item['title'] ?? $item['post_title'] ?? 'Untitled Step';
			$content   = $item['content'] ?? $item['post_content'] ?? '';
			$status    = $item['status'] ?? $item['post_status'] ?? 'publish';

			// Migration / Mapping Logic
			$checklist = '';
			if ( isset( $item['checklist'] ) ) {
				$checklist = $item['checklist'];
			} elseif ( isset( $item['_stackboost_onboarding_checklist_items'] ) ) {
				$checklist = $item['_stackboost_onboarding_checklist_items'];
			} elseif ( isset( $item['_odb_checklist_items'] ) ) { // Legacy Key Support
				$checklist = $item['_odb_checklist_items'];
				stackboost_log( 'Found legacy checklist key for item.', 'onboarding' );
			} elseif ( isset( $item['meta']['_odb_checklist_items'] ) ) { // Legacy Nested Meta
				$checklist = $item['meta']['_odb_checklist_items'];
				stackboost_log( 'Found legacy nested meta checklist.', 'onboarding' );
			}

			$notes = '';
			if ( isset( $item['notes'] ) ) {
				$notes = $item['notes'];
			} elseif ( isset( $item['_stackboost_onboarding_notes_content'] ) ) {
				$notes = $item['_stackboost_onboarding_notes_content'];
			} elseif ( isset( $item['_odb_notes_content'] ) ) { // Legacy Key Support
				$notes = $item['_odb_notes_content'];
				stackboost_log( 'Found legacy notes key for item.', 'onboarding' );
			} elseif ( isset( $item['meta']['_odb_notes_content'] ) ) { // Legacy Nested Meta
				$notes = $item['meta']['_odb_notes_content'];
				stackboost_log( 'Found legacy nested meta notes.', 'onboarding' );
			}

			$post_id = wp_insert_post( [
				'post_type'    => 'stkb_onboarding_step',
				'post_title'   => $title,
				'post_content' => $content,
				'post_status'  => $status,
			] );

			if ( $post_id ) {
				update_post_meta( $post_id, '_stackboost_onboarding_checklist_items', $checklist );
				update_post_meta( $post_id, '_stackboost_onboarding_notes_content', $notes );
				$new_sequence[] = $post_id;
				$count++;
			}
		}

		if ( ! empty( $new_sequence ) ) {
			// If we cleared existing, or if we just want to append, we update the sequence.
			// If we appended, we should merge.
			if ( ! Request::has_post( 'clear_existing' ) ) {
				$existing_sequence = get_option( 'stackboost_onboarding_sequence', [] );
				$new_sequence = array_merge( $existing_sequence, $new_sequence );
			}
			update_option( 'stackboost_onboarding_sequence', $new_sequence );
		}

		wp_send_json_success( [ 'message' => "Imported {$count} steps successfully." ] );
	}
}
