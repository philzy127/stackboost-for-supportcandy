# Developer Documentation

## Directory Module

### Overview
The Directory module handles Staff, Locations, and Departments. It has been refactored to use a `DirectoryService` as the single source of truth for data access.

### Key Classes
*   `StackBoost\ForSupportCandy\Services\DirectoryService`: The main service class. Use this for all data retrieval.
*   `StackBoost\ForSupportCandy\Modules\Directory\Admin\Management`: Handles the CSV import/export and management UI.
*   `StackBoost\ForSupportCandy\Modules\Directory\Shortcodes\DirectoryShortcode`: Renders the frontend directory table.

### Data Model
*   **Phone Numbers:** Stored as raw digits in post meta (`_stackboost_office_phone`, `_stackboost_mobile_phone`). Extensions are stored as part of the string if they were imported that way, but the formatting logic (`stackboost_format_phone_number`) handles cleaning this up for display.
*   **Search Logic:** The `DirectoryService::get_staff_members()` method accepts a `search` argument. This search uses a special "phone-aware" logic where it strips non-numeric characters from the search string and compares it against phone fields if the query looks like a number.

### Helper Functions
*   `stackboost_format_phone_number( $number )`: A global helper function located in `bootstrap.php`.
    *   **Input:** Raw phone number string.
    *   **Output:** HTML string containing the formatted number, a `tel:` link, and a copy-to-clipboard button.
    *   **Usage:** Use this in any template or output buffer where a phone number needs to be displayed. Do not manually format phone numbers.

## After Ticket Survey (ATS)

### Overview
The ATS module handles post-ticket surveys. It includes a custom question builder, frontend form rendering via shortcode, and a results reporting view.

### Architecture
*   **Admin UI:** The "Manage Questions" tab (`manage-questions-template.php`) uses a jQuery-based modal system for adding/editing questions. It now supports a `ticket_number` question type.
*   **AJAX:** All form actions (Save, Delete, Reorder) are handled via `Ajax.php` to provide a seamless experience without page reloads.
*   **Frontend Rendering:** `Shortcode.php` renders the form. For `ticket_number` questions, it renders a text input but enforces numeric validation server-side (`handle_submission`).
*   **Data Storage:**
    *   `wp_stackboost_ats_questions`: Stores question definitions.
    *   `wp_stackboost_ats_dropdown_options`: Stores options for dropdown questions.
    *   `wp_stackboost_ats_survey_submissions`: Stores metadata for each user submission.
    *   `wp_stackboost_ats_survey_answers`: Stores the actual answers linked to submissions and questions.

### URL Prefill Logic
*   The `Shortcode.php` class handles pre-filling.
*   It checks for a `prefill_key` property on the question object.
*   If found, it looks for that key in `$_GET`.
*   **Smart Matching (Dropdowns):** The logic in `Shortcode::render_question_field` calculates a "match score" based on exact match, prefix match, and substring match to select the most appropriate option from the URL value.

## Unified Ticket Macro (UTM)

### Overview
The UTM module generates a standardized HTML summary of a ticket for use in email notifications or external systems.

### Formatting Rules
*   Field names are forced to `white-space: nowrap` to prevent awkward wrapping.
*   Trailing colons are stripped from field labels before display.
*   Empty fields (or fields with "Not Applicable" or whitespace only) are automatically filtered out unless explicitly configured otherwise.
