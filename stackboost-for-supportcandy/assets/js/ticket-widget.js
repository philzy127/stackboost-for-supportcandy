jQuery(document).ready(function($) {
    // Check if our localized settings object exists.
    if (typeof stackboostWidgetSettings === 'undefined') {
        return;
    }

    const myWidget = $('#stackboost-directory-pseudo-widget');
    const targetWidgetSelector = '.wpsc-itw-' + stackboostWidgetSettings.targetWidget;
    const targetWidget = $(targetWidgetSelector);

    // Ensure both our widget and the target widget exist before trying to move anything.
    if (myWidget.length && targetWidget.length) {

        // Move the widget into the correct position.
        if (stackboostWidgetSettings.position === 'before') {
            myWidget.insertBefore(targetWidget);
        } else {
            myWidget.insertAfter(targetWidget);
        }

        // Make the widget visible.
        myWidget.show();
    }
});