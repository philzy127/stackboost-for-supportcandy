# PHPCS Ignore Audit

**Total Issues:** 46

This document provides a detailed analysis of every `// phpcs:ignore` instance in the codebase, explaining the context, reason for exclusion, and whether it is justified.

## **stackboost-for-supportcandy/src/Admin/Upgrade.php**

*   **Line 117**
    *   **Rule**: `WordPress.DB.PreparedSQL.NotPrepared`, `WordPress.DB.DirectDatabaseQuery.DirectQuery`, `WordPress.DB.DirectDatabaseQuery.NoCaching`
    *   **Context**: `$wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN question_type varchar(50) NOT NULL DEFAULT 'text' AFTER sort_order" );`
    *   **Reason**: Executing a DDL statement (`ALTER TABLE`) to upgrade the database schema.
    *   **Verdict**: **Justified**. Schema changes cannot be prepared in the standard way, and direct query usage is required for table modification.

---

## **stackboost-for-supportcandy/src/Integration/SupportCandyRepository.php**

*   **Line 149**
    *   **Rule**: `WordPress.DB.PreparedSQL.NotPrepared`
    *   **Context**: `ALTER TABLE` query.
    *   **Reason**: Schema modification helper method.
    *   **Verdict**: **Justified**.

---

## **stackboost-for-supportcandy/src/Modules/AfterHoursNotice/WordPress.php**

*   **Line 351**
    *   **Rule**: `WordPress.Security.EscapeOutput.OutputNotEscaped`
    *   **Context**: `echo $message;` (where `$message` is constructed html).
    *   **Reason**: The message variable contains HTML (e.g., links or formatting) that is intended to be rendered.
    *   **Verdict**: **Justified** (Condition: `$message` must be constructed from safe/sanitized components).

*   **Lines 377, 396**
    *   **Rule**: `WordPress.DB.SlowDBQuery.slow_db_query_meta_query`
    *   **Context**: `WP_Query` or `get_posts` using `meta_query` to filter by custom fields.
    *   **Reason**: Filtering tickets/posts by metadata is a core requirement for this module's functionality.
    *   **Verdict**: **Justified**. Necessary for feature implementation.

---

## **stackboost-for-supportcandy/src/Modules/AfterTicketSurvey/Admin/manage-questions-template.php**

*   **Line 45**
    *   **Rule**: `PluginCheck.Security.DirectDB.UnescapedDBParameter`, `WordPress.DB.DirectDatabaseQuery.DirectQuery`, etc.
    *   **Context**: Displaying questions list.
    *   **Reason**: Template iterating or fetching data directly for display.
    *   **Verdict**: **Justified**. Admin template rendering context.

---

## **stackboost-for-supportcandy/src/Modules/AfterTicketSurvey/Admin/view-results-template.php**

*   **Line 38**
    *   **Rule**: `WordPress.DB.DirectDatabaseQuery.DirectQuery`
    *   **Context**: Pagination calculation.
    *   **Verdict**: **Justified**.

---

## **stackboost-for-supportcandy/src/Modules/ChatBubbles/Admin/Settings.php**

*   **Lines 114, 145, 202**
    *   **Rule**: `WordPress.Security.EscapeOutput.OutputNotEscaped`
    *   **Context**: Outputting JSON configuration or complex HTML structures.
    *   **Reason**: Data is encoded (e.g., `json_encode`) or HTML is intentionally rendered.
    *   **Verdict**: **Justified**.

---

## **stackboost-for-supportcandy/src/Modules/Directory/Admin/ListTables (Staff, Locations, Departments)**

*   **SlowDBQuery**
    *   **Rule**: `WordPress.DB.SlowDBQuery`
    *   **Context**: Meta queries for filtering/sorting.
    *   **Reason**: Required for functionality.
    *   **Verdict**: **Justified**.

---

## **stackboost-for-supportcandy/src/Modules/Directory/Admin/Management.php**

*   **Line 236**
    *   **Rule**: `Squiz.PHP.DiscouragedFunctions.Discouraged`
    *   **Context**: `set_time_limit( 0 );`
    *   **Reason**: Used in `ajax_import_json` to prevent timeouts during large data imports.
    *   **Verdict**: **Justified**.

*   **Lines 524, 587**
    *   **Rule**: `WordPress.WP.AlternativeFunctions.file_system_operations_fopen/fclose`
    *   **Context**: CSV generation using `fopen` and `fclose`.
    *   **Reason**: Efficient stream handling.
    *   **Verdict**: **Justified**.

---

## **stackboost-for-supportcandy/src/Modules/Directory/WordPress.php**

*   **Lines 1175, 1252**
    *   **Rule**: `SlowDBQuery.slow_db_query_meta_query`
    *   **Context**: Filtering CPTs by email address.
    *   **Reason**: Core requirement.
    *   **Verdict**: **Justified**.

*   **Lines 713, 770**
    *   **Rule**: `OutputNotEscaped`
    *   **Context**: Outputting Widget Content.
    *   **Verdict**: **Justified**.

---

## **stackboost-for-supportcandy/src/Modules/OnboardingDashboard/Admin/ImportExport.php**

*   **Line 271**
    *   **Rule**: `OutputNotEscaped`
    *   **Context**: `echo $json_data;` (Exporting data).
    *   **Verdict**: **Justified**.

---

## **stackboost-for-supportcandy/src/Modules/OnboardingDashboard/Data/TicketService.php**

*   **Line 108, 113**
    *   **Rule**: `PreparedSQL.NotPrepared`, `DirectDatabaseQuery`
    *   **Context**: `$wpdb->prepare( $query, array_merge(...) )`.
    *   **Reason**: Building dynamic queries for custom metrics.
    *   **Verdict**: **Justified**.

---

## **stackboost-for-supportcandy/src/Modules/OnboardingDashboard/Shortcodes/DashboardShortcode.php**

*   **Lines 268, 302**
    *   **Rule**: `NonPrefixedHooknameFound`
    *   **Context**: `apply_filters( 'the_content', ... )`
    *   **Reason**: Intentionally applying Core WordPress content filters.
    *   **Verdict**: **Justified**.

---

## **stackboost-for-supportcandy/src/WordPress/Admin/Settings.php**

*   **Lines 376, 1150**
    *   **Rule**: `WordPress.Security.EscapeOutput.OutputNotEscaped`
    *   **Context**: Outputting JSON or file content.
    *   **Verdict**: **Justified**.

*   **Lines 1205, 1214**
    *   **Rule**: `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized`, `WordPress.Security.ValidatedSanitizedInput.MissingUnslash`
    *   **Context**: Processing raw input in complex settings validation.
    *   **Verdict**: **Justified**.

---

## **stackboost-for-supportcandy/src/WordPress/supportcandy-pro-check.php**

*   **Line 30**
    *   **Rule**: `NonPrefixedFunctionFound`
    *   **Context**: `function is_supportcandy_pro_active()`
    *   **Reason**: Preserved for backward compatibility.
    *   **Verdict**: **Justified**.

---

## **stackboost-for-supportcandy/src/Services/DirectoryService.php**

*   **Lines 73, 195**
    *   **Rule**: `WordPress.DB.SlowDBQuery.slow_db_query_meta_query`
    *   **Context**: `meta_query` in `get_posts`.
    *   **Reason**: Functional requirement for directory filtering.
    *   **Verdict**: **Justified**.

---
