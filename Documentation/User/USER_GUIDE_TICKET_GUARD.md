# User Guide: Ticket Guard Engine

**Ticket Guard** is a real-time intake optimization and steering engine for SupportCandy. It monitors ticket description and text entry fields in real time to steer users toward appropriate ticket categories and eliminate generic or misclassified submissions (such as placing billing or system outage tickets into "General / Other").

---

## Key Features & How They Work

### 1. Real-Time Keyword Monitoring
* **Text Field Selection:** Select any text or textarea field to monitor (e.g. Description, Custom Textareas). Field dropdowns are alphabetized and searchable via Select2.
* **Compound Keyword Matching:**
  * **New Line = OR Condition:** Each line in the keyword list acts as an independent trigger condition.
  * **`&` Operator = AND Condition:** Use `&` on a line to require multiple phrases (e.g., `UC Portal & Password` requires both "UC Portal" AND "Password" in the text).

### 2. Smart Option Pre-Verification
* **Automatic Suppression:** Ticket Guard checks whether the user's form ALREADY has the corrective target option(s) selected. If the user has already chosen the correct category/option, Ticket Guard automatically suppresses warnings and modal popups so compliant users are never interrupted.

### 3. Action Levels & Escalation
Ticket Guard provides four independently selectable actions that can be combined based on the severity of the intake issue:

| Action Level | Type | Severity | Description |
| :--- | :--- | :--- | :--- |
| **Disable Submit Button** | Hard Block | High | Locks form submission until ticket details or categories are adjusted. Includes hover tooltips and click modal alerts explaining why the button is locked. |
| **Display Guidance Modal** | Interactive Steer | Medium-High | Displays a popup modal offering an option swap ("Switch Options") or an override ("Proceed Anyway"). |
| **Inline Warning Banner** | Soft Notice | Medium-Low | Renders real-time feedback beneath text inputs in Information (Green), Alert (Amber), or Warning (Red) styles. |
| **Automatically Swap Fields** | Automatic Steer | High / Silent | Automatically updates dropdowns, radio buttons, or checkboxes to the target options on the fly. |

---

## Action Details & Macro Placeholders

### Dynamic Placeholder Macros
Customize modal titles, body text, inline warnings, and submit disabled messages using dynamic placeholders:

* `{primary_field}`: Resolves to the name of the Primary Target Field (e.g., "Category").
* `{primary_response}` / `{target_option}`: Resolves to the label of the Primary Target Option (e.g., "Billing & Invoices").
* `{secondary_field}`: Resolves to the name of the Secondary Target Field (e.g., "Priority").
* `{secondary_response}`: Resolves to the label of the Secondary Target Option (e.g., "High").
* `{fix_it_button}`: Explicitly positions the "Fix It" action icon button within your custom inline warning text.

### Inline Warning Banner Icons
When corrective target options are configured for an inline warning rule:
* **Fix It Icon Button (Tools Icon):**
  * Displays a tools icon (`dashicons-admin-tools`) with a **green hover effect** (`#16a34a`).
  * Clicking "Fix It" automatically applies the configured target option swaps, clears the warning banner, and unlocks submission.
  * Appears automatically at the right of the banner, or at the exact location of the `{fix_it_button}` macro placeholder.
* **Dismiss Icon Button (Dismiss Icon):**
  * Displays a dismiss icon (`dashicons-dismiss`) with an **orange hover effect** (`#f59e0b`).
  * Clicking "Dismiss" dismisses the warning for the user's session and re-enables the submit button to handle valid edge cases without blocking legitimate requests.

---

## Combining Actions by Severity (Recipes)

### Recipe 1: Soft Nudge (Low Severity)
* **Goal:** Remind users typing billing keywords in a general text field to consider the Billing queue, without forcing a block.
* **Configuration:**
  * **Monitored Field:** Description
  * **Keywords:** `invoice`, `overcharge`, `credit card`
  * **Action:** Enable *Show Real-Time Inline Warning Banner* (Notice Style: Alert Amber)
  * **Target Field:** Primary Field = Category, Primary Option = Billing & Invoices
* **User Experience:** An amber banner appears beneath the text area with a green tools icon ("Fix It") and an orange dismiss icon. The user can click "Fix It" to auto-select Billing, or click Dismiss / proceed normally.

### Recipe 2: Interactive Guidance (Medium Severity)
* **Goal:** Interrupt users describing account lockouts or password issues to guide them toward Self-Service or IT Access queues.
* **Configuration:**
  * **Monitored Field:** Description
  * **Keywords:** `password reset`, `account locked`, `UC Portal & Password`
  * **Action:** Enable *Display Guidance Modal Popup*
  * **Target Field:** Primary Field = Category, Primary Option = IT Security & Access
  * **Guidance Body Text:** `It looks like you are describing an issue with {primary_response}. Switching to {primary_field} will ensure your ticket reaches the security team immediately.`
* **User Experience:** A modal popup opens giving the user a clear choice: "Switch Options" (which automatically updates the field and closes the modal) or "Proceed Anyway".

### Recipe 3: Hard Block & Stop (High Severity)
* **Goal:** Prohibit users from submitting critical outage or security emergency keywords under generic categories.
* **Configuration:**
  * **Monitored Field:** Description
  * **Keywords:** `server down`, `databreach`, `ransomware`
  * **Actions:** Enable *Disable Submit Button* AND *Display Guidance Modal Popup*
  * **Target Field:** Primary Field = Category, Primary Option = Critical Outage Emergency
* **User Experience:** The submit button is locked with a grayscale/disabled appearance and hover tooltip. Clicking the disabled submit button displays an explanatory modal dialog. Switching the category to Critical Outage instantly unlocks the submit button.

---

## Management & Instant AJAX Saving

* **Searchable Dropdowns:** Target fields and option selectors are alphabetized and searchable using built-in SelectWoo integration.
* **Instant AJAX Saving:** Adding rules, updating rule configurations, deleting rules, or toggling the global engine switch saves instantly via background AJAX with real-time toast notification acknowledgements. No manual page-level save button required.
