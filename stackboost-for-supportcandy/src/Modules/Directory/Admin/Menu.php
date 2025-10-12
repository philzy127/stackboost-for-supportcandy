<?php

namespace StackBoost\ForSupportCandy\Modules\Directory\Admin;

/**
 * Class Menu
 *
 * Handles the admin menu for the directory, integrating it
 * correctly into the main StackBoost menu.
 *
 * @package StackBoost\ForSupportCandy\Modules\Directory\Admin
 */
class Menu {

    /**
     * Menu constructor.
     */
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_directory_submenu' ] );
    }

    /**
     * Add the "Company Directory" submenu and its items.
     */
    public function add_directory_submenu() {
        // Add the main "Company Directory" submenu page.
        add_submenu_page(
            'stackboost-for-supportcandy',
            'Company Directory',
            'Company Directory',
            'manage_options',
            'edit.php?post_type=chp_staff_directory'
        );

        // Add the "Tools" page as a submenu of the CPT.
        add_submenu_page(
            'edit.php?post_type=chp_staff_directory',
            'Directory Tools',
            'Tools',
            'manage_options',
            'stackboost-directory-tools',
            [ $this, 'render_tools_page' ]
        );
    }

    /**
     * Render the tools page with its own tabbed interface.
     */
    public function render_tools_page() {
        $current_tool_tab = $_GET['tool'] ?? 'import';
        ?>
        <div class="wrap">
            <h1>Directory Tools</h1>
            <nav class="nav-tab-wrapper">
                <a href="?post_type=chp_staff_directory&page=stackboost-directory-tools&tool=import" class="nav-tab <?php echo $current_tool_tab === 'import' ? 'nav-tab-active' : ''; ?>">Import</a>
                <a href="?post_type=chp_staff_directory&page=stackboost-directory-tools&tool=clear" class="nav-tab <?php echo $current_tool_tab === 'clear' ? 'nav-tab-active' : ''; ?>">Clear Data</a>
                <a href="?post_type=chp_staff_directory&page=stackboost-directory-tools&tool=how_to_use" class="nav-tab <?php echo $current_tool_tab === 'how_to_use' ? 'nav-tab-active' : ''; ?>">How to Use</a>
            </nav>
            <div class="tool-tab-content" style="margin-top: 20px;">
                <?php
                switch ( $current_tool_tab ) {
                    case 'clear':
                        (new Clearer())->render_page();
                        break;
                    case 'how_to_use':
                        (new How_To_Use())->render_page();
                        break;
                    case 'import':
                    default:
                        (new Importer())->render_page();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }
}