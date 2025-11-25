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

		// Phone Logic
		$phone_mode             = $config['phone_config_mode']; // 'single' or 'multiple'
		$phone_single_field     = $config['phone_single_field'];
		$phone_has_type         = $config['phone_has_type'];
		$phone_type_field       = $config['phone_type_field'];
		$phone_mobile_val       = $config['phone_type_value_mobile'];
		$phone_multi_config     = $config['phone_multi_config']; // array of ['field', 'type']

		// Pre-process Multi-Config for faster lookup: [ 'field_slug' => 'type_slug' ]
		$phone_field_map = [];
		if ( 'multiple' === $phone_mode && is_array( $phone_multi_config ) ) {
			foreach ( $phone_multi_config as $item ) {
				if ( ! empty( $item['field'] ) ) {
					$phone_field_map[ $item['field'] ] = $item['type'] ?? 'generic';
				}
			}
		}

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

								// -- Logic: Phone Formatting & Icon Logic --
								$is_phone_field = false;
								$phone_type_to_display = '';

								// Check if this column is a configured phone field
								if ( 'single' === $phone_mode ) {
									if ( $slug === $phone_single_field ) {
										$is_phone_field = true;
										// Determine Type
										if ( 'yes' === $phone_has_type && ! empty( $phone_type_field ) ) {
											// Check value of the Type Field for this ticket
											$type_val = $ticket[ $phone_type_field ] ?? '';
											// SupportCandy might return array for options
											if ( is_array( $type_val ) ) {
												if ( in_array( $phone_mobile_val, $type_val ) ) {
													$phone_type_to_display = 'mobile';
												} else {
													// Try to map based on option label text if possible?
													// For now, if not mobile, default to generic or check logic.
													// Requirement: "If ... matches the Mobile Type (Scenario A), append the mobile icon."
													// It doesn't strictly say to map others, but user asked for standard icons.
													// We can default to 'generic' or try to guess. Let's stick to 'mobile' specific check first.
													$phone_type_to_display = 'generic';
												}
											} elseif ( $type_val == $phone_mobile_val ) {
												$phone_type_to_display = 'mobile';
											} else {
												$phone_type_to_display = 'generic';
											}
										} else {
											// No type field -> Generic phone
											$phone_type_to_display = 'generic';
										}
									}
								} elseif ( 'multiple' === $phone_mode ) {
									if ( isset( $phone_field_map[ $slug ] ) ) {
										$is_phone_field = true;
										$phone_type_to_display = $phone_field_map[ $slug ];
									}
								}

								// Format and Add Icon
								if ( $is_phone_field && ! empty( $raw_value ) ) {
									// Format
									$display_value = self::format_phone( $display_value );

									// Get Icon
									$icon_name = self::get_phone_icon( $phone_type_to_display );
									if ( $icon_name ) {
										$display_value .= ' <i class="material-icons" style="font-size: 1em; vertical-align: middle; margin-left: 5px;">' . esc_html( $icon_name ) . '</i>';
									}
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
	 * Get Material Icon name for a phone type.
	 */
	private static function get_phone_icon( $type ) {
		$map = [
			'mobile'  => 'smartphone',
			'work'    => 'building', // User requested 'building' for Office/Work
			'home'    => 'home',
			'fax'     => 'print',
			'generic' => 'phone',    // User requested 'phone' for Generic
		];
		return $map[ $type ] ?? 'phone';
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

		// Determine fields to hydrate based on new logic
		$fields_to_hydrate = $config['table_columns'];
		$fields_to_hydrate[] = $config['field_onboarding_date'];
		$fields_to_hydrate[] = $config['field_cleared'];
		$fields_to_hydrate[] = $config['request_type_field'];

		// Phone fields
		if ( 'single' === $config['phone_config_mode'] ) {
			$fields_to_hydrate[] = $config['phone_single_field'];
			if ( 'yes' === $config['phone_has_type'] ) {
				$fields_to_hydrate[] = $config['phone_type_field'];
			}
		} elseif ( 'multiple' === $config['phone_config_mode'] ) {
			if ( is_array( $config['phone_multi_config'] ) ) {
				foreach ( $config['phone_multi_config'] as $p_conf ) {
					if ( ! empty( $p_conf['field'] ) ) {
						$fields_to_hydrate[] = $p_conf['field'];
					}
				}
			}
		}

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
