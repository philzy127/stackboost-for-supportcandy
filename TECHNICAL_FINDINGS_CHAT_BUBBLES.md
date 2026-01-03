# Technical Findings: Chat Bubbles in SupportCandy Emails

## Problem Analysis
The goal was to style the ticket history (rendered via the `{{ticket_history}}` macro) in SupportCandy email notifications using the "Chat Bubbles" design.
However, SupportCandy's architecture presents a specific constraint:
1.  The `{{ticket_history}}` macro is processed internally by the plugin.
2.  The resulting HTML is hardcoded (a simple `<table>` structure).
3.  There are **no hooks** available during the macro replacement process to modify this HTML or wrap it in custom containers.
4.  The primary email hook, `wpsc_en_before_sending`, runs *after* all macros have been replaced, leaving us with a flat HTML string where the history is indistinguishable from other content.

## Solution Strategy: "Option Injection"
To solve this without modifying the core plugin, we utilized the `option_{option_name}` WordPress filter.

### 1. Intercepting the Template
SupportCandy fetches email templates using `get_option()`. We hooked into the following options:
*   `wpsc-en-reply-ticket`
*   `wpsc-en-create-ticket`
*   `wpsc-en-close-ticket`
*   (and others)

**Mechanism:**
Inside the `option_` filter, we inspect the raw template string (e.g., `Hi {{customer_name}}, {{ticket_history}}`).
We replace the macro with a wrapped version using HTML comments as markers:
`<!--SB_HISTORY_START-->{{ticket_history}}<!--SB_HISTORY_END-->`

### 2. Processing the Body
We then hook into `wpsc_en_before_sending`. The `$email_notification->body` now contains the expanded history HTML *between* our markers.

**Regex Parsing:**
We use a regex to extract the block between `<!--SB_HISTORY_START-->` and `<!--SB_HISTORY_END-->`.
Inside that block, we parse the specific SupportCandy HTML structure:
```regex
#<strong>\s*(.*?)\s*<small>\s*<i>\s*(.*?)\s*</i>\s*</small>\s*</strong>\s*<div style="font-size:10px;">\s*(.*?)\s*</div>\s*(.*?)(?=<br><hr><br>|$)#si
```
*   Group 1: Author Name
*   Group 2: Action (replied, reported, etc.)
*   Group 3: Date
*   Group 4: Content

### 3. Styling
We iterate through the matches, determine the user type (Agent vs Customer) based on the Author Name, and apply the Chat Bubbles inline CSS.

## Logging & Debugging
To effectively debug this flow, logs must be placed in two specific locations:
1.  **Template Level:** Inside the `inject_history_markers` method (the `option_` filter). This is the only place to see the raw template *before* SupportCandy touches it.
2.  **Body Level:** At the start of `process_email_content` (the `wpsc_en_before_sending` hook). This confirms that the markers survived the macro expansion process.

## Key Files
*   `src/Modules/ChatBubbles/Core.php`: Contains all logic for hooking options, injecting markers, and parsing the email body.
