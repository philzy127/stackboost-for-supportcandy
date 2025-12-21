<?php

namespace StackBoost\ForSupportCandy\Modules\Appearance;

use StackBoost\ForSupportCandy\Modules\Appearance\Admin\Page;

class WordPress {

    /**
     * Instance of the Admin Page.
     *
     * @var Page
     */
    private $page;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->page = new Page();
    }

    /**
     * Initialize the module.
     */
    public function init() {
        // Menu is registered centrally in Settings.php to ensure correct order
        add_action( 'wp_ajax_stackboost_save_theme_preference', [ $this, 'ajax_save_theme' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_filter( 'admin_body_class', [ $this, 'add_theme_body_class' ] );
    }

    /**
     * Enqueue assets.
     */
    public function enqueue_assets( $hook_suffix ) {
        // Enqueue global theme CSS on all StackBoost pages
        // We check if the page is a stackboost page.
        // The easiest way is to check the 'stackboost_page_' prefix or known slugs.
        // But for now, let's load it on the main settings and the appearance page.

        $screen = get_current_screen();
        if ( ! $screen ) {
            return;
        }

        // Check if we are on a StackBoost page via GET param (more robust) or Screen ID
        $is_stackboost_page = false;
        if ( isset( $_GET['page'] ) && strpos( $_GET['page'], 'stackboost' ) !== false ) {
            $is_stackboost_page = true;
        } elseif ( strpos( $screen->id, 'stackboost' ) !== false ) {
            $is_stackboost_page = true;
        }

        if ( ! $is_stackboost_page ) {
            return;
        }

        wp_enqueue_style(
            'stackboost-admin-themes',
            STACKBOOST_PLUGIN_URL . 'assets/admin/css/admin-themes.css',
            [],
            STACKBOOST_VERSION
        );

        // Inject dynamic styles for SupportCandy Sync
        // We always inject these variables so they are available for the JS live preview switcher.
        $this->inject_supportcandy_styles();
    }

    /**
     * Inject SupportCandy variables via inline CSS.
     */
    private function inject_supportcandy_styles() {
        $wpsc_settings = get_option( 'wpsc_appearance_settings', [] );

        // Default fallbacks if SC settings aren't found
        $primary_color = isset( $wpsc_settings['primary_color'] ) ? $wpsc_settings['primary_color'] : '#2271b1';
        $bg_color = '#f6f7f7'; // Standard admin BG

        $css = "
            .sb-theme-supportcandy-sync {
                --sb-accent: {$primary_color};
                --sb-bg-main: {$bg_color};
            }
        ";

        wp_add_inline_style( 'stackboost-admin-themes', $css );
    }

    /**
     * Add current theme class to body (optional, but requested wrapper class is better).
     * The plan said to add it to the wrapper.
     * But the Prompt said: "Modify the main settings wrapper to inject the selected theme class".
     * Since we control the wrapper in Page.php, we do it there.
     *
     * However, if other modules (like Settings.php) render their own wrappers,
     * they need to know the class.
     *
     * We'll provide a static helper for that.
     */
    public static function get_active_theme_class() {
        $settings = get_option( 'stackboost_settings', [] );
        $theme = isset( $settings['admin_theme'] ) ? $settings['admin_theme'] : 'sb-theme-clean-tech';

        // Log theme retrieval
        if ( function_exists( 'stackboost_log' ) ) {
            stackboost_log( "Appearance: Retrieved active theme class: {$theme}", 'appearance' );
        }

        return $theme;
    }

    /**
     * AJAX Handler to save theme.
     */
    public function ajax_save_theme() {
        check_ajax_referer( 'stackboost_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $theme = isset( $_POST['theme'] ) ? sanitize_text_field( $_POST['theme'] ) : '';

        // Validate theme
        $valid_themes = [
            'sb-theme-wordpress-sync',
            'sb-theme-supportcandy-sync',
            'sb-theme-cloud-dancer',
            'sb-theme-heroic',
            'sb-theme-clean-tech',
            'sb-theme-hudson-valley-eco'
        ];

        if ( ! in_array( $theme, $valid_themes ) ) {
            wp_send_json_error( 'Invalid theme' );
        }

        $settings = get_option( 'stackboost_settings', [] );
        $settings['admin_theme'] = $theme;
        update_option( 'stackboost_settings', $settings );

        wp_send_json_success( 'Theme saved' );
    }

    public function add_theme_body_class( $classes ) {
        // Optional: Add theme class to body for global targeting if needed
        // $classes .= ' ' . self::get_active_theme_class();
        return $classes;
    }
}
