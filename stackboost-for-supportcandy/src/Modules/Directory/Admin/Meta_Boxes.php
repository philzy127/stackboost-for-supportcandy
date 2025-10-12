<?php

namespace StackBoost\ForSupportCandy\Modules\Directory\Admin;

/**
 * Class Meta_Boxes
 *
 * Handles the custom meta boxes for the Staff Directory CPT.
 *
 * @package StackBoost\ForSupportCandy\Modules\Directory\Admin
 */
class Meta_Boxes {

    /**
     * Meta_Boxes constructor.
     */
    public function __construct() {
        add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
        add_action( 'save_post', [ $this, 'save_meta_box_data' ] );
    }

    /**
     * Add the meta boxes.
     */
    public function add_meta_boxes() {
        add_meta_box(
            'stackboost_staff_details',
            'Staff Details',
            [ $this, 'render_staff_details_meta_box' ],
            'chp_staff_directory',
            'normal',
            'high'
        );
    }

    /**
     * Render the staff details meta box.
     *
     * @param \WP_Post $post The post object.
     */
    public function render_staff_details_meta_box( $post ) {
        wp_nonce_field( 'stackboost_save_meta_box_data', 'stackboost_meta_box_nonce' );

        $fields = [
            '_chp_staff_job_title' => 'Job Title',
            '_office_phone'        => 'Office Phone',
            '_extension'           => 'Extension',
            '_mobile_phone'        => 'Mobile Phone',
            '_email_address'       => 'Email Address',
            '_room_number'         => 'Room Number',
        ];

        echo '<table class="form-table">';

        foreach ( $fields as $key => $label ) {
            $value = get_post_meta( $post->ID, $key, true );
            echo '<tr>';
            echo '<th scope="row"><label for="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th>';
            echo '<td><input type="text" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" class="regular-text" /></td>';
            echo '</tr>';
        }

        // Location Dropdown
        $this->render_location_dropdown( $post->ID );

        // Department Dropdown
        $this->render_department_dropdown( $post->ID );

        // Active Status
        $this->render_active_status( $post->ID );

        echo '</table>';
    }

    /**
     * Render the location dropdown.
     *
     * @param int $post_id The post ID.
     */
    private function render_location_dropdown( int $post_id ) {
        $locations = get_posts( [ 'post_type' => 'chp_location', 'posts_per_page' => -1 ] );
        $selected_location = get_post_meta( $post_id, '_location_id', true );

        echo '<tr>';
        echo '<th scope="row"><label for="_location_id">Location</label></th>';
        echo '<td><select name="_location_id" id="_location_id">';
        echo '<option value="">Select a Location</option>';
        foreach ( $locations as $location ) {
            echo '<option value="' . esc_attr( $location->ID ) . '" ' . selected( $selected_location, $location->ID, false ) . '>' . esc_html( $location->post_title ) . '</option>';
        }
        echo '</select></td>';
        echo '</tr>';
    }

    /**
     * Render the department dropdown.
     *
     * @param int $post_id The post ID.
     */
    private function render_department_dropdown( int $post_id ) {
        $departments = get_posts( [ 'post_type' => 'chp_department', 'posts_per_page' => -1 ] );
        $selected_department = get_post_meta( $post_id, '_department_program', true );

        echo '<tr>';
        echo '<th scope="row"><label for="_department_program">Department</label></th>';
        echo '<td><select name="_department_program" id="_department_program">';
        echo '<option value="">Select a Department</option>';
        foreach ( $departments as $department ) {
            echo '<option value="' . esc_attr( $department->post_title ) . '" ' . selected( $selected_department, $department->post_title, false ) . '>' . esc_html( $department->post_title ) . '</option>';
        }
        echo '</select></td>';
        echo '</tr>';
    }

    /**
     * Render the active status radio buttons.
     *
     * @param int $post_id The post ID.
     */
    private function render_active_status( int $post_id ) {
        $active_status = get_post_meta( $post_id, '_active', true );
        ?>
        <tr>
            <th scope="row">Active Status</th>
            <td>
                <label><input type="radio" name="_active" value="Yes" <?php checked( $active_status, 'Yes' ); ?> /> Yes</label>
                <label style="margin-left: 10px;"><input type="radio" name="_active" value="No" <?php checked( $active_status, 'No' ); ?> /> No</label>
            </td>
        </tr>
        <?php
    }

    /**
     * Save the meta box data.
     *
     * @param int $post_id The post ID.
     */
    public function save_meta_box_data( $post_id ) {
        if ( ! isset( $_POST['stackboost_meta_box_nonce'] ) || ! wp_verify_nonce( $_POST['stackboost_meta_box_nonce'], 'stackboost_save_meta_box_data' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $fields = [
            '_chp_staff_job_title',
            '_office_phone',
            '_extension',
            '_mobile_phone',
            '_email_address',
            '_room_number',
            '_location_id',
            '_department_program',
            '_active',
        ];

        foreach ( $fields as $key ) {
            if ( isset( $_POST[ $key ] ) ) {
                update_post_meta( $post_id, $key, sanitize_text_field( $_POST[ $key ] ) );
            }
        }

        // Also save the location name for convenience
        if ( isset( $_POST['_location_id'] ) ) {
            $location_name = get_the_title( $_POST['_location_id'] );
            update_post_meta( $post_id, '_location', $location_name );
        }
    }
}