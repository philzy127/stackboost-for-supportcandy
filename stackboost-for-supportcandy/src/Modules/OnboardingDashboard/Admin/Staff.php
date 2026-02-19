<?php


namespace StackBoost\ForSupportCandy\Modules\OnboardingDashboard\Admin;

if ( ! defined( 'ABSPATH' ) ) exit;

class Staff {

	/**
	 * Initialize Staff page.
	 */
	public static function init() {
		add_action( 'wp_ajax_stackboost_onboarding_load_staff_data', [ __CLASS__, 'ajax_load_staff_data' ] );
	}

	/**
	 * Render page.
	 */
	public static function render_page() {
		stackboost_log( 'Rendering Staff Page...', 'onboarding' );

		// Check configuration first
		$config = Settings::get_config();
		if ( empty( $config['request_type_field'] ) || empty( $config['request_type_id'] ) || empty( $config['field_staff_name'] ) || empty( $config['field_onboarding_date'] ) || empty( $config['field_cleared'] ) ) {
			?>
			<div class="stackboost-card stackboost-card-connected">
				<h2 style="margin-top: 0; padding-top: 10px;"><?php esc_html_e( 'Staff Management - Onboarding Tickets', 'stackboost-for-supportcandy' ); ?></h2>
				<div class="notice notice-warning inline">
					<p>
						<?php
						printf(
							/* translators: %s: settings url */
							wp_kses_post( __( 'Please fully configure the Onboarding settings (Request Type, Onboarding Options, Staff Name, Onboarding Date, and Cleared Field) in the <strong><a href="%s">Settings</a></strong> tab to view the staff list.', 'stackboost-for-supportcandy' ) ),
							esc_url( admin_url( 'admin.php?page=stackboost-onboarding-dashboard&tab=settings' ) )
						);
						?>
					</p>
				</div>
			</div>
			<?php
			return;
		}
		?>
		<div>
			<!-- Header & Controls -->
			<div class="stackboost-card stackboost-card-connected">
				<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 15px;">
					<h2 style="margin:0; padding:0;"><?php esc_html_e( 'Staff Management - Onboarding Tickets', 'stackboost-for-supportcandy' ); ?></h2>
					<div style="margin-right: 5px; margin-top: 10px;">
						<span id="stkb-refresh-status" style="margin-right: 10px; display: none; vertical-align: middle;"></span>
						<button id="stkb-force-refresh" class="button"><?php esc_html_e( 'Update Now', 'stackboost-for-supportcandy' ); ?></button>
					</div>
				</div>

				<div id="stackboost-staff-content">
					<div style="text-align: center; padding: 40px;">
						<span class="spinner is-active" style="float: none; margin: 0 10px 0 0;"></span>
						<?php esc_html_e( 'Loading Staff Data...', 'stackboost-for-supportcandy' ); ?>
					</div>
				</div>

			</div> <!-- Close Main Connected Card -->

			<script type="text/javascript">
				jQuery(document).ready(function($) {
					var $content = $('#stackboost-staff-content');
					var $status = $('#stkb-refresh-status');
					var $button = $('#stkb-force-refresh');

					function loadData(forceRefresh) {
						$status.text('<?php echo esc_js( __( 'Loading ticket data...', 'stackboost-for-supportcandy' ) ); ?>').css('color', 'black').show();
						$button.prop('disabled', true);

						if (forceRefresh) {
							$content.html('<div style="text-align: center; padding: 40px;"><span class="spinner is-active" style="float: none; margin: 0 10px 0 0;"></span> <?php echo esc_js( __( 'Refreshing data...', 'stackboost-for-supportcandy' ) ); ?></div>');
						}

						$.post(ajaxurl, {
							action: 'stackboost_onboarding_load_staff_data',
							nonce: '<?php echo esc_js( wp_create_nonce( "stkb_load_staff_nonce" ) ); ?>',
							force_refresh: forceRefresh ? 1 : 0
						}, function(response) {
							if (response.success) {
								$content.html(response.data.html);
								$status.text('<?php echo esc_js( __( 'Data loaded.', 'stackboost-for-supportcandy' ) ); ?>').css('color', 'green').fadeOut(3000);
							} else {
								$content.html('<div class="notice notice-error inline"><p>' + (response.data.message || 'Unknown error') + '</p></div>');
								$status.text('<?php echo esc_js( __( 'Load failed.', 'stackboost-for-supportcandy' ) ); ?>').css('color', 'red');
							}
							$button.prop('disabled', false);
						}).fail(function() {
							$content.html('<div class="notice notice-error inline"><p><?php echo esc_js( __( 'Server error. Please try again.', 'stackboost-for-supportcandy' ) ); ?></p></div>');
							$status.text('<?php echo esc_js( __( 'Server error.', 'stackboost-for-supportcandy' ) ); ?>').css('color', 'red');
							$button.prop('disabled', false);
						});
					}

					// Initial Load
					loadData(false);

					// Button Click
					$button.on('click', function() {
						loadData(true);
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
		$staff_name_field_key         = $config['field_staff_name']; // Identify name column

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
											if ( $raw_value instanceof \DateTime ) {
												$date = $raw_value;
											} else {
												$date = new \DateTime( $raw_value );
											}
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
									$icon_class = self::get_phone_icon( $phone_type_to_display );
									if ( $icon_class ) {
										$display_value .= ' <span class="dashicons ' . esc_attr( $icon_class ) . '" style="font-size: 16px; width: 16px; height: 16px; vertical-align: middle; margin-left: 5px;"></span>';
									}
								}

								// -- Generic: Arrays (e.g. Multi-selects) --
								if ( is_array( $display_value ) ) {
									// SupportCandy to_array might return array of IDs or values.
									// If it's simple array, implode.
									$display_value = implode( ', ', $display_value );
								}

								// -- Logic: Certificate Checkmark --
								if ( $slug === $staff_name_field_key ) {
									if ( ! empty( $ticket['has_certificate'] ) ) {
										$display_value .= ' <span class="dashicons dashicons-yes-alt" style="color: green; font-size: 18px; width: 18px; height: 18px; vertical-align: middle; margin-left: 5px;" title="' . esc_attr__( 'Certificate Generated', 'stackboost-for-supportcandy' ) . '"></span>';
									}
								}

								// Handle Objects before passing to wp_kses_post (specifically DateTime)
								if ( $display_value instanceof \DateTime ) {
									$display_value = $display_value->format( 'Y-m-d H:i:s' );
								} elseif ( is_object( $display_value ) ) {
									// Fallback for other objects to string conversion or empty
									if ( method_exists( $display_value, '__toString' ) ) {
										$display_value = (string) $display_value;
									} else {
										// Cannot display generic object, set to empty to avoid fatal error
										$display_value = '';
									}
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
	 * Get Dashicon class for a phone type.
	 */
	private static function get_phone_icon( $type ) {
		$map = [
			'mobile'  => 'dashicons-smartphone',
			'work'    => 'dashicons-building',
			'home'    => 'dashicons-admin-home',
			'fax'     => 'dashicons-printer',
			'generic' => 'dashicons-phone',
		];
		return $map[ $type ] ?? 'dashicons-phone';
	}

	/**
	 * Get and sort tickets (Legacy Wrapper).
	 * Now delegates to Data\TicketService.
	 */
	public static function get_sorted_tickets_internal() {
		return \StackBoost\ForSupportCandy\Modules\OnboardingDashboard\Data\TicketService::get_onboarding_tickets();
	}

	/**
	 * Format Phone.
	 */
	private static function format_phone( $phone ) {
		if ( ! is_string( $phone ) && ! is_numeric( $phone ) ) {
			return $phone;
		}
		$phone = preg_replace('/[^0-9]/', '', (string) $phone);
		if (strlen($phone) === 10) {
			return preg_replace('/(\d{3})(\d{3})(\d{4})/', '($1) $2-$3', $phone);
		}
		return $phone;
	}

	/**
	 * AJAX: Load Staff Data.
	 */
	public static function ajax_load_staff_data() {
		check_ajax_referer( 'stkb_load_staff_nonce', 'nonce' );

		$force_refresh = isset( $_POST['force_refresh'] ) && '1' === $_POST['force_refresh'];
		$transient_key = 'stackboost_onboarding_tickets_cache';

		if ( $force_refresh ) {
			delete_transient( $transient_key );
		}

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
				$onboarding_tickets = $onboarding_tickets; // Keep WP_Error
			}
		}

		// Process Result
		$result = is_wp_error( $cached_data ) ? $cached_data : ( $cached_data['data'] ?? [] );
		if ( ! isset( $result ) && is_wp_error( $cached_data ) ) {
			$result = $cached_data; // Propagate error
		} elseif ( ! isset( $result ) ) {
			// Emergency fallback if cache structure is weird
			$result = self::get_sorted_tickets_internal();
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		// Buffer Output
		ob_start();

		$last_updated_timestamp = $cached_data['timestamp'] ?? null;
		if ( $last_updated_timestamp ) {
			$last_updated_string = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_updated_timestamp );
			echo '<p style="margin-bottom: 15px;"><em>' . sprintf( esc_html__( 'Data current as of: %s', 'stackboost-for-supportcandy' ), esc_html( $last_updated_string ) ) . '</em></p>';
		}

		echo '<hr style="margin: 20px 0;">';
		self::render_table( $result['previous_onboarding'] ?? [], __( 'Previous Onboarding Tickets', 'stackboost-for-supportcandy' ) );

		echo '<hr style="margin: 20px 0;">';
		self::render_table( $result['this_week_onboarding'] ?? [], __( 'Onboarding Tickets for This Week', 'stackboost-for-supportcandy' ) );

		echo '<hr style="margin: 20px 0;">';
		self::render_table( $result['future_onboarding'] ?? [], __( 'Future Onboarding Tickets', 'stackboost-for-supportcandy' ) );

		echo '<hr style="margin: 20px 0;">';
		self::render_table( $result['uncleared_or_unscheduled'] ?? [], __( 'Onboarding Tickets Not Yet Scheduled or Cleared', 'stackboost-for-supportcandy' ) );

		$html = ob_get_clean();

		wp_send_json_success( [ 'html' => $html ] );
	}
}