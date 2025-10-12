<?php
/**
 * StackBoost Company Directory How To Use.
 *
 * This file provides the "How to Use" page for the Company Directory module.
 * It's a migration of the documentation from the standalone plugin, adapted
 * for the StackBoost framework.
 *
 * @package StackBoost
 * @subpackage Modules\Directory\Admin
 */

namespace StackBoost\ForSupportCandy\Modules\Directory\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * HowToUse Class
 *
 * Renders the 'How to Use' page for the module.
 */
class HowToUse {

	/**
	 * Renders the 'How to Use' admin page.
	 */
	public static function render_how_to_use_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'How to Use StackBoost Company Directory', 'stackboost-for-supportcandy' ); ?></h1>
			<p><?php esc_html_e( 'This guide provides detailed instructions for managing and displaying your staff directory.', 'stackboost-for-supportcandy' ); ?></p>

			<!-- Adding a New Staff Member -->
			<div class="postbox">
				<h2 class="hndle"><span><?php esc_html_e( 'Adding a New Staff Member', 'stackboost-for-supportcandy' ); ?></span></h2>
				<div class="inside">
					<p><?php esc_html_e( 'Follow these steps to add a new person to the directory:', 'stackboost-for-supportcandy' ); ?></p>
					<ol>
						<li><?php echo wp_kses_post( __( 'In the left-hand admin menu, navigate to <strong>Company Directory</strong>.', 'stackboost-for-supportcandy' ) ); ?></li>
						<li><?php echo wp_kses_post( __( 'Click the <strong>Staff</strong> tab, then click the <strong>Add New</strong> button.', 'stackboost-for-supportcandy' ) ); ?></li>
						<li><?php esc_html_e( 'Enter the staff member\'s full name in the top title field.', 'stackboost-for-supportcandy' ); ?></li>
						<li><?php echo wp_kses_post( __( 'Fill out the fields in the <strong>Staff Details</strong> section below.', 'stackboost-for-supportcandy' ) ); ?></li>
						<li><?php echo wp_kses_post( __( 'To add a photo, use the <strong>Featured Image</strong> box on the right-hand side.', 'stackboost-for-supportcandy' ) ); ?></li>
						<li><?php echo wp_kses_post( __( 'When finished, click the <strong>Publish</strong> button.', 'stackboost-for-supportcandy' ) ); ?></li>
					</ol>
				</div>
			</div>

			<!-- Uploading a Photo -->
			<div class="postbox">
				<h2 class="hndle"><span><?php esc_html_e( 'Uploading a Staff Photo', 'stackboost-for-supportcandy' ); ?></span></h2>
				<div class="inside">
					<p><?php esc_html_e( 'The staff member\'s photo is handled by the standard WordPress "Featured Image" functionality.', 'stackboost-for-supportcandy' ); ?></p>
					<ol>
						<li><?php echo wp_kses_post( __( 'On the "Add New" or "Edit Staff" screen, find the <strong>Featured Image</strong> meta box on the right side of the page.', 'stackboost-for-supportcandy' ) ); ?></li>
						<li><?php esc_html_e( 'Click the "Set featured image" link.', 'stackboost-for-supportcandy' ); ?></li>
						<li><?php esc_html_e( 'Upload a new image or select an existing one from the Media Library.', 'stackboost-for-supportcandy' ); ?></li>
						<li><?php esc_html_e( 'Click the "Set featured image" button in the media library window.', 'stackboost-for-supportcandy' ); ?></li>
					</ol>
					<p><?php echo wp_kses_post( __( '<em>For best results, use a square image or one with a portrait orientation.</em>', 'stackboost-for-supportcandy' ) ); ?></p>
				</div>
			</div>

			<!-- Field Explanations -->
			<div class="postbox">
				<h2 class="hndle"><span><?php esc_html_e( 'Staff Details Explained', 'stackboost-for-supportcandy' ); ?></span></h2>
				<div class="inside">
					<p><?php esc_html_e( 'Here is a description of each field available for a staff member:', 'stackboost-for-supportcandy' ); ?></p>
					<ul>
						<li><?php echo wp_kses_post( __( '<strong>Office Phone:</strong> The primary office phone number.', 'stackboost-for-supportcandy' ) ); ?></li>
						<li><?php echo wp_kses_post( __( '<strong>Extension:</strong> The phone extension, if applicable.', 'stackboost-for-supportcandy' ) ); ?></li>
						<li><?php echo wp_kses_post( __( '<strong>Mobile Phone:</strong> The staff member\'s mobile number (optional).', 'stackboost-for-supportcandy' ) ); ?></li>
						<li><?php echo wp_kses_post( __( '<strong>Location:</strong> Select the primary work location from the dropdown. This may pre-fill other fields like phone number.', 'stackboost-for-supportcandy' ) ); ?></li>
						<li><?php echo wp_kses_post( __( '<strong>Room #:</strong> The specific room or office number.', 'stackboost-for-supportcandy' ) ); ?></li>
						<li><?php echo wp_kses_post( __( '<strong>Department / Program:</strong> Select the relevant department or program from the dropdown.', 'stackboost-for-supportcandy' ) ); ?></li>
						<li><?php echo wp_kses_post( __( '<strong>Title:</strong> The official job title.', 'stackboost-for-supportcandy' ) ); ?></li>
						<li><?php echo wp_kses_post( __( '<strong>Email Address:</strong> The staff member\'s email address.', 'stackboost-for-supportcandy' ) ); ?></li>
						<li><?php echo wp_kses_post( __( '<strong>Active:</strong> (Only on Edit screen) Check this box if the staff member is currently active. Uncheck to hide them from the public directory.', 'stackboost-for-supportcandy' ) ); ?></li>
						<li><?php echo wp_kses_post( __( '<strong>Active as of:</strong> The date the staff member becomes active. New entries default to the current date. The entry will not appear on the public directory until this date.', 'stackboost-for-supportcandy' ) ); ?></li>
						<li><?php echo wp_kses_post( __( '<strong>Inactive as of:</strong> Optional. The date the staff member becomes inactive. After this date, the entry will be hidden from the public directory.', 'stackboost-for-supportcandy' ) ); ?></li>
					</ul>
					<p><?php esc_html_e( 'The following fields are informational and are updated automatically:', 'stackboost-for-supportcandy' ); ?></p>
					<ul>
						<li><?php echo wp_kses_post( __( '<strong>Unique ID:</strong> A unique identifier automatically assigned to each entry.', 'stackboost-for-supportcandy' ) ); ?></li>
						<li><?php echo wp_kses_post( __( '<strong>Last Updated By:</strong> The user who last saved the entry.', 'stackboost-for-supportcandy' ) ); ?></li>
						<li><?php echo wp_kses_post( __( '<strong>Last Updated On:</strong> The date and time of the last update.', 'stackboost-for-supportcandy' ) ); ?></li>
					</ul>
				</div>
			</div>

			<!-- Displaying the Directory -->
			<div class="postbox">
				<h2 class="hndle"><span><?php esc_html_e( 'Displaying the Directory on a Page', 'stackboost-for-supportcandy' ); ?></span></h2>
				<div class="inside">
					<p><?php esc_html_e( 'To display the staff directory on any page or post, edit the page and insert the following shortcode into a content block:', 'stackboost-for-supportcandy' ); ?></p>
					<p><code>[stackboost_directory]</code></p>
					<p><?php esc_html_e( 'This will render a searchable and sortable table of all active staff members.', 'stackboost-for-supportcandy' ); ?></p>
				</div>
			</div>

			<!-- Advanced Tools -->
			<div class="postbox">
				<h2 class="hndle"><span><?php esc_html_e( 'Advanced Tools', 'stackboost-for-supportcandy' ); ?></span></h2>
				<div class="inside">
					<p><?php echo wp_kses_post( __( '<strong>Import:</strong> Navigate to "Company Directory" > "Import" to bulk-upload new staff members from a CSV file. Note that this tool only adds new entries and does not update existing ones.', 'stackboost-for-supportcandy' ) ); ?></p>
					<p><?php echo wp_kses_post( __( '<strong>Clear Data:</strong> The "Clear Data" tool (under "Company Directory" > "Clear Data") allows you to remove all staff directory entries from the database. <strong>Warning: This action is permanent and cannot be undone.</strong>', 'stackboost-for-supportcandy' ) ); ?></p>
				</div>
			</div>

		</div>
		<?php
	}
}