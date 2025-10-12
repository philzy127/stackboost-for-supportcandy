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
		return print_r( $item, true );
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
			'delete' => sprintf( '<a href="%s" class="submitdelete">%s</a>', get_delete_post_link( $item->ID ), __( 'Trash', 'stackboost-for-supportcandy' ) ),
		);

		return sprintf( '<strong><a class="row-title" href="%s">%s</a></strong>%s', get_edit_post_link( $item->ID ), $item->post_title, $this->row_actions( $actions ) );
	}

	/**
	 * Get bulk actions.
	 *
	 * @return array
	 */
	protected function get_bulk_actions() {
		return array(
			'trash' => __( 'Trash', 'stackboost-for-supportcandy' ),
		);
	}

	/**
	 * Process bulk actions.
	 */
	public function process_bulk_action() {
		// Detect when a bulk action is being triggered.
		if ( 'trash' === $this->current_action() ) {
			$post_ids = isset( $_REQUEST['post'] ) ? wp_parse_id_list( (array) $_REQUEST['post'] ) : array();
			if ( ! empty( $post_ids ) ) {
				foreach ( $post_ids as $post_id ) {
					wp_trash_post( $post_id );
				}
			}
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

		$args = array(
			'post_type'      => $this->post_type,
			'posts_per_page' => $per_page,
			'offset'         => $offset,
			'post_status'    => 'publish',
		);

		$orderby = ( ! empty( $_REQUEST['orderby'] ) ) ? sanitize_sql_orderby( $_REQUEST['orderby'] ) : 'title';
		$order   = ( ! empty( $_REQUEST['order'] ) ) ? sanitize_key( $_REQUEST['order'] ) : 'asc';

		if ( ! empty( $orderby ) & ! empty( $order ) ) {
			$args['orderby'] = $orderby;
			$args['order']   = $order;
		}

		$query      = new \WP_Query( $args );
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