<?php

namespace StackBoost\ForSupportCandy\Modules\OnboardingDashboard\Data;

class CustomPostTypes {

	/**
	 * Initialize CPTs.
	 */
	public static function init() {
		add_action( 'init', [ __CLASS__, 'register_onboarding_cpt' ], 0 );
		add_action( 'init', [ __CLASS__, 'register_legacy_onboarding_cpt' ], 0 );
		add_action( 'add_meta_boxes', [ __CLASS__, 'add_meta_boxes' ] );
		add_action( 'save_post', [ __CLASS__, 'save_meta_box_data' ] );
	}

	/**
	 * Register the new Onboarding Steps CPT.
	 */
	public static function register_onboarding_cpt() {
		$labels = array(
			'name'                  => _x( 'Onboarding Steps', 'Post Type General Name', 'stackboost-for-supportcandy' ),
			'singular_name'         => _x( 'Onboarding Step', 'Post Type Singular Name', 'stackboost-for-supportcandy' ),
			'menu_name'             => __( 'Onboarding Dashboard', 'stackboost-for-supportcandy' ),
			'name_admin_bar'        => __( 'Onboarding Step', 'stackboost-for-supportcandy' ),
			'archives'              => __( 'Onboarding Step Archives', 'stackboost-for-supportcandy' ),
			'attributes'            => __( 'Onboarding Step Attributes', 'stackboost-for-supportcandy' ),
			'parent_item_colon'     => __( 'Parent Onboarding Step:', 'stackboost-for-supportcandy' ),
			'all_items'             => __( 'All Onboarding Steps', 'stackboost-for-supportcandy' ),
			'add_new_item'          => __( 'Add New Onboarding Step', 'stackboost-for-supportcandy' ),
			'add_new'               => __( 'Add New', 'stackboost-for-supportcandy' ),
			'new_item'              => __( 'New Onboarding Step', 'stackboost-for-supportcandy' ),
			'edit_item'             => __( 'Edit Onboarding Step', 'stackboost-for-supportcandy' ),
			'update_item'           => __( 'Update Onboarding Step', 'stackboost-for-supportcandy' ),
			'view_item'             => __( 'View Onboarding Step', 'stackboost-for-supportcandy' ),
			'view_items'            => __( 'View Onboarding Steps', 'stackboost-for-supportcandy' ),
			'search_items'          => __( 'Search Onboarding Step', 'stackboost-for-supportcandy' ),
			'not_found'             => __( 'Not found', 'stackboost-for-supportcandy' ),
			'not_found_in_trash'    => __( 'Not found in Trash', 'stackboost-for-supportcandy' ),
			'featured_image'        => __( 'Featured Image', 'stackboost-for-supportcandy' ),
			'set_featured_image'    => __( 'Set featured image', 'stackboost-for-supportcandy' ),
			'remove_featured_image' => __( 'Remove featured image', 'stackboost-for-supportcandy' ),
			'use_featured_image'    => __( 'Use as featured image', 'stackboost-for-supportcandy' ),
			'insert_into_item'      => __( 'Insert into onboarding step', 'stackboost-for-supportcandy' ),
			'uploaded_to_this_item' => __( 'Uploaded to this onboarding step', 'stackboost-for-supportcandy' ),
			'items_list'            => __( 'Onboarding Steps list', 'stackboost-for-supportcandy' ),
			'items_list_navigation' => __( 'Onboarding Steps list navigation', 'stackboost-for-supportcandy' ),
			'filter_items_list'     => __( 'Filter onboarding steps list', 'stackboost-for-supportcandy' ),
		);
		$args = array(
			'label'                 => __( 'Onboarding Step', 'stackboost-for-supportcandy' ),
			'description'           => __( 'Onboarding steps for the dashboard', 'stackboost-for-supportcandy' ),
			'labels'                => $labels,
			'supports'              => array( 'title' ),
			'hierarchical'          => false,
			'public'                => true,
			'show_ui'               => true,
			'show_in_menu'          => false,
			'menu_position'         => 5,
			'menu_icon'             => 'dashicons-welcome-learn-more',
			'show_in_admin_bar'     => true,
			'show_in_nav_menus'     => true,
			'can_export'            => true,
			'has_archive'           => true,
			'exclude_from_search'   => true,
			'publicly_queryable'    => true,
			'capability_type'       => 'post', // Reverted to 'post' to ensure immediate access for admins
			'show_in_rest'          => true,
		);
		register_post_type( 'stkb_onboarding_step', $args );
	}

	/**
	 * Register the Legacy Onboarding Steps CPT (hidden) for import compatibility.
	 */
	public static function register_legacy_onboarding_cpt() {
		$args = array(
			'label'                 => __( 'Legacy Onboarding Step', 'stackboost-for-supportcandy' ),
			'public'                => false,
			'show_ui'               => false, // Hide from UI
			'show_in_menu'          => false,
			'can_export'            => true, // Allow export/import
			'supports'              => array( 'title', 'editor', 'custom-fields' ),
		);
		register_post_type( 'onboarding_step', $args );
	}

	/**
	 * Add Meta Boxes.
	 */
	public static function add_meta_boxes() {
		add_meta_box(
			'stkb_onboarding_checklist_meta_box',
			__( 'Onboarding Checklist', 'stackboost-for-supportcandy' ),
			[ __CLASS__, 'render_checklist_meta_box' ],
			'stkb_onboarding_step',
			'normal',
			'high'
		);

		add_meta_box(
			'stkb_onboarding_notes_meta_box',
			__( 'Onboarding Notes', 'stackboost-for-supportcandy' ),
			[ __CLASS__, 'render_notes_meta_box' ],
			'stkb_onboarding_step',
			'normal',
			'high'
		);
	}

	/**
	 * Render Checklist Meta Box.
	 * @param \WP_Post $post
	 */
	public static function render_checklist_meta_box( $post ) {
		wp_nonce_field( 'stkb_onboarding_save_meta', 'stkb_onboarding_meta_nonce' );
		$checklist_items = get_post_meta( $post->ID, '_stackboost_onboarding_checklist_items', true );

		echo '<p>';
		echo '<label for="stkb_onboarding_checklist_field">' . __( 'Enter each checklist item on a new line:', 'stackboost-for-supportcandy' ) . '</label>';
		echo '<textarea name="stkb_onboarding_checklist_field" id="stkb_onboarding_checklist_field" rows="8" style="width:100%;">' . esc_textarea( $checklist_items ) . '</textarea>';
		echo '</p>';
		echo '<p class="description">' . __( 'Each line will be a separate checkbox item on the frontend. Use [text in brackets] for info tooltips.', 'stackboost-for-supportcandy' ) . '</p>';
	}

	/**
	 * Render Notes Meta Box.
	 * @param \WP_Post $post
	 */
	public static function render_notes_meta_box( $post ) {
		$notes_content = get_post_meta( $post->ID, '_stackboost_onboarding_notes_content', true );

		wp_editor(
			$notes_content,
			'stkb_onboarding_notes_field',
			array(
				'textarea_name' => 'stkb_onboarding_notes_field',
				'textarea_rows' => 10,
				'teeny'         => false,
				'media_buttons' => true,
			)
		);
		echo '<p class="description">' . __( 'Enter any additional notes for this step. Supports rich text, images, and links.', 'stackboost-for-supportcandy' ) . '</p>';
	}

	/**
	 * Save Meta Box Data.
	 * @param int $post_id
	 */
	public static function save_meta_box_data( $post_id ) {
		if ( ! isset( $_POST['stkb_onboarding_meta_nonce'] ) || ! wp_verify_nonce( $_POST['stkb_onboarding_meta_nonce'], 'stkb_onboarding_save_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['stkb_onboarding_checklist_field'] ) ) {
			$checklist_items = sanitize_textarea_field( $_POST['stkb_onboarding_checklist_field'] );
			update_post_meta( $post_id, '_stackboost_onboarding_checklist_items', $checklist_items );
		}

		if ( isset( $_POST['stkb_onboarding_notes_field'] ) ) {
			$notes_content = wp_kses_post( $_POST['stkb_onboarding_notes_field'] );
			update_post_meta( $post_id, '_stackboost_onboarding_notes_content', $notes_content );
		}
	}
}
