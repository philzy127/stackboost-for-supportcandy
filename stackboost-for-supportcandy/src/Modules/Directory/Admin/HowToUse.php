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
				<h2 class="hndle"><span><?php esc_html_e( 'Adding & Managing Staff, Locations, and Departments', 'stackboost-for-supportcandy' ); ?></span></h2>
				<div class="inside">
					<p><?php echo wp_kses_post( __( 'The <strong>Staff</strong>, <strong>Locations</strong>, and <strong>Departments</strong> tabs allow you to manage the core data of your directory. To add a new item to any of these categories, simply click the "Add New" button at the top of the respective table.', 'stackboost-for-supportcandy' ) ); ?></p>
					<p><?php echo wp_kses_post( __( '<strong>Note:</strong> The ability to add and edit these items is controlled by the roles defined in the <strong>Settings</strong> tab. By default, only Administrators can manage these listings.', 'stackboost-for-supportcandy' ) ); ?></p>
				</div>
			</div>

			<!-- Displaying the Directory -->
			<div class="postbox">
				<h2 class="hndle"><span><?php esc_html_e( 'Displaying the Directory on a Page', 'stackboost-for-supportcandy' ); ?></span></h2>
				<div class="inside">
					<p><?php esc_html_e( 'To display the staff directory on any page or post, edit the page and insert the following shortcode into a content block:', 'stackboost-for-supportcandy' ); ?></p>
					<p><code>[stackboost_directory]</code></p>
					<p><?php esc_html_e( 'This will render a searchable and sortable table of all active staff members. The phone numbers will be formatted for consistency and will be clickable for dialing on mobile devices. Users can also click the copy icons next to email addresses and phone numbers to easily copy them.', 'stackboost-for-supportcandy' ); ?></p>
				</div>
			</div>

			<!-- Settings -->
			<div class="postbox">
				<h2 class="hndle"><span><?php esc_html_e( 'Settings', 'stackboost-for-supportcandy' ); ?></span></h2>
				<div class="inside">
					<p><?php echo wp_kses_post( __( 'The <strong>Settings</strong> tab allows you to control who can interact with the Company Directory module.', 'stackboost-for-supportcandy' ) ); ?></p>
					<ul>
						<li><?php echo wp_kses_post( __( '<strong>Listing Display Mode:</strong> Choose how individual staff listings are displayed on the front-end directory. "Page View" opens the details on a new page, while "Modal View" opens them in a pop-up window on the same page.', 'stackboost-for-supportcandy' ) ); ?></li>
						<li><?php echo wp_kses_post( __( '<strong>Editing Permissions:</strong> Select the user roles that should be allowed to create, edit, and delete staff, locations, and departments.', 'stackboost-for-supportcandy' ) ); ?></li>
						<li><?php echo wp_kses_post( __( '<strong>Management Tab Access:</strong> Select the user roles that should have access to the powerful tools in the Management tab.', 'stackboost-for-supportcandy' ) ); ?></li>
					</ul>
					<p><?php echo wp_kses_post( __( '<em>Note: The Administrator role always has full access, regardless of these settings.</em>', 'stackboost-for-supportcandy' ) ); ?></p>
				</div>
			</div>

			<!-- Management Tools -->
			<div class="postbox">
				<h2 class="hndle"><span><?php esc_html_e( 'Management Tools', 'stackboost-for-supportcandy' ); ?></span></h2>
				<div class="inside">
					<?php // This section explains the bulk data operations. ?>
					<p><?php echo wp_kses_post( __( 'The <strong>Management</strong> tab provides tools for bulk data operations. Access to this tab is restricted by the roles you define in the Settings tab.', 'stackboost-for-supportcandy' ) ); ?></p>
					<ul>
						<li><?php echo wp_kses_post( __( '<strong>Import Staff from CSV:</strong> Bulk-upload new staff members from a CSV file. This tool only adds new entries and does not update existing ones.', 'stackboost-for-supportcandy' ) ); ?></li>
						<li><?php echo wp_kses_post( __( '<strong>Clear Directory Data:</strong> Deletes all <strong>staff members</strong> from the directory. This does not affect locations or departments.', 'stackboost-for-supportcandy' ) ); ?></li>
						<li><?php echo wp_kses_post( __( '<strong>Fresh Start:</strong> A nuclear option that permanently deletes <strong>all</strong> staff, locations, and departments, and clears the trash. This action requires double confirmation and cannot be undone.', 'stackboost-for-supportcandy' ) ); ?></li>
					</ul>
				</div>
			</div>

			<!-- Testing Tab -->
			<div class="postbox">
				<h2 class="hndle"><span><?php esc_html_e( 'Testing', 'stackboost-for-supportcandy' ); ?></span></h2>
				<div class="inside">
					<p><?php echo wp_kses_post( __( 'The <strong>Testing</strong> tab provides a simple interface to directly test the `DirectoryService`. This is useful for developers or for troubleshooting to confirm that the service is fetching data correctly.', 'stackboost-for-supportcandy' ) ); ?></p>
					<ul>
						<li><?php echo wp_kses_post( __( '<strong>Find Employee Profile:</strong> Enter a WordPress User ID or email address to see if the service can find the corresponding directory profile ID.', 'stackboost-for-supportcandy' ) ); ?></li>
						<li><?php echo wp_kses_post( __( '<strong>Retrieve Employee Data:</strong> Enter a directory profile ID (the post ID of the staff member) to retrieve the full, structured data object for that employee.', 'stackboost-for-supportcandy' ) ); ?></li>
					</ul>
				</div>
			</div>

		</div>
		<?php
	}
}