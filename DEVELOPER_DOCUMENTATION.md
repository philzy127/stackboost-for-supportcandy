# StackBoost for SupportCandy - Developer Documentation

## Overview

StackBoost extends SupportCandy with modular features. Each feature is encapsulated in a dedicated module directory under `src/Modules/`.

## Architecture

*   **Plugin Root:** `stackboost-for-supportcandy/`
*   **Source:** `src/`
    *   **Core:** `Core/` (Base classes like `Module`, `Singleton`)
    *   **Modules:** `Modules/` (Feature implementations)
    *   **WordPress:** `WordPress/` (Admin pages, Settings, Plugin integration)
*   **Assets:** `assets/` (CSS, JS, Images)

## Core Modules

### 1. Unified Ticket Macro (UTM)
*   **Location:** `src/Modules/UnifiedTicketMacro/`
*   **Purpose:** Replaces the `{{stackboost_unified_ticket}}` macro in emails with a dynamic HTML table.
*   **Key Hooks:** `wpsc_replace_macros` (Email Body Replacement).
*   **Logging:** Detailed hook logging is implemented in `WordPress.php` to trace ticket lifecycle events (`wpsc_create_new_ticket`, `wpsc_post_reply`, etc.).

### 2. Feature Spotlight (Upsell Widget)
*   **Location:** `src/WordPress/Admin/Settings.php`
*   **Purpose:** Displays context-aware feature upsells on the main settings dashboard.
*   **Logic:**
    *   `get_upsell_content()`: Defines the content (Hook, Copy, URL, Icon) for 7 feature cards.
    *   `get_upsell_pool()`: Returns an array of available cards based on the user's license tier (Lite, Pro, Business).
    *   **Rendering:** Uses an inline jQuery script to implement a carousel with manual navigation and a 60-second auto-rotation timer.
    *   **Note:** The widget logic reads a transient for start index compatibility but currently defaults to random start (`array_rand`) for better discovery in the carousel format.

### 3. Queue Macro
*   **Location:** `src/Modules/QueueMacro/`
*   **Purpose:** Calculates and displays a ticket's position in the support queue.
*   **Key Logic:** `Core::calculate_queue_count` executes a SQL count query based on the configured "Type Field" (e.g., status, priority).

### 4. Chat Bubbles
*   **Location:** `src/Modules/ChatBubbles/`
*   **Purpose:** Styles ticket threads as chat bubbles with theme synchronization.
*   **Key Components:**
    *   `Core::generate_css()`: Generates dynamic CSS targeting both Admin (`.wpsc-it-container`) and Frontend (`.wpsc-shortcode-container`, `#wpsc-container`) selectors.
    *   `Core::get_stackboost_theme_colors()`: A helper that maps Admin Theme slugs to hex codes, allowing the frontend to replicate the admin theme without loading admin assets.
    *   **Frontend Integration:** Uses `wp_enqueue_scripts` to register a virtual style handle (`stackboost-chat-bubbles-frontend`) and attach inline CSS.

### 5. Conditional Options
*   **Location:** `src/Modules/ConditionalOptions/`
*   **Purpose:** Enforces granular visibility rules for field options based on user roles.
*   **Key Logic:**
    *   **Context Locking:** Enforced in `admin-matrix.js`. Ensures admin cannot select both WP roles and SupportCandy roles simultaneously.
    *   **Pseudo-Roles:**
        *   `guest`: Mapped to `!is_user_logged_in()`.
        *   `user` (SC Context): Mapped to `empty($sc_roles)`, effectively targeting any user without an explicit agent role.
    *   **Enforcement:**
        *   **Backend:** `WordPress::enforce_permissions_on_submission` hooks into `wpsc_create_ticket_data` to sanitize incoming data.
        *   **Frontend:** `frontend-enforcement.js` uses `stackboostCORules` localized data to remove options from the DOM.

## Logging Standards

*   **Central Function:** `stackboost_log( $message, $context )` defined in `bootstrap.php`.
*   **Policy:**
    *   **NO** `console.log()` in production JS. Use `window.stackboostLog()` wrapper if available, or PHP-injected debug flags.
    *   **NO** `error_log()` or `file_put_contents()` for debug data. Use `stackboost_log()`.
    *   **Context:** Always provide a specific context string (e.g., `'module-utm'`, `'core'`, `'directory'`) to allow granular filtering in the admin panel.

## Frontend Development

*   **CSS:** Use BEM naming convention where possible. Prefix classes with `.stackboost-`.
*   **JS:** Enqueue scripts via `admin_enqueue_scripts` hook. Use `wp_localize_script` to pass PHP data (nonces, settings).

## Release Process

1.  **Version Bump:** Update `STACKBOOST_VERSION` constant in `Plugin.php` and the file header.
2.  **Changelog:** Update `CHANGELOG.md`.
3.  **Build:** Run the build script (if applicable) to generate the `.zip` package.
