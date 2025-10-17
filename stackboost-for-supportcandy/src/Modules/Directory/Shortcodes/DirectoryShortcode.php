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
	 * The custom post type slug for staff entries.
	 *
	 * @var string
	 */
	private $post_type;

	/**
	 * Constructor.
	 *
	 * @param CustomPostTypes $cpts An instance of the CustomPostTypes class.
	 */
	public function __construct( CustomPostTypes $cpts ) {
		$this->post_type = $cpts->post_type;
		add_shortcode( 'stackboost_directory', array( $this, 'render_directory_shortcode' ) );
	}

	/**
	 * Render the directory shortcode content.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output for the directory table.
	 */
	public function render_directory_shortcode( $atts ) {
		$full_table_args = array(
			'post_type'      => $this->post_type,
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'meta_query'     => array(
				array(
					'key'     => '_active',
					'value'   => 'Yes',
					'compare' => '=',
				),
			),
			'orderby'        => 'title',
			'order'          => 'ASC',
		);

		$full_directory_entries = new \WP_Query( $full_table_args );

		$directory_wordpress = \StackBoost\ForSupportCandy\Modules\Directory\WordPress::get_instance();
		$can_edit_entries = $directory_wordpress->can_user_edit();

		ob_start();
		?>
		<div class="stackboost-staff-directory-container">
			<div id="stackboost-full-directory-table-wrapper">
				<?php if ( $full_directory_entries->have_posts() ) : ?>
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
								'span'   => array(
									'class'          => true,
									'data-phone'     => true,
									'data-extension' => true,
									'title'          => true,
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

							while ( $full_directory_entries->have_posts() ) :
								$full_directory_entries->the_post();
								$post_id                 = get_the_ID();
								$office_phone            = get_post_meta( $post_id, '_office_phone', true );
								$extension               = get_post_meta( $post_id, '_extension', true );
								$mobile_phone            = get_post_meta( $post_id, '_mobile_phone', true );
								$department_program      = get_post_meta( $post_id, '_department_program', true );
								$job_title               = get_post_meta( $post_id, '_stackboost_staff_job_title', true );
								$email                   = get_post_meta( $post_id, '_email_address', true );
								$staff_permalink         = get_permalink( $post_id );
								$edit_post_link          = get_edit_post_link( $post_id );
								$phone_output_parts      = array();
								$searchable_phone_string = '';
								$copy_icon_svg           = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="16px" height="16px" style="vertical-align: middle; margin-left: 5px; cursor: pointer;"><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/></svg>';

								if ( ! empty( $office_phone ) ) {
									$phone_output_parts[]     = '<strong>' . esc_html__( 'Office', 'stackboost-for-supportcandy' ) . ':</strong> ' . $this->format_phone_number( $office_phone, $extension, $copy_icon_svg );
									$searchable_phone_string .= preg_replace( '/\D/', '', $office_phone ) . preg_replace( '/\D/', '', $extension );
								}

								if ( ! empty( $mobile_phone ) ) {
									$phone_output_parts[]     = '<strong>' . esc_html__( 'Mobile', 'stackboost-for-supportcandy' ) . ':</strong> ' . $this->format_phone_number( $mobile_phone, '', $copy_icon_svg );
									$searchable_phone_string .= preg_replace( '/\D/', '', $mobile_phone );
								}

								$formatted_phone_output = implode( '<br>', $phone_output_parts );
								?>
								<tr>
									<td>
										<a href="<?php echo esc_url( $staff_permalink ); ?>"><?php echo esc_html( get_the_title() ); ?></a>
										<?php if ( ! empty( $email ) ) : ?>
											<span class="stackboost-copy-email-icon"
												  data-email="<?php echo esc_attr( $email ); ?>"
												  title="<?php esc_attr_e( 'Click to copy email', 'stackboost-for-supportcandy' ); ?>">
												<?php echo $copy_icon_svg; ?>
											</span>
										<?php endif; ?>
										<?php if ( $can_edit_entries && $edit_post_link ) : ?>
											<a href="<?php echo esc_url( $edit_post_link ); ?>" title="<?php esc_attr_e( 'Edit this entry', 'stackboost-for-supportcandy' ); ?>" style="margin-left: 5px;">
												<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="16px" height="16px" style="vertical-align: middle; cursor: pointer;">
													<path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
												</svg>
											</a>
										<?php endif; ?>
									</td>
									<td data-search="<?php echo esc_attr( $searchable_phone_string ); ?>"><?php echo ! empty( $formatted_phone_output ) ? wp_kses( $formatted_phone_output, $allowed_html ) : '&mdash;'; ?></td>
									<td><?php echo esc_html( $department_program ); ?></td>
									<td><?php echo esc_html( $job_title ); ?></td>
								</tr>
							<?php endwhile; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p><?php esc_html_e( 'No directory entries found.', 'stackboost-for-supportcandy' ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
		wp_reset_postdata();

		return ob_get_clean();
	}

	/**
	 * Format a phone number for display and click-to-dial.
	 *
	 * @param string $phone The phone number.
	 * @param string $extension The extension, if any.
	 * @param string $copy_icon_svg The SVG for the copy icon.
	 * @return string The formatted HTML for the phone number.
	 */
	private function format_phone_number( string $phone, string $extension, string $copy_icon_svg ): string {
		$phone_digits = preg_replace( '/\D/', '', $phone );
		$display_phone = $phone;

		if ( 10 === strlen( $phone_digits ) ) {
			$display_phone = sprintf( '(%s) %s-%s',
				substr( $phone_digits, 0, 3 ),
				substr( $phone_digits, 3, 3 ),
				substr( $phone_digits, 6 )
			);
		}

		$tel_link = 'tel:' . $phone_digits;
		if ( ! empty( $extension ) ) {
			$tel_link .= ';ext=' . $extension;
			$display_phone .= ', ext. ' . esc_html( $extension );
		}

		$copy_span = sprintf(
			'<span class="stackboost-copy-phone-icon" data-phone="%s" data-extension="%s" title="%s">%s</span>',
			esc_attr( $phone_digits ),
			esc_attr( $extension ),
			esc_attr__( 'Click to copy phone number', 'stackboost-for-supportcandy' ),
			$copy_icon_svg
		);

		return sprintf( '<a href="%s">%s</a>%s', esc_url( $tel_link ), esc_html( $display_phone ), $copy_span );
	}
}