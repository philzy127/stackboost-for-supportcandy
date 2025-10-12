# StackBoost - For SupportCandy: User Documentation

Welcome to the official user guide for **StackBoost - For SupportCandy**. This plugin is a comprehensive enhancement suite designed to supercharge the popular SupportCandy helpdesk plugin for WordPress. It adds powerful new features and quality-of-life improvements to streamline your support workflow, empower your agents, and provide a better experience for your users.

This document provides a detailed, user-focused overview of every feature included in the plugin. It explains what each feature does, how to configure its settings, and how it will affect the behavior and display of your support system.

---

## 1. After Hours Notice

**Purpose:** This feature displays a customizable notice at the top of the "Create Ticket" form when a user is visiting outside of your defined business hours, on a weekend, or on a designated holiday. This is useful for managing user expectations about response times.

**How it Works:** The plugin checks the current time (using your WordPress site's timezone) against the settings you configure. If the current time falls within the "after hours" window, the notice you created is displayed to the user.

**Configuration:**

You can find the settings for this module under `StackBoost > After Hours Notice` in your WordPress admin dashboard.

*   **Enable Feature:** You must check this box to activate the After Hours Notice functionality.
*   **After Hours Start (24h):** Enter the hour your business day ends and "after hours" begins. This uses a 24-hour format (e.g., `17` for 5:00 PM).
*   **Before Hours End (24h):** Enter the hour your business day resumes. For example, if your support hours are 8 AM to 5 PM, you would set "After Hours Start" to `17` and "Before Hours End" to `8`.
*   **Include All Weekends:** If checked, the notice will be displayed all day on Saturdays and Sundays, regardless of the time settings.
*   **Holidays:** List any company holidays, one per line, in `MM-DD-YYYY` format (e.g., `12-25-2024`). The notice will be displayed for the entire day on these dates.
*   **After Hours Message:** This is the message that will be shown to your users. You can use the WordPress editor to format the text, add links, or include other basic HTML.

**User Impact:** When a user visits the "Create Ticket" page during the times you've specified, they will see your message displayed prominently above the form. This helps prevent frustration by letting them know that their request may not be seen immediately.

---

## 2. After Ticket Survey

**Purpose:** This feature allows you to gather valuable feedback from your users by creating a survey that they can fill out. You can create a dedicated survey page and link to it in your support emails or other communications.

**How it Works:** The module provides a shortcode to display a survey form on any WordPress page. You build the survey by creating questions in the admin area. When a user submits the survey, their answers are stored in the database for you to review.

**Configuration:**

The admin interface for this module is located at `StackBoost > After Ticket Survey`. It is organized into several tabs.

### How to Use (Tab)

This tab provides a quick overview of the feature and explains the basic steps to get started:
1.  **Create Questions:** Go to the "Manage Questions" tab to build your survey.
2.  **Add the Shortcode:** Create a new WordPress page and add the `[stackboost_after_ticket_survey]` shortcode to its content.
3.  **Configure Settings:** Go to the "Settings" tab to link survey questions to ticket data.
4.  **Link to the Survey:** Share the link to your new survey page with users (e.g., in the "Ticket Closed" email template).

### Manage Questions (Tab)

This is where you build your survey. You can add, edit, and delete questions.

*   **Question Types:**
    *   `Short Text`: A single-line text input field.
    *   `Long Text`: A multi-line textarea for longer answers.
    *   `Rating`: A 1-to-5 star rating scale.
    *   `Dropdown`: A dropdown select field. You must provide a comma-separated list of options for the user to choose from.
*   **Is Required:** Check this box to make the question mandatory.
*   **Sort Order:** Controls the order in which questions appear on the form.

### Manage Submissions (Tab)

This tab lists all the individual survey submissions received. You can view the date of each submission and delete any that are no longer needed.

### View Results (Tab)

This tab provides an aggregated view of all survey responses. It's a powerful way to see trends in the feedback you receive. You can click on individual submissions to see all of a user's answers in a convenient modal window.

### Settings (Tab)

This tab allows you to connect your survey to the SupportCandy ticket system for more advanced functionality.

*   **Survey Page Background Color:** Customize the background color of your survey page to match your site's branding.
*   **Ticket Number Question:** Select the "Short Text" question from your survey that you use to ask for the ticket number. This allows the results page to link directly to the relevant ticket.
*   **Technician Question:** Select the "Dropdown" question that you use to ask about the support agent.
*   **Ticket System Base URL:** Enter the base URL for your ticket view page (e.g., `https://support.example.com/ticket/`). This is used to construct the direct links from the results page.

**User Impact:** Users can easily provide feedback on their support experience. By pre-filling information like the ticket number and technician's name using URL parameters (e.g., `your-survey-page/?ticket_id=123&tech=Jules`), you can make the process even smoother for them.

---

## 3. Conditional Views

**Purpose:** This feature gives you granular control over the SupportCandy ticket list, allowing you to change which columns are visible depending on the selected ticket view (or "filter"). This helps agents focus on the information that is most relevant to a specific queue.

**How it Works:** You create a set of rules that are applied in real-time as an agent switches between different ticket views in the frontend. The plugin uses JavaScript to dynamically show or hide the columns based on the rules you've defined for the active view.

**Configuration:**

You can find the settings for this module under `StackBoost > Conditional Views` in your WordPress admin dashboard.

*   **Enable Feature:** You must check this box to activate the rule-based system.
*   **Rules:** This is the core of the feature. You can build a list of rules to control column visibility.
    *   **Add New Rule:** Click this button to add a new, empty rule to the list.
    *   **Remove Rule:** Click the `X` button on the right side of a rule to delete it.

**Building a Rule:**

Each rule consists of four parts that work together like a sentence:

1.  **Action:**
    *   `SHOW`: Makes the selected column visible.
    *   `SHOW ONLY`: Hides all other columns *except* the one selected. This is a powerful way to create a very focused view.
    *   `HIDE`: Makes the selected column invisible.
2.  **Column:** The specific ticket list column that the action will apply to (e.g., "Priority", "Status", or a custom field).
3.  **Condition:**
    *   `WHEN IN VIEW`: The action will trigger when the agent is looking at the selected view.
    *   `WHEN NOT IN VIEW`: The action will trigger when the agent is looking at *any other view*.
4.  **View:** The SupportCandy ticket view/filter that the rule targets. This list is populated automatically from your SupportCandy settings.

**Example Rule:**

Let's say you want to hide the "Customer" column when looking at the "Billing" queue. Your rule would be:

`HIDE` `Customer` `WHEN IN VIEW` `Billing`

**User Impact:** This feature directly benefits support agents by de-cluttering the ticket list. It allows you to create highly specialized workspaces for different teams or support functions, ensuring that agents see only the data they need to do their jobs effectively.

---

## 4. Quality of Life (QOL) Enhancements

**Purpose:** This module is a collection of smaller, agent-focused improvements designed to make working with the SupportCandy ticket list faster and more intuitive.

**How it Works:** These features are primarily implemented in JavaScript and modify the behavior and appearance of the ticket list page in real-time.

**Configuration:**

You can find the settings for these features on the main plugin settings page at `StackBoost > StackBoost`.

### Ticket Details Card

*   **What it does:** Allows an agent to right-click on any ticket in the list to instantly see a pop-up card with the full ticket details, including the initial message. This avoids the need to open the ticket in a new tab just to get context.
*   **Setting:** `Enable Feature` under the "Ticket Details Card" section.
*   **User Impact:** This is a major time-saver for agents, as it allows them to quickly triage or reference tickets without leaving the main list view.

### General Cleanup

*   **Hide Empty Columns:**
    *   **What it does:** Automatically detects and hides any column in the ticket list that has no data for any of the currently visible tickets. For example, if no tickets in the current view have an "Assigned Agent", that column will be hidden to save space.
    *   **Setting:** `Hide Empty Columns`
*   **Hide Priority Column:**
    *   **What it does:** If all tickets currently visible in the list have a priority of "Low", the entire "Priority" column will be hidden. This is useful if you primarily deal with low-priority tickets and don't need the column taking up space.
    *   **Setting:** `Hide Priority Column`
*   **User Impact:** These cleanup features create a more dynamic and less cluttered interface for agents. The ticket list adapts to the data being shown, maximizing the use of screen real estate.

### Hide Ticket Types from Non-Agents

*   **What it does:** This feature allows you to hide certain ticket "types" or "categories" from the dropdown menu when a non-agent user is creating a new ticket. This is useful for internal-only categories that customers should not be able to select.
*   **How it Works:** You specify the name of the custom field that you use for your ticket types. Then, you provide a list of the specific types that should be hidden from users.
*   **Settings:**
    *   `Enable Feature`: Activates this functionality.
    *   `Custom Field Name`: Select the SupportCandy custom field that acts as your ticket category selector.
    *   `Ticket Types to Hide`: List the exact names of the categories you want to hide, with one name per line.
*   **User Impact:** This ensures that customers can only select from a curated list of ticket categories, preventing them from selecting internal-only types and reducing ticket miscategorization.

---

## 5. Queue Macro

**Purpose:** This feature provides a `{{queue_count}}` macro that you can use in your SupportCandy email templates. It tells a user how many other tickets are in the queue ahead of theirs, helping to manage expectations for response times.

**How it Works:** When a new ticket is created, the plugin intercepts the outgoing email notification. It finds the `{{queue_count}}` macro and replaces it with a number calculated based on your settings. The calculation counts the number of existing tickets that have the same "type" (e.g., the same category) and are in a "non-closed" status.

**Configuration:**

You can find the settings for this module under `StackBoost > Queue Macro`.

*   **Enable Feature:** You must check this box to activate the macro replacement.
*   **Ticket Type Field:** This is the most important setting. You must choose which SupportCandy field defines your queues. For example, if your support is divided by department, you might choose the "Category" field. If it's divided by urgency, you might choose "Priority". The macro will count other tickets that have the *same value* in this field.
*   **Non-Closed Statuses:** Select all the statuses that represent an "open" or "active" ticket. The macro will only count tickets that are in one of these selected statuses. You can use the dual-list box to move statuses from "Available" to "Selected".
*   **Test Queue Counts:** After saving your settings, you can click the "Run Test" button. This will show you the current count for every queue based on your configuration, which is a great way to verify that your logic is correct.

**How to Use the Macro:**

1.  Go to the SupportCandy email notification settings (`SupportCandy > Email Notifications`).
2.  Edit the template for "New Ticket Auto-Reply" (or any other template where you want to show the queue position).
3.  Add the `{{queue_count}}` macro to the body of the email.

**Example Email Body:**

> Hello,
>
> We have received your request (Ticket #{{ticket_id}}).
> You are currently number **{{queue_count}}** in the queue.
>
> An agent will get back to you as soon as possible.

**User Impact:** When a user submits a ticket, they receive an immediate, automated confirmation that includes their position in the queue. This transparency can significantly improve the user experience and reduce follow-up inquiries asking for a status update.