<?php

namespace StackBoost\ForSupportCandy\Modules\Directory\Admin;

/**
 * Class How_To_Use
 *
 * Renders the "How to Use" page.
 *
 * @package StackBoost\ForSupportCandy\Modules\Directory\Admin
 */
class How_To_Use {

    /**
     * Render the "How to Use" page.
     */
    public function render_page() {
        ?>
        <div class="wrap">
            <h2>How to Use the Directory</h2>

            <h3>Displaying the Directory</h3>
            <p>To display the staff directory on any page or post, use the following shortcode:</p>
            <p><code>[stackboost_directory]</code></p>

            <h3>Managing Staff</h3>
            <p>You can add, edit, and delete individual staff members from the "All Staff" section in the "Directory" menu.</p>

            <h3>Managing Locations and Departments</h3>
            <p>You can manage locations and departments from their respective sections under the "Directory" menu.</p>

            <h3>Importing Staff</h3>
            <p>You can bulk-import staff members from a CSV file using the "Import" tool. The CSV file should have a header row with the following columns: <code>name</code>, <code>job_title</code>, <code>department</code>, <code>office_phone</code>, <code>email</code>.</p>
        </div>
        <?php
    }
}