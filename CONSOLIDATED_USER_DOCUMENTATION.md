# StackBoost for SupportCandy - Consolidated User Documentation

## Welcome to StackBoost

StackBoost is a comprehensive extension suite for SupportCandy, adding features like a company directory, onboarding workflows, advanced ticket customization, and automation tools.

This manual covers every function available within the plugin.

---

## 1. General Settings & Diagnostics

### General Settings
Located at **StackBoost > General Settings**.
*   **Overview:** Displays the current status of all StackBoost modules.
*   **License:** Input your license key to activate updates and premium features.

### Diagnostics
Located at **StackBoost > Diagnostics**. This page is for troubleshooting and system health.

*   **Master Logging Switch:**
    *   **Enable Diagnostic Log:** Toggles the entire logging system on or off.
    *   **Module Logging:** Individual toggles to enable file logging for specific modules (e.g., "Company Directory", "Unified Ticket Macro"). Use this to isolate issues.
*   **Actions:**
    *   **Download Log:** Downloads the `debug.log` file to your computer.
    *   **Clear Log:** Erases the current log file content.

---

## 2. Company Directory

Located at **StackBoost > Company Directory**. This module manages staff profiles for integration with tickets.

### Tab: Staff
*   **Add New:** Creates a new staff member.
    *   **Name:** Full name of the employee.
    *   **Job Title:** Position or role.
    *   **Email:** Work email address (used for matching tickets).
    *   **Phone Numbers:**
        *   **Office / Mobile:** Auto-formats as `(xxx) xxx-xxxx`.
        *   **Extension:** Optional extension for office lines.
    *   **Location / Department:** Select from pre-configured options.
    *   **Linked WordPress User:** Search and select a WP user account to permanently link this profile.
*   **Edit:** Click any row to modify details.

### Tab: Departments
Manage internal teams (e.g., "IT", "HR").
*   **Add New:** Create a department name.
*   **Usage:** Used for filtering staff and displaying info on the ticket widget.

### Tab: Locations
Manage physical sites (e.g., "Building A", "New York Office").
*   **Add New:** Create a location name.

### Tab: Settings
Global configuration for the Directory module.
*   **Listing Display Mode:**
    *   **Modal View:** Clicking a staff name in the frontend directory opens a popup.
    *   **Page View:** Opens a dedicated URL for the staff profile.
*   **Revisions to Keep:** Set the number of revisions to save for Staff, Locations, and Departments. (0 to disable, -1 for unlimited).
*   **Contact Widget:**
    *   **Enable:** Toggle the widget on SupportCandy ticket pages.
    *   **Position:** Choose where the widget appears (e.g., Sidebar Top, Sidebar Bottom).
*   **Permissions:** Checkboxes to allow specific roles (Editor, Author, etc.) to:
    *   Create/Edit/Delete Staff.
    *   Access the Management Tab.

### Tab: Management (Tools)
Bulk actions for data administration.
*   **Import Staff from CSV:**
    *   Upload a `.csv` file to bulk-add users.
    *   **Required Columns:** `Name, Email, Office Phone, Extension, Mobile Phone, Job Title, Department/Program`.
*   **Clear Directory Data:** Deletes all Staff profiles (keeps Locations/Departments).
*   **Fresh Start:** Permanently deletes ALL Directory data (Staff, Locations, Departments). **Irreversible.**
*   **Legacy Migration:** (If applicable) One-click button to migrate data from the old "CHP Staff Directory" plugin.

### Frontend Shortcode
Place `[stackboost_directory]` on any page to display the searchable staff table.

---

## 3. Onboarding Dashboard

Located at **StackBoost > Onboarding Dashboard**. Manages new hire checklists and certificates.

### Tab: Steps (Checklist)
*   **Add Step:** Create a new task (e.g., "Setup Email").
*   **Reorder:** Drag and drop rows to change the sequence.
*   **Staff Assignment:** (Optional) Assign default staff to specific steps.

### Tab: Staff
Displays a table of active onboarding tickets.
*   **Columns:** Customizable via Settings.
*   **Status:** Shows the completion percentage of the checklist.

### Tab: Certificate
Configures the PDF content generated upon completion.
*   **Company Name:** The header text for the certificate.
*   **Opening Statement:** The introductory text. Supports placeholders like `[Trainer Name]`, `[Staff Name]`, `[Date]`.
*   **Footer Text:** Text at the bottom of the certificate.

### Tab: Settings
Configures the core logic of the module.

*   **General Configuration:**
    *   **Request Type Field:** Select the SupportCandy field that identifies the type of request.
    *   **Onboarding Option:** Select the specific value that triggers the onboarding workflow.
    *   **Inactive Statuses:** Select statuses (e.g., "Closed") that should hide tickets from the active onboarding list.
    *   **Logic Fields:** Map your SupportCandy fields to the internal logic for "Staff Name", "Onboarding Date", and "Onboarding Cleared".
*   **Phone Configuration:**
    *   **Single Field Mode:** If you use one phone field, optionally link a "Phone Type" field to identify mobile numbers.
    *   **Multiple Fields Mode:** Map specific SupportCandy fields to phone types (Mobile, Work, Home, Fax).
*   **Staff Table Columns:**
    *   **Available/Selected Fields:** Customize which columns appear in the "Staff" tab.
    *   **Renaming Rules:** Rename column headers for the staff table.

---

## 4. Conditional Options

Located at **StackBoost > Conditional Options**.

Controls the visibility of specific options within dropdown/radio fields based on the user's role.

*   **Feature Status:** Master switch to enable/disable all conditional option rules globally.

### Manage Rules
*   **Table View:**
    *   **Target Field:** The SupportCandy field being modified.
    *   **Context:** Whether the rule applies based on "WP Roles" or "SupportCandy Roles".
    *   **Customized Options:** Lists the specific options (e.g., "High", "Low") that have active restrictions.
*   **Add New Rule:**
    1.  **Select Field:** Choose a field from the dropdown. Only fields that support options (Dropdown, Radio, Checkbox, Status, Priority, Category) are available. Fields with existing rules are disabled to prevent conflicts.
    2.  **Select Context:** Choose the role context:
        *   **SupportCandy Roles:** Applies to Agents based on their SupportCandy role. Also includes special roles for Guests and Non-Agent Users.
        *   **WP Roles:** Applies based on standard WordPress roles (Administrator, Editor, etc.) and Guest.
        *   **Note:** You cannot mix contexts. Once you select roles in one context, the other context is locked. To switch, uncheck all roles first.
    3.  **Configure Matrix:**
        *   The matrix lists every option for the selected field.
        *   Click the role names (e.g., "Agent", "Manager") to **BLOCK** that option for that role.
        *   **Red (Selected):** The user with this role CANNOT see this option.
        *   **White (Unselected):** The option is visible.

### Special Role Targets
In addition to standard roles, you can target specific user states:
*   **Guest / Visitor:** Targets any user who is **not logged in** to WordPress. Available in both contexts.
*   **User (SupportCandy Context):** Targets any user (logged in or guest) who **does not have a SupportCandy Agent role**. This is useful for creating rules that apply to all customers/end-users regardless of their WP role.

---

## 5. After Ticket Survey (ATS)

Located at **StackBoost > After Ticket Survey**. Collects user feedback.

### Tab: Manage Questions
Create and configure your survey questions here.

*   **Add Question:**
    *   **Question Type:**
        *   **Ticket Number:** Special field for linking results to tickets. Only one allowed per survey.
        *   **Short/Long Text:** Standard text inputs.
        *   **Rating (1-5):** A 5-star style rating scale.
        *   **Dropdown:** Create a select menu (e.g., for Technicians).
    *   **URL Parameter (Prefill Key):** Define a URL parameter (e.g., `ticket_id`) to pre-fill this answer.
    *   **Read-only if Pre-filled:** If checked, the user cannot change the answer if it was populated via the URL.
    *   **Required:** Marks the question as mandatory.

### Tab: View Results / Manage Submissions
*   **Results:** Table of all feedback received.
*   **Delete:** Remove specific entries.

### Usage
*   **Shortcode:** `[stackboost_after_ticket_survey]`
*   **Pre-filling Data:**
    *   **URL Parameters:** Add query parameters matching your **Prefill Key**.
    *   *Example:* `https://yoursite.com/survey/?ticket_id=123&tech=John`

---

## 6. Unified Ticket Macro (UTM)

Located at **StackBoost > Unified Ticket Macro**. Standardizes email notifications.

### General Settings
*   **Enable Feature:** Toggles the UTM system.

### Fields to Display
*   **Available Fields:** A list of all SupportCandy fields.
*   **Selected Fields:** Drag fields here to include them in the email summary.
*   **Field Order:**
    *   **Manual:** Drag items up/down in the "Selected Fields" box to set the order.
    *   **Use SupportCandy Field Order:** Checkbox. If enabled, fields follow the global order set in *SupportCandy > Ticket Form Fields*, ignoring manual sorting here.

### Rename Field Titles
*   **Renaming Rules:** Create rules to change how a field label appears in the email.
    *   *Example:* Rename "ID" to "Ticket #".
    *   **Display:** Select the field (e.g., ID).
    *   **As:** Enter the new label (e.g., Ticket #).

### Usage
In SupportCandy Email Templates, use `{{stackboost_unified_ticket}}`. This replaces the entire body with a formatted HTML table containing your selected fields.

---

## 7. After-Hours Notice

Located at **StackBoost > After-Hours Notice**.

### General
*   **Enable Notice:** Master On/Off switch.
*   **Message:** The text shown to users (e.g., "We are closed.").
*   **Type:** Info (Blue), Warning (Yellow), Error (Red).
*   **Add Notice to Emails:** If enabled, prepends the after-hours message to email notifications sent during closed hours.

### Schedule
*   **Business Hours:**
    *   **Start Time:** Enter the hour (0-23) when you open (e.g., `8` for 8 AM).
    *   **End Time:** Enter the hour (0-23) when you close (e.g., `17` for 5 PM).
*   **Weekends:** Check "Include All Weekends" to close all day on Sat/Sun.
*   **Holidays:** Enter dates in `MM-DD-YYYY` format (one per line) to close for specific days.

### Display Locations
Checkboxes to show the notice on:
*   **Ticket List:** The user's dashboard.
*   **Individual Ticket:** The ticket detail view.
*   **Create Ticket Form:** The submission page.

---

## 8. Ticket View Customizer

Located at **StackBoost > Ticket View**. Customizes the ticket detail page UI.

### Organization & Cleanup
*   **Hide Admin Bar:** Hides the black WordPress admin bar for non-admin users viewing tickets.
*   **Hide Ticket Types:** A text area to input Ticket Types (one per line). These types will be **hidden** from the "Create Ticket" dropdown but remain in the system for historical records.
*   **Hide Empty Columns:** Automatically hides any column in the ticket list that is completely empty for the current view.
*   **Hide Priority Column:** Hides the "Priority" column if all visible tickets have a priority of "Low".

### Enhanced UI
*   **Enable Ticket Details Card:** Enables a feature where right-clicking a ticket in the list shows a quick details card.
*   **Page Last Loaded:** Shows the time when the ticket list was last refreshed (configurable placement).

### Field Grouping Section
Allows you to organize custom fields into collapsible sections.
*   **Add Group:** Create a new section header (e.g., "Device Details").
*   **Drag & Drop:** Move fields into these groups.
*   **Result:** On the frontend ticket page, fields are visually grouped under these headers.

---

## 9. Date & Time Formatting

Located at **StackBoost > Date & Time**.

### Rules
Allows you to override how dates are displayed in ticket lists.
*   **Add Rule:**
    *   **Column:** Select a date field (Date Created, Date Closed, Last Reply, or Custom Fields).
    *   **Format Type:**
        *   **Date Only:** e.g., "October 5, 2023".
        *   **Time Only:** e.g., "2:30 PM".
        *   **Date & Time:** Both.
        *   **Custom:** Use a PHP date string (e.g., `d-m-Y`).
    *   **Options:**
        *   **Use Long Date:** Switches from `10/05/2023` to `October 5, 2023`.
        *   **Show Day of Week:** Adds "Monday, ..." to the start.

---

## 10. Conditional Views

Located at **StackBoost > Conditional Views**.

### Function
Dynamically hides columns in the ticket list based on the current View (Filter).

### Rule Builder
*   **Action:**
    *   **SHOW:** Display the column.
    *   **SHOW ONLY:** Display *only* this column (and hide others).
    *   **HIDE:** Remove the column.
*   **Column:** Select the specific SupportCandy column.
*   **Condition:**
    *   **WHEN IN VIEW:** Applies when the user is on the selected View.
    *   **WHEN NOT IN VIEW:** Applies on all other Views.
*   **View:** Select the SupportCandy Filter (e.g., "My Open Tickets", "Closed Tickets").

*Example:* "HIDE [Due Date] WHEN IN VIEW [Closed Tickets]".

---

## 11. Queue Macro

Located at **StackBoost > Queue Macro**.

### Settings
*   **Non-Closed Statuses:** Select which ticket statuses should count as "Active" or "In Queue".
    *   **Available:** Statuses not currently counted.
    *   **Selected:** Statuses that count toward the queue total.
*   **Test Queue Counts:** A button to calculate and display the current queue count based on your settings for verification.

### Usage
*   **Macro:** `{{stackboost_queue_count}}`
*   **Function:** Inserts the number of tickets that match the "Selected" statuses defined above.
*   **Usage:** Useful for auto-response emails: "There are currently {{stackboost_queue_count}} tickets ahead of you."

---

## 12. Privacy & GDPR Compliance

StackBoost is designed with strict data privacy principles ("Zero External Leakage") to ensure compliance with GDPR and other privacy regulations.

### Zero External Leakage
*   **No CDNs:** The plugin does not load scripts or styles from external CDNs (like Google Fonts or jQuery CDN). All assets are bundled locally.
*   **PDF Generation:** The internal PDF engine (DOMPDF) is configured to **strictly block** any remote resource requests. Images in PDFs must be local server files.

### Gravatar Consent Gate
By default, the plugin uses local placeholders for staff photos.
*   **Enable Gravatars:** If you wish to use Gravatars (which requires sending user email hashes to Automattic), you can enable this in the **Company Directory** block settings or shortcode.
*   **Warning:** The system will present a mandatory consent warning requiring you to acknowledge the privacy implications (IP address exposure to a third party) before enabling this feature.

### Data Rights (Export & Erasure)
StackBoost integrates with WordPress's native privacy tools found under **Tools > Export Personal Data** and **Tools > Erase Personal Data**.

*   **Export:** When an administrator exports data for a user email address, StackBoost will include the full Staff Directory profile (Name, Title, Phones, etc.) in the report.
*   **Erasure:** When an administrator processes an erasure request for an email address, StackBoost will locate the corresponding Staff Directory entry and **move it to the Trash**.
    *   *Note:* The item is moved to Trash rather than permanently deleted immediately, providing a safety net against accidental deletion. An administrator can then permanently delete it from the Trash.
