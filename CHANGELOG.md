Change Log
==========

1.6.5
-----
*   Added: Unified Ticket Macro (UTM) - Response Placement option (`beside`, `below`, `mobile_only` / Automatic) to position field responses below questions for improved ticket readability, with automatic responsive mobile stacking.
*   Added: Directory - Google Contacts Auto Sync via Google People API. Users can bulk-sync the directory to their Google Workspace contacts directly from the frontend using SSO login hints.
*   Added: Ticket Metrics - AI Trend Analysis tool powered by Google Gemini (`gemini-flash-lite-latest`) to generate automated HTML summaries of unclassified "Other" ticket descriptions.
*   Added: Ticket Metrics - Service Level Agreement (SLA) threshold tracking. Administrators can define target Response and Resolution times in hours, and the dashboard automatically computes dynamic SLA Breach percentages.
*   Added: Ticket Metrics - "Average Touches" metric to calculate the mean number of threads/replies per ticket within the reporting period.
*   Added: Ticket Metrics - Interactive "Submission Heatmap" visualizing ticket creation volume density by Day of the Week and Hour of the Day based on the localized WordPress timezone.
*   Added: Ticket Metrics - Deep integration with the After Ticket Survey module. Dynamically queries the native `stackboost_ats_questions` schema to map surveys to closed tickets and computes exact Agent-level Survey Response Rates and Average CSAT Scores.
*   Added: Ticket Metrics - "Max CSAT Score" normalization setting to format raw aggregate survey calculations into human-readable percentage outputs (e.g., `4.2 / 5 (84%)`).
*   Added: Ticket Metrics - "Secondary Chart Type" setting to allow selecting distinct chart visuals (Pie, Bar, Radar, etc.) explicitly for subcategory breakdown modals, or disabling them entirely (`None`).
*   Improved: Ticket Metrics - Completely refactored the entire dashboard HTML into an auto-flowing CSS Grid. Replaced rigid HTML tables with strict Flexbox columns to guarantee symmetrical vertical alignment of all data cards.
*   Improved: Ticket Metrics - Implemented a highly secure masking state for the Gemini API Key input. Once saved, keys display as `********` and require an explicit confirmation prompt to "Deactivate / Remove" before they can be overwritten.
*   Fixed: Ticket Metrics - Corrected the CSS padding discrepancies between Left and Right-sided columns that caused visual staggering.
*   Fixed: Ticket Metrics - Added robust boolean configuration flags (`is_sla_configured`, `is_survey_configured`) passed from the backend payload to perfectly control UI card visibility instead of using brittle `N/A` string-comparison logic in JavaScript.
*   Fixed: Ticket Metrics - Implemented `TRIM(a.answer_value) REGEXP '^[0-9]+'` inside CSAT SQL parsing, safely extracting leading numeric scores even if users format their survey choices defensively (e.g., "5 - Excellent").

1.6.4
-----
*   Fixed: Compliance - Fully removes non-functional Pro settings ("Card View Type" and "Enable Chat Bubbles") from the free plugin build to comply with WordPress.org repository rules, while enforcing strict functional defaults.

1.6.2
-----
*   Added: Ticket Metrics - Replaced the include/exclude agent filter with a two-box "Tracked Agents" vs "Other Agents" interface.
*   Added: Ticket Metrics - Grouped all untracked agents into an "Other" category to prevent chart pollution.
*   Added: Ticket Metrics - Added a "Queue Health" 1-row card combining Resolution Rate, Tickets Created, and Tickets Closed into a single horizontal view.
*   Added: Ticket Metrics - "Touched Tickets" and "Active Backlog" metrics to assess total support queue volume visually.
*   Added: Ticket Metrics - Individual tooltips for the "Other Agents" category to display specific ticket breakdowns per agent.
*   Added: Ticket Metrics - Dashboard auto-loads "This week" metrics upon initialization instead of requiring a manual click.
*   Fixed: Ticket Metrics - Initial Response Calculation (FRT) now accurately queries the native `frd` SupportCandy column.
*   Fixed: Ticket Metrics - "Tickets Created" metric calculation error returning 0.
*   Fixed: Ticket Metrics - Tooltip missing initialization bug inside dynamically loaded Modals.
*   Fixed: Ticket Metrics - "Undefined" label on bar/radar charts missing default datasets.
*   Improved: Ticket Metrics - Refactored Modal layouts for 2-column flex grids, moved headings inside cards, and rearranged dashboard metric blocks.

1.6.1
-----
*   Added: Ticket Details Card - "Chat Bubbles" view for conversation history (Pro Feature).
*   Changed: Ticket Details Card - Refactored "UTM" view to use a list layout similar to the "Standard" view for consistency.
*   Improved: Ticket Details Card - Automatically suppresses the initial description in the history section if it is already displayed in the UTM fields.
*   Improved: Ticket Details Card - Hides fields in UTM view that contain only commas/whitespace (common artifact of empty multi-selects).
*   Fixed: Chat Bubbles - Settings page "Live Preview" not updating due to incorrect JS selectors and specificity issues.
*   Fixed: Chat Bubbles - "Enable Chat Bubbles" setting in Ticket View not saving correctly due to missing sanitization whitelist entry.
*   Fixed: Ticket Details Card - Removed unwanted horizontal separator line in conversation history when description is hidden.

1.6.0
-----
*   Added: Conditional Options - Mutual exclusivity enforcement between WordPress Roles and SupportCandy Roles contexts to prevent conflicting rules.
*   Added: Conditional Options - "Guest / Visitor" role option in both contexts to target users who are not logged in.
*   Added: Conditional Options - "User" role option in SupportCandy context to target any user (logged in or guest) who does not have a SupportCandy Agent role.
*   Added: Conditional Options - "Customized Options" column in the rule management table to display which specific field options have rules applied.
*   Improved: Conditional Options - "Add Rule" dropdown now filters out text fields and disables fields that already have a rule configured.
*   Fixed: Conditional Options - Friendly option names not appearing in the rules list immediately after adding a new rule.
*   Fixed: Conditional Options - Feature potentially disabling itself when saving a new rule.

1.5.2
-----
*   Fixed: Chat Bubbles visual bugs including Right alignment content ordering and spacing.
*   Fixed: Chat Bubbles 'Center' alignment now correctly centers all content, including status change logs.
*   Fixed: Admin Live Preview for Chat Bubbles now correctly renders the preview using the actual SupportCandy DOM structure and enqueues core styles to prevent broken layouts.
*   Fixed: CSS generation issue where child rules were invalidly nested inside parent blocks.

1.5.1
-----
*   Added: Chat Bubbles module.
*   Added: 'After Hours Notice' module.
*   Added: 'Page Last Loaded' module.
*   Added: 'Ticket View' module (Organization & General Cleanup).
*   Changed: Refactored core plugin structure for better modularity.
