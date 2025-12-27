# StackBoost for SupportCandy - Developer Documentation

## Overview

StackBoost for SupportCandy is a modular WordPress plugin designed to enhance the capabilities of the SupportCandy helpdesk plugin. It uses a modern, namespace-based architecture to provide features like a Company Directory, Onboarding Dashboard, Unified Ticket Macros, and more.

## Architecture

### Directory Structure

The plugin is organized into the following directory structure:

*   `bootstrap.php`: The entry point for the plugin, handling the autoloader and initialization.
*   `includes/`: Helper functions and third-party libraries.
*   `src/`: The core source code, following PSR-4 standards.
    *   `Core/`: Abstract base classes and interfaces (e.g., `Module`).
    *   `Modules/`: Self-contained feature modules (e.g., `Directory`, `OnboardingDashboard`).
    *   `WordPress/`: WordPress integration classes (e.g., `Plugin`, `Admin/Settings`).

### Modules

Each feature is encapsulated as a "Module". A typical module structure looks like this:

*   `Core.php`: Contains the business logic, decoupled from WordPress as much as possible.
*   `WordPress.php`: The WordPress adapter that handles hooks, filters, and settings registration.
*   `Admin/`: Admin-specific classes (e.g., settings pages, list tables).
*   `Data/`: Data access objects or custom post type definitions.

### Logging System

The plugin features a centralized, granular logging system for diagnostics.

*   **Central Function:** `stackboost_log( $message, $context = 'general' )` defined in `bootstrap.php`.
*   **Contexts:** Logs are categorized by context (e.g., `'module-utm'`, `'onboarding'`).
*   **Configuration:**
    *   **Master Switch (`diagnostic_log_enabled`):** Controls ALL console logging (client-side) and enables the system globally. If this is OFF, no logs (file or console) are generated.
    *   **Module Toggles (`enable_log_module`):** Control FILE logging for specific contexts. If the Master Switch is ON but a module toggle is OFF, logs for that module will appear in the browser console (if implemented in JS) but NOT in the `debug.log` file.
    *   The `stackboost_log` function automatically maps the `context` argument to the corresponding setting to determine if the log should be written to the file.
*   **Log Location:** Logs are written to `wp-content/uploads/stackboost-logs/debug.log`.

**Usage Example:**

```php
stackboost_log( 'Starting import process...', 'directory-import' );
```

### Frontend Logging Standardization

For client-side JavaScript, native `console.log` calls are strictly prohibited in production code. All frontend logging must use the `window.stackboostLog()` wrapper.

*   **Signature:** `window.stackboostLog( message, data = null, level = 'log' )`
*   **Features:**
    1.  **Routing:** If the script is running in an admin context (where `window.stackboost_log` is available via `stackboost-admin-common.js`), the log is routed to the server-side log file with a `[Frontend]` prefix.
    2.  **Debug Guard:** If running on the public frontend, the log is only printed to the browser console if the global debug flag (`stackboostPublicAjax.debug_enabled`) is true.
*   **Aliases:** `sbUtilLog(message, data)` (alias for level='log') and `sbUtilError(message, data)` (alias for level='error').

### Modal System

The plugin includes a centralized modal system to replace native browser `alert()` and `confirm()` dialogs.

*   **Helper Functions:** `stackboostAlert(message, title, callback)` and `stackboostConfirm(message, title, onConfirm, onCancel, confirmText, cancelText, isDanger)`.
*   **Assets:** Defined in `assets/js/stackboost-util.js` and `assets/css/stackboost-util.css`.
*   **Usage:** Enqueue `stackboost-util` script and style to use these functions.

### Settings API

All settings are centralized through `src/WordPress/Admin/Settings.php`.

*   **Registration:** Settings are registered via `register_settings` in the `Settings` class.
*   **Sanitization:** A central `sanitize_settings` method handles validation for all fields, using a whitelist approach keyed by the admin page slug.
*   **Menu Management:** The `get_menu_config()` method in `Settings.php` is the single source of truth for the admin menu structure.

### Asset Management (GDPR Compliance)

The plugin adheres to a strict **No-CDN Policy** to ensure GDPR compliance. All external assets (scripts, styles, fonts, images) must be bundled locally.

*   **Central Registration:** `src/WordPress/Plugin.php` contains a `register_global_assets()` method hooked to `init`. This method registers shared local libraries (e.g., DataTables, Tippy.js, Popper.js) with unique handles (e.g., `stackboost-datatables-js`, `stackboost-tippy`).
*   **Usage:** Modules must enqueue these shared handles instead of registering their own or linking to CDNs.
*   **Bundled Assets:**
    *   `assets/libraries/datatables/` (v2.3.6)
    *   `assets/libraries/tippy/` (v6.0)
    *   `assets/libraries/popper/` (v2.0)
    *   `assets/libraries/jquery-ui/` (v1.12.1 Smoothness)
    *   `assets/images/placeholder.png` (Replaces placehold.co)

## Module Specifics

### Directory Module

*   **Service:** `StackBoost\ForSupportCandy\Services\DirectoryService`
    *   **Singleton:** `DirectoryService::get_instance()`
    *   **Photo Logic:** The `retrieve_employee_data()` method exposes several photo-related properties:
        *   `thumbnail_url`: The resolved "display" photo URL (used by legacy widgets/code). Follows the "Implied Fallback" logic (Custom -> Gravatar -> Placeholder).
        *   `custom_photo_url`: The raw URL of the uploaded featured image (or empty).
        *   `gravatar_url`: The resolved Gravatar URL (always populated if email exists).
        *   `placeholder_url`: The URL to the local placeholder image.
*   **Block/Shortcode:** `Modules\Directory\Shortcodes\DirectoryShortcode`
    *   Uses the raw properties from the Service to implement granular display logic based on `preferGravatar` and `enableGravatarFallback` attributes.
*   **Custom Post Types:** Defined in `Modules\Directory\Data\CustomPostTypes`. All CPTs (`sb_staff_dir`, `sb_department`, `sb_location`) have `'show_in_rest' => true` to support Gutenberg Block Editor data fetching.

### After-Hours Notice

*   **Class:** `Modules\AfterHoursNotice\WordPress`
*   **Logic:** `is_currently_after_hours()` determines the open/closed status.
*   **SupportCandy Integration:**
    *   Uses `WPSC_Working_Hour` and `WPSC_Wh_Exception` to check scheduling status.
    *   Uses `WPSC_Holiday` to check for holidays.
    *   All external calls are wrapped in `class_exists()` checks for safety.
*   **Precedence:**
    1.  SC Exceptions (Override all).
    2.  Manual Holidays (Override open status if hybrid mode is active).
    3.  SC Standard Schedule or Manual Schedule (depending on toggle).

### After Ticket Survey (ATS)

*   **Tables:** `wp_stackboost_ats_questions` (contains question definitions).
*   **Schema Update:** The `is_readonly_prefill` column (`tinyint(1)`) was added to the questions table in version 1.5. The `Install` class handles schema updates via `dbDelta` and includes self-healing logic in `check_db_version()`.
*   **Validation:**
    *   **Highlander Rule:** Implemented in `Ajax.php` (backend) and `stackboost-ats-manage-questions.js` (frontend) to strictly limit one 'ticket_number' question per form.
    *   **Frontend Read-only:** `Shortcode.php` validates pre-filled values against question constraints (numeric for tickets, existing options for dropdowns) before rendering the field as read-only (`pointer-events: none`).

### Ticket View

*   **AJAX Endpoint:** `stackboost_get_ticket_details_card`
*   **Response Structure:**
    ```json
    {
        "success": true,
        "data": {
            "details": "HTML string for the standard fields table",
            "history": "HTML string for the description and conversation history",
            "effective_view_type": "standard|utm"
        }
    }
    ```
*   **Effective View Type:** The `effective_view_type` property is critical. It indicates the *actual* view type rendered by the backend. The frontend MUST use this value to determine behavior (e.g., whether to scrape standard fields or rely on the backend content) rather than relying solely on the local settings, as the backend may force a fallback (e.g., due to license status).

## Deployment

### Versioning

The plugin follows Semantic Versioning. The version is defined in:
1.  `stackboost-for-supportcandy.php` (Plugin Header)
2.  `STACKBOOST_VERSION` constant in `stackboost-for-supportcandy.php`

### Release Process

1.  Update the `CHANGELOG.md` with new features and fixes.
2.  Increment the version number in `stackboost-for-supportcandy.php`.
3.  Commit changes with a standard message (e.g., `Bump version to 1.3.2`).
4.  Tag the release in git.
