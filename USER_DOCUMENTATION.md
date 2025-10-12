# StackBoost Company Directory - User Manual

## Introduction

Welcome to the StackBoost Company Directory module! This guide provides a comprehensive overview of how to manage and display your organization's staff directory directly within the StackBoost for SupportCandy plugin.

## Accessing the Company Directory

You can find the Company Directory management page by navigating to **StackBoost > Company Directory** in your WordPress admin sidebar.

![StackBoost Menu](https://i.imgur.com/your-screenshot.png) *<-- Placeholder for a screenshot of the menu*

This will take you to the main directory page, which features a tabbed interface for managing all aspects of your directory.

## Managing Staff

The **Staff** tab is the primary view where you will see a list of all your staff members.

### Adding a New Staff Member

1.  Click the **Add New** button at the top of the Staff tab.
2.  Fill in the staff member's details:
    *   **Name:** The full name of the staff member (this is the only required field).
    *   **Staff Details:** Fill in the contact information, location, department, and job title in the "Staff Details" section.
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

The **Import** tab provides a powerful tool for bulk-adding new staff members from a CSV file.

### CSV File Format

Your CSV file **must** have the following columns in the header row:

`Name,Office Phone,Extension,Mobile Phone,Location,Room #,Department / Program,Title,Email Address,Active`

*   **Name:** This is the only required field.
*   **Location & Department:** If a location or department in your CSV does not already exist, the importer will create it for you.
*   **Active:** Use `Yes` or `1` for active entries, and `No` or `0` for inactive entries.

**Important:** The importer **only adds new entries**; it does not update existing ones.

## Clearing Data

The **Clear Data** tab provides a way to permanently delete **all** staff directory entries at once. This is a destructive action and cannot be undone. You will be asked to type "DELETE" to confirm.

## Displaying the Directory on Your Website

To display the staff directory on a public-facing page, simply add the following shortcode to any page or post:

`[stackboost_directory]`

This will render a searchable and sortable table of all your **active** staff members.