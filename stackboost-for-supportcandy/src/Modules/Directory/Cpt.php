<?php

namespace StackBoost\ForSupportCandy\Modules\Directory;

/**
 * Class Cpt
 *
 * Registers the custom post types for the Directory module.
 *
 * @package StackBoost\ForSupportCandy\Modules\Directory
 */
class Cpt {

    /**
     * Cpt constructor.
     */
    public function __construct() {
        add_action( 'init', [ $this, 'register_post_types' ] );
    }

    /**
     * Register the custom post types.
     */
    public function register_post_types() {
        $this->register_staff_directory_cpt();
        $this->register_location_cpt();
        $this->register_department_cpt();
    }

    /**
     * Register the Staff Directory CPT.
     */
    private function register_staff_directory_cpt() {
        $labels = [
            'name'               => _x( 'Staff Directory', 'post type general name', 'stackboost-for-supportcandy' ),
            'singular_name'      => _x( 'Staff Member', 'post type singular name', 'stackboost-for-supportcandy' ),
            'menu_name'          => _x( 'Staff Directory', 'admin menu', 'stackboost-for-supportcandy' ),
            'name_admin_bar'     => _x( 'Staff Member', 'add new on admin bar', 'stackboost-for-supportcandy' ),
            'add_new'            => _x( 'Add New', 'staff member', 'stackboost-for-supportcandy' ),
            'add_new_item'       => __( 'Add New Staff Member', 'stackboost-for-supportcandy' ),
            'new_item'           => __( 'New Staff Member', 'stackboost-for-supportcandy' ),
            'edit_item'          => __( 'Edit Staff Member', 'stackboost-for-supportcandy' ),
            'view_item'          => __( 'View Staff Member', 'stackboost-for-supportcandy' ),
            'all_items'          => __( 'All Staff', 'stackboost-for-supportcandy' ),
            'search_items'       => __( 'Search Staff', 'stackboost-for-supportcandy' ),
            'parent_item_colon'  => __( 'Parent Staff:', 'stackboost-for-supportcandy' ),
            'not_found'          => __( 'No staff members found.', 'stackboost-for-supportcandy' ),
            'not_found_in_trash' => __( 'No staff members found in Trash.', 'stackboost-for-supportcandy' ),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => 'stackboost-directory',
            'query_var'          => true,
            'rewrite'            => [ 'slug' => 'staff' ],
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 20,
            'supports'           => [ 'title', 'editor', 'thumbnail' ],
            'menu_icon'          => 'dashicons-groups',
        ];

        register_post_type( 'chp_staff_directory', $args );
    }

    /**
     * Register the Location CPT.
     */
    private function register_location_cpt() {
        $labels = [
            'name'          => _x( 'Locations', 'post type general name', 'stackboost-for-supportcandy' ),
            'singular_name' => _x( 'Location', 'post type singular name', 'stackboost-for-supportcandy' ),
        ];

        $args = [
            'labels'        => $labels,
            'public'        => false,
            'show_ui'       => true,
            'show_in_menu'  => 'edit.php?post_type=chp_staff_directory',
            'capability_type' => 'post',
            'supports'      => [ 'title' ],
        ];

        register_post_type( 'chp_location', $args );
    }

    /**
     * Register the Department CPT.
     */
    private function register_department_cpt() {
        $labels = [
            'name'          => _x( 'Departments', 'post type general name', 'stackboost-for-supportcandy' ),
            'singular_name' => _x( 'Department', 'post type singular name', 'stackboost-for-supportcandy' ),
        ];

        $args = [
            'labels'        => $labels,
            'public'        => false,
            'show_ui'       => true,
            'show_in_menu'  => 'edit.php?post_type=chp_staff_directory',
            'capability_type' => 'post',
            'supports'      => [ 'title' ],
        ];

        register_post_type( 'chp_department', $args );
    }
}