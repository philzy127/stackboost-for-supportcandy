<?php

namespace StackBoost\ForSupportCandy\Modules\Directory\Admin;

/**
 * Class Importer
 *
 * Handles the import functionality.
 *
 * @package StackBoost\ForSupportCandy\Modules\Directory\Admin
 */
class Importer {

    /**
     * Importer constructor.
     */
    public function __construct() {
        add_action( 'admin_init', [ $this, 'handle_import' ] );
    }

    /**
     * Render the import page.
     */
    public function render_page() {
        ?>
        <div class="wrap">
            <h2>Import Staff from CSV</h2>
            <p>Upload a CSV file to import staff members. The CSV should have the following columns: <code>name</code>, <code>job_title</code>, <code>department</code>, <code>office_phone</code>, <code>email</code>.</p>
            <form method="post" enctype="multipart/form-data">
                <input type="file" name="staff_csv" />
                <input type="hidden" name="stackboost_action" value="import_staff" />
                <?php wp_nonce_field( 'stackboost_import_staff_nonce' ); ?>
                <?php submit_button( 'Import' ); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Handle the CSV import.
     */
    public function handle_import() {
        if ( isset( $_POST['stackboost_action'] ) && $_POST['stackboost_action'] === 'import_staff' ) {
            if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'stackboost_import_staff_nonce' ) ) {
                return;
            }

            if ( ! empty( $_FILES['staff_csv']['tmp_name'] ) ) {
                $file = $_FILES['staff_csv']['tmp_name'];
                $handle = fopen( $file, "r" );
                $header = fgetcsv( $handle ); // Skip header row

                while ( ( $row = fgetcsv( $handle ) ) !== false ) {
                    $data = array_combine( $header, $row );
                    $this->create_staff_member( $data );
                }

                fclose( $handle );

                echo '<div class="notice notice-success is-dismissible"><p>Import complete!</p></div>';
            }
        }
    }

    /**
     * Create a new staff member post.
     *
     * @param array $data The data for the staff member.
     */
    private function create_staff_member( array $data ) {
        $post_id = wp_insert_post( [
            'post_title'  => $data['name'],
            'post_type'   => 'chp_staff_directory',
            'post_status' => 'publish',
        ] );

        if ( $post_id ) {
            update_post_meta( $post_id, '_chp_staff_job_title', sanitize_text_field( $data['job_title'] ) );
            update_post_meta( $post_id, '_department_program', sanitize_text_field( $data['department'] ) );
            update_post_meta( $post_id, '_office_phone', sanitize_text_field( $data['office_phone'] ) );
            update_post_meta( $post_id, '_email_address', sanitize_email( $data['email'] ) );
            update_post_meta( $post_id, '_active', 'Yes' );
        }
    }
}