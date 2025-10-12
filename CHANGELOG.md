# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.1.0] - 2025-10-12

### Added
- **Company Directory Module:** A new, fully integrated module for managing and displaying a staff directory.
  - Adds a "Company Directory" submenu to the main StackBoost admin page.
  - Provides a tabbed interface for managing Staff, Locations, and Departments.
  - Includes a CSV importer for bulk-adding new staff members.
  - Provides a tool to clear all directory data.
  - Implements a `[stackboost_directory]` shortcode for front-end display.
  - Includes full trash management (view, trash, restore, delete permanently) for all directory items.
  - Includes search functionality for all directory items.

### Changed
- The main plugin now loads the Company Directory module.

### Fixed
- Numerous bugs related to the initial implementation of the Company Directory module.