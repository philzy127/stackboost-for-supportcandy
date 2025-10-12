<?php
/**
 * StackBoost Meta Boxes for Company Directory.
 *
 * This file handles the creation and saving of custom meta boxes
 * for the Staff, Location, and Department CPTs within the Company
 * Directory module. It's a migration of the meta box logic from the
 * standalone plugin, adapted for the StackBoost framework.
 *
 * @package StackBoost
 * @subpackage Modules\Directory\Data
 */

namespace StackBoost\ForSupportCandy\Modules\Directory\Data;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * MetaBoxes Class
 *
 * Handles the creation and saving of custom meta boxes.
 */
class MetaBoxes {

	/**
	 * The custom post type slug for staff entries.
	 *
	 * @var string
	 */
	private $post_type;

	/**
	 * The custom post type slug for locations.
	 *
	 * @var string
	 */
	private $location_post_type;

	/**
	 * The custom post type slug for departments.
	 *
	 * @var string
	 */
	private $department_post_type;

	/**
	 * Constructor.
	 *
	 * @param CustomPostTypes $cpts An instance of the CustomPostTypes class.
	 */
	public function __construct( CustomPostTypes $cpts ) {
		$this->post_type            = $cpts->post_type;
		$this->location_post_type   = $cpts->location_post_type;
		$this->department_post_type = $cpts->department_post_type;

		// Add meta boxes for staff directory.
		add_action( 'add_meta_boxes', array( $this, 'add_directory_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_directory_meta_box_data' ) );

		// Add meta boxes for locations.
		add_action( 'add_meta_boxes', array( $this, 'add_location_details_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_location_details_meta_box_data' ) );
	}

	/**
	 * Add the custom meta box for Staff Directory.
	 */
	public function add_directory_meta_box() {
		add_meta_box(
			'stackboost_staff_directory_details',
			__( 'Staff Details', 'stackboost-for-supportcandy' ),
			array( $this, 'render_directory_meta_box' ),
			$this->post_type,
			'normal',
			'high'
		);
	}

	/**
	 * Render the custom meta box content for Staff Directory.
	 *
	 * @param \WP_Post $post The current post object.
	 */
	public function render_directory_meta_box( $post ) {
		wp_nonce_field( 'stackboost_staff_directory_meta_box', 'stackboost_staff_directory_meta_box_nonce' );

		$current_screen    = get_current_screen();
		$is_add_new_screen = ( $current_screen && 'add' === $current_screen->action );

		$fields = array(
			'office_phone'        => 'Office Phone',
			'extension'           => 'Extension',
			'mobile_phone'        => 'Mobile Phone',
			'location'            => 'Location',
			'room_number'         => 'Room #',
			'department_program'  => 'Department / Program',
			'chp_staff_job_title' => 'Title',
			'email_address'       => 'Email Address',
			'active'              => 'Active',
			'active_as_of_date'   => 'Active as of:',
			'planned_exit_date'   => 'Inactive as of:',
		);

		if ( ! $is_add_new_screen ) {
			$fields['unique_id']       = 'Unique ID';
			$fields['last_updated_by'] = 'Last Updated By';
			$fields['last_updated_on'] = 'Last Updated On';
		}

		echo '<table class="form-table">';
		foreach ( $fields as $key => $label ) {
			if ( 'active' === $key && $is_add_new_screen ) {
				continue;
			}

			$value = get_post_meta( $post->ID, '_' . $key, true );
			echo '<tr>';
			echo '<th scope="row"><label for="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th>';
			echo '<td>';
			if ( 'location' === $key ) {
				$locations           = get_posts(
					array(
						'post_type'      => $this->location_post_type,
						'posts_per_page' => -1,
						'orderby'        => 'title',
						'order'          => 'ASC',
						'post_status'    => 'publish',
					)
				);
				$current_location_id = get_post_meta( $post->ID, '_location_id', true );
				echo '<select name="' . esc_attr( $key ) . '" id="' . esc_attr( $key ) . '" class="postbox-select-location">';
				echo '<option value="">' . esc_html__( 'Select a Location', 'stackboost-for-supportcandy' ) . '</option>';
				foreach ( $locations as $location_post ) {
					echo '<option value="' . esc_attr( $location_post->ID ) . '" ' . selected( $current_location_id, $location_post->ID, false ) . '>' . esc_html( $location_post->post_title ) . '</option>';
				}
				echo '</select>';
				echo '<input type="hidden" id="stackboost_staff_directory_location_name_hidden" name="stackboost_staff_directory_location_name_hidden" value="' . esc_attr( get_post_meta( $post->ID, '_location', true ) ) . '">';
				echo '<p class="description">' . esc_html__( 'Select a physical location for this staff member.', 'stackboost-for-supportcandy' ) . '</p>';
			} elseif ( 'department_program' === $key ) {
				$departments = get_posts(
					array(
						'post_type'      => $this->department_post_type,
						'posts_per_page' => -1,
						'orderby'        => 'title',
						'order'          => 'ASC',
						'post_status'    => 'publish',
					)
				);
				echo '<select name="' . esc_attr( $key ) . '" id="' . esc_attr( $key ) . '" class="postbox-select-department">';
				echo '<option value="">' . esc_html__( 'Select a Department/Program', 'stackboost-for-supportcandy' ) . '</option>';
				foreach ( $departments as $department_post ) {
					echo '<option value="' . esc_attr( $department_post->post_title ) . '" ' . selected( $value, $department_post->post_title, false ) . '>' . esc_html( $department_post->post_title ) . '</option>';
				}
				echo '</select>';
				echo '<p class="description">' . esc_html__( 'Select the department or program for this staff member.', 'stackboost-for-supportcandy' ) . '</p>';
			} elseif ( 'active' === $key ) {
				$checked = ( 'Yes' === $value || '1' === $value ) ? 'checked' : '';
				echo '<input type="checkbox" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="Yes" ' . $checked . ' />';
				echo '<p class="description">' . esc_html__( 'Check if this entry is active; uncheck if deprecated.', 'stackboost-for-supportcandy' ) . '</p>';
			} elseif ( 'active_as_of_date' === $key ) {
				$date_value = $value ? esc_attr( $value ) : ( $is_add_new_screen ? current_time( 'Y-m-d' ) : '' );
				echo '<input type="text" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . $date_value . '" class="regular-text stackboost-datepicker" />';
				echo '<p class="description">' . esc_html__( 'Date from which this entry is considered active.', 'stackboost-for-supportcandy' ) . '</p>';
			} elseif ( 'planned_exit_date' === $key ) {
				$date_value = $value ? esc_attr( $value ) : '';
				echo '<input type="text" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . $date_value . '" class="regular-text stackboost-datepicker" />';
				echo '<p class="description">' . esc_html__( 'Optional: Date when this entry is planned to become inactive.', 'stackboost-for-supportcandy' ) . '</p>';
			} elseif ( in_array( $key, array( 'unique_id', 'last_updated_by', 'last_updated_on' ), true ) ) {
				echo '<p id="' . esc_attr( $key ) . '">' . esc_html( $value ) . '</p>';
			} else {
				echo '<input type="text" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" class="regular-text" />';
			}
			echo '</td>';
			echo '</tr>';
		}
		echo '</table>';

		echo '<script type="text/javascript">';
		echo 'jQuery(document).ready(function($) {';
		echo '$(".stackboost-datepicker").datepicker({';
		echo 'dateFormat: "yy-mm-dd"';
		echo '});';
		echo '});';
		echo '</script>';
	}

	/**
	 * Add the custom meta box for Location Details.
	 */
	public function add_location_details_meta_box() {
		add_meta_box(
			'stackboost_location_details',
			__( 'Location Details', 'stackboost-for-supportcandy' ),
			array( $this, 'render_location_details_meta_box' ),
			$this->location_post_type,
			'normal',
			'high'
		);
	}

	/**
	 * Render the custom meta box content for Location Details.
	 *
	 * @param \WP_Post $post The current post object.
	 */
	public function render_location_details_meta_box( $post ) {
		wp_nonce_field( 'stackboost_location_details_meta_box', 'stackboost_location_details_meta_box_nonce' );

		$address_line1               = get_post_meta( $post->ID, '_address_line1', true );
		$city                        = get_post_meta( $post->ID, '_city', true );
		$state                       = get_post_meta( $post->ID, '_state', true );
		$zip                         = get_post_meta( $post->ID, '_zip', true );
		$location_phone_number       = get_post_meta( $post->ID, '_location_phone_number', true );
		$location_department_program = get_post_meta( $post->ID, '_location_department_program', true );

		$is_complete              = ! empty( $address_line1 ) && ! empty( $city ) && ! empty( $state ) && ! empty( $zip );
		$needs_completion_display = $is_complete ? '<span style="color: green; font-weight: bold;">' . esc_html__( 'No', 'stackboost-for-supportcandy' ) . '</span>' : '<span style="color: red; font-weight: bold;">' . esc_html__( 'Yes', 'stackboost-for-supportcandy' ) . '</span>';

		echo '<table class="form-table">';

		echo '<tr>';
		echo '<th scope="row"><label for="address_line1">' . esc_html__( 'Address Line 1', 'stackboost-for-supportcandy' ) . '</label></th>';
		echo '<td><input type="text" id="address_line1" name="address_line1" value="' . esc_attr( $address_line1 ) . '" class="regular-text" /></td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th scope="row"><label for="city">' . esc_html__( 'City', 'stackboost-for-supportcandy' ) . '</label></th>';
		echo '<td><input type="text" id="city" name="city" value="' . esc_attr( $city ) . '" class="regular-text" /></td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th scope="row"><label for="state">' . esc_html__( 'State', 'stackboost-for-supportcandy' ) . '</label></th>';
		echo '<td><input type="text" id="state" name="state" value="' . esc_attr( $state ) . '" class="regular-text" /></td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th scope="row"><label for="zip">' . esc_html__( 'Zip', 'stackboost-for-supportcandy' ) . '</label></th>';
		echo '<td><input type="text" id="zip" name="zip" value="' . esc_attr( $zip ) . '" class="regular-text" /></td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th scope="row"><label for="location_phone_number">' . esc_html__( 'Phone Number', 'stackboost-for-supportcandy' ) . '</label></th>';
		echo '<td><input type="text" id="location_phone_number" name="location_phone_number" value="' . esc_attr( $location_phone_number ) . '" class="regular-text" /></td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th scope="row"><label for="location_department_program">' . esc_html__( 'Department / Program (Optional)', 'stackboost-for-supportcandy' ) . '</label></th>';
		echo '<td><input type="text" id="location_department_program" name="location_department_program" value="' . esc_attr( $location_department_program ) . '" class="regular-text" /></td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Needs Completion', 'stackboost-for-supportcandy' ) . '</th>';
		echo '<td>' . $needs_completion_display . '<p class="description">' . esc_html__( 'This status is automatically determined by the completeness of the address fields.', 'stackboost-for-supportcandy' ) . '</p></td>';
		echo '</tr>';

		echo '</table>';
	}

	/**
	 * Save custom meta box data when the Staff Directory post is saved.
	 *
	 * @param int $post_id The ID of the post being saved.
	 */
	public function save_directory_meta_box_data( $post_id ) {
		if ( ! isset( $_POST['stackboost_staff_directory_meta_box_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_POST['stackboost_staff_directory_meta_box_nonce'], 'stackboost_staff_directory_meta_box' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( $this->post_type === get_post_type( $post_id ) && ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$is_new_post = empty( get_post_meta( $post_id, '_unique_id', true ) );

		$unique_id = get_post_meta( $post_id, '_unique_id', true );
		if ( empty( $unique_id ) ) {
			global $wpdb;
			$max_id        = $wpdb->get_var( "SELECT MAX(CAST(meta_value AS UNSIGNED)) FROM {$wpdb->postmeta} WHERE meta_key = '_unique_id'" );
			$new_unique_id = ( $max_id ) ? $max_id + 1 : 1;
			update_post_meta( $post_id, '_unique_id', $new_unique_id );
		}

		$fields_to_save = array(
			'office_phone',
			'extension',
			'mobile_phone',
			'room_number',
			'department_program',
			'chp_staff_job_title',
			'email_address',
			'active_as_of_date',
			'planned_exit_date',
		);

		foreach ( $fields_to_save as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				$value = sanitize_text_field( $_POST[ $field ] );
				update_post_meta( $post_id, '_' . $field, $value );
			}
		}

		if ( isset( $_POST['location'] ) ) {
			$location_id = sanitize_text_field( $_POST['location'] );
			update_post_meta( $post_id, '_location_id', $location_id );

			if ( isset( $_POST['stackboost_staff_directory_location_name_hidden'] ) ) {
				$location_name = sanitize_text_field( $_POST['stackboost_staff_directory_location_name_hidden'] );
				update_post_meta( $post_id, '_location', $location_name );
			}
		}

		$active_status = 'No';

		if ( $is_new_post ) {
			$active_status = 'Yes';
		} elseif ( isset( $_POST['active'] ) && 'Yes' === $_POST['active'] ) {
			$active_status = 'Yes';
		}

		$active_as_of_date_str = isset( $_POST['active_as_of_date'] ) ? sanitize_text_field( $_POST['active_as_of_date'] ) : '';
		$planned_exit_date_str = isset( $_POST['planned_exit_date'] ) ? sanitize_text_field( $_POST['planned_exit_date'] ) : '';

		$current_timestamp = current_time( 'timestamp' );

		if ( ! empty( $active_as_of_date_str ) ) {
			$active_as_of_timestamp = strtotime( $active_as_of_date_str );
			if ( $active_as_of_timestamp && $active_as_of_timestamp > $current_timestamp ) {
				$active_status = 'No';
			}
		}

		if ( ! empty( $planned_exit_date_str ) ) {
			$planned_exit_timestamp = strtotime( $planned_exit_date_str );
			if ( $planned_exit_timestamp && $planned_exit_timestamp < $current_timestamp ) {
				$active_status = 'No';
			}
		}

		update_post_meta( $post_id, '_active', $active_status );

		$current_user = wp_get_current_user();
		if ( $current_user && $current_user->display_name ) {
			update_post_meta( $post_id, '_last_updated_by', sanitize_text_field( $current_user->display_name ) );
		} else {
			update_post_meta( $post_id, '_last_updated_by', __( 'System', 'stackboost-for-supportcandy' ) );
		}
		update_post_meta( $post_id, '_last_updated_on', date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) );
	}

	/**
	 * Save custom meta box data when the Location post is saved.
	 *
	 * @param int $post_id The ID of the post being saved.
	 */
	public function save_location_details_meta_box_data( $post_id ) {
		if ( ! isset( $_POST['stackboost_location_details_meta_box_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_POST['stackboost_location_details_meta_box_nonce'], 'stackboost_location_details_meta_box' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( $this->location_post_type === get_post_type( $post_id ) && ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$address_line1 = isset( $_POST['address_line1'] ) ? sanitize_text_field( $_POST['address_line1'] ) : '';
		$city          = isset( $_POST['city'] ) ? sanitize_text_field( $_POST['city'] ) : '';
		$state         = isset( $_POST['state'] ) ? sanitize_text_field( $_POST['state'] ) : '';
		$zip           = isset( $_POST['zip'] ) ? sanitize_text_field( $_POST['zip'] ) : '';

		update_post_meta( $post_id, '_address_line1', $address_line1 );
		update_post_meta( $post_id, '_city', $city );
		update_post_meta( $post_id, '_state', $state );
		update_post_meta( $post_id, '_zip', $zip );

		if ( isset( $_POST['location_phone_number'] ) ) {
			update_post_meta( $post_id, '_location_phone_number', sanitize_text_field( $_POST['location_phone_number'] ) );
		}
		if ( isset( $_POST['location_department_program'] ) ) {
			update_post_meta( $post_id, '_location_department_program', sanitize_text_field( $_POST['location_department_program'] ) );
		}

		$is_complete             = ! empty( $address_line1 ) && ! empty( $city ) && ! empty( $state ) && ! empty( $zip );
		$needs_completion_status = $is_complete ? 'no' : 'yes';
		update_post_meta( $post_id, '_needs_completion', $needs_completion_status );
	}
}