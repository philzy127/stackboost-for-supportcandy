# User Guide: Ticket View Enhancements

This module provides various "Quality of Life" improvements to the SupportCandy ticket list interface.

## Configuration

Go to **StackBoost > Ticket View**.

## Features

### 1. Ticket Details Card
*   **What it does:** Allows you to right-click on any ticket in the ticket list to see a quick summary card.
*   **Benefits:** Quickly view details without navigating away from the list.

### 2. General Cleanup
*   **Hide Empty Columns:** Automatically hides columns in the ticket list if they have no data for any of the currently visible tickets.
    *   *Example:* If you have a "Computer ID" column, but none of the 20 tickets on the page have a Computer ID, the column is removed to save space.
*   **Hide Priority Column:** Hides the "Priority" column if all visible tickets have "Low" priority (or the default lowest priority). Reduces visual clutter.

### 3. Hide Ticket Types
*   **What it does:** Hides specific options in the "Ticket Category" (or other type field) dropdown menu for non-agent users.
*   **Usage:**
    1.  Select the **Custom Field** that represents the ticket type.
    2.  Enter the **Values to Hide** (one per line) in the text box.
    3.  These options will now be invisible to regular users submitting tickets, but visible to agents.
