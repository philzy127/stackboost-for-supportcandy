# StackBoost for SupportCandy - User Documentation

## Welcome to StackBoost

StackBoost enhances your SupportCandy helpdesk with powerful new features like a Company Directory, Onboarding Dashboard, and advanced automation tools.

## Getting Started

After activating the plugin, you will see a new **StackBoost** menu in your WordPress admin dashboard.

### General Settings

The **General Settings** page provides an overview of the plugin status and allows you to configure global options.

### Diagnostics

The **Diagnostics** page (formerly "Tools") gives you control over the system's health and debugging features.

*   **Enable Diagnostic Log:** This master switch turns on the logging system. Keep this OFF unless you are troubleshooting an issue.
*   **Module Logging:** This section allows you to turn on logging for specific features (e.g., "Unified Ticket Macro" or "Company Directory"). This is useful for troubleshooting a specific problem without generating too much noise.
*   **Log Actions:**
    *   **Download Log:** Download the current debug log file to share with support.
    *   **Clear Log:** Instantly clear the log file to start fresh.

### Company Directory

Manage your staff, locations, and departments.

*   **Staff:** Add and edit staff members. You can link them to WordPress users for advanced integration.
*   **Import:** Use the "Management" tab to import staff from a JSON file.
*   **Contact Widget:** Configure a widget to show staff contact details directly on the SupportCandy ticket view.

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
*   **Settings:** Configure color schemes and behavior.
*   **Integration:** The survey link is automatically added to closed ticket emails if configured.

## Support

If you encounter issues, please check the **Diagnostics** page to see if any errors are being logged. You can download the log file and send it to our support team for assistance.
