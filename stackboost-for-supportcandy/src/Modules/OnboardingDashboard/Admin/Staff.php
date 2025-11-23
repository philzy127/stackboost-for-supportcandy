<?php

namespace StackBoost\ForSupportCandy\Modules\OnboardingDashboard\Admin;

class Staff {

	/**
	 * Option for credentials (reuse from Settings).
	 */
	const OPTION_USERNAME = ApiSettings::OPTION_USERNAME;
	const OPTION_SECRET_KEY = ApiSettings::OPTION_SECRET_KEY;

	/**
	 * Initialize Staff page.
	 */
	public static function init() {
		add_action( 'wp_ajax_stackboost_onboarding_refresh_cache', [ __CLASS__, 'ajax_force_refresh_cache' ] );
	}

	/**
	 * Render page.
	 */
	public static function render_page() {
		stackboost_log( 'Rendering Staff Page...', 'onboarding' );

		// Check configuration first
		$config = Settings::get_config();
		if ( empty( $config['request_type_field'] ) || empty( $config['request_type_id'] ) ) {
			?>
			<div class="wrap">
				<h2><?php esc_html_e( 'Staff Management - Onboarding Tickets', 'stackboost-for-supportcandy' ); ?></h2>
				<div class="notice notice-warning inline">
					<p>
						<?php
						printf(
							wp_kses_post( __( 'Please configure the Onboarding settings (Request Type, ID, and Column Mapping) in the <strong><a href="%s">Settings</a></strong> tab to view the staff list.', 'stackboost-for-supportcandy' ) ),
							esc_url( admin_url( 'admin.php?page=stackboost-onboarding-dashboard&tab=settings' ) )
						);
						?>
					</p>
				</div>
			</div>
			<?php
			return;
		}

		$transient_key = 'stackboost_onboarding_tickets_cache';

		?>
		<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
		<div>
			<h2><?php esc_html_e( 'Staff Management - Onboarding Tickets', 'stackboost-for-supportcandy' ); ?></h2>

			<button id="stkb-force-refresh" class="button" style="margin-bottom: 15px;"><?php esc_html_e( 'Update Now', 'stackboost-for-supportcandy' ); ?></button>
			<span id="stkb-refresh-status" style="margin-left: 10px; display: none; vertical-align: middle;"></span>

			<?php
			$cached_data = get_transient( $transient_key );

			if ( false === $cached_data ) {
				$onboarding_tickets = self::get_sorted_tickets_internal();
				if ( ! is_wp_error( $onboarding_tickets ) ) {
					$data_to_cache = [
						'data'      => $onboarding_tickets,
						'timestamp' => time(),
					];
					set_transient( $transient_key, $data_to_cache, 10 * MINUTE_IN_SECONDS );
					$cached_data = $data_to_cache;
				} else {
					$onboarding_tickets = $onboarding_tickets;
				}
			}

			$onboarding_tickets = is_wp_error( $cached_data ) ? $cached_data : ( $cached_data['data'] ?? [] );
			// Handle case where $onboarding_tickets is a WP_Error if fetch failed and not cached
			if ( ! isset( $onboarding_tickets ) && is_wp_error( $cached_data ) ) {
				$onboarding_tickets = $cached_data;
			} elseif ( ! isset( $onboarding_tickets ) ) {
				// If neither (fresh fetch failed), try to fetch fresh one last time
				$onboarding_tickets = self::get_sorted_tickets_internal();
			}


			$last_updated_timestamp = $cached_data['timestamp'] ?? null;

			if ( $last_updated_timestamp ) {
				$last_updated_string = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_updated_timestamp );
				echo '<p style="margin-bottom: 15px;"><em>' . sprintf( esc_html__( 'Data current as of: %s', 'stackboost-for-supportcandy' ), esc_html( $last_updated_string ) ) . '</em></p>';
			}

			if ( is_wp_error( $onboarding_tickets ) ) {
				?>
				<div class="notice notice-error">
					<p><?php printf( esc_html__( 'Failed to retrieve tickets: %s', 'stackboost-for-supportcandy' ), esc_html( $onboarding_tickets->get_error_message() ) ); ?></p>
				</div>
				<?php
				return;
			}

			// Render Tables
			self::render_table( $onboarding_tickets['previous_onboarding'] ?? [], __( 'Previous Onboarding Tickets', 'stackboost-for-supportcandy' ) );
			echo '<hr>';
			self::render_table( $onboarding_tickets['this_week_onboarding'] ?? [], __( 'Onboarding Tickets for This Week', 'stackboost-for-supportcandy' ) );
			echo '<hr>';
			self::render_table( $onboarding_tickets['future_onboarding'] ?? [], __( 'Future Onboarding Tickets', 'stackboost-for-supportcandy' ) );
			echo '<hr>';
			self::render_table( $onboarding_tickets['uncleared_or_unscheduled'] ?? [], __( 'Onboarding Tickets Not Yet Scheduled or Cleared', 'stackboost-for-supportcandy' ) );
			?>

			<script type="text/javascript">
				jQuery(document).ready(function($) {
					$('#stkb-force-refresh').on('click', function() {
						var $button = $(this);
						var $status = $('#stkb-refresh-status');

						$button.prop('disabled', true);
						$status.text('<?php esc_html_e( "Updating...", "stackboost-for-supportcandy" ); ?>').css('color', 'black').show();

						$.post(ajaxurl, {
							action: 'stackboost_onboarding_refresh_cache',
							nonce: '<?php echo wp_create_nonce( "stkb_refresh_cache_nonce" ); ?>'
						}, function(response) {
							if (response.success) {
								$status.text('<?php esc_html_e( "Data updated! Reloading...", "stackboost-for-supportcandy" ); ?>').css('color', 'green');
								setTimeout(function() { location.reload(); }, 1000);
							} else {
								$status.text('<?php esc_html_e( "Update failed.", "stackboost-for-supportcandy" ); ?>').css('color', 'red');
								$button.prop('disabled', false);
							}
						});
					});
				});
			</script>
		</div>
		<?php
	}

	/**
	 * Render a single table.
	 */
	private static function render_table( $tickets, $title ) {
		$config = Settings::get_config();

		// Custom field keys from config
		$onboarding_date_field_key = $config['field_onboarding_date'];
		$full_name_field_key       = $config['field_full_name'];
		$position_field_key        = $config['field_position'];
		$supervisor_name_field_key = $config['field_supervisor'];
		$personal_email_field_key  = $config['field_email'];
		$shipping_address_field_key = $config['field_shipping_address'];
		$tracking_number_field_key = $config['field_tracking_number'];
		$personal_phone_field_key  = $config['field_phone'];
		$is_mobile_radio_field_key = $config['field_is_mobile'];
		$onboarding_cleared_field_key = $config['field_cleared'];
		$mobile_option_id          = $config['mobile_option_id'];

		?>
		<h2><?php echo esc_html( $title ); ?></h2>
		<?php if ( ! empty( $tickets ) ) : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Ticket Number', 'stackboost-for-supportcandy' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Full Name', 'stackboost-for-supportcandy' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Position', 'stackboost-for-supportcandy' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Supervisor', 'stackboost-for-supportcandy' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Personal Email', 'stackboost-for-supportcandy' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Personal Phone', 'stackboost-for-supportcandy' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Shipping Address', 'stackboost-for-supportcandy' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Tracking Number(s)', 'stackboost-for-supportcandy' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Onboarding Date', 'stackboost-for-supportcandy' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Onboarding Cleared?', 'stackboost-for-supportcandy' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $tickets as $ticket ) :
						$onboarding_date_string = $ticket[$onboarding_date_field_key] ?? '';
						$onboarding_date = !empty($onboarding_date_string) ? new \DateTime($onboarding_date_string) : null;

						$phone_raw = $ticket[$personal_phone_field_key] ?? 'N/A';
						$phone_display = self::format_phone( $phone_raw );
						$is_mobile = false;
						if ( isset( $ticket[$is_mobile_radio_field_key] ) && ! empty( $mobile_option_id ) ) {
							$val = $ticket[$is_mobile_radio_field_key];
							if ( (is_array($val) && in_array($mobile_option_id, $val)) || $val == $mobile_option_id ) {
								$is_mobile = true;
							}
						}
						$phone_icon = '';
						if ( $phone_raw !== 'N/A' && !empty($phone_raw) ) {
							$icon = $is_mobile ? 'smartphone' : 'phone';
							$phone_icon = '<i class="material-icons" style="font-size: 1em; vertical-align: middle; margin-left: 5px;">' . $icon . '</i>';
						}

						$ticket_url = admin_url( 'admin.php?page=wpsc-tickets&section=ticket-list&id=' . ( $ticket['id'] ?? '' ) );
						$cleared_val = $ticket[$onboarding_cleared_field_key] ?? null;
						$is_cleared = !empty($cleared_val);
					?>
						<tr>
							<td>
								<?php if ( ! empty( $ticket['id'] ) ) : ?>
									<a href="<?php echo esc_url( $ticket_url ); ?>" target="_blank">
										<?php echo esc_html( $ticket['id'] ); ?>
									</a>
								<?php else : ?>
									N/A
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $ticket[$full_name_field_key] ?? 'N/A' ); ?></td>
							<td><?php echo esc_html( $ticket[$position_field_key] ?? 'N/A' ); ?></td>
							<td><?php echo esc_html( $ticket[$supervisor_name_field_key] ?? 'N/A' ); ?></td>
							<td><?php echo esc_html( $ticket[$personal_email_field_key] ?? 'N/A' ); ?></td>
							<td><?php echo esc_html( $phone_display ) . $phone_icon; ?></td>
							<td><?php echo esc_html( $ticket[$shipping_address_field_key] ?? 'N/A' ); ?></td>
							<td><?php echo esc_html( $ticket[$tracking_number_field_key] ?? 'N/A' ); ?></td>
							<td><?php echo $onboarding_date ? esc_html( $onboarding_date->format( 'Y-m-d' ) ) : 'N/A'; ?></td>
							<td><?php echo $is_cleared ? __( 'Yes', 'stackboost-for-supportcandy' ) : __( 'No', 'stackboost-for-supportcandy' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p><?php esc_html_e( 'No tickets found.', 'stackboost-for-supportcandy' ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Get and sort tickets (Internal Logic).
	 */
	private static function get_sorted_tickets_internal() {
		stackboost_log( 'Fetching sorted tickets via internal logic...', 'onboarding' );
		if ( ! class_exists( 'WPSC_Ticket' ) ) {
			stackboost_log( 'SupportCandy WPSC_Ticket class not found.', 'error' );
			return new \WP_Error( 'missing_dependency', 'SupportCandy WPSC_Ticket class not found.' );
		}

		$config = Settings::get_config();

		$request_type_key   = $config['request_type_field'];
		$onboarding_type_id = $config['request_type_id'];
		$inactive_ids       = $config['inactive_statuses']; // Array
		$onboarding_date_key = $config['field_onboarding_date'];
		$cleared_key        = $config['field_cleared'];

		if ( empty( $request_type_key ) || empty( $onboarding_type_id ) ) {
			return new \WP_Error( 'missing_config', 'Onboarding Settings not configured.' );
		}

		// 1. Fetch All Active Onboarding Tickets
		// Using WPSC_Ticket::find to filter at database level for performance
		$args = [
			'items_per_page' => 0, // All
			'is_active'      => 1, // Only active tickets (not trashed/deleted, though internal is_active means something else usually. Status check is better.)
			'meta_query'     => [
				'relation' => 'AND',
				[
					'slug'    => $request_type_key,
					'compare' => '=',
					'val'     => $onboarding_type_id,
				]
			]
		];

		$tickets_result = \WPSC_Ticket::find( $args );
		$all_tickets_objects = isset( $tickets_result['results'] ) ? $tickets_result['results'] : [];
		stackboost_log( 'Found ' . count( $all_tickets_objects ) . ' active onboarding tickets.', 'onboarding' );

		// Convert objects to array structure expected by render_table to minimize refactoring there
		$all_tickets = [];
		foreach ( $all_tickets_objects as $ticket_obj ) {
			// Convert WPSC_Ticket object to associative array of its properties/custom fields
			$t_array = $ticket_obj->to_array();

            // Manually hydrate custom fields if they are missing from to_array()
            // This is crucial because SupportCandy objects use magic methods for custom fields
            $custom_fields = [
                $config['field_onboarding_date'],
                $config['field_full_name'],
                $config['field_position'],
                $config['field_supervisor'],
                $config['field_email'],
                $config['field_shipping_address'],
                $config['field_tracking_number'],
                $config['field_phone'],
                $config['field_is_mobile'],
                $config['field_cleared'],
                $config['request_type_field']
            ];

            foreach ( $custom_fields as $cf ) {
                // Skip empty config fields
                if ( empty( $cf ) ) {
                    continue;
                }
                if ( ! isset( $t_array[ $cf ] ) ) {
                    // Try retrieving via magic property
                    $t_array[ $cf ] = $ticket_obj->$cf ?? null;
                }
            }

			$all_tickets[] = $t_array;
		}

		$sorted = [
			'previous_onboarding' => [],
			'this_week_onboarding' => [],
			'future_onboarding' => [],
			'uncleared_or_unscheduled' => [],
		];

		$now = new \DateTime();
		$start_week = clone $now;
		$start_week->setISODate( $now->format('o'), $now->format('W'), 1 )->setTime(0,0,0);
		$end_week = clone $start_week;
		$end_week->modify('+6 days')->setTime(23,59,59);

		foreach ( $all_tickets as $ticket ) {
			$status_id = $ticket['status'] ?? null;
			// status_id is int in DB but WPSC_Ticket object property might return object or ID depending on config.
			// to_array() returns raw data, so it should be ID.

			// Check against inactive statuses manually since `is_active` in find() might not cover specific closed statuses
			if ( in_array( $status_id, $inactive_ids ) ) {
				continue;
			}

			$cleared_val = $ticket[$cleared_key] ?? null;
			$date_str = $ticket[$onboarding_date_key] ?? null;

			$is_cleared = ! empty( $cleared_val );

			if ( $is_cleared && ! empty( $date_str ) ) {
				try {
					$date = new \DateTime( $date_str );
					if ( $date < $start_week ) {
						$sorted['previous_onboarding'][] = $ticket;
					} elseif ( $date >= $start_week && $date <= $end_week ) {
						$sorted['this_week_onboarding'][] = $ticket;
					} else {
						$sorted['future_onboarding'][] = $ticket;
					}
				} catch ( \Exception $e ) {
					// Ignore date error
				}
			} else {
				$sorted['uncleared_or_unscheduled'][] = $ticket;
			}
		}

		return $sorted;
	}

	/**
	 * Format Phone.
	 */
	private static function format_phone( $phone ) {
		$phone = preg_replace('/[^0-9]/', '', $phone);
		if (strlen($phone) === 10) {
			return preg_replace('/(\d{3})(\d{3})(\d{4})/', '($1) $2-$3', $phone);
		}
		return $phone;
	}

	/**
	 * AJAX: Refresh Cache.
	 */
	public static function ajax_force_refresh_cache() {
		check_ajax_referer( 'stkb_refresh_cache_nonce', 'nonce' );
		delete_transient( 'stackboost_onboarding_tickets_cache' );
		wp_send_json_success();
	}
}
