# Technical Feasibility Report: Ticket Guard (Intake Optimization System)

**Module Name:** Ticket Guard
**Target Platform:** SupportCandy via StackBoost for SupportCandy
**Document Status:** Complete Feasibility Analysis & Technical Design
**Date:** Current

---

## 1. Executive Summary

This feasibility report evaluates the architectural design, technical viability, user experience impact, and implementation strategy for **Ticket Guard**—a proposed rules-based intake optimization feature module for **StackBoost for SupportCandy**.

### Core Objective
Support organizations using SupportCandy frequently encounter the "dreaded 'Other' ticket" phenomenon: end-users select generic categories (e.g., "Other", "General Inquiry", "Miscellaneous") rather than specific ticket types (e.g., "Billing Dispute", "Password Reset", "Software Bug"). This leads to misrouted tickets, delayed First Response Times (FRT), increased manual triage overhead, and inaccurate metrics.

**Ticket Guard** serves as a proactive, client-side and server-side intake steering engine. It monitors selected text input fields in real-time during ticket creation, detects specific keywords, phrases, or regex patterns, and actively intervenes to guide the user toward selecting more accurate ticket categories or fields before submission.

---

## 2. Key Capabilities & Configuration Options

To provide maximum flexibility for administrators, Ticket Guard will support independently selectable action options on a per-rule or global basis:

1. **Monitored Fields Selector:**
   - Ability to target individual standard fields (Subject `df_subject`, Description `df_description`) and custom text/textarea fields (`cf_*`).
2. **Keyword & Phrase Matching Engine:**
   - Multi-mode matching: exact match, case-insensitive substring, multi-keyword phrases (AND/OR logic), and regular expressions (Regex).
3. **Independently Selectable Action Toggles:**
   - **[x] Disable Submit Button:** Dynamically locks the SupportCandy form submit button when trigger terms are detected, preventing ticket submission until the user selects a suggested ticket type or removes the offending input.
   - **[x] Modal Dialog Notice / Guidance:** Displays a modern, responsive StackBoost modal popup (`.stackboost-modal`) upon keyword detection or submission attempt. Explains why the current selection may be incorrect and offers interactive call-to-action (CTA) buttons (e.g., *"Change Category to Billing"* vs. *"Proceed Anyway"*).
   - **[x] Inline Warnings / Banners:** Renders a real-time contextual warning banner directly above or below the monitored field or submit area with custom icons, colors, and instructions.
   - **[x] Ticket Type / Category Swaps:** Automatically or interactively changes the active ticket type/category dropdown (`df_category`, `df_type`, or custom dropdown `cf_*`) to a pre-configured, more appropriate target value.

---

## 3. Architectural Compatibility with SupportCandy

### 3.1 Form Lifecycle & DOM Structure
SupportCandy renders create-ticket forms in two primary modes:
1. **Shortcode View:** Embedded on pages via `[wpsc_create_ticket]` or within the main SupportCandy container (`#wpsc-container`).
2. **Modal View:** Triggered via admin/frontend modal popups (`.wpsc-modal-container`).

Both modes output standard HTML form controls (`<input type="text">`, `<textarea>`, `<select>`). Dropdowns are initialized using SelectWoo/Select2 (`.select2-hidden-accessible`).

### 3.2 Dynamic & AJAX Loading
SupportCandy heavily relies on AJAX to load form steps, dynamically re-render fields when categories change, or load the creation form inside popups.
- **Feasibility:** High. StackBoost's existing modules (e.g., `ConditionalOptions`, `ConditionalViews`) already monitor AJAX lifecycle events using delegated jQuery listeners and `$(document).ajaxComplete()`.
- **Observer Mechanism:** Ticket Guard will attach event delegation to the parent container (`document` or `#wpsc-container`), guaranteeing that dynamically injected inputs are immediately monitored without requiring page reloads.

### 3.3 Text Area & Rich Text Editor (WYSIWYG) Support
SupportCandy description fields can render as plain `<textarea>` elements or rich-text WYSIWYG editors (TinyMCE, HTML editors).
- **Feasibility:** Plain textareas respond directly to `input`, `keyup`, and `paste` DOM events.
- **WYSIWYG Handling:** For TinyMCE instances, Ticket Guard will hook into editor initialization (`tinymce.on('AddEditor')`) to attach `keyup` and `change` event observers to the editor iframe content. If plain textareas are used, standard debounced DOM observers will be used.

---

## 4. Detailed Feature Feasibility & Action Analysis

| Action / Feature | Feasibility | Technical Implementation Mechanism | Key UX / Operational Benefit |
| :--- | :--- | :--- | :--- |
| **Monitored Fields Selector** | **High** | Query standard (`df_subject`, `df_description`) and custom field metadata via `\WPSC_Custom_Field::get_cf_by_slug()`. | Allows targeted monitoring of subject lines or detailed descriptions without affecting unrelated fields. |
| **Keyword/Phrase Engine** | **High** | Client-side JS regex/string matching combined with PHP backend regex validation. | Flexible rule definitions covering common problem terms (e.g., "refund", "invoice" -> Billing). |
| **Disabling Submit Button** | **High** | Target SupportCandy submit button (`.wpsc-submit-btn`, `#wpsc-submit`), toggle `disabled` attribute & apply CSS opacity class. | Prevents users from blindly submitting generic tickets when better options exist. |
| **Modal Warning / Guidance** | **High** | Utilize StackBoost's standardized modal markup (`.stackboost-modal`, `.stackboost-modal-content`). | High-visibility intervention that explains *why* another ticket type is recommended. |
| **Inline Warnings / Banners** | **High** | Inject lightweight `.stackboost-tg-warning` banner elements adjacent to monitored inputs. | Non-intrusive, real-time feedback while the user is actively typing. |
| **Ticket Type / Field Swaps** | **High** | Programmatically update `<select>` element values and trigger `.trigger('change')` / `.trigger('change.select2')`. | Automates category switching with zero extra effort required from the end-user. |

---

## 5. Proposed Module Architecture & Code Design

### 5.1 Directory Structure
```
src/Modules/TicketGuard/
├── Admin/
│   ├── Page.php                   # Settings page renderer & rule builder UI
│   └── Settings.php               # Data validation & options registration
├── Core.php                       # Business logic, rule processing & sanitization
├── WordPress.php                  # Module initialization, hooks & AJAX endpoints
└── assets/
    ├── css/
    │   ├── admin-rules.css        # Rule builder matrix styling
    │   └── frontend-guard.css     # Inline banners, modal styles & disabled states
    └── js/
        ├── admin-rules.js         # Rule configuration script
        └── frontend-guard.js      # Real-time DOM observer, keyword engine & field swapper
```

### 5.2 Client-Side Execution Flow (`frontend-guard.js`)

```
 [User Types in Subject / Description]
                  │
                  ▼
   [Debounced Observer (250ms Delay)]
                  │
                  ▼
 [Evaluate Active Rules Against Input Text]
                  │
       ┌──────────┴──────────┐
       │ Match Found?        │
       ▼                     ▼
    [ NO ]                [ YES ]
       │                     │
       ├─ Enable Submit      ├─ Action: Disable Submit Button? ───► Disable Button
       ├─ Remove Banners     ├─ Action: Display Inline Banner? ──► Inject Banner
       └─ Close Modals       ├─ Action: Show Modal Prompt? ──────► Trigger Modal
                             └─ Action: Swap Category/Type? ────► Update Select2 & Trigger Change
```

### 5.3 Server-Side Enforcement (Non-JS Fallback & Security)
Client-side validation can be bypassed by power users or disabled JavaScript. To ensure rule integrity:
- Ticket Guard hooks into `wpsc_create_ticket_data` (or `wpsc_before_create_ticket`).
- If a rule specifies a strict submit block and the incoming data matches the keyword rule while retaining the forbidden category ("Other"), the backend strips or rejects the payload, returning a clear error notice.

---

## 6. Admin Interface & Data Schema Design

### 6.1 Rule Data Structure (`stackboost_settings['ticket_guard_rules']`)
```json
[
  {
    "id": "rule_billing_keywords",
    "name": "Steer Billing Keywords away from 'Other'",
    "enabled": true,
    "monitored_fields": ["df_subject", "df_description"],
    "match_mode": "contains_any",
    "keywords": ["refund", "invoice", "overcharge", "credit card", "billing"],
    "trigger_category": "df_category_other",
    "suggested_category": "df_category_billing",
    "actions": {
      "disable_submit": true,
      "show_modal": true,
      "show_inline_warning": true,
      "auto_swap_category": false
    },
    "messaging": {
      "modal_title": "Looking for Billing Support?",
      "modal_body": "It looks like your ticket relates to billing or invoices. Switching to the 'Billing' category ensures faster response times from our accounting team.",
      "inline_warning": "Keywords detected: Consider selecting 'Billing Support' for faster service."
    }
  }
]
```

### 6.2 Sanitization & Core Integration
- Registered in `src/WordPress/Admin/Settings.php` under the whitelist array for `stackboost-ticket-guard`.
- Text input fields and textareas sanitized via `wp_kses_post()` or `sanitize_textarea_field()`.
- Rule IDs and categories sanitized via `sanitize_key()`.

---

## 7. Edge Cases, Technical Risks & Mitigation Strategies

| Edge Case / Risk | Technical Impact | Mitigation Strategy |
| :--- | :--- | :--- |
| **Performance Degradation on Long Inputs** | Real-time regex matching on large description bodies could freeze the UI thread. | Apply a 250ms–300ms `debounce` wrapper on input events. Limit maximum string length evaluated per cycle if text exceeds 10,000 characters. |
| **Conflict with SupportCandy Conditional Fields** | Swapping a category via JS might trigger SupportCandy's native field conditional visibility logic, removing/adding fields dynamically. | Trigger native jQuery `.trigger('change')` on updated select inputs so SupportCandy's event listeners execute naturally and update dependent fields. |
| **False Positive Over-blocking** | Overly aggressive broad keywords (e.g. "pay") might block legitimate non-billing tickets. | Support negative keywords (exclusions), regex boundary matching (`\bkeyword\b`), and permit "Proceed Anyway" overrides in modal settings. |
| **Multiple Matching Rules** | Two rules match different target categories simultaneously. | Assign rule priorities (drag-and-drop ordering) so the highest-priority rule takes precedence. |

---

## 8. Development Effort & Licensing Tier Recommendation

### Effort Estimation
- **Core Architecture & Backend Settings:** 1 Day
- **Frontend DOM Engine, TinyMCE integration & Select2 Swapper:** 1.5 Days
- **Admin Rule Builder Matrix UI:** 1 Day
- **Testing, QA & Documentation:** 1 Day
- **Total Estimated Effort:** ~4.5 Developer Days

### Licensing Tier Recommendation
- **Recommended Tier:** **Pro** or **Business**.
- **Rationale:** Ticket Guard directly drives support team efficiency and intake quality. Placing this module in the **Pro** or **Business** tier aligns with existing premium value drivers (such as *Conditional Views*, *Queue Macro*, and *Unified Ticket Macro*).

---

## 9. Conclusion & Final Assessment

The proposed **Ticket Guard (Intake Optimization System)** module is **100% technically feasible** within the StackBoost for SupportCandy ecosystem.

It leverages existing StackBoost patterns (jQuery event delegation, SelectWoo integration, BEM modal components, `wpsc_create_ticket_data` backend hooks, and centralized admin settings) while solving a critical operational bottleneck for support teams.

**Recommendation:** Proceed with development as a Pro/Business module.
