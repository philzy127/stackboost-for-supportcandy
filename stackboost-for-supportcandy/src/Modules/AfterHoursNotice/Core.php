<?php

namespace StackBoost\ForSupportCandy\Modules\AfterHoursNotice;

use DateTime;
use DateTimeZone;
use Throwable;

/**
 * Core business logic for the After Hours Notice feature.
 *
 * This class is responsible for determining if a given timestamp falls
 * within the configured "after hours" period, including weekends and holidays.
 * It has no dependencies on WordPress functions.
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
	 *     @type string|int $start_hour       The time when after-hours starts (e.g., '17:00' or 17).
	 *     @type string|int $end_hour         The time when business hours resume (e.g., '08:00' or 8).
	 *     @type bool       $include_weekends Whether to consider all day Saturday and Sunday as after hours.
	 *     @type array      $holidays         An array of holiday dates in 'Y-m-d' format.
	 * }
	 * @param int|null $current_timestamp The timestamp to check. Defaults to the current time.
	 * @param string   $timezone_string   The timezone string (e.g., 'America/New_York').
	 *
	 * @return bool True if it is after hours, false otherwise.
	 */
	public function is_after_hours( array $settings, ?int $current_timestamp = null, string $timezone_string = 'UTC' ): bool {
        // Debug Logging
        if ( function_exists( 'stackboost_log' ) ) {
            stackboost_log(
                sprintf(
                    'Checking After Hours. Start: %s, End: %s, TZ: %s',
                    $settings['start_hour'] ?? 'unset',
                    $settings['end_hour'] ?? 'unset',
                    $timezone_string
                ),
                'after_hours_debug'
            );
        }

        // Normalize start/end times.
        // Handles legacy integer (e.g., 17) by appending ':00', or uses string directly.
        $start_input = $settings['start_hour'] ?? '17:00';
        $end_input   = $settings['end_hour'] ?? '08:00';

        if ( is_numeric( $start_input ) ) {
            $start_input = sprintf( '%02d:00', (int) $start_input );
        }
        if ( is_numeric( $end_input ) ) {
            $end_input = sprintf( '%02d:00', (int) $end_input );
        }

		$include_weekends = $settings['include_weekends'] ?? false;
		$holidays         = $settings['holidays'] ?? [];

		$current_timestamp = $current_timestamp ?? time();

        try {
            $timezone = new DateTimeZone( $timezone_string );
        } catch ( Throwable $e ) {
            // Fallback to UTC if timezone is invalid
            $timezone = new DateTimeZone( 'UTC' );
            if ( function_exists( 'stackboost_log' ) ) {
                stackboost_log( 'Invalid timezone provided to is_after_hours: ' . $timezone_string, 'after_hours_error' );
            }
        }

		$now = new DateTime( 'now', $timezone );
		$now->setTimestamp( $current_timestamp );

		// Check if today is a holiday.
		$today_formatted = $now->format( 'Y-m-d' );
		if ( in_array( $today_formatted, $holidays, true ) ) {
            if ( function_exists( 'stackboost_log' ) ) stackboost_log( 'After Hours: Today is a holiday.', 'after_hours_debug' );
			return true;
		}

		// Check if it's a weekend.
		$day_of_week = (int) $now->format( 'N' ); // 1 (for Monday) through 7 (for Sunday)
		if ( $include_weekends && ( $day_of_week === 6 || $day_of_week === 7 ) ) {
            if ( function_exists( 'stackboost_log' ) ) stackboost_log( 'After Hours: It is a weekend.', 'after_hours_debug' );
			return true;
		}

        try {
            // Create DateTime objects for start and end times relative to "Today" in the target timezone.
            // Using the constructor directly allows robust parsing of various formats ("17:00", "05:00 PM", "5pm").
            // We use 'now' as base to get the date part, and overwrite the time part with the setting.
            // This normalizes everything to the current date for time-of-day comparison.

            $start_dt = new DateTime( $start_input, $timezone );
            $end_dt   = new DateTime( $end_input, $timezone );

            // Explicitly set the date to "today" to ensure we are comparing times on the same reference day.
            // This prevents issues if the time string caused date rollovers (unlikely with simple times, but safe).
            $start_dt->setDate( (int)$now->format('Y'), (int)$now->format('m'), (int)$now->format('d') );
            $end_dt->setDate( (int)$now->format('Y'), (int)$now->format('m'), (int)$now->format('d') );

        } catch ( Throwable $e ) {
            if ( function_exists( 'stackboost_log' ) ) {
                stackboost_log( 'Error parsing start/end times: ' . $e->getMessage(), 'after_hours_error' );
            }
            return false;
        }

        // Determine if the range is overnight (e.g., Start 17:00, End 08:00).
        $start_ts_today = $start_dt->getTimestamp();
        $end_ts_today   = $end_dt->getTimestamp();
        $now_ts         = $now->getTimestamp();

        if ( $start_ts_today > $end_ts_today ) {
            // Overnight Logic (e.g. 5pm to 8am)
            // It is after hours if time is AFTER 5pm OR BEFORE 8am.
            if ( $now_ts >= $start_ts_today || $now_ts < $end_ts_today ) {
                if ( function_exists( 'stackboost_log' ) ) stackboost_log( 'After Hours: Within overnight window.', 'after_hours_debug' );
                return true;
            }
        } else {
            // Same Day Logic (e.g. 12pm to 1pm)
            // It is after hours if time is AFTER 12pm AND BEFORE 1pm.
            if ( $now_ts >= $start_ts_today && $now_ts < $end_ts_today ) {
                if ( function_exists( 'stackboost_log' ) ) stackboost_log( 'After Hours: Within same-day window.', 'after_hours_debug' );
                return true;
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
