# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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