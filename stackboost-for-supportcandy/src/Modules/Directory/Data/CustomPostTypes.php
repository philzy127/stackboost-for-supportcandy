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
	public $post_type = 'sb_staff_dir';

	/**
	 * The custom post type slug for locations.
	 *
	 * @var string
	 */
	public $location_post_type = 'sb_location';

	/**
	 * The custom post type slug for departments.
	 *
	 * @var string
	 */
	public $department_post_type = 'sb_department';

	/**
	 * Constructor.
	 *
	 * Hooks the registration of CPTs into the 'init' action.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_all_cpts' ) );

		// Hooks for enabling meta field revisioning.
		add_action( 'save_post', array( $this, 'save_revision_meta_data' ), 10, 2 );
		add_action( 'wp_restore_post_revision', array( $this, 'restore_revision_meta_data' ), 10, 2 );
		add_filter( '_wp_post_revision_fields', array( $this, 'add_revision_meta_fields' ), 10, 2 );

		// Filter to limit revisions.
		add_filter( 'wp_revisions_to_keep', array( $this, 'filter_revisions_to_keep' ), 10, 2 );
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
		$args   = array(
			'label'               => __( 'Staff Directory', 'stackboost-for-supportcandy' ),
			'description'         => __( 'Staff directory entries.', 'stackboost-for-supportcandy' ),
			'labels'              => $labels,
			'supports'            => array( 'title', 'thumbnail', 'revisions' ),
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
		$result = register_post_type( $this->post_type, $args );
		if (is_wp_error($result)) {
		} else {
		}
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
			'supports'            => array( 'title', 'revisions' ),
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
		$result = register_post_type( $this->location_post_type, $args );
		if (is_wp_error($result)) {
		} else {
		}
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
			'supports'            => array( 'title', 'revisions' ),
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
		$result = register_post_type( $this->department_post_type, $args );
		if (is_wp_error($result)) {
		} else {
		}
	}

	/**
	 * Get the list of meta fields to be revisioned.
	 * @return array
	 */
	private function get_revisioned_meta_fields(): array {
		return array(
			'_office_phone',
			'_extension',
			'_mobile_phone',
			'_location_id',
			'_room_number',
			'_department_program',
			'_stackboost_staff_job_title',
			'_email_address',
			'_active',
			'_active_as_of_date',
			'_planned_exit_date',
			'_user_id',
		);
	}

	/**
	 * Save revision meta data.
	 * @param int $post_id
	 * @param \WP_Post $post
	 */
	public function save_revision_meta_data( int $post_id, \WP_Post $post ) {
		if ( ! in_array( $post->post_type, [ $this->post_type, $this->location_post_type, $this->department_post_type ], true ) ) {
			return;
		}
		if ( ! wp_revisions_enabled( $post ) ) {
			return;
		}

		$parent_id = wp_is_post_revision( $post_id );
		if ( $parent_id ) {
			$parent = get_post( $parent_id );
			$meta_fields = $this->get_revisioned_meta_fields();

			foreach ( $meta_fields as $meta_key ) {
				$meta_value = get_post_meta( $parent->ID, $meta_key, true );
				if ( false !== $meta_value ) {
					add_metadata( 'post', $post_id, $meta_key, $meta_value );
				}
			}
		}
	}

	/**
	 * Restore revision meta data.
	 * @param int $post_id
	 * @param int $revision_id
	 */
	public function restore_revision_meta_data( int $post_id, int $revision_id ) {
		$post        = get_post( $post_id );
		$revision    = get_post( $revision_id );
		$meta_fields = $this->get_revisioned_meta_fields();

		foreach ( $meta_fields as $meta_key ) {
			$meta_value = get_metadata( 'post', $revision->ID, $meta_key, true );
			if ( false !== $meta_value ) {
				update_post_meta( $post_id, $meta_key, $meta_value );
			} else {
				delete_post_meta( $post_id, $meta_key );
			}
		}
	}

	/**
	 * Add meta fields to revision screen.
	 * @param array $fields
	 * @param array $post
	 * @return array
	 */
	public function add_revision_meta_fields( array $fields, array $post ): array {
		if ( ! in_array( $post['post_type'], [ $this->post_type, $this->location_post_type, $this->department_post_type ], true ) ) {
			return $fields;
		}
		$meta_fields = $this->get_revisioned_meta_fields();

		foreach ( $meta_fields as $meta_key ) {
			$fields[ $meta_key ] = ucwords( str_replace( '_', ' ', $meta_key ) );
		}

		return $fields;
	}

	/**
	 * Filter the number of revisions to keep for directory post types.
	 *
	 * @param int      $num  Number of revisions to keep.
	 * @param \WP_Post $post The post object.
	 * @return int The filtered number of revisions.
	 */
	public function filter_revisions_to_keep( $num, $post ) {
		if ( in_array( $post->post_type, array( $this->post_type, $this->location_post_type, $this->department_post_type ), true ) ) {
			$options           = get_option( \StackBoost\ForSupportCandy\Modules\Directory\Admin\Settings::OPTION_NAME, array() );
			$revisions_to_keep = $options['revisions_to_keep'] ?? '';

			if ( '' !== $revisions_to_keep ) {
				return intval( $revisions_to_keep );
			}
		}
		return $num;
	}
}