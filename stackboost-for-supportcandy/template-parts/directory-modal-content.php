<?php
/**
 * The Template Part for displaying staff directory entry content in a modal.
 *
 * @package StackBoost
 */

// This file is loaded via AJAX, so we need to ensure global context is set up.
if ( ! defined( 'ABSPATH' ) || ! isset( $post ) ) {
    // Direct access is not allowed, or post is not set.
    // The AJAX handler in WordPress.php should have already set up the global $post.
    return;
}

// Manually include the functions file as it's not always loaded in an AJAX context.
require_once \STACKBOOST_PLUGIN_PATH . 'includes/functions.php';

$employee_id = $post->ID;
$directory_service = \StackBoost\ForSupportCandy\Services\DirectoryService::get_instance();
$employee = $directory_service->retrieve_employee_data( $employee_id );

if ( ! $employee ) {
    // Handle case where employee data could not be retrieved.
    echo '<p>Employee not found.</p>';
    return;
}

// Use a wrapper class to match the styling of the single page if needed.
?>
<div class="stackboost-staff-entry">
    <article id="post-<?php echo esc_attr( $employee_id ); ?>" <?php post_class( '', $employee_id ); ?>>
        <header class="entry-header">
            <h1 class="entry-title"><?php echo esc_html( $employee->name ); ?></h1>
        </header><!-- .entry-header -->

        <div class="entry-content">
            <div class="staff-detail-container">
                <div class="staff-photo">
                    <?php
                    if ( has_post_thumbnail( $employee_id ) ) {
                        echo get_the_post_thumbnail( $employee_id, 'medium' );
                    } else {
                        echo '<img src="' . esc_url( 'https://placehold.co/300x300/e0e0e0/555555?text=No+Photo' ) . '" alt="No staff photo available" />';
                    }
                    ?>
                </div>
                <div class="staff-info">
                    <table class="staff-details-table">
                        <?php if ( ! empty( $employee->job_title ) ) : ?>
                        <tr>
                            <th><?php esc_html_e( 'Title:', 'stackboost-for-supportcandy' ); ?></th>
                            <td><?php echo esc_html( $employee->job_title ); ?></td>
                        </tr>
                        <?php endif; ?>

                        <?php if ( ! empty( $employee->department_program ) ) : ?>
                        <tr>
                            <th><?php esc_html_e( 'Department / Program:', 'stackboost-for-supportcandy' ); ?></th>
                            <td><?php echo esc_html( $employee->department_program ); ?></td>
                        </tr>
                        <?php endif; ?>

                        <?php
                        $copy_icon_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="16px" height="16px" style="vertical-align: middle; margin-left: 5px; cursor: pointer;"><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/></svg>';
                        $allowed_html = array(
                            'a' => array( 'href' => true ),
                            'span' => array( 'class' => true, 'data-phone' => true, 'data-extension' => true, 'title' => true ),
                            'svg' => array( 'class' => true, 'xmlns' => true, 'width' => true, 'height' => true, 'viewbox' => true, 'fill' => true, 'style' => true ),
                            'path' => array( 'd' => true ),
                        );
                        ?>
                        <?php if ( ! empty( $employee->office_phone ) ) : ?>
                        <tr>
                            <th><?php esc_html_e( 'Office Phone:', 'stackboost-for-supportcandy' ); ?></th>
                            <td><?php echo wp_kses( \stackboost_format_phone_number( $employee->office_phone, $employee->extension, $copy_icon_svg ), $allowed_html ); ?></td>
                        </tr>
                        <?php endif; ?>

                        <?php if ( ! empty( $employee->mobile_phone ) ) : ?>
                        <tr>
                            <th><?php esc_html_e( 'Mobile Phone:', 'stackboost-for-supportcandy' ); ?></th>
                            <td><?php echo wp_kses( \stackboost_format_phone_number( $employee->mobile_phone, '', $copy_icon_svg ), $allowed_html ); ?></td>
                        </tr>
                        <?php endif; ?>

                        <?php if ( ! empty( $employee->email ) ) : ?>
                        <tr>
                            <th><?php esc_html_e( 'Email:', 'stackboost-for-supportcandy' ); ?></th>
                            <td><a href="mailto:<?php echo esc_attr( $employee->email ); ?>"><?php echo esc_html( $employee->email ); ?></a></td>
                        </tr>
                        <?php endif; ?>

                        <?php if ( ! empty( $employee->location_name ) ) : ?>
                        <tr>
                            <th><?php esc_html_e( 'Location:', 'stackboost-for-supportcandy' ); ?></th>
                            <td><?php echo esc_html( $employee->location_name ); ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div><!-- .staff-detail-container -->
        </div><!-- .entry-content -->
    </article><!-- #post-<?php echo esc_attr( $employee_id ); ?> -->
</div>