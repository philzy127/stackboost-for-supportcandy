# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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