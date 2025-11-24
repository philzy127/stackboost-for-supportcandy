# User Guide: Conditional Views

**Conditional Views** allows you to dynamically show or hide columns in the SupportCandy ticket list based on the currently selected Filter (View).

## Why use this?
Different teams need to see different data.
*   **IT Team:** Needs to see "Computer Model" and "IP Address".
*   **HR Team:** Needs to see "Employee ID" and "Department".
*   **Default View:** Needs to remain clean.

Instead of showing all columns to everyone, you can create rules to adapt the table.

## Configuration

Go to **StackBoost > Conditional Views**.

### Creating Rules

1.  **Enable Feature:** Toggle the system on.
2.  **Add New Rule:** Click the button to create a rule row.
3.  **Configure Rule:**
    *   **Action:**
        *   **SHOW:** Force the column to appear.
        *   **SHOW ONLY:** Show *only* this column (and others with this rule), hiding everything else not explicitly shown.
        *   **HIDE:** Remove the column.
    *   **Column:** Select the column you want to control.
    *   **Condition:**
        *   **WHEN IN VIEW:** Apply this rule when the user selects the specified view.
        *   **WHEN NOT IN VIEW:** Apply this rule when the user selects *any other* view.
    *   **View:** Select the SupportCandy Filter (View) that triggers the rule.

### Example
*   **Action:** HIDE
*   **Column:** "Computer Model"
*   **Condition:** WHEN NOT IN VIEW
*   **View:** "IT Requests"

*Result:* The "Computer Model" column will be hidden everywhere **except** when the user selects the "IT Requests" filter.
