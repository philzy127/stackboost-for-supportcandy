<?php

namespace StackBoost\ForSupportCandy\Modules\Directory;

/**
 * Class Cpt
 *
 * Registers the custom post types for the Directory module.
 * This is a direct port from the standalone CHP Staff Directory plugin.
 *
 * @package StackBoost\ForSupportCandy\Modules\Directory
 */
class Cpt {

    /**
     * Cpt constructor.
     */
    public function __construct() {
        add_action( 'init', [ $this, 'register_all_cpts' ] );
    }

    /**
     * Register all custom post types.
     */
    public function register_all_cpts() {
        $this->register_staff_directory_cpt();
        $this->register_location_cpt();
        $this->register_department_cpt();
    }

    /**
     * Register the Staff Directory custom post type.
     */
    private function register_staff_directory_cpt() {
        $labels = array(
            'name'                  => _x( 'Staff Directory', 'Post Type General Name', 'stackboost-for-supportcandy' ),
            'singular_name'         => _x( 'Staff Entry', 'Post Type Singular Name', 'stackboost-for-supportcandy' ),
            'menu_name'             => __( 'Staff Directory', 'stackboost-for-supportcandy' ),
            'name_admin_bar'        => __( 'Staff Entry', 'stackboost-for-supportcandy' ),
            'archives'              => __( 'Staff Archives', 'stackboost-for-supportcandy' ),
            'attributes'            => __( 'Staff Attributes', 'stackboost-for-supportcandy' ),
            'parent_item_colon'     => __( 'Parent Staff Entry:', 'stackboost-for-supportcandy' ),
            'all_items'             => __( 'All Staff', 'stackboost-for-supportcandy' ),
            'add_new_item'          => __( 'Add New Staff Entry', 'stackboost-for-supportcandy' ),
            'add_new'               => __( 'Add New', 'stackboost-for-supportcandy' ),
            'new_item'              => __( 'New Staff Entry', 'stackboost-for-supportcandy' ),
            'edit_item'             => __( 'Edit Staff Entry', 'stackboost-for-supportcandy' ),
            'update_item'           => __( 'Update Staff Entry', 'stackboost-for-supportcandy' ),
            'view_item'             => __( 'View Staff Entry', 'stackboost-for-supportcandy' ),
            'view_items'            => __( 'View Staff', 'stackboost-for-supportcandy' ),
            'search_items'          => __( 'Search Staff', 'stackboost-for-supportcandy' ),
            'not_found'             => __( 'Not found', 'stackboost-for-supportcandy' ),
            'not_found_in_trash'    => __( 'Not found in Trash', 'stackboost-for-supportcandy' ),
            'featured_image'        => __( 'Staff Photo', 'stackboost-for-supportcandy' ),
            'set_featured_image'    => __( 'Set staff photo', 'stackboost-for-supportcandy' ),
            'remove_featured_image' => __( 'Remove staff photo', 'stackboost-for-supportcandy' ),
            'use_featured_image'    => __( 'Use as staff photo', 'stackboost-for-supportcandy' ),
            'insert_into_item'      => __( 'Insert into staff entry', 'stackboost-for-supportcandy' ),
            'uploaded_to_this_item' => __( 'Uploaded to this staff entry', 'stackboost-for-supportcandy' ),
            'items_list'            => __( 'Staff list', 'stackboost-for-supportcandy' ),
            'items_list_navigation' => __( 'Staff list navigation', 'stackboost-for-supportcandy' ),
            'filter_items_list'     => __( 'Filter staff list', 'stackboost-for-supportcandy' ),
        );
        $args = array(
            'label'                 => __( 'Staff Directory', 'stackboost-for-supportcandy' ),
            'description'           => __( 'Staff directory entries', 'stackboost-for-supportcandy' ),
            'labels'                => $labels,
            'supports'              => array( 'title', 'editor', 'thumbnail' ),
            'hierarchical'          => false,
            'public'                => true,
            'show_ui'               => true,
            'show_in_menu'          => false, // Set to false to manually control menu placement.
            'menu_position'         => 5,
            'menu_icon'             => 'dashicons-groups',
            'show_in_admin_bar'     => true,
            'show_in_nav_menus'     => true,
            'can_export'            => true,
            'has_archive'           => true,
            'exclude_from_search'   => false,
            'publicly_queryable'    => true,
            'capability_type'       => 'post',
            'rewrite'               => array( 'slug' => 'staff' ),
        );
        register_post_type( 'chp_staff_directory', $args );
    }

    /**
     * Register the Location custom post type.
     */
    private function register_location_cpt() {
        $labels = array(
            'name'                  => _x( 'Locations', 'Post Type General Name', 'stackboost-for-supportcandy' ),
            'singular_name'         => _x( 'Location', 'Post Type Singular Name', 'stackboost-for-supportcandy' ),
            'menu_name'             => __( 'Locations', 'stackboost-for-supportcandy' ),
        );
        $args = array(
            'label'                 => __( 'Location', 'stackboost-for-supportcandy' ),
            'description'           => __( 'Physical locations for staff', 'stackboost-for-supportcandy' ),
            'labels'                => $labels,
            'supports'              => array( 'title' ),
            'hierarchical'          => false,
            'public'                => true,
            'show_ui'               => true,
            'show_in_menu'          => false, // Managed by our custom menu.
            'capability_type'       => 'post',
        );
        register_post_type( 'chp_location', $args );
    }

    /**
     * Register the Department custom post type.
     */
    private function register_department_cpt() {
        $labels = array(
            'name'                  => _x( 'Departments', 'Post Type General Name', 'stackboost-for-supportcandy' ),
            'singular_name'         => _x( 'Department', 'Post Type Singular Name', 'stackboost-for-supportcandy' ),
            'menu_name'             => __( 'Departments', 'stackboost-for-supportcandy' ),
        );
        $args = array(
            'label'                 => __( 'Department', 'stackboost-for-supportcandy' ),
            'description'           => __( 'Departments and Programs for staff', 'stackboost-for-supportcandy' ),
            'labels'                => $labels,
            'supports'              => array( 'title' ),
            'hierarchical'          => false,
            'public'                => true,
            'show_ui'               => true,
            'show_in_menu'          => false, // Managed by our custom menu.
            'capability_type'       => 'post',
        );
        register_post_type( 'chp_department', $args );
    }
}