<?php

namespace StackBoost\ForSupportCandy\Modules\OnboardingDashboard\Admin;

class TicketSearch {

	/**
	 * Initialize the page.
	 */
	public static function init() {
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );
	}

	/**
	 * Enqueue scripts.
	 */
	public static function enqueue_scripts( $hook ) {
		if ( 'stackboost-for-supportcandy_page_stackboost-onboarding-dashboard' !== $hook ) {
			return;
		}

		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'steps';
		if ( 'ticket_search' !== $tab ) {
			return;
		}

		// We can reuse the admin-ticket-search.js from the original plugin, but I'll inline it for simplicity
		// as it's a small utility.
	}

	/**
	 * Render page.
	 */
	public static function render_page() {
		?>
		<div>
			<h2><?php esc_html_e( 'API Ticket Search', 'stackboost-for-supportcandy' ); ?></h2>
			<p><?php esc_html_e( 'Search for raw ticket data via the configured API credentials.', 'stackboost-for-supportcandy' ); ?></p>

			<div class="card" style="max-width: 600px; padding: 20px;">
				<p>
					<label for="ticketId"><?php esc_html_e( 'Ticket ID:', 'stackboost-for-supportcandy' ); ?></label>
					<input type="number" id="ticketId" class="regular-text">
				</p>
				<p>
					<button type="button" id="searchTicketBtn" class="button button-primary"><?php esc_html_e( 'Search Ticket', 'stackboost-for-supportcandy' ); ?></button>
				</p>
			</div>

			<div id="searchResult" style="margin-top: 20px; background: #fff; padding: 20px; border: 1px solid #ccd0d4; display: none;">
				<pre id="jsonOutput" style="white-space: pre-wrap; word-wrap: break-word;"></pre>
			</div>
		</div>

		<script type="text/javascript">
			jQuery(document).ready(function($) {
				$('#searchTicketBtn').on('click', function() {
					var ticketId = $('#ticketId').val();
					var btn = $(this);
					var output = $('#jsonOutput');
					var wrapper = $('#searchResult');

					if (!ticketId) {
						alert('Please enter a Ticket ID');
						return;
					}

					btn.prop('disabled', true).text('Searching...');
					wrapper.hide();

					$.post(ajaxurl, {
						action: 'stackboost_onboarding_test_credentials', // Re-using the test action as it does a GET request logic
						// Actually, the test action queries /tickets. We need a custom action for specific ID or modify test action.
						// Let's write a specific inline logic here or just use the Settings::ajax_test_credentials if it was more flexible.
						// For now, I'll use the credentials to make a client-side call to the backend proxy.
						// Wait, I can just add a new AJAX handler for this.
						action: 'stackboost_onboarding_search_ticket',
						ticket_id: ticketId,
						nonce: '<?php echo wp_create_nonce( 'stackboost_onboarding_settings_nonce' ); ?>'
					}, function(response) {
						btn.prop('disabled', false).text('<?php esc_attr_e( 'Search Ticket', 'stackboost-for-supportcandy' ); ?>');
						wrapper.show();
						if (response.success) {
							var json = JSON.stringify(JSON.parse(response.data.response), null, 4);
							output.text(json);
						} else {
							output.text('Error: ' + response.data.message);
						}
					});
				});
			});
		</script>
		<?php
	}
}

// Add the search handler to Settings class or here? I'll add it here for now but hooks need to be registered.
add_action( 'wp_ajax_stackboost_onboarding_search_ticket', function() {
	check_ajax_referer( 'stackboost_onboarding_settings_nonce', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( [ 'message' => 'Permission denied.' ] );
	}

	$ticket_id = absint( $_POST['ticket_id'] ?? 0 );
	$username  = get_option( Settings::OPTION_USERNAME, '' );
	$secret_key = get_option( Settings::OPTION_SECRET_KEY, '' );
	$site_url   = get_site_url();

	if ( ! $ticket_id ) {
		wp_send_json_error( [ 'message' => 'Invalid Ticket ID.' ] );
	}

	$response = wp_remote_get( $site_url . '/wp-json/supportcandy/v2/tickets/' . $ticket_id, [
		'headers' => [
			'Authorization' => 'Basic ' . base64_encode( $username . ':' . $secret_key ),
		],
		'timeout' => 15,
		'sslverify' => false,
	] );

	if ( is_wp_error( $response ) ) {
		wp_send_json_error( [ 'message' => $response->get_error_message() ] );
	}

	$body = wp_remote_retrieve_body( $response );
	wp_send_json_success( [ 'response' => $body ] );
} );
