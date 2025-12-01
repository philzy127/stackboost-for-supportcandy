
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
