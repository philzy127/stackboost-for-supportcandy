<?php

namespace StackBoost\ForSupportCandy\Services;

/**
 * Handles the complete removal of plugin data during uninstallation.
 *
 * @package StackBoost\Services
 */
class UninstallService {

    /**
     * Run the full data wipe.
     *
     * CAUTION: This permanently deletes all StackBoost data.
     */
    public static function run_clean_uninstall() {
        self::delete_custom_post_types();
        self::delete_options();
        self::delete_transients();
    }

    /**
     * Delete all posts associated with StackBoost Custom Post Types.
     */
    private static function delete_custom_post_types() {
        $post_types = [
            'sb_staff_dir',
            'sb_location',
            'sb_department',
            'stkb_onboarding_step',
            'onboarding_step', // Legacy CPT
        ];

        foreach ( $post_types as $post_type ) {
            $posts = get_posts( [
                'post_type'   => $post_type,
                'numberposts' => -1,
                'post_status' => 'any',
                'fields'      => 'ids',
            ] );

            foreach ( $posts as $post_id ) {
                wp_delete_post( $post_id, true ); // Force delete (skip trash)
            }
        }
    }

    /**
     * Delete all StackBoost options.
     */
    private static function delete_options() {
        $options = [
            'stackboost_settings',
            'stackboost_directory_settings',
            'stackboost_directory_widget_settings',
            'stackboost_onboarding_config',
            'stackboost_license_key',
            'stackboost_license_instance_id',
            'stackboost_license_tier',
            'stackboost_license_variant_id',
            'sb_last_verified_at',
            'stackboost_last_update_completed_timestamp',
            'stackboost_phone_migration_complete',
        ];

        foreach ( $options as $option ) {
            delete_option( $option );
        }
    }

    /**
     * Delete StackBoost transients.
     */
    private static function delete_transients() {
        delete_transient( 'stackboost_license_error_msg' );
        delete_transient( 'stackboost_onboarding_tickets_cache' );
    }
}
