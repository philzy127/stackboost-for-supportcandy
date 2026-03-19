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
Eliminate "tab fatigue" and boost agent efficiency with a high-performance preview system accessible directly from the ticket list.
*   **Instant Context:** Right-click any ticket to launch a "Details Card" containing essential info and conversation history without leaving the main dashboard.
*   **Smart Responsive Layout:** The interface automatically adapts to a side-by-side view for longer tickets, maximizing screen real estate.
*   **Interactive History:** Expand or collapse full conversation threads instantly via AJAX-powered triggers for a lightning-fast experience.

### Core Enhancements
Take back control of your support dashboard with essential Quality of Life (QoL) refinements.
*   **Interface Cleanup:** Automatically declutter your workspace by hiding empty columns or force-hiding the Priority column.
*   **After Hours Notice:** Set clear boundaries by displaying customizable warning banners on ticket forms when users attempt to submit requests outside of your business hours.
*   **Enforced Resolution:** Hide the "Reply & Close" button for non-agents to ensure tickets only reach a 'Closed' status once your team has verified the solution.

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
Stop overwhelming agents with irrelevant data. Transform the ticket list into a context-aware workspace that adapts to the specific task at hand.
*   **Contextual Intelligence:** Set columns to "Show Only" in specific views (e.g., show "Order ID" only in the 'Billing' filter) and hide them everywhere else automatically.
*   **Precision Overrides:** Use HIDE and SHOW rules to create exceptions for specific workflows, ensuring the right data is always front and center.
*   **Grid Optimization:** Drastically reduce horizontal scrolling and visual noise by tailoring the ticket list to the unique needs of every department.

### Metrics *(NEW)* (Pro Tier)
Turn raw support data into a competitive advantage with deep, real-time visualization.
*   **Actionable Dashboards:** High-performance interactive charts provide a bird's-eye view of ticket volume, resolution trends, and queue health.
*   **Clean Data Reporting:** Filter out the noise by grouping agents into 'Tracked' vs 'Untracked' categories—perfect for keeping your charts focused on active support activity.
*   **Queue Health Monitoring:** Track initial response times and backlog growth at a glance to identify bottlenecks before they impact customer satisfaction.

### Queue Macro (Pro Tier)
Manage customer expectations automatically by providing total transparency into your support volume.
*   **Dynamic Place-in-Line:** Automatically calculates a user's position in the queue based on open tickets of the same type and notifies them instantly upon submission.
*   **Workflow Integration:** Plugs directly into the SupportCandy workflow builder. No manual counting or complex math required—just click to apply the macro to your automated replies.

### Chat Bubbles (Pro Tier)
A complete UI overhaul for ticket conversations. This independent module replaces the traditional "email-style" thread with a modern, intuitive chat interface.
*   **Thread Transformation:** Takes over the "Notes" and conversation sections of your tickets, turning long text blocks into easy-to-read interactive bubbles.
*   **Universal Utility:** Once enabled, this sleek interface is utilized across the product, providing a consistent experience in both the main ticket view and the Ticket Details Card.
*   **Customizable Themes:** Swap between several professionally designed themes or use the full styling suite to match your brand's exact aesthetic.

### After Ticket Survey (Pro Tier)
Close the loop on every interaction by automating your customer satisfaction (CSAT) feedback.
*   **Advanced Survey Builder:** Create custom feedback forms with various question types directly within your WordPress admin.
*   **Automated Dispatch:** StackBoost monitors your ticket statuses and automatically emails the survey link the moment a ticket is closed.
*   **Conflict Prevention:** Intelligent logic ensures data integrity by preventing duplicate submissions and enforcing structural limits (like one survey per unique Ticket ID).

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
