<?php

namespace StackBoost\ForSupportCandy\Modules\OnboardingDashboard\Admin;

class ApiSettings {

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
		// No actions needed currently as API settings are removed.
	}

	/**
	 * Render the Settings page.
	 */
	public static function render_page() {
		?>
		<div>
			<h2><?php esc_html_e( 'Settings', 'stackboost-for-supportcandy' ); ?></h2>

			<div class="card" style="max-width: 800px; margin-top: 20px; padding: 20px;">
				<p><?php esc_html_e( 'The API integration has been replaced with direct internal hooks for improved performance and security. No configuration is currently required.', 'stackboost-for-supportcandy' ); ?></p>
			</div>
		</div>
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

}
