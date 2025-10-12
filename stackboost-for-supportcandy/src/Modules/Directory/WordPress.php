<?php

namespace StackBoost\ForSupportCandy\Modules\Directory;

/**
 * WordPress integration class for the Directory service.
 *
 * This class acts as the bridge between the Directory's core logic and the
 * broader WordPress environment. It ensures the Core service is instantiated
 * and accessible to other parts of the StackBoost plugin.
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
     * The Core service instance.
     *
     * @var Core
     */
    public Core $core;

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
     * It initializes the Core service and hooks into WordPress.
     */
    private function __construct() {
        $this->core = Core::get_instance();

        // To hide the test page, comment out the line below.
        add_action( 'admin_menu', [ $this, 'add_test_page_submenu' ] );
    }

    /**
     * Add the test page as a submenu under the main StackBoost menu.
     */
    public function add_test_page_submenu() {
        add_submenu_page(
            'stackboost-for-supportcandy',          // Parent slug
            'Directory Service Test',               // Page title
            'Directory Test',                       // Menu title
            'manage_options',                       // Capability
            'stackboost-directory-test',            // Menu slug
            [ $this, 'render_test_page' ]           // Callback function
        );
    }

    /**
     * Render the content for the Directory Service test page.
     */
    public function render_test_page() {
        ?>
        <div class="wrap">
            <h1><span class="dashicons-admin-generic" style="font-size: 1.2em; margin-right: 5px;"></span>StackBoost - Directory Service Test</h1>
            <p>Use this page to test the integration with the Company Directory plugin. Enter an email address of a user who exists in the Company Directory to see the results.</p>

            <form method="POST" action="">
                <?php wp_nonce_field( 'stackboost_directory_test_nonce' ); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">
                            <label for="test_email">Email Address to Test</label>
                        </th>
                        <td>
                            <input type="email" id="test_email" name="test_email" value="<?php echo isset( $_POST['test_email'] ) ? esc_attr( $_POST['test_email'] ) : ''; ?>" class="regular-text" placeholder="employee@example.com" required />
                        </td>
                    </tr>
                </table>
                <?php submit_button( 'Run Test' ); ?>
            </form>

            <?php
            if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['test_email'] ) ) {
                // Verify nonce
                if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'stackboost_directory_test_nonce' ) ) {
                    echo '<div class="notice notice-error"><p>Security check failed. Please try again.</p></div>';
                    return;
                }

                $email_to_test = sanitize_email( $_POST['test_email'] );

                echo '<div id="test-results" style="margin-top: 20px; padding: 15px; border: 1px solid #ccd0d4; background-color: #fff; box-shadow: 0 1px 1px rgba(0,0,0,.04);">';
                echo '<h2>Test Results</h2>';

                // --- Test 1: Find Profile ---
                echo '<h3>Test 1: Find Profile by Email</h3>';
                echo '<p>Searching for profile with email: <strong>' . esc_html( $email_to_test ) . '</strong></p>';
                $profile_id = $this->core->find_employee_profile( $email_to_test );
                if ( $profile_id ) {
                    echo '<p style="color:green; font-weight: bold;">✔ Success! Found profile ID: ' . esc_html( $profile_id ) . '</p>';
                } else {
                    echo '<p style="color:red; font-weight: bold;">✘ Failed. Could not find a profile for this email.</p>';
                }
                echo '<hr>';

                // --- Test 2: Get Employee Data ---
                echo '<h3>Test 2: Get Employee Data</h3>';
                if ( $profile_id ) {
                    $employee_data = $this->core->get_employee_data( $profile_id );
                    if ( ! empty( $employee_data ) ) {
                        echo '<p style="color:green; font-weight: bold;">✔ Success! Retrieved the following data:</p>';
                        echo '<pre style="background-color: #f3f3f3; padding: 10px; border-radius: 4px;">';
                        print_r( $employee_data );
                        echo '</pre>';
                    } else {
                        echo '<p style="color:red; font-weight: bold;">✘ Failed. Could not retrieve data for profile ID ' . esc_html( $profile_id ) . '.</p>';
                    }
                } else {
                    echo '<p style="color:orange; font-weight: bold;">- Skipped. No profile ID was found in Test 1.</p>';
                }
                echo '<hr>';

                // --- Test 3: Get Employee Manager ---
                echo '<h3>Test 3: Get Employee Manager (Placeholder)</h3>';
                if ( $profile_id ) {
                    $manager_id = $this->core->get_employee_manager( $profile_id );
                    echo '<p><strong>Result:</strong> The placeholder function returned: <strong>' . esc_html( $manager_id ) . '</strong> (This is expected to be 0).</p>';
                } else {
                    echo '<p style="color:orange; font-weight: bold;">- Skipped. No profile ID was found in Test 1.</p>';
                }
                echo '</div>';
            }
            ?>
        </div>
        <?php
    }
}