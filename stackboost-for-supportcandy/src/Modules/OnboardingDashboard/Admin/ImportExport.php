<?php

namespace StackBoost\ForSupportCandy\Modules\OnboardingDashboard\Admin;

class ImportExport {

	/**
	 * Initialize the Import/Export page.
	 */
	public static function init() {
		add_action( 'wp_ajax_stackboost_onboarding_migrate_data', [ __CLASS__, 'ajax_migrate_legacy_data' ] );
	}

	/**
	 * Render the Import/Export page.
	 */
	public static function render_page() {
		// Check for legacy data count
		$legacy_count = wp_count_posts( 'onboarding_step' )->publish ?? 0;
		?>
		<div>
			<h2><?php esc_html_e( 'Import / Export & Migration', 'stackboost-for-supportcandy' ); ?></h2>

			<!-- Data Migration Section -->
			<div class="card" style="max-width: 800px; margin-top: 20px; padding: 20px;">
				<h3><?php esc_html_e( 'Legacy Data Migration', 'stackboost-for-supportcandy' ); ?></h3>
				<p><?php esc_html_e( 'If you have imported data from the old "Onboarding Dashboard" plugin using WordPress Importer, the posts may be stored as "Legacy Onboarding Steps". Use this tool to convert them to the new format.', 'stackboost-for-supportcandy' ); ?></p>

				<p><strong><?php printf( esc_html__( 'Found %d legacy items.', 'stackboost-for-supportcandy' ), $legacy_count ); ?></strong></p>

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
				// Migrate Data
				$('#migrateDataBtn').on('click', function() {
					if (!confirm('<?php esc_html_e( 'Are you sure you want to migrate all legacy data? This action cannot be undone.', 'stackboost-for-supportcandy' ); ?>')) {
						return;
					}
					var btn = $(this);
					var msg = $('#migrationMessage');
					btn.prop('disabled', true).text('Migrating...');

					$.post(ajaxurl, {
						action: 'stackboost_onboarding_migrate_data',
						nonce: '<?php echo wp_create_nonce( 'stackboost_onboarding_settings_nonce' ); ?>'
					}, function(response) {
						msg.removeClass('notice-error notice-success').hide();
						if (response.success) {
							msg.addClass('notice-success').html('<p>' + response.data.message + '</p>').show();
							btn.text('Migration Complete');
							setTimeout(function() { location.reload(); }, 2000);
						} else {
							btn.prop('disabled', false).text('<?php esc_html_e( 'Migrate Legacy Data Now', 'stackboost-for-supportcandy' ); ?>');
							msg.addClass('notice-error').html('<p>' + response.data.message + '</p>').show();
						}
					});
				});
			});
		</script>
		<?php
	}

	/**
	 * AJAX: Migrate Legacy Data.
	 */
	public static function ajax_migrate_legacy_data() {
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
			wp_send_json_error( [ 'message' => 'No legacy data found.' ] );
		}

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
}
