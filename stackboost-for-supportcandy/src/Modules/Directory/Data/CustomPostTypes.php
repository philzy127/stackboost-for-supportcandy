<?php
/**
 * StackBoost Custom Post Types for Company Directory.
 *
 * This file registers the custom post types required for the
 * Company Directory module, including Staff, Locations, and Departments.
 * It is a migration of the CPT registration from the standalone
 * Company Directory plugin, adapted for the StackBoost framework.
 *
 * @package StackBoost
 * @subpackage Modules\Directory\Data
 */

namespace StackBoost\ForSupportCandy\Modules\Directory\Data;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * CustomPostTypes Class
 *
 * Registers the custom post types for the Company Directory module.
 */
class CustomPostTypes {

	/**
	 * The custom post type slug for staff entries.
	 *
	 * @var string
	 */
	public $post_type = 'stackboost_staff_directory';

	/**
	 * The custom post type slug for locations.
	 *
	 * @var string
	 */
	public $location_post_type = 'stackboost_location';

	/**
	 * The custom post type slug for departments.
	 *
	 * @var string
	 */
	public $department_post_type = 'stackboost_department';

	/**
	 * Constructor.
	 *
	 * Hooks the registration of CPTs into the 'init' action.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_all_cpts' ) );
	}

	/**
	 * Register all custom post types.
	 */
	public function register_all_cpts() {
		error_log('[StackBoost DEBUG] Firing register_all_cpts()');
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
		$args   = array(
			'label'               => __( 'Staff Directory', 'stackboost-for-supportcandy' ),
			'description'         => __( 'Staff directory entries.', 'stackboost-for-supportcandy' ),
			'labels'              => $labels,
			'supports'            => array( 'title', 'thumbnail' ),
			'hierarchical'        => false,
			'public'              => true,
			'show_ui'             => true,
			'show_in_menu'        => false, // Set to false to hide from top-level menu.
			'menu_position'       => 5,
			'menu_icon'           => 'dashicons-groups',
			'show_in_admin_bar'   => true,
			'show_in_nav_menus'   => true,
			'can_export'          => true,
			'has_archive'         => true,
			'exclude_from_search' => false,
			'publicly_queryable'  => true,
			'capability_type'     => 'post',
			'rewrite'             => array( 'slug' => 'staff' ),
		);
		error_log('[StackBoost DEBUG] Registering CPT: ' . $this->post_type);
		register_post_type( $this->post_type, $args );
	}

	/**
	 * Register the Location custom post type.
	 */
	private function register_location_cpt() {
		$labels = array(
			'name'                  => _x( 'Locations', 'Post Type General Name', 'stackboost-for-supportcandy' ),
			'singular_name'         => _x( 'Location', 'Post Type Singular Name', 'stackboost-for-supportcandy' ),
			'menu_name'             => __( 'Locations', 'stackboost-for-supportcandy' ),
			'name_admin_bar'        => __( 'Location', 'stackboost-for-supportcandy' ),
			'archives'              => __( 'Location Archives', 'stackboost-for-supportcandy' ),
			'attributes'            => __( 'Location Attributes', 'stackboost-for-supportcandy' ),
			'parent_item_colon'     => __( 'Parent Location:', 'stackboost-for-supportcandy' ),
			'all_items'             => __( 'All Locations', 'stackboost-for-supportcandy' ),
			'add_new_item'          => __( 'Add New Location', 'stackboost-for-supportcandy' ),
			'add_new'               => __( 'Add New', 'stackboost-for-supportcandy' ),
			'new_item'              => __( 'New Location', 'stackboost-for-supportcandy' ),
			'edit_item'             => __( 'Edit Location', 'stackboost-for-supportcandy' ),
			'update_item'           => __( 'Update Location', 'stackboost-for-supportcandy' ),
			'view_item'             => __( 'View Location', 'stackboost-for-supportcandy' ),
			'view_items'            => __( 'View Locations', 'stackboost-for-supportcandy' ),
			'search_items'          => __( 'Search Locations', 'stackboost-for-supportcandy' ),
			'not_found'             => __( 'Not found', 'stackboost-for-supportcandy' ),
			'not_found_in_trash'    => __( 'Not found in Trash', 'stackboost-for-supportcandy' ),
			'insert_into_item'      => __( 'Insert into location', 'stackboost-for-supportcandy' ),
			'uploaded_to_this_item' => __( 'Uploaded to this location', 'stackboost-for-supportcandy' ),
			'items_list'            => __( 'Locations list', 'stackboost-for-supportcandy' ),
			'items_list_navigation' => __( 'Locations list navigation', 'stackboost-for-supportcandy' ),
			'filter_items_list'     => __( 'Filter locations list', 'stackboost-for-supportcandy' ),
		);
		$args   = array(
			'label'               => __( 'Location', 'stackboost-for-supportcandy' ),
			'description'         => __( 'Physical locations for staff', 'stackboost-for-supportcandy' ),
			'labels'              => $labels,
			'supports'            => array( 'title' ),
			'hierarchical'        => false,
			'public'              => true,
			'show_ui'             => true,
			'show_in_menu'        => false, // Set to false to hide from menu.
			'menu_position'       => 20,
			'show_in_admin_bar'   => true,
			'show_in_nav_menus'   => true,
			'can_export'          => true,
			'has_archive'         => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'capability_type'     => 'post',
			'rewrite'             => false,
		);
		error_log('[StackBoost DEBUG] Registering CPT: ' . $this->location_post_type);
		register_post_type( $this->location_post_type, $args );
	}

	/**
	 * Register the Department custom post type.
	 */
	private function register_department_cpt() {
		$labels = array(
			'name'                  => _x( 'Departments', 'Post Type General Name', 'stackboost-for-supportcandy' ),
			'singular_name'         => _x( 'Department', 'Post Type Singular Name', 'stackboost-for-supportcandy' ),
			'menu_name'             => __( 'Departments', 'stackboost-for-supportcandy' ),
			'name_admin_bar'        => __( 'Department', 'stackboost-for-supportcandy' ),
			'archives'              => __( 'Department Archives', 'stackboost-for-supportcandy' ),
			'attributes'            => __( 'Department Attributes', 'stackboost-for-supportcandy' ),
			'parent_item_colon'     => __( 'Parent Department:', 'stackboost-for-supportcandy' ),
			'all_items'             => __( 'All Departments', 'stackboost-for-supportcandy' ),
			'add_new_item'          => __( 'Add New Department', 'stackboost-for-supportcandy' ),
			'add_new'               => __( 'Add New', 'stackboost-for-supportcandy' ),
			'new_item'              => __( 'New Department', 'stackboost-for-supportcandy' ),
			'edit_item'             => __( 'Edit Department', 'stackboost-for-supportcandy' ),
			'update_item'           => __( 'Update Department', 'stackboost-for-supportcandy' ),
			'view_item'             => __( 'View Department', 'stackboost-for-supportcandy' ),
			'view_items'            => __( 'View Departments', 'stackboost-for-supportcandy' ),
			'search_items'          => __( 'Search Departments', 'stackboost-for-supportcandy' ),
			'not_found'             => __( 'Not found', 'stackboost-for-supportcandy' ),
			'not_found_in_trash'    => __( 'Not found in Trash', 'stackboost-for-supportcandy' ),
			'insert_into_item'      => __( 'Insert into department', 'stackboost-for-supportcandy' ),
			'uploaded_to_this_item' => __( 'Uploaded to this department', 'stackboost-for-supportcandy' ),
			'items_list'            => __( 'Departments list', 'stackboost-for-supportcandy' ),
			'items_list_navigation' => __( 'Departments list navigation', 'stackboost-for-supportcandy' ),
			'filter_items_list'     => __( 'Filter departments list', 'stackboost-for-supportcandy' ),
		);
		$args   = array(
			'label'               => __( 'Department', 'stackboost-for-supportcandy' ),
			'description'         => __( 'Departments and Programs for staff', 'stackboost-for-supportcandy' ),
			'labels'              => $labels,
			'supports'            => array( 'title' ),
			'hierarchical'        => false,
			'public'              => true,
			'show_ui'             => true,
			'show_in_menu'        => false, // Set to false to hide from menu.
			'menu_position'       => 25,
			'show_in_admin_bar'   => true,
			'show_in_nav_menus'   => true,
			'can_export'          => true,
			'has_archive'         => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'capability_type'     => 'post',
			'rewrite'             => false,
		);
		error_log('[StackBoost DEBUG] Registering CPT: ' . $this->department_post_type);
		register_post_type( $this->department_post_type, $args );
	}
}