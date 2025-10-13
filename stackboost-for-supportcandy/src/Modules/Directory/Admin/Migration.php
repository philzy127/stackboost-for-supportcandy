<?php
/**
 * StackBoost Company Directory Migration.
 *
 * This file handles the data migration from the old 'chp' prefixes
 * to the new 'stackboost' prefixes.
 *
 * @package StackBoost
 * @subpackage Modules\Directory\Admin
 */

namespace StackBoost\ForSupportCandy\Modules\Directory\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Migration Class
 *
 * Handles the data migration.
 */
class Migration {

	/**
	 * The option name to store the migration status.
	 *
	 * @var string
	 */
	const MIGRATION_OPTION = 'stackboost_directory_migration_status';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_notices', array( $this, 'show_migration_notice' ) );
		add_action( 'wp_ajax_stackboost_directory_run_migration', array( $this, 'run_migration' ) );
	}

	/**
	 * Show the migration notice if the migration has not been run.
	 */
	public function show_migration_notice() {
		if ( 'completed' !== get_option( self::MIGRATION_OPTION ) ) {
			?>
			<div class="notice notice-info is-dismissible">
				<p>
					<?php esc_html_e( 'The StackBoost Company Directory plugin needs to update its database to the latest version.', 'stackboost-for-supportcandy' ); ?>
				</p>
				<p>
					<button id="stackboost-run-migration" class="button button-primary">
						<?php esc_html_e( 'Run Migration', 'stackboost-for-supportcandy' ); ?>
					</button>
				</p>
				<div id="stackboost-migration-message" style="margin-top: 15px;"></div>
			</div>
			<script type="text/javascript">
				jQuery(document).ready(function($) {
					$('#stackboost-run-migration').on('click', function() {
						var button = $(this);
						var messageDiv = $('#stackboost-migration-message');

						button.prop('disabled', true);
						messageDiv.html('<?php esc_html_e( 'Running migration...', 'stackboost-for-supportcandy' ); ?>');

						$.post(ajaxurl, {
							action: 'stackboost_directory_run_migration',
							nonce: '<?php echo esc_js( wp_create_nonce( 'stackboost_directory_migration_nonce' ) ); ?>'
						}, function(response) {
							if (response.success) {
								messageDiv.html('<p style="color: green;">' + response.data.message + '</p>');
								button.closest('.notice').fadeOut();
							} else {
								messageDiv.html('<p style="color: red;">' + response.data.message + '</p>');
								button.prop('disabled', false);
							}
						});
					});
				});
			</script>
			<?php
		}
	}

	/**
	 * Run the data migration.
	 */
	public function run_migration() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have sufficient permissions to perform this action.', 'stackboost-for-supportcandy' ) ) );
		}

		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'stackboost_directory_migration_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'stackboost-for-supportcandy' ) ) );
		}

		if ( 'completed' === get_option( self::MIGRATION_OPTION ) ) {
			wp_send_json_error( array( 'message' => __( 'Migration has already been completed.', 'stackboost-for-supportcandy' ) ) );
		}

		global $wpdb;

		// Rename post types
		$wpdb->query( "UPDATE {$wpdb->posts} SET post_type = 'stackboost_staff_directory' WHERE post_type = 'chp_staff_directory'" );
		$wpdb->query( "UPDATE {$wpdb->posts} SET post_type = 'stackboost_location' WHERE post_type = 'chp_location'" );
		$wpdb->query( "UPDATE {$wpdb->posts} SET post_type = 'stackboost_department' WHERE post_type = 'chp_department'" );

		// Rename meta keys
		$wpdb->query( "UPDATE {$wpdb->postmeta} SET meta_key = '_stackboost_staff_job_title' WHERE meta_key = '_chp_staff_job_title'" );

		update_option( self::MIGRATION_OPTION, 'completed' );

		// Flush rewrite rules to ensure the new post types are recognized.
		flush_rewrite_rules();

		wp_send_json_success( array( 'message' => __( 'Migration completed successfully.', 'stackboost-for-supportcandy' ) ) );
	}
}