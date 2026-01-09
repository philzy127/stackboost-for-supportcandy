Change Log
==========

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
