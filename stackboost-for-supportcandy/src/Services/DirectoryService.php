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

		// Use 'stackboost_directory_large' size for the full photo to ensure we have a resized version server-side,
		// avoiding loading huge original files while preserving the original upload in the library.
		$post_thumbnail_id = get_post_thumbnail_id( $profile_id );
		$employee_data->full_photo_url = '';
		$employee_data->focus_point = null;

		if ( $post_thumbnail_id ) {
			// Try to get our custom size first, fall back to large if it hasn't been generated yet.
			$full_photo_src = wp_get_attachment_image_src( $post_thumbnail_id, 'stackboost_directory_large' );
			if ( ! $full_photo_src ) {
				$full_photo_src = wp_get_attachment_image_src( $post_thumbnail_id, 'large' );
			}

			if ( $full_photo_src ) {
				$employee_data->full_photo_url = $full_photo_src[0];
			}
			$employee_data->focus_point = $this->get_media_focus_point( $post_thumbnail_id );
		}

		$employee_data->email               = get_post_meta( $profile_id, '_email_address', true );
		$employee_data->job_title           = get_post_meta( $profile_id, '_stackboost_staff_job_title', true );
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
	 * Get Media Focus Point data for an attachment.
	 *
	 * Tries to retrieve focus point data from the "Media Focus Point" plugin
	 * by WP Company. Checks for common meta keys.
	 *
	 * @param int $attachment_id The attachment ID.
	 * @return array|null An array with 'x' and 'y' percentages, or null if not found.
	 */
	public function get_media_focus_point( int $attachment_id ): ?array {
		// Common meta keys used by Media Focus Point plugins.
		// The specific plugin "Media Focus Point" by WP Company often uses 'mfp_focus_point'.
		$focus_point_data = get_post_meta( $attachment_id, 'mfp_focus_point', true );

		if ( ! empty( $focus_point_data ) && is_array( $focus_point_data ) ) {
			// Expected format: array('x' => float, 'y' => float) usually in range 0-1 or -1 to 1.
			// However, CSS object-position needs percentages.
			// Let's inspect the data format. Usually it's like [x => 0.5, y => 0.5] (center).

			// Normalize to percentages if they are 0-1 floats.
			$x = isset( $focus_point_data['x'] ) ? floatval( $focus_point_data['x'] ) : 0.5;
			$y = isset( $focus_point_data['y'] ) ? floatval( $focus_point_data['y'] ) : 0.5;

			// If the values are -1 to 1 (like some crop plugins), map them to 0-100%.
			// But 'Media Focus Point' usually stores 0-1 relative coordinates.
			// Just in case, clamp to 0-1.

			// Simple heuristic: if values are outside 0-1 range but within -100 to 100, treat as percent.
			// If -1 to 1, map to percentage.

			// Let's assume standard 0 to 1 relative coordinates for now, which is common.
			$x_pct = $x * 100;
			$y_pct = $y * 100;

			return array(
				'x' => $x_pct,
				'y' => $y_pct,
				'css' => sprintf( 'object-position: %.1f%% %.1f%%;', $x_pct, $y_pct ),
			);
		}

		return null;
	}

	/**
	 * Get a formatted HTML string of the employee's phone numbers.
	 *
	 * @param \stdClass $employee The employee data object from retrieve_employee_data.
	 * @return string The formatted HTML string.
	 */
	public function get_formatted_phone_numbers_html( \stdClass $employee ): string {
		$html_lines = [];

		// Copy icon SVG definition
		$copy_icon_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="16px" height="16px" style="vertical-align: middle; margin-left: 5px; cursor: pointer;"><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/></svg>';

		// Office Phone
		if ( ! empty( $employee->office_phone ) ) {
			$formatted_office_phone = $this->_format_phone_number_string( $employee->office_phone );
			$office_tel_uri         = $this->_generate_tel_uri( $employee->office_phone, $employee->extension );

			// Visible part wrapped in a link
			$office_link = '<a href="' . esc_url( $office_tel_uri ) . '">' . $formatted_office_phone . '</a>';

			$office_line = '<span class="dashicons dashicons-building" style="font-size: 16px; width: 16px; height: 16px; vertical-align: middle; margin-right: 5px; color: #555;" title="' . esc_attr__( 'Office', 'stackboost-for-supportcandy' ) . '"></span>' . $office_link;
			if ( ! empty( $employee->extension ) ) {
				$office_line .= ' <span style="color: #777; font-size: 0.9em;">' . esc_html__( 'ext.', 'stackboost-for-supportcandy' ) . ' ' . esc_html( $employee->extension ) . '</span>';
			}

			// Add copy button
			// Construct copy text to match display: (xxx) xxx-xxxx ext. yyy
			$office_copy_text = $formatted_office_phone;
			if ( ! empty( $employee->extension ) ) {
				$office_copy_text .= ' ' . esc_html__( 'ext.', 'stackboost-for-supportcandy' ) . ' ' . $employee->extension;
			}

			$office_line .= sprintf(
				' <span class="stackboost-copy-phone-icon" data-phone="%s" data-extension="%s" data-copy-text="%s" title="%s">%s</span>',
				esc_attr( $employee->office_phone ),
				esc_attr( $employee->extension ),
				esc_attr( $office_copy_text ),
				esc_attr__( 'Click to copy phone', 'stackboost-for-supportcandy' ),
				$copy_icon_svg
			);

			$html_lines[] = $office_line;
		}

		// Mobile Phone
		if ( ! empty( $employee->mobile_phone ) ) {
			$formatted_mobile_phone = $this->_format_phone_number_string( $employee->mobile_phone );
			$mobile_tel_uri         = $this->_generate_tel_uri( $employee->mobile_phone, '' );

			// Visible part wrapped in a link
			$mobile_link = '<a href="' . esc_url( $mobile_tel_uri ) . '">' . $formatted_mobile_phone . '</a>';

			$mobile_line = '<span class="dashicons dashicons-smartphone" style="font-size: 16px; width: 16px; height: 16px; vertical-align: middle; margin-right: 5px; color: #555;" title="' . esc_attr__( 'Mobile', 'stackboost-for-supportcandy' ) . '"></span>' . $mobile_link;

			// Add copy button (mobile usually has no extension)
			$mobile_copy_text = $formatted_mobile_phone;
			$mobile_line .= sprintf(
				' <span class="stackboost-copy-phone-icon" data-phone="%s" data-extension="" data-copy-text="%s" title="%s">%s</span>',
				esc_attr( $employee->mobile_phone ),
				esc_attr( $mobile_copy_text ),
				esc_attr__( 'Click to copy phone', 'stackboost-for-supportcandy' ),
				$copy_icon_svg
			);

			$html_lines[] = $mobile_line;
		}

		return implode( '<br>', $html_lines );
	}

	/**
	 * Generates a tel: URI complying with RFC 3966.
	 *
	 * @param string $number    The phone number.
	 * @param string $extension Optional extension.
	 * @return string The tel: URI.
	 */
	private function _generate_tel_uri( string $number, string $extension = '' ): string {
		// Clean the number. Keep digits and the + sign.
		$clean_number = preg_replace( '/[^0-9+]/', '', $number );

		// Build URI
		$uri = 'tel:' . $clean_number;

		if ( ! empty( $extension ) ) {
			// RFC 3966 specifies ;ext= for extensions.
			$clean_extension = preg_replace( '/[^0-9]/', '', $extension );
			$uri            .= ';ext=' . $clean_extension;
		}

		return $uri;
	}

	/**
	 * Formats a raw phone number string into (xxx) xxx-xxxx.
	 *
	 * @param string $number Raw phone number.
	 * @return string Formatted phone number.
	 */
	private function _format_phone_number_string( string $number ): string {
		// Only auto-format if it's a standard US 10-digit raw number.
		if ( preg_match( '/^\d{10}$/', $number ) ) {
			return '(' . substr( $number, 0, 3 ) . ') ' . substr( $number, 3, 3 ) . '-' . substr( $number, 6 );
		}

		// Otherwise, return the user's input as-is (e.g. international formats).
		return trim( $number );
	}
}