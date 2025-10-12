# StackBoost - For SupportCandy: Developer Documentation

## 1. Introduction & Architectural Overview

This document serves as a technical guide for developers looking to understand, extend, or integrate with the StackBoost plugin. It details the plugin's architecture, core components, module structure, and key integration points.

### Core Architectural Principles

The plugin is built on a set of principles designed to promote modularity, maintainability, and ease of development.

#### 1.1. Modular Design

The plugin's functionality is broken down into distinct **Modules**, located in `src/Modules/`. Each module is self-contained and encapsulates a specific feature set (e.g., `AfterHoursNotice`, `QueueMacro`). This design allows features to be developed, tested, and even disabled independently without affecting the rest of the plugin.

#### 1.2. Logic vs. Integration Separation (Core/WordPress Pattern)

A key architectural pattern is the separation of pure business logic from WordPress-specific implementation details. Within each module, you will typically find:

*   `Core.php`: This class contains the module's core business logic. It is written to be "WordPress-agnostic," meaning it does not use any WordPress functions, hooks, or globals. This makes the logic portable and easier to unit test.
*   `WordPress.php`: This class acts as the **WordPress Adapter**. It is responsible for integrating the core logic with the WordPress ecosystem. This includes:
    *   Registering hooks (actions and filters).
    *   Creating admin settings pages and fields.
    *   Enqueuing scripts and styles.
    *   Handling AJAX requests.
    *   Rendering shortcodes and other frontend output.

#### 1.3. PSR-4 Autoloading

The plugin uses a PSR-4 compliant autoloader, defined in `bootstrap.php`. All classes within the `StackBoost\ForSupportCandy` namespace are automatically loaded from the `src/` directory. This eliminates the need for manual `require_once` statements for classes.

#### 1.4. Singleton Pattern

The main plugin class (`StackBoost\ForSupportCandy\WordPress\Plugin`) and each module's `WordPress.php` adapter class are implemented as singletons. This ensures that only one instance of each is created, providing a single, stable point of access for managing hooks and state. Access them via the static `get_instance()` method.

#### 1.5. Feature Gating

Each module's functionality is wrapped in a conditional check using the `stackboost_is_feature_active('module_slug')` function. This function checks a central options array to see if the user has enabled the feature in the admin settings. This provides a clean and consistent way to enable or disable entire modules.

#### 1.6. Naming Conventions

To maintain consistency and avoid conflicts, the plugin follows strict naming conventions:

*   **Internal PHP:** All functions, classes, constants, and hooks use the `stackboost_` prefix (e.g., `stackboost_run()`, `class StackBoost_My_Class`).
*   **Public-Facing Assets:** All CSS classes, slugs, and database options use the `stackboost-` prefix (e.g., `.stackboost-notice`, `page=stackboost-settings`).
*   **Text Domain:** The text domain for internationalization is `stackboost-for-supportcandy`.

---

## 2. Core Plugin Components

These are the foundational files and classes that enable the plugin and its modular architecture.

### 2.1. `stackboost-for-supportcandy.php` (Main Plugin File)

*   **Purpose:** This is the entry point of the plugin recognized by WordPress.
*   **Key Actions:**
    *   Defines essential constants like `STACKBOOST_VERSION`, `STACKBOOST_PLUGIN_PATH`, etc.
    *   Includes the `bootstrap.php` file to kickstart the plugin's loading process.
    *   Handles plugin activation/deactivation hooks if necessary (though this is often delegated to module `Install` classes).

### 2.2. `bootstrap.php`

*   **Purpose:** This file is responsible for setting up the autoloader and initializing the main plugin class.
*   **Key Actions:**
    *   **`spl_autoload_register`**: Sets up the PSR-4 autoloader for the `StackBoost\ForSupportCandy` namespace, mapping it to the `src/` directory.
    *   **`stackboost_run()`**: This function is the primary launcher for the plugin. It calls `Plugin::get_instance()` to get the main plugin singleton and start its initialization process.
    *   Includes the `includes/functions.php` file for global helper functions.

### 2.3. `src/WordPress/Plugin.php`

*   **Purpose:** This is the main orchestrator class for the entire plugin. It is responsible for loading all the individual modules.
*   **Key Properties:**
    *   `$modules`: An array that holds the instances of all loaded module adapters (`WordPress.php` from each module).
*   **Key Methods:**
    *   `__construct()`: The constructor is where all modules are instantiated. To add a new module to the plugin, you must add it to the `$modules_to_load` array in this method.
    *   `init_hooks()`: This method iterates through all loaded modules and calls the `init_hooks()` method on each one, effectively delegating hook registration to the individual modules.
    *   `get_supportcandy_columns()`, `get_custom_field_id_by_name()`: These are helper methods that provide other modules with useful data from the SupportCandy plugin itself, acting as a service locator for SupportCandy data.

### 2.4. `src/Core/Module.php`

*   **Purpose:** An abstract base class that all module `WordPress.php` adapters should extend. It provides a common structure and shared functionality for settings rendering.
*   **Key Methods:**
    *   `__construct()`: Calls the `init_hooks()` method on the child class.
    *   `get_slug()`: An abstract method that forces child modules to declare their unique slug.
    *   `render_*_field()`: A collection of reusable methods (`render_checkbox_field`, `render_text_field`, etc.) for rendering standard HTML form elements on settings pages. This promotes UI consistency and reduces code duplication. Any new module can use these methods to build its settings page.

---

## 3. Module Documentation

This section provides a technical breakdown of each individual module.

### 3.1. After Hours Notice

*   **Namespace:** `StackBoost\ForSupportCandy\Modules\AfterHoursNotice`
*   **Slug:** `after_hours_notice`

#### Core Logic (`Core.php`)

*   `is_after_hours( array $settings, ?int $timestamp, string $timezone )`: The primary logic function. It takes an array of settings (hours, weekends, holidays) and a timestamp, and returns `true` if the time falls within the after-hours window. It correctly handles overnight periods (e.g., 5 PM to 8 AM).
*   `parse_holidays( string $holidays_string )`: A utility function that takes a newline-separated string of dates and parses it into a clean array of `Y-m-d` formatted strings.

#### WordPress Adapter (`WordPress.php`)

*   **Hooks:**
    *   `admin_init`: Used to call `register_settings()`.
    *   `supportcandy_before_create_ticket_form`: The main frontend hook. It triggers the `display_notice()` method.
*   **Key Methods:**
    *   `display_notice()`: Retrieves the plugin settings, gets the current WordPress timezone (`wp_timezone_string()`), and calls the `is_after_hours()` method from the `Core` class. If it returns true, it echoes the configured message.
    *   `register_settings()`: Uses the WordPress Settings API (`add_settings_section`, `add_settings_field`) to create the admin configuration page for this module. It utilizes the shared rendering methods from the parent `Module` class.

### 3.2. After Ticket Survey

*   **Namespace:** `StackBoost\ForSupportCandy\Modules\AfterTicketSurvey`
*   **Slug:** `after_ticket_survey`
*   **Database Tables:** This module creates four custom tables:
    *   `stackboost_ats_questions`: Stores the survey questions, types, and sort order.
    *   `stackboost_ats_dropdown_options`: Stores the options for dropdown questions.
    *   `stackboost_ats_survey_submissions`: Stores metadata for each survey submitted.
    *   `stackboost_ats_survey_answers`: Stores the actual answers, linked to a submission and a question.

#### Orchestration (`WordPress.php`)

This class is the central coordinator, instantiating the other classes (`Install`, `Shortcode`, `Admin`, `Ajax`) and registering all their hooks.

*   **Hooks:**
    *   `register_activation_hook`: Calls `Install::run_install()` to create the custom database tables.
    *   `admin_menu`: Calls `Admin::add_admin_menu()` to create the admin interface.
    *   `add_shortcode`: Registers `[stackboost_after_ticket_survey]` to be handled by `Shortcode::render_shortcode()`.
    *   `wp_ajax_*`: Registers AJAX handlers in the `Ajax` class.
    *   `wp_enqueue_scripts`, `admin_enqueue_scripts`: Enqueues module-specific CSS and JavaScript assets.

#### Shortcode Rendering (`Shortcode.php`)

*   `render_shortcode()`: The main entry point. It checks for a POST submission and, if present, calls `handle_submission()`. Otherwise, it calls `display_form()`.
*   `handle_submission()`: Processes the `$_POST` data. It creates a new entry in the `survey_submissions` table, then iterates through the questions and inserts each answer into the `survey_answers` table.
*   `display_form()`: Renders the survey form HTML. It retrieves questions from the database and calls `render_question_field()` for each one. It also handles pre-filling data (ticket ID, tech name) from URL `$_GET` parameters if the corresponding settings are configured.

#### Admin Interface (`Admin.php`)

*   `render_page()`: Renders the main tabbed interface for the module's admin page. It includes templates for each tab from the `Admin/` subdirectory.
*   `handle_admin_post()`: A central handler for all form submissions on the admin page (e.g., adding/editing questions, deleting submissions). It uses a `switch` on a hidden `form_action` field to determine the appropriate action.
*   `process_question_form()`: Contains the logic for creating and updating questions and their associated dropdown options in the database.

#### AJAX Handling (`Ajax.php`)

*   This class contains methods prefixed with `wp_ajax_` that are registered as AJAX endpoints. For example, `update_report_heading()` handles requests from the admin "Results" page to update a question's report heading in-place.

### 3.3. Conditional Views

*   **Namespace:** `StackBoost\ForSupportCandy\Modules\ConditionalViews`
*   **Slug:** `conditional_views`

#### Core Logic (`Core.php`)

*   `get_processed_rules( ?array $rules )`: This function's primary role is data sanitization. It takes the raw array of rules saved from the settings page and validates each property (`action`, `condition`, etc.), returning a clean, structured array that is safe to pass to the frontend.

#### WordPress Adapter (`WordPress.php`)

*   **Hooks:**
    *   `wp_enqueue_scripts`: Calls `enqueue_frontend_scripts()`.
    *   `admin_init`: Calls `register_settings()`.
*   **Key Methods:**
    *   `enqueue_frontend_scripts()`: This is the key integration point. It retrieves the saved rules, processes them using `Core::get_processed_rules()`, and then passes the clean ruleset to the main frontend JavaScript file (`stackboost-frontend.js`) via `wp_localize_script`. The JavaScript then has everything it needs to apply the rules without further AJAX calls.
    *   `register_settings()`: Creates the settings UI for the feature.
    *   `render_rule_builder()`: Renders the dynamic rule-building interface in the admin settings. It includes a JavaScript template (`#stackboost-rule-template`) that is used by the admin-side JS to add new rule rows dynamically.
    *   `get_supportcandy_views()`: A helper method that retrieves the available ticket filters/views from the SupportCandy options in the database (`wpsc-atl-default-filters`) to populate the "View" dropdown in the rule builder.

### 3.4. QOL Enhancements

*   **Namespace:** `StackBoost\ForSupportCandy\Modules\QolEnhancements`
*   **Slug:** `qol_enhancements`

#### Core Logic (`Core.php`)

*   `parse_types_to_hide( string $types_string )`: A simple utility that takes a newline-separated string of ticket type names and converts it into a clean array.

#### WordPress Adapter (`WordPress.php`)

This module is a collection of smaller, mostly frontend features. The PHP class is primarily responsible for adding settings fields and localizing the necessary data for the frontend JavaScript.

*   **Hooks:**
    *   `wp_enqueue_scripts`: Calls `enqueue_frontend_scripts()`.
    *   `admin_init`: Calls `register_settings()`.
*   **Key Methods:**
    *   `enqueue_frontend_scripts()`: This method gathers all the settings for the various QOL features (e.g., is the hover card enabled? which ticket types should be hidden?). It packages this information into a `$features` array and localizes it for the main frontend script using the `add_localized_features()` helper. This is a key pattern: the PHP code provides data, and the JavaScript provides the behavior.
    *   `register_settings()`: Adds all the settings fields for the various QOL features to the main plugin settings page. This includes the on/off toggles and the configuration fields for the "Hide Ticket Types" feature.
    *   `add_localized_features()`: A helper method designed to merge this module's feature flags and data with the main `stackboost_settings` JavaScript object. This avoids overwriting data from other modules and allows for a single, unified settings object on the frontend.

### 3.5. Queue Macro

*   **Namespace:** `StackBoost\ForSupportCandy\Modules\QueueMacro`
*   **Slug:** `queue_macro`

#### Core Logic (`Core.php`)

*   `calculate_queue_count( object $db, string $type_field, $type_value, array $statuses )`: Calculates the queue for a single, specific ticket type. It constructs a dynamic SQL query to count rows in the `psmsc_tickets` table where the `$type_field` matches `$type_value` and the `status` is in the `$statuses` array.
*   `get_all_queue_counts( object $db, string $type_field, array $statuses )`: Used by the AJAX test endpoint. It first finds all unique values in the specified `$type_field` and then iterates through them, calling `calculate_queue_count()` for each one to get a full report of all queue counts.
*   `get_id_to_name_map( object $db )`: A helper that queries various SupportCandy tables (`categories`, `statuses`, etc.) to build a comprehensive map of IDs to human-readable names. This is used by `get_all_queue_counts()` to provide more descriptive results.

#### WordPress Adapter (`WordPress.php`)

*   **Hooks:**
    *   `wpsc_macros`: Calls `register_macro()` to add `{{queue_count}}` to the list of available macros in SupportCandy.
    *   `wpsc_create_ticket_email_data`: This filter is the core of the feature. It calls `replace_macro_in_email()` before the new ticket email is sent.
    *   `wp_ajax_stackboost_test_queue_counts`: Wires up the `ajax_test_queue_counts()` method for the admin settings page.
*   **Key Methods:**
    *   `register_macro( array $macros )`: Adds the macro's tag and title to the array provided by the filter.
    *   `replace_macro_in_email( array $data, object $thread )`: Checks if the macro exists in the email body. If so, it retrieves the module's settings, determines the ticket's "type" value from the `$_POST` data of the new ticket form, and calls `Core::calculate_queue_count()`. It then uses `str_replace()` to insert the calculated count into the email body.
    *   `ajax_test_queue_counts()`: The handler for the "Run Test" button. It retrieves the saved settings and calls `Core::get_all_queue_counts()` to get the data, which it then returns as a JSON response.
    *   `render_statuses_dual_list_field()`: Renders a custom dual-list box UI for selecting the "Non-Closed Statuses" in the admin settings, providing a more user-friendly experience than a simple multi-select box.

---

## 4. Helper Functions and Global Context

This section covers globally available functions and important concepts for developers.

### 4.1. `includes/functions.php`

This file contains globally-scoped helper functions that can be accessed from anywhere in the plugin.

*   `stackboost_is_feature_active( string $feature_slug )`: This is the primary function for **feature gating**. It checks the main `stackboost_settings` option in the database to see if the feature corresponding to the given slug has been enabled by the user. The constructor of each module's `WordPress.php` class uses this function to determine if it should load itself.
    *   **Example:** `if ( ! stackboost_is_feature_active( 'queue_macro' ) ) { return; }`

### 4.2. Key Integration Patterns

When developing a new module, follow these established patterns.

*   **Adding a New Module:**
    1.  Create a new directory in `src/Modules/`.
    2.  Create your `Core.php` and `WordPress.php` classes inside the new directory, following the established pattern.
    3.  In `src/WordPress/Plugin.php`, add your new module's main class name to the `$modules_to_load` array in the constructor.
    4.  Your module will now be loaded and its `init_hooks()` method will be called automatically.

*   **Adding Frontend JavaScript Features:**
    1.  Add your feature's logic to the main frontend JavaScript file (e.g., `assets/js/stackboost-frontend.js`).
    2.  In your module's `WordPress.php` class, hook into `wp_enqueue_scripts`.
    3.  Inside your hooked function, gather any required data or settings.
    4.  Use the `add_localized_features()` method (or a similar filter-based approach) to pass your data to the `stackboost_settings` JavaScript object.
    5.  In your JavaScript, read the data from `stackboost_settings.features.your_feature_slug` to configure your script's behavior.

*   **Adding a New Admin Page:**
    1.  Hook into `admin_menu`. In your callback, use `add_submenu_page` to add your page under the main "StackBoost" menu.
    2.  Use the WordPress Settings API (`register_setting`, `add_settings_section`, `add_settings_field`) to build the form.
    3.  Leverage the `render_*_field()` methods in the base `Module.php` class to render common field types.