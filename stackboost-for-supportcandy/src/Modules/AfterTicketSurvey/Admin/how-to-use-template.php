<?php
/**
 * Template for the "How to Use" tab in the After Ticket Survey admin page.
 */
?>
<div class="stackboost-ats-how-to-use">
    <p>This module allows you to easily create, customize, and manage after-ticket surveys to gather valuable feedback from your users.</p>

    <div class="stackboost-ats-admin-section">
        <h3>1. Display the Survey on a Page</h3>
        <p>To show the survey form on any page or post on your website, simply add the following shortcode to the content editor:</p>
        <pre><code>[stackboost_after_ticket_survey]</code></pre>
        <p>Once you add this, the survey form will appear on that page for your users to fill out.</p>
    </div>

    <div class="stackboost-ats-admin-section">
        <h3>2. Manage Your Survey Questions</h3>
        <p>You have full control over the questions in your survey. To add new questions, edit existing ones, or remove questions:</p>
        <ul>
            <li>Go to <strong>StackBoost → After Ticket Survey</strong> and click the <strong>Manage Questions</strong> tab.</li>
            <li>Here, you'll see a list of all your current survey questions.</li>
            <li>Use the "Add New Question" form to create new questions. You can choose from different types:
                <ul>
                    <li><strong>Short Text:</strong> For brief answers like a ticket number.</li>
                    <li><strong>Long Text:</strong> For detailed feedback or comments.</li>
                    <li><strong>Rating (1-5):</strong> For questions requiring a numerical rating.</li>
                    <li><strong>Dropdown:</strong> For questions with predefined options, like a list of technicians.</li>
                </ul>
            </li>
            <li>You can also <strong>Edit</strong> or <strong>Delete</strong> existing questions using the buttons next to each question in the table.</li>
        </ul>
    </div>

    <div class="stackboost-ats-admin-section">
        <h3>3. View and Manage Survey Results</h3>
        <p>Once users start submitting surveys, you can view and manage all the collected feedback:</p>
        <ul>
            <li>Go to the <strong>View Results</strong> tab to see all submissions in a table.</li>
            <li>Go to the <strong>Manage Submissions</strong> tab to delete any unwanted or test submissions.</li>
        </ul>
    </div>

    <div class="stackboost-ats-admin-section">
        <h3>4. Configure Your Settings</h3>
        <p>The settings page allows you to customize how the module works to better fit your needs:</p>
        <ul>
            <li>Go to the <strong>Settings</strong> tab.</li>
            <li>Here, you can configure:
                <ul>
                    <li><strong>Survey Page Background Color:</strong> Change the background color of the survey page to match your site's theme.</li>
                    <li><strong>Ticket Number Question:</strong> Tell the module which question asks for the ticket number. This makes the link from the results page to your ticketing system reliable.</li>
                    <li><strong>Technician Question:</strong> Specify which "Dropdown" question is used for technicians. This allows you to pre-fill the technician's name in the survey.</li>
                    <li><strong>Ticket System Base URL:</strong> Set the base URL for your ticketing system (e.g., `https://support.example.com/tickets/`). The module will append the ticket ID to this URL.</li>
                </ul>
            </li>
        </ul>
    </div>

    <div class="stackboost-ats-admin-section">
        <h3>5. Pre-filling Survey Data via URL</h3>
        <p>You can pre-fill the ticket number and technician fields by adding parameters to your survey URL. This is ideal for including in email notifications from your helpdesk system.</p>
        <p>The following parameters are supported:</p>
        <ul>
            <li><code>ticket_id</code>: This will populate the field you designated as the "Ticket Number Question".</li>
            <li><code>tech</code>: This will pre-select the value in the "Technician Question" dropdown. The value must match one of the options exactly.</li>
        </ul>
        <h4>Example URL:</h4>
        <pre><code>https://yourwebsite.com/survey-page/?ticket_id=12345&tech=John%20Doe</code></pre>
        <p><strong>Note:</strong> For this to work, you must first configure the "Ticket Number Question" and "Technician Question" on the <strong>Settings</strong> tab.</p>
    </div>
</div>