# Changelog

## [1.5.1] - 2025-10-13
### Added
- **Chat Bubbles:** A complete module for styling SupportCandy ticket threads as modern chat bubbles.
    - **Uniformity:** Identical rendering on Admin Dashboard and Frontend Ticket Views.
    - **Theme Sync:** Automatically inherits colors from the active StackBoost Admin Theme.
    - **Visual Controls:** Granular settings for Padding, Image Bounding Boxes, and Shadows (Distance, Blur, Spread).
    - **Avatar Logic:** Intelligent hiding of user avatars for chat messages while preserving them for system events.

## [1.2.4] - 2024-05-22
### Added
- **Feature Spotlight:** Added a context-aware "Feature Spotlight" widget to the main settings dashboard.
    - Displays rotating feature cards relevant to the user's license tier (Lite, Pro, Business).
    - Includes a carousel interface with manual navigation and 60-second auto-rotation.
    - Provides direct links to feature documentation and upgrade pages.
- **Queue Macro:** Added clear descriptions emphasizing "New Ticket" queue transparency.
- **Unified Ticket Macro:** Updated descriptions to highlight the dynamic HTML table generation capability.

### Fixed
- Corrected inaccurate marketing copy in the admin dashboard for several features.
- Improved layout stability for dashboard widgets to prevent content jumping.

## [1.2.3] - 2024-05-20
### Added
- **Queue Macro:** Initial implementation of the Queue Position macro `{{queue_count}}`.
- **Diagnostic Logging:** Added `stackboost_log()` centralized logging system.

### Changed
- Refactored `Settings.php` to support modular settings pages.
