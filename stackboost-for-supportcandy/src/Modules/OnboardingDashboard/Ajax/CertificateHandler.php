<?php

namespace StackBoost\ForSupportCandy\Modules\OnboardingDashboard\Ajax;

use StackBoost\ForSupportCandy\Services\PdfService;
use StackBoost\ForSupportCandy\Modules\OnboardingDashboard\Admin\Settings;

class CertificateHandler {

	/**
	 * Initialize AJAX handler.
	 */
	public static function init() {
		add_action( 'wp_ajax_stackboost_onboarding_send_certificates', [ __CLASS__, 'handle_request' ] );
	}

	/**
	 * Handle the certificate generation and sending request.
	 */
	public static function handle_request() {
		// Verify Nonce
		check_ajax_referer( 'stkb_onboarding_certificate_nonce', 'nonce' );

		// Verify Capability
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_onboarding_dashboard' ) ) {
			// Note: 'manage_onboarding_dashboard' is a custom cap from original plugin.
			// We can add it or just stick to manage_options for now.
			// For this refactor, I'll stick to 'edit_posts' as a baseline or 'manage_options'.
			// Actually, let's assume 'edit_posts' for technicians.
			if ( ! current_user_can( 'edit_posts' ) ) {
				wp_send_json_error( 'Permission denied.' );
			}
		}

		$present_attendees = isset( $_POST['present_attendees'] ) ? json_decode( stripslashes( $_POST['present_attendees'] ), true ) : [];

		if ( empty( $present_attendees ) ) {
			wp_send_json_success( [ 'message' => 'No attendees present.' ] );
		}

		$results = [];
		$username = get_option( Settings::OPTION_USERNAME, '' );
		$secret_key = get_option( Settings::OPTION_SECRET_KEY, '' );
		$base_url = get_site_url();

		$current_user = wp_get_current_user();
		$trainer_name = $current_user ? $current_user->display_name : 'Unknown Trainer';
		$completion_date = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );

		// Prepare Content for PDF
		$sequence_ids = get_option( 'stackboost_onboarding_sequence', [] );
		if ( empty( $sequence_ids ) ) {
			$defaults = get_posts([
				'post_type' => 'stkb_onboarding_step',
				'post_status' => 'publish',
				'posts_per_page' => -1,
				'orderby' => 'date',
				'order' => 'ASC'
			]);
			$sequence_ids = wp_list_pluck( $defaults, 'ID' );
		}

		$content_blocks = [];
		$estimated_units = 0;

		foreach ( $sequence_ids as $id ) {
			$post = get_post( $id );
			if ( $post && $post->post_status === 'publish' && $post->post_type === 'stkb_onboarding_step' ) {
				$content_blocks[] = [
					'type' => 'heading',
					'text' => $post->post_title,
					'units' => 2
				];
				$estimated_units += 2;

				$raw_checklist = get_post_meta( $post->ID, '_stackboost_onboarding_checklist_items', true );
				$items = array_filter( array_map( 'trim', explode( "\n", $raw_checklist ) ) );
				$clean_items = [];
				foreach ( $items as $item ) {
					$clean_items[] = preg_replace( '/\s*\[([^\]]*)\]/', '', trim($item) );
				}

				if ( ! empty( $clean_items ) ) {
					$content_blocks[] = [
						'type' => 'checklist_group',
						'items' => $clean_items,
						'units' => count( $clean_items )
					];
					$estimated_units += count( $clean_items );
				}
			}
		}

		foreach ( $present_attendees as $attendee ) {
			$attendee_name = $attendee['name'];
			$ticket_id = $attendee['id'];

			$html = self::generate_html( $attendee_name, $trainer_name, $completion_date, $content_blocks, $estimated_units );
			$pdf_content = PdfService::get_instance()->generate_pdf( $html );

			if ( ! $pdf_content ) {
				$results[] = [
					'attendee' => $attendee_name,
					'status' => 'error',
					'message' => 'PDF Generation Failed.'
				];
				continue;
			}

			// Save to temp file
			$filename = sanitize_file_name( 'Onboarding_Certificate_' . $attendee_name . '.pdf' );
			$filepath = sys_get_temp_dir() . '/' . $filename;
			file_put_contents( $filepath, $pdf_content );

			// Upload to SupportCandy via API (TODO: Refactor to internal call)
			$upload_result = self::upload_via_api( $base_url, $username, $secret_key, $ticket_id, $filepath, $filename, $attendee_name );

			if ( is_wp_error( $upload_result ) ) {
				$results[] = [
					'attendee' => $attendee_name,
					'status' => 'error',
					'message' => $upload_result->get_error_message()
				];
			} else {
				$results[] = [
					'attendee' => $attendee_name,
					'status' => 'success',
					'message' => 'Certificate sent.'
				];
			}

			if ( file_exists( $filepath ) ) {
				unlink( $filepath );
			}
		}

		wp_send_json_success( [ 'message' => 'Processing complete.', 'results' => $results ] );
	}

	/**
	 * Generate HTML for the PDF.
	 */
	private static function generate_html( $attendee_name, $trainer_name, $date, $blocks, $total_units ) {
		// Split into two columns logic
		$half = $total_units / 2;
		$current_sum = 0;
		$split_index = count( $blocks ); // Default to all in col 1

		for ( $i = 0; $i < count( $blocks ); $i++ ) {
			$current_sum += $blocks[$i]['units'];
			if ( $current_sum >= $half ) {
				// Find next heading to split cleanly
				$split_index = $i + 1;
				for ( $j = $i + 1; $j < count( $blocks ); $j++ ) {
					if ( $blocks[$j]['type'] === 'heading' ) {
						$split_index = $j;
						break 2;
					}
				}
				break;
			}
		}

		$left_col = array_slice( $blocks, 0, $split_index );
		$right_col = array_slice( $blocks, $split_index );

		$render_blocks = function( $blocks ) {
			$html = '';
			foreach ( $blocks as $block ) {
				if ( $block['type'] === 'heading' ) {
					$html .= '<div class="checklist-heading">' . esc_html( $block['text'] ) . '</div>';
				} elseif ( $block['type'] === 'checklist_group' ) {
					$html .= '<div class="shared-blue-box"><ul>';
					foreach ( $block['items'] as $item ) {
						$html .= '<li>' . esc_html( $item ) . '</li>';
					}
					$html .= '</ul></div>';
				}
			}
			return $html;
		};

		// Styles (Ported from original)
		$css = '
			body { font-family: "Helvetica", Arial, sans-serif; margin: 0; padding: 0; font-size: 10pt; line-height: 1.4; color: #333; }
			.certificate-container { width: 180mm; min-height: 257mm; margin: 1mm auto; background: #fff; }
			.header-left { font-size: 14pt; font-weight: bold; }
			.attendee-name { font-size: 18pt; font-weight: bold; color: #0056b3; margin-top: 10px; }
			.completion-statement { margin-bottom: 7mm; }
			.column { width: 49%; display: inline-block; vertical-align: top; }
			.shared-blue-box { background-color: #E0F2F7; padding: 5px; border-radius: 8px; margin-bottom: 3mm; margin-right: 1mm; }
			.shared-blue-box ul { list-style: disc; padding-left: 15px; margin: 0; }
			.checklist-heading { font-weight: bold; margin-bottom: 1mm; margin-top: 2mm; }
			.footer-text { border-top: 1px solid #ccc; padding-top: 3mm; margin-top: 10px; }
		';

		$html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>' . $css . '</style></head><body>
			<div class="certificate-container">
				<div class="header-left">Children\'s Home of Poughkeepsie</div>
				<div class="attendee-name">' . esc_html( $attendee_name ) . '</div>
				<div class="completion-statement">
					' . esc_html( $attendee_name ) . ' has completed IT Onboarding Training with ' . esc_html( $trainer_name ) . ' and has been present for:
				</div>
				<div style="font-size: 0;">
					<div class="column">' . $render_blocks( $left_col ) . '</div>
					<div class="column" style="margin-left: 2%;">' . $render_blocks( $right_col ) . '</div>
				</div>
				<div class="footer-text">Completed: ' . esc_html( $date ) . ' - ' . esc_html( $trainer_name ) . '</div>
			</div>
		</body></html>';

		return $html;
	}

	/**
	 * Upload via API (Legacy Method).
	 * TODO: Replace with internal SupportCandy method calls.
	 */
	private static function upload_via_api( $base_url, $username, $secret_key, $ticket_id, $filepath, $filename, $attendee_name ) {
		$auth = 'Basic ' . base64_encode( $username . ':' . $secret_key );

		// 1. Upload
		$boundary = '----------------------------' . microtime(true);
		$body = "--$boundary\r\n";
		$body .= 'Content-Disposition: form-data; name="file"; filename="' . $filename . '"' . "\r\n";
		$body .= "Content-Type: application/pdf\r\n\r\n";
		$body .= file_get_contents( $filepath ) . "\r\n";
		$body .= "--$boundary--\r\n";

		$upload_resp = wp_remote_post( $base_url . '/wp-json/supportcandy/v2/attachments', [
			'headers' => [ 'Content-Type' => 'multipart/form-data; boundary=' . $boundary, 'Authorization' => $auth ],
			'body' => $body,
			'timeout' => 60,
			'sslverify' => false
		]);

		if ( is_wp_error( $upload_resp ) ) return $upload_resp;
		$upload_data = json_decode( wp_remote_retrieve_body( $upload_resp ), true );

		if ( ! isset( $upload_data['id'] ) ) {
			return new \WP_Error( 'upload_failed', 'Upload API failed.' );
		}
		$attach_id = $upload_data['id'];

		// 2. Attach to Ticket
		$msg_body = [
			'type' => 'note',
			'body' => "Onboarding Certificate for $attendee_name attached.",
			'attachments' => (string)$attach_id // SupportCandy expects string "1,2"
		];

		$msg_resp = wp_remote_post( $base_url . "/wp-json/supportcandy/v2/tickets/$ticket_id/threads", [
			'headers' => [ 'Content-Type' => 'application/json', 'Authorization' => $auth ],
			'body' => json_encode( $msg_body ),
			'timeout' => 60,
			'sslverify' => false
		]);

		if ( is_wp_error( $msg_resp ) ) return $msg_resp;
		if ( 200 !== wp_remote_retrieve_response_code( $msg_resp ) ) {
			return new \WP_Error( 'msg_failed', 'Message API failed.' );
		}

		return true;
	}
}
