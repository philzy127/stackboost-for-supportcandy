<?php

namespace StackBoost\ForSupportCandy\Modules\Directory\Admin;

/**
 * Class Admin_List
 *
 * Customizes the admin list table for the Staff Directory CPT.
 * This is a direct port from the standalone CHP Staff Directory plugin.
 *
 * @package StackBoost\ForSupportCandy\Modules\Directory\Admin
 */
class Admin_List {

    /**
     * Admin_List constructor.
     */
    public function __construct() {
        add_filter( 'manage_chp_staff_directory_posts_columns', [ $this, 'set_custom_edit_columns' ] );
        add_action( 'manage_chp_staff_directory_posts_custom_column' , [ $this, 'custom_column_content' ], 10, 2 );
        add_filter( 'manage_edit-chp_staff_directory_sortable_columns', [ $this, 'set_sortable_columns' ] );
        add_action( 'pre_get_posts', [ $this, 'sort_custom_columns_query' ] );
    }

    /**
     * Sets custom columns for the CHP Staff Directory list table.
     *
     * @param array $columns The existing columns.
     * @return array The modified columns.
     */
    public function set_custom_edit_columns( $columns ) {
        $columns['title'] = __( 'Staff Name', 'stackboost-for-supportcandy' );
        $columns['chp_contact_phone'] = __( 'Phone', 'stackboost-for-supportcandy' );
        $columns['chp_email_address'] = __( 'Email', 'stackboost-for-supportcandy' );
        $columns['chp_department_program'] = __( 'Department / Program', 'stackboost-for-supportcandy' );
        $columns['chp_job_title'] = __( 'Job Title', 'stackboost-for-supportcandy' );
        unset( $columns['date'] );
        return $columns;
    }

    /**
     * Renders content for custom columns in the CHP Staff Directory list table.
     *
     * @param string $column The name of the column to display.
     * @param int    $post_id The ID of the current post.
     */
    public function custom_column_content( $column, $post_id ) {
        switch ( $column ) {
            case 'chp_contact_phone':
                $output = '';
                $office_phone = get_post_meta( $post_id, '_office_phone', true );
                $extension = get_post_meta( $post_id, '_extension', true );
                if ( ! empty( $office_phone ) ) {
                    $output .= '<strong>' . esc_html__( 'Office', 'stackboost-for-supportcandy' ) . ':</strong> ' . esc_html( $office_phone );
                    if ( ! empty( $extension ) ) {
                        $output .= ' ext. ' . esc_html( $extension );
                    }
                }
                echo ! empty( $output ) ? $output : '&mdash;';
                break;
            case 'chp_email_address':
                $email = get_post_meta( $post_id, '_email_address', true );
                if ( ! empty( $email ) ) {
                    echo '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>';
                } else {
                    echo '&mdash;';
                }
                break;
            case 'chp_department_program':
                echo esc_html( get_post_meta( $post_id, '_department_program', true ) );
                break;
            case 'chp_job_title':
                echo esc_html( get_post_meta( $post_id, '_chp_staff_job_title', true ) );
                break;
        }
    }

    /**
     * Makes custom columns sortable in the CHP Staff Directory list table.
     *
     * @param array $columns The existing sortable columns.
     * @return array The modified sortable columns.
     */
    public function set_sortable_columns( $columns ) {
        $columns['chp_department_program'] = 'chp_department_program';
        $columns['chp_job_title'] = 'chp_job_title';
        return $columns;
    }

    /**
     * Modifies the main query to sort by custom fields when requested.
     *
     * @param \WP_Query $query The current WP_Query object.
     */
    public function sort_custom_columns_query( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() ) {
            return;
        }
        if ( 'chp_staff_directory' !== $query->get( 'post_type' ) ) {
            return;
        }
        $orderby = $query->get( 'orderby' );
        if ( 'chp_department_program' === $orderby ) {
            $query->set( 'orderby', 'meta_value' );
            $query->set( 'meta_key', '_department_program' );
        } elseif ( 'chp_job_title' === $orderby ) {
            $query->set( 'orderby', 'meta_value' );
            $query->set( 'meta_key', '_chp_staff_job_title' );
        }
    }
}