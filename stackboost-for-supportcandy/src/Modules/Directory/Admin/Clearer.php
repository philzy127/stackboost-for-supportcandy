<?php

namespace StackBoost\ForSupportCandy\Modules\Directory\Admin;

/**
 * Class Clearer
 *
 * Handles the data clearing functionality.
 *
 * @package StackBoost\ForSupportCandy\Modules\Directory\Admin
 */
class Clearer {

    /**
     * Clearer constructor.
     */
    public function __construct() {
        add_action( 'admin_init', [ $this, 'handle_clear_data' ] );
    }

    /**
     * Render the clear data page.
     */
    public function render_page() {
        ?>
        <div class="wrap">
            <h2>Clear Staff Directory Data</h2>
            <p style="color: red;"><strong>Warning:</strong> This will permanently delete all staff members, locations, and departments. This action cannot be undone.</p>
            <form method="post">
                <input type="hidden" name="stackboost_action" value="clear_data" />
                <?php wp_nonce_field( 'stackboost_clear_data_nonce' ); ?>
                <?php submit_button( 'Delete All Data', 'delete' ); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Handle the data clearing.
     */
    public function handle_clear_data() {
        if ( isset( $_POST['stackboost_action'] ) && $_POST['stackboost_action'] === 'clear_data' ) {
            if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'stackboost_clear_data_nonce' ) ) {
                return;
            }

            $this->delete_all_posts_of_type( 'chp_staff_directory' );
            $this->delete_all_posts_of_type( 'chp_location' );
            $this->delete_all_posts_of_type( 'chp_department' );

            echo '<div class="notice notice-success is-dismissible"><p>All directory data has been deleted.</p></div>';
        }
    }

    /**
     * Delete all posts of a given post type.
     *
     * @param string $post_type The post type to delete.
     */
    private function delete_all_posts_of_type( string $post_type ) {
        $posts = get_posts( [
            'post_type'      => $post_type,
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ] );

        foreach ( $posts as $post_id ) {
            wp_delete_post( $post_id, true );
        }
    }
}