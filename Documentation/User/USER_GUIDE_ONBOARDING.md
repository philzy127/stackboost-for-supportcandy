# User Guide: Onboarding Dashboard Module

The **Onboarding Dashboard** module provides a streamlined interface for managing employee onboarding sessions directly within WordPress. It integrates with **SupportCandy** tickets to display a list of new hires ("Attendees") and guides the facilitator through a sequence of onboarding steps.

## 1. Getting Started

### Prerequisites
*   **SupportCandy** plugin must be installed and active.
*   **StackBoost - For SupportCandy** plugin must be active.

### The Shortcode
To display the Onboarding Dashboard on the frontend (e.g., for use by IT staff or HR during a presentation), create a WordPress page and add the following shortcode:

```
[stackboost_onboarding_dashboard]
```

This page will serve as the "Presentation View" for the onboarding session.

---

## 2. Configuration

Before using the dashboard, you must configure how it identifies "Onboarding" tickets. Go to **StackBoost > Onboarding Dashboard > Settings**.

### General Configuration
*   **Request Type Field:** Select the SupportCandy custom field that identifies the type of ticket (e.g., "Request Type" or "Category").
*   **Onboarding Option:** Select the specific value that represents an Onboarding ticket (e.g., "New Hire Onboarding").
    *   *Note: Only tickets with this specific value will appear in the dashboard.*
*   **Inactive Statuses:** Select ticket statuses that should be hidden (e.g., "Closed", "Cancelled").
*   **Onboarding Date Field:** (Optional) The date field representing the start date. Used to sort attendees into "Previous", "This Week", and "Future" lists.
*   **Onboarding Cleared Field:** (Optional) A checkbox field indicating if the user has been cleared for work.

### Phone Configuration
This section controls how phone numbers are displayed in the Staff list.

*   **Mode:**
    *   **Single Field:** Use this if you have one main phone number field. You can optionally link a "Type" field to show a mobile icon when the type matches "Mobile".
    *   **Multiple Fields:** Use this if you have separate fields for different phone types (e.g., "Work Phone", "Cell Phone").
*   **Icon Mapping:** The system automatically displays icons based on the phone type:
    *   **Mobile:** Smartphone Icon
    *   **Work/Office:** Building Icon
    *   **Home:** House Icon
    *   **Fax:** Printer Icon
    *   **Generic:** Standard Phone Icon

### Staff Table Columns
Use the dual-list selector to choose which SupportCandy fields to display as columns in the backend Staff list.
*   **Left Box:** Available Fields.
*   **Right Box:** Selected Fields (Drag or use arrows to reorder).

---

## 3. Managing Onboarding Steps

Onboarding "Steps" are the slides or pages shown during the presentation.

### Managing Steps
Go to **StackBoost > Onboarding Dashboard > Steps**.
*   **Add Step:** Click "Add New Step" to create a slide.
*   **Reorder:** Drag and drop steps in the list to change their presentation order.
*   **Edit/Delete:** Use the buttons on each step card.

### Step Content
When editing a step, you have two main content areas:
1.  **Checklist Items:** A list of tasks the facilitator must complete or mention.
    *   *Tip:* Add tooltips by putting text in brackets at the end of a line: `Explain Login [Must use secure password]`.
2.  **Notes Content:** Rich text content (images, links, paragraphs) visible to the facilitator for reference.

---

## 4. Import / Export

You can move your onboarding steps between sites (e.g., Staging to Production) using the **Import / Export** tab.
*   **Export:** Downloads a JSON file containing all steps, checklists, and notes.
*   **Import:** Uploads a JSON file.
    *   *Note:* The importer supports legacy data formats from previous versions of the dashboard.
    *   **Important:** Importing steps does **not** import your General Settings (Phone logic, Ticket ID mapping). You must re-configure the "Settings" tab after importing to match your new site's SupportCandy fields.

---

## 5. Running a Session

1.  Navigate to the frontend page containing the `[stackboost_onboarding_dashboard]` shortcode.
2.  **Preview Screen:** You will see a list of "Expected Attendees" (Onboarding tickets scheduled for this week).
3.  **Start:** Click "Begin Onboarding".
4.  **Navigation:** Use "Next Step" and "Back" buttons.
    *   *Note:* The "Next Step" button is disabled until you check off all items in the **Checklist**.
5.  **Completion:** At the final step, you can mark any attendees who were **absent**.
6.  **Certificates:** Click "Send Completion Certificates" to generate PDFs and attach them to the attendees' tickets in SupportCandy.
