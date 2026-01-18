<?php


namespace StackBoost\ForSupportCandy\Modules\OnboardingDashboard\Ajax;

if ( ! defined( 'ABSPATH' ) ) exit;

use StackBoost\ForSupportCandy\Services\PdfService;
use StackBoost\ForSupportCandy\Modules\OnboardingDashboard\Admin\Settings;

class CertificateHandler {

	/**
	 * Initialize AJAX handler.
	 */
	public static function init() {
		add_action( 'wp_ajax_stackboost_onboarding_send_certificates', [ __CLASS__, 'handle_request' ] );
		add_action( 'wp_ajax_stackboost_log_client_event', [ __CLASS__, 'handle_client_log' ] );
	}

	/**
	 * Handle client-side log requests.
	 */
	public static function handle_client_log() {
		// Verify Nonce (reusing certificate nonce as it's the context for dashboard actions)
		if ( ! check_ajax_referer( 'stkb_onboarding_certificate_nonce', 'nonce', false ) ) {
			wp_send_json_error( 'Invalid Nonce' );
		}

		$message = isset( $_POST['message'] ) ? sanitize_text_field( wp_unslash( $_POST['message'] ) ) : '';
		$context = isset( $_POST['context'] ) ? sanitize_text_field( wp_unslash( $_POST['context'] ) ) : 'onboarding_js';

		if ( ! empty( $message ) ) {
			stackboost_log( "[Client] $message", $context );
			wp_send_json_success();
		}
		wp_send_json_error();
	}

	/**
	 * Handle the certificate generation and sending request.
	 */
	public static function handle_request() {
		stackboost_log( 'Certificate Generation Initiated.', 'onboarding' );
		// Verify Nonce
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		if ( ! check_ajax_referer( 'stkb_onboarding_certificate_nonce', 'nonce', false ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
			stackboost_log( 'Certificate Generation Failed: Invalid Nonce. POST nonce: ' . ( $_POST['nonce'] ?? 'NULL' ), 'error' );
			wp_send_json_error( 'Security check failed. Please refresh the page and try again.' );
		}

		// Verify Capability
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_onboarding_dashboard' ) ) {
			if ( ! current_user_can( 'edit_posts' ) ) {
				stackboost_log( 'Permission denied for certificate generation.', 'error' );
				wp_send_json_error( 'Permission denied.' );
			}
		}

		$present_attendees = isset( $_POST['present_attendees'] ) ? json_decode( sanitize_text_field( wp_unslash( $_POST['present_attendees'] ) ), true ) : [];

		if ( empty( $present_attendees ) ) {
			stackboost_log( 'No attendees marked present.', 'onboarding' );
			wp_send_json_success( [ 'message' => 'No attendees present.' ] );
		}

		stackboost_log( 'Processing certificates for ' . count( $present_attendees ) . ' attendees.', 'onboarding' );
		$results = [];

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

		stackboost_log( 'Starting checklist content generation. Sequence Count: ' . count( $sequence_ids ), 'onboarding' );

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
				stackboost_log( "Step ID: {$post->ID} ('{$post->post_title}'). Raw Checklist Length: " . strlen( $raw_checklist ), 'onboarding' );

				$items = array_filter( array_map( 'trim', explode( "\n", $raw_checklist ) ) );
				$clean_items = [];
				foreach ( $items as $item ) {
					$original = trim( $item );
					$clean = preg_replace( '/\s*\[([^\]]*)\]/', '', $original );

					// Debug log for regex behavior
					// stackboost_log( "Item: '$original' -> Cleaned: '$clean'", 'onboarding' );

					if ( empty( $clean ) && ! empty( $original ) ) {
						// If cleaning resulted in empty string (entire item was in brackets),
						// remove just the brackets and keep the text.
						$clean = str_replace( [ '[', ']' ], '', $original );
						stackboost_log( "Item '$original' was fully bracketed. Fallback cleanup result: '$clean'", 'onboarding' );
					}

					if ( ! empty( $clean ) ) {
						$clean_items[] = $clean;
					}
				}

				stackboost_log( "Step ID: {$post->ID}. Cleaned Items Count: " . count( $clean_items ), 'onboarding' );

				if ( ! empty( $clean_items ) ) {
					$content_blocks[] = [
						'type' => 'checklist_group',
						'items' => $clean_items,
						'units' => count( $clean_items )
					];
					$estimated_units += count( $clean_items );
				}
			} else {
				stackboost_log( "Skipping ID $id (Not found or not published stkb_onboarding_step)", 'onboarding' );
			}
		}

		stackboost_log( "Total Content Blocks Generated: " . count( $content_blocks ), 'onboarding' );

		foreach ( $present_attendees as $attendee ) {
			$attendee_name = $attendee['name'];
			$ticket_id = $attendee['id'];

			try {
				$html = self::generate_html( $attendee_name, $trainer_name, $completion_date, $content_blocks, $estimated_units );
				$pdf_content = PdfService::get_instance()->generate_pdf( $html );

				if ( ! $pdf_content ) {
					stackboost_log( 'PDF Generation Failed for: ' . $attendee_name, 'error' );
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

				// Upload to SupportCandy via Internal Method
				$upload_result = self::upload_via_internal_method( $ticket_id, $filepath, $filename, $attendee_name );

				if ( is_wp_error( $upload_result ) ) {
					stackboost_log( 'Upload/Attach failed for: ' . $attendee_name . '. Error: ' . $upload_result->get_error_message(), 'error' );
					$results[] = [
						'attendee' => $attendee_name,
						'status' => 'error',
						'message' => $upload_result->get_error_message()
					];
				} else {
					stackboost_log( 'Certificate sent successfully for: ' . $attendee_name, 'onboarding' );
					$results[] = [
						'attendee' => $attendee_name,
						'status' => 'success',
						'message' => 'Certificate sent.'
					];
				}

				if ( file_exists( $filepath ) ) {
					wp_delete_file( $filepath );
				}
			} catch ( \Throwable $e ) {
				stackboost_log( 'Critical error processing certificate for ' . $attendee_name . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine(), 'error' );
				$results[] = [
					'attendee' => $attendee_name,
					'status'   => 'error',
					'message'  => 'Internal Error: ' . $e->getMessage(),
				];
			}
		}

		wp_send_json_success( [ 'message' => 'Processing complete.', 'results' => $results ] );
	}

	/**
	 * Generate HTML for the PDF.
	 */
	private static function generate_html( $attendee_name, $trainer_name, $date, $blocks, $total_units ) {
		// Get Customization Settings
		$config = Settings::get_config();

		$company_name = $config['certificate_company_name'];
		if ( empty( $company_name ) ) {
			$company_name = get_bloginfo( 'name' );
		}

		$opening_text = $config['certificate_opening_text'];
		if ( empty( $opening_text ) ) {
			$opening_text = 'New Staffmember has completed Onboarding Training with [Trainer Name] and has been present for:';
		}

		$footer_text = $config['certificate_footer_text'];
		if ( empty( $footer_text ) ) {
			$footer_text = 'Completed: [Date] - [Trainer Name]';
		}

		// Replace Placeholders
		$placeholders = [
			'[Trainer Name]' => $trainer_name,
			'[Staff Name]'   => $attendee_name,
			'[Date]'         => $date,
		];

		$opening_text = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $opening_text );
		$footer_text  = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $footer_text );

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
					// Use simple divs with bullets instead of <ul><li> to ensure Dompdf renders text
					$html .= '<div class="shared-blue-box">';
					foreach ( $block['items'] as $item ) {
						// Clean any stray newlines or invalid chars
						$clean_item = trim( preg_replace( '/\s+/', ' ', $item ) );
						// Force color and font-size to ensure visibility inside font-size:0 container
						$html .= '<div style="color: #000000; font-size: 10pt; margin-bottom: 2px;">&bull; ' . esc_html( $clean_item ) . '</div>';
					}
					$html .= '</div>';
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
			.shared-blue-box ul { list-style: disc; padding-left: 15px; margin: 0; color: #000; }
			.checklist-heading { font-weight: bold; margin-bottom: 1mm; margin-top: 2mm; font-size: 10pt; }
			.footer-text { border-top: 1px solid #ccc; padding-top: 3mm; margin-top: 10px; }
		';

		$html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>' . $css . '</style></head><body>
			<div class="certificate-container">
				<div class="header-left">' . esc_html( $company_name ) . '</div>
				<div class="attendee-name">' . esc_html( $attendee_name ) . '</div>
				<div class="completion-statement">
					' . nl2br( esc_html( $opening_text ) ) . '
				</div>
				<div style="font-size: 0;">
					<div class="column">' . $render_blocks( $left_col ) . '</div>
					<div class="column" style="margin-left: 2%;">' . $render_blocks( $right_col ) . '</div>
				</div>
				<div class="footer-text">' . esc_html( $footer_text ) . '</div>
			</div>
		</body></html>';

		return $html;
	}

	/**
	 * Upload via Internal Methods (WPSC_Attachment and WPSC_Thread).
	 */
	private static function upload_via_internal_method( $ticket_id, $filepath, $filename, $attendee_name ) {
		if ( ! class_exists( 'WPSC_Attachment' ) || ! class_exists( 'WPSC_Thread' ) ) {
			return new \WP_Error( 'missing_dependency', 'SupportCandy classes not found.' );
		}

		// 1. Handle File Saving
		$upload_dir = wp_upload_dir();
		$today      = new \DateTime();
		$rel_path   = '/wpsc/' . $today->format( 'Y' ) . '/' . $today->format( 'm' ) . '/';
		$target_dir = $upload_dir['basedir'] . $rel_path;

		if ( ! file_exists( $target_dir ) ) {
			wp_mkdir_p( $target_dir );
		}

		// Ensure unique filename
		$base_filename = sanitize_file_name( pathinfo( $filename, PATHINFO_FILENAME ) );
		$ext           = 'pdf';
		$final_filename = time() . '_' . $base_filename . '.' . $ext;
		$final_path     = $target_dir . $final_filename;
		$final_rel_path = $rel_path . $final_filename;

		if ( ! copy( $filepath, $final_path ) ) {
			return new \WP_Error( 'file_copy_error', 'Failed to save certificate file.' );
		}

		// 2. Create Attachment Record
		$attachment_data = [
			'name'         => $base_filename . '.' . $ext,
			'file_path'    => $final_rel_path,
			'is_image'     => 0,
			'is_active'    => 1, // Active immediately
			'source'       => 'note', // Attached to a note
			'ticket_id'    => $ticket_id,
			'date_created' => $today->format( 'Y-m-d H:i:s' ),
		];

		$attachment = \WPSC_Attachment::insert( $attachment_data );
		if ( ! $attachment || ! $attachment->id ) {
			return new \WP_Error( 'db_insert_error', 'Failed to create attachment record.' );
		}

		// 3. Create Thread (Note)
		$current_user = wp_get_current_user();
		$agent = \WPSC_Agent::get_by_user_id( $current_user->ID );
		$customer = \WPSC_Customer::get_by_email( $current_user->user_email );

		$thread_data = [
			'ticket'      => $ticket_id, // Schema key is 'ticket', not 'ticket_id'
			'customer'    => ( $customer && $customer->id ) ? $customer->id : 0,
			'type'        => 'note',
			'body'        => "Onboarding Certificate for $attendee_name attached.",
			'attachments' => $attachment->id, // Pass single ID or pipe-separated string, not array
			'is_active'   => 1,
			'date_created'=> $today->format( 'Y-m-d H:i:s' ),
			'date_updated'=> $today->format( 'Y-m-d H:i:s' ),
		];

		$thread = \WPSC_Thread::insert( $thread_data );
		if ( ! $thread || ! $thread->id ) {
			return new \WP_Error( 'thread_insert_error', 'Failed to create note.' );
		}

		return true;
	}
}