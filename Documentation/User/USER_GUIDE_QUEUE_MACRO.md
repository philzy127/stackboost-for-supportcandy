# User Guide: Queue Macro

The **Queue Macro** module allows you to inform customers of their position in the support queue via automated emails.

## 1. The Macro

Use the following tag in your "New Ticket" auto-responder email template:

```
{{queue_count}}
```

**Example Usage:**
> "Thank you for your request. You are currently #{{queue_count}} in the queue."

## 2. Configuration

Go to **StackBoost > Queue Macro Settings**.

*   **Enable Feature:** Toggle the functionality on or off.
*   **Ticket Type Field:** Select the field that separates your queues.
    *   *Example:* If you select "Priority", a "High" priority ticket will be counted against other "High" priority tickets.
*   **Non-Closed Statuses:** Select the statuses that are considered "Active" in the queue.
    *   *Typically:* Open, In Progress, On Hold.
    *   *Excluded:* Closed, Resolved.

## 3. How it Works
When a new ticket is created:
1.  The system identifies the value of the "Ticket Type Field" for the new ticket (e.g., Priority = High).
2.  It counts how many *other* tickets exist with that same value (High) and are in one of the "Non-Closed Statuses".
3.  It adds 1 (for the new ticket) and replaces `{{queue_count}}` with the total.
