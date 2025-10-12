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
		add_shortcode( 'chp_staff_directory', array( $this, 'render_directory_shortcode' ) ); // For backward compatibility.
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

		$current_user     = wp_get_current_user();
		$user_roles       = (array) $current_user->roles;
		$can_edit_entries = in_array( 'it_technician', $user_roles, true ) || in_array( 'administrator', $user_roles, true );

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
							);

							while ( $full_directory_entries->have_posts() ) :
								$full_directory_entries->the_post();
								$post_id              = get_the_ID();
								$office_phone         = get_post_meta( $post_id, '_office_phone', true );
								$extension            = get_post_meta( $post_id, '_extension', true );
								$mobile_phone         = get_post_meta( $post_id, '_mobile_phone', true );
								$department_program   = get_post_meta( $post_id, '_department_program', true );
								$job_title            = get_post_meta( $post_id, '_chp_staff_job_title', true );
								$email                = get_post_meta( $post_id, '_email_address', true );
								$staff_permalink      = get_permalink( $post_id );
								$edit_post_link       = get_edit_post_link( $post_id );
								$phone_output_parts   = array();
								$copy_icon_svg        = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="16px" height="16px" style="vertical-align: middle; margin-left: 5px; cursor: pointer;"><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/></svg>';

								if ( ! empty( $office_phone ) ) {
									$office_phone_digits = preg_replace( '/\D/', '', $office_phone );
									$office_display      = esc_html( $office_phone );
									if ( ! empty( $extension ) ) {
										$office_display .= ' ext. ' . esc_html( $extension );
									}
									$copy_span            = sprintf(
										'<span class="stackboost-copy-phone-icon" data-phone="%s" data-extension="%s" title="%s">%s</span>',
										esc_attr( $office_phone_digits ),
										esc_attr( $extension ),
										esc_attr__( 'Click to copy phone number', 'stackboost-for-supportcandy' ),
										$copy_icon_svg
									);
									$phone_output_parts[] = '<strong>' . esc_html__( 'Office', 'stackboost-for-supportcandy' ) . ':</strong> ' . $office_display . $copy_span;
								}

								if ( ! empty( $mobile_phone ) ) {
									$mobile_phone_digits = preg_replace( '/\D/', '', $mobile_phone );
									$mobile_display      = esc_html( $mobile_phone );
									$copy_span           = sprintf(
										'<span class="stackboost-copy-phone-icon" data-phone="%s" data-extension="" title="%s">%s</span>',
										esc_attr( $mobile_phone_digits ),
										esc_attr__( 'Click to copy phone number', 'stackboost-for-supportcandy' ),
										$copy_icon_svg
									);
									$phone_output_parts[] = '<strong>' . esc_html__( 'Mobile', 'stackboost-for-supportcandy' ) . ':</strong> ' . $mobile_display . $copy_span;
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
									<td><?php echo ! empty( $formatted_phone_output ) ? wp_kses( $formatted_phone_output, $allowed_html ) : '&mdash;'; ?></td>
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

		$output = ob_get_clean();

		// Inject CSS directly into the page to override theme/plugin conflicts.
		$style_override = '
			<style>
				.dataTables_wrapper .dataTables_length,
				.dataTables_wrapper .dt-length {
					position: relative !important;
					display: inline-block !important;
				}
				.dataTables_wrapper .dataTables_length select,
				.dataTables_wrapper .dt-length select {
					-webkit-appearance: none !important;
					-moz-appearance: none !important;
					appearance: none !important;
					padding-right: 30px !important;
					background-color: #fff !important;
				}
				.dataTables_wrapper .dataTables_length::after,
				.dataTables_wrapper .dt-length::after {
					content: "" !important;
					position: absolute !important;
					top: 50% !important;
					right: 12px !important;
					transform: translateY(-50%) !important;
					width: 0 !important;
					height: 0 !important;
					border-left: 5px solid transparent !important;
					border-right: 5px solid transparent !important;
					border-top: 6px solid #666 !important;
					pointer-events: none !important;
					z-index: 2 !important;
				}
			</style>
		';

		return $output . $style_override;
	}
}