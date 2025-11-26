<?php

namespace StackBoost\ForSupportCandy\Modules\AfterHoursNotice;

use DateTime;
use DateTimeZone;

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
	 *     @type int    $start_hour       The hour when after-hours starts (e.g., 17 for 5 PM).
	 *     @type int    $end_hour         The hour when business hours resume (e.g., 8 for 8 AM).
	 *     @type bool   $include_weekends Whether to consider all day Saturday and Sunday as after hours.
	 *     @type array  $holidays         An array of holiday dates in 'Y-m-d' format.
	 * }
	 * @param int|null $current_timestamp The timestamp to check. Defaults to the current time.
	 * @param string   $timezone_string   The timezone string (e.g., 'America/New_York').
	 *
	 * @return bool True if it is after hours, false otherwise.
	 */
	public function is_after_hours( array $settings, ?int $current_timestamp = null, string $timezone_string = 'UTC' ): bool {
		$start_hour       = $settings['start_hour'] ?? 17;
		$end_hour         = $settings['end_hour'] ?? 8;
		$include_weekends = $settings['include_weekends'] ?? false;
		$holidays         = $settings['holidays'] ?? [];

		$current_timestamp = $current_timestamp ?? time();
		$timezone          = new DateTimeZone( $timezone_string );
		$now               = new DateTime( 'now', $timezone );
		$now->setTimestamp( $current_timestamp );
		stackboost_log( "AfterHoursNotice Core: Checking timestamp {$current_timestamp} in timezone {$timezone_string}.", 'after-hours' );

		// Check if today is a holiday.
		$today_formatted = $now->format( 'Y-m-d' );
		stackboost_log( "AfterHoursNotice Core: Comparing current date '{$today_formatted}' against holidays: " . print_r( $holidays, true ), 'after-hours' );
		if ( in_array( $today_formatted, $holidays, true ) ) {
			stackboost_log( 'AfterHoursNotice Core: Holiday match found. Returning true.', 'after-hours' );
			return true;
		}
		stackboost_log( 'AfterHoursNotice Core: No holiday match.', 'after-hours' );

		// Check if it's a weekend.
		$day_of_week = (int) $now->format( 'N' ); // 1 (for Monday) through 7 (for Sunday)
		if ( $include_weekends && ( $day_of_week === 6 || $day_of_week === 7 ) ) {
			return true;
		}

		// Check if the current time is within the after-hours window.
		$current_hour = (int) $now->format( 'G' ); // 24-hour format of an hour without leading zeros

		// This handles overnight periods (e.g., 17:00 to 08:00)
		if ( $start_hour > $end_hour ) {
			if ( $current_hour >= $start_hour || $current_hour < $end_hour ) {
				return true;
			}
		} else { // This handles same-day periods (e.g., 00:00 to 08:00)
			if ( $current_hour >= $start_hour && $current_hour < $end_hour ) {
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
		stackboost_log( "AfterHoursNotice Core: Parsing holidays string: '{$holidays_string}'", 'after-hours' );
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