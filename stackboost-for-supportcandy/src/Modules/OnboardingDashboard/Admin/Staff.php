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

		// Columns Config
		$display_columns = $config['table_columns'];
		$rename_rules    = [];
		if ( is_array( $config['rename_rules'] ) ) {
			foreach ( $config['rename_rules'] as $rule ) {
				$rename_rules[ $rule['field'] ] = $rule['name'];
			}
		}

		// Logic Config
		$onboarding_date_field_key    = $config['field_onboarding_date'];
		$onboarding_cleared_field_key = $config['field_cleared'];

		// Mobile Logic
		$mobile_mode                  = $config['mobile_logic_mode'];
		$field_mobile_number          = $config['field_mobile_number'];
		$field_is_mobile_indicator    = $config['field_is_mobile'];
		$mobile_indicator_value       = $config['mobile_option_id'];

		// Get all columns to resolve Names from Slugs
		$plugin_instance = \StackBoost\ForSupportCandy\WordPress\Plugin::get_instance();
		$all_columns     = $plugin_instance->get_supportcandy_columns();

		?>
		<h2><?php echo esc_html( $title ); ?></h2>
		<?php if ( ! empty( $tickets ) ) : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<!-- Always show Ticket ID first -->
						<th scope="col"><?php esc_html_e( 'Ticket Number', 'stackboost-for-supportcandy' ); ?></th>
						<?php foreach ( $display_columns as $slug ) :
							$label = $rename_rules[ $slug ] ?? ( $all_columns[ $slug ] ?? $slug );
						?>
							<th scope="col"><?php echo esc_html( $label ); ?></th>
						<?php endforeach; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $tickets as $ticket ) :
						$ticket_url = admin_url( 'admin.php?page=wpsc-tickets&section=ticket-list&id=' . ( $ticket['id'] ?? '' ) );
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
							<?php foreach ( $display_columns as $slug ) :
								$raw_value = $ticket[ $slug ] ?? '';
								$display_value = $raw_value;

								// -- Logic: Date Field Formatting --
								if ( $slug === $onboarding_date_field_key ) {
									if ( ! empty( $raw_value ) ) {
										try {
											$date = new \DateTime( $raw_value );
											$display_value = $date->format( 'Y-m-d' );
										} catch ( \Exception $e ) {
											$display_value = $raw_value;
										}
									} else {
										$display_value = 'N/A';
									}
								}

								// -- Logic: Cleared Field Formatting --
								elseif ( $slug === $onboarding_cleared_field_key ) {
									$display_value = ! empty( $raw_value ) ? __( 'Yes', 'stackboost-for-supportcandy' ) : __( 'No', 'stackboost-for-supportcandy' );
								}

								// -- Logic: Phone Formatting & Mobile Icon --
								// 1. Format anything that looks like a raw 10-digit phone number
								if ( is_string( $display_value ) && preg_match( '/^\d{10}$/', preg_replace( '/[^0-9]/', '', $display_value ) ) ) {
									$display_value = self::format_phone( $display_value );
								}

								// 2. Mobile Icon Logic
								$show_mobile_icon = false;

								if ( 'separate_field' === $mobile_mode ) {
									// In separate field mode, if THIS column is the mobile field and has a value, show icon
									if ( $slug === $field_mobile_number && ! empty( $raw_value ) ) {
										$show_mobile_icon = true;
									}
								} elseif ( 'indicator_field' === $mobile_mode ) {
									// In indicator mode, check if this is the phone field AND if the indicator condition is met
									$is_mobile_device = false;
									if ( isset( $ticket[ $field_is_mobile_indicator ] ) && ! empty( $mobile_indicator_value ) ) {
										$val = $ticket[ $field_is_mobile_indicator ];
										if ( ( is_array( $val ) && in_array( $mobile_indicator_value, $val ) ) || $val == $mobile_indicator_value ) {
											$is_mobile_device = true;
										}
									}

									// If this is a mobile device, append icon to the 'field_phone_number' column
									if ( $is_mobile_device && $slug === $config['field_phone_number'] ) {
										$show_mobile_icon = true;
									}
								}

								if ( $show_mobile_icon ) {
									$display_value .= ' <i class="material-icons" style="font-size: 1em; vertical-align: middle; margin-left: 5px;">smartphone</i>';
								}

								// -- Generic: Arrays (e.g. Multi-selects) --
								if ( is_array( $display_value ) ) {
									// SupportCandy to_array might return array of IDs or values.
									// If it's simple array, implode.
									$display_value = implode( ', ', $display_value );
								}

								?>
								<td><?php echo wp_kses_post( $display_value ); ?></td>
							<?php endforeach; ?>
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
			'is_active'      => 1, // Only active tickets
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

		// Convert objects to array structure expected by render_table
		$all_tickets = [];

		// Merge logic fields and display columns to ensure we fetch everything needed
		$fields_to_hydrate = array_merge(
			$config['table_columns'],
			[
				$config['field_onboarding_date'],
				$config['field_cleared'],
				$config['field_phone_number'], // Needed for icon placement in indicator mode
				$config['field_mobile_number'], // Needed for separate field mode
				$config['field_is_mobile'], // Needed for indicator logic
				$config['request_type_field']
			]
		);
		$fields_to_hydrate = array_unique( array_filter( $fields_to_hydrate ) );

		foreach ( $all_tickets_objects as $ticket_obj ) {
			// Convert WPSC_Ticket object to associative array
			$t_array = $ticket_obj->to_array();

            // Manually hydrate custom fields
            foreach ( $fields_to_hydrate as $cf ) {
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
