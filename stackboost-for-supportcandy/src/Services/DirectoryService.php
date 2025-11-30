<?php
/**
 * Directory Service
 *
 * This service provides a centralized API for interacting with the
 * Company Directory data. It abstracts the underlying data source
 * (whether it's CPTs, a separate table, or an external API) and
 * provides a stable, consistent interface for other modules.
 *
 * @package StackBoost
 * @subpackage Services
 */

namespace StackBoost\ForSupportCandy\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * DirectoryService Class
 */
class DirectoryService {

	/**
	 * The single instance of the class.
	 *
	 * @var DirectoryService|null
	 */
	private static $instance = null;

	/**
	 * The post type for staff members.
	 *
	 * @var string
	 */
	private $staff_post_type = 'sb_staff_dir';

	/**
	 * Get the single instance of the class.
	 *
	 * @return DirectoryService
	 */
	public static function get_instance(): DirectoryService {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor to prevent direct instantiation.
	 */
	private function __construct() {
		// This is a singleton.
	}

	/**
	 * Find an employee profile by WordPress User ID or email address.
	 *
	 * This method searches the directory for a staff member linked to the
	 * given WP_User ID or who has a matching email address.
	 *
	 * @param int|string $user_id_or_email The WP_User ID or email address.
	 *
	 * @return int|null The staff profile post ID if found, otherwise null.
	 */
	public function find_employee_profile( $user_id_or_email ): ?int {
		$query_args = array(
			'post_type'      => $this->staff_post_type,
			'posts_per_page' => 1,
			'post_status'    => 'publish',
			'meta_query'     => array(
				'relation' => 'OR',
			),
		);

		if ( is_numeric( $user_id_or_email ) ) {
			$query_args['meta_query'][] = array(
				'key'     => '_user_id',
				'value'   => (int) $user_id_or_email,
				'compare' => '=',
			);
		} elseif ( is_email( $user_id_or_email ) ) {
			$query_args['meta_query'][] = array(
				'key'     => '_email_address',
				'value'   => sanitize_email( $user_id_or_email ),
				'compare' => '=',
			);
		} else {
			return null; // Invalid input.
		}

		$employee_query = new \WP_Query( $query_args );

		if ( $employee_query->have_posts() ) {
			return $employee_query->posts[0]->ID;
		}

		return null;
	}

	/**
	 * Retrieve a structured object of key employee details.
	 *
	 * @param int $profile_id The unique identifier for the directory profile (post ID).
	 *
	 * @return \stdClass|null A structured object with employee data or null if not found.
	 */
	public function retrieve_employee_data( int $profile_id ): ?\stdClass {
		$post = get_post( $profile_id );

		if ( ! $post || $post->post_type !== $this->staff_post_type ) {
			return null;
		}

		$employee_data                      = new \stdClass();
		$employee_data->id                  = $profile_id;
		$employee_data->name                = get_the_title( $profile_id );
		$employee_data->permalink           = get_permalink( $profile_id );
		$employee_data->edit_post_link      = get_edit_post_link( $profile_id );
		$employee_data->thumbnail_url       = get_the_post_thumbnail_url( $profile_id, 'medium' );
		$employee_data->full_photo_url      = get_the_post_thumbnail_url( $profile_id, 'full' );
		$employee_data->email               = get_post_meta( $profile_id, '_email_address', true );
		$employee_data->job_title           = get_post_meta( $profile_id, '_chp_staff_job_title', true );
		$employee_data->department_program  = get_post_meta( $profile_id, '_department_program', true );
		$employee_data->office_phone        = get_post_meta( $profile_id, '_office_phone', true );
		$employee_data->extension           = get_post_meta( $profile_id, '_extension', true );
		$employee_data->mobile_phone        = get_post_meta( $profile_id, '_mobile_phone', true );
		$employee_data->location_id         = get_post_meta( $profile_id, '_location_id', true );
		$employee_data->location_name       = ! empty( $employee_data->location_id ) ? get_the_title( $employee_data->location_id ) : '';
		$employee_data->room_number         = get_post_meta( $profile_id, '_room_number', true );
		$employee_data->active_status       = get_post_meta( $profile_id, '_active', true );
		$employee_data->active_as_of_date   = get_post_meta( $profile_id, '_active_as_of_date', true );
		$employee_data->planned_exit_date   = get_post_meta( $profile_id, '_planned_exit_date', true );
		$employee_data->last_updated_on     = get_post_meta( $profile_id, '_last_updated_on', true );

		$employee_data->location_details = array();
		if ( ! empty( $employee_data->location_id ) ) {
			$location_post = get_post( $employee_data->location_id );
			if ( $location_post ) {
				$employee_data->location_details['address_line1'] = get_post_meta( $employee_data->location_id, '_address_line1', true );
				$employee_data->location_details['city'] = get_post_meta( $employee_data->location_id, '_city', true );
				$employee_data->location_details['state'] = get_post_meta( $employee_data->location_id, '_state', true );
				$employee_data->location_details['zip'] = get_post_meta( $employee_data->location_id, '_zip', true );
				$employee_data->location_details['phone_number'] = get_post_meta( $employee_data->location_id, '_location_phone_number', true );
			}
		}

		return $employee_data;
	}

	/**
	 * Get all active employees with all necessary data for the directory shortcode.
	 *
	 * This method is optimized to retrieve all data required for the shortcode
	 * in a single, efficient query, avoiding N+1 problems.
	 *
	 * @return array An array of \stdClass objects, each representing an employee.
	 */
	public function get_all_active_employees_for_shortcode(): array {
		$employees = array();

		$query_args = array(
			'post_type'      => $this->staff_post_type,
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => '_active',
					'value'   => 'Yes',
					'compare' => '=',
				),
				array(
					'relation' => 'OR',
					array(
						'key'     => '_private',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => '_private',
						'value'   => 'Yes',
						'compare' => '!=',
					),
				),
			),
			'orderby'        => 'title',
			'order'          => 'ASC',
		);

		$query = new \WP_Query( $query_args );

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$post_id     = get_the_ID();
				$employees[] = $this->retrieve_employee_data( $post_id );
			}
		}
		wp_reset_postdata();

		return $employees;
	}

	/**
	 * Get a staff member's complete data by their email address.
	 *
	 * This is a convenience method that combines finding and retrieving.
	 *
	 * @param string $email The email address to search for.
	 *
	 * @return \stdClass|null A structured object with employee data or null if not found.
	 */
	public function get_staff_by_email( string $email ): ?\stdClass {
		$profile_id = $this->find_employee_profile( $email );

		if ( ! $profile_id ) {
			return null;
		}

		return $this->retrieve_employee_data( $profile_id );
	}

	/**
	 * Get a formatted HTML string of the employee's phone numbers.
	 *
	 * @param \stdClass $employee The employee data object from retrieve_employee_data.
	 * @return string The formatted HTML string.
	 */
	public function get_formatted_phone_numbers_html( \stdClass $employee ): string {
		$html_lines = [];

		// Office Phone
		if ( ! empty( $employee->office_phone ) ) {
			$formatted_office_phone = $this->_format_phone_number_string( $employee->office_phone );
			$office_line            = '<i class="material-icons" style="font-size: 16px; vertical-align: middle; margin-right: 5px; color: #555;" title="' . esc_attr__( 'Office', 'stackboost-for-supportcandy' ) . '">building</i>' . $formatted_office_phone;
			if ( ! empty( $employee->extension ) ) {
				$office_line .= ' <span style="color: #777; font-size: 0.9em;">' . esc_html__( 'ext.', 'stackboost-for-supportcandy' ) . ' ' . esc_html( $employee->extension ) . '</span>';
			}
			$html_lines[] = $office_line;
		}

		// Mobile Phone
		if ( ! empty( $employee->mobile_phone ) ) {
			$formatted_mobile_phone = $this->_format_phone_number_string( $employee->mobile_phone );
			$html_lines[]           = '<i class="material-icons" style="font-size: 16px; vertical-align: middle; margin-right: 5px; color: #555;" title="' . esc_attr__( 'Mobile', 'stackboost-for-supportcandy' ) . '">smartphone</i>' . $formatted_mobile_phone;
		}

		return implode( '<br>', $html_lines );
	}

	/**
	 * Formats a raw phone number string into (xxx) xxx-xxxx.
	 *
	 * @param string $number Raw phone number.
	 * @return string Formatted phone number.
	 */
	private function _format_phone_number_string( string $number ): string {
		$number = preg_replace( '/[^0-9]/', '', $number );
		if ( strlen( $number ) === 10 ) {
			return '(' . substr( $number, 0, 3 ) . ') ' . substr( $number, 3, 3 ) . '-' . substr( $number, 6 );
		}
		return trim( $number );
	}
}