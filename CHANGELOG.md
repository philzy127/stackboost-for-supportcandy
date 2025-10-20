# Changelog

All notable changes to this project will be documented in this file.

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