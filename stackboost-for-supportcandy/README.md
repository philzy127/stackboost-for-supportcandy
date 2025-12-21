# StackBoost - For SupportCandy

StackBoost - For SupportCandy is a powerful enhancement plugin for the SupportCandy helpdesk system in WordPress. It adds a suite of advanced features designed to streamline ticket management, improve agent workflow, and provide greater control over the user interface.

## Features

StackBoost includes several modules that can be enabled or disabled based on your needs.

### Core Enhancements
*   **Ticket View Popup:** Right-click any ticket in the list to see a quick "Details Card" popup.
    *   **Smart Layout:** Automatically switches to a side-by-side view if the content is too tall.
    *   **Interactive History:** Expand and collapse conversation history directly within the popup.
    *   **License Fallback:** gracefully degrades to standard fields if the PRO license is inactive.
*   **General Cleanup:** Automatically hide empty columns or the priority column to reduce clutter in the ticket list.
*   **Ticket Type Hiding:** Restrict which ticket types are visible to non-agent users in the submission form.
*   **After Hours Notice:** Display a customizable warning notice on the ticket form when users attempt to submit a ticket outside of configured business hours.

### Conditional Column Hiding
Create powerful, context-aware rules to control column visibility in the ticket list based on the active view (filter).

*   **SHOW ONLY:** Make a column visible *only* in a specific view and hide it everywhere else by default.
*   **HIDE:** Explicitly hide a column in a specific view.
*   **SHOW:** Create exceptions to override implicit hiding rules.

*Example:* Show the "Billing Code" column *only* when the "Accounting" view is active.

### Company Directory (Business Tier)
A complete system to manage staff, locations, and departments.
*   **Staff Profiles:** Detailed profiles with photo, contact info, and WordPress user linking.
*   **Contact Widget:** A dashboard widget on the ticket view showing the contact details of the ticket requester (if they are in the directory).
*   **Frontend Directory:** A searchable staff directory for your users.

### Onboarding Dashboard (Business Tier)
Streamline your employee onboarding process.
*   **Steps Sequence:** Define a drag-and-drop sequence of onboarding tasks.
*   **Progress Tracking:** Track the progress of new hires through the dashboard.
*   **PDF Certificates:** Automatically generate and email completion certificates.

### Unified Ticket Macro (Pro Tier)
Generate consistent, professional ticket summaries for email notifications.
*   **Customizable Fields:** Select exactly which fields to include in the email summary.
*   **Macro Support:** Use `{{stackboost_unified_ticket}}` in your SupportCandy email templates.

### After Ticket Survey (Pro Tier)
Collect customer satisfaction feedback automatically.
*   **Survey Builder:** Create custom surveys with various question types.
*   **Automation:** Automatically email the survey link when a ticket is closed.
*   **Highlander Rule:** Enforces a limit of one "Ticket Number" field per survey to prevent data conflicts.

### Diagnostics & Logging
A robust system for troubleshooting.
*   **Centralized Logging:** A master switch controls logging across all modules.
*   **Module-Level Control:** Enable file logging for specific modules (e.g., UTM, Directory) while keeping others silent.
*   **Browser Console Logs:** View debug information directly in the browser console when enabled.

## Installation

1.  Upload the `stackboost-for-supportcandy` folder to the `/wp-content/plugins/` directory.
2.  Activate the plugin through the 'Plugins' menu in WordPress.
3.  Navigate to the **StackBoost** menu to configure your settings.
4.  (Optional) Enter your license key in **StackBoost > General Settings** to activate Pro or Business features.

## Requirements

*   WordPress 6.0+
*   PHP 7.4+
*   SupportCandy (Free or Pro)
