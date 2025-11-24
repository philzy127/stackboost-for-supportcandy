# User Guide: After Ticket Survey

This module allows you to collect feedback from users after their tickets are resolved.

## 1. Setup

### Shortcode
Create a WordPress page and add the following shortcode. This page will host the survey form.

```
[stackboost_after_ticket_survey]
```

### Configuration
Go to **StackBoost > After Ticket Survey**.

*   **Survey Page Background Color:** Customize the background color of the survey page to match your branding.
*   **Ticket Number Question:** Select the survey question that asks for the Ticket ID.
*   **Technician Question:** Select the survey question that asks for the Technician's name.
*   **Ticket System Base URL:** Enter the URL of your ticket system (e.g., `https://yoursite.com/support/ticket/`). This is used to link back to the ticket from the survey report.

## 2. Sending Surveys

To send a survey:
1.  Configure a **SupportCandy Email Notification** (e.g., "Ticket Closed").
2.  Include a link to your survey page in the email body.
3.  Pass the ticket ID and Technician ID as URL parameters (if your survey plugin supports pre-filling).
    *   *Note:* This integration primarily focuses on **displaying** the survey interface properly via the shortcode and linking the results. The actual email trigger is handled by SupportCandy's native notification system.

## 3. Viewing Results
Go to the **Results** tab in the settings page to view submitted surveys.
