=== StackBoost - For SupportCandy ===
Contributors: StackBoost
Tags: supportcandy, helpdesk, support, ticket system
Requires at least: 6.0
Tested up to: 6.9
Stable tag: 1.6.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

StackBoost enhances SupportCandy with advanced workflow, UI controls, and powerful add-on modules.

## Features

StackBoost includes several modules that can be enabled or disabled based on your needs.

### Ticket View Card
Boost agent efficiency with a powerful, right-click "Details Card" popup directly from the ticket list.
*   **Smart Layout:** Automatically adapts to a side-by-side view for longer tickets, maximizing screen real estate.
*   **Interactive History:** Expand and collapse full conversation threads instantly without leaving the main dashboard.

### Core Enhancements
Take back control of your support dashboard and refine the user experience.
*   **General Cleanup:** Automatically hide empty, unused columns or force-hide the priority column to reduce visual clutter.
*   **After Hours Notice:** Display a clear, customizable warning banner on the ticket form when users attempt to submit requests outside of your configured business hours.
*   **Prevent Premature Closing:** Hide the "Reply & Close" button for non-agent users to ensure tickets stay open until support has fully resolved the issue.

### Conditional Options
Take granular control over your ticket forms by defining exactly which options are visible to specific user groups. Using an intuitive administration matrix, you can block specific WordPress or SupportCandy roles from seeing individual choices within multiple-choice fields (such as dropdowns, checkboxes, and radio buttons).
*   **Smart Visibility Rules:** Tailor your forms based on WordPress roles or SupportCandy agent roles.
*   **Broad Field Support:** Works seamlessly with standard fields (Category, Priority, Status) and custom option-based fields.
*   **Zero Distortion:** Options are filtered in real-time on the frontend without requiring page reloads or slowing down the user experience.
*   **Enterprise-Grade Security:** Unlike tools that only hide elements visually, StackBoost includes server-side enforcement. This ensures that restricted options are stripped out during submission, preventing unauthorized data from ever reaching your database.

### Date & Time Formatting
Enhance the user experience by enforcing unified Date and Time formats across your support desk.
*   **Rules-Based Styling:** Create precise formatting rules that apply dynamically to target date/time columns in your ticket list.
*   **Timezone Safe:** Correctly parses and applies local WordPress timezone offsets to all displayed timestamps to completely prevent confusion for international users and remote teams.

<!-- <stackboost-pro-only> -->
### Unified Ticket Macro (Pro Tier)
Our most powerful feature! Generate consistent, professional ticket summaries for email notifications to drastically improve communication clarity.
*   **Native GUI Integration:** Select the macro directly from the default SupportCandy email macro dropdown. No need to memorize complex shortcodes!
*   **Customizable Fields:** Select exactly which fields to include in the email summary.

### Conditional Views (Pro Tier)
Create powerful, context-aware rules to control column visibility in the ticket list based on the active view (filter).
*   **SHOW ONLY:** Make a column visible *only* in a specific view and hide it everywhere else by default.
*   **HIDE:** Explicitly hide a column in a specific view.
*   **SHOW:** Create exceptions to override implicit hiding rules.

### Metrics *(NEW)* (Pro Tier)
Gain deep, actionable insights into your support desk performance.
*   **Visual Dashboards:** Beautiful, interactive charts detailing ticket volume, resolution rates, and agent activity.
*   **Agent Tracking:** Group agents into tracked vs untracked categories to prevent chart pollution.
*   **Queue Health:** Monitor active backlogs and initial response times at a glance.

### Queue Macro (Pro Tier)
Manage user expectations instantly by calculating their place in line.
*   **Dynamic Counting:** Automatically counts open tickets by type to accurately notify the recipient of their specific queue position upon new ticket submission.
*   **Native GUI Integration:** Available directly inside the default SupportCandy workflow builder GUI. Just click and apply!

### Chat Bubbles (Pro Tier)
Transform standard ticket threads into a modern, easy-to-read chat bubble interface within the Ticket View Card.
*   **Full Customization:** Completely customizable appearance allowing you to set the exact look and feel you desire.
*   **Included Themes:** Instantly swap to one of the many professionally designed, pre-built theme options.

### After Ticket Survey (Pro Tier)
Collect customer satisfaction feedback automatically.
*   **Survey Builder:** Create custom surveys with various question types.
*   **Automation:** Automatically email the survey link when a ticket is closed.
*   **Highlander Rule:** Enforces a limit of one "Ticket Number" field per survey to prevent data conflicts.

### Contact Directory (Business Tier)
A complete system to manage staff, locations, and departments.
*   **Staff Profiles:** Detailed profiles with photo, contact info, and WordPress user linking.
*   **Contact Widget:** A dashboard widget on the ticket view showing the contact details of the ticket requester (if they are in the directory).
*   **Frontend Directory:** A searchable staff directory for your users.

### Onboarding Dashboard (Business Tier)
Streamline your employee onboarding process.
*   **Steps Sequence:** Define a drag-and-drop sequence of onboarding tasks.
*   **Progress Tracking:** Track the progress of new hires through the dashboard.
*   **PDF Certificates:** Automatically generate and email completion certificates.
<!-- </stackboost-pro-only> -->

### Diagnostics & Logging
A robust system for troubleshooting.
*   **Centralized Logging:** A master switch controls logging across all modules.
*   **Module-Level Control:** Enable file logging for specific modules (e.g., UTM, Directory) while keeping others silent.
*   **Browser Console Logs:** View debug information directly in the browser console when enabled.

## 3rd-Party Libraries

This plugin utilizes the following 3rd-party open-source libraries:
*   **SelectWoo (v1.0.8):** A fork of Select2 by WooCommerce, used for enhanced select boxes. [Repository](https://github.com/woocommerce/selectWoo)
<!-- <stackboost-pro-only> -->
*   **DataTables (v2.3.6):** Used for advanced table sorting and filtering in premium modules. [Website](https://datatables.net/)
<!-- </stackboost-pro-only> -->
*   **Tippy.js (v6.0):** Used for tooltips. [Website](https://atomiks.github.io/tippyjs/)
*   **Popper.js (v2.0):** Used as a positioning engine for Tippy.js. [Website](https://popper.js.org/)
*   **jQuery UI:** Used for date pickers and drag-and-drop interfaces. [Website](https://jqueryui.com/)

## Source Code

The full source code and build tools for this plugin are publicly maintained on GitHub:
[https://github.com/stackboost/stackboost-for-supportcandy](https://github.com/stackboost/stackboost-for-supportcandy)

## Installation

1.  Upload the `stackboost-for-supportcandy` folder to the `/wp-content/plugins/` directory.
2.  Activate the plugin through the 'Plugins' menu in WordPress.
3.  Navigate to the **StackBoost** menu to configure your settings.
4.  (Optional) Enter your license key in **StackBoost > General Settings** to activate Pro or Business features.

## Requirements

*   WordPress 6.0+
*   PHP 7.4+
*   SupportCandy (Free or Pro)
