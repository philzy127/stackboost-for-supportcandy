# StackBoost for SupportCandy - User Documentation

## Welcome to StackBoost

StackBoost enhances your SupportCandy helpdesk with powerful new features like a Company Directory, Onboarding Dashboard, and advanced automation tools.

## Getting Started

After activating the plugin, you will see a new **StackBoost** menu in your WordPress admin dashboard.

### General Settings

The **General Settings** page provides an overview of the plugin status and allows you to configure global options.

*   **Feature Spotlight:** A new dynamic widget on the dashboard highlights key features available in your plan (or upgrades you might be missing). Use the carousel arrows to browse through the features and click "Learn More" to see details.
    *   **Context Aware:** The widget automatically adjusts based on your license tier (Lite, Pro, Business) to show you the most relevant tools.
    *   **Auto-Rotation:** The spotlight automatically cycles through features every 60 seconds, but you can pause it by hovering over the card.

### Diagnostics

The **Diagnostics** page (formerly "Tools") gives you control over the system's health and debugging features.

*   **Enable Diagnostic Log:** This master switch controls the entire logging system.
    *   **Console Logging:** When this switch is ON, debug messages will appear in your browser's developer console for all modules.
    *   **File Logging:** This switch must be ON for any logs to be written to the server's log file.
*   **Module Logging:** This section allows you to enable **file logging** for specific features (e.g., "Unified Ticket Macro" or "Company Directory").
    *   **How it works:** If the Master Switch is ON, you can selectively turn on file logging for individual modules to troubleshoot specific issues without cluttering the log file with unrelated data.
*   **Log Actions:**
    *   **Download Log:** Download the current debug log file to share with support.
    *   **Clear Log:** Instantly clear the log file to start fresh.

### Company Directory

Manage your staff, locations, and departments.

*   **Staff:** Add and edit staff members. You can link them to WordPress users for advanced integration.
*   **Import:** Use the "Management" tab to import staff from a JSON file.
*   **Contact Widget:** Configure a widget to show staff contact details directly on the SupportCandy ticket view.
*   **Directory Block:** Display your staff directory on any page using the `StackBoost Directory` block.
    *   **Visible Fields:** Toggle fields like Name, Phone, Email, Department, and Title.
    *   **Email Display:** Optionally display the staff member's email address (with a convenient copy button) directly under their name.
    *   **Photo Options:**
        *   **Photo Shape:** Choose between Circle, Square, Portrait, or Landscape styles for staff photos.
        *   **Gravatar Integration:** Enable "Prefer Gravatar" to prioritize Gravatar images, or "Fallback to Gravatar" to use them only when no custom photo is uploaded.

### Onboarding Dashboard

Streamline your employee onboarding process.

*   **Steps:** Define the sequence of onboarding tasks.
*   **Dashboard:** View the progress of new hires.
*   **Certificates:** Automatically generate and email PDF completion certificates.

### Unified Ticket Macro (UTM)

Create consistent, formatted ticket updates.

*   **Configuration:** Go to the "Unified Ticket Macro" page to select which fields to include in your macro.
*   **Usage:** Use the `{{stackboost_unified_ticket}}` macro in your SupportCandy email templates.

### After Ticket Survey (ATS)

Collect feedback from your users.

*   **Questions:** Design your survey using the "Manage Questions" tab.
    *   **Highlander Rule:** You can now only add one **Ticket Number** field per survey. If you attempt to add a second one, the option will be disabled.
    *   **Read-only if Pre-filled:** A new option in the question settings allows you to lock a field if it is populated via a URL parameter (e.g., `?ticket_id=123`). This is useful for preventing users from changing critical data like ticket numbers or technician names. The field will remain uneditable but will still be submitted with the form.
*   **Settings:** Configure color schemes and behavior.
*   **Integration:** The survey link is automatically added to closed ticket emails if configured.

### Ticket View

Enhance the ticket list with a quick-preview popup.

*   **Right-Click Preview:** Right-click any ticket in the list to see a "Details Card" popup.
*   **Smart Layout:** The card automatically switches to a side-by-side (horizontal) layout if the content is too tall to fit comfortably on the screen.
*   **Interactive:** You can expand and collapse sections (like "Conversation History") within the card.
*   **Pro Features:** Advanced layouts (Unified Ticket Macro view) are marked with a **PRO** badge in the settings and require an active Pro or Business license.

### After-Hours Notice

Display a custom warning message on the ticket submission form when your business is closed.

*   **Configuration:** You can now choose to inherit your schedule from SupportCandy or set it manually.
    *   **Use SupportCandy Working Hours:** When enabled, the plugin checks SupportCandy's "Working Hours" and "Exceptions" settings. Exceptions always take precedence.
    *   **Use SupportCandy Holiday Schedule:** When enabled, the plugin checks SupportCandy's "Holiday" list.
    *   **Hybrid Mode:** You can mix these settings. For example, if you use SupportCandy's Working Hours but have **manual holidays** entered in the text box below, the system will use the SupportCandy schedule *unless* today is listed in your manual holiday list, in which case it will show the closed notice.
*   **Manual Settings:**
    *   **After Hours Start:** The hour (0-23) when your business closes.
    *   **Before Hours End:** The hour (0-23) when your business opens.
    *   **Holidays:** A list of dates (one per line, MM-DD-YYYY or YYYY-MM-DD) when your business is closed all day.
*   **Important:** The system uses your **WordPress Timezone** setting (Settings > General). Ensure this matches your local time, or the notice may appear at the wrong time.

## Support

If you encounter issues, please check the **Diagnostics** page to see if any errors are being logged. You can download the log file and send it to our support team for assistance.
