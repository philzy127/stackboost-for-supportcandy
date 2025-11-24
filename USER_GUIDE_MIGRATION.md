# User Guide: Directory Migration Tool

This tool is designed for a **one-time migration** of data from the legacy "CHP Staff Directory" plugin to the new StackBoost Directory module.

## Usage

1.  Go to **StackBoost > Directory Integration**.
2.  **Import Staff:** Click the button to import staff members from the legacy table to the new `stkb_staff_dir` Custom Post Type.
3.  **Import Locations:** Click the button to import locations to the new `stkb_location` CPT.
4.  **Import Departments:** Click the button to import departments to the new `stkb_department` CPT.

## Important Notes
*   **Backup:** Always backup your database before running a migration.
*   **One-Way:** This process copies data *from* the old system *to* the new one. It does not sync them.
*   **Duplicates:** The tool attempts to prevent duplicates, but running it multiple times is not recommended without clearing the new data first.
