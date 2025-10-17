<?php
/**
 * The template for displaying a single staff member.
 *
 * @package StackBoost
 */

get_header(); ?>

<div id="primary" class="content-area">
    <main id="main" class="site-main" role="main">

        <?php
        // Start the loop.
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

            <article id="post-<?php echo esc_attr( $employee_id ); ?>" <?php post_class( 'stackboost-single-staff' ); ?>>
                <header class="entry-header">
                    <?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
                </header><!-- .entry-header -->

                <div class="entry-content">
                    <div class="stackboost-staff-member-details">
                        <div class="stackboost-staff-photo">
                            <?php if ( has_post_thumbnail() ) : ?>
                                <?php the_post_thumbnail( 'large' ); ?>
                            <?php else : ?>
                                <img src="<?php echo esc_url( \STACKBOOST_PLUGIN_URL . 'assets/images/default-avatar.png' ); ?>" alt="<?php echo esc_attr( $employee->name ); ?>" />
                            <?php endif; ?>
                        </div>
                        <div class="stackboost-staff-info">
                            <table class="stackboost-staff-details-table">
                                <tbody>
                                    <tr>
                                        <th><?php esc_html_e( 'Job Title:', 'stackboost-for-supportcandy' ); ?></th>
                                        <td><?php echo esc_html( $employee->job_title ); ?></td>
                                    </tr>
                                    <tr>
                                        <th><?php esc_html_e( 'Department/Program:', 'stackboost-for-supportcandy' ); ?></th>
                                        <td><?php echo esc_html( $employee->department_program ); ?></td>
                                    </tr>
                                    <tr>
                                        <th><?php esc_html_e( 'Email:', 'stackboost-for-supportcandy' ); ?></th>
                                        <td><a href="mailto:<?php echo esc_attr( $employee->email ); ?>"><?php echo esc_html( $employee->email ); ?></a></td>
                                    </tr>
                                    <tr>
                                        <th><?php esc_html_e( 'Office Phone:', 'stackboost-for-supportcandy' ); ?></th>
                                        <td><?php echo esc_html( $employee->office_phone ); ?></td>
                                    </tr>
                                    <?php if ( ! empty( $employee->extension ) ) : ?>
                                        <tr>
                                            <th><?php esc_html_e( 'Extension:', 'stackboost-for-supportcandy' ); ?></th>
                                            <td><?php echo esc_html( $employee->extension ); ?></td>
                                        </tr>
                                    <?php endif; ?>
                                    <?php if ( ! empty( $employee->mobile_phone ) ) : ?>
                                        <tr>
                                            <th><?php esc_html_e( 'Mobile Phone:', 'stackboost-for-supportcandy' ); ?></th>
                                            <td><?php echo esc_html( $employee->mobile_phone ); ?></td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div><!-- .entry-content -->

            </article><!-- #post-## -->

            <?php
            // If comments are open or we have at least one comment, load up the comment template.
            if ( comments_open() || get_comments_number() ) {
                comments_template();
            }

            // End of the loop.
        endwhile;
        ?>

    </main><!-- .site-main -->
</div><!-- .content-area -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>