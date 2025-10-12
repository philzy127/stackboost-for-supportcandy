<?php

namespace StackBoost\ForSupportCandy\Modules\Directory\Admin;

/**
 * Class Meta_Boxes
 *
 * Handles the creation and saving of custom meta boxes for staff entries and locations.
 * This is a direct port from the standalone CHP Staff Directory plugin.
 *
 * @package StackBoost\ForSupportCandy\Modules\Directory\Admin
 */
class Meta_Boxes {

    /**
     * Meta_Boxes constructor.
     */
    public function __construct() {
        add_action( 'add_meta_boxes', [ $this, 'add_all_meta_boxes' ] );
        add_action( 'save_post', [ $this, 'save_directory_meta_box_data' ] );
        add_action( 'save_post', [ $this, 'save_location_details_meta_box_data' ] );
    }

    /**
     * Add all meta boxes.
     */
    public function add_all_meta_boxes() {
        $this->add_directory_meta_box();
        $this->add_location_details_meta_box();
    }

    /**
     * Add the custom meta box for Staff Directory.
     */
    public function add_directory_meta_box() {
        add_meta_box(
            'chp_staff_directory_details',
            __( 'Staff Details', 'stackboost-for-supportcandy' ),
            [ $this, 'render_directory_meta_box' ],
            'chp_staff_directory',
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
        wp_nonce_field( 'chp_staff_directory_meta_box', 'chp_staff_directory_meta_box_nonce' );

        $fields = [
            'chp_staff_job_title' => 'Title',
            'office_phone'        => 'Office Phone',
            'extension'           => 'Extension',
            'mobile_phone'        => 'Mobile Phone',
            'email_address'       => 'Email Address',
            'room_number'         => 'Room #',
        ];

        echo '<table class="form-table">';

        foreach ( $fields as $key => $label ) {
            $value = get_post_meta( $post->ID, '_' . $key, true );
            echo '<tr>';
            echo '<th scope="row"><label for="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th>';
            echo '<td><input type="text" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" class="regular-text" /></td>';
            echo '</tr>';
        }

        $this->render_location_dropdown( $post->ID );
        $this->render_department_dropdown( $post->ID );
        $this->render_active_status( $post->ID );

        echo '</table>';
    }

    private function render_location_dropdown( int $post_id ) {
        $locations = get_posts( [ 'post_type' => 'chp_location', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ] );
        $current_location_id = get_post_meta( $post_id, '_location_id', true );
        ?>
        <tr>
            <th scope="row"><label for="location"><?php esc_html_e( 'Location', 'stackboost-for-supportcandy' ); ?></label></th>
            <td>
                <select name="location" id="location" class="postbox-select-location">
                    <option value=""><?php esc_html_e( 'Select a Location', 'stackboost-for-supportcandy' ); ?></option>
                    <?php foreach ( $locations as $location_post ) : ?>
                        <option value="<?php echo esc_attr( $location_post->ID ); ?>" <?php selected( $current_location_id, $location_post->ID ); ?>><?php echo esc_html( $location_post->post_title ); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" id="chp_staff_directory_location_name_hidden" name="chp_staff_directory_location_name_hidden" value="<?php echo esc_attr( get_post_meta( $post_id, '_location', true ) ); ?>">
            </td>
        </tr>
        <?php
    }

    private function render_department_dropdown( int $post_id ) {
        $departments = get_posts( [ 'post_type' => 'chp_department', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ] );
        $current_department = get_post_meta( $post_id, '_department_program', true );
        ?>
        <tr>
            <th scope="row"><label for="department_program"><?php esc_html_e( 'Department / Program', 'stackboost-for-supportcandy' ); ?></label></th>
            <td>
                <select name="department_program" id="department_program" class="postbox-select-department">
                    <option value=""><?php esc_html_e( 'Select a Department/Program', 'stackboost-for-supportcandy' ); ?></option>
                    <?php foreach ( $departments as $department_post ) : ?>
                        <option value="<?php echo esc_attr( $department_post->post_title ); ?>" <?php selected( $current_department, $department_post->post_title ); ?>><?php echo esc_html( $department_post->post_title ); ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <?php
    }

    private function render_active_status( int $post_id ) {
        $value = get_post_meta( $post_id, '_active', true );
        $checked = ( $value === 'Yes' || $value === '1' ) ? 'checked' : '';
        ?>
        <tr>
            <th scope="row"><label for="active"><?php esc_html_e( 'Active', 'stackboost-for-supportcandy' ); ?></label></th>
            <td>
                <input type="checkbox" id="active" name="active" value="Yes" <?php echo $checked; ?> />
                <p class="description"><?php esc_html_e( 'Check if this entry is active; uncheck if deprecated.', 'stackboost-for-supportcandy' ); ?></p>
            </td>
        </tr>
        <?php
    }

    /**
     * Add the custom meta box for Location Details.
     */
    public function add_location_details_meta_box() {
        add_meta_box(
            'chp_location_details',
            __( 'Location Details', 'stackboost-for-supportcandy' ),
            [ $this, 'render_location_details_meta_box' ],
            'chp_location',
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
        wp_nonce_field( 'chp_location_details_meta_box', 'chp_location_details_meta_box_nonce' );
        $fields = [
            'address_line1' => 'Address Line 1',
            'city' => 'City',
            'state' => 'State',
            'zip' => 'Zip',
            'location_phone_number' => 'Phone Number',
            'location_department_program' => 'Department / Program (Optional)',
        ];

        echo '<table class="form-table">';
        foreach ( $fields as $key => $label ) {
            $value = get_post_meta( $post->ID, '_' . $key, true );
            echo '<tr>';
            echo '<th scope="row"><label for="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th>';
            echo '<td><input type="text" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" class="regular-text" /></td>';
            echo '</tr>';
        }
        echo '</table>';
    }

    /**
     * Save custom meta box data when the Staff Directory post is saved.
     *
     * @param int $post_id The ID of the post being saved.
     */
    public function save_directory_meta_box_data( $post_id ) {
        if ( ! isset( $_POST['chp_staff_directory_meta_box_nonce'] ) || ! wp_verify_nonce( $_POST['chp_staff_directory_meta_box_nonce'], 'chp_staff_directory_meta_box' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( 'chp_staff_directory' !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $fields_to_save = [
            'office_phone', 'extension', 'mobile_phone', 'room_number', 'department_program', 'chp_staff_job_title', 'email_address'
        ];

        foreach ( $fields_to_save as $field ) {
            if ( isset( $_POST[ $field ] ) ) {
                update_post_meta( $post_id, '_' . $field, sanitize_text_field( $_POST[ $field ] ) );
            }
        }

        if ( isset( $_POST['location'] ) ) {
            $location_id = sanitize_text_field( $_POST['location'] );
            update_post_meta( $post_id, '_location_id', $location_id );
            if ( isset( $_POST['chp_staff_directory_location_name_hidden'] ) ) {
                update_post_meta( $post_id, '_location', sanitize_text_field( $_POST['chp_staff_directory_location_name_hidden'] ) );
            }
        }

        $active_status = isset( $_POST['active'] ) && $_POST['active'] === 'Yes' ? 'Yes' : 'No';
        update_post_meta( $post_id, '_active', $active_status );
    }

    /**
     * Save custom meta box data when the Location post is saved.
     *
     * @param int $post_id The ID of the post being saved.
     */
    public function save_location_details_meta_box_data( $post_id ) {
        if ( ! isset( $_POST['chp_location_details_meta_box_nonce'] ) || ! wp_verify_nonce( $_POST['chp_location_details_meta_box_nonce'], 'chp_location_details_meta_box' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( 'chp_location' !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $fields = [ 'address_line1', 'city', 'state', 'zip', 'location_phone_number', 'location_department_program' ];
        foreach ( $fields as $field ) {
            if ( isset( $_POST[ $field ] ) ) {
                update_post_meta( $post_id, '_' . $field, sanitize_text_field( $_POST[ $field ] ) );
            }
        }
    }
}