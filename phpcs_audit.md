# PHPCS Ignore Audit

**Total Issues:** 31

This document provides a detailed, line-by-line inventory of every `// phpcs:ignore` instance in the codebase, explaining the context and the detailed reasoning for each exclusion.

## **stackboost-for-supportcandy/src/Admin/Upgrade.php**

*   **Line 117**
    *   **Rule**: `WordPress.DB.PreparedSQL.NotPrepared`, `WordPress.DB.DirectDatabaseQuery.DirectQuery`, `WordPress.DB.DirectDatabaseQuery.NoCaching`
    *   **Context**: Executing an `ALTER TABLE` statement via `$wpdb->query` to add a column (`question_type`) to the database table if it does not exist.
    *   **Reason**: Schema modification (DDL) cannot be executed via standard prepared statements or `wp_insert`/`wp_update`. Direct database access is required to modify table structure during the upgrade process. The table name is constructed using `$wpdb->prefix`, ensuring safety.

---

## **stackboost-for-supportcandy/src/Integration/SupportCandyRepository.php**

*   **Line 148**
    *   **Rule**: `WordPress.DB.PreparedSQL.NotPrepared`
    *   **Context**: Executing `ALTER TABLE` statements within the `add_column_if_not_exists` helper method.
    *   **Reason**: As with the Upgrade class, Data Definition Language (DDL) statements for schema changes cannot be prepared using placeholders. This repository class isolates this logic to prevent direct DB access in the main business logic.

---

## **stackboost-for-supportcandy/src/Modules/AfterHoursNotice/WordPress.php**

*   **Lines 376, 395**
    *   **Rule**: `WordPress.DB.SlowDBQuery.slow_db_query_meta_query`
    *   **Context**: Using `WP_Query` or `get_posts` with `meta_query` to filter SupportCandy tickets or posts based on custom field values (e.g., ticket status or custom timestamps).
    *   **Reason**: The module's core functionality requires filtering content based on metadata that is not available in the main posts table. While meta queries can be slower, they are necessary here to retrieve the correct subset of data. Indexes are used where possible on custom tables.

---

## **stackboost-for-supportcandy/src/Modules/Directory/Admin/LocationsListTable.php**

*   **Line 282**
    *   **Rule**: `WordPress.DB.SlowDBQuery.slow_db_query_meta_key`
    *   **Context**: Sorting Locations by custom fields in `prepare_items`.
    *   **Reason**: Sorting CPTs by metadata requires a meta key in the query.

*   **Line 293**
    *   **Rule**: `WordPress.DB.SlowDBQuery.slow_db_query_meta_query`
    *   **Context**: Searching Locations by custom fields in `prepare_items`.
    *   **Reason**: Searching across multiple custom fields (e.g., address, city) necessitates a meta query.

---

## **stackboost-for-supportcandy/src/Modules/Directory/Admin/Management.php**

*   **Line 236**
    *   **Rule**: `Squiz.PHP.DiscouragedFunctions.Discouraged`
    *   **Context**: Calling `set_time_limit( 0 )` within the `ajax_import_json` function.
    *   **Reason**: This function handles the import of potentially large JSON datasets for the Staff Directory. Setting the time limit to zero prevents the process from timing out during a long-running import operation, ensuring data integrity.

*   **Line 461**
    *   **Rule**: `WordPress.DB.DirectDatabaseQuery.DirectQuery`, `WordPress.DB.DirectDatabaseQuery.NoCaching`
    *   **Context**: Truncating the custom table `stackboost_directory_meta`.
    *   **Reason**: `TRUNCATE TABLE` is a direct database operation that cannot be performed via standard WP functions. It is required for the "Fresh Start" / "Clear Data" functionality.

*   **Lines 524, 587**
    *   **Rule**: `WordPress.WP.AlternativeFunctions.file_system_operations_fopen`, `WordPress.WP.AlternativeFunctions.file_system_operations_fclose`
    *   **Context**: Using `fopen` and `fclose` to generate CSV export files on the fly.
    *   **Reason**: PHP streams are the most memory-efficient way to generate large CSV files. The WordPress Filesystem API is primarily designed for file management rather than stream-based writing.

---

## **stackboost-for-supportcandy/src/Modules/Directory/Admin/StaffListTable.php**

*   **Lines 331, 335**
    *   **Rule**: `WordPress.DB.SlowDBQuery.slow_db_query_meta_key`
    *   **Context**: Sorting Staff by custom fields (e.g., Job Title, Department) in `prepare_items`.
    *   **Reason**: Sorting CPTs by metadata requires ordering by meta keys.

*   **Line 346**
    *   **Rule**: `WordPress.DB.SlowDBQuery.slow_db_query_meta_query`
    *   **Context**: Searching Staff by custom fields in `prepare_items`.
    *   **Reason**: Searching staff by name, email, or department requires a meta query against the CPT meta.

---

## **stackboost-for-supportcandy/src/Modules/Directory/Data/MetaBoxes.php**

*   **Line 276**
    *   **Rule**: `WordPress.Security.ValidatedSanitizedInput.MissingUnslash`, `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized`
    *   **Context**: Verifying `$_POST` existence check for saving meta box data.
    *   **Reason**: This is a simple existence check (`isset`) before processing. Actual processing uses `Request` or proper sanitization later in the block.

*   **Line 294**
    *   **Rule**: `WordPress.DB.DirectDatabaseQuery.DirectQuery`, `WordPress.DB.DirectDatabaseQuery.NoCaching`
    *   **Context**: Deleting from a custom table (`stackboost_directory_relations`).
    *   **Reason**: Managing many-to-many relationships (Staff <-> Departments) in a custom table requires direct SQL DELETE/INSERT operations as there is no WP core API for custom relationship tables.

*   **Line 401**
    *   **Rule**: `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized`, `WordPress.Security.ValidatedSanitizedInput.MissingUnslash`
    *   **Context**: Loop variables for saving array data (MetaBoxes.php).
    *   **Reason**: The inputs are sanitized individually inside the loop (e.g., `absint`). The linter flags the array iteration itself.

---

## **stackboost-for-supportcandy/src/Modules/Directory/WordPress.php**

*   **Lines 487, 492**
    *   **Rule**: `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized`, `WordPress.Security.ValidatedSanitizedInput.MissingUnslash`
    *   **Context**: Checking `$_POST` variables (`post_id`, `post_type`) in `ajax_get_staff_details`.
    *   **Reason**: The values are sanitized immediately using `absint()` or verified against known strings. The linter flags the initial `isset` or raw access before the sanitization line.

*   **Lines 1180, 1257**
    *   **Rule**: `WordPress.DB.SlowDBQuery.slow_db_query_meta_query`
    *   **Context**: Filtering Directory CPTs by `_stackboost_email_address` using a `meta_query`.
    *   **Reason**: The module links WordPress Users/SupportCandy Customers to Directory Staff entries via email address. This lookup is essential to display the correct contact widget and requires a meta query against the staff CPT.

---

## **stackboost-for-supportcandy/src/Modules/OnboardingDashboard/Admin/ImportExport.php**

*   **Line 271**
    *   **Rule**: `WordPress.Security.EscapeOutput.OutputNotEscaped`
    *   **Context**: Echoing `$json_data` to serve a JSON file download.
    *   **Reason**: This occurs after setting headers for a file download. The content is a JSON-encoded string intended to be saved as a file. Escaping it would corrupt the JSON structure.

---

## **stackboost-for-supportcandy/src/Modules/OnboardingDashboard/Admin/Settings.php**

*   **Lines 259, 286, 536**
    *   **Rule**: `WordPress.Security.NonceVerification.Recommended`
    *   **Context**: Checking `$_GET['tab']` to determine the active tab for rendering.
    *   **Reason**: Tabs are navigational elements. The value is sanitized via `sanitize_key` or compared against a whitelist before use. Nonce verification is not required for simple navigation state.

---

## **stackboost-for-supportcandy/src/Modules/OnboardingDashboard/Data/TicketService.php**

*   **Line 38**
    *   **Rule**: `WordPress.DB.SlowDBQuery.slow_db_query_meta_query`
    *   **Context**: Filtering tickets by custom fields in `get_tickets`.
    *   **Reason**: Custom reporting requirements necessitate meta queries.

*   **Lines 106, 113**
    *   **Rule**: `WordPress.DB.DirectDatabaseQuery.DirectQuery`, `WordPress.DB.DirectDatabaseQuery.NoCaching`, `WordPress.DB.PreparedSQL.InterpolatedNotPrepared`
    *   **Context**: Executing a custom certificate query (`COUNT(*) ... GROUP BY ticket_id`).
    *   **Reason**: This complex aggregate query is not supported by standard `WP_Query` or `WPSC_Ticket::find`. The table name is derived from `$wpdb->prefix` (verified safe), but `InterpolatedNotPrepared` is flagged because it's not a literal string. `DirectQuery` is necessary for custom table aggregation.

---

## **stackboost-for-supportcandy/src/Services/DirectoryService.php**

*   **Lines 73, 195**
    *   **Rule**: `WordPress.DB.SlowDBQuery.slow_db_query_meta_query`
    *   **Context**: Using `meta_query` in `get_posts` to retrieve staff members based on email or other custom attributes.
    *   **Reason**: Core requirement for the Directory Service to map external identifiers (like email) to Staff CPT entries. The data architecture (CPT + Meta) mandates this query structure.

---

## **stackboost-for-supportcandy/src/WordPress/Admin/Settings.php**

*   **Line 1105**
    *   **Rule**: `WordPress.Security.EscapeOutput.OutputNotEscaped`
    *   **Context**: Outputting file content for log download.
    *   **Reason**: The content is a plain text log file served as a download attachment. Escaping functions would break the file stream.

*   **Line 1172**
    *   **Rule**: `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized`
    *   **Context**: Retrieving `$_POST['stackboost_settings']` before passing to a sanitizer callback.
    *   **Reason**: The value is passed immediately to `sanitize_settings` (via `register_setting` callback mechanism simulation), where comprehensive sanitization occurs.
