
## After Ticket Survey Enhancements (v1.3.0)

### Modernized "Manage Questions" Interface
*   **Modal Editing:** Adding and editing questions is now done in a clean popup modal, replacing the old inline form at the bottom of the page.
*   **Drag-and-Drop Reordering:** You can now easily reorder questions by dragging and dropping rows in the table. Changes are saved automatically.
*   **Top-Level Actions:** The "Add New Question" button has been moved to the top of the page for better visibility.

### URL Prefill & Smart Matching
*   **Prefill Key:** Every question type now has an optional **"URL Parameter (Prefill Key)"** setting.
    *   This allows you to pre-fill survey answers by passing data in the survey link (e.g., `&ticket_id=12345` or `&rating=5`).
    *   Simply enter the parameter name (e.g., `ticket_id`) in the question settings to link it.
*   **Editable Fields:** Pre-filled fields remain fully editable by the user, ensuring they can correct any wrong information.
*   **Smart Matching for Dropdowns:**
    *   Dropdown fields now use "Smart Logic" to select the correct option from a URL parameter.
    *   **Logic:**
        1.  **Exact Match:** It first looks for an exact match (case-insensitive).
        2.  **Best Partial Match:** If no exact match is found, it looks for the best partial match. This handles cases where the URL value is shorter (e.g., "Philip") but the dropdown option is longer (e.g., "Philip Edwards"), or vice-versa (e.g., URL has "Ticket #123" and option is "123").
    *   **Note:** Dropdowns stop at the *first* best match found. This logic does not support "fuzzy" matching for misspellings, but is designed for data structure variations.

## Company Directory Enhancements (v1.2.14)

### New Features
*   **Click-to-Call Phone Numbers:**
    *   Phone numbers in the directory table and modal are now clickable links (`tel:`).
    *   These links automatically handle extensions (using the RFC 3966 standard `;ext=`), ensuring they work correctly on mobile devices.
    *   The link target includes the international format (preserving `+`), but the visual display remains user-friendly.

*   **Copy to Clipboard:**
    *   Added copy buttons next to all phone numbers (Office and Mobile).
    *   **Smart Formatting:** When copying a phone number, the text placed on the clipboard matches the formatted display (e.g., `(555) 123-4567 ext. 890`) rather than the raw link format. This ensures pasted numbers look professional.
    *   Visual feedback (green checkmark and toast notification) confirms the action.

*   **Smart "Numbers-Only" Search:**
    *   The directory search bar now features intelligent phone number filtering.
    *   You can search for a phone number using any format (e.g., `5551234`, `555-1234`, `(555) 1234`) and it will correctly match the record regardless of how it is stored or displayed.
    *   This "Numbers-to-Numbers" logic strips punctuation from both your search query and the stored data to find the match.
    *   Search behavior for Names, Titles, and Departments remains standard (text-based).

### Usage
*   **Directory Table:** The features are automatically active on the `[stackboost_directory]` shortcode.
*   **Directory Widget:** The SupportCandy ticket widget also inherits the click-to-call and copy functionality.

## After-Hours Message (v1.2.9)

### Editor Enhancements
*   The text editor for the "After-Hours Message" now includes an expanded toolbar for better formatting control.
*   **New Options:** You can now easily add **Bold**, *Italic*, Underline, Text Color, Bulleted/Numbered Lists, Indentation, Blockquotes, Horizontal Lines, and Alignment.
*   The editor retains its compact size ("teeny" mode) to keep the settings page clean but provides these powerful formatting tools when needed.
