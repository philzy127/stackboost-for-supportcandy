<?php

namespace StackBoost\ForSupportCandy\Modules\AfterHoursNotice;

use DateTime;
use DateTimeZone;

/**
 * Core business logic for the After Hours Notice feature.
 *
 * This class is responsible for determining if a given timestamp falls
 * within the configured "after hours" period, including weekends and holidays.
 * It has no dependencies on WordPress functions, except when SC classes are called.
 *
 * @package StackBoost\ForSupportCandy\Modules\AfterHoursNotice
 */
class Core {

	/**
	 * Determines if it is currently "after hours" based on settings.
	 *
	 * @param array $settings {
	 *     An array of settings for the feature.
	 *
	 *     @type int    $start_hour       The hour when after-hours starts (e.g., 17 for 5 PM).
	 *     @type int    $end_hour         The hour when business hours resume (e.g., 8 for 8 AM).
	 *     @type bool   $include_weekends Whether to consider all day Saturday and Sunday as after hours.
	 *     @type array  $holidays         An array of holiday dates in 'Y-m-d' format.
	 *     @type bool   $use_sc_hours     Whether to use SupportCandy's Working Hours & Exceptions.
	 *     @type bool   $use_sc_holidays  Whether to use SupportCandy's Holiday Schedule.
	 * }
	 * @param int|null $current_timestamp The timestamp to check. Defaults to the current time.
	 * @param string   $timezone_string   The timezone string (e.g., 'America/New_York').
	 *
	 * @return bool True if it is after hours, false otherwise.
	 */
	public function is_after_hours( array $settings, ?int $current_timestamp = null, string $timezone_string = 'UTC' ): bool {
		$use_sc_hours    = ! empty( $settings['use_sc_hours'] );
		$use_sc_holidays = ! empty( $settings['use_sc_holidays'] );

		$current_timestamp = $current_timestamp ?? time();
		$timezone          = new DateTimeZone( $timezone_string );
		$now               = new DateTime( 'now', $timezone );
		$now->setTimestamp( $current_timestamp );

		// -------------------------------------------------------------------------
		// 1. SupportCandy Exceptions Check (Priority 1 - Matches SC logic)
		// -------------------------------------------------------------------------
		if ( $use_sc_hours && class_exists( 'WPSC_Wh_Exception' ) ) {
			try {
				// SC methods expect a DateTime object, often using the WP timezone.
				// We pass our $now object which is already in the correct timezone.
				$exception = \WPSC_Wh_Exception::get_exception_by_date( $now );
				if ( $exception ) {
					// Exception found! This overrides everything else.
					// Check if we are outside the exception's defined hours.

					// Parse start/end times (e.g., "09:00:00")
					$start_parts = explode( ':', $exception->start_time );
					$end_parts   = explode( ':', $exception->end_time );

					$ex_start_hour = (int) $start_parts[0];
					$ex_start_min  = (int) $start_parts[1];

					$ex_end_hour = (int) $end_parts[0];
					$ex_end_min  = (int) $end_parts[1];

					$current_hour = (int) $now->format( 'G' );
					$current_min  = (int) $now->format( 'i' );

					// Convert to minutes for easier comparison
					$current_minutes = ( $current_hour * 60 ) + $current_min;
					$start_minutes   = ( $ex_start_hour * 60 ) + $ex_start_min;
					$end_minutes     = ( $ex_end_hour * 60 ) + $ex_end_min;

					// If current time is BEFORE start OR AFTER end, it is "After Hours" (Closed).
					// Otherwise, we are Open.
					if ( $current_minutes < $start_minutes || $current_minutes >= $end_minutes ) {
						return true; // Closed
					} else {
						return false; // Open
					}
				}
			} catch ( \Throwable $e ) {
				// Log error if needed, or fail gracefully to manual settings
                if ( function_exists( 'stackboost_log' ) ) {
                    stackboost_log( 'AfterHoursNotice: Error checking SC Exception: ' . $e->getMessage() );
                }
			}
		}

		// -------------------------------------------------------------------------
		// 2. Holidays Check (Priority 2)
		// -------------------------------------------------------------------------
		if ( $use_sc_holidays && class_exists( 'WPSC_Holiday' ) ) {
			try {
				// SC Holidays check
				$is_holiday = \WPSC_Holiday::get_holiday_by_date( $now );
				if ( $is_holiday ) {
					return true; // It's a holiday, we are closed.
				}
			} catch ( \Throwable $e ) {
                if ( function_exists( 'stackboost_log' ) ) {
                    stackboost_log( 'AfterHoursNotice: Error checking SC Holiday: ' . $e->getMessage() );
                }
			}
		} else {
			// Manual Holidays Check
			$manual_holidays = $settings['holidays'] ?? [];
			$today_formatted = $now->format( 'Y-m-d' );
			if ( in_array( $today_formatted, $manual_holidays, true ) ) {
				return true;
			}
		}

		// -------------------------------------------------------------------------
		// 3. Standard Schedule Check (Priority 3)
		// -------------------------------------------------------------------------
		if ( $use_sc_hours && class_exists( 'WPSC_Working_Hour' ) ) {
			try {
				// Get working hours for the company (agent_id = 0)
				$working_hrs = \WPSC_Working_Hour::get( 0 );
				$day_num     = (int) $now->format( 'N' ); // 1 (Mon) - 7 (Sun)

				if ( isset( $working_hrs[ $day_num ] ) ) {
					$wh = $working_hrs[ $day_num ];

					// If start_time is 'off', we are closed all day.
					if ( $wh->start_time === 'off' ) {
						return true;
					}

					// Check time bounds
					$start_parts = explode( ':', $wh->start_time );
					$end_parts   = explode( ':', $wh->end_time );

					$wh_start_hour = (int) $start_parts[0];
					$wh_start_min  = (int) $start_parts[1];

					$wh_end_hour = (int) $end_parts[0];
					$wh_end_min  = (int) $end_parts[1];

					$current_hour = (int) $now->format( 'G' );
					$current_min  = (int) $now->format( 'i' );

					$current_minutes = ( $current_hour * 60 ) + $current_min;
					$start_minutes   = ( $wh_start_hour * 60 ) + $wh_start_min;
					$end_minutes     = ( $wh_end_hour * 60 ) + $wh_end_min;

					if ( $current_minutes < $start_minutes || $current_minutes >= $end_minutes ) {
						return true; // Closed
					}

					return false; // Open
				}
			} catch ( \Throwable $e ) {
                if ( function_exists( 'stackboost_log' ) ) {
                    stackboost_log( 'AfterHoursNotice: Error checking SC Working Hours: ' . $e->getMessage() );
                }
			}
		}

		// Manual Standard Schedule Fallback (only runs if SC hours NOT used OR SC failed)
        // If SC hours ARE enabled but we didn't return above (e.g. class missing), we fall through here.
        // However, the requirement is "If the SC options are unchecked... uses the manual settings".
        // If checked, we should probably NOT fall back to manual unless it failed?
        // For safety, let's only run manual logic if use_sc_hours is FALSE.

        if ( ! $use_sc_hours ) {
            $start_hour       = $settings['start_hour'] ?? 17;
            $end_hour         = $settings['end_hour'] ?? 8;
            $include_weekends = $settings['include_weekends'] ?? false;

            // Check if it's a weekend.
            $day_of_week = (int) $now->format( 'N' );
            if ( $include_weekends && ( $day_of_week === 6 || $day_of_week === 7 ) ) {
                return true;
            }

            // Check if the current time is within the after-hours window.
            $current_hour = (int) $now->format( 'G' );

            if ( $start_hour > $end_hour ) {
                // Overnight (e.g., 17:00 to 08:00)
                if ( $current_hour >= $start_hour || $current_hour < $end_hour ) {
                    return true;
                }
            } else {
                // Same day (e.g., 00:00 to 08:00)
                if ( $current_hour >= $start_hour && $current_hour < $end_hour ) {
                    return true;
                }
            }
        }

		return false;
	}

	/**
	 * Parses a string of holidays into a clean array.
	 *
	 * @param string $holidays_string A string with one holiday per line (MM-DD-YYYY or YYYY-MM-DD).
	 * @return array An array of holiday dates in 'Y-m-d' format.
	 */
	public function parse_holidays( string $holidays_string ): array {
		if ( empty( $holidays_string ) ) {
			return [];
		}

		$holidays = [];
		// Normalize line endings and split into an array.
		$lines = preg_split( '/\r\n|\r|\n/', $holidays_string );

		foreach ( $lines as $line ) {
			$trimmed_line = trim( $line );
			if ( empty( $trimmed_line ) ) {
				continue;
			}

			// Attempt to create a DateTime object to validate and standardize the format.
			// This is flexible and handles various formats like m-d-Y, Y-m-d, etc.
			$date = DateTime::createFromFormat( 'm-d-Y', $trimmed_line );
			if ( ! $date ) {
				$date = DateTime::createFromFormat( 'Y-m-d', $trimmed_line );
			}

			if ( $date ) {
				// Standardize the format for reliable comparison.
				$holidays[] = $date->format( 'Y-m-d' );
			}
		}
		return $holidays;
	}
}