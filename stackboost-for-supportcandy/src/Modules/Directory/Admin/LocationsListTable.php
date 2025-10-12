<?php
/**
 * StackBoost Locations List Table.
 *
 * This file contains the class for the Locations List Table, which extends
 * WP_List_Table to display location entries in the admin area.
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
 * LocationsListTable Class
 *
 * Creates a WP_List_Table for displaying location entries.
 */
class LocationsListTable extends \WP_List_Table {

	/**
	 * The custom post type slug for locations.
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
				'singular' => __( 'Location', 'stackboost-for-supportcandy' ),
				'plural'   => __( 'Locations', 'stackboost-for-supportcandy' ),
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
			'cb'                   => '<input type="checkbox" />',
			'title'                => __( 'Location Name', 'stackboost-for-supportcandy' ),
			'chp_needs_completion' => __( 'Needs Completion', 'stackboost-for-supportcandy' ),
		);
	}

	/**
	 * Get a list of sortable columns.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'title'                => array( 'title', false ),
			'chp_needs_completion' => array( 'chp_needs_completion', false ),
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
		switch ( $column_name ) {
			case 'chp_needs_completion':
				$needs_completion = get_post_meta( $item->ID, '_needs_completion', true );
				if ( 'yes' === $needs_completion ) {
					return '<span style="color: red; font-weight: bold;">' . esc_html__( 'Yes', 'stackboost-for-supportcandy' ) . '</span>';
				} else {
					return '<span style="color: green;">' . esc_html__( 'No', 'stackboost-for-supportcandy' ) . '</span>';
				}
			default:
				return print_r( $item, true );
		}
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
	 * Add filter controls to the top of the table.
	 *
	 * @param string $which
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' === $which ) {
			$this->add_location_needs_completion_filter();
		}
	}

	/**
	 * Adds a dropdown filter for "Needs Completion" status.
	 */
	public function add_location_needs_completion_filter() {
		$current_filter = isset( $_REQUEST['chp_needs_completion_filter'] ) ? sanitize_text_field( $_REQUEST['chp_needs_completion_filter'] ) : 'all';
		?>
		<div class="alignleft actions">
			<label class="screen-reader-text" for="chp_needs_completion_filter"><?php esc_html_e( 'Filter by Completion Status', 'stackboost-for-supportcandy' ); ?></label>
			<select name="chp_needs_completion_filter" id="chp_needs_completion_filter">
				<option value="all" <?php selected( $current_filter, 'all' ); ?>><?php esc_html_e( 'All Completion Statuses', 'stackboost-for-supportcandy' ); ?></option>
				<option value="yes" <?php selected( $current_filter, 'yes' ); ?>><?php esc_html_e( 'Needs Completion', 'stackboost-for-supportcandy' ); ?></option>
				<option value="no" <?php selected( $current_filter, 'no' ); ?>><?php esc_html_e( 'Completed', 'stackboost-for-supportcandy' ); ?></option>
			</select>
			<?php submit_button( __( 'Filter' ), 'button', 'filter_action', false, array( 'id' => 'post-query-submit' ) ); ?>
		</div>
		<?php
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
			if ( 'chp_needs_completion' === $orderby ) {
				$args['meta_key'] = '_needs_completion';
				$args['orderby']  = 'meta_value';
			} else {
				$args['orderby'] = $orderby;
			}
			$args['order'] = $order;
		}

		$current_filter = isset( $_REQUEST['chp_needs_completion_filter'] ) ? sanitize_text_field( $_REQUEST['chp_needs_completion_filter'] ) : 'all';
		if ( 'all' !== $current_filter ) {
			$args['meta_query'] = array(
				array(
					'key'   => '_needs_completion',
					'value' => $current_filter,
				),
			);
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