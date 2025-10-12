<?php

namespace StackBoost\ForSupportCandy\Modules\Directory\Admin;

/**
 * Class Admin_List
 *
 * Customizes the admin list table for the Staff Directory CPT.
 *
 * @package StackBoost\ForSupportCandy\Modules\Directory\Admin
 */
class Admin_List {

    /**
     * Admin_List constructor.
     */
    public function __construct() {
        add_filter( 'manage_chp_staff_directory_posts_columns', [ $this, 'add_custom_columns' ] );
        add_action( 'manage_chp_staff_directory_posts_custom_column', [ $this, 'render_custom_columns' ], 10, 2 );
        add_filter( 'manage_edit-chp_staff_directory_sortable_columns', [ $this, 'make_columns_sortable' ] );
    }

    /**
     * Add custom columns to the staff directory list table.
     *
     * @param array $columns The existing columns.
     * @return array The modified columns.
     */
    public function add_custom_columns( $columns ): array {
        $new_columns = [];
        foreach ( $columns as $key => $title ) {
            $new_columns[ $key ] = $title;
            if ( $key === 'title' ) {
                $new_columns['job_title'] = 'Job Title';
                $new_columns['department'] = 'Department';
                $new_columns['phone'] = 'Phone';
                $new_columns['email'] = 'Email';
            }
        }
        return $new_columns;
    }

    /**
     * Render the content for the custom columns.
     *
     * @param string $column_name The name of the column.
     * @param int    $post_id     The post ID.
     */
    public function render_custom_columns( string $column_name, int $post_id ) {
        switch ( $column_name ) {
            case 'job_title':
                echo esc_html( get_post_meta( $post_id, '_chp_staff_job_title', true ) );
                break;
            case 'department':
                echo esc_html( get_post_meta( $post_id, '_department_program', true ) );
                break;
            case 'phone':
                echo esc_html( get_post_meta( $post_id, '_office_phone', true ) );
                break;
            case 'email':
                $email = get_post_meta( $post_id, '_email_address', true );
                echo '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>';
                break;
        }
    }

    /**
     * Make the custom columns sortable.
     *
     * @param array $columns The existing sortable columns.
     * @return array The modified sortable columns.
     */
    public function make_columns_sortable( $columns ): array {
        $columns['job_title'] = '_chp_staff_job_title';
        $columns['department'] = '_department_program';
        return $columns;
    }
}