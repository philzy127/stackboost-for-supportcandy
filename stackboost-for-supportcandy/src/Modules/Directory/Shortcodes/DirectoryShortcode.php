<?php
/**
 * StackBoost Directory Shortcode.
 *
 * This file defines the shortcode for displaying the Company Directory
 * on the front end. It is a migration of the shortcode from the
 * standalone plugin, adapted for the StackBoost framework.
 *
 * @package StackBoost
 * @subpackage Modules\Directory\Shortcodes
 */

namespace StackBoost\ForSupportCandy\Modules\Directory\Shortcodes;

use StackBoost\ForSupportCandy\Services\DirectoryService;
use StackBoost\ForSupportCandy\Modules\Directory\Data\CustomPostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * DirectoryShortcode Class
 *
 * Handles the [stackboost_directory] shortcode.
 */
class DirectoryShortcode {

	/**
	 * Constructor.
	 *
	 * @param CustomPostTypes $cpts An instance of the CustomPostTypes class.
	 */
	public function __construct( CustomPostTypes $cpts ) {
		add_shortcode( 'stackboost_directory', array( $this, 'render_directory_shortcode' ) );
	}

	/**
	 * Render the directory shortcode content.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output for the directory table.
	 */
	public function render_directory_shortcode( $atts ) {
		$directory_service = DirectoryService::get_instance();
		$employees         = $directory_service->get_all_active_employees_for_shortcode();

		$directory_wordpress = \StackBoost\ForSupportCandy\Modules\Directory\WordPress::get_instance();
		$can_edit_entries    = $directory_wordpress->can_user_edit();

		$settings             = get_option( \StackBoost\ForSupportCandy\Modules\Directory\Admin\Settings::OPTION_NAME, array() );
		$listing_display_mode = $settings['listing_display_mode'] ?? 'page';

		ob_start();
		?>
		<div class="stackboost-staff-directory-container">
			<div id="stackboost-full-directory-table-wrapper">
				<?php if ( ! empty( $employees ) ) : ?>
					<table id="stackboostStaffDirectoryTable" class="display stackboost-staff-directory-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Name', 'stackboost-for-supportcandy' ); ?></th>
								<th><?php esc_html_e( 'Phone', 'stackboost-for-supportcandy' ); ?></th>
								<th><?php esc_html_e( 'Department / Program', 'stackboost-for-supportcandy' ); ?></th>
								<th><?php esc_html_e( 'Title', 'stackboost-for-supportcandy' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							$allowed_html = array(
								'strong' => array(),
								'br'     => array(),
								'a'      => array(
									'href'            => true,
									'class'           => true,
									'data-post-id'    => true,
									'data-wp-nonce'   => true,
								),
								'span'   => array(
									'class'          => true,
									'data-phone'     => true,
									'data-extension' => true,
									'title'          => true,
									'data-email'     => true,
									'style'          => true, // Allow style for inline styling of icons
								),
								'svg'    => array(
									'class'   => true,
									'xmlns'   => true,
									'width'   => true,
									'height'  => true,
									'viewbox' => true,
									'fill'    => true,
									'style'   => true,
								),
								'path'   => array(
									'd' => true,
								),
								'td'     => array(
									'data-search' => true,
								),
							);

							foreach ( $employees as $employee ) :
								$searchable_phone_string = preg_replace( '/\D/', '', $employee->office_phone . $employee->extension . $employee->mobile_phone );
								$formatted_phone_output  = $directory_service->get_formatted_phone_numbers_html( $employee );
								$copy_icon_svg           = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="16px" height="16px" style="vertical-align: middle; margin-left: 5px; cursor: pointer;"><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/></svg>';
								?>
								<tr>
									<td>
										<?php if ( 'modal' === $listing_display_mode ) : ?>
											<a href="#" class="stackboost-modal-trigger" data-post-id="<?php echo esc_attr( $employee->id ); ?>"><?php echo esc_html( $employee->name ); ?></a>
										<?php else : ?>
											<a href="<?php echo esc_url( $employee->permalink ); ?>"><?php echo esc_html( $employee->name ); ?></a>
										<?php endif; ?>

										<?php if ( ! empty( $employee->email ) ) : ?>
											<span class="stackboost-copy-email-icon"
												  data-email="<?php echo esc_attr( $employee->email ); ?>"
												  title="<?php esc_attr_e( 'Click to copy email', 'stackboost-for-supportcandy' ); ?>">
												<?php echo wp_kses( $copy_icon_svg, $allowed_html ); ?>
											</span>
										<?php endif; ?>
										<?php if ( $can_edit_entries && $employee->edit_post_link ) : ?>
											<a href="<?php echo esc_url( $employee->edit_post_link ); ?>" title="<?php esc_attr_e( 'Edit this entry', 'stackboost-for-supportcandy' ); ?>" style="margin-left: 5px;">
												<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="16px" height="16px" style="vertical-align: middle; cursor: pointer;">
													<path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
												</svg>
											</a>
										<?php endif; ?>
									</td>
									<td data-search="<?php echo esc_attr( $searchable_phone_string ); ?>"><?php echo ! empty( $formatted_phone_output ) ? wp_kses( $formatted_phone_output, $allowed_html ) : '&mdash;'; ?></td>
									<td><?php echo esc_html( $employee->department_program ); ?></td>
									<td><?php echo esc_html( $employee->job_title ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p><?php esc_html_e( 'No directory entries found.', 'stackboost-for-supportcandy' ); ?></p>
				<?php endif; ?>
			</div>
			<?php if ( 'modal' === $listing_display_mode ) : ?>
				<div id="stackboost-staff-modal" class="stackboost-modal" style="display: none;">
					<div class="stackboost-modal-content">
						<span class="stackboost-modal-close">&times;</span>
						<div class="stackboost-modal-body">
							<!-- Content will be loaded here via AJAX -->
						</div>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

}