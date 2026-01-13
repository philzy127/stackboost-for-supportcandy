<?php

namespace StackBoost\ForSupportCandy\Modules\OnboardingDashboard\Data;

use StackBoost\ForSupportCandy\Modules\OnboardingDashboard\Admin\Settings;

class TicketService {

	/**
	 * Get and sort onboarding tickets.
	 *
	 * @return array|\WP_Error
	 */
	public static function get_onboarding_tickets() {
		stackboost_log( 'TicketService: Fetching sorted tickets...', 'onboarding' );
		if ( ! class_exists( '\WPSC_Ticket' ) ) {
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
		stackboost_log( 'TicketService: Found ' . count( $all_tickets_objects ) . ' active onboarding tickets.', 'onboarding' );

		// Convert objects to array structure expected by consumers
		$all_tickets = [];

		// Determine fields to hydrate based on logic requirements
		$fields_to_hydrate = $config['table_columns'];
		$fields_to_hydrate[] = $config['field_staff_name'];
		$fields_to_hydrate[] = $config['field_onboarding_date'];
		$fields_to_hydrate[] = $config['field_cleared'];
		$fields_to_hydrate[] = $config['request_type_field'];

		// Phone fields hydration
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

		// Optimize Certificate Check: Collect all IDs first
		$ticket_ids = [];
		foreach ( $all_tickets_objects as $ticket_obj ) {
			$ticket_ids[] = $ticket_obj->id;
		}

		// Batch check for certificates
		$certificates_map = [];
		if ( ! empty( $ticket_ids ) ) {
			global $wpdb;

			// Dynamic table name detection
			$table_name = $wpdb->prefix . 'wpsc_attachments';
			$table_name_like = $wpdb->esc_like( $table_name );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name_like ) ) != $table_name ) {
				$table_name = $wpdb->prefix . 'psmsc_attachments';
			}

			stackboost_log( "TicketService: Checking certificates in table: $table_name", 'onboarding' );

			$ids_placeholder = implode( ',', array_fill( 0, count( $ticket_ids ), '%d' ) );
			// $table_name is derived from $wpdb->prefix so it is safe to interpolate directly, but usually safer to use $wpdb->prefix logic strictly.
			// Since we checked against SHOW TABLES output above, it matches a real table name.
			$safe_table_name = esc_sql( $table_name );
			$query = "SELECT ticket_id, COUNT(*) as count FROM {$safe_table_name} WHERE ticket_id IN ($ids_placeholder) AND name LIKE %s GROUP BY ticket_id";

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$prepared_query = $wpdb->prepare( $query, array_merge( $ticket_ids, [ 'Onboarding_Certificate_%' ] ) );

			stackboost_log( "TicketService: Query: $prepared_query", 'onboarding' );

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$results = $wpdb->get_results( $prepared_query );

			if ( $results ) {
				stackboost_log( "TicketService: Certificate Query Results Found: " . count( $results ), 'onboarding' );
				foreach ( $results as $row ) {
					if ( $row->count > 0 ) {
						$certificates_map[ $row->ticket_id ] = true;
					}
				}
			} else {
				stackboost_log( "TicketService: No certificates found matching criteria.", 'onboarding' );
			}
		}

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

            // Hydrate certificate status
            $t_array['has_certificate'] = isset( $certificates_map[ $ticket_obj->id ] );
			if ( $t_array['has_certificate'] ) {
				stackboost_log( "TicketService: Ticket ID {$ticket_obj->id} has a certificate.", 'onboarding' );
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

			// Check against inactive statuses
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
					// Ignore date error, treat as unscheduled/problematic
				}
			} else {
				$sorted['uncleared_or_unscheduled'][] = $ticket;
			}
		}

		return $sorted;
	}
}
