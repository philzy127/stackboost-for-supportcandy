# Changelog

All notable changes to this project will be documented in this file.

## [1.5.0] - 2025-12-28

### Added
- **Privacy & GDPR Compliance:**
    - **Consent Gates:** Implemented a new consent system for the Directory Block. Administrators must now explicitly confirm that they understand the privacy implications (IP leakage to Automattic) before enabling Gravatars.
    - **Zero External Leakage:** The PDF generation engine (DOMPDF) has been locked down to strictly disable remote file fetching, preventing any accidental data leakage to external servers.
    - **Data Erasure Hooks:** Integrated with the native WordPress "Erase Personal Data" tool. Processing an erasure request for an email address will now locate the corresponding Staff Directory profile and safely move it to the Trash.
    - **Data Export Hooks:** Integrated with the native WordPress "Export Personal Data" tool to include full Staff Directory profiles in the generated report.

### Changed
- **Logging Improvements:** Replaced remaining `console.error` calls in admin scripts with the centralized `stackboostLog` utility to ensure all errors are captured in the diagnostic log.
- **Documentation:** Updated user manuals to include a dedicated "Privacy & GDPR Compliance" section detailing the new data rights features.

## [1.4.3] - 2025-12-26

### Added
- **GDPR Compliance (Assets):**
    - **Local Asset Bundling:** All external third-party assets (DataTables, Tippy.js, Popper.js, jQuery UI CSS, and placeholder images) are now bundled locally within the plugin.
    - **No-CDN Policy:** Removed all references to external CDNs (Unpkg, Cloudflare, Google APIs, Placehold.co) to prevent unauthorized IP leakage.
- **DataTables 2.3.6:** Upgraded and standardized the DataTables library to version 2.3.6 across all modules.
- **Frontend Logging:** Introduced a standardized `window.stackboostLog()` utility for frontend scripts. This wrapper respects the global "Enable Diagnostic Log" setting, preventing debug noise in production while routing logs to the backend file when in an admin context.

### Changed
- **Asset Registration:** Centralized the registration of shared assets (DataTables, Tippy, Popper) in `src/WordPress/Plugin.php`. Modules now depend on these global handles (`stackboost-datatables-js`, `stackboost-tippy`) instead of managing their own dependencies.
- **Directory Module:** Updated the Directory Block and Shortcode to use the new centralized local assets.
- **Block Configuration:** Audited Block Editor configurations for the After Ticket Survey, Onboarding Dashboard, and Directory modules to ensure compatibility with modern WordPress standards.

## [1.4.2] - 2025-12-24

### Added
- **Directory Block:**
    - **Photo Shapes:** Added a "Photo Shape" option to the block and shortcode, allowing staff photos to be displayed as Circle, Square, Portrait, or Landscape.
    - **Gravatar Integration:** Introduced "Prefer Gravatar" and "Fallback to Gravatar" toggles for granular control over photo display priority.
    - **Email Display:** Added an optional "Email" field that displays the staff member's email address (with a copy-to-clipboard button) directly below their name.
- **Editor Visuals:** The Directory Block now renders static mockups of the "Search" bar and "Items Per Page" controls within the Gutenberg editor, providing immediate visual feedback that these features are enabled.

### Fixed
- **Department Filters:** Resolved a bug where the "Filter by Department" list in the Directory Block settings would get stuck on "Loading departments..." by enabling REST API support for Directory Custom Post Types.
- **React Deprecations:** Fixed deprecation warnings in the Block Editor console by updating `SelectControl`, `ToggleControl`, and other components with modern props (`__next40pxDefaultSize`, `__nextHasNoMarginBottom`).
- **HTML Entities:** Fixed an issue where department names with special characters (e.g., "Finance & Stuff") were displaying as HTML entities in the block settings filter list.

## [1.4.1] - 2025-12-24

### Added
- **SupportCandy Schedule Integration:** The After-Hours Notice module can now optionally inherit its working hours and holiday schedule directly from SupportCandy's settings.
- **Hybrid Scheduling:** Added support for hybrid configurations, allowing administrators to use SupportCandy's schedule while applying additional manual holiday overrides from StackBoost.
- **Settings Toggles:** Added "Use SupportCandy Working Hours" and "Use SupportCandy Holiday Schedule" toggles to the After-Hours Notice settings, with dynamic UI controls.

### Changed
- **Logic Refactor:** Centralized the "After Hours" determination logic (`is_currently_after_hours`) to handle multiple scheduling sources and conflict resolution robustly.
- **Timezone Handling:** Improved timezone handling for recurring holidays to strictly respect the WordPress site configuration instead of the server's default timezone.

## [1.4.0] - 2025-12-22

### Added
- **UI Themification:** Introduced a comprehensive theming system with a standardized color palette (accent, secondary, destructive, success) across the admin interface.
- **Card Layouts:** Implemented a new "Connected Card" layout for tabbed interfaces (Directory, Onboarding, After Ticket Survey) to create a seamless, modern visual experience where tabs blend into the content area.
- **Standardized Buttons:** Unified the design of "delete" and "remove" buttons across all modules (Conditional Views, Date & Time, UTM, After Ticket Survey, Onboarding) to use a consistent icon-only style with a red hover effect.
- **Admin Tabs Styling:** Added dedicated styling for admin tabs to match the active theme's accent color.

### Changed
- **Logging Standardization:** Audited and refactored client-side (JS) and server-side (PHP) logging. All debug output now routes through the centralized `stackboost_log` utilities, respecting the global "Enable Diagnostic Log" setting and removing stray console/error logs.
- **Module Layouts:**
    - **Directory:** Restructured settings and management pages into logical card groups with clear headers.
    - **Onboarding:** Updated the dashboard, staff, and settings pages to use the new card system.
    - **After Ticket Survey:** Updated the tab structure to use the connected card layout.

## [1.3.9] - 2025-12-21

### Changed
- **Ticket View UI:** Implemented a new "PRO" badge for restricted features, replacing the generic lock icon.
- **Ticket View Interaction:** Refined the behavior of the Ticket View popup.
    - **Layout Logic:** The "Smart Layout" (switching to horizontal view) now triggers more intelligently (at 85% viewport height) and only when sufficient content exists in both columns.
    - **Tippy Resizing:** Expanding/collapsing sections within the popup now correctly recalculates the popup's size and position.
    - **Lightbox Isolation:** Interacting with a lightbox/modal inside the popup no longer inadvertently closes the entire popup.
- **License Fallback:** Improved the robustness of the license validation logic. If the backend reverts to "Standard" view due to an inactive license, the frontend now correctly detects this and triggers the standard scraping logic instead of rendering an empty view.
- **Logging Standardization:** Audited and updated client-side logging to use the centralized `stackboost_log` utilities in key frontend scripts.

## [1.3.8] - 2025-12-16

### Added
- **License Management:** Implemented a full licensing system powered by Lemon Squeezy.
    - **Activation UI:** A new interface in General Settings allows users to enter a license key to activate Pro or Business features.
    - **Tiered Access:** The plugin now enforces strict feature gating based on three tiers: Lite, Pro, and Business.
    - **Validation Engine:** A robust `LicenseManager` service handles validation, including a 12-hour local cache to improve performance and a 72-hour grace period to prevent lockouts during API outages.
- **Strict Feature Gating:** Major architectural update to ensure that premium modules (like Company Directory and Onboarding) are physically prevented from loading or registering menus unless a valid license for that tier is active.
- **Admin Notices:** Added dismissible admin notices to alert administrators if their license key becomes invalid or expires.

### Changed
- **Logging Standardization:** Refactored all client-side JavaScript logging to use the centralized `stackboost_log()` utility, ensuring that browser console output respects the global "Enable Diagnostic Log" setting.
- **Feature Matrix:** Updated the internal feature definitions to strictly follow the new product tiers:
    - **Lite:** Quality of Life, After-Hours Notice, Date/Time Formatting.
    - **Pro:** Conditional Views, Queue Macro, After Ticket Survey, Unified Ticket Macro.
    - **Business:** Company Directory, Onboarding Dashboard.

### Fixed
- **Settings Visibility:** Resolved a bug where Business-tier settings would not appear immediately after activation due to legacy tier naming in the code.
- **Cleanup:** Removed development artifacts (`test_phone_logic.php`, `phpunit.xml`) to prepare for distribution.

## [1.3.3] - 2025-12-12

### Added
- **ATS Highlander Rule:** Implemented a new constraint that restricts the "Ticket Number" question type to a maximum of one per survey form.
    - **UI:** The "Ticket Number" option in the "Question Type" dropdown is now automatically disabled if a ticket number question already exists, with a visible warning message.
    - **Validation:** Added server-side checks to prevent bypassing this rule via API manipulation.
- **ATS Read-only Prefill:** Introduced a new "Read-only if Pre-filled" configuration option for survey questions.
    - **Functionality:** Allows questions to be locked (made read-only) if they are successfully populated via a URL parameter (e.g., `?ticket_id=123`).
    - **Validation:** Ensures that the field is only locked if the pre-filled data is valid (numeric for ticket numbers, matching option for dropdowns). Invalid data is ignored, leaving the field editable.
- **ATS Self-Healing Schema:** The database installer now automatically detects and adds the new `is_readonly_prefill` column if it is missing.

## [1.3.2] - 2025-12-14

### Added
- **Diagnostic Logging:** Introduced a comprehensive, granular diagnostic logging system.
    - **Diagnostics Page:** Renamed the "Tools" admin menu item to "Diagnostics" to better reflect its purpose.
    - **Module Toggles:** Added a "Module Logging" section to the Diagnostics settings page, allowing administrators to enable/disable logging for individual modules (e.g., General, UTM, Onboarding, Ticket View).
    - **Master Switch:** A master "Enable Diagnostic Log" switch controls the entire logging system.
- **Modern Modal System:** Implemented a custom, styled modal system to replace native browser `alert()` and `confirm()` dialogs across the admin interface. This provides a consistent and modern user experience.

### Changed
- **Menu Structure:** Renamed the "Tools" submenu to "Diagnostics".
- **UI Refinement:** Replaced all native browser alerts and confirmation dialogs with the new StackBoost modal system in the Directory, Onboarding Dashboard, ATS, and Diagnostics modules.

### Fixed
- **Logging Consistency:** Standardized logging across the plugin. Replaced disparate log calls with the centralized `stackboost_log()` function, ensuring all debug information is captured in a single, controlled log file.

## [1.3.1] - 2025-12-14

### Added
- **ATS Ticket Number Type:** Introduced a new "Ticket Number" question type. This simplifies configuration by automatically enabling clickable ticket links in the results view without needing manual ID mapping.
- **ATS Validation:** Implemented strict server-side validation for "Ticket Number" fields to ensuring only numeric values are saved. The frontend input remains a text field to avoid browser-specific number input quirks.

### Removed
- **ATS Settings Tab:** Removed the obsolete "Settings" tab and its associated backend logic. Configuration is now streamlined within the "Manage Questions" interface.

## [1.3.0] - 2025-12-11

### Added
- **ATS Modern UI:** The "Manage Questions" tab in the After Ticket Survey module has been completely overhauled. It now uses a modal interface for adding/editing questions and supports drag-and-drop reordering.
- **ATS URL Prefill:** Added a "URL Parameter (Prefill Key)" feature to all question types. This allows survey answers to be pre-filled via URL parameters (e.g., `&ticket_id=123`).
- **ATS Fuzzy Logic:** Implemented "Smart Matching" for Dropdown questions. When pre-filling from a URL, the system now intelligently selects the best option using a "Best Match Wins" scoring algorithm (Exact > Starts With > Contains), solving issues with partial name matches.
- **ATS Self-Healing DB:** Implemented a robust installation check that detects missing database tables or columns and automatically repairs the schema without requiring manual intervention.

### Changed
- **ATS Architecture:** Moved form handling from legacy POST controllers to a modern AJAX-based system for better performance and user experience.
- **ATS Install Logic:** Moved the database version check from `plugins_loaded` to `admin_init` to ensure reliable execution during plugin updates.

## [1.2.9] - 2025-12-05

### Changed
- **After-Hours Editor:** The WYSIWYG editor for the "After-Hours Message" has been upgraded. It now features an expanded toolbar with options for Text Color, Horizontal Rules, Blockquotes, and more, while retaining its compact "teeny" mode. This required explicitly loading additional TinyMCE plugins to override the default "teeny" restrictions.

## [1.2.8] - 2025-11-30

### Changed
- **UTM Formatting:** The Unified Ticket Macro output has been refined. Field names now have `white-space: nowrap` and `vertical-align: top` to prevent wrapping and ensure clean alignment. Trailing colons are stripped from field names to avoid double colons.
- **Rich Text Fields:** Paragraph tags in rich text fields (like Description) now have `margin: 0` to fix alignment offsets.

### Fixed
- **UTM Description:** Fixed a bug where the ticket description was missing from the macro output. The module now correctly fetches the description from the initial ticket report thread instead of the empty ticket property.
- **Description Filtering:** The description field is now intelligently hidden if it is empty, contains only whitespace, or matches "Not Applicable".

## [1.2.6] - 2025-11-10

### Added
- **Centralized Admin Menu:** The entire admin menu structure (both sidebar and top admin bar) is now managed by a single source of truth in `Settings::get_menu_config()`, ensuring consistent ordering and preventing module conflicts.
- **AJAX Log Clearing:** The "Clear Log" button in the Tools section now uses AJAX to clear the debug log instantly without reloading the page, accompanied by a toast notification.
- **Client-Side Logging:** Introduced a centralized `window.stackboost_log` utility for admin pages that respects the global "Enable Diagnostic Log" setting.

### Changed
- **Admin Menu Order:** Reordered the admin menu items to follow a logical workflow: General Settings, Ticket View, Conditional Views, After-Hours Notice, Queue Macro, Unified Ticket Macro, After Ticket Survey, Directory, Onboarding, Tools, and How To Use.
- **Version Banner:** The SupportCandy version banner is now restricted to appear only on the main "General Settings" page, reducing visual clutter on other plugin screens.
- **Menu Registration:** Removed decentralized menu registration code from individual modules (`Directory`, `Onboarding`, `UTM`, `ATS`) to support the new centralized architecture.

### Fixed
- **UTM Menu Visibility:** Fixed a bug where the "Unified Ticket Macro" menu item was hidden for "Pro" and "Business" users. It is now correctly enabled for the `plus` and `operations_suite` license tiers.

## [1.2.5] - 2025-11-08

### Fixed
- **Settings Save Failure:** Resolved a critical, widespread bug that prevented settings from being saved on multiple admin pages, including "Queue Macro" and "After Hours Notice". The root cause was a silent conflict in the WordPress Settings API caused by different modules attempting to register the same settings group.
- **Architectural Refactor:** The entire settings registration and sanitization process has been centralized into the main `src/WordPress/Admin/Settings.php` class. This architectural change eliminates the conflict and makes the settings system more robust and stable.
- **Improved Sanitization:** The central sanitization function has been completely rewritten to use explicit, type-specific sanitization for every setting, ensuring data integrity and preventing silent save failures.
- **Admin Page JavaScript Conflict:** Fixed a bug where a shared JavaScript file was causing visual and functional issues on admin pages it was not intended for. The script is now correctly scoped to only run on the relevant page.

## [1.2.4] - 2025-10-26

### Added
- **Context-Aware Redirects:** When editing a staff member from a SupportCandy ticket, the user is now automatically redirected back to the ticket they came from. This works for both frontend and backend ticket views.

### Changed
- The success banner after updating a staff member is now context-aware. It will display a "Return to Ticket" link if the edit originated from a ticket, or a "Return to Directory" link otherwise.

### Fixed
- **Redundant "Add New" Button:** Removed the unnecessary "Add New" button that appeared at the top of the "Add New Staff" page.
- **Autoloader Conflict:** Resolved a critical PSR-4 autoloader conflict that was causing fatal errors by renaming `Admin.php` to `AdminController.php` in the AfterTicketSurvey module.
- **Obsolete Code:** Removed references to non-existent `Importer` and `Clearer` classes to prevent fatal errors.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.3] - 2025-10-20

### Fixed
- **Contact Widget Positioning:** Resolved a long-standing bug where the Contact Widget would not appear in its configured position, instead always rendering at the bottom of the page. The fix involved a robust, self-contained inline JavaScript that correctly identifies the visible widget container and is immune to issues caused by AJAX re-renders.
- **Contact Widget Duplication:** Fixed a critical issue where two instances of the Contact Widget would appear. The final solution makes the client-side script responsible for generating a unique ID and cleaning up any stale instances, making it resilient to server-side caching or other environmental factors that were causing the duplication.
- **Contact Widget Configuration:** The widget now correctly respects the "Enable Widget" setting and will not be rendered if it is disabled.

### Changed
- The "Contact Widget" feature is now fully functional and stable.

## [1.2.2] - 2025-10-18

### Added
- **WordPress User Linking:** Added a "Linked WordPress User" field to the "Staff Details" editor. This allows an administrator to create a stable link between a directory entry and a WordPress user account.
- The UI features a scalable, AJAX-powered search box (using Select2) to find users, and a display area to show the currently linked user with "Change" and "Remove" options.
- A new "Contact Widget" tab has been added to the Directory admin screen.

### Fixed
- Resolved a series of critical JavaScript and PHP errors that were preventing the "Contact Widget" from appearing on the SupportCandy ticket screen.

## [1.2.1] - 2025-10-17

### Added
- **Phone Number Formatting:** Implemented front-end, real-time formatting for `Office Phone` and `Mobile Phone` fields in the staff directory admin screen to `(xxx) xxx-xxxx`.
- **Data Migration for Phone Numbers:** Added a new, user-triggered database migration to strip special characters from all existing phone numbers, ensuring they are stored in a raw digit format.

### Changed
- **Phone Number Storage:** The system now saves all phone numbers as raw digits in the database, stripping parentheses, spaces, and dashes on save and during import.

### Fixed
- **CSV Import Functionality:** Re-implemented the previously missing server-side logic for the CSV import feature.
- **CSV Import Click Action:** Fixed a bug where the "Import" button was unresponsive due to a mismatched ID in the JavaScript file.

## [1.2.0] - 2025-10-17

### Added
- **Modal View for Directory:** Added a new "Listing Display Mode" setting in the Directory settings tab. This allows administrators to choose whether clicking a staff member on the front-end directory opens their details on a new page ("Page View") or in a pop-up modal window ("Modal View").
- This includes a new AJAX endpoint for fetching modal content, new CSS for styling the modal, and new JavaScript for handling the modal's behavior.

### Fixed
- **Modal Content Loading:** Resolved a series of bugs that prevented the modal from loading content, including a fatal PHP error in the AJAX handler and an issue with fetching the staff member's photo. The final solution uses a dedicated template part and passes all data directly to it from the AJAX handler for a more robust implementation.

## [1.1.0] - 2025-10-16

### Added
- **Directory Service:** Introduced a new `DirectoryService` class to act as a single, authoritative source for all Company Directory data, abstracting the data layer from the presentation layer.
- **Admin Testing UI:** Added a new "Testing" tab to the Company Directory admin page, allowing administrators to test the `DirectoryService` methods directly from the backend.
- **Single Staff Page Template:** Created a new `single-sb_staff_dir.php` template to ensure a consistent and correct display for single staff members, independent of the active theme.
- **Centralized Phone Formatter:** Created a new global function `stackboost_format_phone_number()` to handle phone number formatting and copy-to-clipboard functionality, removing duplicated code.

### Changed
- **Refactored Directory Shortcode:** The `[stackboost_directory]` shortcode has been updated to use the new `DirectoryService`, simplifying its code and ensuring consistent data access.
- **Single Staff Page Design:** The single staff member page has been redesigned to be visually identical to the original plugin's design, featuring a two-column layout with a photo and a details table.

### Fixed
- **Blank Single Staff Page:** Resolved a critical bug where single staff member pages would appear blank due to a fatal error caused by the theme's template not being able to find the correct data after the refactoring. The new template and a `single_template` filter fix this issue.
- **Missing Phone Formatting:** Applied the standard phone number formatting and copy-to-clipboard functionality to the single staff member page, making it consistent with the main directory view.

## [1.0.0] - 2025-10-13

### Changed
- **Major Refactoring:** Replaced all instances of the internal `chp_` prefix with the public-facing `stackboost_` prefix throughout the entire codebase.
- Updated the plugin name to "StackBoost - For SupportCandy" and the author to "StackBoost" in all relevant files.
- Shortened the Custom Post Type slugs to be under the 20-character limit imposed by WordPress (`sb_staff_dir`, `sb_department`, `sb_location`).

### Added
- **Upgrade Routine:** Implemented a robust, timestamp-based upgrade routine to handle one-time tasks like flushing rewrite rules.

### Fixed
- **Invalid Post Type Error:** Resolved a critical bug where the Staff and Department custom post types were not accessible due to their slugs exceeding the 20-character limit.
- **Datepicker Not a Function Error:** Fixed a JavaScript error on the "Add New Staff Entry" page by correctly enqueuing the `jquery-ui-datepicker` script.
