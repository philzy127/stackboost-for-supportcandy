<?php
/**
 * The Template for displaying all single staff directory entries.
 *
 * @package StackBoost
 */

get_header(); ?>

<div id="primary" class="content-area">
    <main id="main" class="site-main">

        <?php
        while ( have_posts() ) :
            the_post();

            $employee_id = get_the_ID();
            $directory_service = \StackBoost\ForSupportCandy\Services\DirectoryService::get_instance();
            $employee = $directory_service->retrieve_employee_data( $employee_id );

            if ( ! $employee ) {
                // Handle case where employee data could not be retrieved.
                echo '<p>Employee not found.</p>';
                continue;
            }
            ?>

            <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                <header class="entry-header">
                    <?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
                </header><!-- .entry-header -->

                <div class="entry-content">
                    <div class="staff-detail-container">
                        <div class="staff-photo">
                            <?php
                            if ( has_post_thumbnail() ) {
                                the_post_thumbnail( 'medium' ); // Display the featured image (staff photo)
                            } else {
                                // Placeholder image if no photo is set
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
                                    'span' => array(
                                        'class' => true,
                                        'data-phone' => true,
                                        'data-extension' => true,
                                        'title' => true,
                                    ),
                                    'svg' => array(
                                        'class' => true,
                                        'xmlns' => true,
                                        'width' => true,
                                        'height' => true,
                                        'viewbox' => true,
                                        'fill' => true,
                                        'style' => true,
                                    ),
                                    'path' => array(
                                        'd' => true,
                                    ),
                                );

                                if ( ! empty( $employee->office_phone ) ) :
                                    $office_phone_digits = preg_replace( '/\D/', '', $employee->office_phone );
                                    $office_display = esc_html( $employee->office_phone );
                                    if ( ! empty( $employee->extension ) ) {
                                        $office_display .= ' ext. ' . esc_html( $employee->extension );
                                    }
                                    $copy_span = sprintf(
                                        '<span class="chp-copy-phone-icon" data-phone="%s" data-extension="%s" title="%s">%s</span>',
                                        esc_attr( $office_phone_digits ),
                                        esc_attr( $employee->extension ),
                                        esc_attr__( 'Click to copy phone number', 'stackboost-for-supportcandy' ),
                                        $copy_icon_svg
                                    );
                                ?>
                                <tr>
                                    <th><?php esc_html_e( 'Office Phone:', 'stackboost-for-supportcandy' ); ?></th>
                                    <td><?php echo wp_kses( $office_display . $copy_span, $allowed_html ); ?></td>
                                </tr>
                                <?php endif; ?>

                                <?php if ( ! empty( $employee->mobile_phone ) ) :
                                    $mobile_phone_digits = preg_replace( '/\D/', '', $employee->mobile_phone );
                                    $mobile_display = esc_html( $employee->mobile_phone );
                                    $copy_span = sprintf(
                                        '<span class="chp-copy-phone-icon" data-phone="%s" data-extension="" title="%s">%s</span>',
                                        esc_attr( $mobile_phone_digits ),
                                        esc_attr__( 'Click to copy phone number', 'stackboost-for-supportcandy' ),
                                        $copy_icon_svg
                                    );
                                ?>
                                <tr>
                                    <th><?php esc_html_e( 'Mobile Phone:', 'stackboost-for-supportcandy' ); ?></th>
                                    <td><?php echo wp_kses( $mobile_display . $copy_span, $allowed_html ); ?></td>
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

                                <?php if ( ! empty( $employee->room_number ) ) : ?>
                                <tr>
                                    <th><?php esc_html_e( 'Room #:', 'stackboost-for-supportcandy' ); ?></th>
                                    <td><?php echo esc_html( $employee->room_number ); ?></td>
                                </tr>
                                <?php endif; ?>

                                <?php if ( ! empty( $employee->location_details['address_line1'] ) ) : ?>
                                <tr>
                                    <th><?php esc_html_e( 'Location Address:', 'stackboost-for-supportcandy' ); ?></th>
                                    <td>
                                        <?php echo esc_html( $employee->location_details['address_line1'] ); ?><br>
                                        <?php echo esc_html( $employee->location_details['city'] ); ?>, <?php echo esc_html( $employee->location_details['state'] ); ?> <?php echo esc_html( $employee->location_details['zip'] ); ?>
                                    </td>
                                </tr>
                                <?php endif; ?>

                                <?php if ( ! empty( $employee->location_details['phone_number'] ) ) : ?>
                                <tr>
                                    <th><?php esc_html_e( 'Location Phone:', 'stackboost-for-supportcandy' ); ?></th>
                                    <td><?php echo esc_html( $employee->location_details['phone_number'] ); ?></td>
                                </tr>
                                <?php endif; ?>

                                <tr>
                                    <th><?php esc_html_e( 'Status:', 'stackboost-for-supportcandy' ); ?></th>
                                    <td><?php echo esc_html( $employee->active_status ); ?></td>
                                </tr>
                                <?php if ( ! empty( $employee->active_as_of_date ) ) : ?>
                                <tr>
                                    <th><?php esc_html_e( 'Active as of:', 'stackboost-for-supportcandy' ); ?></th>
                                    <td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $employee->active_as_of_date ) ) ); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if ( ! empty( $employee->planned_exit_date ) ) : ?>
                                <tr>
                                    <th><?php esc_html_e( 'Inactive as of:', 'stackboost-for-supportcandy' ); ?></th>
                                    <td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $employee->planned_exit_date ) ) ); ?></td>
                                </tr>
                                <?php endif; ?>

                                <?php if ( ! empty( $employee->last_updated_on ) ) : ?>
                                <tr>
                                    <th><?php esc_html_e( 'Last Updated On:', 'stackboost-for-supportcandy' ); ?></th>
                                    <td><?php echo esc_html( $employee->last_updated_on ); ?></td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        </div>
                    </div><!-- .staff-detail-container -->
                </div><!-- .entry-content -->

                <footer class="entry-footer">
                    <?php
                    // Use get_permalink with the specific page ID provided by the user
                    $directory_page_url = get_permalink( 179 ); // Use the provided page ID: 179
                    echo '<p><a href="' . esc_url( $directory_page_url ) . '">' . esc_html__( '&laquo; Back to Staff Directory', 'stackboost-for-supportcandy' ) . '</a></p>';
                    ?>
                </footer><!-- .entry-footer -->

            </article><!-- #post-<?php the_ID(); ?> -->

        <?php endwhile; // End of the loop. ?>

    </main><!-- #main -->
</div><!-- #primary -->

<?php
get_sidebar(); // Loads the sidebar.php template from the active theme.
get_footer();  // Loads the footer.php template from the active theme.
?>