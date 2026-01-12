<?php


namespace StackBoost\ForSupportCandy\Modules\OnboardingDashboard\Shortcodes;

if ( ! defined( 'ABSPATH' ) ) exit;

use StackBoost\ForSupportCandy\Modules\OnboardingDashboard\Admin\Staff;
use StackBoost\ForSupportCandy\Modules\OnboardingDashboard\Admin\Settings;

class DashboardShortcode {

	/**
	 * Initialize shortcode.
	 */
	public static function init() {
		add_shortcode( 'stackboost_onboarding_dashboard', [ __CLASS__, 'render_shortcode' ] );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );
	}

	/**
	 * Enqueue Frontend Scripts.
	 */
	public static function enqueue_scripts() {
		global $post;
		$is_shortcode_page = ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'stackboost_onboarding_dashboard' ) );
		$is_block_page     = ( is_a( $post, 'WP_Post' ) && has_block( 'stackboost/onboarding-dashboard', $post ) );
		$is_completion_page = ( isset( $_GET['step_id'] ) && $_GET['step_id'] === 'completion' );

		if ( $is_shortcode_page || $is_block_page || $is_completion_page ) {
            // Attempt to dequeue legacy script to prevent conflicts
            wp_dequeue_script( 'onboarding-dashboard' );
            wp_deregister_script( 'onboarding-dashboard' );

            // Only enqueue manually if it's NOT a block page (block handles CSS via metadata)
            if ( ! $is_block_page ) {
                wp_enqueue_style(
                    'stackboost-onboarding-dashboard',
                    \STACKBOOST_PLUGIN_URL . 'assets/css/onboarding-dashboard.css',
                    [],
                    \STACKBOOST_VERSION
                );
            }

			// Enqueue util script first to ensure logging functions are available
			wp_enqueue_script( 'stackboost-util' );

			wp_enqueue_script(
				'stackboost-onboarding-dashboard',
				\STACKBOOST_PLUGIN_URL . 'assets/js/onboarding-dashboard.js',
				[ 'jquery', 'stackboost-util' ], // Added stackboost-util dependency
				\STACKBOOST_VERSION,
				true
			);

			// Localize Data
			$debug_enabled = false;
			$general_settings = get_option( 'stackboost_settings', [] );
			if ( isset( $general_settings['diagnostic_log_enabled'] ) && $general_settings['diagnostic_log_enabled'] ) {
				$debug_enabled = true;
			}

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

			$full_sequence = [];
			$current_url = get_permalink();

			if ( ! empty( $sequence_ids ) ) {
				foreach ( $sequence_ids as $id ) {
					$p = get_post( $id );
					if ( $p && $p->post_status === 'publish' && $p->post_type === 'stkb_onboarding_step' ) {
						$full_sequence[] = [
							'id' => (int)$id,
							'permalink' => esc_url( add_query_arg( 'step_id', $id, $current_url ) ),
						];
					}
				}
			}

			// Add Virtual Completion Step
			$full_sequence[] = [
				'id' => 'completion',
				'permalink' => esc_url( add_query_arg( 'step_id', 'completion', $current_url ) ),
			];

			$current_step_id = null;
			$current_step_index = -1;

			if ( isset( $_GET['step_id'] ) ) {
				$req = $_GET['step_id'];
				foreach ( $full_sequence as $idx => $data ) {
					if ( $data['id'] == $req ) {
						$current_step_id = $req;
						$current_step_index = $idx;
						break;
					}
				}
			} else {
				if ( ! empty( $full_sequence ) ) {
					$current_step_id = $full_sequence[0]['id'];
					$current_step_index = 0;
				}
			}

			$checklist_items = [];
			if ( $current_step_id && $current_step_id !== 'completion' ) {
				$raw = get_post_meta( $current_step_id, '_stackboost_onboarding_checklist_items', true );
				$checklist_items = array_filter( array_map( 'trim', explode( "\n", $raw ) ) );
			}

			// Fetch This Week Attendees
			$this_week_attendees = [];

			// Fetch fresh data directly using TicketService (no cache)
			$tickets_data = \StackBoost\ForSupportCandy\Modules\OnboardingDashboard\Data\TicketService::get_onboarding_tickets();

			if ( ! is_wp_error( $tickets_data ) && isset( $tickets_data['this_week_onboarding'] ) ) {
				$count = count( $tickets_data['this_week_onboarding'] );
				stackboost_log( "Frontend Dashboard: Found {$count} attendees for this week.", 'onboarding' );

				$config = \StackBoost\ForSupportCandy\Modules\OnboardingDashboard\Admin\Settings::get_config();
				$name_field_key = $config['field_staff_name'] ?? '';

				foreach ( $tickets_data['this_week_onboarding'] as $t ) {
					if ( ! empty( $t['id'] ) ) {
						// Use configured name field, fallback to subject, then ID.
						$name_val = '';
						if ( ! empty( $name_field_key ) && ! empty( $t[ $name_field_key ] ) ) {
							$name_val = $t[ $name_field_key ];
						} else {
							$name_val = $t['subject'] ?? $t['id'];
						}

						$this_week_attendees[] = [
							'id'   => $t['id'],
							'name' => esc_html( $name_val ),
                            'has_certificate' => ! empty( $t['has_certificate'] ),
						];
					}
				}
                stackboost_log( "Frontend Dashboard: Final Attendees Data: " . json_encode($this_week_attendees), 'onboarding' );
			} else {
				stackboost_log( "Frontend Dashboard: Failed to fetch attendees or no data returned. Error: " . ( is_wp_error( $tickets_data ) ? $tickets_data->get_error_message() : 'None' ), 'error' );
			}

			wp_localize_script( 'stackboost-onboarding-dashboard', 'stackboostOdbVars', [
				'checklistItems' => $checklist_items,
				'fullSequence' => $full_sequence,
				'currentStepId' => $current_step_id,
				'currentStepIndex' => $current_step_index,
				'thisWeekAttendees' => $this_week_attendees,
				'isLastStep' => ( $current_step_id === 'completion' ),
				'completionStepId' => 'completion',
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'sendCertificatesNonce' => wp_create_nonce( 'stkb_onboarding_certificate_nonce' ),
				'debugEnabled' => $debug_enabled,
			]);
		}
	}

	/**
	 * Render Shortcode.
	 */
	public static function render_shortcode( $atts ) {
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

		// Identify the requested step
		// Logic mirrors the original plugin to determine active step.
		$full_sequence_map = []; // ID -> Index
		$full_sequence = [];
		foreach ( $sequence_ids as $id ) {
			$full_sequence[] = $id;
		}

		$req_id = $_GET['step_id'] ?? null;
		$is_completion = ( $req_id === 'completion' );
		$is_pre = ( ! $req_id );

		// Find current index
		$current_index = -1;
		if ( $req_id && is_numeric( $req_id ) ) {
			$current_index = array_search( $req_id, $full_sequence );
		}

		$output = '';

		if ( $is_pre ) {
			// Pre-Onboarding View
			$first_link = ! empty( $full_sequence ) ? add_query_arg( 'step_id', $full_sequence[0], get_permalink() ) : '#';
			ob_start();
			?>
			<div class="onboarding-dashboard-container onboarding-pre-session-view">
				<h2 class="onboarding-title">Onboarding Session Preview</h2>
				<div class="onboarding-attendees-preview">
					<h3><span class="dashicons dashicons-groups"></span> Expected Attendees for Today's Session</h3>
					<div id="attendees-list-preview"><p>Loading attendees...</p></div>
				</div>
				<div class="onboarding-actions">
					<button class="onboarding-reset-button" id="reset-checkboxes">Reset All Checkboxes</button>
					<a href="<?php echo esc_url( $first_link ); ?>" class="onboarding-begin-button">Begin Onboarding &rarr;</a>
				</div>
			</div>
			<?php
			return ob_get_clean();
		} elseif ( $is_completion ) {
			// Completion View
			ob_start();
			?>
			<div class="onboarding-dashboard-container onboarding-completion-stage">
				<h2 class="onboarding-title"><?php esc_html_e('Onboarding Completion', 'stackboost-for-supportcandy'); ?></h2>
				<div class="onboarding-checklist-section">
					<h3><span class="dashicons dashicons-groups"></span> <?php esc_html_e('Attendee Status', 'stackboost-for-supportcandy'); ?></h3>
					<p><?php esc_html_e('Mark attendees who were NOT present for the onboarding session:', 'stackboost-for-supportcandy'); ?></p>
					<ul id="onboarding-attendees-selection" class="onboarding-checklist">
						<li><label><input type="checkbox" disabled> <span><?php esc_html_e('Loading attendees...', 'stackboost-for-supportcandy'); ?></span></label></li>
					</ul>
				</div>
				<button id="send-certificates-button" class="onboarding-next-button" disabled>
					<?php esc_html_e('Send Completion Certificates', 'stackboost-for-supportcandy'); ?>
				</button>
				<p class="onboarding-completion-status-message" style="display:none; margin-top: 10px;"></p>
				<div class="onboarding-navigation">
					<button class="onboarding-back-button">&larr; Back</button>
				</div>
			</div>
			<?php
			return ob_get_clean();
		} else {
			// Normal Step View
			$step_post = get_post( $req_id );
			if ( ! $step_post || $step_post->post_type !== 'stkb_onboarding_step' ) {
				return '<p class="onboarding-error">' . __( 'Invalid Step.', 'stackboost-for-supportcandy' ) . '</p>';
			}

			$checklist_raw = get_post_meta( $step_post->ID, '_stackboost_onboarding_checklist_items', true );
			$notes = get_post_meta( $step_post->ID, '_stackboost_onboarding_notes_content', true );
			$checklist = array_filter( array_map( 'trim', explode( "\n", $checklist_raw ) ) );

			ob_start();
			?>
			<div class="onboarding-dashboard-container">
				<h2 class="onboarding-title"><?php echo esc_html( $step_post->post_title ); ?></h2>
				<div class="onboarding-main-content onboarding-main-step-content">
					<?php echo apply_filters( 'the_content', $step_post->post_content ); ?>

					<?php if ( ! empty( $checklist ) ) : ?>
						<div class="onboarding-checklist-section">
							<h3><span class="dashicons dashicons-yes-alt"></span> Checklist</h3>
							<ul class="onboarding-checklist">
								<?php foreach( $checklist as $item ) :
									$tooltip = '';
									if ( preg_match( '/\[([^\]]*)\]/', $item, $matches ) ) {
										$tooltip = $matches[1];
										$item = preg_replace( '/\s*\[([^\]]*)\]/', '', $item );
									}
									?>
									<li>
										<label>
											<input type="checkbox">
											<span><?php echo esc_html( trim( $item ) ); ?></span>
										</label>
										<?php if ( ! empty( $tooltip ) ) : ?>
											<span class="onboarding-info-icon dashicons dashicons-info" title="<?php echo esc_attr( $tooltip ); ?>"></span>
										<?php endif; ?>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $notes ) ) : ?>
						<div class="onboarding-notes-section">
							<h3><span class="dashicons dashicons-edit"></span> Notes</h3>
							<div class="onboarding-notes-content">
								<?php echo apply_filters( 'the_content', $notes ); ?>
							</div>
						</div>
					<?php endif; ?>
				</div>
				<div class="onboarding-navigation">
					<button class="onboarding-back-button">&larr; Back</button>
					<?php
					$has_next = ( $current_index + 1 < count( $full_sequence ) );
					if ( $has_next ) : ?>
						<button class="onboarding-next-button" disabled>Next Step &rarr;</button>
					<?php else : ?>
						<button class="onboarding-next-button onboarding-complete-button" id="proceed-to-completion-stage" disabled>Final Step &rarr;</button>
					<?php endif; ?>
				</div>
			</div>
			<?php
			return ob_get_clean();
		}
	}
}