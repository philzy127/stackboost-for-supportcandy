<?php
/**
 * Handles plugin upgrade routines.
 *
 * @package StackBoost\Admin
 */

namespace StackBoost\ForSupportCandy\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Upgrade class.
 */
class Upgrade {

	const MIGRATION_OPTION_NAME = 'stackboost_phone_migration_complete';

	/**
	 * Initialize the upgrade notice.
	 */
	public static function init() {
		if ( 'completed' !== get_option( self::MIGRATION_OPTION_NAME ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'show_migration_notice' ) );
			add_action( 'wp_ajax_stackboost_run_phone_migration', array( __CLASS__, 'run_migration' ) );
		}
	}

	/**
	 * Show the admin notice to run the migration.
	 */
	public static function show_migration_notice() {
		?>
		<div class="notice notice-info is-dismissible" id="stackboost-phone-migration-notice">
			<p>
				<strong><?php esc_html_e( 'StackBoost Data Migration', 'stackboost-for-supportcandy' ); ?></strong><br>
				<?php esc_html_e( 'We need to update your database to a new format for phone numbers. This will ensure better formatting and reliability.', 'stackboost-for-supportcandy' ); ?>
			</p>
			<p>
				<button class="button button-primary" id="stackboost-run-phone-migration-btn"><?php esc_html_e( 'Run Database Update', 'stackboost-for-supportcandy' ); ?></button>
				<span class="spinner" style="float: none; margin-top: 4px;"></span>
			</p>
		</div>
		<script type="text/javascript">
			jQuery(document).ready(function($) {
				$('#stackboost-run-phone-migration-btn').on('click', function(e) {
					e.preventDefault();
					var $button = $(this);
					var $spinner = $button.next('.spinner');

					$button.prop('disabled', true);
					$spinner.addClass('is-active');

					$.post(ajaxurl, {
						action: 'stackboost_run_phone_migration',
						nonce: '<?php echo esc_js( wp_create_nonce( 'stackboost_phone_migration_nonce' ) ); ?>'
					}, function(response) {
						$spinner.removeClass('is-active');
						if (response.success) {
							$('#stackboost-phone-migration-notice').html('<p>' + response.data + '</p>').removeClass('notice-info').addClass('notice-success');
						} else {
							$('#stackboost-phone-migration-notice').html('<p>' + response.data + '</p>').removeClass('notice-info').addClass('notice-error');
							$button.prop('disabled', false);
						}
					});
				});

				// Dismiss notice via AJAX
				$('#stackboost-phone-migration-notice').on('click', '.notice-dismiss', function() {
					$.post(ajaxurl, {
						action: 'stackboost_dismiss_phone_migration_notice'
					});
				});
			});
		</script>
		<?php
	}

	/**
	 * Run the phone number migration.
	 */
	public static function run_migration() {
		if ( ! check_ajax_referer( 'stackboost_phone_migration_nonce', 'nonce', false ) ) {
			wp_send_json_error( __( 'Security check failed.', 'stackboost-for-supportcandy' ), 403 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'You do not have permission to perform this action.', 'stackboost-for-supportcandy' ), 403 );
		}

		global $wpdb;
		$meta_keys = array( '_office_phone', '_mobile_phone' );

		$placeholders = implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) );
		$query = $wpdb->prepare(
			"SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_key IN ($placeholders) AND meta_value IS NOT NULL AND meta_value != ''",
			$meta_keys
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $query );

		$updated_count = 0;
		foreach ( $results as $row ) {
			$cleaned_value = preg_replace( '/\D/', '', $row->meta_value );
			if ( $cleaned_value !== $row->meta_value ) {
				update_post_meta( $row->post_id, $row->meta_key, $cleaned_value, $row->meta_value );
				$updated_count++;
			}
		}

		update_option( self::MIGRATION_OPTION_NAME, 'completed' );

		wp_send_json_success( sprintf(
			/* translators: %d: number of phone numbers updated */
			esc_html__( 'Database update complete. %d phone number(s) were updated.', 'stackboost-for-supportcandy' ),
			$updated_count
		) );
	}
}