<?php

namespace StackBoost\ForSupportCandy\Modules\Directory;

use StackBoost\ForSupportCandy\Modules\Directory\Admin\Admin_List;
use StackBoost\ForSupportCandy\Modules\Directory\Admin\Menu;
use StackBoost\ForSupportCandy\Modules\Directory\Admin\Meta_Boxes;
use StackBoost\ForSupportCandy\Modules\Directory\Admin\Importer;
use StackBoost\ForSupportCandy\Modules\Directory\Admin\Clearer;
use StackBoost\ForSupportCandy\Modules\Directory\Admin\How_To_Use;

/**
 * WordPress integration class for the Directory module.
 *
 * This class acts as the bridge between the Directory's core logic and the
 * broader WordPress environment.
 *
 * @package StackBoost\ForSupportCandy\Modules\Directory
 */
final class WordPress {

    /**
     * The single instance of the class.
     *
     * @var WordPress|null
     */
    private static ?WordPress $instance = null;

    /**
     * Get the single instance of the class.
     *
     * @return WordPress
     */
    public static function get_instance(): WordPress {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor to prevent direct instantiation.
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load the required files and instantiate the classes.
     */
    private function load_dependencies() {
        // Core
        require_once __DIR__ . '/Cpt.php';
        require_once __DIR__ . '/Shortcode.php';
        new Cpt();
        new Shortcode();

        // Admin
        if ( is_admin() ) {
            require_once __DIR__ . '/Admin/Meta_Boxes.php';
            require_once __DIR__ . '/Admin/Admin_List.php';
            require_once __DIR__ . '/Admin/Importer.php';
            require_once __DIR__ . '/Admin/Clearer.php';
            require_once __DIR__ . '/Admin/How_To_Use.php';
            require_once __DIR__ . '/Admin/Menu.php';

            new Meta_Boxes();
            new Admin_List();
            new Menu();
        }
    }

    /**
     * Initialize WordPress hooks.
     */
    private function init_hooks() {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    /**
     * Enqueue frontend scripts and styles for the directory.
     */
    public function enqueue_assets() {
        global $post;
        if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'stackboost_directory' ) ) {
            // Enqueue DataTables CSS
            wp_enqueue_style( 'datatables-css', 'https://cdn.datatables.net/1.10.25/css/jquery.dataTables.min.css', [], '1.10.25' );

            // Enqueue custom CSS
            wp_enqueue_style(
                'stackboost-directory',
                \STACKBOOST_PLUGIN_URL . 'assets/css/stackboost-directory.css',
                [],
                \STACKBOOST_VERSION
            );

            // Enqueue DataTables JS
            wp_enqueue_script( 'datatables-js', 'https://cdn.datatables.net/1.10.25/js/jquery.dataTables.min.js', [ 'jquery' ], '1.10.25', true );

            // Enqueue custom JS
            wp_enqueue_script(
                'stackboost-directory',
                \STACKBOOST_PLUGIN_URL . 'assets/js/stackboost-directory.js',
                [ 'jquery', 'datatables-js' ],
                \STACKBOOST_VERSION,
                true
            );
        }
    }
}