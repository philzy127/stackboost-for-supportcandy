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

		stackboost_log( 'TicketService: Config loaded. Request Type IDs: ' . print_r( $config['request_type_id'], true ), 'onboarding' );
		stackboost_log( 'TicketService: Inactive Statuses: ' . print_r( $config['inactive_statuses'], true ), 'onboarding' );

		$request_type_key   = $config['request_type_field'];
		$onboarding_type_ids = $config['request_type_id']; // This is now an array
		$inactive_ids       = $config['inactive_statuses']; // Array
		$onboarding_date_key = $config['field_onboarding_date'];
		$cleared_key        = $config['field_cleared'];

		if ( empty( $request_type_key ) || empty( $onboarding_type_ids ) ) {
			stackboost_log( 'TicketService: Missing configuration. Aborting.', 'onboarding' );
			return new \WP_Error( 'missing_config', 'Onboarding Settings not configured.' );
		}

		// Ensure IDs are an array (robustness)
		if ( ! is_array( $onboarding_type_ids ) ) {
			$onboarding_type_ids = [ $onboarding_type_ids ];
		}

		// 1. Fetch Onboarding Tickets via Meta Query (Scalable)
		// We use meta_query to let the DB do the heavy lifting of filtering by request type.
		// items_per_page = -1 or 0 usually means All. Let's use 0 as per SC convention.

		// Build Meta Query for Request Type
		$args = [
			'items_per_page' => 0,
			'meta_query' => [
				[
					'key'     => $request_type_key, // e.g. 'cust_40'
					'value'   => $onboarding_type_ids, // e.g. [69, 72]
					'compare' => 'IN',
				]
			]
		];

		stackboost_log( 'TicketService: Attempting DB-level filtering via meta_query for ' . $request_type_key, 'onboarding' );

		try {
			$tickets_result = \WPSC_Ticket::find( $args );
			$all_tickets_raw = isset( $tickets_result['results'] ) ? $tickets_result['results'] : [];
		} catch ( \Throwable $e ) {
			stackboost_log( 'TicketService: Error fetching tickets with meta_query: ' . $e->getMessage(), 'error' );
			$all_tickets_raw = [];
		}

		stackboost_log( 'TicketService: WPSC_Ticket::find (meta_query) returned ' . count($all_tickets_raw) . ' tickets.', 'onboarding' );

		// 2. PHP-Side Verification (Safety Net)
		// We iterate through results to ensure they actually match.
		// This handles cases where SupportCandy might ignore the meta_query for custom table columns.
		$all_tickets_objects = [];
		foreach ( $all_tickets_raw as $ticket_obj ) {
			$val = $ticket_obj->$request_type_key ?? null;
			$matches = false;

			// Handle WPSC_Option objects where isset($val->id) fails due to magic getters
			if ( is_object($val) ) {
				$id_check = $val->id;
				if ( $id_check && in_array( $id_check, $onboarding_type_ids ) ) {
					$matches = true;
				}
			} elseif ( is_array($val) ) {
				foreach($val as $v) {
					if ( is_object($v) ) {
						$id_check = $v->id;
						if ( $id_check && in_array( $id_check, $onboarding_type_ids ) ) {
							$matches = true;
							break;
						}
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

		stackboost_log( 'TicketService: Found ' . count( $all_tickets_objects ) . ' matching onboarding tickets after PHP verification.', 'onboarding' );

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
			if ( is_object( $ticket_obj->status ) ) {
				// Direct access required for SupportCandy objects (isset fails)
				$t_array['status'] = $ticket_obj->status->id;
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
				stackboost_log( sprintf( 'TicketService: Skipping Ticket #%d due to inactive status (ID: %s).', $ticket['id'], $status_id ), 'onboarding' );
				continue;
			}

			$cleared_val = $ticket[$cleared_key] ?? null;
			$date_str = $ticket[$onboarding_date_key] ?? null;

			$is_cleared = ! empty( $cleared_val );

			if ( $is_cleared && ! empty( $date_str ) ) {
				try {
					if ( $date_str instanceof \DateTime ) {
						$date = $date_str;
					} else {
						$date = new \DateTime( $date_str );
					}

					if ( $date < $start_week ) {
						$sorted['previous_onboarding'][] = $ticket;
					} elseif ( $date >= $start_week && $date <= $end_week ) {
						$sorted['this_week_onboarding'][] = $ticket;
					} else {
						$sorted['future_onboarding'][] = $ticket;
					}
				} catch ( \Exception $e ) {
					stackboost_log( sprintf( 'TicketService: Date parsing error for Ticket #%d: %s', $ticket['id'], $e->getMessage() ), 'onboarding' );
					// Ignore date error, treat as unscheduled/problematic
				}
			} else {
				$sorted['uncleared_or_unscheduled'][] = $ticket;
			}
		}

		return $sorted;
	}
}
