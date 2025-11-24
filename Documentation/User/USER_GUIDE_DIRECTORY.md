# User Guide: Company Directory Module

The **Company Directory** module provides a complete solution for managing staff profiles, locations, and departments within WordPress. It integrates with SupportCandy to display contact information directly on tickets.

## 1. Overview

*   **Staff Directory:** Create profiles for employees with contact info, location, and department.
*   **Frontend Display:** Show a searchable staff list on your intranet or public site.
*   **Ticket Integration:** Automatically show a "Contact Information" widget on SupportCandy tickets when a staff member submits a request.

## 2. Management

Go to **StackBoost > Company Directory**.

### Staff
*   **Add New:** Create a new staff profile.
    *   **Fields:** Name, Job Title, Email, Phone Extensions, Location, Department.
*   **Edit:** Click on any staff member to update their details.

### Locations & Departments
*   **Locations:** Manage building names or office locations (e.g., "Main Office", "Warehouse").
*   **Departments:** Manage internal departments (e.g., "IT", "HR", "Sales").
*   *Note:* Assigning staff to these allows for better filtering and organization.

### Management Tools
*   **Import:** Upload a CSV file to bulk-import staff members.
*   **Clear Data:** (Admins only) Delete all staff data to start fresh.

## 3. Frontend Directory

To display the directory on a WordPress page, use the following shortcode:

```
[stackboost_directory]
```

### Features
*   **Search:** Real-time search by name, email, or job title.
*   **Sort:** Click column headers to sort.
*   **Details:** Clicking a name opens a detailed view (Modal or Page, configured in Settings).

## 4. Ticket Integration (Contact Widget)

When a user submits a ticket in SupportCandy, this module checks if their email address matches a Staff Profile.

*   **Match Found:** A "Contact Information" widget appears in the ticket sidebar (or chosen location).
*   **Content:** Displays Name, Title, Phone, Location, and Department.
*   **Edit Link:** Admins see an "Edit" icon to quickly update the staff member's profile directly from the ticket.

## 5. Settings

Go to **StackBoost > Company Directory > Settings**.

*   **Listing Display Mode:** Choose between opening details in a **Modal** (Popup) or a separate **Page**.
*   **Default Photo:** Set a fallback image for staff without photos.
*   **Contact Widget:** Enable/Disable the widget and choose its position (e.g., Sidebar Top/Bottom).
