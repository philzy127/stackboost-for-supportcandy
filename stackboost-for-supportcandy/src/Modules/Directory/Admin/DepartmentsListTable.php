<?php
/**
 * StackBoost Departments List Table.
 *
 * This file contains the class for the Departments List Table, which extends
 * WP_List_Table to display department entries in the admin area.
 *
 * @package StackBoost
 * @subpackage Modules\Directory\Admin
 */

namespace StackBoost\ForSupportCandy\Modules\Directory\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * DepartmentsListTable Class
 *
 * Creates a WP_List_Table for displaying department entries.
 */
class DepartmentsListTable extends \WP_List_Table {

	/**
	 * The custom post type slug for departments.
	 *
	 * @var string
	 */
	private $post_type;

	/**
	 * Constructor.
	 *
	 * @param string $post_type The post type slug.
	 */
	public function __construct( $post_type ) {
		$this->post_type = $post_type;

		parent::__construct(
			array(
				'singular' => __( 'Department', 'stackboost-for-supportcandy' ),
				'plural'   => __( 'Departments', 'stackboost-for-supportcandy' ),
				'ajax'     => false,
			)
		);
	}

	/**
	 * Get the list of columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'cb'    => '<input type="checkbox" />',
			'title' => __( 'Department Name', 'stackboost-for-supportcandy' ),
		);
	}

	/**
	 * Get a list of sortable columns.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'title' => array( 'title', false ),
		);
	}

	/**
	 * Render a column when no custom rendering function is available.
	 *
	 * @param  object $item
	 * @param  string $column_name
	 * @return mixed
	 */
	public function column_default( $item, $column_name ) {
		// Removed print_r
		return '';
	}

	/**
	 * Render the checkbox column.
	 *
	 * @param  object $item
	 * @return string
	 */
	public function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="post[]" value="%s" />',
			$item->ID
		);
	}

	/**
	 * Render the title column with actions.
	 *
	 * @param  object $item
	 * @return string
	 */
	public function column_title( $item ) {
		$actions = array(
			'edit'   => sprintf( '<a href="%s">%s</a>', get_edit_post_link( $item->ID ), __( 'Edit', 'stackboost-for-supportcandy' ) ),
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_status = isset( $_REQUEST['post_status'] ) ? sanitize_key( wp_unslash( $_REQUEST['post_status'] ) ) : '';

		if ( 'trash' === $post_status ) {
			$actions['untrash'] = sprintf( '<a href="%s">%s</a>', wp_nonce_url( admin_url( 'admin.php?page=stackboost-directory&tab=departments&action=untrash&post=' . $item->ID ), 'untrash-post_' . $item->ID ), __( 'Restore', 'stackboost-for-supportcandy' ) );
			$actions['delete']  = sprintf( '<a href="%s">%s</a>', wp_nonce_url( admin_url( 'admin.php?page=stackboost-directory&tab=departments&action=delete&post=' . $item->ID ), 'delete-post_' . $item->ID ), __( 'Delete Permanently', 'stackboost-for-supportcandy' ) );
		} else {
			$actions['trash'] = sprintf( '<a href="%s" class="submitdelete">%s</a>', wp_nonce_url( admin_url( 'admin.php?page=stackboost-directory&tab=departments&action=trash&post=' . $item->ID ), 'trash-post_' . $item->ID ), __( 'Trash', 'stackboost-for-supportcandy' ) );
		}

		return sprintf( '<strong><a class="row-title" href="%s">%s</a></strong>%s', get_edit_post_link( $item->ID ), $item->post_title, $this->row_actions( $actions ) );
	}

	/**
	 * Get bulk actions.
	 *
	 * @return array
	 */
	protected function get_bulk_actions() {
		$actions = array();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_status = isset( $_REQUEST['post_status'] ) ? sanitize_key( wp_unslash( $_REQUEST['post_status'] ) ) : '';

		if ( 'trash' === $post_status ) {
			$actions['untrash'] = __( 'Restore', 'stackboost-for-supportcandy' );
			$actions['delete']  = __( 'Delete Permanently', 'stackboost-for-supportcandy' );
		} else {
			$actions['trash'] = __( 'Trash', 'stackboost-for-supportcandy' );
		}
		return $actions;
	}

	/**
	 * Get the views for the list table.
	 *
	 * @return array
	 */
	protected function get_views() {
		$status_links = array();
		$num_posts    = wp_count_posts( $this->post_type, 'readable' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_status  = isset( $_REQUEST['post_status'] ) ? sanitize_key( wp_unslash( $_REQUEST['post_status'] ) ) : 'all';

		$all_class           = ( 'all' === $post_status ) ? ' class="current"' : '';
		$status_links['all'] = "<a href='admin.php?page=stackboost-directory&tab=departments'{$all_class}>All <span class='count'>(" . ( $num_posts->publish + $num_posts->draft ) . ')</span></a>';

		if ( ! empty( $num_posts->trash ) ) {
			$trash_class           = ( 'trash' === $post_status ) ? ' class="current"' : '';
			$status_links['trash'] = "<a href='admin.php?page=stackboost-directory&tab=departments&post_status=trash'{$trash_class}>Trash <span class='count'>(" . $num_posts->trash . ')</span></a>';
		}

		return $status_links;
	}

	/**
	 * Process bulk actions.
	 */
	public function process_bulk_action() {
		$action = $this->current_action();
		if ( ! $action ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_ids = isset( $_REQUEST['post'] ) ? wp_parse_id_list( wp_unslash( (array) $_REQUEST['post'] ) ) : array();
		if ( empty( $post_ids ) ) {
			return;
		}

		check_admin_referer( 'bulk-' . $this->_args['plural'] );

		switch ( $action ) {
			case 'trash':
				foreach ( $post_ids as $post_id ) {
					wp_trash_post( $post_id );
				}
				break;
			case 'untrash':
				foreach ( $post_ids as $post_id ) {
					wp_untrash_post( $post_id );
				}
				break;
			case 'delete':
				foreach ( $post_ids as $post_id ) {
					wp_delete_post( $post_id, true );
				}
				break;
		}
	}

	/**
	 * Prepare the items for the table to process.
	 */
	public function prepare_items() {
		$this->process_bulk_action();
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$per_page     = 20;
		$current_page = $this->get_pagenum();
		$offset       = ( $current_page - 1 ) * $per_page;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$args = array(
			'post_type'      => $this->post_type,
			'posts_per_page' => $per_page,
			'offset'         => $offset,
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'post_status'    => ( isset( $_REQUEST['post_status'] ) ? sanitize_key( wp_unslash( $_REQUEST['post_status'] ) ) : 'any' ),
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$orderby = ( ! empty( $_REQUEST['orderby'] ) ) ? sanitize_sql_orderby( wp_unslash( $_REQUEST['orderby'] ) ) : 'title';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order   = ( ! empty( $_REQUEST['order'] ) ) ? sanitize_key( wp_unslash( $_REQUEST['order'] ) ) : 'asc';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_REQUEST['s'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$args['s'] = sanitize_text_field( wp_unslash( $_REQUEST['s'] ) );
		}

		if ( ! empty( $orderby ) & ! empty( $order ) ) {
			$args['orderby'] = $orderby;
			$args['order']   = $order;
		}

		$query      = new \WP_Query( $args );
		if ($query->have_posts()) {
		} else {
		}
		$this->items = $query->posts;

		$total_items = $query->found_posts;

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
			)
		);
	}
}
