<?php
/**
 * The Template Part for displaying staff directory entry content in a modal.
 *
 * This template is loaded via AJAX and expects the employee data to be passed in the $args array.
 *
 * @package StackBoost
 * @var array $args
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// The employee data is passed from the AJAX handler via load_template $args.
$stackboost_employee = $args['employee'] ?? null;
if ( ! $stackboost_employee ) {
	echo '<p>Error: Employee data not provided to template.</p>';
	return;
}

// The global $post object is set by the AJAX handler so functions like post_class() and get_the_ID() work correctly.
$stackboost_employee_id = get_the_ID();

// Manually include the functions file as it's not always loaded in an AJAX context.
require_once \STACKBOOST_PLUGIN_PATH . 'includes/functions.php';

?>
<div class="stackboost-staff-entry">
	<article id="post-<?php echo esc_attr( $stackboost_employee_id ); ?>" <?php post_class( '', $stackboost_employee_id ); ?>>
		<header class="entry-header">
			<h1 class="entry-title"><?php echo esc_html( $stackboost_employee->name ); ?></h1>
		</header><!-- .entry-header -->

		<div class="entry-content">
			<div class="staff-detail-container">
				<div class="staff-photo">
					<?php
					if ( ! empty( $stackboost_employee->thumbnail_url ) ) {
						echo '<img src="' . esc_url( $stackboost_employee->thumbnail_url ) . '" alt="' . esc_attr( $stackboost_employee->name ) . '" />';
					} else {
						echo '<img src="' . esc_url( 'https://placehold.co/300x300/e0e0e0/555555?text=No+Photo' ) . '" alt="No staff photo available" />';
					}
					?>
				</div>
				<div class="staff-info">
					<table class="staff-details-table">
						<?php if ( ! empty( $stackboost_employee->job_title ) ) : ?>
						<tr>
							<th><?php esc_html_e( 'Title:', 'stackboost-for-supportcandy' ); ?></th>
							<td><?php echo esc_html( $stackboost_employee->job_title ); ?></td>
						</tr>
						<?php endif; ?>

						<?php if ( ! empty( $stackboost_employee->department_program ) ) : ?>
						<tr>
							<th><?php esc_html_e( 'Department / Program:', 'stackboost-for-supportcandy' ); ?></th>
							<td><?php echo esc_html( $stackboost_employee->department_program ); ?></td>
						</tr>
						<?php endif; ?>

						<?php
						$stackboost_copy_icon_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="16px" height="16px" style="vertical-align: middle; margin-left: 5px; cursor: pointer;"><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/></svg>';
						$stackboost_allowed_html  = array(
							'a'    => array( 'href' => true ),
							'span' => array( 'class' => true, 'data-phone' => true, 'data-extension' => true, 'title' => true ),
							'svg'  => array( 'class' => true, 'xmlns' => true, 'width' => true, 'height' => true, 'viewbox' => true, 'fill' => true, 'style' => true ),
							'path' => array( 'd' => true ),
						);
						?>
						<?php if ( ! empty( $stackboost_employee->office_phone ) ) : ?>
						<tr>
							<th><?php esc_html_e( 'Office Phone:', 'stackboost-for-supportcandy' ); ?></th>
							<td><?php echo wp_kses( \stackboost_format_phone_number( $stackboost_employee->office_phone, $stackboost_employee->extension, $stackboost_copy_icon_svg ), $stackboost_allowed_html ); ?></td>
						</tr>
						<?php endif; ?>

						<?php if ( ! empty( $stackboost_employee->mobile_phone ) ) : ?>
						<tr>
							<th><?php esc_html_e( 'Mobile Phone:', 'stackboost-for-supportcandy' ); ?></th>
							<td><?php echo wp_kses( \stackboost_format_phone_number( $stackboost_employee->mobile_phone, '', $stackboost_copy_icon_svg ), $stackboost_allowed_html ); ?></td>
						</tr>
						<?php endif; ?>

						<?php if ( ! empty( $stackboost_employee->email ) ) : ?>
						<tr>
							<th><?php esc_html_e( 'Email:', 'stackboost-for-supportcandy' ); ?></th>
							<td><a href="mailto:<?php echo esc_attr( $stackboost_employee->email ); ?>"><?php echo esc_html( $stackboost_employee->email ); ?></a></td>
						</tr>
						<?php endif; ?>

						<?php if ( ! empty( $stackboost_employee->location_name ) ) : ?>
						<tr>
							<th><?php esc_html_e( 'Location:', 'stackboost-for-supportcandy' ); ?></th>
							<td><?php echo esc_html( $stackboost_employee->location_name ); ?></td>
						</tr>
						<?php endif; ?>

						<?php if ( ! empty( $stackboost_employee->room_number ) ) : ?>
						<tr>
							<th><?php esc_html_e( 'Room #:', 'stackboost-for-supportcandy' ); ?></th>
							<td><?php echo esc_html( $stackboost_employee->room_number ); ?></td>
						</tr>
						<?php endif; ?>

						<?php if ( ! empty( $stackboost_employee->location_details['address_line1'] ) ) : ?>
						<tr>
							<th><?php esc_html_e( 'Location Address:', 'stackboost-for-supportcandy' ); ?></th>
							<td>
								<?php echo esc_html( $stackboost_employee->location_details['address_line1'] ); ?><br>
								<?php echo esc_html( $stackboost_employee->location_details['city'] ); ?>, <?php echo esc_html( $stackboost_employee->location_details['state'] ); ?> <?php echo esc_html( $stackboost_employee->location_details['zip'] ); ?>
							</td>
						</tr>
						<?php endif; ?>

						<?php if ( ! empty( $stackboost_employee->location_details['phone_number'] ) ) : ?>
						<tr>
							<th><?php esc_html_e( 'Location Phone:', 'stackboost-for-supportcandy' ); ?></th>
							<td><?php echo esc_html( $stackboost_employee->location_details['phone_number'] ); ?></td>
						</tr>
						<?php endif; ?>


						<?php if ( ! empty( $stackboost_employee->last_updated_on ) ) : ?>
						<tr>
							<th><?php esc_html_e( 'Last Updated On:', 'stackboost-for-supportcandy' ); ?></th>
							<td><?php echo esc_html( $stackboost_employee->last_updated_on ); ?></td>
						</tr>
						<?php endif; ?>
					</table>
				</div>
			</div><!-- .staff-detail-container -->
		</div><!-- .entry-content -->
	</article><!-- #post-<?php echo esc_attr( $stackboost_employee_id ); ?> -->
</div>
