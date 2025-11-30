# StackBoost - For SupportCandy: User Documentation

## 1. Introduction

StackBoost is a powerful enhancement suite for SupportCandy, offering a collection of modules designed to improve the support experience for both agents and customers.

## 2. Modules Overview

### 2.1. Company Directory

The Company Directory module allows you to manage staff, locations, and departments directly within WordPress.

#### Key Features:
*   **Staff Management:** Add, edit, and organize staff members with details like phone numbers, extensions, and job titles.
*   **User Linking:** Link directory entries to WordPress user accounts.
*   **Private Listings:** Mark entries as "Private" to hide them from the frontend directory while keeping them accessible to agents in the backend and ticket widgets.
*   **Widget Integration:** Display relevant staff contact information directly on the SupportCandy ticket page.
*   **Revision Limits:** Control the number of revisions stored for directory entries to save database space.

#### Configuration:
*   **General Settings:** Navigate to `StackBoost > Directory > Settings`.
    *   **Listing Display Mode:** Choose between 'Page View' or 'Modal View' for frontend listings.
    *   **Revisions to Keep:** Set the number of revisions to keep for Staff, Locations, and Departments (e.g., 5). Set to 0 to disable, or -1 for unlimited.
*   **Contact Widget:** Navigate to `StackBoost > Directory > Contact Widget`.
    *   **Enable Widget:** Toggle the widget on/off.
    *   **Display Fields:** Select which fields to show (e.g., Name, Phone, Email).
    *   **Photo Options:** You can now display the staff photo as a "Photo (Thumbnail)" or "Photo (Link)". Both open a full-size image in a modal popup when clicked.

### 2.2. After Hours Notice

Displays a warning message to customers when they attempt to create a ticket outside of your business hours.

*   **Configuration:** Go to `StackBoost > After-Hours Notice`.
*   **Settings:** Define your business hours, weekends, and holidays. Customize the message displayed to users.

### 2.3. After Ticket Survey

Automatically sends a survey to customers after a ticket is closed.

*   **Configuration:** Go to `StackBoost > After Ticket Survey`.
*   **Questions:** Create custom questions (Rating, Text, etc.).
*   **Reporting:** View survey results and technician performance.

### 2.4. Conditional Views

Hide or show specific columns or views in the SupportCandy ticket list based on rules.

*   **Configuration:** Go to `StackBoost > Conditional Views`.
*   **Rule Builder:** Create rules to hide columns based on conditions (e.g., "Hide 'Assignee' if 'Status' is 'Closed'").

### 2.5. Queue Macro

Adds a `{{queue_count}}` macro to email notifications, showing the customer their position in the queue.

*   **Configuration:** Go to `StackBoost > Queue Macro`.
*   **Settings:** Define which statuses constitute the "queue" and which ticket field determines the queue type (e.g., Category or Department).

### 2.6. Unified Ticket Macro

Replaces multiple email macros with a single `{{stackboost_unified_ticket}}` macro that generates a clean, customizable HTML summary of the ticket.

*   **Configuration:** Go to `StackBoost > Unified Ticket Macro`.
*   **Customization:** Select which fields to include in the summary and reorder them.

## 3. General Settings & Tools

*   **Diagnostic Log:** Located under `StackBoost > Tools`. Enable this only when requested by support to capture detailed debug information.
*   **Date & Time Formatting:** Located under `StackBoost > Date & Time`. Customize how dates are displayed across SupportCandy.

## 4. Support

If you encounter any issues, please check the Diagnostic Log for errors or contact support.
