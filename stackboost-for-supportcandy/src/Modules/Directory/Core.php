<?php

namespace StackBoost\ForSupportCandy\Modules\Directory;

/**
 * Core class for the Directory service.
 *
 * This class handles the business logic of interacting with the Company Directory plugin.
 * It is designed to be a singleton to ensure a single point of communication.
 *
 * @package StackBoost\ForSupportCandy\Modules\Directory
 */
final class Core {

    /**
     * The single instance of the class.
     *
     * @var Core|null
     */
    private static ?Core $instance = null;

    /**
     * Get the single instance of the class.
     *
     * @return Core
     */
    public static function get_instance(): Core {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor to prevent direct instantiation.
     */
    private function __construct() {
        // Placeholder for any initialization logic.
    }

    /**
     * Find an employee's profile in the Company Directory.
     *
     * Given a WordPress user ID or email, this function queries the Company Directory
     * to find the corresponding staff profile and returns its unique identifier (post ID).
     *
     * @param int|string $user_identifier The WP user ID (int) or email address (string).
     * @return int The post ID of the staff profile, or 0 if not found.
     */
    public function find_employee_profile( $user_identifier ): int {
        global $wpdb;

        $meta_key   = '';
        $meta_value = '';

        if ( is_numeric( $user_identifier ) ) {
            // It's a user ID, so get the email address.
            $user = get_user_by( 'ID', $user_identifier );
            if ( $user ) {
                $meta_key   = '_email_address';
                $meta_value = $user->user_email;
            }
        } elseif ( is_string( $user_identifier ) && is_email( $user_identifier ) ) {
            // It's an email address.
            $meta_key   = '_email_address';
            $meta_value = $user_identifier;
        }

        if ( empty( $meta_key ) || empty( $meta_value ) ) {
            return 0;
        }

        $post_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s",
                $meta_key,
                $meta_value
            )
        );

        if ( ! $post_id ) {
            return 0;
        }

        // Make sure the post is a staff directory entry.
        if ( 'chp_staff_directory' !== get_post_type( $post_id ) ) {
            return 0;
        }

        return (int) $post_id;
    }

    /**
     * Retrieve a structured set of an employee's data.
     *
     * Given a Company Directory profile ID, this function fetches all relevant
     * meta fields and returns them as a structured array.
     *
     * @param int $profile_id The post ID of the staff profile.
     * @return array An associative array of employee data, or empty if not found.
     */
    public function get_employee_data( int $profile_id ): array {
        if ( empty( $profile_id ) || 'chp_staff_directory' !== get_post_type( $profile_id ) ) {
            return [];
        }

        $all_meta = get_post_meta( $profile_id );
        $data     = [];

        $key_map = [
            '_office_phone'        => 'office_phone',
            '_extension'           => 'extension',
            '_mobile_phone'        => 'mobile_phone',
            '_location'            => 'location_name',
            '_location_id'         => 'location_id',
            '_room_number'         => 'room_number',
            '_department_program'  => 'department',
            '_chp_staff_job_title' => 'job_title',
            '_email_address'       => 'email',
            '_active'              => 'is_active',
        ];

        foreach ( $key_map as $meta_key => $output_key ) {
            $data[ $output_key ] = $all_meta[ $meta_key ][0] ?? '';
        }

        // Add the profile ID itself for reference.
        $data['profile_id'] = $profile_id;

        return $data;
    }

    /**
     * Identify an employee's manager.
     *
     * This is a placeholder function for future development. The logic for
     * determining a manager will be implemented in a later task.
     *
     * @param int $profile_id The post ID of the employee's staff profile.
     * @return int The post ID of the manager's profile, or 0 if not applicable.
     */
    public function get_employee_manager( int $profile_id ): int {
        // Placeholder: Manager logic to be implemented in a future story.
        // For now, it will always return 0, indicating no manager was found.
        return 0;
    }
}