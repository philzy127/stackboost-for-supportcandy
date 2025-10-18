# StackBoost - For SupportCandy: Directory Module - User Manual

## Introduction

Welcome to the Directory module for StackBoost - For SupportCandy! This guide provides a comprehensive overview of how to manage and display your organization's staff directory.

## Accessing the Company Directory

You can find the Company Directory management page by navigating to **StackBoost > Directory** in your WordPress admin sidebar.

![StackBoost Menu](https://i.imgur.com/your-screenshot.png) *<-- Placeholder for a screenshot of the menu*

This will take you to the main directory page, which features a tabbed interface for managing all aspects of your directory.

## Managing Staff

The **Staff** tab is the primary view where you will see a list of all your staff members.

### Adding a New Staff Member

1.  Click the **Add New** button at the top of the Staff tab.
2.  Fill in the staff member's details:
    *   **Name:** The full name of the staff member (this is the only required field).
    *   **Staff Details:** Fill in the contact information, location, department, and job title in the "Staff Details" section. The **Office Phone** and **Mobile Phone** fields will be automatically formatted for you as you type.
    *   **Staff Photo:** Use the "Featured Image" box on the right to upload a photo.
3.  Click **Publish** to save the new entry.

### Editing a Staff Member

To edit an existing entry, simply hover over their name in the list and click the **Edit** link.

### Trashing and Deleting Staff

*   **To Trash an Entry:** Hover over a staff member's name and click the **Trash** link. You can also select multiple entries using the checkboxes and choose "Trash" from the "Bulk Actions" dropdown.
*   **To View Trashed Entries:** Click the **Trash** link at the top of the list table.
*   **To Restore or Permanently Delete:** When viewing the trash, you will see options under each entry to **Restore** it to the main list or **Delete Permanently**.

## Managing Locations and Departments

The **Locations** and **Departments** tabs work just like the Staff tab. You can add, edit, and trash items in the same way. This allows you to pre-populate the dropdown menus that are available when adding or editing a staff member.

## Importing Data via CSV

The **Management** tab contains a tool for bulk-adding new staff members from a CSV file.

### CSV File Format

Your CSV file **must** have a header row, and the columns must be in the following order:

1.  `Name`
2.  `Email`
3.  `Office Phone`
4.  `Extension`
5.  `Mobile Phone`
6.  `Job Title`
7.  `Department/Program`

*   **Name:** This is the only required field. If a name is missing, that row will be skipped.
*   **Phone Numbers:** Any special characters (like parentheses or dashes) will be automatically stripped out. Only the digits will be stored.

**Important:** The importer **only adds new entries**; it does not update existing ones.

## Settings

The **Settings** tab allows you to configure how the directory is displayed and who can manage it.

*   **Listing Display Mode:** Choose how individual staff listings are displayed on the front-end directory. "Page View" opens the details on a new page, while "Modal View" opens them in a pop-up window on the same page.
*   **Editing Permissions:** Select the user roles that should be allowed to create, edit, and delete staff, locations, and departments.
*   **Management Tab Access:** Select the user roles that should have access to the powerful tools in the Management tab.

## Clearing Data

The **Clear Data** tab provides a way to permanently delete **all** staff directory entries at once. This is a destructive action and cannot be undone. You will be asked to type "DELETE" to confirm.

## Displaying the Directory on Your Website

To display the staff directory on a public-facing page, simply add the following shortcode to any page or post:

`[stackboost_directory]`

This will render a searchable and sortable table of all your **active** staff members.