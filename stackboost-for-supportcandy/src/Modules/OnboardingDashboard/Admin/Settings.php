<?php

namespace StackBoost\ForSupportCandy\Modules\OnboardingDashboard\Admin;

class Settings {

	/**
	 * Option name for API Username.
	 */
	const OPTION_USERNAME = 'sc_api_integrator_username';

	/**
	 * Option name for API Secret Key.
	 */
	const OPTION_SECRET_KEY = 'sc_api_integrator_secret_key';

	/**
	 * Initialize the Settings page.
	 */
	public static function init() {
		add_action( 'wp_ajax_stackboost_onboarding_save_credentials', [ __CLASS__, 'ajax_save_credentials' ] );
		add_action( 'wp_ajax_stackboost_onboarding_test_credentials', [ __CLASS__, 'ajax_test_credentials' ] );
		add_action( 'wp_ajax_stackboost_onboarding_migrate_data', [ __CLASS__, 'ajax_migrate_legacy_data' ] );
	}

	/**
	 * Render the Settings page.
	 */
	public static function render_page() {
		$site_url   = get_site_url();
		$username   = get_option( self::OPTION_USERNAME, '' );
		$secret_key = get_option( self::OPTION_SECRET_KEY, '' );

		// Check for legacy data count
		$legacy_count = wp_count_posts( 'onboarding_step' )->publish ?? 0;
		?>
		<div>
			<h2><?php esc_html_e( 'Onboarding Dashboard Settings', 'stackboost-for-supportcandy' ); ?></h2>

			<!-- API Settings Section -->
			<div class="card" style="max-width: 800px; margin-top: 20px; padding: 20px;">
				<h2><?php esc_html_e( 'API Credentials', 'stackboost-for-supportcandy' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'These credentials are used for the legacy API integration (SupportCandy attachments). Even though the module runs locally, the current implementation requires these keys to mimic an external API call.', 'stackboost-for-supportcandy' ); ?>
				</p>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="baseUrl"><?php esc_html_e( 'Base URL', 'stackboost-for-supportcandy' ); ?></label></th>
						<td>
							<input type="text" id="baseUrl" class="regular-text" value="<?php echo esc_url( $site_url ); ?>" readonly>
							<p class="description"><?php esc_html_e( 'Your WordPress Site URL.', 'stackboost-for-supportcandy' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="username"><?php esc_html_e( 'API Username', 'stackboost-for-supportcandy' ); ?></label></th>
						<td>
							<input type="text" id="username" class="regular-text" value="<?php echo esc_attr( $username ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="secretKey"><?php esc_html_e( 'API Application Password', 'stackboost-for-supportcandy' ); ?></label></th>
						<td>
							<input type="password" id="secretKey" class="regular-text" value="<?php echo esc_attr( $secret_key ); ?>">
						</td>
					</tr>
				</table>
				<p class="submit">
					<button type="button" id="saveCredentialsBtn" class="button button-primary"><?php esc_html_e( 'Save Credentials', 'stackboost-for-supportcandy' ); ?></button>
					<button type="button" id="testCredentialsBtn" class="button button-secondary"><?php esc_html_e( 'Test Connection', 'stackboost-for-supportcandy' ); ?></button>
				</p>
				<div id="apiMessage" style="margin-top: 10px; display: none;" class="notice inline"></div>
			</div>

			<!-- Data Migration Section -->
			<div class="card" style="max-width: 800px; margin-top: 20px; padding: 20px;">
				<h2><?php esc_html_e( 'Data Migration', 'stackboost-for-supportcandy' ); ?></h2>
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
				// Save Credentials
				$('#saveCredentialsBtn').on('click', function() {
					var btn = $(this);
					var msg = $('#apiMessage');
					btn.prop('disabled', true);

					$.post(ajaxurl, {
						action: 'stackboost_onboarding_save_credentials',
						username: $('#username').val(),
						secret_key: $('#secretKey').val(),
						nonce: '<?php echo wp_create_nonce( 'stackboost_onboarding_settings_nonce' ); ?>'
					}, function(response) {
						btn.prop('disabled', false);
						msg.removeClass('notice-error notice-success').hide();
						if (response.success) {
							msg.addClass('notice-success').html('<p>' + response.data.message + '</p>').show();
						} else {
							msg.addClass('notice-error').html('<p>' + response.data.message + '</p>').show();
						}
					});
				});

				// Test Credentials
				$('#testCredentialsBtn').on('click', function() {
					var btn = $(this);
					var msg = $('#apiMessage');
					btn.prop('disabled', true);

					$.post(ajaxurl, {
						action: 'stackboost_onboarding_test_credentials',
						username: $('#username').val(),
						secret_key: $('#secretKey').val(),
						base_url: $('#baseUrl').val(),
						nonce: '<?php echo wp_create_nonce( 'stackboost_onboarding_settings_nonce' ); ?>'
					}, function(response) {
						btn.prop('disabled', false);
						msg.removeClass('notice-error notice-success').hide();
						if (response.success) {
							msg.addClass('notice-success').html('<p>' + response.data.message + '</p>').show();
						} else {
							msg.addClass('notice-error').html('<p>' + response.data.message + '</p>').show();
						}
					});
				});

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
	 * AJAX: Save Credentials.
	 */
	public static function ajax_save_credentials() {
		check_ajax_referer( 'stackboost_onboarding_settings_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ] );
		}

		$username   = sanitize_text_field( $_POST['username'] ?? '' );
		$secret_key = sanitize_text_field( $_POST['secret_key'] ?? '' );

		update_option( self::OPTION_USERNAME, $username );
		update_option( self::OPTION_SECRET_KEY, $secret_key );

		wp_send_json_success( [ 'message' => 'Credentials saved.' ] );
	}

	/**
	 * AJAX: Test Credentials.
	 */
	public static function ajax_test_credentials() {
		check_ajax_referer( 'stackboost_onboarding_settings_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ] );
		}

		$username   = sanitize_text_field( $_POST['username'] ?? '' );
		$secret_key = sanitize_text_field( $_POST['secret_key'] ?? '' );
		$base_url   = esc_url_raw( $_POST['base_url'] ?? '' );

		$response = wp_remote_get( $base_url . '/wp-json/supportcandy/v2/tickets', [
			'headers' => [
				'Authorization' => 'Basic ' . base64_encode( $username . ':' . $secret_key ),
			],
			'timeout' => 10,
			'sslverify' => false,
		] );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( [ 'message' => 'Connection failed: ' . $response->get_error_message() ] );
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 === $code ) {
			wp_send_json_success( [ 'message' => 'Connection successful!' ] );
		} else {
			wp_send_json_error( [ 'message' => 'API Error: ' . $code ] );
		}
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
