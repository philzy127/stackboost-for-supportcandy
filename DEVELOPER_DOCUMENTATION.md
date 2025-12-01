
## Directory Module Development (v1.2.14)

### Phone Number Handling

*   **Central Formatting Function:** `stackboost_format_phone_number( string $phone, string $extension, string $copy_icon_svg ): string`
    *   Located in `includes/functions.php`.
    *   Used by both the Directory Shortcode (`DirectoryShortcode.php`) and the Modal Template (`directory-modal-content.php`).
    *   **Logic:**
        *   Generates an RFC 3966 compliant `tel:` URI (e.g., `tel:+15551234567;ext=890`).
        *   Preserves the user-friendly display format.
        *   Appends a copy icon `<span>`.
        *   **Crucial:** Adds a `data-copy-text` attribute to the `<span>` containing the exact formatted display string.

*   **Copy Functionality (`stackboost-directory.js`)**
    *   **Event Delegation:** Listeners are attached to `document` to handle dynamic content (DataTables, Modals).
    *   **Priority:** The script checks for `data-copy-text` first. If present, it uses that value. If not, it falls back to constructing the number from `data-phone` and `data-extension`.
    *   **Clipboard API:** Uses `navigator.clipboard.writeText` with a robust `document.execCommand` fallback for older contexts or non-secure origins.

*   **DataTables Custom Search**
    *   A custom filter is pushed to `$.fn.dataTable.ext.search` in `stackboost-directory.js`.
    *   **Phone Column (Index 1):** Strips all non-digit characters (`\D`) from both the search input and the table cell data before comparing. This enables "fuzzy" phone search.
    *   **Other Columns:** Uses standard substring matching (stripping HTML tags).
    *   **Initialization:** The script hijacks the default DataTables search input (`unbind` default, `bind` custom) to trigger a redraw, which fires the custom filter.

### Troubleshooting & Robustness

*   **DataTables Crash Protection:**
    *   DataTables will crash (`cloneNode` error) if initialized on a hidden element (e.g., inside a non-active tab) when `responsive: true` is set.
    *   **Fix:** `stackboost-directory.js` wraps initialization in a `try...catch`. If the responsive init fails, it automatically falls back to a standard initialization (`responsive: false`, `autoWidth: false`) and logs a warning.
    *   **Listeners:** Click listeners are defined *before* the DataTables init block to ensure they work even if the table fails to render enhanced features.

*   **Logging:**
    *   Frontend logging uses `sbLog()` and `sbError()` wrappers.
    *   These respect the `debug_enabled` flag passed from `WordPress.php` (derived from the `enable_logging` setting).

## After-Hours Message (v1.2.9)

### WYSIWYG Editor Customization
*   The `AfterHoursNotice` module overrides the standard `render_wp_editor_field` method.
*   **Teeny Mode Override:** To provide rich formatting options (Text Color, HR) within the compact `teeny` editor mode, the module explicitly loads the `textcolor` and `hr` TinyMCE plugins via the `tinymce` configuration array.
*   **Toolbar Configuration:** The `toolbar1` setting is manually defined to include these custom buttons alongside standard formatting options.
