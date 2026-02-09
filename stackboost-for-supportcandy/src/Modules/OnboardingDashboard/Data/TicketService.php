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
		$onboarding_type_ids = $config['request_type_id']; // This is now an array
		$inactive_ids       = $config['inactive_statuses']; // Array
		$onboarding_date_key = $config['field_onboarding_date'];
		$cleared_key        = $config['field_cleared'];

		if ( empty( $request_type_key ) || empty( $onboarding_type_ids ) ) {
			return new \WP_Error( 'missing_config', 'Onboarding Settings not configured.' );
		}

		// Ensure IDs are an array (robustness)
		if ( ! is_array( $onboarding_type_ids ) ) {
			$onboarding_type_ids = [ $onboarding_type_ids ];
		}

		// 1. Fetch All Active Onboarding Tickets
		// Using WPSC_Ticket::find to filter at database level for performance
		$args = [
			'items_per_page' => 9999, // Use large number instead of 0 to avoid potential SC bugs
			'page_no'        => 1,
			'is_active'      => 1, // Only active tickets
		];

		try {
			$tickets_result = \WPSC_Ticket::find( $args );
			$all_active_tickets = isset( $tickets_result['results'] ) ? $tickets_result['results'] : [];
		} catch ( \Throwable $e ) {
			stackboost_log( 'TicketService: Error fetching tickets: ' . $e->getMessage(), 'error' );
			$all_active_tickets = [];
			// If critical, return WP_Error, but trying empty list is safer for UI
			// return new \WP_Error( 'db_error', $e->getMessage() );
		}

		// Filter by Request Type (PHP-side to avoid SQL errors with meta_query)
		$all_tickets_objects = [];
		foreach ( $all_active_tickets as $ticket_obj ) {
			$val = $ticket_obj->$request_type_key ?? null;
			$matches = false;

			if ( is_object($val) && isset($val->id) && in_array($val->id, $onboarding_type_ids) ) {
				$matches = true;
			} elseif ( is_array($val) ) {
				foreach($val as $v) {
					if ( is_object($v) && isset($v->id) && in_array($v->id, $onboarding_type_ids) ) {
						$matches = true;
						break;
					}
					if ( is_scalar($v) && in_array($v, $onboarding_type_ids) ) {
						$matches = true;
						break;
					}
				}
			} elseif ( is_scalar($val) && in_array($val, $onboarding_type_ids) ) {
				$matches = true;
			}

			if ( $matches ) {
				$all_tickets_objects[] = $ticket_obj;
			}
		}

		stackboost_log( 'TicketService: Found ' . count( $all_tickets_objects ) . ' matching onboarding tickets after filtering.', 'onboarding' );

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
			// Ensure strict integer typing for IDs to guarantee safety in SQL IN clauses
			$ticket_ids[] = (int) $ticket_obj->id;
		}

		// Batch check for certificates using Repository
		$certificates_map = [];
		if ( ! empty( $ticket_ids ) && class_exists( 'StackBoost\ForSupportCandy\Integration\SupportCandyRepository' ) ) {
			$repo = new \StackBoost\ForSupportCandy\Integration\SupportCandyRepository();
			$certificates_map = $repo->get_tickets_with_certificates( $ticket_ids );

			if ( ! empty( $certificates_map ) ) {
				stackboost_log( "TicketService: Found certificates for " . count( $certificates_map ) . " tickets.", 'onboarding' );
			} else {
				stackboost_log( "TicketService: No certificates found.", 'onboarding' );
			}
		}

		foreach ( $all_tickets_objects as $ticket_obj ) {
			// Manual construction to avoid to_array() triggering warnings on corrupt fields
			$t_array = [
				'id'           => $ticket_obj->id,
				'subject'      => $ticket_obj->subject,
				'date_created' => $ticket_obj->date_created,
				'date_updated' => $ticket_obj->date_updated,
				'date_closed'  => $ticket_obj->date_closed,
				'status'       => $ticket_obj->status, // Object or ID? Usually Object in SC models
				'priority'     => $ticket_obj->priority,
				'category'     => $ticket_obj->category,
				'customer'     => $ticket_obj->customer,
			];

			// Handle Status ID for filtering later
			if ( is_object( $ticket_obj->status ) && isset( $ticket_obj->status->id ) ) {
				$t_array['status'] = $ticket_obj->status->id; // Normalize to ID for inactive check
			} elseif ( is_array( $ticket_obj->status ) && isset( $ticket_obj->status['id'] ) ) {
				$t_array['status'] = $ticket_obj->status['id'];
			}

            // Manually hydrate configured custom fields
            foreach ( $fields_to_hydrate as $cf ) {
				// Use magic getter, but suppress warnings if field definition is missing in SC
				try {
					// Use silence operator @ to catch PHP Warnings (like Undefined array key) which are not caught by try-catch
					$t_array[ $cf ] = @$ticket_obj->$cf ?? null;
				} catch ( \Throwable $e ) {
					$t_array[ $cf ] = null;
				}
            }

            // Hydrate certificate status
            $t_array['has_certificate'] = isset( $certificates_map[ $ticket_obj->id ] );

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
