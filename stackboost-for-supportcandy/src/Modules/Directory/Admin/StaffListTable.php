<?php
/**
 * StackBoost Staff List Table.
 *
 * This file contains the class for the Staff List Table, which extends
 * WP_List_Table to display staff directory entries in the admin area.
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
 * StaffListTable Class
 *
 * Creates a WP_List_Table for displaying staff entries.
 */
class StaffListTable extends \WP_List_Table {

	/**
	 * The custom post type slug for staff entries.
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
				'singular' => __( 'Staff Entry', 'stackboost-for-supportcandy' ),
				'plural'   => __( 'Staff Entries', 'stackboost-for-supportcandy' ),
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
		$current_filter = isset( $_REQUEST['chp_active_filter'] ) ? sanitize_text_field( $_REQUEST['chp_active_filter'] ) : 'yes';

		$columns = array(
			'cb'                   => '<input type="checkbox" />',
			'title'                => __( 'Staff Name', 'stackboost-for-supportcandy' ),
			'chp_contact_phone'    => __( 'Phone', 'stackboost-for-supportcandy' ),
			'chp_email_address'    => __( 'Email', 'stackboost-for-supportcandy' ),
			'chp_department_program' => __( 'Department / Program', 'stackboost-for-supportcandy' ),
			'chp_job_title'        => __( 'Job Title', 'stackboost-for-supportcandy' ),
		);

		if ( 'all' === $current_filter ) {
			$columns['chp_active_status'] = __( 'Active', 'stackboost-for-supportcandy' );
		}

		return $columns;
	}

	/**
	 * Get a list of sortable columns.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'title'                => array( 'title', false ),
			'chp_department_program' => array( 'chp_department_program', false ),
			'chp_job_title'        => array( 'chp_job_title', false ),
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
			case 'chp_contact_phone':
				$output       = '';
				$office_phone = get_post_meta( $item->ID, '_office_phone', true );
				$extension    = get_post_meta( $item->ID, '_extension', true );
				$mobile_phone = get_post_meta( $item->ID, '_mobile_phone', true );

				if ( ! empty( $office_phone ) ) {
					$output .= '<strong>' . esc_html__( 'Office', 'stackboost-for-supportcandy' ) . ':</strong> ' . esc_html( $office_phone );
					if ( ! empty( $extension ) ) {
						$output .= ' ext. ' . esc_html( $extension );
					}
				}

				if ( ! empty( $mobile_phone ) ) {
					if ( ! empty( $output ) ) {
						$output .= '<br>';
					}
					$output .= '<strong>' . esc_html__( 'Mobile', 'stackboost-for-supportcandy' ) . ':</strong> ' . esc_html( $mobile_phone );
				}

				return ! empty( $output ) ? $output : '&mdash;';
			case 'chp_email_address':
				$email = get_post_meta( $item->ID, '_email_address', true );
				if ( ! empty( $email ) ) {
					return '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>';
				} else {
					return '&mdash;';
				}
			case 'chp_department_program':
				return esc_html( get_post_meta( $item->ID, '_department_program', true ) );
			case 'chp_job_title':
				return esc_html( get_post_meta( $item->ID, '_chp_staff_job_title', true ) );
			case 'chp_active_status':
				$active_status = get_post_meta( $item->ID, '_active', true );
				if ( 'Yes' === $active_status ) {
					return '<span style="color: green;">' . esc_html__( 'Yes', 'stackboost-for-supportcandy' ) . '</span>';
				} else {
					return '<span style="color: red;">' . esc_html__( 'No', 'stackboost-for-supportcandy' ) . '</span>';
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
			'view'   => sprintf( '<a href="%s">%s</a>', get_permalink( $item->ID ), __( 'View', 'stackboost-for-supportcandy' ) ),
		);

		return sprintf( '<strong><a class="row-title" href="%s">%s</a></strong>%s', get_edit_post_link( $item->ID ), $item->post_title, $this->row_actions( $actions ) );
	}

	/**
	 * Get bulk actions.
	 *
	 * @return array
	 */
	protected function get_bulk_actions() {
		$actions = array();
		if ( 'trash' === ( $_REQUEST['post_status'] ?? '' ) ) {
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
		$class        = '';
		$post_status  = $_REQUEST['post_status'] ?? '';

		$status_links['all'] = "<a href='admin.php?page=stackboost-directory&tab=staff'>All <span class='count'>(" . sum_object_property( $num_posts, 'publish' ) . ')</span></a>';

		if ( ! empty( $num_posts->trash ) ) {
			$status_links['trash'] = "<a href='admin.php?page=stackboost-directory&tab=staff&post_status=trash'>Trash <span class='count'>(" . $num_posts->trash . ')</span></a>';
		}

		return $status_links;
	}

	/**
	 * Add filter controls to the top of the table.
	 *
	 * @param string $which
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' === $which ) {
			$this->add_active_status_filter();
		}
	}

	/**
	 * Adds a dropdown filter for active status.
	 */
	public function add_active_status_filter() {
		$current_filter = isset( $_REQUEST['chp_active_filter'] ) ? sanitize_text_field( $_REQUEST['chp_active_filter'] ) : 'yes';
		?>
		<div class="alignleft actions">
			<label class="screen-reader-text" for="chp_active_filter"><?php esc_html_e( 'Filter by Active Status', 'stackboost-for-supportcandy' ); ?></label>
			<select name="chp_active_filter" id="chp_active_filter">
				<option value="all" <?php selected( $current_filter, 'all' ); ?>><?php esc_html_e( 'All Entries', 'stackboost-for-supportcandy' ); ?></option>
				<option value="yes" <?php selected( $current_filter, 'yes' ); ?>><?php esc_html_e( 'Active', 'stackboost-for-supportcandy' ); ?></option>
				<option value="no" <?php selected( $current_filter, 'no' ); ?>><?php esc_html_e( 'Inactive', 'stackboost-for-supportcandy' ); ?></option>
			</select>
			<?php submit_button( __( 'Filter' ), 'button', 'filter_action', false, array( 'id' => 'post-query-submit' ) ); ?>
		</div>
		<?php
	}

	/**
	 * Process bulk actions.
	 */
	public function process_bulk_action() {
		$action = $this->current_action();
		if ( ! $action ) {
			return;
		}
		$post_ids = isset( $_REQUEST['post'] ) ? wp_parse_id_list( (array) $_REQUEST['post'] ) : array();
		if ( empty( $post_ids ) ) {
			return;
		}

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

		$args = array(
			'post_type'      => $this->post_type,
			'posts_per_page' => $per_page,
			'offset'         => $offset,
			'post_status'    => ( isset( $_REQUEST['post_status'] ) ? sanitize_key( $_REQUEST['post_status'] ) : 'publish' ),
		);

		$orderby = ( ! empty( $_REQUEST['orderby'] ) ) ? sanitize_sql_orderby( $_REQUEST['orderby'] ) : 'title';
		$order   = ( ! empty( $_REQUEST['order'] ) ) ? sanitize_key( $_REQUEST['order'] ) : 'asc';

		if ( ! empty( $orderby ) & ! empty( $order ) ) {
			if ( 'chp_department_program' === $orderby ) {
				$args['meta_key'] = '_department_program';
				$args['orderby']  = 'meta_value';
			} elseif ( 'chp_job_title' === $orderby ) {
				$args['meta_key'] = '_chp_staff_job_title';
				$args['orderby']  = 'meta_value';
			} else {
				$args['orderby'] = $orderby;
			}
			$args['order'] = $order;
		}

		$current_filter = isset( $_REQUEST['chp_active_filter'] ) ? sanitize_text_field( $_REQUEST['chp_active_filter'] ) : 'yes';
		if ( 'all' !== $current_filter ) {
			$args['meta_query'] = array(
				array(
					'key'   => '_active',
					'value' => 'yes' === $current_filter ? 'Yes' : 'No',
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