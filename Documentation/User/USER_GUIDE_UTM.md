# User Guide: Unified Ticket Macro

The **Unified Ticket Macro (UTM)** simplifies email notifications by providing a single, customizable macro that outputs a table of ticket details.

## 1. The Macro

Use the following tag in any SupportCandy Email Notification template (Subject or Body):

```
{{stackboost_unified_ticket}}
```

When the email is sent, this tag will be replaced with a clean HTML table containing the ticket fields you have configured.

## 2. Configuration

Go to **StackBoost > Unified Ticket Macro**.

### General Settings
*   **Enable Feature:** Toggle the functionality on or off.

### Fields to Display
*   **Selection:** Use the dual-list box to choose which fields to include in the table.
    *   **Left:** Available SupportCandy fields.
    *   **Right:** Selected fields.
*   **Ordering:** Drag and drop fields in the "Selected" list to change their order in the email table.
*   **Use SupportCandy Order:** Check this to ignore manual sorting and use the global field order defined in SupportCandy settings.

### Renaming Rules
*   **Custom Labels:** You can rename fields specifically for the email output without changing their name in the actual ticket form.
    *   *Example:* Rename "ID" to "Ticket #" or "cust_12" to "Computer Model".
    *   Click "Add Rule", select the field, and type the new name.
