<?php

namespace StackBoost\ForSupportCandy\Modules\Appearance\Admin;

class Page {

    /**
     * Render the Appearance settings page.
     */
    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Get current theme
        $settings = get_option( 'stackboost_settings', [] );
        $current_theme = isset( $settings['admin_theme'] ) ? $settings['admin_theme'] : 'sb-theme-clean-tech';

        // Themes array
        $themes = [
            'sb-theme-wordpress-sync'      => 'WordPress Sync',
            'sb-theme-supportcandy-sync'   => 'SupportCandy Sync',
            'sb-theme-cloud-dancer'        => 'Cloud Dancer',
            'sb-theme-heroic'              => 'Heroic',
            'sb-theme-clean-tech'          => 'Clean Tech (Default)',
            'sb-theme-hudson-valley-eco'   => 'Hudson Valley Eco',
            'sb-theme-dark-mode'           => 'Dark Mode',
        ];

        ?>
        <div class="wrap stackboost-dashboard <?php echo esc_attr( $current_theme ); ?>">
            <h1><?php esc_html_e( 'StackBoost Appearance', 'stackboost-for-supportcandy' ); ?></h1>

            <div class="stackboost-dashboard-grid">
                <!-- Theme Selection Card -->
                <div class="stackboost-card">
                    <h2><?php esc_html_e( 'Admin Theme', 'stackboost-for-supportcandy' ); ?></h2>
                    <p><?php esc_html_e( 'Select a theme to customize the look and feel of the StackBoost admin interface.', 'stackboost-for-supportcandy' ); ?></p>

                    <form id="stackboost-appearance-form">
                        <label for="stackboost_admin_theme"><strong><?php esc_html_e( 'Select Theme:', 'stackboost-for-supportcandy' ); ?></strong></label>
                        <br>
                        <select name="stackboost_admin_theme" id="stackboost_admin_theme" style="margin-top: 10px; width: 100%; max-width: 300px;">
                            <?php foreach ( $themes as $value => $label ) : ?>
                                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_theme, $value ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description" style="margin-top: 5px;">
                            <?php esc_html_e( 'Changes are saved automatically.', 'stackboost-for-supportcandy' ); ?>
                        </p>
                        <div style="margin-top: 15px;">
                            <button type="button" id="stackboost_save_theme_btn" class="button button-primary">
                                <?php esc_html_e( 'Save Theme', 'stackboost-for-supportcandy' ); ?>
                            </button>
                        </div>
                    </form>
                </div>

            </div>
        </div>
        <?php
    }
}
