<?php

namespace StackBoost\ForSupportCandy\Modules\Directory;

/**
 * Class Shortcode
 *
 * Handles the [stackboost_directory] shortcode to display the staff directory.
 *
 * @package StackBoost\ForSupportCandy\Modules\Directory
 */
class Shortcode {

    /**
     * The core directory service.
     *
     * @var Core
     */
    private Core $core;

    /**
     * Shortcode constructor.
     */
    public function __construct() {
        $this->core = Core::get_instance();
        add_shortcode( 'stackboost_directory', [ $this, 'render_directory' ] );
    }

    /**
     * Render the HTML for the staff directory.
     *
     * @return string The rendered HTML.
     */
    public function render_directory(): string {
        $employees = $this->get_all_active_employees();

        ob_start();
        ?>
        <div class="stackboost-directory-wrapper">
            <table id="stackboost-directory-table" class="display" style="width:100%">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Job Title</th>
                        <th>Department</th>
                        <th>Office Phone</th>
                        <th>Email</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $employees as $employee ) : ?>
                        <tr>
                            <td><?php echo esc_html( $employee['name'] ); ?></td>
                            <td><?php echo esc_html( $employee['job_title'] ); ?></td>
                            <td><?php echo esc_html( $employee['department'] ); ?></td>
                            <td><?php echo esc_html( $employee['office_phone'] ); ?></td>
                            <td><a href="mailto:<?php echo esc_attr( $employee['email'] ); ?>"><?php echo esc_html( $employee['email'] ); ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get all active employees from the directory.
     *
     * @return array An array of employee data.
     */
    private function get_all_active_employees(): array {
        $args = [
            'post_type'      => 'chp_staff_directory',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'     => '_active',
                    'value'   => 'Yes',
                    'compare' => '=',
                ],
            ],
        ];

        $query = new \WP_Query( $args );
        $employees = [];

        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $profile_id = get_the_ID();
                $employee_data = $this->core->get_employee_data( $profile_id );
                $employee_data['name'] = get_the_title();
                $employees[] = $employee_data;
            }
        }
        wp_reset_postdata();

        return $employees;
    }
}