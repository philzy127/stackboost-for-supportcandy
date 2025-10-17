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

            <article id="post-<?php echo esc_attr( $employee_id ); ?>" <?php post_class() ; ?>>
                <header class="entry-header">
                    <?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
                </header><!-- .entry-header -->

                <div class="entry-content">
                    <p>
                        <strong><?php esc_html_e( 'Job Title:', 'stackboost-for-supportcandy' ); ?></strong>
                        <?php echo esc_html( $employee->job_title ); ?>
                    </p>
                    <p>
                        <strong><?php esc_html_e( 'Department/Program:', 'stackboost-for-supportcandy' ); ?></strong>
                        <?php echo esc_html( $employee->department_program ); ?>
                    </p>
                    <p>
                        <strong><?php esc_html_e( 'Email:', 'stackboost-for-supportcandy' ); ?></strong>
                        <a href="mailto:<?php echo esc_attr( $employee->email ); ?>"><?php echo esc_html( $employee->email ); ?></a>
                    </p>
                    <p>
                        <strong><?php esc_html_e( 'Office Phone:', 'stackboost-for-supportcandy' ); ?></strong>
                        <?php echo esc_html( $employee->office_phone ); ?>
                    </p>
                    <?php if ( ! empty( $employee->extension ) ) : ?>
                        <p>
                            <strong><?php esc_html_e( 'Extension:', 'stackboost-for-supportcandy' ); ?></strong>
                            <?php echo esc_html( $employee->extension ); ?>
                        </p>
                    <?php endif; ?>
                    <?php if ( ! empty( $employee->mobile_phone ) ) : ?>
                        <p>
                            <strong><?php esc_html_e( 'Mobile Phone:', 'stackboost-for-supportcandy' ); ?></strong>
                            <?php echo esc_html( $employee->mobile_phone ); ?>
                        </p>
                    <?php endif; ?>
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