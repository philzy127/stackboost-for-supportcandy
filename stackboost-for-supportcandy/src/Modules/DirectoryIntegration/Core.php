<?php

namespace StackBoost\ForSupportCandy\Modules\DirectoryIntegration;

/**
 * Core class for the Directory Integration module.
 *
 * This class handles the business logic for the one-time migration
 * from the standalone CHP Staff Directory plugin.
 *
 * @package StackBoost\ForSupportCandy\Modules\DirectoryIntegration
 */
final class Core {

    /**
     * The option name used to store the migration status.
     */
    const MIGRATION_STATUS_OPTION = 'stackboost_directory_migration_status';

    /**
     * Check if a migration is required.
     *
     * A migration is considered necessary if the migration status flag has not been set
     * and the custom post type from the old plugin (`chp_staff_directory`) exists.
     *
     * @return bool True if a migration is needed, false otherwise.
     */
    public function is_migration_needed(): bool {
        $migration_complete = get_option( self::MIGRATION_STATUS_OPTION, false );

        // If the flag is already set, no need to migrate.
        if ( $migration_complete ) {
            return false;
        }

        // If the old CPT exists, a migration is needed.
        return post_type_exists( 'chp_staff_directory' );
    }

    /**
     * Run the one-time migration process.
     *
     * This function will be responsible for migrating settings from the old
     * plugin to the new integrated modules. For this initial task, it simply
     * sets a flag to indicate that the migration has been run.
     */
    public function run_migration() {
        // Placeholder for future settings migration logic.
        // For example, when the Role-Based Editing feature is built, the logic
        // to migrate the 'it_technician' and 'administrator' roles would go here.

        // Mark the migration as complete to prevent it from running again.
        update_option( self::MIGRATION_STATUS_OPTION, true );
    }

    /**
     * Check if the standalone CHP Staff Directory plugin is active.
     *
     * @return bool True if the old plugin is active, false otherwise.
     */
    public function is_standalone_plugin_active(): bool {
        // The main file of the standalone plugin is 'CHP Staff Directory/CHP Staff Directory.php'.
        // Note the space in the directory name.
        $plugin_path = 'CHP Staff Directory/CHP Staff Directory.php';
        return is_plugin_active( $plugin_path );
    }
}