<?php

namespace StackBoost\ForSupportCandy\Modules\Directory\Admin;

/**
 * Class Menu
 *
 * Handles the admin menu and the tabbed interface for the directory tools.
 *
 * @package StackBoost\ForSupportCandy\Modules\Directory\Admin
 */
class Menu {

    /**
     * @var array The tool pages.
     */
    private array $tool_pages = [];

    /**
     * Menu constructor.
     */
    public function __construct() {
        $this->tool_pages['import'] = new Importer();
        $this->tool_pages['clear'] = new Clearer();
        $this->tool_pages['how_to_use'] = new How_To_Use();

        add_action( 'admin_menu', [ $this, 'add_main_menu' ] );
    }

    /**
     * Add the main "Directory" menu page.
     */
    public function add_main_menu() {
        add_menu_page(
            'Staff Directory',
            'Directory',
            'manage_options',
            'stackboost-directory',
            [ $this, 'render_main_page' ],
            'dashicons-groups',
            20
        );
    }

    /**
     * Render the main admin page with the tabbed interface.
     */
    public function render_main_page() {
        $current_tab = $_GET['tab'] ?? 'import';
        ?>
        <div class="wrap">
            <h1>StackBoost Directory Tools</h1>
            <nav class="nav-tab-wrapper">
                <a href="?page=stackboost-directory&tab=import" class="nav-tab <?php echo $current_tab === 'import' ? 'nav-tab-active' : ''; ?>">Import</a>
                <a href="?page=stackboost-directory&tab=clear" class="nav-tab <?php echo $current_tab === 'clear' ? 'nav-tab-active' : ''; ?>">Clear Data</a>
                <a href="?page=stackboost-directory&tab=how_to_use" class="nav-tab <?php echo $current_tab === 'how_to_use' ? 'nav-tab-active' : ''; ?>">How to Use</a>
            </nav>

            <div class="tab-content">
                <?php
                if ( isset( $this->tool_pages[ $current_tab ] ) ) {
                    $this->tool_pages[ $current_tab ]->render_page();
                }
                ?>
            </div>
        </div>
        <?php
    }
}