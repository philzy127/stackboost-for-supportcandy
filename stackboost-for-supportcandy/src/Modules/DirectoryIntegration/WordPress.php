<?php

namespace StackBoost\ForSupportCandy\Modules\DirectoryIntegration;

/**
 * WordPress integration class for the Directory Integration module.
 *
 * This class handles the WordPress-specific hooks, including the activation
 * hook for the migration and the admin notice for deactivating the old plugin.
 *
 * @package StackBoost\ForSupportCandy\Modules\DirectoryIntegration
 */
final class WordPress {

    /**
     * The Core service instance.
     *
     * @var Core
     */
    private Core $core;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->core = new Core();
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks.
     */
    private function init_hooks() {
        add_action( 'admin_menu', [ $this, 'add_migration_tool_page' ] );
        add_action( 'admin_init', [ $this, 'handle_migration_form' ] );
        add_action( 'admin_notices', [ $this, 'show_deactivation_notice' ] );
        add_action( 'wp_ajax_stackboost_dismiss_deactivation_notice', [ $this, 'dismiss_deactivation_notice' ] );
    }

    /**
     * Handle the form submission from the migration tool page.
     */
    public function handle_migration_form() {
        if ( isset( $_POST['stackboost_run_migration'] ) && check_admin_referer( 'stackboost_run_migration_nonce' ) ) {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( 'You do not have permission to perform this action.' );
            }

            $this->core->run_migration();

            // Redirect to the same page with a success message
            wp_redirect( admin_url( 'admin.php?page=stackboost-migration-tool&migration=success' ) );
            exit;
        }
    }

    /**
     * Add the migration tool page as a submenu under the main StackBoost menu.
     */
    public function add_migration_tool_page() {
        add_submenu_page(
            'stackboost-for-supportcandy',
            'Migration Tool',
            'Migration Tool',
            'manage_options',
            'stackboost-migration-tool',
            [ $this, 'render_migration_tool_page' ]
        );
    }

    /**
     * Render the content for the Migration Tool page.
     */
    public function render_migration_tool_page() {
        ?>
        <div class="wrap">
            <h1><span class="dashicons-update-alt" style="font-size: 1.2em; margin-right: 5px;"></span>StackBoost - Migration Tool</h1>
            <p>This tool helps you migrate settings from the standalone <strong>CHP Staff Directory</strong> plugin to the integrated StackBoost modules.</p>

            <?php if ( get_option( Core::MIGRATION_STATUS_OPTION, false ) ) : ?>
                <div class="notice notice-success">
                    <p><strong>Migration Complete!</strong> The one-time settings migration has already been performed. No further action is needed.</p>
                </div>
            <?php elseif ( ! $this->core->is_migration_needed() ) : ?>
                <div class="notice notice-info">
                    <p><strong>No Migration Needed.</strong> The system did not detect data from the standalone <strong>CHP Staff Directory</strong> plugin, so no migration is necessary.</p>
                </div>
            <?php else : ?>
                <div class="notice notice-warning">
                    <p><strong>Action Required:</strong> Data from the standalone <strong>CHP Staff Directory</strong> plugin has been detected. Click the button below to migrate your settings.</p>
                </div>
                <form method="POST" action="">
                    <?php wp_nonce_field( 'stackboost_run_migration_nonce' ); ?>
                    <input type="hidden" name="stackboost_run_migration" value="1" />
                    <?php submit_button( 'Run Migration' ); ?>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Show an admin notice recommending deactivation of the old plugin.
     *
     * This notice appears if the migration has been completed, the old plugin
     * is still active, and the user has not dismissed the notice.
     */
    public function show_deactivation_notice() {
        // Only show to users who can manage plugins.
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Check if the notice should be displayed.
        $migration_complete = get_option( Core::MIGRATION_STATUS_OPTION, false );
        $notice_dismissed = get_user_meta( get_current_user_id(), 'stackboost_deactivation_notice_dismissed', true );

        if ( $migration_complete && ! $notice_dismissed && $this->core->is_standalone_plugin_active() ) {
            ?>
            <div class="notice notice-warning is-dismissible stackboost-deactivation-notice">
                <p>
                    <strong>Success!</strong> The StackBoost Directory Integration has migrated your settings from the standalone <strong>CHP Staff Directory</strong> plugin.
                </p>
                <p>
                    To avoid conflicts, please <a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>">deactivate the old plugin</a> now.
                </p>
            </div>
            <script type="text/javascript">
                jQuery(document).on('click', '.stackboost-deactivation-notice .notice-dismiss', function() {
                    jQuery.post(ajaxurl, {
                        action: 'stackboost_dismiss_deactivation_notice'
                    });
                });
            </script>
            <?php
        }
    }

    /**
     * AJAX handler to dismiss the deactivation notice.
     *
     * This function saves a user meta option so the notice does not appear again
     * for the current user.
     */
    public function dismiss_deactivation_notice() {
        update_user_meta( get_current_user_id(), 'stackboost_deactivation_notice_dismissed', true );
        wp_die(); // This is required to terminate immediately and return a proper response.
    }
}