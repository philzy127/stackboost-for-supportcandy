# Technical Documentation: Onboarding Dashboard Module

## Overview

The `OnboardingDashboard` module is a comprehensive solution for managing onboarding presentations linked to SupportCandy tickets. It consists of:
1.  **Backend Management:** Admin pages for configuring settings, managing steps (CPT), and viewing staff/ticket data.
2.  **Frontend Presentation:** A React-like (jQuery-based) single-page application loaded via shortcode.
3.  **PDF Generation:** Services for creating and emailing completion certificates.

## Architecture

### Directory Structure
```
src/Modules/OnboardingDashboard/
├── Admin/              # Admin pages (Settings, Staff, Sequence, ImportExport)
├── Ajax/               # AJAX Handlers (Certificate generation, data fetches)
├── Data/               # Data models (CPT registration)
├── Shortcodes/         # Frontend shortcode logic
└── assets/             # CSS/JS files
```

### Data Models
*   **Post Type:** `stkb_onboarding_step`
    *   **Usage:** Stores individual onboarding slides.
    *   **Meta:**
        *   `_stackboost_onboarding_checklist_items`: Newline-separated list.
        *   `_stackboost_onboarding_notes_content`: HTML content.
    *   **Visibility:** `show_in_menu => false`. Managed entirely via the custom `Sequence.php` admin page.
*   **Sequence:** stored in `stackboost_onboarding_sequence` (WP Option). Array of Post IDs defining the display order.

### Configuration (`stackboost_onboarding_config`)
The module uses a centralized settings array.
*   **Request Type / ID:** Maps the module to specific SupportCandy tickets.
*   **Phone Configuration:** (See below).

## Phone Logic Refactor (v2.0)

The phone configuration system was refactored to support flexible data structures.

### Data Model
*   `phone_config_mode`: `'single'` or `'multiple'`.
*   **Single Mode:**
    *   `phone_single_field`: Slug of the main phone field.
    *   `phone_has_type`: `'yes'`/`'no'`.
    *   `phone_type_field`: Slug of the field indicating type (e.g., Mobile/Home).
    *   `phone_type_value_mobile`: The value that triggers the "Mobile" icon.
*   **Multiple Mode:**
    *   `phone_multi_config`: Array of `['field' => slug, 'type' => string]`.
    *   **Supported Types:** `mobile`, `work`, `home`, `fax`, `generic`.

### Rendering Logic (`Staff.php`)
The `render_table` method:
1.  **Hydrates** all configured phone fields (even if not columns) via `WPSC_Ticket::find`.
2.  Checks the configured mode.
3.  **Icon Mapping:**
    *   Mobile -> `smartphone`
    *   Work -> `building`
    *   Home -> `home`
    *   Fax -> `print`
    *   Generic -> `phone`
4.  Appends the `<i class="material-icons">...</i>` to the formatted phone number.

### Migration Strategy
A **Just-in-Time** migration is implemented in `Settings::get_config()`. It detects legacy keys (`mobile_logic_mode`) and transforms them into the new `phone_config_mode` structure in-memory. This ensures backward compatibility without requiring a database upgrade script.

## Import / Export System

The module supports importing JSON data to migrate "Steps" between environments.

### Supported Formats
1.  **Standard:** Array of objects `[{ title: "...", ... }]`.
2.  **Legacy (Root Posts):** Object with root `posts` key ` { posts: [...] }`.

### Meta Key Mapping
The importer (`ImportExport.php`) automatically detects and maps legacy meta keys to the new namespace:
*   `_odb_checklist_items` -> `_stackboost_onboarding_checklist_items`
*   `_odb_notes_content` -> `_stackboost_onboarding_notes_content`
*   **Nested Meta:** Supports legacy exports where meta is nested in a `meta` object: `$item['meta']['_odb_checklist_items']`.

## Frontend Logic

The frontend is rendered via `[stackboost_onboarding_dashboard]`.
*   **Class:** `Shortcodes\DashboardShortcode`.
*   **Logic:**
    1.  Fetches the sequence of step IDs.
    2.  Fetches "This Week's Attendees" using the cached data from `Staff::render_page()` (transient: `stackboost_onboarding_tickets_cache`).
    3.  Localizes all data to `stackboost-onboarding-dashboard.js`.
    4.  The JS handles the "virtual router" (query param `step_id`), checklist validation, and the final "Completion" screen.

### Certificates
The "Send Certificates" button triggers `stackboost_onboarding_send_certificates` AJAX action.
*   It generates a PDF using `dompdf`.
*   It creates a new SupportCandy Thread (Attachment) on the user's ticket.
*   It emails the PDF to the configured HR contact (if applicable).
