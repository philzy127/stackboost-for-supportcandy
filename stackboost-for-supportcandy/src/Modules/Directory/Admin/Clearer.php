<?php
/**
 * StackBoost Company Directory Clearer.
 *
 * This file handles the functionality to clear all staff directory data.
 * It's a migration of the clearer from the standalone plugin, adapted for
 * the StackBoost framework.
 *
 * @package StackBoost
 * @subpackage Modules\Directory\Admin
 */

namespace StackBoost\ForSupportCandy\Modules\Directory\Admin;

use StackBoost\ForSupportCandy\Modules\Directory\Data\CustomPostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Clearer Class
 *
 * Handles the functionality to clear all staff directory data.
 */
class Clearer {

	/**
	 * The custom post type slug.
	 *
	 * @var string
	 */
	private static $post_type_static;

	/**
	 * Constructor.
	 */
	public function __construct( CustomPostTypes $cpts ) {
		self::$post_type_static = $cpts->post_type;
		add_action( 'wp_ajax_stackboost_directory_clear_db', array( __CLASS__, 'handle_clear_db_action' ) );
	}

	/**
	 * Render the clear database page.
	 */
	public static function render_clear_db_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Clear StackBoost Company Directory Data', 'stackboost-for-supportcandy' ); ?></h1>
			<p><?php esc_html_e( 'This action will permanently delete ALL entries from the Company Directory. This cannot be undone.', 'stackboost-for-supportcandy' ); ?></p>
			<p><strong><?php esc_html_e( 'Are you sure you want to proceed?', 'stackboost-for-supportcandy' ); ?></strong></p>
			<button id="stackboost-clear-db-button" class="button button-danger"><?php esc_html_e( 'Delete All Staff Directory Data', 'stackboost-for-supportcandy' ); ?></button>
			<div id="stackboost-clear-db-message" style="margin-top: 20px;"></div>
		</div>

		<div id="stackboost-clear-db-confirm-modal" class="stackboost-modal">
			<div class="stackboost-modal-content">
				<span class="stackboost-modal-close-button">&times;</span>
				<h2><?php esc_html_e( 'Confirm Data Deletion', 'stackboost-for-supportcandy' ); ?></h2>
				<p><?php esc_html_e( 'This will permanently delete all Company Directory entries. This action cannot be undone.', 'stackboost-for-supportcandy' ); ?></p>
				<p><?php esc_html_e( 'Type "DELETE" to confirm:', 'stackboost-for-supportcandy' ); ?></p>
				<input type="text" id="stackboost-modal-confirm-input" style="width: 80%; padding: 8px; margin-bottom: 15px;" />
				<button id="stackboost-modal-confirm-yes" class="button button-danger" disabled><?php esc_html_e( 'Yes, Delete All Data', 'stackboost-for-supportcandy' ); ?></button>
				<button id="stackboost-modal-confirm-no" class="button button-secondary"><?php esc_html_e( 'No, Cancel', 'stackboost-for-supportcandy' ); ?></button>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle the AJAX request to clear database.
	 */
	public static function handle_clear_db_action() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have sufficient permissions to perform this action.', 'stackboost-for-supportcandy' ) ) );
		}

		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'stackboost_directory_clear_db_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'stackboost-for-supportcandy' ) ) );
		}

		$args = array(
			'post_type'      => self::$post_type_static,
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'post_status'    => array( 'publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit', 'trash' ),
		);

		$query         = new \WP_Query( $args );
		$deleted_count = 0;

		if ( $query->have_posts() ) {
			foreach ( $query->posts as $post_id ) {
				if ( wp_delete_post( $post_id, true ) ) {
					$deleted_count++;
				}
			}
		}

		if ( $deleted_count > 0 ) {
			wp_send_json_success( array( 'message' => sprintf( esc_html__( '%d entries deleted successfully.', 'stackboost-for-supportcandy' ), $deleted_count ) ) );
		} else {
			wp_send_json_success( array( 'message' => __( 'No staff directory entries found to delete.', 'stackboost-for-supportcandy' ) ) );
		}
	}
}