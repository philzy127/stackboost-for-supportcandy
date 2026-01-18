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
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_filter = isset( $_REQUEST['stackboost_active_filter'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['stackboost_active_filter'] ) ) : 'yes';

		$columns = array(
			'cb'                   => '<input type="checkbox" />',
			'title'                => __( 'Staff Name', 'stackboost-for-supportcandy' ),
			'stackboost_contact_phone'    => __( 'Phone', 'stackboost-for-supportcandy' ),
			'stackboost_email_address'    => __( 'Email', 'stackboost-for-supportcandy' ),
			'sb_department_program' => __( 'Department / Program', 'stackboost-for-supportcandy' ),
			'stackboost_job_title'        => __( 'Job Title', 'stackboost-for-supportcandy' ),
		);

		if ( 'all' === $current_filter ) {
			$columns['stackboost_active_status'] = __( 'Active', 'stackboost-for-supportcandy' );
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
			'sb_department_program' => array( 'sb_department_program', false ),
			'stackboost_job_title'        => array( 'stackboost_job_title', false ),
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
			case 'stackboost_contact_phone':
				$output       = '';
				$office_phone = get_post_meta( $item->ID, '_office_phone', true );
				$extension    = get_post_meta( $item->ID, '_extension', true );
				$mobile_phone = get_post_meta( $item->ID, '_mobile_phone', true );

				$directory_service = \StackBoost\ForSupportCandy\Services\DirectoryService::get_instance();

				if ( ! empty( $office_phone ) ) {
					$formatted_office = $directory_service->format_phone_number_string( $office_phone );
					$output .= '<span class="dashicons dashicons-building" style="font-size: 16px; width: 16px; height: 16px; vertical-align: middle; margin-right: 5px; color: #555;" title="' . esc_attr__( 'Office', 'stackboost-for-supportcandy' ) . '"></span>' . esc_html( $formatted_office );
					if ( ! empty( $extension ) ) {
						$output .= ' <span style="color: #777; font-size: 0.9em;">' . esc_html__( 'ext.', 'stackboost-for-supportcandy' ) . ' ' . esc_html( $extension ) . '</span>';
					}
				}

				if ( ! empty( $mobile_phone ) ) {
					if ( ! empty( $output ) ) {
						$output .= '<br>';
					}
					$formatted_mobile = $directory_service->format_phone_number_string( $mobile_phone );
					$output .= '<span class="dashicons dashicons-smartphone" style="font-size: 16px; width: 16px; height: 16px; vertical-align: middle; margin-right: 5px; color: #555;" title="' . esc_attr__( 'Mobile', 'stackboost-for-supportcandy' ) . '"></span>' . esc_html( $formatted_mobile );
				}

				return ! empty( $output ) ? $output : '&mdash;';
			case 'stackboost_email_address':
				$email = get_post_meta( $item->ID, '_email_address', true );
				if ( ! empty( $email ) ) {
					return '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>';
				} else {
					return '&mdash;';
				}
			case 'sb_department_program':
				return esc_html( get_post_meta( $item->ID, '_department_program', true ) );
			case 'stackboost_job_title':
				return esc_html( get_post_meta( $item->ID, '_stackboost_staff_job_title', true ) );
			case 'stackboost_active_status':
				$active_status = get_post_meta( $item->ID, '_active', true );
				if ( 'Yes' === $active_status ) {
					return '<span style="color: green;">' . esc_html__( 'Yes', 'stackboost-for-supportcandy' ) . '</span>';
				} else {
					return '<span style="color: red;">' . esc_html__( 'No', 'stackboost-for-supportcandy' ) . '</span>';
				}
			default:
				// Removed print_r
				return '';
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
			'view'   => sprintf( '<a href="%s">%s</a>', get_permalink( $item->ID ), __( 'View', 'stackboost-for-supportcandy' ) ),
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_status = isset( $_REQUEST['post_status'] ) ? sanitize_key( wp_unslash( $_REQUEST['post_status'] ) ) : '';

		if ( 'trash' === $post_status ) {
			$actions['untrash'] = sprintf( '<a href="%s">%s</a>', wp_nonce_url( admin_url( 'admin.php?page=stackboost-directory&tab=staff&action=untrash&post=' . $item->ID ), 'untrash-post_' . $item->ID ), __( 'Restore', 'stackboost-for-supportcandy' ) );
			$actions['delete']  = sprintf( '<a href="%s">%s</a>', wp_nonce_url( admin_url( 'admin.php?page=stackboost-directory&tab=staff&action=delete&post=' . $item->ID ), 'delete-post_' . $item->ID ), __( 'Delete Permanently', 'stackboost-for-supportcandy' ) );
		} else {
			$actions['trash']   = sprintf( '<a href="%s" class="submitdelete">%s</a>', wp_nonce_url( admin_url( 'admin.php?page=stackboost-directory&tab=staff&action=trash&post=' . $item->ID ), 'trash-post_' . $item->ID ), __( 'Trash', 'stackboost-for-supportcandy' ) );
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

		$all_class = ( 'all' === $post_status ) ? ' class="current"' : '';
		$status_links['all'] = "<a href='admin.php?page=stackboost-directory&tab=staff'{$all_class}>All <span class='count'>(" . ( $num_posts->publish + $num_posts->draft ) . ')</span></a>';

		if ( ! empty( $num_posts->trash ) ) {
			$trash_class = ( 'trash' === $post_status ) ? ' class="current"' : '';
			$status_links['trash'] = "<a href='admin.php?page=stackboost-directory&tab=staff&post_status=trash'{$trash_class}>Trash <span class='count'>(" . $num_posts->trash . ')</span></a>';
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
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_status = isset( $_REQUEST['post_status'] ) ? sanitize_key( wp_unslash( $_REQUEST['post_status'] ) ) : '';
		if ( 'trash' === $post_status ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_filter = isset( $_REQUEST['stackboost_active_filter'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['stackboost_active_filter'] ) ) : 'yes';
		?>
		<div class="alignleft actions">
			<label class="screen-reader-text" for="stackboost_active_filter"><?php esc_html_e( 'Filter by Active Status', 'stackboost-for-supportcandy' ); ?></label>
			<select name="stackboost_active_filter" id="stackboost_active_filter">
				<option value="all" <?php selected( $current_filter, 'all' ); ?>><?php esc_html_e( 'All Entries', 'stackboost-for-supportcandy' ); ?></option>
				<option value="yes" <?php selected( $current_filter, 'yes' ); ?>><?php esc_html_e( 'Active', 'stackboost-for-supportcandy' ); ?></option>
				<option value="no" <?php selected( $current_filter, 'no' ); ?>><?php esc_html_e( 'Inactive', 'stackboost-for-supportcandy' ); ?></option>
			</select>
			<?php submit_button( __( 'Filter', 'stackboost-for-supportcandy' ), 'button', 'filter_action', false, array( 'id' => 'post-query-submit' ) ); ?>
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
			if ( 'sb_department_program' === $orderby ) {
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				$args['meta_key'] = '_department_program';
				$args['orderby']  = 'meta_value';
			} elseif ( 'stackboost_job_title' === $orderby ) {
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				$args['meta_key'] = '_stackboost_staff_job_title';
				$args['orderby']  = 'meta_value';
			} else {
				$args['orderby'] = $orderby;
			}
			$args['order'] = $order;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_filter = isset( $_REQUEST['stackboost_active_filter'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['stackboost_active_filter'] ) ) : 'yes';
		if ( 'all' !== $current_filter ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			$args['meta_query'] = array(
				array(
					'key'   => '_active',
					'value' => 'yes' === $current_filter ? 'Yes' : 'No',
				),
			);
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
