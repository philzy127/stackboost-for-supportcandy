<?php

namespace StackBoost\ForSupportCandy\Modules\Directory;

/**
 * Class Shortcode
 *
 * Handles the [stackboost_directory] shortcode to display the staff directory.
 * This is a direct port from the standalone CHP Staff Directory plugin.
 *
 * @package StackBoost\ForSupportCandy\Modules\Directory
 */
class Shortcode {

    /**
     * Shortcode constructor.
     */
    public function __construct() {
        add_shortcode( 'stackboost_directory', [ $this, 'render_directory_shortcode' ] );
    }

    /**
     * Render the directory shortcode content.
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output for the directory table.
     */
    public function render_directory_shortcode( $atts ) {
        $full_table_args = [
            'post_type'      => 'chp_staff_directory',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => [
                [
                    'key'     => '_active',
                    'value'   => 'Yes',
                    'compare' => '=',
                ],
            ],
            'orderby'        => 'title',
            'order'          => 'ASC',
        ];
        $full_directory_entries = new \WP_Query( $full_table_args );

        ob_start();
        ?>
        <div class="stackboost-directory-container">
            <table id="stackboostDirectoryTable" class="display stackboost-directory-table">
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
                    while ( $full_directory_entries->have_posts() ) : $full_directory_entries->the_post();
                        $post_id = get_the_ID();
                        $office_phone = get_post_meta( $post_id, '_office_phone', true );
                        $extension = get_post_meta( $post_id, '_extension', true );
                        $department_program = get_post_meta( $post_id, '_department_program', true );
                        $job_title = get_post_meta( $post_id, '_chp_staff_job_title', true );
                        $email = get_post_meta( $post_id, '_email_address', true );
                        ?>
                        <tr>
                            <td>
                                <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                                <?php if ( ! empty( $email ) ) : ?>
                                    <a href="mailto:<?php echo esc_attr( $email ); ?>" style="margin-left: 5px;">(Email)</a>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                if ( ! empty( $office_phone ) ) {
                                    echo esc_html( $office_phone );
                                    if ( ! empty( $extension ) ) {
                                        echo ' ext. ' . esc_html( $extension );
                                    }
                                } else {
                                    echo '&mdash;';
                                }
                                ?>
                            </td>
                            <td><?php echo esc_html( $department_program ); ?></td>
                            <td><?php echo esc_html( $job_title ); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php
        wp_reset_postdata();
        return ob_get_clean();
    }
}