<?php
/**
 * Core class for the Directory Migration module.
 *
 * @package StackBoost
 */

namespace StackBoost\ForSupportCandy\Modules\DirectoryMigration;

/**
 * Class Core
 */
class Core {

	/**
	 * The required migration timestamp.
	 *
	 * @var string
	 */
	private $required_migration_timestamp = '2025-10-12 22:26:00';

	/**
	 * The option key for the migration timestamp.
	 *
	 * @var string
	 */
	private $migration_timestamp_key = 'stackboost_db_migration_timestamp';

	/**
	 * Core constructor.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'check_migration_status' ) );
		add_action( 'wp_ajax_stackboost_run_migration', array( $this, 'run_migration' ) );
	}

	/**
	 * Check the migration status and display a notice if required.
	 */
	public function check_migration_status() {
		$current_migration_timestamp = get_option( $this->migration_timestamp_key, '1970-01-01 00:00:00' );

		if ( strtotime( $current_migration_timestamp ) < strtotime( $this->required_migration_timestamp ) ) {
			add_action( 'admin_notices', array( $this, 'render_migration_notice' ) );
		}
	}

	/**
	 * Render the migration notice.
	 */
	public function render_migration_notice() {
		$ajax_url = admin_url( 'admin-ajax.php' );
		?>
		<div class="notice notice-warning is-dismissible">
			<p>
				<?php esc_html_e( 'StackBoost for SupportCandy requires a database update. Please run the migration to avoid issues.', 'stackboost-for-supportcandy' ); ?>
				<a href="#" id="stackboost-run-migration-button" class="button button-primary"><?php esc_html_e( 'Run Migration', 'stackboost-for-supportcandy' ); ?></a>
			</p>
		</div>
		<script type="text/javascript">
			jQuery(document).ready(function($) {
				$('#stackboost-run-migration-button').on('click', function(e) {
					e.preventDefault();
					$(this).prop('disabled', true).text('<?php esc_html_e( 'Running...', 'stackboost-for-supportcandy' ); ?>');
					$.post('<?php echo esc_url( $ajax_url ); ?>', { action: 'stackboost_run_migration', nonce: '<?php echo esc_js( wp_create_nonce( 'stackboost_migration_nonce' ) ); ?>' }, function(response) {
						if (response.success) {
							$('#stackboost-run-migration-button').closest('.notice').find('p').html('<?php esc_html_e( 'Migration complete!', 'stackboost-for-supportcandy' ); ?>');
						} else {
							$('#stackboost-run-migration-button').closest('.notice').find('p').html('<?php esc_html_e( 'Migration failed. Please check the error logs.', 'stackboost-for-supportcandy' ); ?>');
						}
					});
				});
			});
		</script>
		<?php
	}

	/**
	 * Run the migration.
	 */
	public function run_migration() {
		check_ajax_referer( 'stackboost_migration_nonce', 'nonce' );

		global $wpdb;

		// Rename post types
		$wpdb->query( "UPDATE {$wpdb->posts} SET post_type = 'stackboost_staff_directory' WHERE post_type = 'chp_staff_directory'" );
		$wpdb->query( "UPDATE {$wpdb->posts} SET post_type = 'stackboost_location' WHERE post_type = 'chp_location'" );
		$wpdb->query( "UPDATE {$wpdb->posts} SET post_type = 'stackboost_department' WHERE post_type = 'chp_department'" );

		// Rename meta keys
		$wpdb->query( "UPDATE {$wpdb->postmeta} SET meta_key = '_stackboost_staff_job_title' WHERE meta_key = '_chp_staff_job_title'" );

		// Update the migration timestamp
		update_option( $this->migration_timestamp_key, $this->required_migration_timestamp );

		wp_send_json_success();
	}
}